<?php
namespace MaoTK\ZibllToolkit\PrivateLinks;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-private-links';
    public const SAVE = 'mao_tk_toolkit_private_links_save';
    private $manager;
    private $admin;
    private $screen = '';

    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_' . self::SAVE, array($this, 'save'));
        add_action('add_meta_boxes_link', array($this, 'link_box'));
    }

    public function menu(): void
    {
        $this->screen = (string) add_submenu_page(Admin::SLUG, '私密链接可见性', '私密链接可见性', 'manage_options', self::SLUG, array($this, 'render'));
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $hook): void
    {
        if ($this->screen !== '' && $hook === $this->screen && current_user_can('manage_options')) {
            wp_enqueue_style('mao-tk-private-links-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/private-links-admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
            wp_enqueue_script('mao-tk-private-links-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/js/private-links-admin.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        }
    }

    public function link_box(): void
    {
        if (!current_user_can('manage_options')) { return; }
        add_meta_box('mao-tk-private-links-policy', 'Toolkit 私密链接', array($this, 'link_box_content'), 'link', 'side', 'default');
    }

    public function link_box_content(): void
    {
        echo '<p>勾选“私密链接”后，开启 Toolkit 模块可让指定角色或账号在前台查看。游客和未获准账号仍不可见。</p><p><a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">管理可见范围</a></p><p class="description">模块关闭时恢复子比原生隐藏规则。链接仍保留私密标记。</p>';
    }

    private function page_number(): int
    {
        return isset($_REQUEST['user_page']) && is_scalar($_REQUEST['user_page']) ? max(1, min(100000, absint($_REQUEST['user_page']))) : 1;
    }

    private function search(): string
    {
        return isset($_REQUEST['user_search']) && is_string($_REQUEST['user_search']) ? substr(sanitize_text_field(wp_unslash($_REQUEST['user_search'])), 0, 100) : '';
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有修改私密链接设置的权限。', '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请通过设置表单提交。', '', array('response' => 405)); }
        check_admin_referer(self::SAVE);
        $settings = new Settings(); $state = $settings->read();
        if (!$state['valid']) { wp_die('配置格式异常，已停止写入，原有配置未被覆盖。', '', array('response' => 400)); }
        $next = $state['value'];
        $section = $_POST['section'] ?? '';
        if ($section === 'roles') {
            $roles = isset($_POST['allowed_roles']) ? wp_unslash($_POST['allowed_roles']) : array();
            if (!is_array($roles) || count($roles) > 200) { wp_die('角色格式无效。', '', array('response' => 400)); }
            foreach ($roles as $role) { if (!is_string($role) || !array_key_exists($role, Settings::roles())) { wp_die('角色已变动，请刷新后重新选择。', '', array('response' => 400)); } }
            $next['allowed_roles'] = array_values(array_unique($roles));
        } elseif ($section === 'users') {
            if (!current_user_can('list_users')) { wp_die('你没有管理用户可见范围的权限。', '', array('response' => 403)); }
            $overrides = isset($_POST['user_override']) ? wp_unslash($_POST['user_override']) : null;
            if (!is_array($overrides) || count($overrides) > 20) { wp_die('请刷新用户列表后重试。', '', array('response' => 400)); }
            foreach ($overrides as $id => $decision) {
                if (!preg_match('/^[1-9][0-9]{0,18}$/D', (string) $id) || !in_array($decision, array('inherit', 'allow', 'deny'), true) || !get_userdata((int) $id) || !is_user_member_of_blog((int) $id)) { wp_die('用户或可见性设置无效。', '', array('response' => 400)); }
                if ($decision === 'inherit') { unset($next['user_overrides'][(int) $id]); }
                else { $next['user_overrides'][(int) $id] = $decision; }
            }
        } else { wp_die('设置分区无效。', '', array('response' => 400)); }
        $saved = $settings->save($next);
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'private_notice' => $saved ? 'saved' : 'failed', 'user_page' => $this->page_number(), 'user_search' => $this->search()), admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问此页面的权限。', '', array('response' => 403)); }
        $state = (new Settings())->read(); $settings = $state['value']; $roles = Settings::roles();
        $notice = isset($_GET['private_notice']) && is_string($_GET['private_notice']) ? $_GET['private_notice'] : '';
        $module_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : '';
        $module = array('id' => Settings::MODULE, 'name' => '私密链接可见性', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === Settings::MODULE) { $module = $record; break; } }
        $page = $this->page_number(); $search = $this->search(); $users = array(); $total = 0;
        if (current_user_can('list_users')) {
            $args = array('number' => 20, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC', 'count_total' => true, 'blog_id' => get_current_blog_id());
            if ($search !== '') { $args['search'] = '*' . $search . '*'; $args['search_columns'] = array('user_login', 'display_name'); }
            $query = new \WP_User_Query($args); $users = $query->get_results(); $total = (int) $query->get_total();
            $pages = max(1, (int) ceil($total / 20));
            if ($page > $pages) { $page = $pages; $args['paged'] = $page; $query = new \WP_User_Query($args); $users = $query->get_results(); }
        }
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/private-links-settings.php';
    }
}
