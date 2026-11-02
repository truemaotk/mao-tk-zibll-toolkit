<?php
namespace MaoTK\ZibllToolkit\ProfilePresets;

defined('ABSPATH') || exit;

final class Runtime
{
    public const ACTION = 'mao_tk_toolkit_profile_presets';
    public const NONCE = 'mao_tk_toolkit_profile_presets_self';
    private static $used = false;

    public static function conflict(): bool { return function_exists('zibll_avatar_get_option'); }
    public static function available(): bool { return function_exists('zib_update_user_meta') && function_exists('zib_get_user_meta'); }

    public static function register(): void
    {
        if (self::conflict()) { throw new \RuntimeException('旧预设头像插件仍启用，请先停用旧插件，原有图片与设置保留。'); }
        if (!self::available() || !(new Settings())->read()['valid']) { throw new \RuntimeException('用户资料接口未就绪或预设配置无效。'); }
        add_action('wp', array(self::class, 'prepare_author'));
        add_action('locate_template_user_center', array(self::class, 'prepare'));
        add_filter('author_header_drop_lists', array(self::class, 'author_menu'), 20, 2);
        add_filter('user_page_header_desc', array(self::class, 'center_buttons'), 20, 2);
        add_action('wp_enqueue_scripts', array(self::class, 'enqueue'));
        add_action('wp_footer', array(self::class, 'dialog'), 20);
        add_action('wp_ajax_' . self::ACTION, array(self::class, 'ajax'));
        add_action('user_register', array(self::class, 'registered'), 100);
    }

    public static function prepare(): void
    {
        if (is_user_logged_in() && current_user_can('read')) { self::$used = true; }
    }

    public static function prepare_author(): void
    {
        if (is_author() && (int) get_queried_object_id() === get_current_user_id()) { self::prepare(); }
    }

    private static function entries(bool $menu): string
    {
        $state = (new Settings())->read(); if (!$state['valid']) { return ''; } $s = $state['value']; $html = '';
        foreach (array('avatar', 'cover') as $kind) {
            if (!$s[$kind . '_enabled']) { continue; }
            $button = '<button type="button" class="mao-tk-profile-entry" data-mao-tk-profile-open="' . esc_attr($kind) . '">' . esc_html($s[$kind . '_label']) . '</button>';
            $html .= $menu ? '<li>' . $button . '</li>' : $button;
        }
        return $html;
    }

    public static function author_menu(string $lists, $author_id): string
    {
        if (!get_current_user_id() || (int) $author_id !== get_current_user_id() || !current_user_can('read')) { return $lists; }
        return $lists . self::entries(true);
    }

    public static function center_buttons(string $html, $user_id): string
    {
        if (!get_current_user_id() || (int) $user_id !== get_current_user_id() || !current_user_can('read')) { return $html; }
        return $html . '<span class="mao-tk-profile-entries">' . self::entries(false) . '</span>';
    }

    public static function enqueue(): void
    {
        if (!self::$used || !get_current_user_id()) { return; }
        \MaoTK\ZibllToolkit\LoadingUI::enqueue();
        wp_enqueue_style('mao-tk-profile-presets', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/profile-presets/frontend.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-profile-presets', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/profile-presets/frontend.js', array('mao-tk-toolkit-loading'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    public static function dialog(): void
    {
        if (!self::$used || !get_current_user_id()) { return; }
        $state = (new Settings())->read(); if (!$state['valid']) { return; } $settings = $state['value'];
        if (!$settings['avatar_enabled'] && !$settings['cover_enabled']) { return; }
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/profile-presets-dialog.php';
    }

    /** Only the caller's own account, and only a currently configured server-side preset. */
    public static function apply(int $user_id, string $kind, string $id, array $settings): bool
    {
        if (!self::available() || !get_userdata($user_id) || !in_array($kind, array('avatar', 'cover'), true) || !$settings[$kind . '_enabled']) { return false; }
        $item = Catalog::find($kind, $id, $settings); if (!$item) { return false; }
        $key = $kind === 'avatar' ? 'custom_avatar' : 'cover_image';
        $before_url = zib_get_user_meta($user_id, $key, true); $before_id = zib_get_user_meta($user_id, $key . '_id', true);
        // Zero marks a reusable preset, never a shared attachment to delete during a later native upload.
        zib_update_user_meta($user_id, $key . '_id', 0);
        zib_update_user_meta($user_id, $key, $item['url']);
        if ((string) zib_get_user_meta($user_id, $key, true) !== $item['url'] || (int) zib_get_user_meta($user_id, $key . '_id', true) !== 0) {
            zib_update_user_meta($user_id, $key . '_id', $before_id); zib_update_user_meta($user_id, $key, $before_url); return false;
        }
        update_user_meta($user_id, 'mao_tk_profile_preset_' . $kind, $id);
        return true;
    }

    public static function registered($user_id): void
    {
        $state = (new Settings())->read();
        if (!$state['valid'] || !$state['value']['random_on_register'] || !$state['value']['avatar_enabled'] || !self::available()) { return; }
        if (zib_get_user_meta((int) $user_id, 'custom_avatar', true) || zib_get_user_meta((int) $user_id, 'custom_avatar_id', true)) { return; }
        $rows = Catalog::all('avatar', $state['value']);
        if ($rows) { self::apply((int) $user_id, 'avatar', $rows[wp_rand(0, count($rows) - 1)]['id'], $state['value']); }
    }

    public static function ajax(): void
    {
        if (!is_user_logged_in() || !current_user_can('read')) { wp_send_json_error(array('message' => '请先登录自己的账号。'), 403); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_send_json_error(array('message' => '请通过选择窗口操作。'), 405); }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) { wp_send_json_error(array('message' => '验证已过期，请刷新页面后重试。'), 403); }
        $state = (new Settings())->read();
        if (!$state['valid'] || self::conflict() || !self::available()) { wp_send_json_error(array('message' => '预设功能暂不可用。'), 503); }
        $s = $state['value']; $kind = isset($_POST['kind']) && is_string($_POST['kind']) ? wp_unslash($_POST['kind']) : '';
        if (!in_array($kind, array('avatar', 'cover'), true) || !$s[$kind . '_enabled']) { wp_send_json_error(array('message' => '此类预设未开启。'), 403); }
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : '';
        if ($operation === 'list') {
            $page = isset($_POST['page']) && is_string($_POST['page']) && preg_match('/^[1-9][0-9]{0,4}$/D', $_POST['page']) ? (int) $_POST['page'] : 1;
            $result = Catalog::page($kind, $s, $page); $key = $kind === 'avatar' ? 'custom_avatar' : 'cover_image';
            $result['current_url'] = (string) zib_get_user_meta(get_current_user_id(), $key, true);
            wp_send_json_success($result);
        }
        if ($operation !== 'save') { wp_send_json_error(array('message' => '操作无效。'), 400); }
        $id = isset($_POST['preset_id']) && is_string($_POST['preset_id']) ? wp_unslash($_POST['preset_id']) : '';
        $item = Catalog::find($kind, $id, $s);
        if (!$item) { wp_send_json_error(array('message' => '这张图片已不可选，请刷新预设列表。'), 400); }
        if (!self::apply(get_current_user_id(), $kind, $id, $s)) { wp_send_json_error(array('message' => '保存失败，请稍后重试。'), 500); }
        wp_send_json_success(array('message' => $kind === 'avatar' ? '头像已更新。' : '背景已更新。', 'url' => $item['url'], 'kind' => $kind, 'user_id' => get_current_user_id()));
    }
}
