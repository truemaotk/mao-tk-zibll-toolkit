<?php
namespace MaoTK\ZibllToolkit\NotFound;
defined('ABSPATH') || exit;
final class Runtime
{
    private static $enabled = false;
    public static function enable(): void { self::$enabled = true; }
    public static function enabled(): bool { return self::$enabled && get_template() === 'zibll' && (new Settings())->read()['valid']; }
    public static function register(): void
    {
        add_filter('404_template', array(__CLASS__, 'template'), 90);
        add_filter('template_include', array(__CLASS__, 'preview_template'), 95);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 25);
    }
    public static function preview(): bool
    {
        return isset($_GET['mao_tk_404_preview'], $_GET['_mao_tk_404_nonce']) && $_GET['mao_tk_404_preview'] === '1' && is_string($_GET['_mao_tk_404_nonce']) && current_user_can('manage_options') && (bool) wp_verify_nonce(wp_unslash($_GET['_mao_tk_404_nonce']), 'mao_tk_404_preview');
    }
    private static function html(): bool
    {
        return !is_admin() && !is_feed() && !is_embed() && !wp_is_json_request() && !(defined('REST_REQUEST') && REST_REQUEST) && !wp_doing_ajax();
    }
    public static function template(string $template): string
    {
        return self::enabled() && is_404() && self::html() ? MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/not-found/template.php' : $template;
    }
    public static function preview_template(string $template): string
    {
        return get_template() === 'zibll' && self::preview() && self::html() ? MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/not-found/template.php' : $template;
    }
    public static function enqueue(): void
    {
        if (!self::html() || get_template() !== 'zibll' || (!(self::enabled() && is_404()) && !self::preview())) { return; }
        wp_enqueue_style('mao-tk-not-found', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/not-found/view.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
    }
    public static function search_enabled(array $settings): bool { return $settings['show_search'] && function_exists('_pz') && _pz('404_search_s', true) && function_exists('zib_get_main_search'); }
}
