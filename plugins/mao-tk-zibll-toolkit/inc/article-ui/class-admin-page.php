<?php
namespace MaoTK\ZibllToolkit\ArticleUI;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-article-ui';
    private $manager;
    private $admin;
    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }
    public function register(): void { add_action('admin_menu', array($this, 'menu'), 20); }
    public function menu(): void
    {
        $hook = add_submenu_page(Admin::SLUG, '文章 UI 组件', '文章 UI 组件', 'manage_options', self::SLUG, array($this, 'render'));
        if (is_string($hook)) { $this->admin->allow_screen($hook); }
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问此页面的权限。', '', array('response' => 403)); }
        $modules = array_values(array_filter($this->manager->all(), static function (array $record): bool { return strpos($record['id'], 'article-ui-') === 0; }));
        $valid = $this->manager->storage_is_valid();
        $notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? wp_unslash($_GET['mao_tk_notice']) : '';
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/article-ui-settings.php';
    }
}
