<?php
namespace MaoTK\ZibllToolkit\ProfilePresets;

defined('ABSPATH') || exit;

final class Catalog
{
    private static $builtin;
    public const PAGE_SIZE = 24;

    public static function builtin(string $kind): array
    {
        if (self::$builtin === null) {
            $json = file_get_contents(MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/profile-presets/catalog.json');
            $rows = is_string($json) ? json_decode($json, true) : array();
            self::$builtin = array();
            if (is_array($rows)) { foreach ($rows as $row) {
                if (!is_array($row) || !isset($row['id'], $row['file'], $row['kind'], $row['width'], $row['height']) || !is_string($row['file']) || !preg_match('#^(avatars/avatar|covers/cover)-[0-9]{3}\.(png|jpg|jpeg|gif|webp)$#D', $row['file'])) { continue; }
                if (!is_file(MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/profile-presets/' . $row['file'])) { continue; }
                $row['url'] = MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/profile-presets/' . $row['file'];
                $row['thumb'] = $row['url']; $row['name'] = ($row['kind'] === 'avatar' ? '预设头像 ' : '预设背景 ') . substr($row['id'], -3);
                self::$builtin[] = $row;
            } }
        }
        return array_values(array_filter(self::$builtin, static function ($row) use ($kind) { return $row['kind'] === $kind; }));
    }

    public static function media(int $id): ?array
    {
        if (get_post_type($id) !== 'attachment' || get_post_status($id) === 'trash' || !in_array(get_post_mime_type($id), array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) { return null; }
        $file = get_attached_file($id); $uploads = wp_get_upload_dir();
        $real = is_string($file) ? realpath($file) : false; $base = realpath($uploads['basedir']);
        if (!$real || !$base || strpos(wp_normalize_path($real), trailingslashit(wp_normalize_path($base))) !== 0) { return null; }
        $image = wp_get_attachment_image_src($id, 'full'); $thumb = wp_get_attachment_image_src($id, 'thumbnail');
        if (!$image || (int) $image[1] < 1 || (int) $image[2] < 1) { return null; }
        return array('id' => 'media-' . $id, 'url' => $image[0], 'thumb' => $thumb ? $thumb[0] : $image[0], 'name' => get_the_title($id) ?: '自定义图片 ' . $id, 'width' => (int) $image[1], 'height' => (int) $image[2]);
    }

    public static function all(string $kind, array $settings): array
    {
        if (!in_array($kind, array('avatar', 'cover'), true)) { return array(); }
        $rows = $settings[$kind === 'avatar' ? 'builtin_avatars' : 'builtin_covers'] ? self::builtin($kind) : array();
        foreach ($settings[$kind . '_media'] as $id) { $row = self::media($id); if ($row) { $row['kind'] = $kind; $rows[] = $row; } }
        return $rows;
    }

    public static function page(string $kind, array $settings, int $page): array
    {
        $rows = self::all($kind, $settings); $total = count($rows); $pages = max(1, (int) ceil($total / self::PAGE_SIZE)); $page = min(max(1, $page), $pages);
        return array('items' => array_slice($rows, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE), 'total' => $total, 'page' => $page, 'pages' => $pages);
    }

    public static function find(string $kind, string $id, array $settings): ?array
    {
        foreach (self::all($kind, $settings) as $row) { if ($row['id'] === $id) { return $row; } }
        return null;
    }
}
