<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

class Settings
{
    protected const OPTION = 'mao_tk_zibll_toolkit_r2_settings';
    private const CIPHER = 'aes-256-gcm';
    public const FIELDS = array(
        'MAOTK_R2_ACCOUNT_ID' => array(
            'label' => 'Account ID',
            'limit' => 32,
            'pattern' => '/\A[a-f0-9]{32}\z/i',
            'secret' => false,
            'hint' => '在 Cloudflare R2 概览中复制账户 ID。'
        ),
        'MAOTK_R2_BUCKET' => array(
            'label' => '私有桶名称',
            'limit' => 63,
            'pattern' => '/\A[a-z0-9][a-z0-9-]{1,61}[a-z0-9]\z/',
            'secret' => false,
            'hint' => '填写 R2 桶名称，保持公开访问关闭。'
        ),
        'MAOTK_R2_ACCESS_KEY_ID' => array(
            'label' => 'Access Key ID',
            'limit' => 128,
            'pattern' => '/\A[A-Za-z0-9]{16,128}\z/',
            'secret' => true,
            'hint' => '使用限定到该桶的 Object Read & Write 凭据。'
        ),
        'MAOTK_R2_SECRET_ACCESS_KEY' => array(
            'label' => 'Secret Access Key',
            'limit' => 128,
            'pattern' => '/\A[A-Za-z0-9+\/=]{32,128}\z/',
            'secret' => true,
            'hint' => '填写与 Access Key ID 配套的密钥。'
        ),
        'MAOTK_R2_DOWNLOAD_ORIGIN' => array(
            'label' => 'Worker 下载域名',
            'limit' => 262,
            'pattern' => '~\Ahttps://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}/?\z~',
            'secret' => false,
            'hint' => '例如 https://download.example.com。填写 HTTPS 域名。'
        ),
        'MAOTK_R2_SIGNING_KEY' => array(
            'label' => 'Worker 签名密钥',
            'limit' => 64,
            'pattern' => '/\A[a-f0-9]{64}\z/i',
            'secret' => true,
            'hint' => '与 Worker 的 MAOTK_R2_SIGNING_KEY 一致，共 64 个十六进制字符。'
        )
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
        return function_exists('openssl_encrypt') &&
            function_exists('openssl_decrypt') &&
            in_array(self::CIPHER, openssl_get_cipher_methods(), true) &&
            self::key() !== '';
    }

