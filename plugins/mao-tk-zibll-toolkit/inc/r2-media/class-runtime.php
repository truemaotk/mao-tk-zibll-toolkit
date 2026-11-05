<?php
namespace MaoTK\ZibllToolkit\R2Media;

defined('ABSPATH') || exit;

final class Runtime
{
    public const MODULE = 'r2-media';
    private const ROUTE = '/maotk-media/v1/source/';
    private static $lookup = array();
    private static $queued = array();
    private static $source = array();

    public static function register(): void
    {
        add_action(Sync::ITEM, array(Sync::class, 'run'), 10, 5);
        add_action(Sync::UPLOAD, array(Sync::class, 'run_upload'), 10, 3);
        add_action(Sync::SCAN, array(Sync::class, 'scan'));
        add_filter('cron_schedules', array(Sync::class, 'schedule_interval'));
        add_action(Sync::BULK_STEP, array(Sync::class, 'bulk_step'));
        Sync::ensure_bulk_schedule();
        add_action(Sync::PURGE, array(Sync::class, 'purge'), 10, 2);
        add_action('delete_attachment', array(Sync::class, 'forget'));
        add_action('rest_api_init', array(self::class, 'register_source'));
        add_filter('rest_pre_serve_request', array(self::class, 'serve_source'), 10, 3);
        add_filter('template_include', array(self::class, 'start_render'), PHP_INT_MAX);
        add_filter('wp_update_attachment_metadata', array(self::class, 'on_upload'), 100, 2);
        if (Settings::storage() && !wp_next_scheduled(Sync::SCAN)) {
            Sync::schedule_scan();
        }
    }

    public static function stop(): void
    {
        $bulk = get_option(Sync::BULK, array());
        if (!empty($bulk['managed'])) {
            Sync::set_paused($bulk['job'], true);
        } else {
            Sync::interrupt_bulk();
        }
        foreach (array(Sync::SCAN, Sync::ITEM, Sync::UPLOAD, Sync::PURGE, Sync::BULK_STEP) as $hook) {
            wp_unschedule_hook($hook);
        }
    }

    public static function on_upload($metadata, int $id)
    {
        if (Settings::storage() && in_array(get_post_mime_type($id), Files::TYPES, true)) {
            Sync::enqueue($id, false, 0, '', Sync::UPLOAD);
        }
        return $metadata;
    }

    public static function start_render($template)
    {
        if (is_admin() || is_user_logged_in() || is_preview() || is_feed() || is_404() || is_embed()
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !class_exists('WP_HTML_Tag_Processor') || !Settings::storage()) {
            return $template;
        }
        if (is_singular()) {
            $post = get_queried_object();
            if (!$post instanceof \WP_Post || $post->post_status !== 'publish' || $post->post_password !== ''
                || (function_exists('zib_get_post_allow_view_data') && empty(zib_get_post_allow_view_data($post)['allow']))) {
                return $template;
            }
        }
        ob_start(array(self::class, 'rewrite_html'));
        return $template;
    }

