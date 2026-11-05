<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

class Settings
{
    protected const OPTION = 'mao_tk_zibll_toolkit_r2_settings';
    private const CIPHER = 'aes-256-gcm';
    public const FIELDS = array(
        'MAOTK_R2_ACCOUNT_ID' => array('label' => 'Account ID', 'limit' => 32, 'pattern' => '/\A[a-f0-9]{32}\z/i', 'secret' => false, 'hint' => '在 Cloudflare R2 概览中复制账户 ID。'),
        'MAOTK_R2_BUCKET' => array('label' => '私有桶名称', 'limit' => 63, 'pattern' => '/\A[a-z0-9][a-z0-9-]{1,61}[a-z0-9]\z/', 'secret' => false, 'hint' => '填写 R2 桶名称，保持公开访问关闭。'),
        'MAOTK_R2_ACCESS_KEY_ID' => array('label' => 'Access Key ID', 'limit' => 128, 'pattern' => '/\A[A-Za-z0-9]{16,128}\z/', 'secret' => true, 'hint' => '使用限定到该桶的 Object Read & Write 凭据。'),
        'MAOTK_R2_SECRET_ACCESS_KEY' => array('label' => 'Secret Access Key', 'limit' => 128, 'pattern' => '/\A[A-Za-z0-9+\/=]{32,128}\z/', 'secret' => true, 'hint' => '填写与 Access Key ID 配套的密钥。'),
        'MAOTK_R2_DOWNLOAD_ORIGIN' => array('label' => 'Worker 下载域名', 'limit' => 262, 'pattern' => '~\Ahttps://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}/?\z~', 'secret' => false, 'hint' => '例如 https://download.example.com。填写 HTTPS 域名。'),
        'MAOTK_R2_SIGNING_KEY' => array('label' => 'Worker 签名密钥', 'limit' => 64, 'pattern' => '/\A[a-f0-9]{64}\z/i', 'secret' => true, 'hint' => '与 Worker 的 MAOTK_R2_SIGNING_KEY 一致，共 64 个十六进制字符。'),
    );

    public static function get(string $field): string
    {
        if (defined($field)) {
            $value = constant($field);
        } else {
            $value = self::stored()[$field] ?? '';
            if (is_string($value) && $value !== '' && static::FIELDS[$field]['secret']) {
                $value = self::decrypt($field, $value);
            }
        }
        return self::valid($field, $value) ? $value : '';
    }

    public static function can_encrypt(): bool
    {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true) && self::key() !== '';
    }

    public static function save(array $submitted): string
    {
        $stored = self::stored();
        $updated = $stored;
        foreach (static::FIELDS as $field => $rules) {
            if (defined($field) || !array_key_exists($field, $submitted)) {
                continue;
            }
            $value = is_string($submitted[$field]) ? trim($submitted[$field]) : null;
            if ($value === '' && $rules['secret']) {
                continue;
            }
            if ($value === '') {
                unset($updated[$field]);
                continue;
            }
            if (!self::valid($field, $value)) {
                return $field;
            }
            if ($rules['secret']) {
                if (!self::can_encrypt()) {
                    return 'encryption';
                }
                $nonce = random_bytes(12);
                $encrypted = openssl_encrypt($value, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag, $field, 16);
                if ($encrypted === false) {
                    return 'encryption';
                }
                $value = base64_encode($nonce . $tag . $encrypted);
            }
            $updated[$field] = $value;
        }
        if ($updated !== $stored && !update_option(static::OPTION, $updated, false)) {
            return 'write';
        }
        return 'saved';
    }

    private static function stored(): array
    {
        $stored = get_option(static::OPTION, array());
        return is_array($stored) ? $stored : array();
    }

    private static function valid(string $field, $value): bool
    {
        return is_string($value) && strlen($value) <= static::FIELDS[$field]['limit']
            && preg_match(static::FIELDS[$field]['pattern'], $value) === 1;
    }

    private static function decrypt(string $field, string $value): string
    {
        $packed = base64_decode($value, true);
        if ($packed === false || strlen($packed) < 29 || strlen($packed) > 156 || !self::can_encrypt()) {
            return '';
        }
        $plain = openssl_decrypt(substr($packed, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16), $field);
        return is_string($plain) ? $plain : '';
    }

    private static function key(): string
    {
        // Use server configuration only so a database copy alone cannot decrypt the credentials.
        $secrets = array();
        foreach (array('AUTH_KEY', 'AUTH_SALT') as $name) {
            $value = defined($name) ? constant($name) : '';
            if (is_string($value) && strlen(trim($value)) >= 32 && $value !== 'put your unique phrase here') {
                $secrets[] = hash('sha256', $value, true);
            }
        }
        return $secrets ? hash_hmac('sha256', 'mao-tk-r2-settings-v1.' . get_current_blog_id(), implode('', $secrets), true) : '';
    }
}
