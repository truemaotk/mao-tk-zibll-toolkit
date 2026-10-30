<?php
namespace MaoTK\ZibllToolkit\ArticleNote;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-article-note';
    private $manager;
    private $admin;
    private $screen = '';
    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }
    public function register(): void { add_action('admin_menu', array($this, 'menu'), 20); add_action('admin_enqueue_scripts', array($this, 'enqueue')); }
    public function menu(): void
    {
        $hook = add_submenu_page(Admin::SLUG, '正文提示框', '正文提示框', 'manage_options', self::SLUG, array($this, 'render'));
        $this->screen = is_string($hook) ? $hook : ''; $this->admin->allow_screen($this->screen);
    }
    public function enqueue(string $hook): void
    {
        if (!$this->screen || $hook !== $this->screen || !current_user_can('manage_options')) { return; }
        wp_enqueue_style('mao-tk-toolkit-note-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_style(Integration::BASE, MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/base.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_style(Integration::VISUAL, MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/note.css', array(Integration::BASE), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-toolkit-note-model', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/model.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        wp_enqueue_script('mao-tk-toolkit-note-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/admin.js', array('mao-tk-toolkit-note-model'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        $state = (new Settings())->read();
        wp_add_inline_script('mao-tk-toolkit-note-admin', 'window.MaoTKNoteSettings=' . wp_json_encode(Settings::payload($state['value']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
    public static function save(ModuleManager $manager): void
    {
        // Called only after the shared Admin::save capability/POST/nonce guards.
        $raw = isset($_POST['note']) && is_array($_POST['note']) ? wp_unslash($_POST['note']) : null;
        $provider = isset($_POST['note_provider']) && is_string($_POST['note_provider']) ? wp_unslash($_POST['note_provider']) : '';
        $value = Settings::posted($raw);
        if ($value === null || !in_array($provider, array('coexist', 'toolkit'), true)) { wp_die('提示框配置无效：请使用完整 HEX、12–28px 尺寸和已注册图标；原设置未改变。', '', array('response' => 400)); }
        $saved = (new Settings())->save($manager, $value, $provider);
        $tab = isset($_POST['note_ui_tab']) && is_string($_POST['note_ui_tab']) && in_array($_POST['note_ui_tab'], array_merge(array('connection', 'defaults'), array_keys(Settings::types())), true) ? $_POST['note_ui_tab'] : 'info';
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'note_notice' => $saved ? 'saved' : 'failed', 'note_tab' => $tab), admin_url('admin.php'))); exit;
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die('你没有访问此页面的权限。', '', array('response' => 403)); }
        $state = (new Settings())->read(); $settings = $state['value']; $status = Integration::instance()->status();
        $module = array('id' => 'article-note', 'name' => '正文提示框', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === 'article-note') { $module = $record; break; } }
        if ($module['status'] === 'loaded' && !Integration::instance()->usable()) { $module['status'] = 'waiting-provider'; }
        $valid = $this->manager->storage_is_valid(); $icons = Icons::all();
        $notice = isset($_GET['note_notice']) && is_string($_GET['note_notice']) ? $_GET['note_notice'] : '';
        $module_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : '';
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/article-note-settings.php';
    }
}
