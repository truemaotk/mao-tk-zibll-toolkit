<?php
namespace MaoTK\ZibllToolkit\Migration;

defined('ABSPATH') || exit;

/** Versioned JSON + original image bytes; ZIP entries are read, never extractTo(). */
final class Package
{
    public const MAX_LINKS = 1000;
    public const MAX_ZIP = 33554432;
    public const MAX_EXPANDED = 67108864;
    public const MAX_IMAGE = 2097152;
    public const MAX_JSON = 8388608;

    public static function path(string $path): bool
    {
        return $path !== '' && strlen($path) < 240 && $path[0] !== '/' && !preg_match('#[\\\\\x00-\x1f:]|(^|/)\.\.?(/|$)|//|/$#', $path);
    }

    public static function text($value, int $max = 1000): string
    {
        if (!is_string($value) || strlen($value) > $max || strpos($value, "\0") !== false) { throw new \RuntimeException('清单字段类型或长度无效。'); }
        return $value;
    }

    public static function url($value, bool $empty = false): string
    {
        $value = self::text($value, 8192);
        if ($empty && $value === '') { return ''; }
        $p = wp_parse_url($value);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), array('http', 'https'), true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || preg_match('/[\x00-\x20<>"\\\\]/', $value)) {
            throw new \RuntimeException('链接必须是完整的 HTTP/HTTPS 地址，且不能包含账号密码或控制字符。');
        }
        return $value; // Referral query parameters, order and encoding stay byte-for-byte intact.
    }

    public static function image(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE) { throw new \RuntimeException('LOGO 为空或超过 2 MiB。'); }
        $info = @getimagesizefromstring($bytes);
        $types = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico');
        if (!$info || !isset($types[$info['mime']]) || $info[0] > 12000 || $info[1] > 12000 || $info[0] * $info[1] > 40000000) { throw new \RuntimeException('LOGO 不是支持的图片，或图片像素过大；SVG 和可执行文件不会导入。'); }
        return array('sha256' => hash('sha256', $bytes), 'ext' => $types[$info['mime']], 'mime' => $info['mime'], 'bytes' => strlen($bytes));
    }

    public static function read(string $file, string $dir): array
    {
        if (!class_exists('ZipArchive')) { throw new \RuntimeException('服务器未启用 ZipArchive。'); }
        if (!is_file($file) || filesize($file) > self::MAX_ZIP) { throw new \RuntimeException('ZIP 不存在或超过 32 MiB。'); }
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CHECKCONS) !== true) { throw new \RuntimeException('ZIP 无法打开或校验失败。'); }
        try {
            if ($zip->numFiles > 2100) { throw new \RuntimeException('ZIP 文件数量过多。'); }
            $names = array(); $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i); $name = $s['name'];
                if (substr($name, -1) === '/' && in_array($name, array('images/'), true)) { continue; }
                if (!self::path($name) || isset($names[strtolower($name)]) || ($name !== 'manifest.json' && !preg_match('#^images/[^/]+\.(png|jpe?g|webp|gif|ico|bmp)$#iD', $name))) { throw new \RuntimeException('ZIP 含非法、重复或不支持的路径。'); }
                $opsys = 0; $attr = 0; $zip->getExternalAttributesIndex($i, $opsys, $attr);
                if ((($attr >> 16) & 0170000) === 0120000 || !empty($s['encryption_method'])) { throw new \RuntimeException('不接受符号链接或加密 ZIP。'); }
                $limit = $name === 'manifest.json' ? self::MAX_JSON : self::MAX_IMAGE;
                $total += (int) $s['size'];
                if ($s['size'] > $limit || $total > self::MAX_EXPANDED) { throw new \RuntimeException('ZIP 解压大小超过限制。'); }
                $names[strtolower($name)] = $name;
            }
            $raw = $zip->getFromName('manifest.json');
            $data = is_string($raw) ? json_decode($raw, true, 64) : null;
            if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) { throw new \RuntimeException('manifest.json 缺失或损坏。'); }
            $legacy = !isset($data['format']);
            if ($legacy) { $data = self::legacy($data); }
            if (($data['format'] ?? '') !== 'maotk-links' || ($data['version'] ?? null) !== 1 || !is_array($data['links'] ?? null) || !is_array($data['categories'] ?? null) || !is_array($data['assets'] ?? null)) { throw new \RuntimeException('迁移格式或版本不支持。'); }
            if (count($data['links']) < 1 || count($data['links']) > self::MAX_LINKS || count($data['categories']) > 2000) { throw new \RuntimeException('链接或分类数量超出限制。'); }
            $data['source'] = self::text($data['source'] ?? '', 1000);
            if ($data['source'] === '') { throw new \RuntimeException('迁移包缺少来源标识。'); }
            $categories = array();
            foreach ($data['categories'] as $c) {
                if (!is_array($c)) { throw new \RuntimeException('分类格式无效。'); }
                $key = self::text($c['key'] ?? '', 200);
                if ($key === '' || isset($categories[$key])) { throw new \RuntimeException('分类标识重复。'); }
                $categories[$key] = array('key' => $key, 'name' => self::text($c['name'] ?? '', 200), 'slug' => self::text($c['slug'] ?? '', 200), 'description' => self::text($c['description'] ?? '', 10000), 'parent' => self::text($c['parent'] ?? '', 200));
                if ($categories[$key]['name'] === '') { throw new \RuntimeException('分类名称不能为空。'); }
            }
            foreach ($categories as $c) {
                $seen = array(); $key = $c['key'];
                while ($key !== '') {
                    if (!isset($categories[$key]) || isset($seen[$key])) { throw new \RuntimeException('分类父级缺失或存在循环。'); }
                    $seen[$key] = true; $key = $categories[$key]['parent'];
                }
            }
            if (count($data['assets']) > self::MAX_LINKS) { throw new \RuntimeException('LOGO 数量超出限制。'); }
            $assets = array();
            foreach ($data['assets'] as $key => $a) {
                if (!is_array($a) || !self::path((string) ($a['file'] ?? '')) || strpos($a['file'], 'images/') !== 0) { throw new \RuntimeException('图片清单路径无效。'); }
                $bytes = $zip->getFromName($a['file']);
                if (!is_string($bytes)) { throw new \RuntimeException('迁移包缺少图片：' . basename($a['file'])); }
                $info = self::image($bytes);
                if (!$legacy && (!isset($a['sha256']) || !hash_equals($info['sha256'], (string) $a['sha256']))) { throw new \RuntimeException('LOGO 校验值不一致。'); }
                $info['file'] = $info['sha256'] . '.' . $info['ext'];
                if (isset($a['meta'])) {
                    if (!is_array($a['meta'])) { throw new \RuntimeException('LOGO 元数据格式无效。'); }
                    $info['meta'] = array();
                    foreach (array('title', 'caption', 'description', 'alt') as $field) { $info['meta'][$field] = self::text($a['meta'][$field] ?? '', 10000); }
                }
                if (file_put_contents($dir . '/' . $info['file'], $bytes, LOCK_EX) !== strlen($bytes)) { throw new \RuntimeException('保存图片失败。'); }
                $assets[(string) $key] = $info;
            }
            $items = array(); $ids = array();
            foreach ($data['links'] as $item) {
                if (!is_array($item)) { throw new \RuntimeException('链接记录格式无效。'); }
                $item = self::link($item);
                if (isset($ids[$item['key']])) { throw new \RuntimeException('链接来源标识重复。'); }
                $ids[$item['key']] = true;
                foreach ($item['categories'] as $key) { if (!is_string($key) || !isset($categories[$key])) { throw new \RuntimeException('链接引用了不存在的分类。'); } }
                if ($item['asset'] !== '' && !isset($assets[$item['asset']])) { throw new \RuntimeException('链接引用了不存在的 LOGO。'); }
                $items[] = $item;
            }
            return array('source' => $data['source'], 'categories' => $categories, 'assets' => $assets, 'items' => $items, 'legacy' => $legacy);
        } finally { $zip->close(); }
    }

    public static function link(array $item): array
    {
        $out = array('key' => self::text((string) ($item['key'] ?? ''), 200), 'name' => self::text($item['name'] ?? '', 1020), 'url' => self::url($item['url'] ?? ''),
            'description' => self::text($item['description'] ?? '', 10000), 'notes' => self::text($item['notes'] ?? '', 65535), 'image' => self::url($item['image'] ?? '', true),
            'rss' => self::url($item['rss'] ?? '', true), 'target' => self::text($item['target'] ?? '', 30), 'visible' => self::text($item['visible'] ?? 'N', 1),
            'rel' => self::text($item['rel'] ?? '', 255), 'rating' => (int) ($item['rating'] ?? 0), 'owner' => self::text($item['owner'] ?? '', 60),
            'asset' => self::text($item['asset'] ?? '', 240), 'categories' => $item['categories'] ?? array());
        if ($out['key'] === '' || $out['name'] === '' || !in_array($out['visible'], array('Y', 'N'), true) || !in_array($out['target'], array('', '_blank', '_self', '_top', '_parent', '_none'), true) || !is_array($out['categories']) || count($out['categories']) > 100) { throw new \RuntimeException('链接的名称、状态、打开方式或分类无效。'); }
        if (in_array($out['target'], array('_self', '_none'), true)) { $out['target'] = ''; }
        if ($out['target'] === '_parent') { throw new \RuntimeException('WordPress 原生链接不支持 _parent 打开方式，请先调整源链接。'); }
        foreach (array('url', 'image', 'rss', 'description', 'name') as $field) {
            $value = in_array($field, array('url', 'image', 'rss'), true) ? esc_html($out[$field]) : $out[$field];
            $chars = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
            if ($chars > 255) { throw new \RuntimeException('链接字段超过 WordPress 原生字段长度，已停止导入，避免内容被截断。'); }
        }
        return $out;
    }

    private static function legacy(array $rows): array
    {
        $out = array('format' => 'maotk-links', 'version' => 1, 'source' => 'legacy-links', 'links' => array(), 'categories' => array(), 'assets' => array());
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['url'], $row['name'])) { throw new \RuntimeException('不是支持的旧版友链清单。'); }
            $keys = array();
            foreach ((array) ($row['cats'] ?? array()) as $name) {
                $name = self::text($name, 200); $key = hash('sha256', $name); $keys[] = $key;
                $out['categories'][$key] = array('key' => $key, 'name' => $name, 'slug' => '', 'parent' => '', 'description' => '');
            }
            $asset = '';
            if (!empty($row['file'])) { $asset = self::text($row['file'], 230); $out['assets'][$asset] = array('file' => 'images/' . $asset); }
            $out['links'][] = array('key' => hash('sha256', (string) $row['url']), 'name' => $row['name'], 'url' => $row['url'], 'description' => $row['desc'] ?? '', 'notes' => $row['notes'] ?? '',
                'target' => $row['target'] ?? '', 'visible' => $row['visible'] ?? 'N', 'rating' => $row['rating'] ?? 0, 'rss' => $row['rss'] ?? '', 'image' => $row['image_orig'] ?? '', 'asset' => $asset, 'categories' => $keys);
        }
        $out['categories'] = array_values($out['categories']);
        return $out;
    }
}
