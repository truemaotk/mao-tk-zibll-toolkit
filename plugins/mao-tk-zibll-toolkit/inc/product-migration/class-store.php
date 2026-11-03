<?php
namespace MaoTK\ZibllToolkit\ProductMigration;

defined('ABSPATH') || exit;

/** Private, bounded task storage. No package is placed in public uploads. */
final class Store
{
    public const PREFIX = 'mao_tk_product_migration_job_';
    public const INDEX = 'mao_tk_product_migration_jobs';
    public const TTL = 86400;
    public const HISTORY_LIMIT = 20;
    public const MAX_ACTIVE = 100;

    public static function inside(string $file, string $root): bool
    {
        $file = wp_normalize_path($file);
        $root = rtrim(wp_normalize_path($root), '/');
        if(DIRECTORY_SEPARATOR==='\\'){$file=strtolower($file);$root=strtolower($root);}
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
        $root = $base . '/maotk-product-migration-' . substr(hash('sha256', home_url('/') . '|' . get_current_blog_id()), 0, 20);
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
        if (count($items) > Package::MAX_POSTS) { throw new \RuntimeException('单次最多处理 ' . Package::MAX_POSTS . ' 件商品，请按分类分批。'); }
        $job = array_merge(array('id' => bin2hex(random_bytes(16)), 'owner' => get_current_user_id(), 'type' => $type,
            'created' => time(), 'updated' => time(), 'expires' => time() + self::TTL, 'revision' => 0,
            'status' => 'preview', 'cursor' => 0, 'items' => $items, 'results' => array(), 'backups' => array(), 'journal' => array(), 'warnings' => array()), $extra);
        $key = 'index:' . get_current_user_id(); self::recover($key); $lock = self::lock($key);
        try {
            $index = self::prune(); $active = 0;
            foreach ($index as $id) { $previous = get_option(self::PREFIX . $id); if (is_array($previous) && (int) $previous['expires'] >= time()) { $active++; } }
            if ($active >= self::MAX_ACTIVE) { throw new \RuntimeException('当前账号的有效任务已达到 100 个，请等待旧任务过期后再创建。'); }
            self::dir($job['id']);
            self::write_file($job);
            if (!add_option(self::PREFIX . $job['id'], self::header($job), '', false)) { throw new \RuntimeException('无法保存任务。'); }
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
        if (!isset($job['items'])) {
            // Expired metadata stays readable, but its private payload has already been purged.
            if ((int)$job['expires'] < time()) { throw new \RuntimeException('任务已过期，仅保留列表摘要。'); }
            $path=self::dir($id).'/task.json';
            if(is_link($path)||!is_file($path)||filesize($path)>67108864)throw new \RuntimeException('任务文件丢失或过大。');
            $full=json_decode((string)file_get_contents($path),true,64);
            if(!is_array($full)||($full['id']??'')!==$id||($full['owner']??0)!==get_current_user_id())throw new \RuntimeException('任务文件校验失败。');
            $job=$full;
        }
        return $job;
    }

    public static function save(array &$job): void
    {
        $job['updated'] = time(); $job['revision']++;
        self::write_file($job);$header=self::header($job);
        update_option(self::PREFIX . $job['id'], $header, false);
        if (get_option(self::PREFIX . $job['id']) !== $header) { throw new \RuntimeException('任务保存失败，请刷新后核对进度。'); }
    }

    private static function header(array $job): array
    {
        return array('id'=>$job['id'],'owner'=>$job['owner'],'expires'=>$job['expires'],'revision'=>$job['revision'],'summary'=>self::summary($job));
    }
    private static function write_file(array $job): void
    {
        $raw=wp_json_encode($job,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($raw)||strlen($raw)>67108864)throw new \RuntimeException('任务正文与备份超过 64 MiB，请缩小迁移范围。');
        $dir=self::dir($job['id']);$tmp=$dir.'/task-'.bin2hex(random_bytes(8)).'.tmp';
        if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw)){@unlink($tmp);throw new \RuntimeException('任务文件写入失败。');}
        if(!rename($tmp,$dir.'/task.json')){@unlink($tmp);throw new \RuntimeException('任务文件替换失败。');}
    }

    public static function lock(string $key): string
    {
        $name = 'mao_tk_product_migration_lock_' . hash('sha256', $key);
        if (!add_option($name, time(), '', false)) {
            // A stale lock requires explicit recovery; never delete another worker's live lock.
            throw new \RuntimeException('任务正在处理，请稍后重试；若上次请求意外终止，可在 2 分钟后恢复任务。');
        }
        return $name;
    }

    public static function recover(string $key): void
    {
        global $wpdb;
        $name = 'mao_tk_product_migration_lock_' . hash('sha256', $key);
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
            $job=get_option(self::PREFIX . $id);
            if(is_array($job)&&(int)$job['owner']===get_current_user_id()){$s=$job['summary']??self::summary($job);if($job['expires']<time())$s['status']='expired';$out[]=$s;}
        }
        return $out;
    }

    /** Keep every unexpired task indexed, even when it falls outside the visible latest 20. */
    private static function prune(): array
    {
        $index = array_values(array_unique((array) get_user_meta(get_current_user_id(), self::INDEX, true)));
        $keep = array(); $position = 0;
        foreach ($index as $id) {
            $job=get_option(self::PREFIX . $id);if(!is_array($job)||(int)$job['owner']!==get_current_user_id())continue;
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

