<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

/** Explicit, bounded conversion of stored references; also supports downgrading to the old signer. */
final class ReferenceMigration
{
    public const STATE = 'mao_tk_zibll_toolkit_r2_ref_migration';
    public const JOURNAL = 'mao_tk_zibll_toolkit_r2_ref_undo_';
    private const LOCK = 'mao_tk_zibll_toolkit_r2_ref_lock';
    private const REF = '([A-Za-z0-9_-]{1,1366}\.[a-f0-9]{64})(?![A-Za-z0-9_-])';
    private const BATCH = 100;

    /** Read-only: opening settings never scans articles or generates a key. */
    public static function status(): array
    {
        $state = get_option(self::STATE, array());
        return is_array($state) ? $state : array();
    }

    private static function acquire(): string
    {
        global $wpdb;
        $token = (time() + 900) . ':' . bin2hex(random_bytes(16));
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK
        ));
        if ($existing === null) {
            $claimed = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                self::LOCK, $token
            ));
        } elseif ((int) $existing < time()) {
            $claimed = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                $token, self::LOCK, $existing
            ));
        }
        if (!empty($claimed)) {
            wp_cache_delete(self::STATE, 'options');
            wp_cache_delete('notoptions', 'options');
            return $token;
        }
        throw new \RuntimeException(__('另一批下载引用正在处理，请稍后重试。', 'mao-tk-zibll-toolkit'));
    }

    private static function release(string $token): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            self::LOCK, $token
        ));
        wp_cache_delete(self::LOCK, 'options');
        wp_cache_delete('notoptions', 'options');
    }

    /** The cursor advances after a no-op or successful write; a conflict is retried on the next click. */
    public static function run(bool $legacy = false, bool $restart = false): array
    {
        $token = self::acquire();
        try {
            if (!$legacy) {
                Runtime::reference_secret(true);
            }
            $mode = $legacy ? 'legacy' : 'current';
            $state = self::status();
            if ($restart || ($state['mode'] ?? '') !== $mode) {
                $state = array('mode' => $mode, 'kind' => 'content', 'cursor' => 0, 'done' => false, 'total' => 0);
            }
            $result = array('updated' => 0, 'references' => 0, 'conflicts' => 0, 'remaining' => false);
            if ($state['done']) {
                return $result;
            }
            $deadline = microtime(true) + 5;
            $slots = self::BATCH;
            while ($slots > 0 && microtime(true) < $deadline && !$state['done']) {
                $page_size = min(10, $slots);
                $rows = self::candidates($state['kind'], (int) $state['cursor'], $page_size);
                foreach ($rows as $row) {
                    if (microtime(true) >= $deadline) {
                        break 2;
                    }
                    $slots--;
                    $rewrite = self::rewrite($row, $legacy);
                    if ($rewrite['changes']) {
                        self::backup($row, $rewrite['changes'], $mode);
                        if (!self::write($row, $rewrite['value'])) {
                            $result['conflicts']++;
                            break 2;
                        }
                        $result['updated']++;
                        $result['references'] += count($rewrite['changes']);
                    }
                    $state['cursor'] = $row['kind'] === 'content' ? $row['post_id'] : $row['meta_id'];
                }
                if (count($rows) < $page_size) {
                    if ($state['kind'] === 'content') {
                        $state['kind'] = 'meta';
                        $state['cursor'] = 0;
                    } else {
                        $state['done'] = true;
                    }
                }
            }
            $state['total'] += $result['references'];
            if (!update_option(self::STATE, $state, false) && self::status() !== $state) {
                throw new \RuntimeException(__('无法保存扫描位置，重试时会跳过已处理的引用。', 'mao-tk-zibll-toolkit'));
            }
            $result['remaining'] = !$state['done'];
            return $result;
        } finally {
            self::release($token);
        }
    }

    private static function candidates(string $kind, int $cursor, int $limit): array
    {
        global $wpdb;
        $native = '%' . $wpdb->esc_like('maotk_ref=') . '%';
        if ($kind === 'content') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID > %d AND post_type <> 'revision'
                 AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)
                 ORDER BY ID ASC LIMIT %d",
                $cursor, '%' . $wpdb->esc_like(PublicDownload::QUERY . '=') . '%',
                '%' . $wpdb->esc_like(PublicDownload::LEGACY_ACTION) . '%', $native, $limit
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d
                 AND meta_key = 'posts_zibpay' AND meta_value LIKE %s ORDER BY meta_id ASC LIMIT %d",
                $cursor, $native, $limit
            ), ARRAY_A);
        }
        if (!is_array($rows) || $wpdb->last_error) {
            throw new \RuntimeException(__('无法读取旧下载引用，未完成本批扫描。', 'mao-tk-zibll-toolkit'));
        }
        return array_map(static function (array $row) use ($kind): array {
            return $kind === 'content'
                ? array('kind' => $kind, 'post_id' => (int) $row['ID'], 'meta_id' => 0, 'value' => $row['post_content'])
                : array('kind' => $kind, 'post_id' => (int) $row['post_id'], 'meta_id' => (int) $row['meta_id'], 'value' => $row['meta_value']);
        }, $rows);
    }

    private static function backup(array $row, array $changes, string $mode): void
    {
        // Only the replaced references are stored, never full articles or payment settings.
        $entry = array('mode' => $mode, 'kind' => $row['kind'], 'post_id' => $row['post_id'], 'meta_id' => $row['meta_id'], 'changes' => $changes);
        $key = self::JOURNAL . hash('sha256', serialize($entry));
        if (!add_option($key, $entry, '', false) && get_option($key, null) !== $entry) {
            throw new \RuntimeException(__('无法备份下载引用，未修改对应文章或下载设置。', 'mao-tk-zibll-toolkit'));
        }
    }

    private static function write(array $row, string $next): bool
    {
        global $wpdb;
        // HEX compares the full value byte for byte even on case-insensitive database collations.
        if ($row['kind'] === 'content') {
            $ok = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d AND HEX(post_content) = HEX(%s)",
                $next, $row['post_id'], $row['value']
            ));
            if ($ok === 1) {
                clean_post_cache($row['post_id']);
            }
        } else {
            $ok = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d AND HEX(meta_value) = HEX(%s)",
                $next, $row['meta_id'], $row['value']
            ));
            if ($ok === 1) {
                wp_cache_delete($row['post_id'], 'post_meta');
            }
        }
        return $ok === 1;
    }

    /** Only validated references change. The new signature is the same length for serialized meta. */
    public static function rewrite(array $row, bool $legacy = false): array
    {
        $value = $row['value'];
        $changes = array();
        $sep = '(?:&amp;|&#038;|&)';
        $patterns = array(
            '/[?&;]p=([1-9][0-9]{0,18})' . $sep . preg_quote(PublicDownload::QUERY, '/') . '=' . self::REF . '/',
            '/[?&;]post_id=([1-9][0-9]{0,18})' . $sep . 'ref=' . self::REF . '/'
        );
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $next = Runtime::resign_reference((int) $match[1], $match[2], true, $legacy);
                    if ($next !== '') {
                        $changes[$match[2]] = $next;
                    }
                }
            }
        }
        if (preg_match_all('/maotk_ref=' . self::REF . '/', $value, $matches)) {
            foreach ($matches[1] as $ref) {
                $next = Runtime::resign_reference((int) $row['post_id'], $ref, false, $legacy);
                if ($next !== '') {
                    $changes[$ref] = $next;
                }
            }
        }
        return array('value' => $changes ? strtr($value, $changes) : $value, 'changes' => $changes);
    }
}
