<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class PublicDownload
{
    public const QUERY = 'maotk_r2_public';
    public const LEGACY_ACTION = 'mao_tk_r2_public_download';
    private const CONTAINERS = array(
        'core/group',
        'core/columns',
        'core/column',
        'core/details',
        'core/cover',
        'core/media-text',
        'core/quote'
    );

    public static function register(): void
    {
        add_action('template_redirect', array(self::class, 'download'), 1);
    }

    public static function prefix(int $post_id): string
    {
        return add_query_arg('p', $post_id, home_url('/')) . '&' . self::QUERY . '=';
    }

    public static function legacy_prefix(int $post_id): string
    {
        return add_query_arg(array('action' => self::LEGACY_ACTION, 'post_id' => $post_id), admin_url('admin-post.php')) .
            '&ref=';
    }

    public static function reference(int $post_id, string $key): string
    {
        return self::prefix($post_id) . Runtime::reference_value($post_id, $key, true);
    }

    public static function download(): void
    {
        if (!array_key_exists(self::QUERY, $_GET)) {
            return;
        }

        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method !== 'GET' && $method !== 'POST') {
            header('Allow: GET, POST');
            wp_die(esc_html__('请从文章下载按钮发起请求。', 'mao-tk-zibll-toolkit'), '', array('response' => 405));
        }

        $post_id = is_singular('post') ? (int) get_queried_object_id() : 0;
        $ref = is_string($_GET[self::QUERY]) ? wp_unslash($_GET[self::QUERY]) : '';
        $key = $post_id ? Runtime::reference_key($post_id, $ref, true) : null;
        $access = $key !== null ? self::access($post_id) : 404;
        if ($access === 404) {
            wp_die(esc_html__('该公开下载链接已失效。', 'mao-tk-zibll-toolkit'), '', array('response' => 404));
        }
        if ($access === 403) {
            wp_die(esc_html__('当前账号没有该文章的阅读权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
        }

        $urls = array(self::prefix($post_id) . $ref, self::legacy_prefix($post_id) . $ref);
        if (!array_intersect($urls, self::published_urls($post_id))) {
            wp_die(esc_html__('该公开下载链接已失效。', 'mao-tk-zibll-toolkit'), '', array('response' => 404));
        }
        if ($method === 'GET' && !Session::returning()) {
            wp_safe_redirect(get_permalink($post_id));
            exit;
        }
        if ($method === 'POST') {
            $site = wp_parse_url(home_url('/'));
            $origin = $site['scheme'] . '://' . $site['host'] . (isset($site['port']) ? ':' . $site['port'] : '');
            $fetch_site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
            if (
                ($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin ||
                ($fetch_site !== '' && $fetch_site !== 'same-origin') ||
                Session::returning()
            ) {
                wp_die(esc_html__('请回到原文点击下载。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
            }
        }
        $url = self::prefix($post_id) . $ref;
        Runtime::send_download(Session::download_url($url, 'public:' . $post_id, $key));
    }

    /**
     * Parses a public download URL (current or legacy admin-post form) into its post ID and reference.
     *
     * @return array{post_id:int,ref:string,legacy:bool}|null
     */
    public static function parse(string $url): ?array
    {
        foreach (array(false, true) as $legacy) {
            $pattern = $legacy
                ? '/[?&]post_id=([1-9][0-9]{0,18})&ref=/'
                : '/[?&]p=([1-9][0-9]{0,18})&' . self::QUERY . '=/';
            if (!preg_match($pattern, $url, $match)) {
                continue;
            }
            $post_id = (int) $match[1];
            $prefix = $legacy ? self::legacy_prefix($post_id) : self::prefix($post_id);
            if (strpos($url, $prefix) === 0) {
                return array('post_id' => $post_id, 'ref' => substr($url, strlen($prefix)), 'legacy' => $legacy);
            }
        }
        return null;
    }

    /**
     * Visible layout blocks that may hold a public download card. Cards inside other blocks
     * (for example the theme's paid / reply-to-view content) are never treated as public.
     */
    public static function containers(): array
    {
        $containers = apply_filters('mao_tk_toolkit_r2_public_download_containers', self::CONTAINERS);
        return is_array($containers) ? array_values(array_filter($containers, 'is_string')) : self::CONTAINERS;
    }

    /** Return the same article access decision for the inline button and the download endpoint. */
    public static function access(int $post_id): int
    {
        $post = get_post($post_id);
        if (
            !($post instanceof \WP_Post) || $post->post_type !== 'post' ||
            $post->post_status !== 'publish' || $post->post_password !== '' ||
            !class_exists('WP_HTML_Tag_Processor') || !function_exists('zib_get_post_allow_view_data')
        ) {
            return 404;
        }
        return empty(zib_get_post_allow_view_data($post_id)['allow']) ? 403 : 200;
    }

    /** Public download hrefs published in a post's visible blocks, including synced patterns. */
    public static function published_urls(int $post_id): array
    {
        static $cache = array();
        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }
        $post = get_post($post_id);
        $urls = array();
        if ($post instanceof \WP_Post && class_exists('WP_HTML_Tag_Processor') && function_exists('parse_blocks')) {
            $budget = 20000;
            self::collect_cards(parse_blocks($post->post_content), self::containers(), $urls, array(), $budget);
        }
        return $cache[$post_id] = array_keys($urls);
    }

    private static function collect_cards(
        array $blocks,
        array $containers,
        array &$urls,
        array $path,
        int &$budget
    ): void {
        $stack = array_reverse($blocks);
        while ($stack && $budget-- > 0) {
            $block = array_pop($stack);
            if (!is_array($block)) {
                continue;
            }
            $name = $block['blockName'] ?? null;
            if ($name === 'core/block') {
                $ref = (int) ($block['attrs']['ref'] ?? 0);
                $synced = $ref > 0 && count($path) < 8 && !isset($path[$ref]) ? get_post($ref) : null;
                if (
                    $synced instanceof \WP_Post && $synced->post_type === 'wp_block' &&
                    $synced->post_status === 'publish' && (string) $synced->post_password === ''
                ) {
                    self::collect_cards(parse_blocks($synced->post_content), $containers, $urls, $path + array($ref => true), $budget);
                }
                continue;
            }
            if (in_array($name, $containers, true) && !empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                foreach (array_reverse($block['innerBlocks']) as $child) {
                    $stack[] = $child;
                }
                continue;
            }
            $html = (string) ($block['innerHTML'] ?? '');
            if ($name !== 'zibllblock/download' || strpos($html, 'file-download-btn') === false) {
                continue;
            }
            $tags = new \WP_HTML_Tag_Processor($html);
            while ($tags->next_tag(array('tag_name' => 'A', 'class_name' => 'file-download-btn'))) {
                $href = $tags->get_attribute('href');
                if (
                    is_string($href) &&
                    $tags->get_attribute('data-download-file') === null &&
                    self::parse($href) !== null
                ) {
                    $urls[$href] = true;
                }
            }
        }
    }
}
