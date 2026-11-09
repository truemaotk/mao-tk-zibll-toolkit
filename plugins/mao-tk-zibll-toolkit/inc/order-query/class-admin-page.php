<?php
namespace MaoTK\ZibllToolkit\OrderQuery;
use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;
final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-order-query';
    public const SAVE = 'mao_tk_order_query_settings';
    private $manager; private $admin; private $screen = '';
    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }
    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_post_' . self::SAVE, array($this, 'save'));
        add_action('admin_post_mao_tk_order_query_create_page', array($this, 'create_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    }
    public function menu(): void { $this->screen = (string) add_submenu_page(Admin::SLUG, __('订单查询页面', 'mao-tk-zibll-toolkit'), __('订单查询', 'mao-tk-zibll-toolkit'), 'manage_options', self::SLUG, array($this, 'render')); $this->admin->allow_screen($this->screen); }
    public function enqueue(string $hook): void { if ($this->screen !== '' && $hook === $this->screen && current_user_can('manage_options')) { wp_enqueue_style('mao-tk-order-query-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/order-query/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION); } }
    private function authorize(string $nonce): void
    {
        if (!current_user_can('manage_options')) { wp_die(__('你没有管理订单查询页面的权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die(__('请通过设置页提交。', 'mao-tk-zibll-toolkit'), '', array('response' => 405)); }
        check_admin_referer($nonce);
    }
    public function save(): void
    {
        $this->authorize(self::SAVE);
        try {
            if (!isset($_POST['order_query']) || !is_array($_POST['order_query']) || !isset($_POST['revision']) || !is_string($_POST['revision']) || !ctype_digit($_POST['revision'])) { throw new \InvalidArgumentException(__('设置格式无效。', 'mao-tk-zibll-toolkit')); }
            $raw = wp_unslash($_POST['order_query']);
            if (!isset($raw['history_enabled']) || !is_string($raw['history_enabled']) || !in_array($raw['history_enabled'], array('0','1'), true)) { throw new \InvalidArgumentException(__('记录开关无效。', 'mao-tk-zibll-toolkit')); }
            $raw['history_enabled'] = $raw['history_enabled'] === '1';
            $ok = (new Settings())->save($raw, (int) $_POST['revision']);
        } catch (\InvalidArgumentException $e) { wp_die(esc_html(translate($e->getMessage(), 'mao-tk-zibll-toolkit')), '', array('response' => 400, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'order_notice' => $ok ? 'saved' : 'conflict'), admin_url('admin.php'))); exit;
    }
    public static function pages(): array { return get_posts(array('post_type' => 'page', 'post_status' => array('publish','draft','pending','private','future'), 'posts_per_page' => 20, 'meta_key' => '_wp_page_template', 'meta_value' => Settings::TEMPLATE)); }
    public function create_page(): void
    {
        $this->authorize('mao_tk_order_query_create_page');
        if (!current_user_can('publish_pages') || !Runtime::enabled() || !Orders::available()) { wp_die(__('请先启用模块并确认页面发布权限与主题接口。', 'mao-tk-zibll-toolkit'), '', array('response' => 403)); }
        if (!self::pages()) {
            $settings = (new Settings())->read();
            if (!$settings['valid']) { wp_die(__('配置格式异常，请先检查设置。', 'mao-tk-zibll-toolkit'), '', array('response' => 400)); }
            $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $settings['value']['title'], 'post_name' => 'order-query', 'post_content' => '', 'comment_status' => 'closed', 'ping_status' => 'closed', 'page_template' => Settings::TEMPLATE), true);
            if (is_wp_error($id)) { wp_die(__('页面创建失败，请在页面列表手动创建并选择模板。', 'mao-tk-zibll-toolkit'), '', array('response' => 500)); }
        }
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'order_notice' => 'page-ready'), admin_url('admin.php'))); exit;
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(__('你没有访问此页面的权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403)); }
        $state = (new Settings())->read(); $settings = $state['value']; $pages = self::pages();
        $module = array('id' => Settings::MODULE, 'name' => __('订单查询页面', 'mao-tk-zibll-toolkit'), 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === Settings::MODULE) { $module = $record; break; } }
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/order-query-settings.php';
    }
}
