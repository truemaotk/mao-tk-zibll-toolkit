<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;

final class ContentSearch
{
    public static function types(): array
    {
        return post_type_exists('product') ? array('post', 'product') : array('post');
    }

    public static function can_preview($post): bool
    {
        return is_object($post) && isset($post->ID, $post->post_type, $post->post_status)
            && in_array($post->post_type, self::types(), true)
            && in_array($post->post_status, array('publish', 'draft', 'pending', 'private', 'future'), true)
            && current_user_can('edit_post', (int) $post->ID) && current_user_can('read_post', (int) $post->ID);
    }

    /** Bounded title/ID search. Every returned item receives an object permission check. */
    public function find(string $query): array
    {
        $query = trim(sanitize_text_field($query));
        $candidates = array();
        if (preg_match('/^[1-9][0-9]{0,9}$/D', $query)) {
            $exact = get_post((int) $query);
            if (self::can_preview($exact)) { $candidates[(int) $exact->ID] = $exact; }
        }
        $args = array('post_type' => self::types(), 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => 40, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false);
        if ($query !== '') { $args['s'] = $query; $args['search_columns'] = array('post_title'); }
        foreach (get_posts($args) as $post) {
            if (!self::can_preview($post)) { continue; }
            // WordPress 6.0 does not support search_columns; still enforce title-only results.
            if ($query !== '' && preg_match('/' . preg_quote($query, '/') . '/iu', get_the_title((int) $post->ID)) !== 1) { continue; }
            $candidates[(int) $post->ID] = $post;
        }
        $items = array();
        $statuses = array('publish' => __('已发布', 'mao-tk-zibll-toolkit'), 'draft' => __('草稿', 'mao-tk-zibll-toolkit'), 'pending' => __('待审核', 'mao-tk-zibll-toolkit'), 'private' => __('私密', 'mao-tk-zibll-toolkit'), 'future' => __('定时', 'mao-tk-zibll-toolkit'));
        foreach (array_slice(array_values($candidates), 0, 20) as $post) {
            $items[] = array('id' => (int) $post->ID, 'title' => wp_strip_all_tags(get_the_title((int) $post->ID)), 'type' => $post->post_type === 'product' ? __('产品', 'mao-tk-zibll-toolkit') : __('文章', 'mao-tk-zibll-toolkit'), 'status' => $statuses[$post->post_status]);
        }
        return array('items' => $items, 'limited' => count($candidates) > 20);
    }
}
