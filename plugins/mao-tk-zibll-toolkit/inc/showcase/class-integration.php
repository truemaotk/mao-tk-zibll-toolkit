<?php
namespace MaoTK\ZibllToolkit\Showcase;

defined('ABSPATH') || exit;

final class Integration
{
    public const REST = 'mao-tk-toolkit/v1';
    private static $owns_block = false;
    public static function supported(): bool { global $wp_version; return version_compare((string) $wp_version, '6.3', '>='); }
    public static function register(): void
    {
        add_action('init', array(__CLASS__, 'init'), 99);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'editor_assets'));
        add_action('add_meta_boxes_page', array(__CLASS__, 'meta_box'));
        add_action('save_post_page', array(__CLASS__, 'save_meta'), 10, 2);
        add_filter('theme_page_templates', array(__CLASS__, 'templates'));
    }
    public static function templates(array $templates): array
    {
        $templates[Settings::TEMPLATE] = Runtime::enabled() ? __('MaoTK 案例展示', 'mao-tk-zibll-toolkit') : __('MaoTK 案例展示（模块未启用）', 'mao-tk-zibll-toolkit'); return $templates;
    }
    public static function init(): void
    {
        register_post_meta('page', Settings::META, array('type' => 'string', 'single' => true, 'default' => '', 'show_in_rest' => true,
            'sanitize_callback' => static function ($value): string { return is_string($value) && ($value === '' || Settings::id($value)) ? $value : ''; },
            'auth_callback' => static function ($allowed, $key, $id): bool { return current_user_can('edit_post', (int) $id); }));
        wp_register_style('mao-tk-toolkit-showcase', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/showcase.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_register_script('mao-tk-toolkit-showcase-block', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/block.js', array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        wp_set_script_translations('mao-tk-toolkit-showcase-block', 'mao-tk-zibll-toolkit', MAO_TK_ZIBLL_TOOLKIT_DIR . 'languages');
        if (self::supported() && !\WP_Block_Type_Registry::get_instance()->is_registered('maotk/demo-showcase')) {
            register_block_type(MAO_TK_ZIBLL_TOOLKIT_DIR . 'blocks/showcase', array('render_callback' => array(__CLASS__, 'block'))); self::$owns_block = true;
        }
        foreach (array('mao_tk_showcase', 'maotk_demo_showcase', 'xt_demo_showcase') as $shortcode) { if (!shortcode_exists($shortcode)) { add_shortcode($shortcode, array(Runtime::class, 'shortcode')); } }
    }
    public static function owns_block(): bool { return self::$owns_block; }
    public static function categories(): array
    {
        $read = (new Settings())->read(); return $read['valid'] ? $read['value']['categories'] : array();
    }
    public static function editor_assets(): void
    {
        $screen = get_current_screen(); if (!$screen || $screen->post_type !== 'page' || !current_user_can('edit_pages')) { return; }
        wp_enqueue_script('mao-tk-toolkit-showcase-page', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/page-editor.js', array('wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        wp_set_script_translations('mao-tk-toolkit-showcase-page', 'mao-tk-zibll-toolkit', MAO_TK_ZIBLL_TOOLKIT_DIR . 'languages');
        wp_add_inline_script('mao-tk-toolkit-showcase-page', 'window.MaoTKShowcasePage=' . wp_json_encode(array('categories' => self::categories(), 'metaKey' => Settings::META, 'template' => Settings::TEMPLATE, 'enabled' => Runtime::enabled(), 'manageUrl' => admin_url('admin.php?page=' . AdminPage::SLUG)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
    public static function meta_box(): void { add_meta_box('mao-tk-showcase-category', __('MaoTK 案例分类', 'mao-tk-zibll-toolkit'), array(__CLASS__, 'render_meta_box'), 'page', 'side', 'default', array('__back_compat_meta_box' => true)); }
    public static function render_meta_box(\WP_Post $post): void
    {
        $current = (string) get_post_meta($post->ID, Settings::META, true); wp_nonce_field('mao_tk_showcase_page', 'mao_tk_showcase_page_nonce');
        echo ('<p><label for="mao-tk-showcase-category-select">' . esc_html__('选择“MaoTK 案例展示”模板后，页面自动显示对应分类。', 'mao-tk-zibll-toolkit') . '</label></p><select id="mao-tk-showcase-category-select" name="mao_tk_showcase_category" class="widefat"><option value="">' . esc_html__('全部案例', 'mao-tk-zibll-toolkit') . '</option>');
        $known = $current === ''; foreach (self::categories() as $category) { if ($current === $category['id']) { $known = true; } echo '<option value="' . esc_attr($category['id']) . '"' . selected($current, $category['id'], false) . '>' . esc_html($category['name']) . '</option>'; }
        if (!$known) { echo '<option selected value="' . esc_attr($current) . ('">' . esc_html__('原分类已移除，请重新选择', 'mao-tk-zibll-toolkit') . '</option>'); }
        echo '</select>';
    }
    public static function save_meta(int $id, \WP_Post $post): void
    {
        if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post', $id) || !isset($_POST['mao_tk_showcase_page_nonce']) || !is_string($_POST['mao_tk_showcase_page_nonce']) || !wp_verify_nonce(wp_unslash($_POST['mao_tk_showcase_page_nonce']), 'mao_tk_showcase_page')) { return; }
        $category = isset($_POST['mao_tk_showcase_category']) && is_string($_POST['mao_tk_showcase_category']) ? wp_unslash($_POST['mao_tk_showcase_category']) : '';
        if ($category !== '' && !Settings::id($category)) { return; }
        update_post_meta($id, Settings::META, $category);
    }
    public static function routes(): void
    {
        $admin = static function () { return current_user_can('manage_options') ? true : new \WP_Error('showcase_forbidden', __('你没有管理案例库的权限。', 'mao-tk-zibll-toolkit'), array('status' => rest_authorization_required_code())); };
        register_rest_route(self::REST, '/showcase', array(
            array('methods' => 'GET', 'permission_callback' => $admin, 'callback' => static function () { return rest_ensure_response((new Settings())->read()); }),
            array('methods' => 'PUT', 'permission_callback' => $admin, 'callback' => static function (\WP_REST_Request $request) {
                if (strlen($request->get_body()) > 1200000) { return new \WP_Error('showcase_size', __('案例库超过保存大小限制。', 'mao-tk-zibll-toolkit'), array('status' => 413)); }
                $payload = $request->get_json_params(); $revision = $request->get_header('if-match');
                if (!is_array($payload) || !is_string($revision) || $revision === '') { return new \WP_Error('showcase_invalid', __('保存内容或版本标识缺失。', 'mao-tk-zibll-toolkit'), array('status' => 400)); }
                $result = (new Settings())->save($payload, $revision); return is_wp_error($result) ? $result : rest_ensure_response($result);
            }),
        ));
        register_rest_route(self::REST, '/showcase/categories', array('methods' => 'GET', 'permission_callback' => static function () { return current_user_can('edit_pages') || current_user_can('edit_posts'); }, 'callback' => static function () { return rest_ensure_response(self::categories()); }));
        register_rest_route(self::REST, '/showcase/icon', array('methods' => 'POST', 'permission_callback' => static function () { return current_user_can('manage_options') && current_user_can('upload_files'); }, 'callback' => array(Media::class, 'import')));
    }
    public static function block(array $attributes): string
    {
        if (!Runtime::enabled()) { return ''; }
        $read = (new Settings())->read(); if (!$read['valid']) { return ''; }
        $legacy = ($attributes['source'] ?? 'legacy') !== 'central';
        if (!$legacy) { Runtime::enqueue(); return Renderer::render($read['value'], is_string($attributes['categoryId'] ?? null) ? $attributes['categoryId'] : '', array('blockAttributes' => $attributes)); }
        // Legacy block data remains local to that block. Never overwrite it with the central library.
        $value = Settings::defaults(); $settings = $value['settings'];
        foreach (array('title','description','showHeader','showDescription','columns','cardRadius','cardTitleSize','maxWidth') as $key) { if (array_key_exists($key, $attributes)) { $settings[$key] = $attributes[$key]; } }
        $settings['widthMode'] = 'custom'; $settings['themeMode'] = ($attributes['themeMode'] ?? 'auto') === 'manual' ? (($attributes['theme'] ?? '') === 'dark' ? 'dark' : 'light') : 'auto';
        foreach (array('buttonOneBg' => 'primaryBg', 'buttonOneHoverBg' => 'primaryHover', 'cardTitleColor' => 'titleLight') as $old => $new) { if (!empty($attributes[$old]) && is_string($attributes[$old]) && sanitize_hex_color($attributes[$old])) { $settings[$new] = $attributes[$old]; } }
        $settings['imageHeight'] = 320; $settings['imageHeightMobile'] = 285;
        $value['settings'] = $settings; $legacy_sizes = array();
        foreach (array_slice(is_array($attributes['cards'] ?? null) ? $attributes['cards'] : array(), 0, 300) as $index => $card) {
            if (!is_array($card)) { continue; } $item = array_intersect_key($card, Settings::card_defaults()); $item = array_replace(Settings::card_defaults(), $item); $item['id'] = Settings::id($item['id']) ? $item['id'] : 'legacy-' . $index; $item['name'] = is_string($item['name']) && $item['name'] !== '' ? $item['name'] : '案例名称'; $item['categoryIds'] = array();
            foreach (array('imageHeight', 'imageHeightMobile') as $key) { if (is_numeric($card[$key] ?? null)) { $legacy_sizes[$item['id']][$key] = min($key === 'imageHeight' ? 720 : 560, max(160, (int) $card[$key])); } }
            $value['cards'][] = $item;
        }
        foreach (array('maxWidth' => array(720,1440), 'cardRadius' => array(0,28), 'cardTitleSize' => array(14,24)) as $key => $range) { $value['settings'][$key] = max($range[0], min($range[1], (int) $value['settings'][$key])); }
        $clean = Settings::normalize($value); if (is_wp_error($clean)) { return ''; }
        foreach ($clean['cards'] as &$card) { $card = array_merge($card, $legacy_sizes[$card['id']] ?? array()); } unset($card);
        $colors = array();
        foreach (array('buttonOneBg'=>'one-bg','buttonOneText'=>'one-text','buttonOneBorder'=>'one-border','buttonOneHoverBg'=>'one-hover-bg','buttonOneHoverText'=>'one-hover-text','buttonOneHoverBorder'=>'one-hover-border','buttonTwoBg'=>'two-bg','buttonTwoText'=>'two-text','buttonTwoBorder'=>'two-border','buttonTwoHoverBg'=>'two-hover-bg','buttonTwoHoverText'=>'two-hover-text','buttonTwoHoverBorder'=>'two-hover-border','titleColor'=>'heading','descriptionColor'=>'description','eyebrowColor'=>'eyebrow','cardTitleColor'=>'card-title','cardDescriptionColor'=>'card-description') as $key=>$variable) { if (is_string($attributes[$key] ?? null) && sanitize_hex_color($attributes[$key])) { $colors['--legacy-' . $variable] = sanitize_hex_color($attributes[$key]); } }
        Runtime::enqueue(); return Renderer::render($clean, '', array('cards' => $clean['cards'], 'blockAttributes' => $attributes, 'legacyCss' => $colors, 'eyebrow' => $attributes['eyebrow'] ?? '', 'legacyTitleSize' => is_numeric($attributes['titleSize'] ?? null) && $attributes['titleSize'] > 0 ? min(72, max(12, (int) $attributes['titleSize'])) : 0));
    }
}
