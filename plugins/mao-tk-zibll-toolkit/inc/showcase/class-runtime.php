<?php
namespace MaoTK\ZibllToolkit\Showcase;

defined('ABSPATH') || exit;

final class Runtime
{
    private static $enabled = false;
    public static function enabled(): bool { return self::$enabled; }
    public static function enable(): void
    {
        self::$enabled = true;
        add_filter('theme_page_templates', array(__CLASS__, 'templates'));
        add_filter('the_content', array(__CLASS__, 'content'), 30);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'maybe_enqueue'), 25);
    }
    public static function templates(array $templates): array { $templates[Settings::TEMPLATE] = 'MaoTK 案例展示'; return $templates; }
    public static function selected(int $id): bool { return $id > 0 && get_post_type($id) === 'page' && get_page_template_slug($id) === Settings::TEMPLATE; }
    public static function enqueue(): void
    {
        \MaoTK\ZibllToolkit\LoadingUI::enqueue();
        wp_enqueue_style('mao-tk-toolkit-showcase', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/showcase.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-toolkit-showcase', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/view.js', array('mao-tk-toolkit-loading'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }
    public static function maybe_enqueue(): void
    {
        if (!self::$enabled || !is_singular()) { return; }
        $post = get_queried_object(); if (!$post instanceof \WP_Post) { return; }
        $needed = self::selected($post->ID) || (Integration::owns_block() && has_block('maotk/demo-showcase', $post->post_content));
        global $shortcode_tags;
        foreach (array('mao_tk_showcase','maotk_demo_showcase','xt_demo_showcase') as $tag) { if (($shortcode_tags[$tag] ?? null) === array(__CLASS__, 'shortcode') && has_shortcode($post->post_content, $tag)) { $needed = true; } }
        if ($needed) { self::enqueue(); }
    }
    public static function content(string $content): string
    {
        if (!self::$enabled || is_admin() || is_feed() || !is_page() || post_password_required() || (defined('REST_REQUEST') && REST_REQUEST)) { return $content; }
        $id = (int) get_the_ID();
        if ($id !== (int) get_queried_object_id() || !self::selected($id)) { return $content; }
        // Explicit block/shortcode placements take precedence; never output a second library.
        $post = get_post($id);
        if ($post && (has_block('maotk/demo-showcase', $post->post_content) || has_shortcode($post->post_content, 'mao_tk_showcase') || has_shortcode($post->post_content, 'maotk_demo_showcase') || has_shortcode($post->post_content, 'xt_demo_showcase'))) { return $content; }
        $stored = (new Settings())->read(); if (!$stored['valid']) { return $content; }
        $category = (string) get_post_meta($id, Settings::META, true);
        self::enqueue(); return $content . Renderer::render($stored['value'], $category, array('showHeader' => false));
    }
    public static function shortcode($attributes = array(), $content = null, string $tag = 'mao_tk_showcase'): string
    {
        if (!self::$enabled) { return ''; }
        $read = (new Settings())->read(); if (!$read['valid']) { return ''; }
        $attributes = is_array($attributes) ? $attributes : array();
        $attrs = shortcode_atts(array('category' => '', 'columns' => '', 'theme' => '', 'source' => ''), $attributes, 'mao_tk_showcase');
        if ($tag !== 'mao_tk_showcase' && $attrs['source'] !== 'central' && !array_key_exists('category', $attributes)) {
            $raw = json_decode((string) file_get_contents(MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/showcase/legacy-cards.json'), true); $legacy = Settings::defaults();
            foreach (is_array($raw) ? $raw : array() as $card) {
                $item = array_replace(Settings::card_defaults(), $card); $item['buttonOneText'] = '查看案例'; $item['buttonOneUrl'] = $item['siteUrl']; $item['buttonTwoText'] = '立即咨询'; $item['buttonTwoUrl'] = 'https://work.weixin.qq.com/kfid/kfca9a9e52dce197f72'; $item['imageAlt'] = $item['name'] . ' 网站首页截图'; $legacy['cards'][] = $item;
            }
            $legacy['settings']['title'] = '独立站建站案例'; $legacy['settings']['themeMode'] = $attrs['theme'] === 'light' ? 'light' : 'dark'; $legacy['settings']['imageHeight'] = 320; $legacy['settings']['imageHeightMobile'] = 285;
            if (in_array((string) $attrs['columns'], array('2','3'), true)) { $legacy['settings']['columns'] = (int) $attrs['columns']; }
            self::enqueue(); return Renderer::render($legacy);
        }
        if (in_array((string) $attrs['columns'], array('2','3'), true)) { $read['value']['settings']['columns'] = (int) $attrs['columns']; }
        if (in_array($attrs['theme'], array('light','dark','auto'), true)) { $read['value']['settings']['themeMode'] = $attrs['theme']; }
        self::enqueue(); return Renderer::render($read['value'], $attrs['category'] === '' || Settings::id($attrs['category']) ? $attrs['category'] : '__invalid_category__');
    }
}
