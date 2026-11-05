<?php
namespace MaoTK\ZibllToolkit\R2Media;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-r2-media';
    private const ACTION = 'mao_tk_r2_media_manage';
    private const PROGRESS_ACTION = 'mao_tk_r2_media_progress';
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
        add_action('admin_menu', array($this, 'menu'), 21);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_' . self::ACTION, array($this, 'submit'));
        add_action('wp_ajax_' . self::PROGRESS_ACTION, array($this, 'progress'));
    }

    public function menu(): void
    {
        $this->screen = (string) add_submenu_page(Admin::SLUG, 'R2 图片同步', 'R2 图片同步', 'manage_options', self::SLUG, array($this, 'render'));
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $screen): void
    {
        if ($this->screen !== '' && $this->screen === $screen) {
            wp_enqueue_style('mao-tk-toolkit-r2-media-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-media/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
            wp_enqueue_script('mao-tk-toolkit-r2-media-progress', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-media/progress.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        }
    }

    private function module(): array
    {
        foreach ($this->manager->all() as $module) {
            if ($module['id'] === Runtime::MODULE) {
                return $module;
            }
        }
        return array('id' => Runtime::MODULE, 'name' => 'R2 图片同步', 'enabled' => false, 'status' => 'disabled');
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('需要管理员权限。', '', array('response' => 403));
        }
        $switch_module = $this->module();
        $switch_valid = $this->manager->storage_is_valid();
        $switch_return_page = self::SLUG;
        $storage = Settings::storage();
        $verified = $storage && Settings::verified($storage->destination());
        $configured = $storage && Settings::origin() !== '' && Settings::get('MAOTK_MEDIA_SIGNING_KEY') !== '';
        $running = $switch_module['status'] === 'loaded';
        $bulk = get_option(Sync::BULK, array());
        $controls = self::controls($bulk, $running && $storage !== null);
        $last_error = get_option(Sync::LAST_ERROR, array());
        $notice_key = 'mao_tk_r2_media_notice_' . get_current_user_id();
        $notice = get_transient($notice_key);
        if ($notice !== false) {
            delete_transient($notice_key);
        }
        require __DIR__ . '/settings-page.php';
    }

    public function progress(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }
        check_ajax_referer(self::PROGRESS_ACTION, 'nonce');
        $bulk = get_option(Sync::BULK, array());
        $controls = self::controls($bulk, $this->module()['status'] === 'loaded' && Settings::storage() !== null);
        $last_error = get_option(Sync::LAST_ERROR, array());
        ob_start();
        require __DIR__ . '/progress-view.php';
        wp_send_json_success(array('html' => ob_get_clean()) + $controls);
    }

    private static function controls(array $bulk, bool $ready): array
    {
        $active = Sync::active($bulk);
        $paused = Sync::paused($bulk);
        $pausing = $paused && Sync::in_flight();
        return array(
            'active' => $active, 'ready' => $ready, 'polling' => $active && (!$paused || $pausing),
            'paused' => $paused, 'pausing' => $pausing,
            'job' => $active && !empty($bulk['managed']) ? $bulk['job'] : '',
        );
    }

    public function submit(): void
    {
        if (!current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('无权执行此操作。', '', array('response' => 403));
        }
        check_admin_referer(self::ACTION);
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? wp_unslash($_POST['operation']) : '';
        $ok = false;
        try {
            if ($operation === 'save') {
                $fields = isset($_POST['media']) && is_array($_POST['media']) ? wp_unslash($_POST['media']) : array();
                $fields['MAOTK_MEDIA_REWRITE'] = isset($fields['MAOTK_MEDIA_REWRITE']) ? '1' : '0';
                $status = Settings::save($fields);
                $ok = $status === 'saved';
                $message = $ok ? '媒体连接设置已保存。' : (Settings::FIELDS[$status]['label'] ?? '媒体连接设置') . '未保存，请检查格式或服务器加密配置。';
            } elseif ($operation === 'test') {
                Settings::verify_connection();
                $ok = true;
                $message = '媒体桶和 Worker 已通过实际读写测试。';
            } elseif (in_array($operation, array('sync', 'repair'), true) && $this->module()['status'] === 'loaded' && Settings::storage()) {
                $ok = Sync::start_bulk($operation === 'repair');
                $message = $ok ? '已提交批量同步，可在历史图片中查看进度。' : '暂未能启动批量同步，请稍后重试。';
            } elseif (in_array($operation, array('pause', 'resume'), true) && $this->module()['status'] === 'loaded' && Settings::storage()) {
                $job = isset($_POST['job']) && is_string($_POST['job']) ? wp_unslash($_POST['job']) : '';
                $ok = Sync::set_paused($job, $operation === 'pause');
                $message = $ok ? ($operation === 'pause' ? '已请求暂停批量同步。' : '已继续批量同步。') : '无法切换任务状态，请核对当前任务后重试。';
            } else {
                $message = '请先配置媒体桶并开启模块。';
            }
        } catch (\RuntimeException $error) {
            $message = $error->getMessage();
        }
        set_transient('mao_tk_r2_media_notice_' . get_current_user_id(), array('ok' => $ok, 'message' => $message), 60);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG), 303);
        exit;
    }
}
