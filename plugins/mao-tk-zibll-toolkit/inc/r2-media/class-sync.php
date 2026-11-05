<?php
namespace MaoTK\ZibllToolkit\R2Media;

use MaoTK\ZibllToolkit\R2Downloads\Storage;

defined('ABSPATH') || exit;

final class Sync
{
    public const ITEM = 'mao_tk_r2_media_sync_item';
    public const UPLOAD = 'mao_tk_r2_media_sync_upload';
    public const SCAN = 'mao_tk_r2_media_scan';
    public const PURGE = 'mao_tk_r2_media_purge';
    public const BULK = 'mao_tk_r2_media_bulk';
    public const BULK_STEP = 'mao_tk_r2_media_bulk_step';
    public const INTERVAL = 'mao_tk_r2_media_interval';
    public const LAST_ERROR = 'mao_tk_r2_media_last_error';
    private const PAUSED = 'mao_tk_r2_media_paused_job';
    private const LOCK = 'mao_tk_r2_media_transfer_lock';
    private const SPACING = 10;
    private const BATCH_STEPS = 20;
    private const BATCH_SECONDS = 8;
    private const SWEEP = 'mao_tk_r2_media_sweep';
    private const CANDIDATES = "p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image/%'";

    public static function enqueue(int $id, bool $repair = false, int $delay = 0, string $job = '', string $hook = self::ITEM): bool
    {
        $args = $job === '' ? array($id, $repair, 0) : array($id, $repair, 0, $job);
        return (bool) wp_next_scheduled($hook, $args) || wp_schedule_single_event(time() + $delay, $hook, $args) === true;
    }

    public static function start_bulk(bool $repair = true): bool
    {
        $lock = self::acquire();
        if ($lock === '') {
            return false;
        }
        try {
            return self::create_bulk($repair);
        } finally {
            self::release($lock);
        }
    }

    private static function create_bulk(bool $repair): bool
    {
        global $wpdb;
        $bulk = get_option(self::BULK, array());
        if (self::active($bulk)) {
            return true;
        }
        $storage = Settings::storage();
        $scope = $wpdb->get_row("SELECT COUNT(*) AS total, MAX(p.ID) AS last_id FROM {$wpdb->posts} p WHERE " . self::CANDIDATES, ARRAY_A);
        if (!$storage || !is_array($scope) || $wpdb->last_error) {
            return false;
        }
        $bulk = array(
            'job' => wp_generate_uuid4(), 'managed' => true, 'destination' => $storage->destination(), 'running' => (int) $scope['total'] > 0,
            'mode' => $repair ? 'repair' : 'missing',
            'cursor' => 0, 'last_id' => (int) $scope['last_id'], 'total' => (int) $scope['total'],
            'scanned' => 0, 'queued' => 0, 'processed' => 0, 'failed' => 0, 'updated' => time(), 'started' => time(),
            'attachment' => 0, 'after' => '', 'item_failed' => false, 'attempts' => 0, 'next' => 0,
        );
        if (!update_option(self::BULK, $bulk, false)) {
            return false;
        }
        if ($bulk['running'] && !self::schedule_bulk()) {
            self::interrupt_bulk();
            return false;
        }
        return true;
    }

    public static function active(array $bulk): bool
    {
        return empty($bulk['interrupted']) && (!empty($bulk['running'])
            || (!empty($bulk['job']) && (int) ($bulk['queued'] ?? 0) > (int) ($bulk['processed'] ?? 0)));
    }

    public static function paused(array $bulk): bool
    {
        return !empty($bulk['managed']) && self::active($bulk) && self::pause_record($bulk['job'])['at'] > 0;
    }

    public static function paused_seconds(string $job): int
    {
        return self::pause_record($job)['seconds'];
    }

    private static function pause_record(string $job): array
    {
        $record = get_option(self::PAUSED, array());
        $record = ($record['job'] ?? '') === $job ? $record : array();
        return array('job' => $job, 'at' => (int) ($record['at'] ?? 0), 'seconds' => (int) ($record['seconds'] ?? 0));
    }

