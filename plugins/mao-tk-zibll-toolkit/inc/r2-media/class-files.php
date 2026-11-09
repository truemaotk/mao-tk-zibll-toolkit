<?php
namespace MaoTK\ZibllToolkit\R2Media;

defined('ABSPATH') || exit;

final class Files
{
    public const STATE = '_mao_tk_r2_media_v1';
    public const LIMIT = 16 * 1024 * 1024;
    public const TYPES = array('image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp');

    public static function entries(int $id): array
    {
        if (get_post_type($id) !== 'attachment' || !in_array(get_post_mime_type($id), self::TYPES, true)) {
            return array();
        }
        $full = get_post_meta($id, '_wp_attached_file', true);
        if (!is_string($full) || !self::valid_path($full)) {
            return array();
        }
        $meta = wp_get_attachment_metadata($id);
        $dir = dirname($full);
        $dir = $dir === '.' ? '' : $dir . '/';
        $files = array($full => empty($meta['original_image']));
        foreach ($meta['sizes'] ?? array() as $size) {
            $name = $size['file'] ?? '';
            if (is_string($name) && basename($name) === $name && self::valid_path($dir . $name)) {
                $files[$dir . $name] = $files[$dir . $name] ?? false;
            }
        }
        $original = $meta['original_image'] ?? '';
        if (
            is_string($original) &&
            basename($original) === $original &&
            self::valid_path($dir . $original) &&
            $original !== ''
        ) {
            $files[$dir . $original] = true;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    public static function relative_url(string $url): string
    {
        $uploads = wp_get_upload_dir();
        $base = wp_parse_url($uploads['baseurl']);
        $parts = wp_parse_url($url);
        if (
            !is_array($base) ||
            !is_array($parts) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            !in_array(strtolower($parts['scheme'] ?? 'https'), array('http', 'https'), true) ||
            (isset($parts['host']) && strcasecmp($parts['host'], $base['host'] ?? '') !== 0) ||
            (isset($parts['host']) && ($parts['port'] ?? null) !== ($base['port'] ?? null))
        ) {
            return '';
        }
        $prefix = rtrim($base['path'] ?? '', '/') . '/';
        $path = $parts['path'] ?? '';
        if ($prefix === '/' || strpos($path, $prefix) !== 0) {
            return '';
        }
        $relative = rawurldecode(substr($path, strlen($prefix)));
        return self::valid_path($relative) ? $relative : '';
    }

    private static function valid_path(string $relative): bool
    {
        return $relative !== '' &&
            strlen($relative) <= 512 &&
            $relative[0] !== '/' &&
            substr($relative, -1) !== '/' &&
            !preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\x00-\\x1f\\x7f:\\\\]|//~', $relative);
    }

    public static function disk_path(string $relative): string
    {
        if (!self::valid_path($relative)) {
            return '';
        }
        $uploads = wp_get_upload_dir();
        $base = realpath($uploads['basedir']);
        if ($base === false) {
            return '';
        }
        $path = wp_normalize_path($base) . '/' . $relative;
        $parent = realpath(dirname($path));
        if ($parent === false || wp_normalize_path($parent) !== dirname($path) || is_link($path)) {
            return '';
        }
        return $path;
    }

    public static function state(int $id, string $destination): array
    {
        $state = get_post_meta($id, self::STATE, true);
        return is_array($state) && ($state['destination'] ?? '') === $destination && is_array($state['files'] ?? null)
            ? $state['files']
            : array();
    }

    public static function asset_id(int $id, string $path, string $hash): string
    {
        return sprintf('%016x', $id) . substr(hash('sha256', home_url('/') . "\n" . $path . "\n" . $hash), 0, 48);
    }

    public static function eligible(int $id): bool
    {
        $attachment = get_post($id);
        if (
            !$attachment ||
            $attachment->post_type !== 'attachment' ||
            $attachment->post_status !== 'inherit' ||
            $attachment->post_password !== ''
        ) {
            return false;
        }
        if (!$attachment->post_parent) {
            return true;
        }
        $parent = get_post($attachment->post_parent);
        return $parent &&
            $parent->post_status === 'publish' &&
            $parent->post_password === '' &&
            is_post_publicly_viewable($parent) &&
            (!function_exists('zib_get_post_allow_view_data') ||
                !empty(zib_get_post_allow_view_data($parent)['allow']));
    }

    public static function type(string $body): string
    {
        $image = @getimagesizefromstring($body);
        $type = $image['mime'] ?? '';
        return in_array($type, self::TYPES, true) ? $type : '';
    }
}
