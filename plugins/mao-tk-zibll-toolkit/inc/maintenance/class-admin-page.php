<?php
namespace MaoTK\ZibllToolkit\Maintenance;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-maintenance';
    public const SAVE = 'mao_tk_toolkit_maintenance_save';
    public const PREVIEW = 'mao_tk_toolkit_maintenance_preview';
    private $manager;
    private $admin;
    private $screen = '';

    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_' . self::SAVE, array($this, 'save'));
        add_action('admin_post_' . self::PREVIEW, array($this, 'preview'));
    }

    public function menu(): void
    {
        $this->screen = (string) add_submenu_page(Admin::SLUG, '站点维护页面', '维护页面', 'manage_options', self::SLUG, array($this, 'render'));
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $hook): void
    {
        if ($this->screen === '' || $hook !== $this->screen || !current_user_can('manage_options')) { return; }
        wp_enqueue_style('mao-tk-maintenance-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/maintenance/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        if (current_user_can('upload_files')) { wp_enqueue_media(); }
        wp_enqueue_script('mao-tk-maintenance-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/maintenance/admin.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    private static function authorize(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有管理维护页面的权限。', '', array('response' => 403)); }
    }

    public function save(): void
    {
        self::authorize();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请通过设置表单提交。', '', array('response' => 405)); }
        check_admin_referer(self::SAVE);
        $operation = isset($_POST['maintenance_operation']) && is_string($_POST['maintenance_operation']) ? $_POST['maintenance_operation'] : '';
        if (!in_array($operation, array('save', 'preview', 'import'), true)) { wp_die('操作无效。', '', array('response' => 400)); }
        $store = new Settings(); $state = $store->read();
        if (!$state['valid']) { wp_die('维护配置格式异常，原值已保留。请先检查配置，未执行保存或导入。', '', array('response' => 400)); }
        try {
            if ($operation === 'import') {
                if ($state['exists']) { throw new \InvalidArgumentException('当前已有维护页配置，未覆盖。请手工核对旧配置后填写。'); }
                $value = $store->legacy();
            } else {
                if (!isset($_POST['maintenance']) || !is_array($_POST['maintenance'])) { throw new \InvalidArgumentException('设置格式无效。'); }
                $value = Settings::normalize(wp_unslash($_POST['maintenance']));
            }
            if ($operation === 'preview') { Renderer::respond($value, true); exit; }
            $saved = $store->save($value);
        } catch (\InvalidArgumentException $error) {
            wp_die(esc_html($error->getMessage()), '', array('response' => 400, 'back_link' => true));
        }
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'maintenance_notice' => $saved ? ($operation === 'import' ? 'imported' : 'saved') : 'failed'), admin_url('admin.php')));
        exit;
    }

    public function preview(): void
    {
        self::authorize();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { wp_die('请使用维护页的预览入口。', '', array('response' => 405)); }
        check_admin_referer(self::PREVIEW);
        $state = (new Settings())->read();
        if (!$state['valid']) { wp_die('维护配置格式异常，未执行预览。', '', array('response' => 400)); }
        Renderer::respond($state['value'], true);
        exit;
    }

    public function render(): void
    {
        self::authorize();
        $state = (new Settings())->read(); $settings = $state['value'];
        $module = array('id' => Settings::MODULE, 'name' => '站点维护页面', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === Settings::MODULE) { $module = $record; break; } }
        $preview_url = wp_nonce_url(admin_url('admin-post.php?action=' . self::PREVIEW), self::PREVIEW);
        $notice = isset($_GET['maintenance_notice']) && is_string($_GET['maintenance_notice']) ? $_GET['maintenance_notice'] : '';
        $conflict = Runtime::conflict();
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/maintenance-settings.php';
    }
}
