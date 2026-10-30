<?php
namespace MaoTK\ZibllToolkit\ArticleNote;

defined('ABSPATH') || exit;

final class Icons
{
    private static $cache;
    private static $rejected = array();

    public static function all(): array
    {
        if (self::$cache !== null) { return self::$cache; }
        $labels = array('info' => '说明圆圈', 'lightbulb' => '提示灯泡', 'circle-check' => '推荐勾选', 'triangle-alert' => '注意三角', 'shield-alert' => '风险盾牌', 'bell' => '通知铃铛', 'circle-help' => '帮助问号', 'bookmark' => '书签');
        $defaults = array();
        foreach ($labels as $key => $label) { $defaults[$key] = array('label' => $label, 'file' => 'assets/article-ui/note/assets/icons/' . $key . '.svg', 'viewBox' => '0 0 24 24'); }
        /** Add new stable keys only. Files must be reviewed SVGs inside this plugin. */
        try { $extra = apply_filters('mao_tk_zibll_toolkit_note_icons', array()); } catch (\Throwable $error) { $extra = array(); self::$rejected[] = 'filter-error'; }
        if (!is_array($extra)) { $extra = array(); self::$rejected[] = 'invalid-registry'; }
        $all = $defaults;
        foreach ($extra as $key => $entry) {
            if (!is_string($key) || isset($defaults[$key])) { self::$rejected[] = is_string($key) ? $key : 'invalid-key'; continue; }
            $all[$key] = $entry;
        }
        self::$cache = array();
        foreach ($all as $key => $entry) {
            if (!preg_match('/^[a-z][a-z0-9-]{0,47}$/D', (string) $key) || !is_array($entry) || !isset($entry['label'], $entry['file'], $entry['viewBox']) || !is_string($entry['label']) || !is_string($entry['file']) || $entry['viewBox'] !== '0 0 24 24') { self::$rejected[] = (string) $key; continue; }
            $file = $entry['file'];
            if (!preg_match('~^assets/[a-zA-Z0-9_./-]+\.svg$~D', $file) || strpos($file, '..') !== false) { self::$rejected[] = $key; continue; }
            $base = realpath(MAO_TK_ZIBLL_TOOLKIT_DIR); $path = realpath(MAO_TK_ZIBLL_TOOLKIT_DIR . $file);
            if (!$base || !$path || strpos(strtolower($path), strtolower($base . DIRECTORY_SEPARATOR)) !== 0 || !is_file($path) || filesize($path) > 32768 || !self::safe_svg((string) file_get_contents($path))) { self::$rejected[] = $key; continue; }
            self::$cache[$key] = array('label' => sanitize_text_field($entry['label']), 'file' => $file, 'url' => MAO_TK_ZIBLL_TOOLKIT_URL . $file, 'viewBox' => '0 0 24 24');
        }
        return self::$cache;
    }

    public static function safe_svg(string $svg): bool
    {
        if (!preg_match('~<svg\b[^>]*\bviewBox=["\x27]0 0 24 24["\x27]~', $svg) || preg_match('~<!DOCTYPE|<!ENTITY|<\?|\bon[a-z]+\s*=|\b(?:href|style)\s*=|url\s*\(|javascript:~i', $svg)) { return false; }
        preg_match_all('~</?\s*([a-zA-Z][a-zA-Z0-9:-]*)\b~', preg_replace('~<!--[\s\S]*?-->~', '', $svg), $tags);
        return !array_diff($tags[1], array('svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'title', 'desc'));
    }

    public static function rejected(): array { self::all(); return self::$rejected; }
}