    public static function rewrite_html(string $html): string
    {
        $status = http_response_code();
        if (($status && $status !== 200) || strlen($html) > 4 * 1024 * 1024 || stripos($html, '<img') === false) {
            return $html;
        }
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Length:') === 0 || (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false)) {
                return $html;
            }
        }
        $storage = Settings::storage();
        if (!$storage) {
            return $html;
        }
        $destination = $storage->destination();
        $origin = Settings::get('MAOTK_MEDIA_REWRITE') === '1' && Settings::verified($destination) ? Settings::origin() : '';
        try {
            $tags = new \WP_HTML_Tag_Processor($html);
            while ($tags->next_tag()) {
                $name = $tags->get_tag();
                if ($name === 'A' && is_string($tags->get_attribute('box-img'))) {
                    $value = $tags->get_attribute('box-img');
                    $next = self::rewrite_url($value, 0, $destination, $origin);
                    if ($next !== $value) {
                        $tags->set_attribute('box-img', $next);
                    }
                    continue;
                }
                if ($name !== 'IMG' && $name !== 'SOURCE') {
                    continue;
                }
                $hint = 0;
                if (preg_match('/(?:^|\s)wp-image-(\d+)(?=\s|$)/', (string) $tags->get_attribute('class'), $match)) {
                    $hint = (int) $match[1];
                }
                foreach (array('src', 'data-src', 'data-original', 'srcset', 'data-srcset') as $attribute) {
                    $value = $tags->get_attribute($attribute);
                    if (!is_string($value) || $value === '') {
                        continue;
                    }
                    $next = strpos($attribute, 'srcset') !== false
                        ? self::rewrite_srcset($value, $hint, $destination, $origin)
                        : self::rewrite_url($value, $hint, $destination, $origin);
                    if ($next !== $value) {
                        $tags->set_attribute($attribute, $next);
                    }
                }
            }
            return $tags->get_updated_html();
        } catch (\Throwable $error) {
            return $html;
        }
    }

    private static function rewrite_srcset(string $value, int $hint, string $destination, string $origin): string
    {
        if (stripos($value, 'data:') !== false) {
            return $value;
        }
        $entries = explode(',', $value);
        foreach ($entries as &$entry) {
            if (preg_match('/\A(\s*)(\S+)(.*)\z/s', $entry, $parts)) {
                $entry = $parts[1] . self::rewrite_url($parts[2], $hint, $destination, $origin) . $parts[3];
            }
        }
        unset($entry);
        return implode(',', $entries);
    }

    private static function rewrite_url(string $url, int $hint, string $destination, string $origin): string
    {
        $relative = Files::relative_url($url);
        if ($relative === '') {
            return $url;
        }
        $id = self::attachment_id($relative, $hint);
        if (!$id || !Files::eligible($id)) {
            return $url;
        }
        $entry = Files::state($id, $destination)[$relative] ?? array();
        $path = Files::disk_path($relative);
        $exists = $path !== '' && is_file($path);
        $current = $entry && (!$exists || ($entry['mtime'] === (int) filemtime($path) && $entry['size'] === (int) filesize($path)));
        if (!$current || !$exists) {
            self::queue_once($id, (bool) $entry && !$exists);
        }
        if (!$current || $origin === '') {
            return $url;
        }
        $asset = $entry['id'];
        $signature = Settings::sign("public\n" . $asset);
        return $signature === '' ? $url : $origin . '/v1/images/' . $asset . '/' . $signature;
    }

    private static function attachment_id(string $relative, int $hint): int
    {
        if ($hint && array_key_exists($relative, Files::entries($hint))) {
            return $hint;
        }
        if (!isset(self::$lookup[$relative])) {
            $uploads = wp_get_upload_dir();
            $base = trailingslashit($uploads['baseurl']);
            $id = attachment_url_to_postid($base . $relative);
            if (!$id) {
                $stem = preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $relative);
                $scaled = preg_replace('/(?=\.[a-z0-9]+$)/i', '-scaled', $stem);
                foreach (array_unique(array($stem, $scaled)) as $candidate) {
                    if ($candidate !== $relative && ($id = attachment_url_to_postid($base . $candidate))) {
                        break;
                    }
                }
            }
            self::$lookup[$relative] = $id && array_key_exists($relative, Files::entries($id)) ? (int) $id : 0;
        }
        return self::$lookup[$relative];
    }

    private static function queue_once(int $id, bool $repair): void
    {
        $count = count(self::$queued);
        if ((isset(self::$queued[$id]) ? $repair && !self::$queued[$id] : $count < 100)) {
            self::$queued[$id] = $repair;
            Sync::enqueue($id, $repair);
        }
    }

    public static function register_source(): void
    {
        register_rest_route('maotk-media/v1', '/source/(?P<asset>[a-f0-9]{64})', array(
            'methods' => 'GET, HEAD', 'permission_callback' => array(self::class, 'authorize'), 'callback' => array(self::class, 'source'),
        ));
    }

    public static function authorize(\WP_REST_Request $request): bool
    {
        $timestamp = $request->get_header('X-MaoTK-Media-Time');
        $signature = $request->get_header('X-MaoTK-Media-Signature');
        if (!preg_match('/\A\d{10}\z/', (string) $timestamp) || abs(time() - (int) $timestamp) > 90
            || !preg_match('/\A[a-f0-9]{64}\z/', (string) $signature)) {
            return false;
        }
        $asset = $request->get_url_params()['asset'];
        $expected = Settings::sign("origin\n" . $request->get_method() . "\n$asset\n$timestamp");
        return $expected !== '' && hash_equals($expected, $signature);
    }

    public static function source(\WP_REST_Request $request): \WP_REST_Response
    {
        $asset = $request->get_url_params()['asset'];
        $id = (int) hexdec(substr($asset, 0, 16));
        $storage = Settings::storage();
        if (!$storage || !Files::eligible($id)) {
            return new \WP_REST_Response(null, 404, array('Cache-Control' => 'no-store'));
        }
        $entries = Files::entries($id);
        foreach (Files::state($id, $storage->destination()) as $relative => $entry) {
            if ($entry['id'] !== $asset || !array_key_exists($relative, $entries)) {
                continue;
            }
            if ($request->get_method() === 'GET') {
                Sync::enqueue($id, true);
            }
            $path = Files::disk_path($relative);
            if ($path !== '' && is_file($path) && (int) filesize($path) === $entry['size']
                && hash_file('sha256', $path) === $entry['hash']) {
                self::$source[$request->get_route()] = $path;
                return new \WP_REST_Response(null, 200, array(
                    'Content-Type' => $entry['type'], 'Content-Length' => (string) $entry['size'],
                    'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
                ));
            }
            break;
        }
        return new \WP_REST_Response(null, 404, array('Cache-Control' => 'no-store'));
    }

    public static function serve_source(bool $served, $response, \WP_REST_Request $request): bool
    {
        $path = self::$source[$request->get_route()] ?? '';
        if ($served || $path === '' || $response->get_status() !== 200 || strpos($request->get_route(), self::ROUTE) !== 0) {
            return $served;
        }
        if ($request->get_method() !== 'HEAD') {
            readfile($path);
        }
        return true;
    }
}
