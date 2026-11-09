<?php
namespace MaoTK\ZibllToolkit\ProfilePresets;
use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-profile-presets';
    public const SAVE = 'mao_tk_toolkit_profile_presets_settings';
    private $manager; private $admin; private $screen = '';
    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }
    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_' . self::SAVE, array($this, 'save'));
    }
    public function menu(): void
    {
        $this->screen = (string) add_submenu_page(Admin::SLUG, __('头像与背景预设', 'mao-tk-zibll-toolkit'), __('头像与背景', 'mao-tk-zibll-toolkit'), 'manage_options', self::SLUG, array($this, 'render'));
        $this->admin->allow_screen($this->screen);
    }
    public function enqueue(string $hook): void
    {
        if ($this->screen === '' || $hook !== $this->screen || !current_user_can('manage_options')) { return; }
        wp_enqueue_style('mao-tk-profile-presets-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/profile-presets/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        if (current_user_can('upload_files')) { wp_enqueue_media(); }
        wp_enqueue_script('mao-tk-profile-presets-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/profile-presets/admin.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        wp_set_script_translations('mao-tk-profile-presets-admin', 'mao-tk-zibll-toolkit', MAO_TK_ZIBLL_TOOLKIT_DIR . 'languages');
    }
    public function save(): void
    {
        if (!current_user_can('manage_options')) { wp_die(__('你没有管理预设图片的权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die(__('请通过设置表单提交。', 'mao-tk-zibll-toolkit'), '', array('response' => 405)); }
        check_admin_referer(self::SAVE);
        if (!isset($_POST['presets']) || !is_array($_POST['presets'])) { wp_die(__('预设设置格式无效。', 'mao-tk-zibll-toolkit'), '', array('response' => 400)); }
        $raw = wp_unslash($_POST['presets']); $revision = $_POST['presets_revision'] ?? '';
        if (!is_string($revision) || !preg_match('/^[0-9]{1,9}$/D', $revision)) { wp_die(__('请刷新设置页后重试。', 'mao-tk-zibll-toolkit'), '', array('response' => 400)); }
        try {
            foreach (array('avatar_enabled', 'cover_enabled', 'random_on_register', 'builtin_avatars', 'builtin_covers') as $key) {
                if (!isset($raw[$key]) || !is_string($raw[$key]) || !in_array($raw[$key], array('0', '1'), true)) { throw new \InvalidArgumentException(__('预设开关格式无效。', 'mao-tk-zibll-toolkit')); }
                $raw[$key] = $raw[$key] === '1';
            }
            foreach (array('avatar_media', 'cover_media') as $key) {
                if (!isset($raw[$key]) || !is_string($raw[$key]) || strlen($raw[$key]) > 4000 || ($raw[$key] !== '' && !preg_match('/^[1-9][0-9]*(,[1-9][0-9]*)*$/D', $raw[$key]))) { throw new \InvalidArgumentException(__('请从媒体库重新选择图片。', 'mao-tk-zibll-toolkit')); }
                $raw[$key] = $raw[$key] === '' ? array() : array_map('intval', explode(',', $raw[$key]));
            }
            $saved = (new Settings())->save($raw, (int) $revision);
        } catch (\InvalidArgumentException $error) { wp_die(esc_html(translate($error->getMessage(), 'mao-tk-zibll-toolkit')), '', array('response' => 400, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('page' => self::SLUG, 'presets_notice' => $saved ? 'saved' : 'conflict'), admin_url('admin.php'))); exit;
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(__('你没有访问此页面的权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403)); }
        $state = (new Settings())->read(); $settings = $state['value'];
        $module = array('id' => Settings::MODULE, 'name' => __('用户头像与背景预设', 'mao-tk-zibll-toolkit'), 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === Settings::MODULE) { $module = $record; break; } }
        $notice = isset($_GET['presets_notice']) && is_string($_GET['presets_notice']) ? $_GET['presets_notice'] : '';
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/profile-presets-settings.php';
    }
}
