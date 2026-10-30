<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;

final class ArticleData
{
    public const PROPERTY_META = '_mao_tk_toolkit_top_info_property';
    public const LEGACY_PROPERTY_META = 'article_property';
    public const STATS_META = '_moxing_top_info_reading_stats';
    public const CACHE_META = '_mao_tk_toolkit_top_info_stats';

    public function property(int $post_id): string
    {
        $key = metadata_exists('post', $post_id, self::PROPERTY_META) ? self::PROPERTY_META : self::LEGACY_PROPERTY_META;
        $value = get_post_meta($post_id, $key, true);
        return is_string($value) ? sanitize_text_field($value) : '';
    }

    public static function stats(string $content, string $version = ''): array
    {
        $clean = html_entity_decode(wp_strip_all_tags(strip_shortcodes($content)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $compact = preg_replace('/[\p{Z}\s]+/u', '', $clean);
        if (!is_string($compact)) { $compact = preg_replace('/\s+/', '', $clean) ?? ''; }
        $count = function_exists('mb_strlen') ? mb_strlen($compact, 'UTF-8') : preg_match_all('/./us', $compact);
        $count = $count === false ? strlen($compact) : $count;
        return array('version' => $version, 'word_count' => max(0, (int) $count), 'read_time' => max(1, (int) ceil($count / 200)));
    }

    private function reading_stats($post): array
    {
        $version = (string) $post->post_modified_gmt;
        if ($version === '' || $version === '0000-00-00 00:00:00') { $version = (string) $post->post_modified; }
        foreach (array(self::CACHE_META, self::STATS_META) as $key) {
            $cached = get_post_meta((int) $post->ID, $key, true);
            if (is_array($cached) && ($cached['version'] ?? null) === $version
                && isset($cached['word_count'], $cached['read_time']) && is_numeric($cached['word_count']) && is_numeric($cached['read_time'])) {
                return array('version' => $version, 'word_count' => max(0, (int) $cached['word_count']), 'read_time' => max(1, (int) $cached['read_time']));
            }
        }
        // A cache miss is calculated in memory; neither preview nor public reads write meta.
        return self::stats((string) $post->post_content, $version);
    }

    public function refresh_stats(int $post_id, $post, bool $update): void
    {
        if (!$post || $post->post_type !== 'post' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !current_user_can('edit_post', $post_id)) { return; }
        $version = (string) $post->post_modified_gmt;
        if ($version === '' || $version === '0000-00-00 00:00:00') { $version = (string) $post->post_modified; }
        // Derived from already-saved content, not a custom request payload. Old cache stays intact.
        update_post_meta($post_id, self::CACHE_META, self::stats((string) $post->post_content, $version));
    }

    /** Only front-end callers may invoke the inherited theme counter API. */
    private function counter(string $function, string $fallback): string
    {
        if (!is_callable($function)) { return $fallback; }
        $level = ob_get_level();
        ob_start();
        try {
            $result = call_user_func($function, '', '');
            $echoed = (string) ob_get_contents();
            $result = is_scalar($result) && !is_bool($result) && (string) $result !== '' ? (string) $result : $echoed;
            $result = trim(wp_strip_all_tags($result));
            return $result !== '' ? $result : $fallback;
        } catch (\Throwable $error) {
            return $fallback;
        } finally {
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }

    public function collect($post, bool $preview = false): array
    {
        $id = (int) $post->ID;
        $author_id = (int) $post->post_author;
        $product = $post->post_type === 'product';
        $categories = $product ? wp_get_post_terms($id, 'product_cat') : get_the_category($id);
        $category = is_array($categories) && $categories ? reset($categories) : null;
        $category_url = $category ? ($product ? get_term_link($category) : get_category_link((int) $category->term_id)) : '';
        $tags = $product ? wp_get_post_terms($id, 'product_tag') : get_the_tags($id);
        $thumbnail = '';
        if (!$preview && function_exists('zib_post_thumbnail')) {
            $level = ob_get_level();
            ob_start();
            try {
                $image = \zib_post_thumbnail('large', 'fit-cover', false, $post);
                $thumbnail = is_string($image) && $image !== '' ? $image : (string) ob_get_contents();
            } catch (\Throwable $error) {
                $thumbnail = '';
            } finally {
                while (ob_get_level() > $level) { ob_end_clean(); }
            }
        }
        if ($thumbnail === '') {
            $url = wp_get_attachment_image_url(get_post_thumbnail_id($id), 'large');
            $thumbnail = is_string($url) && $url !== '' ? '<img src="' . esc_url($url) . '" alt="" loading="eager">' : '';
        }
        return array(
            'title' => get_the_title($id), 'thumbnail' => $thumbnail,
            'property' => $this->property($id),
            'category' => $category ? (string) $category->name : '',
            'category_url' => is_wp_error($category_url) ? '' : (string) $category_url,
            'tags' => is_array($tags) ? array_map(static function ($tag) { return (string) $tag->name; }, array_slice(array_values($tags), 0, 2)) : array(),
            'stats' => $this->reading_stats($post), 'date' => get_the_date('Y-m-d', $id),
            'author' => (string) get_the_author_meta('display_name', $author_id),
            'author_url' => get_author_posts_url($author_id), 'author_posts' => count_user_posts($author_id, $product ? 'product' : 'post', true), 'author_unit' => $product ? '件产品' : '篇文章',
            // Preview never runs theme counter functions or the public article template.
            'views' => $preview ? '—' : $this->counter('get_post_view_count', '—'),
            'comments' => $preview ? number_format_i18n((int) get_comments_number($id)) : $this->counter('get_post_comment_count', number_format_i18n((int) get_comments_number($id))),
        );
    }

    public static function sample(array $settings): array
    {
        return array(
            'title' => '把每一次灵感，写成值得分享的故事',
            'thumbnail' => '<img src="' . esc_url(MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/images/maotk-icon.svg') . '" alt="">',
            'property' => $settings['properties'][0] ?? '', 'category' => '生活随笔', 'category_url' => '#',
            'tags' => array('记录', '灵感'), 'stats' => array('word_count' => 1680, 'read_time' => 9),
            'date' => '2026-10-29', 'author' => 'Mao TK', 'author_url' => '#', 'author_posts' => 24,
            'views' => '1,280', 'comments' => '16',
        );
    }
}
