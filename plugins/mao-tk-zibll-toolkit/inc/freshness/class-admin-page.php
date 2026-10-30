<?php
namespace MaoTK\ZibllToolkit\Freshness;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-article-freshness';
    public const SAVE = 'mao_tk_toolkit_article_freshness_save';
    public const PREVIEW = 'mao_tk_toolkit_article_freshness_preview';
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
        $hook = add_submenu_page(Admin::SLUG, '文章时效提示', '文章时效提示', 'manage_options', self::SLUG, array($this, 'render'));
        $this->screen = is_string($hook) ? $hook : '';
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $hook): void
    {
        if ($this->screen === '' || $hook !== $this->screen || !current_user_can('manage_options')) { return; }
        wp_enqueue_style('mao-tk-toolkit-freshness-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/freshness-admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-toolkit-freshness-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/js/freshness-admin.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    private function posted(string $action): array
    {
        if (!current_user_can('manage_options')) { wp_die('你没有修改文章时效提示的权限。', '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请使用设置表单。', '', array('response' => 405)); }
        check_admin_referer($action);
        $raw = $_POST['freshness'] ?? null;
        if (is_array($raw)) { $raw = wp_unslash($raw); $raw['schema_version'] = 1; }
        $value = Settings::validate($raw);
        if ($value === null) { wp_die('请填写 1—3650 天、有效提示文字和可选的 HTTP/HTTPS 联系链接。', '', array('response' => 400)); }
        return $value;
    }

    public function save(): void
    {
        $value = $this->posted(self::SAVE);
        $saved = (new Settings())->save($value);
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'freshness_notice' => $saved ? 'saved' : 'failed'), admin_url('admin.php')));
        exit;
    }

    public function preview(): void
    {
        $value = $this->posted(self::PREVIEW);
        $html = Runtime::markup($value, time() - ($value['days'] + 1) * DAY_IN_SECONDS);
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Frame-Options: SAMEORIGIN'); header('X-Content-Type-Options: nosniff'); header('X-Robots-Tag: noindex, nofollow');
        header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
        echo '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>时效提示预览</title><link rel="stylesheet" href="' . esc_url(MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/article-freshness.css?ver=' . MAO_TK_ZIBLL_TOOLKIT_VERSION) . '"><style>body{margin:0;padding:20px;font:14px/1.8 system-ui,sans-serif;color:#50575e;background:#fff}a{color:#b56600}.sample{font-size:12px;color:#646970;margin:0 0 16px}</style></head><body><p class="sample">演示：最后更新已超过 ' . esc_html((string) $value['days']) . ' 天。此预览不影响正式文章。</p>' . $html . '<p>这里是文章正文示意。</p></body></html>';
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问此页面的权限。', '', array('response' => 403)); }
        $state = (new Settings())->read(); $settings = $state['value'];
        $module = array('id' => 'article-freshness', 'name' => '文章时效提示', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === 'article-freshness') { $module = $record; break; } }
        $conflict = Runtime::conflict();
        $notice = isset($_GET['freshness_notice']) && is_string($_GET['freshness_notice']) ? $_GET['freshness_notice'] : '';
        $module_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? wp_unslash($_GET['mao_tk_notice']) : '';
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/freshness-settings.php';
    }
}
