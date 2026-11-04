<?php
namespace MaoTK\ZibllToolkit\ArchiveHeader;

defined('ABSPATH') || exit;

final class Runtime
{
    private const TAXONOMIES = array(
        'category' => array('file' => 'category.php', 'filter' => 'cat', 'label' => '分类', 'icon' => 'fa-folder-open', 'count' => 'cat_post_count_s', 'cover' => 'cat_default_cover'),
        'post_tag' => array('file' => 'tag.php', 'filter' => 'tag', 'label' => '标签', 'icon' => 'fa-tags', 'count' => 'tag_post_count_s', 'cover' => 'tag_default_cover'),
        'topics' => array('file' => 'archive.php', 'filter' => 'topics', 'label' => '专题', 'icon' => 'fa-cube', 'count' => 'topics_post_count_s', 'cover' => 'topics_default_cover'),
    );

    // Reviewed Zibll 9.2 templates, with line endings normalized before comparison.
    private const TEMPLATE_HASHES = array(
        'category.php' => 'a93689a2c57b0efd728ca5e0a6b156548e4346c1619866d7e45432cc3113b87f',
        'tag.php' => '7dd02c44c0f174fd5c2372cf33ee575a31c84b48e57e436f3857842a396170f8',
        'archive.php' => '43df403cab2454ae522ba9f6c80d3b48031582bbdc10c8aa2c58cec1aea8384c',
        'template/category-topics.php' => 'f24d19f94767fe2e0fca222583cc43ca61f9653a522002e45d798223a1adf308',
    );

    public static function register(): void
    {
        add_filter('template_include', array(__CLASS__, 'select_template'), 90);
    }

    public static function select_template(string $template): string
    {
        if (is_admin() || is_feed() || is_embed() || !(is_category() || is_tag() || is_tax('topics'))) {
            return $template;
        }
        if (get_template() !== 'zibll' || !preg_match('/^9\.2(?:\.|$)/', (string) wp_get_theme(get_template())->get('Version'))) {
            return $template;
        }
        $term = get_queried_object();
        if (!($term instanceof \WP_Term) || !isset(self::TAXONOMIES[$term->taxonomy])) {
            return $template;
        }
        $needed = array('_pz', 'zib_get_search_link', 'zib_get_svg', 'zib_str_cut', 'zib_get_the_found_posts',
            'zib_get_taxonomy_img_url', 'zib_ajax_option_menu', 'zib_posts_list', 'zib_paging');
        if (is_category()) {
            $needed = array_merge($needed, array('zib_is_docs_mode', 'zib_get_post_cat_allow_view', 'zib_get_cat_allow_view_btn', 'zib_get_null'));
        }
        foreach ($needed as $function) {
            if (!function_exists($function)) {
                return $template;
            }
        }
        if (is_category() && zib_is_docs_mode()) {
            return $template;
        }
        if (!self::matches_parent_template($template, self::TAXONOMIES[$term->taxonomy]['file'])) {
            return $template;
        }
        if (is_tax('topics') && !self::matches_parent_template(locate_template('template/category-topics.php'), 'template/category-topics.php')) {
            return $template;
        }

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        return __DIR__ . '/archive.php';
    }

    private static function matches_parent_template(string $path, string $relative): bool
    {
        $expected = realpath(get_template_directory() . '/' . $relative);
        if (!$expected || !$path || realpath($path) !== $expected || !is_readable($expected)) {
            return false;
        }
        $source = file_get_contents($expected, false, null, 0, 32768);
        return is_string($source) && hash('sha256', str_replace("\r\n", "\n", $source)) === self::TEMPLATE_HASHES[$relative];
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('mao-tk-archive-header', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/archive-header/front.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
    }

    public static function render_header(\WP_Term $term, array $settings): void
    {
        $config = self::TAXONOMIES[$term->taxonomy];
        $page = max(1, (int) get_query_var('paged', 1));
        $show_count = $page === 1 && _pz($config['count'], $term->taxonomy !== 'topics');
        $description = $settings['show_description'] ? trim(wp_strip_all_tags(term_description($term->term_id))) : '';
        $cover = '';
        if ($settings['show_cover']) {
            $cover = zib_get_taxonomy_img_url($term->term_id, null, _pz($config['cover']));
            $cover = $cover ?: _pz('page_cover_img', get_template_directory_uri() . '/img/user_t.jpg');
            $cover = is_string($cover) ? esc_url($cover) : '';
        }

        // The original modal listens on main-search-btn and reads search-trem, search-type and trem-name.
        $search_label = '在此' . $config['label'] . '中搜索';
        // Zibll reads this attribute and inserts it into its search menu as HTML.
        $search_name = htmlspecialchars(esc_html(zib_str_cut($term->name, 0, 8)), ENT_QUOTES, 'UTF-8');
        $search = zib_get_search_link(array(
            'class' => 'mtk-archive-header__action',
            'con' => zib_get_svg('search') . '<span class="mtk-archive-header__sr">' . esc_html($search_label) . '</span>',
            'trem' => (int) $term->term_id,
            'trem_name' => $search_name,
            'type' => 'post',
            'placeholder' => '在' . $config['label'] . '[' . $term->name . ']中搜索',
        ));
        $restriction = $term->taxonomy === 'category'
            ? zib_get_cat_allow_view_btn($term->term_id, 'mtk-archive-header__action', '<i class="fa fa-unlock-alt" aria-hidden="true"></i><span class="mtk-archive-header__sr">文章阅读限制</span>')
            : '';
        require __DIR__ . '/header.php';
    }

    public static function render_list(\WP_Term $term): void
    {
        echo '<div class="posts-row ajaxpager">';
        if ($term->taxonomy === 'category') {
            $access = zib_get_post_cat_allow_view($term->term_id);
            if (!$access['allow']) {
                // Theme-provided access message and login actions, as in zib_post_cat_page_content().
                echo '<div class="ajax-item zib-widget mt20">' . zib_get_null(__('抱歉！您暂无查看此内容的权限', 'zib_language'), 20, 'null-cap.svg', '') . $access['not_html'] . '</div></div>';
                return;
            }
        }
        zib_ajax_option_menu(self::TAXONOMIES[$term->taxonomy]['filter']);
        zib_posts_list();
        zib_paging();
        echo '</div>';
    }
}
