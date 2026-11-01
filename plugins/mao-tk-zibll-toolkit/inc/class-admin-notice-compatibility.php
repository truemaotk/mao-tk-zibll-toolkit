<?php
namespace MaoTK\ZibllToolkit;
defined('ABSPATH') || exit;

/** Appearance only: keep Zibll's notice text, permission checks, shipping link and dismissal. */
final class AdminNoticeCompatibility
{
    public static function register(): void { add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'), 100); }
    public static function enqueue(): void
    {
        if (!is_admin() || get_template() !== 'zibll') { return; }
        wp_enqueue_style('mao-tk-toolkit-admin-notices', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/admin-notices.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
    }
}
