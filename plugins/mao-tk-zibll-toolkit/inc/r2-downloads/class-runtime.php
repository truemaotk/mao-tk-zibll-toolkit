<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Runtime
{
    public const MODULE = 'r2-downloads';
    private const INLINE_MARKER = '#maotk-r2-download';
    public const REFERENCE_KEY_OPTION = 'mao_tk_zibll_toolkit_r2_reference_key';
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
        return $payload . '.' . self::reference_signature($post_id, $payload, $public, self::reference_secret(true));
    }

    /**
     * Signs a reference. New references use a dedicated key that does not change when WordPress
     * salts are rotated; references created before 1.24.0-rc.11 used wp_salt('secure_auth') and
     * are still accepted (see ReferenceMigration for re-signing them).
     */
    private static function reference_signature(int $post_id, string $payload, bool $public, string $key): string
    {
        $purpose = $public
            ? 'maotk-r2-public-reference-v1.' .
                Settings::get('MAOTK_R2_ACCOUNT_ID') .
                '.' .
                Settings::get('MAOTK_R2_BUCKET') .
                '.'
            : 'maotk-r2-reference-v1.';
        return hash_hmac('sha256', $purpose . $post_id . '.' . $payload, $key);
    }

    /** Dedicated signing key: wp-config constant if defined, otherwise a random key stored once in the database. */
    public static function reference_secret(bool $create = false): string
    {
        if (defined('MAO_TK_TOOLKIT_R2_REFERENCE_KEY')) {
            $constant = constant('MAO_TK_TOOLKIT_R2_REFERENCE_KEY');
            if (is_string($constant) && strlen(trim($constant)) >= 32) {
                return hash('sha256', 'maotk-r2-reference-key.' . $constant, true);
            }
            if ($create) {
                throw new \RuntimeException(__('下载引用密钥无效：请设置至少 32 个字符后再生成或迁移。', 'mao-tk-zibll-toolkit'));
            }
            return '';
        }
        $stored = get_option(self::REFERENCE_KEY_OPTION, null);
        if (!is_string($stored) || !preg_match('/\A[a-f0-9]{64}\z/', $stored)) {
            if (!$create) {
                return '';
            }
            global $wpdb;
            if ($stored === null) {
                // Concurrent administrators must reuse the first inserted key.
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                    self::REFERENCE_KEY_OPTION,
                    bin2hex(random_bytes(32))
                ));
                wp_cache_delete('notoptions', 'options');
            }
            wp_cache_delete(self::REFERENCE_KEY_OPTION, 'options');
            $stored = get_option(self::REFERENCE_KEY_OPTION, '');
            if (!is_string($stored) || !preg_match('/\A[a-f0-9]{64}\z/', $stored)) {
                throw new \RuntimeException(
                    __('无法保存下载链接签名密钥，请检查数据库写入权限。', 'mao-tk-zibll-toolkit')
                );
            }
        }
        return (string) hex2bin($stored);
    }

    private static function legacy_secret(): string
    {
        return wp_salt('secure_auth');
    }

    public static function signing_key(): string
    {
        return Settings::get('MAOTK_R2_SIGNING_KEY');
    }

    public static function inline_downloads(string $html): string
    {
        if (
            strpos($html, 'file-download-btn') === false ||
            !is_singular('post') ||
            is_feed() ||
            !class_exists('WP_HTML_Tag_Processor')
        ) {
            return $html;
        }

        $origin = self::origin();
        $prefix = $origin . '/v1/download?maotk_ref=';
        $post_id = (int) get_the_ID();
        if (
            $origin === '' ||
            $post_id !== (int) get_queried_object_id() ||
            get_post_status($post_id) !== 'publish' ||
            post_password_required($post_id)
        ) {
            return $html;
        }

        $has_native_reference = strpos($html, $prefix) !== false || strpos($html, self::INLINE_MARKER) !== false;
        if (
            !$has_native_reference &&
            strpos($html, PublicDownload::QUERY) === false &&
            strpos($html, PublicDownload::LEGACY_ACTION) === false
        ) {
            return $html;
        }
        $native_url = '';
        $downloads = array();
        if ($has_native_reference) {
            $downloads = self::native_downloads($post_id, $prefix);
            $paid = $downloads ? zibpay_is_paid($post_id) : false;
            $visibility = $paid ? zib_get_post_allow_view_data($post_id) : array();
            $can_download =
                !empty($paid['paid_type']) &&
                !empty($visibility['allow']) &&
                ($paid['paid_type'] !== 'free' || !_pz('pay_free_logged_show') || is_user_logged_in());
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
            $public = PublicDownload::parse($ref);
            if (!$public && $ref !== self::INLINE_MARKER && strpos($ref, $prefix) !== 0) {
                continue;
            }

            // The theme's attachment click handler only understands Media Library files.
            $tags->remove_class('file-download-btn');
            $tags->add_class('mtk-r2-inline-download');
            $tags->remove_attribute('target');
            $changed = true;
            if ($public) {
                // Show exactly what the download endpoint will accept: a valid signature on a card
                // placed in a supported visible block. Anything else is disabled instead of 404ing.
                if (
                    PublicDownload::access($public['post_id']) !== 200 ||
                    self::reference_key($public['post_id'], $public['ref'], true) === null ||
                    !in_array($ref, PublicDownload::published_urls($public['post_id']), true)
                ) {
                    $tags->remove_attribute('href');
                    $tags->set_attribute('aria-disabled', 'true');
                    $tags->set_attribute(
                        'title',
                        __('该下载卡片不可用：链接已失效，或卡片放在了不支持公开下载的区块中。', 'mao-tk-zibll-toolkit')
                    );
                    continue;
                }
                $public_card = true;
                $tags->set_attribute('data-maotk-r2-public', '');
                if ($public['legacy']) {
                    $tags->set_attribute('href', PublicDownload::prefix($public['post_id']) . $public['ref']);
                }
                continue;
            }

            $id =
                $ref === self::INLINE_MARKER && count($downloads) === 1 ? reset($downloads) : $downloads[$ref] ?? null;
            if ($id === null) {
                $tags->remove_attribute('href');
                $tags->set_attribute('aria-disabled', 'true');
                $tags->set_attribute('title', __('这篇文章尚未绑定对应的 R2 下载资源。', 'mao-tk-zibll-toolkit'));
            } else {
                $url = $native_url ? add_query_arg('down_id', $id, $native_url) : '#posts-pay';
                $tags->set_attribute('href', esc_url_raw($url));
            }
        }

        if ($changed) {
            wp_enqueue_style(
                'mao-tk-toolkit-r2-inline',
                MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-downloads/inline.css',
                array(),
                MAO_TK_ZIBLL_TOOLKIT_VERSION
            );
        }
        if ($public_card) {
            wp_enqueue_script(
                'mao-tk-toolkit-r2-public',
                MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-downloads/public.js',
                array(),
                MAO_TK_ZIBLL_TOOLKIT_VERSION,
                true
            );
        }
        return $tags->get_updated_html();
    }

    private static function native_downloads(int $post_id, string $prefix): array
    {
        if (
            !function_exists('zib_pay_get_download_url') ||
            !function_exists('zibpay_get_post_down_array') ||
            !function_exists('zibpay_is_paid') ||
            !function_exists('zib_get_post_allow_view_data') ||
            !function_exists('_pz')
        ) {
            return array();
        }
        $settings = get_post_meta($post_id, 'posts_zibpay', true);
        if (
            !is_array($settings) ||
            (string) ($settings['pay_type'] ?? '') !== '2' ||
            empty($settings['pay_download'])
        ) {
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
        return self::reference_state($post_id, $ref, $public) === '' ? null : self::decode_reference($ref);
    }

    /** Returns 'current', 'legacy' (signed with the old salt-based key) or '' when invalid. */
    public static function reference_state(int $post_id, string $ref, bool $public = false): string
    {
        if (
            !preg_match('/\A([A-Za-z0-9_-]{1,1366})\.([a-f0-9]{64})\z/', $ref, $parts) ||
            self::decode_reference($ref) === null
        ) {
            return '';
        }
        $current = self::reference_secret();
        if ($current !== '' && hash_equals(self::reference_signature($post_id, $parts[1], $public, $current), $parts[2])) {
            return 'current';
        }
        // A site may switch from the generated key to wp-config. Existing references keep working.
        if (defined('MAO_TK_TOOLKIT_R2_REFERENCE_KEY')) {
            $stored = get_option(self::REFERENCE_KEY_OPTION, '');
            if (is_string($stored) && preg_match('/\A[a-f0-9]{64}\z/', $stored) &&
                hash_equals(self::reference_signature($post_id, $parts[1], $public, (string) hex2bin($stored)), $parts[2])) {
                return 'current';
            }
        }
        return hash_equals(self::reference_signature($post_id, $parts[1], $public, self::legacy_secret()), $parts[2])
            ? 'legacy'
            : '';
    }

    /** Re-sign a reference for the target signer; return an empty string if no change is needed. */
    public static function resign_reference(int $post_id, string $ref, bool $public, bool $legacy = false): string
    {
        if (self::reference_state($post_id, $ref, $public) !== ($legacy ? 'current' : 'legacy')) {
            return '';
        }
        $payload = substr($ref, 0, (int) strpos($ref, '.'));
        $secret = $legacy ? self::legacy_secret() : self::reference_secret(true);
        if ($secret === '') {
            throw new \RuntimeException(__('无法读取下载引用密钥，未写入该引用。', 'mao-tk-zibll-toolkit'));
        }
        return $payload . '.' . self::reference_signature($post_id, $payload, $public, $secret);
    }

    private static function decode_reference(string $ref): ?string
    {
        if (!preg_match('/\A([A-Za-z0-9_-]{1,1366})\.[a-f0-9]{64}\z/', $ref, $parts)) {
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
        if (
            !function_exists('zib_get_post_allow_view_data') ||
            has_action('zibpay_download_before', 'zibpay_updata_pay_down_mate') !== 10
        ) {
            wp_die(esc_html__('当前主题的下载权限或计数入口不可用。', 'mao-tk-zibll-toolkit'), '', array('response' => 503));
        }
        $post_id = (int) $post_id;
        $ref = substr($file_url, strlen($prefix));
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ||
            get_post_type($post_id) !== 'post' ||
            get_post_status($post_id) !== 'publish' ||
            post_password_required($post_id) ||
            !is_array($paid) ||
            empty($paid['paid_type']) ||
            ($key = self::reference_key($post_id, $ref)) === null
        ) {
            wp_die(esc_html__('下载引用无效或当前文章不可下载。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
        }
        $visibility = zib_get_post_allow_view_data($post_id);
        if (empty($visibility['allow'])) {
            wp_die(esc_html__('当前账号没有该文章的阅读权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
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
            wp_die(esc_html__('下载暂时无法开始。', 'mao-tk-zibll-toolkit'), '', array('response' => 503));
        }
        exit;
    }
}
