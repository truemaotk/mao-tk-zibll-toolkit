<?php
namespace MaoTK\ZibllToolkit\ArchiveHeader;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-archive-header';
    private const ACTION = 'mao_tk_toolkit_save_archive_header';
    private $manager;
    private $admin;

    public function __construct(ModuleManager $manager, Admin $admin)
    {
        $this->manager = $manager;
        $this->admin = $admin;
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 21);
        add_action('admin_post_' . self::ACTION, array($this, 'save'));
    }

    public function menu(): void
    {
        $screen = add_submenu_page(Admin::SLUG, '归档页顶部', '归档页顶部', 'manage_options', self::SLUG, array($this, 'render'));
        $this->admin->allow_screen((string) $screen);
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('需要管理员权限。', '', array('response' => 403));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('请通过设置页提交。', '', array('response' => 405));
        }
        check_admin_referer(self::ACTION);
        $values = array();
        foreach (Settings::defaults() as $key => $default) {
            $input = $_POST[$key] ?? '0';
            if (!in_array($input, array('0', '1'), true)) {
                wp_die('归档页顶部设置无效。', '', array('response' => 400));
            }
            $values[$key] = $input === '1';
        }
        update_option(Settings::OPTION, $values, false);
        $notice = get_option(Settings::OPTION) === $values ? 'saved' : 'save-failed';
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&archive_header_notice=' . $notice));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('需要管理员权限。', '', array('response' => 403));
        }
        $switch_module = array('id' => Settings::MODULE, 'name' => '归档页顶部', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $module) {
            if ($module['id'] === Settings::MODULE) {
                $switch_module = $module;
                break;
            }
        }
        $switch_valid = $this->manager->storage_is_valid();
        $switch_return_page = self::SLUG;
        $settings = Settings::read();
        $action = self::ACTION;
        $notice = $_GET['archive_header_notice'] ?? $_GET['mao_tk_notice'] ?? '';
        require __DIR__ . '/settings-page.php';
    }
}
