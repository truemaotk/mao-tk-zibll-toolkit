<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-r2-downloads';
    private const ACTION = 'mao_tk_toolkit_r2_upload';
    private const SAVE_ACTION = 'mao_tk_toolkit_r2_save';
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
        add_action('wp_ajax_' . self::ACTION, array($this, 'ajax'));
        add_action('admin_post_' . self::SAVE_ACTION, array($this, 'save'));
    }

    public function menu(): void
    {
        $this->screen = (string) add_submenu_page(
            Admin::SLUG, __('R2 文件上传与下载', 'mao-tk-zibll-toolkit'), __('R2 文件上传与下载', 'mao-tk-zibll-toolkit'), 'manage_options', self::SLUG, array($this, 'render')
        );
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $hook): void
    {
        if ($hook === $this->screen && $this->screen !== '' && current_user_can('manage_options')) {
            wp_enqueue_style('mao-tk-toolkit-r2-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-downloads/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
            wp_enqueue_script('mao-tk-toolkit-r2-uploads', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/r2-downloads/upload.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
            wp_set_script_translations('mao-tk-toolkit-r2-uploads', 'mao-tk-zibll-toolkit', MAO_TK_ZIBLL_TOOLKIT_DIR . 'languages');
        }
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('需要管理员权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
        }
        $switch_module = $this->module();
        $switch_valid = $this->manager->storage_is_valid();
        $switch_return_page = self::SLUG;
        $upload_ready = Storage::configured() !== null;
        $download_ready = Runtime::origin() !== '' && Runtime::signing_key() !== '';
        $enabled = $switch_module['status'] === 'loaded';
        $save_status = isset($_GET['r2_notice']) && is_string($_GET['r2_notice']) ? wp_unslash($_GET['r2_notice']) : '';
        $notice = self::notice($save_status);
        $can_encrypt = Settings::can_encrypt();
        require __DIR__ . '/settings-page.php';
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('需要管理员权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die(__('请通过 R2 管理页提交。', 'mao-tk-zibll-toolkit'), '', array('response' => 405));
        }
        check_admin_referer(self::SAVE_ACTION);
        $submitted = isset($_POST['mao_tk_r2']) && is_array($_POST['mao_tk_r2']) ? wp_unslash($_POST['mao_tk_r2']) : array();
        try {
            $status = Settings::save($submitted);
        } catch (\RuntimeException $error) {
            $status = 'encryption';
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&r2_notice=' . rawurlencode($status)) . '#mtk-r2-settings', 303);
        exit;
    }

    private static function notice(string $status): string
    {
        if (isset(Settings::FIELDS[$status])) {
            return Settings::FIELDS[$status]['label'] . __(' 格式有误，连接设置未保存。', 'mao-tk-zibll-toolkit');
        }
        return array(
            'saved' => __('连接设置已保存。可用小文件上传和生成引用验证连接。', 'mao-tk-zibll-toolkit'),
            'encryption' => __('服务器暂时无法加密保存密钥。请检查 OpenSSL 与 WordPress 安全密钥后重试。', 'mao-tk-zibll-toolkit'),
            'write' => __('连接设置写入失败，请重试。', 'mao-tk-zibll-toolkit'),
        )[$status] ?? '';
    }

    public function ajax(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => __('会话已过期或权限不足，请刷新后再试。', 'mao-tk-zibll-toolkit')), 403);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(array('message' => __('请通过 R2 管理页提交。', 'mao-tk-zibll-toolkit')), 405);
        }
        $storage = Storage::configured();
        if ($this->module()['status'] !== 'loaded' || !$storage) {
            wp_send_json_error(array('message' => __('请启用模块并配置 R2 上传凭据。', 'mao-tk-zibll-toolkit')), 503);
        }

        try {
            $operation = self::input('operation');
            if ($operation === 'start') {
                wp_send_json_success(Uploads::start($storage, self::input('filename'), self::input('size')));
            }
            if ($operation === 'reference') {
                wp_send_json_success($this->reference($storage));
            }
            if ($operation === 'list_files') {
                $page = $storage->files(self::input('cursor'));
                foreach ($page['files'] as &$file) {
                    $file['delete_nonce'] = wp_create_nonce(self::delete_action($storage, $file['key']));
                }
                unset($file);
                wp_send_json_success($page);
            }
            if ($operation === 'delete_file') {
                $key = self::input('object_key');
                if (!Storage::managed_key($key) || !wp_verify_nonce(self::input('delete_nonce'), self::delete_action($storage, $key))) {
                    wp_send_json_error(array('message' => __('删除确认已失效，请刷新文件列表后重试。', 'mao-tk-zibll-toolkit')), 403);
                }
                $storage->delete_file($key);
                wp_send_json_success();
            }
            $job = Uploads::session($storage, self::input('ticket'));
            switch ($operation) {
                case 'part':
                    wp_send_json_success(array('url' => Uploads::part($storage, $job, self::input('number'))));
                    break;
                case 'finish':
                    $file = Uploads::finish($storage, $job);
                    $file['delete_nonce'] = wp_create_nonce(self::delete_action($storage, $file['key']));
                    wp_send_json_success($file);
                    break;
                case 'cancel':
                    $storage->abort($job['key'], $job['upload']);
                    wp_send_json_success();
                    break;
                default:
                    throw new \InvalidArgumentException(__('无法识别上传操作。', 'mao-tk-zibll-toolkit'));
            }
        } catch (\InvalidArgumentException $error) {
            wp_send_json_error(array('message' => translate($error->getMessage(), 'mao-tk-zibll-toolkit')), 400);
        } catch (\RuntimeException $error) {
            wp_send_json_error(array('message' => translate($error->getMessage(), 'mao-tk-zibll-toolkit')), 502);
        }
    }

    private function reference(Storage $storage): array
    {
        $post_id = filter_var(self::input('post_id'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        $key = self::input('object_key');
        $mode = self::input('reference_mode');
        if (!in_array($mode, array('', 'native', 'public'), true)) {
            throw new \InvalidArgumentException(__('请选择有效的下载方式。', 'mao-tk-zibll-toolkit'));
        }
        if ($mode === 'public' && !class_exists('WP_HTML_Tag_Processor')) {
            throw new \InvalidArgumentException(__('公开下载需要 WordPress 6.2 或更高版本。', 'mao-tk-zibll-toolkit'));
        }
        if (Runtime::origin() === '' || Runtime::signing_key() === '') {
            throw new \RuntimeException(__('请先配置下载域名和 Worker 签名密钥。', 'mao-tk-zibll-toolkit'));
        }
        if (!$post_id || !current_user_can('edit_post', $post_id) || get_post_type($post_id) !== 'post'
            || !in_array(get_post_status($post_id), array('publish', 'draft', 'pending', 'future'), true)) {
            throw new \InvalidArgumentException(__('请填写有权编辑的文章 ID。', 'mao-tk-zibll-toolkit'));
        }
        if (!Storage::valid_key($key) || $storage->size($key) === null) {
            throw new \InvalidArgumentException(__('R2 桶中没有找到该对象，请核对完整对象键。', 'mao-tk-zibll-toolkit'));
        }
        return array('url' => $mode === 'public' ? PublicDownload::reference($post_id, $key) : Runtime::reference($post_id, $key));
    }

    private static function input(string $name): string
    {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
    }

    private static function delete_action(Storage $storage, string $key): string
    {
        return 'mao_tk_r2_delete_' . hash('sha256', $storage->destination() . '/' . $key);
    }

    private function module(): array
    {
        foreach ($this->manager->all() as $module) {
            if ($module['id'] === Runtime::MODULE) {
                return $module;
            }
        }
        return array('id' => Runtime::MODULE, 'name' => __('R2 文件上传与下载', 'mao-tk-zibll-toolkit'), 'enabled' => false, 'status' => 'disabled');
    }
}
