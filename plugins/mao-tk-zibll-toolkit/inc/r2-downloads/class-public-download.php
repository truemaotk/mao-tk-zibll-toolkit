<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class PublicDownload
{
    public const QUERY = 'maotk_r2_public';
    public const LEGACY_ACTION = 'mao_tk_r2_public_download';
    private const CONTAINERS = array('core/group', 'core/columns', 'core/column');

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
        return add_query_arg(array('action' => self::LEGACY_ACTION, 'post_id' => $post_id), admin_url('admin-post.php')) . '&ref=';
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
            wp_die('请从文章下载按钮发起请求。', '', array('response' => 405));
        }

        $post_id = is_singular('post') ? (int) get_queried_object_id() : 0;
        $ref = is_string($_GET[self::QUERY]) ? wp_unslash($_GET[self::QUERY]) : '';
        $key = $post_id ? Runtime::reference_key($post_id, $ref, true) : null;
        $post = $key !== null ? get_post($post_id) : null;
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish' || $post->post_password !== ''
            || !class_exists('WP_HTML_Tag_Processor') || !function_exists('zib_get_post_allow_view_data')) {
            wp_die('该公开下载链接已失效。', '', array('response' => 404));
        }
        if (empty(zib_get_post_allow_view_data($post_id)['allow'])) {
            wp_die('当前账号没有该文章的阅读权限。', '', array('response' => 403));
        }

        $urls = array(self::prefix($post_id) . $ref, self::legacy_prefix($post_id) . $ref);
        if (strpos($post->post_content, $ref) === false || !self::has_published_card(parse_blocks($post->post_content), $urls)) {
            wp_die('该公开下载链接已失效。', '', array('response' => 404));
        }
        if ($method === 'GET' && !Session::returning()) {
            wp_safe_redirect(get_permalink($post_id));
            exit;
        }
        if ($method === 'POST') {
            $site = wp_parse_url(home_url('/'));
            $origin = $site['scheme'] . '://' . $site['host'] . (isset($site['port']) ? ':' . $site['port'] : '');
            $fetch_site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
            if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin || ($fetch_site !== '' && $fetch_site !== 'same-origin')
                || Session::returning()) {
                wp_die('请回到原文点击下载。', '', array('response' => 403));
            }
        }
        $url = self::prefix($post_id) . $ref;
        Runtime::send_download(Session::download_url($url, 'public:' . $post_id, $key));
    }

    private static function has_published_card(array $blocks, array $urls): bool
    {
        foreach ($blocks as $block) {
            $name = $block['blockName'];
            if (in_array($name, self::CONTAINERS, true) && self::has_published_card($block['innerBlocks'], $urls)) {
                return true;
            }
            if ($name !== 'zibllblock/download' || strpos($block['innerHTML'], 'file-download-btn') === false) {
                continue;
            }
            $tags = new \WP_HTML_Tag_Processor($block['innerHTML']);
            while ($tags->next_tag(array('tag_name' => 'A', 'class_name' => 'file-download-btn'))) {
                if (in_array($tags->get_attribute('href'), $urls, true) && $tags->get_attribute('data-download-file') === null) {
                    return true;
                }
            }
        }
        return false;
    }
}
