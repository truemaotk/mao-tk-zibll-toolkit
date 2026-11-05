<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Runtime
{
    public const MODULE = 'r2-downloads';
    private static $pending_url = '';

    public static function register(): void
    {
        add_action('zibpay_download_before', array(self::class, 'prepare'), 1, 4);
        add_action('zibpay_download_before', array(self::class, 'redirect'), PHP_INT_MAX, 0);
    }

    public static function origin(): string
    {
        return rtrim(Settings::get('MAOTK_R2_DOWNLOAD_ORIGIN'), '/');
    }

    public static function reference(int $post_id, string $key): string
    {
        $payload = Uploads::encode($key);
        return self::origin() . '/v1/download?maotk_ref=' . $payload . '.' . self::reference_signature($post_id, $payload);
    }

    private static function reference_signature(int $post_id, string $payload): string
    {
        return hash_hmac('sha256', 'maotk-r2-reference-v1.' . $post_id . '.' . $payload, wp_salt('secure_auth'));
    }

    public static function signing_key(): string
    {
        return Settings::get('MAOTK_R2_SIGNING_KEY');
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
            || !preg_match('/\A([A-Za-z0-9_-]{1,1366})\.([a-f0-9]{64})\z/', $ref, $parts)
            || !hash_equals(self::reference_signature($post_id, $parts[1]), $parts[2])) {
            wp_die('下载引用无效或当前文章不可下载。', '', array('response' => 403));
        }
        $visibility = zib_get_post_allow_view_data($post_id);
        if (empty($visibility['allow'])) {
            wp_die('当前账号没有该文章的阅读权限。', '', array('response' => 403));
        }
        $key = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if (!is_string($key) || !Storage::valid_key($key)) {
            wp_die('下载引用中的文件键无效。', '', array('response' => 403));
        }
        $secret = self::signing_key();
        if ($secret === '') {
            wp_die('下载签名尚未配置，请联系管理员。', '', array('response' => 503));
        }
        $payload = Uploads::encode(wp_json_encode(array('key' => $key, 'exp' => time() + 600)));
        $signature = Uploads::encode(hash_hmac('sha256', 'v1.' . $payload, $secret, true));
        self::$pending_url = $origin . '/v1/download?token=' . $payload . '.' . $signature;
    }

    public static function redirect(): void
    {
        if (self::$pending_url === '') {
            return;
        }
        nocache_headers();
        header('Cache-Control: private, no-store', true);
        header('Referrer-Policy: no-referrer', true);
        if (!wp_redirect(self::$pending_url, 302, 'Mao TK Toolkit')) {
            wp_die('下载暂时无法开始。', '', array('response' => 503));
        }
        exit;
    }
}
