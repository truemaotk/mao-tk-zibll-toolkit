<?php
namespace MaoTK\ZibllToolkit\Maintenance;

defined('ABSPATH') || exit;

final class Runtime
{
    public static function conflict(): bool
    {
        $legacy = get_option('VaiPlugin', array());
        return defined('Vai_PLUGIN_DIR') && function_exists('VaiPlugin') && is_array($legacy) && !empty($legacy['is_maintenance']);
    }

    public static function register(): void
    {
        if (self::conflict()) { throw new \RuntimeException('旧维护插件仍在运行，请先关闭旧维护模式。'); }
        if (!(new Settings())->read()['valid']) { throw new \RuntimeException('维护配置异常，已保留原值并跳过维护拦截。'); }
        if (!is_admin() && !defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        add_action('template_redirect', array(self::class, 'intercept'), -100);
    }

    public static function bypass(): bool
    {
        if (is_admin() || current_user_can('manage_options') || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) { return true; }
        // Keep the actual native Zibll sign-in template reachable, including its reset tab.
        if (is_page_template('pages/user-sign.php')) { return true; }
        if (is_feed() || is_trackback() || is_robots() || is_favicon() || wp_is_json_request()) { return true; }
        global $pagenow;
        return $pagenow === 'wp-login.php';
    }

    public static function intercept(): void
    {
        if (self::bypass()) { return; }
        $state = (new Settings())->read();
        if (!$state['valid']) { return; }
        Renderer::respond($state['value']);
        exit;
    }
}
