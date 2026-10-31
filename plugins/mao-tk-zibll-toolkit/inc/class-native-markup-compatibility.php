<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

/** Attribute-only compatibility for Zibll 9.1's native header/auth/comment markup. */
final class NativeMarkupCompatibility
{
    private static $started = false;
    private const AUTH_ACTIONS = array('user_signin', 'user_signin_nopas', 'user_signup', 'reset_password');

    public static function register(): void
    {
        if (class_exists('WP_HTML_Tag_Processor')) {
            add_action('template_redirect', array(__CLASS__, 'start'), 0);
        }
    }

    public static function start(): void
    {
        if (self::$started || is_admin() || get_template() !== 'zibll'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
            || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)
            || is_feed() || is_trackback() || is_embed()
            || (function_exists('wp_is_json_request') && wp_is_json_request())
            || !apply_filters('mao_tk_toolkit_native_markup_compat_enabled', true)) {
            return;
        }
        self::$started = true;
        $streamed = false;
        // Zibll exposes no output filter here. Keep streaming/non-HTML responses byte-for-byte.
        ob_start(static function ($html, $phase) use (&$streamed) {
            if (($phase & PHP_OUTPUT_HANDLER_FLUSH) && !($phase & PHP_OUTPUT_HANDLER_FINAL)) {
                $streamed = true;
            }
            if ($streamed || ($phase & PHP_OUTPUT_HANDLER_CLEAN) || !($phase & PHP_OUTPUT_HANDLER_FINAL)) {
                return $html;
            }
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                    return $html;
                }
            }
            try {
                $updated = self::decorate_document($html, self::logo_dimensions());
                if ($updated !== $html && !headers_sent()) {
                    header_remove('Content-Length');
                }
                return $updated;
            } catch (\Throwable $error) {
                return $html;
            }
        });
    }

    /** Uses the HTML API's surgical attribute updates; no DOM reserialization or stored-content edit. */
    public static function decorate_document(string $html, array $logos = array()): string
    {
        if (!class_exists('WP_HTML_Tag_Processor') || strlen($html) > 4 * 1024 * 1024
            || !preg_match('/\A(?:\xEF\xBB\xBF)?\s*(?:<!--.*?-->\s*)*(?:<!doctype\s+html\b|<html\b)/is', substr($html, 0, 4096))
            || stripos($html, '</html>') === false) {
            return $html;
        }
        $scan = new \WP_HTML_Tag_Processor($html);
        $forms = array();
        $ids = array();
        $index = -1;
        $current = null;
        while ($scan->next_tag(array('tag_closers' => 'visit'))) {
            $tag = $scan->get_tag();
            if (!$scan->is_tag_closer()) {
                $id = $scan->get_attribute('id');
                if (is_string($id) && $id !== '') { $ids[$id] = true; }
            }
            if ($tag === 'FORM') {
                if ($scan->is_tag_closer()) { $current = null; continue; }
                $current = ++$index;
                $forms[$current] = array('kind' => $scan->get_attribute('id') === 'commentform' ? 'comment' : '', 'autocomplete' => $scan->get_attribute('autocomplete'));
            } elseif ($tag === 'INPUT' && !$scan->is_tag_closer() && $current !== null
                && strtolower((string) $scan->get_attribute('type')) === 'hidden' && $scan->get_attribute('name') === 'action') {
                $action = $scan->get_attribute('value');
                if (in_array($action, self::AUTH_ACTIONS, true)) { $forms[$current]['kind'] = $action; }
            }
        }
        $p = new \WP_HTML_Tag_Processor($html);
        $index = -1;
        $current = null;
        $next_is_logo = false;
        $helper = 0;
        while ($p->next_tag(array('tag_closers' => 'visit'))) {
            $tag = $p->get_tag();
            $closer = $p->is_tag_closer();
            // Verified theme contract: the first child of .navbar-logo is a native IMG.
            if (!$closer && $tag === 'IMG' && $next_is_logo) {
                self::decorate_logo($p, $logos);
            }
            $next_is_logo = !$closer && in_array($tag, array('A', 'DIV'), true) && $p->has_class('navbar-logo');
            if ($tag === 'FORM') {
                $current = $closer ? null : ++$index;
                continue;
            }
            if ($closer || $current === null || empty($forms[$current]['kind'])) { continue; }
            $kind = $forms[$current]['kind'];
            if ($tag === 'INPUT' && $p->has_class('line-form-input')) {
                $name = $p->get_attribute('name');
                $type = strtolower((string) $p->get_attribute('type'));
                if (in_array($type, array('hidden', 'checkbox', 'radio', 'submit', 'button'), true)) { continue; }
                $tokens = $kind === 'comment'
                    ? array('author' => 'nickname', 'email' => 'email', 'url' => 'url')
                    : array('username' => 'username', 'name' => 'username', 'email' => 'email', 'phone' => 'tel', 'password' => $kind === 'user_signin' ? 'current-password' : 'new-password', 'password2' => 'new-password', 'repassword' => 'new-password');
                if (is_string($name) && isset($tokens[$name])) {
                    self::set_autocomplete($p, strtolower(trim((string) $forms[$current]['autocomplete'])) === 'off' ? 'off' : $tokens[$name]);
                }
            } elseif ($tag === 'TEXTAREA' && $kind === 'comment' && $p->has_class('input-textarea')) {
                // A name would change native form.serialize(). Only give unnamed helpers a unique ID.
                $id = $p->get_attribute('id');
                $name = $p->get_attribute('name');
                if (($id === null || $id === '') && ($name === null || $name === '')) {
                    do { $id = 'mao-tk-comment-helper-' . ++$helper; } while (isset($ids[$id]));
                    $ids[$id] = true;
                    $p->set_attribute('id', $id);
                    self::set_autocomplete($p, 'off');
                }
            }
        }
        return $p->get_updated_html();
    }

    private static function set_autocomplete(\WP_HTML_Tag_Processor $p, string $token): void
    {
        $existing = $p->get_attribute('autocomplete');
        if ($existing === null || $existing === '') { $p->set_attribute('autocomplete', $token); }
    }

    private static function decorate_logo(\WP_HTML_Tag_Processor $p, array $logos): void
    {
        // Preserve theme/third-party custom lazy-loaders; the reported problem is native lazy loading.
        if ($p->get_attribute('data-src') !== null) { return; }
        $src = $p->get_attribute('src');
        if (!is_string($src) || $src === '') { return; }
        if (strtolower((string) $p->get_attribute('loading')) === 'lazy') { $p->set_attribute('loading', 'eager'); }
        $size = $logos[$src] ?? null;
        if (!is_array($size) || count($size) !== 2 || $size[0] <= 0 || $size[1] <= 0) { return; }
        $width = $p->get_attribute('width');
        $height = $p->get_attribute('height');
        if ($width === null && $height === null) {
            $p->set_attribute('width', (string) $size[0]);
            $p->set_attribute('height', (string) $size[1]);
            // HTML width otherwise becomes a display width. Native Zibll only declares
            // height, so retain its original auto-width sizing, including mobile SVGs.
            $style = $p->get_attribute('style');
            $style = is_string($style) ? $style : '';
            if (!preg_match('/(?:\A|;)\s*width\s*:/i', $style)) {
                $p->set_attribute('style', rtrim($style, "; \t\n\r\0\x0B") . ($style !== '' ? ';' : '') . 'width:auto');
            }
        } elseif ($width === null && is_numeric($height) && (float) $height > 0) {
            $p->set_attribute('width', (string) max(1, (int) round((float) $height * $size[0] / $size[1])));
        } elseif ($height === null && is_numeric($width) && (float) $width > 0) {
            $p->set_attribute('height', (string) max(1, (int) round((float) $width * $size[1] / $size[0])));
        }
    }

    public static function logo_dimensions(): array
    {
        if (!function_exists('_pz')) { return array(); }
        $result = array();
        foreach (array('logo_src', 'logo_src_dark') as $key) {
            $url = _pz($key);
            if (!is_string($url) || $url === '' || isset($result[$url])) { continue; }
            $id = attachment_url_to_postid(preg_replace('/[?#].*$/', '', $url));
            if (!$id) { continue; }
            $metadata = wp_get_attachment_metadata($id);
            if (is_array($metadata) && !empty($metadata['width']) && !empty($metadata['height'])) {
                $size = self::valid_dimensions($metadata['width'], $metadata['height']);
                if ($size) { $result[$url] = $size; continue; }
            }
            $file = get_attached_file($id);
            $uploads = wp_upload_dir(null, false);
            $base = !empty($uploads['basedir']) ? realpath($uploads['basedir']) : false;
            $real = is_string($file) ? realpath($file) : false;
            $inside_uploads = $base && $real && (DIRECTORY_SEPARATOR === '\\'
                ? stripos(wp_normalize_path($real), trailingslashit(wp_normalize_path($base))) === 0
                : strpos(wp_normalize_path($real), trailingslashit(wp_normalize_path($base))) === 0);
            if (!$inside_uploads
                || strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'svg' || !is_file($real) || !is_readable($real) || filesize($real) > 131072) { continue; }
            // Only inspect a local SVG root; never fetch URLs or resolve XML entities.
            $head = file_get_contents($real, false, null, 0, 16384);
            if (!is_string($head) || !preg_match('/<svg\b[^>]*>/i', $head, $match)) { continue; }
            $svg = new \WP_HTML_Tag_Processor($match[0]);
            if (!$svg->next_tag('SVG')) { continue; }
            $width = self::svg_length($svg->get_attribute('width'));
            $height = self::svg_length($svg->get_attribute('height'));
            $size = self::valid_dimensions($width, $height);
            if (!$size) {
                $box = $svg->get_attribute('viewBox');
                $parts = is_string($box) ? preg_split('/[\s,]+/', trim($box)) : array();
                if (count($parts) === 4 && is_numeric($parts[2]) && is_numeric($parts[3])) { $size = self::valid_dimensions($parts[2], $parts[3]); }
            }
            if ($size) { $result[$url] = $size; }
        }
        return $result;
    }

    private static function svg_length($value): float
    {
        return is_string($value) && preg_match('/\A(?:\d+(?:\.\d+)?|\.\d+)(?:px)?\z/i', $value) ? (float) $value : 0;
    }

    private static function valid_dimensions($width, $height): array
    {
        if (!is_numeric($width) || !is_numeric($height) || $width <= 0 || $height <= 0 || $width > 65535 || $height > 65535) { return array(); }
        return array(max(1, (int) round((float) $width)), max(1, (int) round((float) $height)));
    }
}
