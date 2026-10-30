<?php
namespace MaoTK\ZibllToolkit\TopInfo;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-top-info';
    public const SAVE = 'mao_tk_toolkit_top_info_save';
    public const PREVIEW = 'mao_tk_toolkit_top_info_preview';
    public const SEARCH = 'mao_tk_toolkit_top_info_search';
    private $manager;
    private $admin;
    private $screen = '';

    public function __construct(ModuleManager $manager, Admin $admin)
    {
        $this->manager = $manager;
        $this->admin = $admin;
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_' . self::SAVE, array($this, 'save'));
        add_action('admin_post_' . self::PREVIEW, array($this, 'preview'));
        add_action('admin_post_' . self::SEARCH, array($this, 'search'));
    }

    public function menu(): void
    {
        $hook = add_submenu_page(Admin::SLUG, '文章顶部信息', '文章顶部信息', 'manage_options', self::SLUG, array($this, 'render'));
        $this->screen = is_string($hook) ? $hook : '';
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $hook): void
    {
        if ($this->screen === '' || $hook !== $this->screen || !current_user_can('manage_options')) { return; }
        wp_enqueue_style('mao-tk-toolkit-top-info-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/top-info-admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-toolkit-top-info-reveal', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/top-info/reveal.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        wp_enqueue_script('mao-tk-toolkit-top-info-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/js/top-info-admin.js', array('mao-tk-toolkit-top-info-reveal'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    private function authorize(string $action): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问文章顶部信息设置的权限。', '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请使用设置页表单。', '', array('response' => 405)); }
        check_admin_referer($action);
    }

    private function posted_settings(): array
    {
        $raw = $_POST['top_info'] ?? null;
        $settings = Settings::from_form(is_array($raw) ? wp_unslash($raw) : null);
        if ($settings === null) { wp_die('颜色、留白或属性格式无效。留白为 0—200 的整数；属性每行一个，最多 100 项，每项最多 600 字节。', '', array('response' => 400)); }
        return $settings;
    }

    public function save(): void
    {
        $this->authorize(self::SAVE);
        $saved = (new Settings())->save($this->posted_settings());
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'top_info_notice' => $saved ? 'saved' : 'save-failed'), admin_url('admin.php')));
        exit;
    }

    public function preview(): void
    {
        $this->authorize(self::PREVIEW);
        $settings = $this->posted_settings();
        $id = $_POST['post_id'] ?? '0';
        if (!is_string($id) || !preg_match('/^(?:0|[1-9][0-9]{0,9})$/D', $id)) { wp_die('文章编号无效。', '', array('response' => 400)); }
        $sample = $id === '0';
        if ($sample) {
            $data = ArticleData::sample($settings);
        } else {
            $post = get_post((int) $id);
            if (!ContentSearch::can_preview($post)) {
                wp_die('内容不存在，或你没有预览该内容的权限。', '', array('response' => 403));
            }
            $data = (new ArticleData())->collect($post, true);
        }
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Robots-Tag: noindex, nofollow');
        header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
        $html = (new Renderer())->render($data, $settings, true);
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-preview.php';
        exit;
    }

    public function search(): void
    {
        $this->authorize(self::SEARCH);
        $query = $_POST['query'] ?? '';
        if (!is_string($query) || strlen($query) > 400) { wp_die('搜索内容过长或格式无效。', '', array('response' => 400)); }
        nocache_headers();
        wp_send_json_success((new ContentSearch())->find(wp_unslash($query)));
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问此页面的权限。', '', array('response' => 403)); }
        $state = (new Settings())->read();
        $settings = $state['value'];
        $module = array('enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === 'top-info') { $module = $record; break; } }
        $conflict = Runtime::conflict();
        $notice = isset($_GET['top_info_notice']) && is_string($_GET['top_info_notice']) ? wp_unslash($_GET['top_info_notice']) : '';
        $module_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? wp_unslash($_GET['mao_tk_notice']) : '';
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-settings.php';
    }
}