    public static function in_flight(): bool
    {
        global $wpdb;
        $value = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK));
        return strpos($value, 'bulk:') === 0 && (int) substr($value, 5) > time();
    }

    public static function set_paused(string $job, bool $paused): bool
    {
        $bulk = get_option(self::BULK, array());
        if (empty($bulk['managed']) || $bulk['job'] !== $job || !self::active($bulk)) {
            return false;
        }
        $record = self::pause_record($job);
        if ($paused) {
            if (!$record['at']) {
                $record['at'] = time();
                if (!update_option(self::PAUSED, $record, false)) {
                    return false;
                }
            }
            wp_clear_scheduled_hook(self::BULK_STEP);
            return true;
        }
        $storage = Settings::storage();
        if (!$storage || $storage->destination() !== $bulk['destination'] || !self::schedule_bulk()) {
            return false;
        }
        if (!$record['at']) {
            return true;
        }
        $record['seconds'] += max(0, time() - $record['at']);
        $record['at'] = 0;
        return update_option(self::PAUSED, $record, false);
    }

    public static function schedule_interval(array $schedules): array
    {
        $schedules[self::INTERVAL] = array('interval' => self::SPACING, 'display' => 'Toolkit R2 图片批量同步');
        return $schedules;
    }

    public static function ensure_bulk_schedule(): void
    {
        if (!wp_next_scheduled(self::BULK_STEP)) {
            $bulk = get_option(self::BULK, array());
            if (!empty($bulk['managed']) && self::active($bulk) && !self::paused($bulk) && Settings::storage()) {
                self::schedule_bulk();
            }
        }
    }

    private static function schedule_bulk(): bool
    {
        return (bool) wp_next_scheduled(self::BULK_STEP) || wp_schedule_event(time() + 1, self::INTERVAL, self::BULK_STEP) === true;
    }

    private static function acquire(string $kind = 'media'): string
    {
        global $wpdb;
        $now = time();
        $token = $kind . ':' . ($now + 120) . ':' . wp_generate_uuid4();
        $previous = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK));
        if ($previous === null) {
            $claimed = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK, $token
            ));
        } elseif ((int) (explode(':', $previous)[1] ?? 0) <= $now) {
            $claimed = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, self::LOCK, $previous
            ));
        }
        if (empty($claimed)) {
            return '';
        }
        // These options may have been read before the worker acquired the lock.
        wp_cache_delete(self::BULK, 'options');
        wp_cache_delete(self::PAUSED, 'options');
        wp_cache_delete('notoptions', 'options');
        return $token;
    }

    private static function release(string $token): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $token));
    }

    public static function interrupt_bulk(): void
    {
        $bulk = get_option(self::BULK, array());
        if (self::active($bulk)) {
            $bulk['running'] = false;
            $bulk['interrupted'] = true;
            update_option(self::BULK, $bulk, false);
        }
    }

    public static function schedule_scan(int $delay = 60): bool
    {
        $scheduled = wp_next_scheduled(self::SCAN);
        if ($scheduled && $scheduled <= time() + $delay) {
            return true;
        }
        if ($scheduled) {
            wp_unschedule_event($scheduled, self::SCAN);
        }
        return wp_schedule_single_event(time() + $delay, self::SCAN) === true;
    }

    public static function scan(): void
    {
        $lock = self::acquire();
        if ($lock === '') {
            self::schedule_scan();
            return;
        }
        try {
            self::scan_library();
        } finally {
            self::release($lock);
        }
    }

    private static function scan_library(): void
    {
        global $wpdb;
        $storage = Settings::storage();
        if (!$storage) {
            self::interrupt_bulk();
            return;
        }
        $bulk = get_option(self::BULK, array());
        if (!empty($bulk['managed']) && self::active($bulk)) {
            self::schedule_scan(60);
            return;
        }
        $manual = !empty($bulk['running']) && empty($bulk['managed']);
        $job = $manual ? ($bulk['job'] ?? '') : '';
        if ($job !== '' && $bulk['destination'] !== $storage->destination()) {
            self::interrupt_bulk();
            self::schedule_scan();
            return;
        }
        $cursor = $manual ? (int) $bulk['cursor'] : (int) get_option(self::SWEEP, 0);
        $join = $manual ? '' : " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = %s)";
        $args = $manual ? array($cursor) : array($cursor, Files::STATE);
        if ($job !== '') {
            $join .= ' AND p.ID <= %d';
            $args[] = $bulk['last_id'];
        }
        $conditions = str_replace('%', '%%', self::CANDIDATES);
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.ID > %d AND {$conditions}{$join} ORDER BY p.ID ASC LIMIT 10",
            $args
        ));
        if ($wpdb->last_error) {
            self::schedule_scan(60);
            return;
        }
        $retry = false;
        foreach ($ids as $position => $raw_id) {
            $id = (int) $raw_id;
            if (Files::entries($id)) {
                if (!self::enqueue($id, true, $position * 2, $job)) {
                    $retry = true;
                    break;
                }
                if ($manual) {
                    $bulk['queued']++;
                }
            }
            if ($job !== '') {
                $bulk['scanned']++;
                $bulk['updated'] = time();
            }
            $cursor = $id;
        }
        $more = $retry || (count($ids) === 10 && ($job === '' || $cursor < $bulk['last_id']));
        if ($manual) {
            $bulk['cursor'] = $cursor;
            $bulk['running'] = $more;
            if ($job !== '' && !$more) {
                $bulk['updated'] = time();
            }
            update_option(self::BULK, $bulk, false);
        } else {
            update_option(self::SWEEP, $more ? $cursor : 0, false);
        }
        self::schedule_scan($more ? 20 : ($manual ? 60 : 12 * HOUR_IN_SECONDS));
    }

    public static function run_upload(int $id, bool $repair, int $offset): void
    {
        self::run($id, $repair, $offset, '', false, self::UPLOAD);
    }

    public static function run(int $id, bool $repair, int $offset, string $job = '', bool $failed = false, string $hook = self::ITEM): void
    {
        $lock = self::acquire();
        if ($lock === '') {
            $args = $job === '' ? array($id, $repair, $offset) : array($id, $repair, $offset, $job, $failed);
            wp_schedule_single_event(time() + self::SPACING, $hook, $args);
            return;
        }
        try {
            self::run_item($id, $repair, $offset, $job, $failed, $hook);
        } finally {
            self::release($lock);
        }
    }

    private static function run_item(int $id, bool $repair, int $offset, string $job, bool $failed, string $hook): void
    {
        $storage = Settings::storage();
        if ($job !== '') {
            $bulk = get_option(self::BULK, array());
            if (($bulk['job'] ?? '') !== $job || !self::active($bulk)) {
                return;
            }
            if (!$storage || $bulk['destination'] !== $storage->destination()) {
                self::interrupt_bulk();
                return;
            }
        }
        $files = Files::entries($id);
        if (!$storage || !$files) {
            self::finish_item($job, true);
            return;
        }
        $destination = $storage->destination();
        $previous = get_post_meta($id, Files::STATE, true);
        $state = Files::state($id, $destination);
        $deadline = microtime(true) + 9;
        $keys = array_keys($files);
        for ($index = $offset; $index < count($keys); $index++) {
            $relative = $keys[$index];
            try {
                if (!self::sync_entry($storage, $id, $relative, $files[$relative], $state, $repair)) {
                    $failed = true;
                }
            } catch (\RuntimeException $error) {
                $failed = true;
                update_option(self::LAST_ERROR, array('id' => $id, 'time' => time(), 'message' => $error->getMessage()), false);
                // Keep the attachment eligible for the next maintenance sweep even if its first transfer failed.
            }
            if (microtime(true) >= $deadline) {
                break;
            }
        }
        if (!self::save_files($id, $destination, $state, $previous)) {
            self::finish_item($job, true);
            return;
        }
        if ($index + 1 < count($keys)) {
            $args = $job === '' ? array($id, $repair, $index + 1) : array($id, $repair, $index + 1, $job, $failed);
            if (wp_schedule_single_event(time() + 3, $hook, $args) === true || wp_next_scheduled($hook, $args)) {
                return;
            }
            $failed = true;
        }
        self::finish_item($job, $failed);
    }

    public static function bulk_step(): void
    {
        foreach (wp_get_ready_cron_jobs() as $events) {
            if (!empty($events[self::UPLOAD])) {
                return;
            }
        }
        $lock = self::acquire('bulk');
        if ($lock === '') {
            return;
        }
        try {
            $bulk = get_option(self::BULK, array());
            if (empty($bulk['managed']) || !self::active($bulk) || self::paused($bulk)) {
                wp_clear_scheduled_hook(self::BULK_STEP);
                return;
            }
            if ($bulk['next'] > time()) {
                return;
            }
            $storage = Settings::storage();
            if (!$storage || $storage->destination() !== $bulk['destination']) {
                self::interrupt_bulk();
                wp_clear_scheduled_hook(self::BULK_STEP);
                return;
            }
            $deadline = microtime(true) + self::BATCH_SECONDS;
            $bulk['next'] = 0;
            for ($step = 0; $step < self::BATCH_STEPS && microtime(true) < $deadline; ++$step) {
                // Pause may be requested by another HTTP process while this batch holds the lease.
                wp_cache_delete(self::PAUSED, 'options');
                wp_cache_delete('notoptions', 'options');
                if (self::paused($bulk)) {
                    break;
                }
                $id = $bulk['attachment'];
                $files = $id ? Files::entries($id) : self::select_attachment($bulk);
                if ($bulk['attachment']) {
                    self::transfer_next($storage, $bulk, $files);
                }
                $bulk['total'] = self::active($bulk) ? max($bulk['total'], $bulk['scanned']) : $bulk['scanned'];
                $bulk['updated'] = time();
                update_option(self::BULK, $bulk, false);
                if (!self::active($bulk) || $bulk['next'] > time()) {
                    break;
                }
            }
            if (!self::active($bulk)) {
                wp_clear_scheduled_hook(self::BULK_STEP);
            }
        } finally {
            self::release($lock);
        }
    }

    private static function select_attachment(array &$bulk): array
    {
        global $wpdb;
        $conditions = str_replace('%', '%%', self::CANDIDATES);
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.ID > %d AND p.ID <= %d AND {$conditions} ORDER BY p.ID ASC LIMIT 10",
            $bulk['cursor'], $bulk['last_id']
        ));
        if ($wpdb->last_error) {
            $bulk['next'] = time() + 60;
            return array();
        }
        foreach ($ids as $raw_id) {
            $id = (int) $raw_id;
            $bulk['cursor'] = $id;
            $bulk['scanned']++;
            $files = Files::entries($id);
            if ($files) {
                $bulk['queued']++;
                $bulk['attachment'] = $id;
                return $files;
            }
        }
        $bulk['running'] = count($ids) === 10 && $bulk['cursor'] < $bulk['last_id'];
        return array();
    }

    private static function transfer_next(Storage $storage, array &$bulk, array $files): void
    {
        $id = $bulk['attachment'];
        foreach ($files as $relative => $original) {
            if (strcmp($relative, $bulk['after']) <= 0) {
                continue;
            }
            $destination = $storage->destination();
            $previous = get_post_meta($id, Files::STATE, true);
            $state = Files::state($id, $destination);
            try {
                $skip_current = ($bulk['mode'] ?? '') === 'missing';
                if (!self::sync_entry($storage, $id, $relative, $original, $state, true, $skip_current)) {
                    throw new \RuntimeException('未找到有效的本地图片或 R2 副本。');
                }
                if (!self::save_files($id, $destination, $state, $previous)) {
                    throw new \RuntimeException('附件已删除或同步记录无法保存。');
                }
            } catch (\RuntimeException $error) {
                $code = $error->getCode();
                if (in_array($code, array(401, 403, 404), true) && self::set_paused($bulk['job'], true)) {
                    update_option(self::LAST_ERROR, array('job' => $bulk['job'], 'id' => $id, 'time' => time(), 'message' => $error->getMessage()), false);
                    return;
                }
                if ($code === 408 || $code === 429 || ($code >= 500 && $code < 600)) {
                    $bulk['attempts']++;
                    $bulk['next'] = time() + 30 * (2 ** ($bulk['attempts'] - 1));
                    if ($bulk['attempts'] < 3) {
                        return;
                    }
                }
                $bulk['item_failed'] = true;
                update_option(self::LAST_ERROR, array('job' => $bulk['job'], 'id' => $id, 'time' => time(), 'message' => $error->getMessage()), false);
            }
            $bulk['after'] = $relative;
            $bulk['attempts'] = 0;
            if ($relative !== array_key_last($files)) {
                return;
            }
            break;
        }
        $bulk['processed']++;
        $bulk['failed'] += (int) ($bulk['item_failed'] || !$files);
        $bulk['attachment'] = 0;
        $bulk['after'] = '';
        $bulk['item_failed'] = false;
        $bulk['attempts'] = 0;
        if ($bulk['cursor'] >= $bulk['last_id']) {
            $bulk['running'] = false;
        }
    }

    private static function sync_entry(Storage $storage, int $id, string $relative, bool $original, array &$state, bool $repair, bool $skip_current = false): bool
    {
        $old_asset = $state[$relative]['id'] ?? '';
        $entry = self::sync_file($storage, $id, $relative, $original, $state[$relative] ?? array(), $repair, $skip_current);
        if (!$entry) {
            return false;
        }
        $state[$relative] = $entry;
        if ($old_asset !== '' && $old_asset !== $entry['id']) {
            wp_schedule_single_event(time() + DAY_IN_SECONDS, self::PURGE, array($storage->destination(), array($old_asset)));
        }
        return true;
    }

    private static function save_files(int $id, string $destination, array $state, $previous): bool
    {
        wp_cache_delete($id, 'posts');
        if (get_post_type($id) !== 'attachment') {
            if ($state) {
                wp_schedule_single_event(time(), self::PURGE, array($destination, array_column($state, 'id')));
            }
            return false;
        }
        $next = array('destination' => $destination, 'files' => $state);
        return $next === $previous || (bool) update_post_meta($id, Files::STATE, $next, $previous);
    }

    private static function finish_item(string $job, bool $failed): void
    {
        if ($job === '') {
            return;
        }
        $bulk = get_option(self::BULK, array());
        if (($bulk['job'] ?? '') !== $job || !self::active($bulk) || $bulk['processed'] >= $bulk['queued']) {
            return;
        }
        $bulk['processed']++;
        $bulk['failed'] += (int) $failed;
        $bulk['updated'] = time();
        update_option(self::BULK, $bulk, false);
    }

    private static function sync_file(Storage $storage, int $id, string $relative, bool $original, array $entry, bool $repair, bool $skip_current): array
    {
        $path = Files::disk_path($relative);
        if ($path === '') {
            if ($repair) {
                throw new \RuntimeException('本地图片目录不可用，无法核对或恢复此文件。');
            }
            return $entry;
        }
        if (!is_file($path)) {
            if (!$entry || !$repair) {
                return $entry;
            }
            $key = ($original ? 'original/' : 'public/') . $entry['id'];
            $body = $storage->get_image($key, Files::LIMIT);
            if ($body === null && $original) {
                $body = $storage->get_image('public/' . $entry['id'], Files::LIMIT);
            }
            if ($body === null || !hash_equals($entry['hash'], hash('sha256', $body)) || Files::type($body) !== $entry['type']) {
                throw new \RuntimeException('本地图缺失，R2 上没有校验一致的副本。');
            }
            self::restore($path, $body);
        } elseif ($entry && (!$repair || $skip_current) && $entry['mtime'] === (int) filemtime($path) && $entry['size'] === (int) filesize($path)) {
            return $entry;
        }

        $mtime = (int) filemtime($path);
        $body = @file_get_contents($path, false, null, 0, Files::LIMIT + 1);
        $type = is_string($body) && strlen($body) <= Files::LIMIT ? Files::type($body) : '';
        if ($type === '') {
            throw new \RuntimeException('图片无法读取、格式不受支持或超过 16 MiB。');
        }
        $hash = hash('sha256', $body);
        $asset = Files::asset_id($id, $relative, $hash);
        $prefixes = $original ? array('original/', 'public/') : array('public/');
        foreach ($prefixes as $prefix) {
            if (($entry['id'] ?? '') !== $asset || !$storage->has_image($prefix . $asset)) {
                $storage->put_image($prefix . $asset, $body, $type);
            }
        }
        return array('id' => $asset, 'hash' => $hash, 'type' => $type, 'mtime' => $mtime, 'size' => strlen($body));
    }

    private static function restore(string $path, string $body): void
    {
        $tmp = dirname($path) . '/.mtk-media-' . wp_generate_uuid4() . '.tmp';
        $handle = @fopen($tmp, 'xb');
        if (!$handle) {
            throw new \RuntimeException('无法在媒体目录中创建恢复文件。');
        }
        try {
            $written = fwrite($handle, $body);
            fclose($handle);
            if ($written !== strlen($body) || (!@link($tmp, $path) && !is_file($path))) {
                throw new \RuntimeException('无法将 R2 副本恢复到原路径。');
            }
        } finally {
            @unlink($tmp);
        }
        clearstatcache(true, $path);
    }

    public static function forget(int $id): void
    {
        $storage = Settings::storage();
        $files = $storage ? Files::state($id, $storage->destination()) : array();
        if ($files) {
            wp_schedule_single_event(time() + 1, self::PURGE, array($storage->destination(), array_column($files, 'id')));
        }
    }

    public static function purge(string $destination, array $assets): void
    {
        $storage = Settings::storage();
        if (!$storage || $storage->destination() !== $destination) {
            return;
        }
        foreach ($assets as $asset) {
            if (is_string($asset) && preg_match('/\A[a-f0-9]{64}\z/', $asset)) {
                $id = (int) hexdec(substr($asset, 0, 16));
                if (get_post_type($id) === 'attachment' && in_array($asset, array_column(Files::state($id, $destination), 'id'), true)) {
                    continue;
                }
                try {
                    $storage->delete_image('public/' . $asset);
                } catch (\RuntimeException $error) {
                    wp_schedule_single_event(time() + 600, self::PURGE, array($destination, array($asset)));
                }
            }
        }
    }
}
