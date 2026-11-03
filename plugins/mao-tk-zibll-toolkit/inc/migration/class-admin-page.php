<?php
namespace MaoTK\ZibllToolkit\Migration;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG = 'mao-tk-toolkit-data-migration';
    public const ACTION = 'mao_tk_toolkit_migration';
    private $manager;
    private $admin;
    private $screen = '';

    public function __construct(ModuleManager $manager, Admin $admin) { $this->manager = $manager; $this->admin = $admin; }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('wp_ajax_' . self::ACTION, array($this, 'ajax'));
        add_action('admin_post_' . self::ACTION . '_download', array($this, 'download'));
    }

    public function menu(): void
    {
        $this->screen = (string) add_submenu_page(Admin::SLUG, '链接导入&导出', '链接导入&导出', 'manage_options', self::SLUG, array($this, 'render'));
        $this->admin->allow_screen($this->screen);
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->screen || $this->screen === '' || !current_user_can('manage_options')) { return; }
        wp_enqueue_style('mao-tk-migration', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/migration/admin.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-migration', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/migration/admin.js', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    private function active(): bool
    {
        foreach ($this->manager->all() as $r) { if ($r['id'] === Links::MODULE) { return $r['status'] === 'loaded'; } }
        return false;
    }

    public static function environment_errors(): array
    {
        $errors = array();
        if (!class_exists('ZipArchive')) { $errors[] = '服务器未启用 ZipArchive，暂时不能迁移。'; }
        try { Store::root(); } catch (\Throwable $error) { $errors[] = $error->getMessage(); }
        return $errors;
    }

    public static function download_url(string $id, string $kind): string
    {
        // JSON consumers need the raw URL, not wp_nonce_url()'s HTML-escaped &amp; form.
        return add_query_arg(array('action' => self::ACTION . '_download', 'job' => $id, 'kind' => $kind, '_wpnonce' => wp_create_nonce(self::ACTION . '_download_' . $id)), admin_url('admin-post.php'));
    }

    public static function present(array $job, int $page = 1): array
    {
        $out = Store::summary($job); $page = max(1, min((int) ceil(max(1, count($job['items'])) / 20), $page));
        $out['page'] = $page; $out['pages'] = max(1, (int) ceil(count($job['items']) / 20)); $out['rows'] = array();
        foreach (array_slice($job['items'], ($page - 1) * 20, 20, true) as $i => $item) {
            $out['rows'][] = array('name' => $item['name'], 'url' => wp_specialchars_decode($item['url'], ENT_QUOTES), 'private' => $item['visible'] !== 'Y',
                'logo' => $item['asset'] !== '' ? '随包迁移' : ($item['image'] !== '' ? '保留图片网址' : '无'),
                'plan' => $job['preview'][$i]['action'] ?? 'export', 'result' => $job['results'][$i] ?? null);
        }
        $out['report'] = $out['status'] !== 'expired' ? self::download_url($job['id'], 'report') : '';
        $out['download'] = $out['status'] !== 'expired' && $job['type'] === 'export' && $job['status'] === 'complete' ? self::download_url($job['id'], 'zip') : '';
        $out['sha256'] = $job['sha256'] ?? ''; $out['partial'] = !empty($job['partial']);
        if ($job['status'] === 'invalid') { $out['rows'] = array(); }
        return $out;
    }

    public function ajax(): void
    {
        try {
            Links::authorize();
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { throw new \RuntimeException('请使用 POST 提交。'); }
            check_ajax_referer(self::ACTION, 'nonce');
            $op = isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : '';
            if (!$this->active() && !in_array($op, array('history', 'inspect', 'availability'), true)) { throw new \RuntimeException('友情链接迁移模块未运行，请先在工作台开启并确认 Zibll 环境。'); }
            if ($op === 'availability') { $errors = self::environment_errors(); wp_send_json_success(array('active' => $this->active() && !$errors, 'errors' => $errors, 'nonce' => wp_create_nonce(self::ACTION))); }
            if (!in_array($op, array('history', 'inspect'), true)) { $errors = self::environment_errors(); if ($errors) { throw new \RuntimeException(implode(' ', $errors)); } }
            if ($op === 'history') { wp_send_json_success(Store::history()); }
            if ($op === 'export') {
                $ids = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : array();
                if (!is_array($ids) || count($ids) > Package::MAX_LINKS) { throw new \RuntimeException('所选链接格式无效。'); }
                foreach ($ids as $id) { if (!is_scalar($id) || !ctype_digit((string) $id)) { throw new \RuntimeException('链接编号无效。'); } }
                $visibility = $_POST['visibility'] ?? 'all';
                if (!is_string($visibility) || !in_array($visibility, array('all', 'public', 'private'), true)) { throw new \RuntimeException('可见性筛选无效。'); }
                $category = isset($_POST['category']) && is_scalar($_POST['category']) ? absint($_POST['category']) : 0;
                if ($category && !term_exists($category, 'link_category')) { throw new \RuntimeException('所选分类已不存在。'); }
                $job = Links::export(array_map('intval', $ids), $category, $visibility, !empty($_POST['images']), !empty($_POST['remote']));
            } elseif ($op === 'upload') {
                $file = $_FILES['package'] ?? null;
                if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['name']) || !preg_match('/\.zip$/iD', $file['name']) || !is_uploaded_file($file['tmp_name'])) { throw new \RuntimeException('没有收到有效 ZIP，请检查 PHP 上传限制后重试。'); }
                $policy = isset($_POST['policy']) && is_string($_POST['policy']) ? $_POST['policy'] : 'skip';
                $job = Links::prepare($file['tmp_name'], $policy);
            } else {
                $id = isset($_POST['job']) && is_string($_POST['job']) ? $_POST['job'] : '';
                if ($op === 'inspect') { $job = Store::load($id, true); }
                elseif (in_array($op, array('start', 'step', 'pause', 'retry'), true)) {
                    if (!isset($_POST['revision']) || !is_scalar($_POST['revision']) || !ctype_digit((string) $_POST['revision'])) { throw new \RuntimeException('任务版本无效，请刷新任务。'); }
                    $job = Links::command($id, $op, (int) $_POST['revision']);
                } else { throw new \RuntimeException('不支持的任务操作。'); }
            }
            $page = isset($_POST['page_number']) && is_scalar($_POST['page_number']) ? absint($_POST['page_number']) : 1;
            wp_send_json_success(self::present($job, $page));
        } catch (\Throwable $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        }
    }

    public function download(): void
    {
        try {
            Links::authorize();
            $id = isset($_GET['job']) && is_string($_GET['job']) ? $_GET['job'] : '';
            check_admin_referer(self::ACTION . '_download_' . $id);
            $job = Store::load($id);
            nocache_headers(); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store, max-age=0');
            if (($_GET['kind'] ?? '') === 'zip' && $job['type'] === 'export' && $job['status'] === 'complete') {
                $file = Store::dir($id) . '/links.zip';
                if (!is_file($file) || !hash_equals($job['sha256'], (string) hash_file('sha256', $file))) { throw new \RuntimeException('下载包已失效，请重新导出。'); }
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="maotk-links-' . gmdate('Ymd-His', $job['created']) . (!empty($job['partial']) ? '-partial' : '') . '.zip"');
                header('Content-Length: ' . filesize($file)); readfile($file); exit;
            }
            if (($_GET['kind'] ?? '') !== 'report') { throw new \RuntimeException('下载类型无效。'); }
            header('Content-Type: application/json; charset=UTF-8'); header('Content-Disposition: attachment; filename="maotk-links-report-' . $id . '.json"');
            echo wp_json_encode(array('summary' => Store::summary($job), 'results' => $job['results'], 'before_update' => $job['backups'], 'sha256' => $job['sha256'] ?? ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
        } catch (\Throwable $error) { wp_die(esc_html($error->getMessage()), '', array('response' => 403)); }
    }

    public function render(): void
    {
        Links::authorize();
        $links = Links::all(Package::MAX_LINKS);
        global $wpdb; $link_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->links}");
        $categories = get_terms(array('taxonomy' => 'link_category', 'hide_empty' => false));
        if (is_wp_error($categories)) { $categories = array(); }
        $errors = self::environment_errors(); $active = $this->active() && !$errors; $storage_error = implode(' ', $errors);
        $module = array('id' => Links::MODULE, 'name' => '链接导入&导出', 'enabled' => false, 'status' => 'disabled');
        foreach ($this->manager->all() as $record) { if ($record['id'] === Links::MODULE) { $module = $record; } }
        $module['name'] = '链接导入&导出';
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/migration-links.php';
    }
}
