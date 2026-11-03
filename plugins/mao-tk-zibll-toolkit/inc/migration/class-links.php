<?php
namespace MaoTK\ZibllToolkit\Migration;

defined('ABSPATH') || exit;

final class Links
{
    public const MODULE = 'data-migration-links';
    private const FIELDS = array('link_name', 'link_url', 'link_image', 'link_description', 'link_notes', 'link_target', 'link_rss', 'link_visible', 'link_rating', 'link_rel', 'link_owner');

    public static function authorize(): void
    {
        if (!current_user_can('manage_options') || !current_user_can('manage_links')) { throw new \RuntimeException('需要站点设置与链接管理权限。'); }
    }

    public static function all(int $limit = 0): array
    {
        // Direct native table read: private-links presentation filters must not hide an admin export.
        global $wpdb;
        $tail = $limit > 0 ? ' LIMIT ' . min(Package::MAX_LINKS, $limit) : '';
        return (array) $wpdb->get_results("SELECT * FROM {$wpdb->links} ORDER BY link_id ASC" . $tail, ARRAY_A);
    }

    public static function existing(string $url): array
    {
        global $wpdb;
        $variants = array_values(array_unique(array($url, esc_html($url), wp_specialchars_decode($url, ENT_QUOTES))));
        $placeholders = implode(',', array_fill(0, count($variants), '%s'));
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->links} WHERE link_url IN ($placeholders) ORDER BY link_id ASC", $variants), ARRAY_A);
        // Some DB collations compare URLs case-insensitively. Never merge distinct referral URLs.
        return array_values(array_filter($rows, static function ($row) use ($variants) { return in_array($row['link_url'], $variants, true); }));
    }

    public static function snapshot(int $id): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->links} WHERE link_id=%d", $id), ARRAY_A);
        if (!$row) { return array(); }
        $out = array_intersect_key($row, array_flip(self::FIELDS));
        $cats = wp_get_object_terms($id, 'link_category', array('fields' => 'ids'));
        if (is_wp_error($cats)) { throw new \RuntimeException('读取链接分类失败。'); }
        $out['link_category'] = array_map('intval', $cats); sort($out['link_category']);
        return $out;
    }

    public static function fingerprint(array $row): string { return hash('sha256', wp_json_encode($row)); }

    public static function export(array $ids, int $category, string $visibility, bool $images, bool $remote): array
    {
        self::authorize();
        if (!class_exists('ZipArchive')) { throw new \RuntimeException('服务器未启用 ZipArchive。'); }
        $items = array(); $categories = array();
        foreach (self::all() as $row) {
            if ($ids && !in_array((int) $row['link_id'], $ids, true)) { continue; }
            if ($visibility === 'public' && $row['link_visible'] !== 'Y') { continue; }
            if ($visibility === 'private' && $row['link_visible'] !== 'N') { continue; }
            $terms = wp_get_object_terms((int) $row['link_id'], 'link_category');
            if (is_wp_error($terms)) { throw new \RuntimeException('读取分类失败。'); }
            if ($category && !in_array($category, array_map(static function ($t) { return (int) $t->term_id; }, $terms), true)) { continue; }
            $keys = array();
            foreach ($terms as $t) {
                $keys[] = (string) $t->term_id; $seen = array();
                while ($t && !is_wp_error($t)) {
                    if (isset($seen[$t->term_id])) { throw new \RuntimeException('分类层级存在循环。'); }
                    $seen[$t->term_id] = true;
                    $categories[(string) $t->term_id] = array('key' => (string) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'description' => $t->description, 'parent' => $t->parent ? (string) $t->parent : '');
                    $t = $t->parent ? get_term($t->parent, 'link_category') : null;
                }
            }
            $owner = get_userdata((int) $row['link_owner']);
            $image_url = $row['link_image'];
            if (strpos($image_url, '//') === 0) { $image_url = (is_ssl() ? 'https:' : 'http:') . $image_url; }
            elseif (strpos($image_url, '/') === 0) { $image_url = home_url($image_url); }
            $items[] = Package::link(array('key' => (string) $row['link_id'], 'name' => $row['link_name'], 'url' => $row['link_url'], 'description' => $row['link_description'],
                'notes' => $row['link_notes'], 'image' => $image_url, 'target' => $row['link_target'], 'visible' => $row['link_visible'], 'rating' => $row['link_rating'],
                'rel' => $row['link_rel'], 'rss' => $row['link_rss'], 'owner' => $owner ? $owner->user_login : '', 'categories' => $keys));
            if (count($items) > Package::MAX_LINKS) { throw new \RuntimeException('当前范围超过 1,000 条，请按分类或指定链接分批导出。'); }
        }
        if (!$items) { throw new \RuntimeException('当前筛选没有可导出的链接。'); }
        $job = Store::create('export', $items, array('source' => home_url('/'), 'categories' => $categories, 'assets' => array(), 'images' => $images, 'remote' => $remote));
        $job['status'] = 'ready'; Store::save($job); return $job;
    }

    public static function prepare(string $file, string $policy): array
    {
        self::authorize();
        if (!in_array($policy, array('skip', 'update'), true)) { throw new \RuntimeException('重复处理规则无效。'); }
        $job = Store::create('import', array(), array('policy' => $policy));
        try {
            $data = Package::read($file, Store::dir($job['id']));
            $job = array_merge($job, $data);
            $job['preview'] = array();
            $seen = array();
            foreach ($job['items'] as $i => $item) {
                $rows = self::existing($item['url']);
                $conflict = count($rows) > 1 || isset($seen[$item['url']]);
                $seen[$item['url']] = true;
                $job['preview'][$i] = array('action' => $conflict ? 'conflict' : ($rows ? $policy : 'add'), 'target' => $rows ? (int) $rows[0]['link_id'] : 0,
                    'hash' => count($rows) === 1 ? self::fingerprint(self::snapshot((int) $rows[0]['link_id'])) : '');
            }
            if ($data['legacy']) { $job['warnings'][] = '已识别旧版 links-export.zip；旧清单没有来源站点与校验值，已按当前文件检查图片，旧 subdir 路径不会写回。'; }
            $outside = count(array_filter($job['items'], static function ($item) { return $item['image'] !== '' && $item['asset'] === ''; }));
            if ($outside) { $job['warnings'][] = $outside . ' 条链接的 LOGO 不在包内，将保留原图片网址；不会自动请求外部图片。'; }
            Store::save($job); return $job;
        } catch (\Throwable $error) {
            $job['status'] = 'invalid'; $job['warnings'][] = $error->getMessage(); Store::save($job); throw $error;
        }
    }

    public static function command(string $id, string $command, int $revision): array
    {
        self::authorize(); Store::recover('job:' . $id); $lock = Store::lock('job:' . $id);
        try {
            $job = Store::load($id);
            // A retried/lost HTTP response must never replay a committed item.
            if ($job['revision'] !== $revision) { return $job; }
            if ($command === 'pause') { if ($job['status'] === 'running') { $job['status'] = 'paused'; Store::save($job); } return $job; }
            if ($command === 'retry') {
                if (!in_array($job['status'], array('complete', 'paused'), true)) { throw new \RuntimeException('请先暂停或等待任务完成。'); }
                foreach ($job['results'] as $i => $r) { if ($r['state'] === 'failed') { unset($job['results'][$i]); } }
                $job['cursor'] = 0; $job['status'] = 'paused'; Store::save($job); return $job;
            }
            if ($command === 'start') {
                if (!in_array($job['status'], array('preview', 'ready', 'paused', 'running'), true)) { return $job; }
                if ($job['type'] === 'import' && !current_user_can('upload_files') && $job['assets']) { throw new \RuntimeException('导入 LOGO 需要媒体上传权限。'); }
                $job['status'] = 'running'; Store::save($job); return $job;
            }
            if ($command !== 'step' || $job['status'] !== 'running') { return $job; }
            while ($job['cursor'] < count($job['items']) && isset($job['results'][$job['cursor']])) { $job['cursor']++; }
            if ($job['cursor'] < count($job['items'])) {
                $i = $job['cursor'];
                try {
                    $message = $job['type'] === 'import' ? self::import_item($job, $i) : self::export_item($job, $i);
                    $job['results'][$i] = array('name' => $job['items'][$i]['name'], 'state' => $message['state'], 'message' => $message['message'], 'id' => $message['id'] ?? 0);
                } catch (\Throwable $error) {
                    $job['results'][$i] = array('name' => $job['items'][$i]['name'], 'state' => 'failed', 'message' => $error->getMessage(), 'id' => 0);
                }
                $job['cursor']++; Store::save($job);
            }
            if ($job['cursor'] >= count($job['items'])) {
                if ($job['type'] === 'export') { self::finish_export($job); }
                $job['status'] = 'complete'; Store::save($job);
            }
            return $job;
        } finally { delete_option($lock); }
    }

    private static function export_item(array &$job, int $i): array
    {
        $item = &$job['items'][$i];
        if (!$job['images'] || $item['image'] === '') { return array('state' => 'success', 'message' => '链接资料已准备'); }
        $upload = wp_upload_dir(); $path = ''; $bytes = null;
        $prefix = rtrim($upload['baseurl'], '/') . '/';
        if (strpos($item['image'], $prefix) !== 0 && !$job['remote']) { return array('state' => 'success', 'message' => '链接已准备，外部 LOGO 保留原网址'); }
        if (strpos($item['image'], $prefix) === 0) {
            $relative = rawurldecode(substr($item['image'], strlen($prefix)));
            $path = realpath($upload['basedir'] . '/' . $relative);
            if ($path && Store::inside($path, (string) realpath($upload['basedir'])) && is_file($path) && filesize($path) <= Package::MAX_IMAGE) { $bytes = file_get_contents($path); }
        }
        if (!is_string($bytes) && $job['remote']) {
            $response = wp_safe_remote_get($item['image'], array('timeout' => 6, 'redirection' => 3, 'limit_response_size' => Package::MAX_IMAGE + 1));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) { $bytes = wp_remote_retrieve_body($response); }
        }
        if (!is_string($bytes)) { throw new \RuntimeException('LOGO 未能打包；可重试，或明确选择不包含 LOGO 后重新导出。'); }
        $info = Package::image($bytes); $hash = $info['sha256']; $info['file'] = 'images/' . $hash . '.' . $info['ext'];
        $size = array_sum(array_column($job['assets'], 'bytes')) + (isset($job['assets'][$hash]) ? 0 : $info['bytes']);
        if ($size > Package::MAX_ZIP - Package::MAX_JSON - 1048576) { throw new \RuntimeException('图片总大小超过单包限制，请按分类拆分导出。'); }
        $aid = attachment_url_to_postid($item['image']);
        if ($aid) {
            $a = get_post($aid);
            $info['meta'] = array('title' => $a->post_title, 'caption' => $a->post_excerpt, 'description' => $a->post_content, 'alt' => (string) get_post_meta($aid, '_wp_attachment_image_alt', true));
        }
        if (file_put_contents(Store::dir($job['id']) . '/' . basename($info['file']), $bytes, LOCK_EX) !== strlen($bytes)) { throw new \RuntimeException('写入 LOGO 失败。'); }
        $item['asset'] = $hash; $job['assets'][$hash] = $info;
        return array('state' => 'success', 'message' => '链接与 LOGO 已准备');
    }

    private static function finish_export(array &$job): void
    {
        $good = array();
        foreach ($job['items'] as $i => $item) { if (($job['results'][$i]['state'] ?? '') === 'success') { $good[] = $item; } }
        $dir = Store::dir($job['id']);
        $file = $dir . '/links.zip';
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { throw new \RuntimeException('无法生成 ZIP，任务可继续重试。'); }
        $manifest = array('format' => 'maotk-links', 'version' => 1, 'source' => $job['source'], 'created_at' => gmdate('c'), 'toolkit' => MAO_TK_ZIBLL_TOOLKIT_VERSION,
            'links' => $good, 'categories' => array_values($job['categories']), 'assets' => $job['assets']);
        $raw = wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($raw) || strlen($raw) > Package::MAX_JSON) { $zip->close(); throw new \RuntimeException('清单大小超出限制。'); }
        $zip->addFromString('manifest.json', $raw);
        foreach ($job['assets'] as $asset) { $zip->addFile($dir . '/' . basename($asset['file']), $asset['file']); }
        if (!$zip->close() || !is_file($file)) { throw new \RuntimeException('ZIP 写入失败。'); }
        $job['sha256'] = hash_file('sha256', $file);
        $job['partial'] = count($good) !== count($job['items']);
    }

    private static function terms(array $categories, array $keys, array &$seen = array()): array
    {
        $ids = array();
        foreach ($keys as $key) {
            if (isset($seen[$key])) { $ids[] = $seen[$key]; continue; }
            $c = $categories[$key]; $parent = 0;
            if ($c['parent'] !== '') { $parents = self::terms($categories, array($c['parent']), $seen); $parent = $parents[0]; }
            $term = term_exists($c['slug'] !== '' ? $c['slug'] : $c['name'], 'link_category', $parent);
            if (!$term) { $term = wp_insert_term($c['name'], 'link_category', array('slug' => $c['slug'], 'description' => $c['description'], 'parent' => $parent)); }
            if (is_wp_error($term)) { throw new \RuntimeException('分类写入失败：' . $c['name']); }
            $id = is_array($term) ? (int) $term['term_id'] : (int) $term;
            $existing = get_term($id, 'link_category');
            if (!$existing || is_wp_error($existing) || (int) $existing->parent !== $parent) { throw new \RuntimeException('分类层级冲突，请先检查目标站分类。'); }
            $seen[$key] = $id; $ids[] = $id;
        }
        return $ids;
    }

    private static function media(array $job, array $item): string
    {
        if ($item['asset'] === '') { return $item['image']; }
        if (!current_user_can('upload_files')) { throw new \RuntimeException('没有媒体上传权限。'); }
        $a = $job['assets'][$item['asset']]; $sha = $a['sha256'];
        $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_mao_tk_migration_sha256', 'meta_value' => $sha, 'fields' => 'ids', 'suppress_filters' => true));
        if ($existing && is_file((string) get_attached_file($existing[0]))) { return (string) wp_get_attachment_url($existing[0]); }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $src = Store::dir($job['id']) . '/' . $a['file'];
        if (!is_file($src) || !hash_equals($sha, (string) hash_file('sha256', $src))) { throw new \RuntimeException('LOGO 文件已丢失或变化，请重新上传迁移包。'); }
        $tmp = Store::dir($job['id']) . '/sideload-' . $sha . '.' . $a['ext'];
        if (!copy($src, $tmp)) { throw new \RuntimeException('无法准备 LOGO。'); }
        $id = media_handle_sideload(array('name' => $sha . '.' . $a['ext'], 'tmp_name' => $tmp), 0, $item['name'] . ' LOGO');
        if (is_wp_error($id)) { @unlink($tmp); throw new \RuntimeException('LOGO 入库失败：' . $id->get_error_message()); }
        update_post_meta($id, '_mao_tk_migration_sha256', $sha);
        if (!empty($a['meta'])) {
            wp_update_post(wp_slash(array('ID' => $id, 'post_title' => $a['meta']['title'], 'post_excerpt' => $a['meta']['caption'], 'post_content' => $a['meta']['description'])));
            update_post_meta($id, '_wp_attachment_image_alt', wp_slash($a['meta']['alt']));
        }
        return (string) wp_get_attachment_url($id);
    }

    private static function import_item(array &$job, int $i): array
    {
        global $wpdb;
        $item = $job['items'][$i]; $preview = $job['preview'][$i];
        $key = 'link:' . $item['url']; Store::recover($key); $lock = Store::lock($key);
        try {
            $rows = self::existing($item['url']);
            if ($preview['action'] === 'conflict' || count($rows) > 1) { throw new \RuntimeException('相同网址对应多条记录或包内重复，已停止该项，请清理冲突后重新预检。'); }
            $id = $rows ? (int) $rows[0]['link_id'] : 0;
            if ($id && $job['policy'] === 'skip' && !isset($job['journal'][$i])) { return array('state' => 'skip', 'message' => '已存在，未改动链接与 LOGO', 'id' => $id); }
            $mapkey = 'mao_tk_migration_map_' . hash('sha256', $job['source'] . '|' . $item['key']);
            $mapping = get_option($mapkey);
            $fingerprint = self::fingerprint($item);
            if (is_array($mapping) && ($mapping['job'] ?? '') === $job['id'] && $id === (int) $mapping['id'] && ($mapping['input'] ?? '') === $fingerprint && ($mapping['output'] ?? '') === self::fingerprint(self::snapshot($id))) {
                return array('state' => 'success', 'message' => '已在本任务完成，未重复写入', 'id' => $id);
            }
            // Recover the narrow crash window between native DB write and task checkpoint.
            // Only the exact journalled fields qualify; native/manual edits remain conflicts.
            if ($id && isset($job['journal'][$i])) {
                $intent = $job['journal'][$i]; $actual = self::snapshot($id); $match = (int) $intent['target'] === 0 || (int) $intent['target'] === $id;
                foreach ($intent['data'] as $field => $value) {
                    if (in_array($field, array('link_id', 'link_category'), true)) { continue; }
                    $a = (string) ($actual[$field] ?? ''); $b = (string) $value;
                    if (in_array($field, array('link_url', 'link_image', 'link_rss'), true)) { $a = wp_specialchars_decode($a, ENT_QUOTES); $b = wp_specialchars_decode($b, ENT_QUOTES); }
                    if (!array_key_exists($field, $actual) || $a !== $b) { $match = false; break; }
                }
                $expected_cats = $intent['data']['link_category']; sort($expected_cats);
                $old_cats = $intent['old_categories']; sort($old_cats);
                if ($match && ($actual['link_category'] === $expected_cats || $actual['link_category'] === $old_cats)) {
                    $assigned = wp_set_object_terms($id, $expected_cats, 'link_category', false);
                    if (is_wp_error($assigned)) { throw new \RuntimeException('中断项分类恢复失败，请重试。'); }
                    clean_bookmark_cache($id);
                    update_option($mapkey, array('id' => $id, 'job' => $job['id'], 'input' => $fingerprint, 'output' => self::fingerprint(self::snapshot($id))), false);
                    return array('state' => 'success', 'message' => '已恢复中断后的写入，未重复新增', 'id' => $id);
                }
            }
            if ($id !== (int) $preview['target'] || ($id && self::fingerprint(self::snapshot($id)) !== $preview['hash'])) { throw new \RuntimeException('预检后目标链接已变化，请重新预检，避免覆盖他人修改。'); }
            $media_lock = '';
            if ($item['asset'] !== '') { $mk = 'asset:' . $job['assets'][$item['asset']]['sha256']; Store::recover($mk); $media_lock = Store::lock($mk); }
            try { $image = self::media($job, $item); } finally { if ($media_lock) { delete_option($media_lock); } }
            $cats = self::terms($job['categories'], $item['categories']);
            if ($id && !isset($job['backups'][$id])) {
                $job['backups'][$id] = self::snapshot($id);
                Store::save($job); // Persist before writing, including when the request dies later.
            }
            $owner = $item['owner'] !== '' ? get_user_by('login', $item['owner']) : false;
            $data = array('link_name' => $item['name'], 'link_url' => $item['url'], 'link_image' => $image,
                'link_description' => $item['description'], 'link_notes' => $item['notes'], 'link_target' => $item['target'], 'link_rss' => $item['rss'],
                'link_visible' => $item['visible'], 'link_rating' => $item['rating'], 'link_rel' => $item['rel'],
                'link_owner' => $owner && is_user_member_of_blog($owner->ID) ? $owner->ID : get_current_user_id(), 'link_category' => $cats);
            if ($id) { $data['link_id'] = $id; }
            $job['journal'][$i] = array('target' => $id, 'data' => $data, 'old_categories' => $id ? $job['backups'][$id]['link_category'] : array((int) get_option('default_link_category')));
            Store::save($job);
            require_once ABSPATH . 'wp-admin/includes/bookmark.php';
            $result = wp_insert_link(wp_slash($data), true);
            if (is_wp_error($result) || !$result) { throw new \RuntimeException('链接写入失败，请检查数据库后重试。'); }
            $result = (int) $result;
            // Empty categories have an explicit meaning; do not silently attach the site's default category.
            $assigned = wp_set_object_terms($result, $cats, 'link_category', false);
            if (is_wp_error($assigned)) { throw new \RuntimeException('链接已写入，但分类保存失败，请核对后重新预检。'); }
            clean_bookmark_cache($result);
            $saved = self::snapshot($result);
            if (wp_specialchars_decode($saved['link_url'] ?? '', ENT_QUOTES) !== wp_specialchars_decode($item['url'], ENT_QUOTES) || ($saved['link_visible'] ?? '') !== $item['visible']) { throw new \RuntimeException('网址或私密标记未完整保存，请核对后重新预检。'); }
            update_option($mapkey, array('id' => $result, 'job' => $job['id'], 'input' => $fingerprint, 'output' => self::fingerprint($saved)), false);
            return array('state' => 'success', 'message' => $id ? '已更新，原记录已备份' : '已新增，私密状态保持', 'id' => $result);
        } finally { delete_option($lock); }
    }
}
