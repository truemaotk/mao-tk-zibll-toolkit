<?php
namespace MaoTK\ZibllToolkit\Showcase;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-showcase';
    private $admin;
    private $manager;
    private $screen = '';
    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }
    public function register(): void { add_action('admin_menu', array($this, 'menu'), 20); add_action('admin_enqueue_scripts', array($this, 'enqueue')); }
    public function menu(): void { $hook = add_submenu_page(Admin::SLUG, '页面组件 · 案例展示', '案例展示', 'manage_options', self::SLUG, array($this, 'render')); $this->screen = is_string($hook) ? $hook : ''; $this->admin->allow_screen($this->screen); }
    public function enqueue(string $hook): void
    {
        if (!$this->screen || $hook !== $this->screen || !current_user_can('manage_options')) { return; }
        wp_enqueue_media();
        wp_enqueue_style('mao-tk-toolkit-showcase-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-toolkit-showcase-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/admin.js', array('media-views', 'mao-tk-toolkit-disclosure-motion'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        $read = (new Settings())->read();
        wp_add_inline_script('mao-tk-toolkit-showcase-admin', 'window.MaoTKShowcaseAdmin=' . wp_json_encode(array('value' => $read['value'], 'revision' => $read['revision'], 'valid' => $read['valid'], 'defaultValue' => Settings::defaults(), 'cardDefaults' => Settings::card_defaults(), 'baseUrl' => MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/', 'restUrl' => rest_url(Integration::REST . '/showcase'), 'nonce' => wp_create_nonce('wp_rest'), 'newPageUrl' => admin_url('post-new.php?post_type=page'), 'enabled' => Runtime::enabled()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有管理案例库的权限。', '', array('response' => 403)); }
        echo '<div class="wrap mao-tk-toolkit" id="mao-tk-showcase-settings"><h1 class="screen-reader-text">案例展示</h1>';
        if (!(new Settings())->read()['valid']) { echo '<div class="notice notice-error"><p>案例库格式异常，原始数据仍保留，已暂停保存。请先检查或恢复案例库备份。</p></div>'; }
        if (!Runtime::enabled()) { echo '<div class="notice notice-warning"><p>案例库可以编辑保存；请在 Toolkit 工作台启用“页面组件 · 案例展示”，前台才会显示。</p></div>'; }
        if (!Integration::supported()) { echo '<div class="notice notice-warning"><p>案例展示前台模块需要WordPress 6.3或更新版本。案例资料保留，其他Toolkit模块不受影响。</p></div>'; }
        elseif (!Integration::owns_block()) { echo '<div class="notice notice-warning"><p>旧主题或其他插件仍在提供 maotk/demo-showcase 区块。Toolkit 保留原提供者，不重复注册；案例页面模板与后台案例库独立可用。</p></div>'; }
        echo '<div class="mtk-showcase-admin"><div data-showcase-admin-app><p>正在加载案例库……</p></div><div id="mtk-showcase-toast" role="status" aria-live="polite"></div><dialog id="mtk-showcase-image-dialog" aria-labelledby="mtk-showcase-image-title"><div class="image-dialog-head"><div><strong id="mtk-showcase-image-title"></strong><span>页面截图预览</span></div><button type="button" class="icon-button" data-action="close-image" aria-label="关闭截图预览"><i class="icon" data-icon="x" aria-hidden="true"></i></button></div><div class="image-dialog-scroll"><img id="mtk-showcase-dialog-image" src="" alt=""></div></dialog></div>';
        echo '<noscript><p>此管理器需要启用浏览器JavaScript；当前案例数据不会被改变。</p></noscript></div>';
    }
}