    public static function save(array $submitted): string
    {
        $original = get_option(static::OPTION, null);
        $stored = is_array($original) ? $original : array();
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
                $value = self::encrypt($field, $value, self::key());
                if ($value === '') {
                    return 'encryption';
                }
            }
            $updated[$field] = $value;
        }
        if ($updated !== $stored && !self::replace_option(static::OPTION, $original, $updated)) {
            return 'write';
        }
        return 'saved';
    }

    public static function has_dedicated_key(): bool
    {
        return self::dedicated_key() !== '';
    }

    public static function migrate_credentials(): bool
    {
        $key = self::dedicated_key();
        if ($key === '' || !self::can_encrypt()) {
            throw new \RuntimeException(__('请先配置独立加密密钥并启用 PHP OpenSSL。', 'mao-tk-zibll-toolkit'));
        }
        $stored = self::stored();
        $updated = $stored;
        foreach (static::FIELDS as $field => $rules) {
            if (!$rules['secret'] || defined($field) || !isset($stored[$field]) || $stored[$field] === '') {
                continue;
            }
            $encrypted = $stored[$field];
            if (is_string($encrypted) && self::valid($field, self::decrypt($field, $encrypted, $key))) {
                continue;
            }
            $plain = is_string($encrypted) ? self::decrypt($field, $encrypted, self::salt_key()) : '';
            if (!self::valid($field, $plain)) {
                throw new \RuntimeException(__('无法读取旧凭据。请用原值重新保存后再试。', 'mao-tk-zibll-toolkit'));
            }
            $encrypted = self::encrypt($field, $plain, $key);
            if ($encrypted === '' || !hash_equals($plain, self::decrypt($field, $encrypted, $key))) {
                throw new \RuntimeException(__('凭据重新加密校验失败。', 'mao-tk-zibll-toolkit'));
            }
            $updated[$field] = $encrypted;
        }
        if ($updated === $stored) {
            return false;
        }
        if (!self::replace_option(static::OPTION, $stored, $updated)) {
            throw new \RuntimeException(__('凭据迁移未写入。请刷新页面后重试。', 'mao-tk-zibll-toolkit'));
        }
        return true;
    }

    protected static function replace_option(string $name, $original, $updated): bool
    {
        global $wpdb;
        if ($original === null) {
            $changed = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                $name,
                maybe_serialize($updated)
            ));
            wp_cache_delete('notoptions', 'options');
        } else {
            // Compare exact bytes so concurrent writes cannot restore stale credentials.
            $changed = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND HEX(option_value) = HEX(%s)",
                maybe_serialize($updated),
                $name,
                maybe_serialize($original)
            ));
            if (isset(wp_load_alloptions()[$name])) {
                wp_cache_delete('alloptions', 'options');
            }
        }
        wp_cache_delete($name, 'options');
        return $changed === 1;
    }

    private static function stored(): array
    {
        $stored = get_option(static::OPTION, array());
        return is_array($stored) ? $stored : array();
    }

    private static function valid(string $field, $value): bool
    {
        return is_string($value) &&
            strlen($value) <= static::FIELDS[$field]['limit'] &&
            preg_match(static::FIELDS[$field]['pattern'], $value) === 1;
    }

    private static function encrypt(string $field, string $value, string $key): string
    {
        $nonce = random_bytes(12);
        $encrypted = openssl_encrypt($value, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $field, 16);
        return $encrypted === false ? '' : base64_encode($nonce . $tag . $encrypted);
    }

    private static function decrypt(string $field, string $value, ?string $only_key = null): string
    {
        $packed = base64_decode($value, true);
        if ($packed === false || strlen($packed) < 29 || strlen($packed) > 156 || !self::can_encrypt()) {
            return '';
        }
        // Try the current key first, then the WordPress-salt key used before a dedicated key was configured.
        $keys = $only_key === null ? array_unique(array(self::key(), self::salt_key())) : array($only_key);
        foreach ($keys as $key) {
            if ($key === '') {
                continue;
            }
            $plain = openssl_decrypt(
                substr($packed, 28),
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                substr($packed, 0, 12),
                substr($packed, 12, 16),
                $field
            );
            if (is_string($plain)) {
                return $plain;
            }
        }
        return '';
    }

    /**
     * Secret fields that are saved but can no longer be decrypted, usually because the WordPress
     * AUTH_KEY / AUTH_SALT were changed. The admin pages ask for the values to be entered again.
     */
    public static function unreadable(): array
    {
        $labels = array();
        foreach (static::FIELDS as $field => $rules) {
            if (!$rules['secret'] || defined($field)) {
                continue;
            }
            $stored = self::stored()[$field] ?? '';
            if (is_string($stored) && $stored !== '' && self::decrypt($field, $stored) === '') {
                $labels[] = translate($rules['label'], 'mao-tk-zibll-toolkit');
            }
        }
        return $labels;
    }

    /**
     * Encryption key from server configuration only, so a database copy alone cannot decrypt the
     * credentials. Define MAO_TK_TOOLKIT_SECRET_KEY (32+ characters) in wp-config.php to keep
     * the credentials readable when WordPress salts are rotated.
     */
    private static function key(): string
    {
        return self::dedicated_key() ?: self::salt_key();
    }

    private static function dedicated_key(): string
    {
        if (defined('MAO_TK_TOOLKIT_SECRET_KEY')) {
            $secret = constant('MAO_TK_TOOLKIT_SECRET_KEY');
            if (is_string($secret) && strlen(trim($secret)) >= 32) {
                return hash_hmac('sha256', 'mao-tk-r2-settings-v2.' . get_current_blog_id(), $secret, true);
            }
        }
        return '';
    }

    private static function salt_key(): string
    {
        $secrets = array();
        foreach (array('AUTH_KEY', 'AUTH_SALT') as $name) {
            $value = defined($name) ? constant($name) : '';
            if (is_string($value) && strlen(trim($value)) >= 32 && $value !== 'put your unique phrase here') {
                $secrets[] = hash('sha256', $value, true);
            }
        }
        return $secrets
            ? hash_hmac('sha256', 'mao-tk-r2-settings-v1.' . get_current_blog_id(), implode('', $secrets), true)
            : '';
    }
}
