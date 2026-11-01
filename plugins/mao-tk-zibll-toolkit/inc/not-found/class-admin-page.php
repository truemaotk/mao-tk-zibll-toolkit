<?php
namespace MaoTK\ZibllToolkit\NotFound;
use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;
final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-not-found';
    public const SAVE = 'mao_tk_toolkit_not_found_settings';
    private $manager; private $admin; private $screen = '';
    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }
    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_' . self::SAVE, array($this, 'save'));
        add_action('admin_post_mao_tk_not_found_refresh', array($this, 'refresh'));
    }
    public function menu(): void { $this->screen = (string) add_submenu_page(Admin::SLUG, '404 公益页面', '404 公益页面', 'manage_options', self::SLUG, array($this, 'render')); $this->admin->allow_screen($this->screen); }
    public function enqueue(string $hook): void
    {
        if ($this->screen !== '' && $this->screen === $hook && current_user_can('manage_options')) { wp_enqueue_style('mao-tk-not-found-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/not-found/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION); }
    }
    public function save(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有管理 404 页面的权限。', '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请通过设置页提交。', '', array('response' => 405)); }
        check_admin_referer(self::SAVE);
        try {
            if (!isset($_POST['not_found']) || !is_array($_POST['not_found']) || !isset($_POST['revision']) || !is_string($_POST['revision']) || !ctype_digit($_POST['revision'])) { throw new \InvalidArgumentException('设置格式无效。'); }
            $raw = wp_unslash($_POST['not_found']);
            foreach (array('charity_enabled','show_photo','show_search') as $key) {
                if (!isset($raw[$key]) || !is_string($raw[$key]) || !in_array($raw[$key], array('0','1'), true)) { throw new \InvalidArgumentException('开关格式无效。'); }
                $raw[$key] = $raw[$key] === '1';
            }
            $ok = (new Settings())->save($raw, (int) $_POST['revision']);
        } catch (\InvalidArgumentException $e) { wp_die(esc_html($e->getMessage()), '', array('response' => 400, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'not_found_notice' => $ok ? 'saved' : 'conflict'), admin_url('admin.php'))); exit;
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问此设置页的权限。', '', array('response' => 403)); }
        $state = (new Settings())->read(); $settings = $state['value'];
        $module = array('id' => Settings::MODULE, 'name' => '404 公益页面', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === Settings::MODULE) { $module = $record; break; } }
        $preview_url = add_query_arg(array('mao_tk_404_preview' => '1', '_mao_tk_404_nonce' => wp_create_nonce('mao_tk_404_preview')), home_url('/'));
        $feed_status = get_transient(Provider::STATUS);
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/not-found-settings.php';
    }
    public function refresh(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有刷新公益信息的权限。', '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请通过设置页提交。', '', array('response' => 405)); }
        check_admin_referer('mao_tk_not_found_refresh');
        $state = (new Settings())->read();
        if (!$state['valid'] || !$state['value']['charity_enabled']) { wp_die('请先开启公益信息并检查设置。', '', array('response' => 400)); }
        delete_transient(Provider::CACHE); delete_transient(Provider::RETRY); $feed = Provider::get(5);
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'not_found_notice' => $feed['record'] && !$feed['stale'] ? 'refreshed' : 'upstream-unavailable'), admin_url('admin.php'))); exit;
    }
}
