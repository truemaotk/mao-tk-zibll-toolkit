<?php
namespace MaoTK\ZibllToolkit\Migration;

defined('ABSPATH') || exit;

/** Private, bounded task storage. No package is placed in public uploads. */
final class Store
{
    public const PREFIX = 'mao_tk_migration_job_';
    public const INDEX = 'mao_tk_migration_jobs';
    public const TTL = 86400;
    public const HISTORY_LIMIT = 20;
    public const MAX_ACTIVE = 100;

    public static function inside(string $file, string $root): bool
    {
        $file = strtolower(wp_normalize_path($file));
        $root = rtrim(strtolower(wp_normalize_path($root)), '/');
        return $file === $root || strpos($file, $root . '/') === 0;
    }

    public static function root(): string
    {
        $base = defined('MAO_TK_TOOLKIT_MIGRATION_TEMP') ? MAO_TK_TOOLKIT_MIGRATION_TEMP : get_temp_dir();
        $base = is_string($base) ? realpath($base) : false;
        if (!$base || !is_writable($base)) { throw new \RuntimeException('私有临时目录不可写，请配置网站目录之外的 MAO_TK_TOOLKIT_MIGRATION_TEMP。'); }
        $public = array(ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR);
        if (!empty($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) { $public[] = $_SERVER['DOCUMENT_ROOT']; }
        foreach ($public as $path) {
            $real = realpath($path);
            if ($real && self::inside($base, $real)) { throw new \RuntimeException('临时目录位于可公开访问的网站目录内，已停止创建迁移包。请配置站外私有临时目录。'); }
        }
        $root = $base . '/maotk-migration-' . substr(hash('sha256', home_url('/') . '|' . get_current_blog_id()), 0, 20);
        if (is_link($root)) { throw new \RuntimeException('临时目录不能是符号链接。'); }
        if (!is_dir($root) && !mkdir($root, 0700)) { throw new \RuntimeException('无法创建私有任务目录。'); }
        return $root;
    }

    public static function dir(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new \RuntimeException('任务标识无效。'); }
        $dir = self::root() . '/' . $id;
        if (is_link($dir)) { throw new \RuntimeException('任务目录异常。'); }
        if (!is_dir($dir) && !mkdir($dir, 0700)) { throw new \RuntimeException('无法创建任务目录。'); }
        return $dir;
    }

    public static function create(string $type, array $items, array $extra = array()): array
    {
        if (count($items) > Package::MAX_LINKS) { throw new \RuntimeException('单次最多处理 ' . Package::MAX_LINKS . ' 条链接，请按分类分批。'); }
        $job = array_merge(array('id' => bin2hex(random_bytes(16)), 'owner' => get_current_user_id(), 'type' => $type,
            'created' => time(), 'updated' => time(), 'expires' => time() + self::TTL, 'revision' => 0,
            'status' => 'preview', 'cursor' => 0, 'items' => $items, 'results' => array(), 'backups' => array(), 'journal' => array(), 'warnings' => array()), $extra);
        $key = 'index:' . get_current_user_id(); self::recover($key); $lock = self::lock($key);
        try {
            $index = self::prune(); $active = 0;
            foreach ($index as $id) { $previous = self::load($id, true); if ((int) $previous['expires'] >= time()) { $active++; } }
            if ($active >= self::MAX_ACTIVE) { throw new \RuntimeException('当前账号的有效任务已达到 100 个，请等待旧任务过期后再创建。'); }
            self::dir($job['id']);
            if (!add_option(self::PREFIX . $job['id'], $job, '', false)) { throw new \RuntimeException('无法保存任务。'); }
            array_unshift($index, $job['id']);
            update_user_meta(get_current_user_id(), self::INDEX, $index);
            self::prune();
        } finally { delete_option($lock); }
        return $job;
    }

    public static function load(string $id, bool $allow_expired = false): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new \RuntimeException('任务标识无效。'); }
        $job = get_option(self::PREFIX . $id);
        if (!is_array($job) || (int) $job['owner'] !== get_current_user_id()) { throw new \RuntimeException('任务不存在或不属于当前账号。'); }
        if (!$allow_expired && (int) $job['expires'] < time()) { self::purge($id); throw new \RuntimeException('任务已过期，请重新创建。'); }
        return $job;
    }

    public static function save(array &$job): void
    {
        $job['updated'] = time(); $job['revision']++;
        update_option(self::PREFIX . $job['id'], $job, false);
        if (get_option(self::PREFIX . $job['id']) !== $job) { throw new \RuntimeException('任务保存失败，请刷新后核对进度。'); }
    }

    public static function lock(string $key): string
    {
        $name = 'mao_tk_migration_lock_' . hash('sha256', $key);
        if (!add_option($name, time(), '', false)) {
            // A stale lock requires explicit recovery; never delete another worker's live lock.
            throw new \RuntimeException('任务正在处理，请稍后重试；若上次请求意外终止，可在 2 分钟后恢复任务。');
        }
        return $name;
    }

    public static function recover(string $key): void
    {
        global $wpdb;
        $name = 'mao_tk_migration_lock_' . hash('sha256', $key);
        $value = get_option($name);
        if (is_numeric($value) && (int) $value < time() - 120) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $name, (string) $value));
            wp_cache_delete($name, 'options');
        }
    }

    public static function history(): array
    {
        $key = 'index:' . get_current_user_id(); self::recover($key); $lock = self::lock($key);
        try { $index = self::prune(); } finally { delete_option($lock); }
        $out = array();
        foreach (array_slice($index, 0, self::HISTORY_LIMIT) as $id) {
            try { $job = self::load((string) $id, true); $out[] = self::summary($job); } catch (\Throwable $error) { /* foreign/removed entry */ }
        }
        return $out;
    }

    /** Keep every unexpired task indexed, even when it falls outside the visible latest 20. */
    private static function prune(): array
    {
        $index = array_values(array_unique((array) get_user_meta(get_current_user_id(), self::INDEX, true)));
        $keep = array(); $position = 0;
        foreach ($index as $id) {
            try { $job = self::load((string) $id, true); } catch (\Throwable $error) { continue; }
            $expired = (int) $job['expires'] < time();
            if ($expired) { self::purge((string) $id); }
            if (!$expired || $position < self::HISTORY_LIMIT) { $keep[] = (string) $id; }
            else { delete_option(self::PREFIX . $id); }
            $position++;
        }
        if ($keep !== $index) { update_user_meta(get_current_user_id(), self::INDEX, $keep); }
        return $keep;
    }

    public static function summary(array $job): array
    {
        $counts = array('success' => 0, 'skip' => 0, 'failed' => 0);
        foreach ($job['results'] as $result) { if (isset($counts[$result['state']])) { $counts[$result['state']]++; } }
        return array('id' => $job['id'], 'type' => $job['type'], 'status' => $job['expires'] < time() ? 'expired' : $job['status'],
            'created' => wp_date('Y-m-d H:i', $job['created']), 'revision' => $job['revision'], 'total' => count($job['items']),
            'done' => count($job['results']), 'counts' => $counts, 'warnings' => $job['warnings']);
    }

    private static function purge(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { return; }
        $dir = self::root() . '/' . $id;
        if (!is_dir($dir) || is_link($dir)) { return; }
        foreach (new \DirectoryIterator($dir) as $file) {
            if ($file->isDot() || $file->isDir() || $file->isLink()) { continue; }
            @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
