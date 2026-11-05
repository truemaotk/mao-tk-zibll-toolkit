<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Runtime
{
    public const MODULE = 'r2-downloads';
    private const INLINE_MARKER = '#maotk-r2-download';
    private static $pending_url = '';

    public static function register(): void
    {
        PublicDownload::register();
        add_action('zibpay_download_before', array(self::class, 'prepare'), 1, 4);
        add_action('zibpay_download_before', array(self::class, 'redirect'), PHP_INT_MAX, 0);
        add_filter('the_content', array(self::class, 'inline_downloads'), 100);
    }

    public static function origin(): string
    {
        return rtrim(Settings::get('MAOTK_R2_DOWNLOAD_ORIGIN'), '/');
    }

    public static function reference(int $post_id, string $key): string
    {
        return self::origin() . '/v1/download?maotk_ref=' . self::reference_value($post_id, $key);
    }

    public static function reference_value(int $post_id, string $key, bool $public = false): string
    {
        $payload = Uploads::encode($key);
        return $payload . '.' . self::reference_signature($post_id, $payload, $public);
    }

    private static function reference_signature(int $post_id, string $payload, bool $public): string
    {
        $purpose = $public
            ? 'maotk-r2-public-reference-v1.' . Settings::get('MAOTK_R2_ACCOUNT_ID') . '.' . Settings::get('MAOTK_R2_BUCKET') . '.'
            : 'maotk-r2-reference-v1.';
        return hash_hmac('sha256', $purpose . $post_id . '.' . $payload, wp_salt('secure_auth'));
    }

    public static function signing_key(): string
    {
        return Settings::get('MAOTK_R2_SIGNING_KEY');
    }

    public static function inline_downloads(string $html): string
    {
        if (strpos($html, 'file-download-btn') === false || !is_singular('post') || is_feed()
            || !class_exists('WP_HTML_Tag_Processor')) {
            return $html;
        }

        $origin = self::origin();
        $prefix = $origin . '/v1/download?maotk_ref=';
        $post_id = (int) get_the_ID();
        if ($origin === '' || $post_id !== (int) get_queried_object_id() || get_post_status($post_id) !== 'publish'
            || post_password_required($post_id)) {
            return $html;
        }

        $has_native_reference = strpos($html, $prefix) !== false || strpos($html, self::INLINE_MARKER) !== false;
        if (!$has_native_reference && strpos($html, PublicDownload::QUERY) === false && strpos($html, PublicDownload::LEGACY_ACTION) === false) {
            return $html;
        }
        $public_prefix = PublicDownload::prefix($post_id);
        $legacy_prefix = PublicDownload::legacy_prefix($post_id);
        $native_url = '';
        $downloads = array();
        if ($has_native_reference) {
            $downloads = self::native_downloads($post_id, $prefix);
            $paid = $downloads ? zibpay_is_paid($post_id) : false;
            $visibility = $paid ? zib_get_post_allow_view_data($post_id) : array();
            $can_download = !empty($paid['paid_type']) && !empty($visibility['allow'])
                && ($paid['paid_type'] !== 'free' || !_pz('pay_free_logged_show') || is_user_logged_in());
            $native_url = $can_download ? zib_pay_get_download_url($post_id) : '';
        }
        $tags = new \WP_HTML_Tag_Processor($html);
        $changed = false;
        $public_card = false;
        while ($tags->next_tag(array('tag_name' => 'A', 'class_name' => 'file-download-btn'))) {
            $ref = $tags->get_attribute('href');
            if (!is_string($ref) || $tags->get_attribute('data-download-file') !== null) {
                continue;
            }
            $legacy = strpos($ref, $legacy_prefix) === 0;
            $public = $legacy || strpos($ref, $public_prefix) === 0;
            if (!$public && $ref !== self::INLINE_MARKER && strpos($ref, $prefix) !== 0) {
                continue;
            }

            // The theme's attachment click handler only understands Media Library files.
            $tags->remove_class('file-download-btn');
            $tags->add_class('mtk-r2-inline-download');
            $tags->remove_attribute('target');
            $changed = true;
            if ($public) {
                $public_card = true;
                $tags->set_attribute('data-maotk-r2-public', '');
                if ($legacy) {
                    $tags->set_attribute('href', $public_prefix . substr($ref, strlen($legacy_prefix)));
                }
                continue;
            }

            $id = $ref === self::INLINE_MARKER && count($downloads) === 1 ? reset($downloads) : ($downloads[$ref] ?? null);
            if ($id === null) {
                $tags->remove_attribute('href');
                $tags->set_attribute('aria-disabled', 'true');
                $tags->set_attribute('title', '这篇文章尚未绑定对应的 R2 下载资源。');
            } else {
                $url = $native_url ? add_query_arg('down_id', $id, $native_url) : '#posts-pay';
                $tags->set_attribute('href', esc_url_raw($url));
            }
        }

        if ($changed) {
            wp_enqueue_style('mao-tk-toolkit-r2-inline', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-downloads/inline.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        }
        if ($public_card) {
            wp_enqueue_script('mao-tk-toolkit-r2-public', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-downloads/public.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        }
        return $tags->get_updated_html();
    }

    private static function native_downloads(int $post_id, string $prefix): array
    {
        if (!function_exists('zib_pay_get_download_url') || !function_exists('zibpay_get_post_down_array')
            || !function_exists('zibpay_is_paid') || !function_exists('zib_get_post_allow_view_data') || !function_exists('_pz')) {
            return array();
        }
        $settings = get_post_meta($post_id, 'posts_zibpay', true);
        if (!is_array($settings) || (string) ($settings['pay_type'] ?? '') !== '2' || empty($settings['pay_download'])) {
            return array();
        }

        $downloads = array();
        foreach (zibpay_get_post_down_array($settings) as $id => $download) {
            $ref = trim((string) ($download['link'] ?? ''));
            if (strpos($ref, $prefix) === 0 && self::reference_key($post_id, substr($ref, strlen($prefix))) !== null) {
                $downloads[$ref] = $id;
            }
        }
        return $downloads;
    }

    public static function reference_key(int $post_id, string $ref, bool $public = false): ?string
    {
        if (!preg_match('/\A([A-Za-z0-9_-]{1,1366})\.([a-f0-9]{64})\z/', $ref, $parts)
            || !hash_equals(self::reference_signature($post_id, $parts[1], $public), $parts[2])) {
            return null;
        }
        $key = base64_decode(strtr($parts[1], '-_', '+/'), true);
        return is_string($key) && Storage::valid_key($key) ? $key : null;
    }

    public static function prepare($post_id, $down_id, $paid, $file_url): void
    {
        self::$pending_url = '';
        $origin = self::origin();
        $prefix = $origin . '/v1/download?maotk_ref=';
        if ($origin === '' || !is_string($file_url) || strpos($file_url, $prefix) !== 0) {
            return;
        }
        if (!function_exists('zib_get_post_allow_view_data')
            || has_action('zibpay_download_before', 'zibpay_updata_pay_down_mate') !== 10) {
            wp_die('当前主题的下载权限或计数入口不可用。', '', array('response' => 503));
        }
        $post_id = (int) $post_id;
        $ref = substr($file_url, strlen($prefix));
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || get_post_type($post_id) !== 'post'
            || get_post_status($post_id) !== 'publish' || post_password_required($post_id)
            || !is_array($paid) || empty($paid['paid_type'])
            || ($key = self::reference_key($post_id, $ref)) === null) {
            wp_die('下载引用无效或当前文章不可下载。', '', array('response' => 403));
        }
        $visibility = zib_get_post_allow_view_data($post_id);
        if (empty($visibility['allow'])) {
            wp_die('当前账号没有该文章的阅读权限。', '', array('response' => 403));
        }
        $url = add_query_arg('down_id', $down_id, zib_pay_get_download_url($post_id));
        self::$pending_url = Session::download_url($url, 'paid:' . $post_id . ':' . $down_id, $key);
    }

    public static function redirect(): void
    {
        if (self::$pending_url !== '') {
            self::send_download(self::$pending_url);
        }
    }

    public static function send_download(string $url, int $status = 302): void
    {
        nocache_headers();
        header('Cache-Control: private, no-store', true);
        header('Referrer-Policy: no-referrer', true);
        if (!wp_redirect($url, $status, 'Mao TK Toolkit')) {
            wp_die('下载暂时无法开始。', '', array('response' => 503));
        }
        exit;
    }
}
