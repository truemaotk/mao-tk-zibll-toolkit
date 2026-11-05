<?php
namespace MaoTK\ZibllToolkit\R2Media;

use MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Settings extends R2Downloads\Settings
{
    protected const OPTION = 'mao_tk_zibll_toolkit_r2_media_settings';
    private const VERIFIED = 'mao_tk_r2_media_verified_connection';
    public const FIELDS = array(
        'MAOTK_MEDIA_ACCOUNT_ID' => array('label' => 'Account ID', 'limit' => 32, 'pattern' => '/\A[a-f0-9]{32}\z/i', 'secret' => false),
        'MAOTK_MEDIA_BUCKET' => array('label' => '私有媒体桶', 'limit' => 63, 'pattern' => '/\A[a-z0-9][a-z0-9-]{1,61}[a-z0-9]\z/', 'secret' => false),
        'MAOTK_MEDIA_ACCESS_KEY_ID' => array('label' => 'Access Key ID', 'limit' => 128, 'pattern' => '/\A[A-Za-z0-9]{16,128}\z/', 'secret' => true),
        'MAOTK_MEDIA_SECRET_ACCESS_KEY' => array('label' => 'Secret Access Key', 'limit' => 128, 'pattern' => '/\A[A-Za-z0-9+\/=]{32,128}\z/', 'secret' => true),
        'MAOTK_MEDIA_ORIGIN' => array('label' => '媒体 Worker HTTPS 地址', 'limit' => 262, 'pattern' => '~\Ahttps://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}/?\z~', 'secret' => false),
        'MAOTK_MEDIA_SIGNING_KEY' => array('label' => '媒体 Worker 签名密钥', 'limit' => 64, 'pattern' => '/\A[a-f0-9]{64}\z/i', 'secret' => true),
        'MAOTK_MEDIA_REWRITE' => array('label' => '启用前台地址切换', 'limit' => 1, 'pattern' => '/\A[01]\z/', 'secret' => false),
    );

    public static function storage(): ?R2Downloads\Storage
    {
        $bucket = self::get('MAOTK_MEDIA_BUCKET');
        if ($bucket === 'maotk-private-downloads' || $bucket === R2Downloads\Settings::get('MAOTK_R2_BUCKET')) {
            return null;
        }
        return R2Downloads\Storage::with_credentials(
            self::get('MAOTK_MEDIA_ACCOUNT_ID'), $bucket,
            self::get('MAOTK_MEDIA_ACCESS_KEY_ID'), self::get('MAOTK_MEDIA_SECRET_ACCESS_KEY')
        );
    }

    public static function origin(): string
    {
        return rtrim(self::get('MAOTK_MEDIA_ORIGIN'), '/');
    }

    public static function sign(string $message): string
    {
        $secret = self::get('MAOTK_MEDIA_SIGNING_KEY');
        return $secret === '' ? '' : hash_hmac('sha256', $message, hex2bin($secret));
    }

    public static function verified(string $destination): bool
    {
        $fingerprint = self::fingerprint($destination);
        return $fingerprint !== '' && hash_equals($fingerprint, (string) get_option(self::VERIFIED, ''));
    }

    private static function fingerprint(string $destination): string
    {
        return self::sign("connection\n" . $destination . "\n" . self::origin() . "\n"
            . self::get('MAOTK_MEDIA_ACCESS_KEY_ID') . "\n" . self::get('MAOTK_MEDIA_SECRET_ACCESS_KEY'));
    }

    public static function verify_connection(): void
    {
        delete_option(self::VERIFIED);
        $storage = self::storage();
        if (!$storage || self::origin() === '' || self::sign('check') === '') {
            throw new \RuntimeException('先保存媒体桶凭据、Worker 地址和签名密钥。');
        }
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF7sAAAAASUVORK5CYII=', true);
        $asset = hash('sha256', wp_generate_uuid4());
        $key = 'public/' . $asset;
        $url = self::origin() . '/v1/images/' . $asset . '/' . self::sign("public\n" . $asset);
        $storage->put_image($key, $image, 'image/png');
        try {
            $response = wp_safe_remote_get($url, array('timeout' => 8, 'redirection' => 0, 'limit_response_size' => 1024));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200
                || wp_remote_retrieve_body($response) !== $image || wp_remote_retrieve_header($response, 'content-type') !== 'image/png') {
                throw new \RuntimeException('Worker 未返回测试图片。请核对 Worker 绑定、域名和签名。');
            }
        } finally {
            $storage->delete_image($key);
        }
        update_option(self::VERIFIED, self::fingerprint($storage->destination()), false);
    }
}
