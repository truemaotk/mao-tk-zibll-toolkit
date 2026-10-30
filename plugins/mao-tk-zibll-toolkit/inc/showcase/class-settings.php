<?php
namespace MaoTK\ZibllToolkit\Showcase;

defined('ABSPATH') || exit;

/** A central case library. Pages store only a category ID, never a copied library. */
final class Settings
{
    public const OPTION = 'mao_tk_zibll_toolkit_showcase';
    public const META = '_mao_tk_toolkit_showcase_category';
    public const TEMPLATE = 'mao-tk-toolkit-showcase.php';
    public const MODULE = 'page-showcase';

    public static function defaults(): array
    {
        return array('schemaVersion' => 1, 'categories' => array(), 'cards' => array(), 'settings' => array(
            'title' => '独立站案例', 'description' => '从品牌商城到内容站，看看不同页面的呈现方式。',
            'showHeader' => true, 'showDescription' => true, 'columns' => 3, 'themeMode' => 'auto',
            'widthMode' => 'theme', 'maxWidth' => 1200, 'cardRadius' => 12, 'gap' => 23, 'cardTitleSize' => 17,
            'sectionPaddingTop' => 10, 'sectionPaddingBottom' => 10,
            'imageHeight' => 270, 'imageHeightMobile' => 255, 'scrollEnabled' => true, 'previewSpeed' => 80,
            'iconSize' => 17, 'buttonOneIcon' => 'arrow-up-right', 'buttonTwoIcon' => 'message-circle', 'syncButtonIcons' => false,
            'buttonOneCustomIcon' => '', 'buttonOneIconUrl' => '', 'buttonOneIconName' => '', 'buttonOneIconId' => 0,
            'buttonTwoCustomIcon' => '', 'buttonTwoIconUrl' => '', 'buttonTwoIconName' => '', 'buttonTwoIconId' => 0,
            'primaryBg' => '#ff890a', 'primaryHover' => '#e97900', 'cardBgLight' => '#ffffff', 'cardBgDark' => '#24282e',
            'borderLight' => '#e6e8ed', 'borderDark' => '#373d46', 'titleLight' => '#252a32', 'titleDark' => '#f0f1f4',
            'showBrowserBar' => true, 'showBrowserDots' => true, 'showBrowserAddress' => true, 'showBrowserTools' => true, 'showHint' => true,
        ));
    }

    public static function card_defaults(): array
    {
        return array('id' => '', 'name' => '', 'category' => '', 'categoryIds' => array(), 'enabled' => true,
            'description' => '', 'siteUrl' => '', 'imageId' => 0, 'imageUrl' => '', 'imageAlt' => '', 'addressText' => '',
            'buttonOneText' => '查看网站', 'buttonOneUrl' => '', 'buttonTwoText' => '咨询方案', 'buttonTwoUrl' => '',
            'showButtonOne' => true, 'showButtonTwo' => true, 'newTab' => true, 'nofollow' => false, 'buttonTwoNofollow' => true,
            'scrollEnabled' => true, 'imageLoading' => 'lazy');
    }

    public static function id($value): bool { return is_string($value) && (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/D', $value); }
    public static function url($value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 2048) { return ''; }
        $parts = wp_parse_url($value);
        if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true) || isset($parts['user']) || isset($parts['pass'])) { return ''; }
        return esc_url_raw($value, array('http', 'https'));
    }

    public static function normalize($input)
    {
        if (!is_array($input) || ($input['schemaVersion'] ?? null) !== 1 || !isset($input['settings'], $input['cards'], $input['categories']) || !is_array($input['settings']) || !is_array($input['cards']) || !is_array($input['categories']) || count($input['cards']) > 300 || count($input['categories']) > 100) { return new \WP_Error('showcase_invalid', '案例库格式不正确；最多支持300个案例、100个分类。', array('status' => 400)); }
        $out = self::defaults();
        $ranges = array('columns' => array(2, 3), 'maxWidth' => array(720, 1440), 'cardRadius' => array(0, 28), 'gap' => array(12, 40), 'cardTitleSize' => array(14, 24), 'sectionPaddingTop' => array(0, 200), 'sectionPaddingBottom' => array(0, 200), 'imageHeight' => array(160, 500), 'imageHeightMobile' => array(160, 500), 'previewSpeed' => array(20, 240), 'iconSize' => array(13, 24), 'buttonOneIconId' => array(0, PHP_INT_MAX), 'buttonTwoIconId' => array(0, PHP_INT_MAX));
        $enums = array('themeMode' => array('auto', 'light', 'dark'), 'widthMode' => array('theme', 'custom'));
        foreach ($out['settings'] as $key => $default) {
            if (!array_key_exists($key, $input['settings'])) { continue; }
            $v = $input['settings'][$key];
            if (is_bool($default)) { if (!is_bool($v)) { return self::invalid($key); } $out['settings'][$key] = $v; }
            elseif (isset($ranges[$key])) { if (!is_int($v) || $v < $ranges[$key][0] || $v > $ranges[$key][1]) { return self::invalid($key); } $out['settings'][$key] = $v; }
            elseif (isset($enums[$key])) { if (!in_array($v, $enums[$key], true)) { return self::invalid($key); } $out['settings'][$key] = $v; }
            elseif (!is_string($v)) { return self::invalid($key); }
            elseif (preg_match('/^(primary|cardBg|border|title)(Bg|Hover|Light|Dark)?$/', $key) && $default[0] === '#') { $color = sanitize_hex_color($v); if (!$color) { return self::invalid($key); } $out['settings'][$key] = $color; }
            elseif (substr($key, -10) === 'CustomIcon' || substr($key, -7) === 'IconUrl') { if ($v !== '' && !self::url($v)) { return self::invalid($key); } $out['settings'][$key] = self::url($v); }
            elseif ($key === 'buttonOneIcon' || $key === 'buttonTwoIcon') { if (!preg_match('/^[a-z0-9-]{1,40}$/D', $v) || !is_file(MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/showcase/icons/' . $v . '.svg')) { return self::invalid($key); } $out['settings'][$key] = $v; }
            else { if (strlen($v) > ($key === 'description' ? 2000 : 300)) { return self::invalid($key); } $out['settings'][$key] = sanitize_textarea_field($v); }
        }
        $ids = array();
        foreach ($input['categories'] as $category) {
            if (!is_array($category) || !self::id($category['id'] ?? null) || !is_string($category['name'] ?? null) || trim($category['name']) === '' || strlen($category['name']) > 240 || isset($ids[$category['id']])) { return self::invalid('分类'); }
            $name = sanitize_text_field($category['name']); if ($name === '') { return self::invalid('分类名称'); }
            $ids[$category['id']] = true; $out['categories'][] = array('id' => $category['id'], 'name' => $name);
        }
        $card_ids = array();
        foreach ($input['cards'] as $card) {
            if (!is_array($card) || !self::id($card['id'] ?? null) || isset($card_ids[$card['id']])) { return self::invalid('案例编号'); }
            $clean = self::card_defaults(); $card_ids[$card['id']] = true;
            foreach ($clean as $key => $default) {
                if (!array_key_exists($key, $card)) { continue; }
                $v = $card[$key];
                if (is_bool($default)) { if (!is_bool($v)) { return self::invalid($key); } $clean[$key] = $v; }
                elseif ($key === 'categoryIds') { if (!is_array($v) || count($v) > 100 || count(array_unique($v, SORT_REGULAR)) !== count($v)) { return self::invalid($key); } foreach ($v as $id) { if (!is_string($id) || !isset($ids[$id])) { return self::invalid('案例分类'); } } $clean[$key] = array_values($v); }
                elseif ($key === 'imageId') { if (!is_int($v) || $v < 0) { return self::invalid($key); } $clean[$key] = $v; }
                elseif (!is_string($v) || strlen($v) > (in_array($key, array('description','siteUrl','imageUrl','buttonOneUrl','buttonTwoUrl'), true) ? 2048 : 500)) { return self::invalid($key); }
                elseif (substr($key, -3) === 'Url') { if ($v !== '' && !self::url($v)) { return self::invalid($key); } $clean[$key] = self::url($v); }
                elseif ($key === 'imageLoading') { if (!in_array($v, array('lazy', 'eager'), true)) { return self::invalid($key); } $clean[$key] = $v; }
                else { $clean[$key] = $key === 'description' ? sanitize_textarea_field($v) : sanitize_text_field($v); }
            }
            if ($clean['name'] === '') { return self::invalid('案例名称'); }
            $out['cards'][] = $clean;
        }
        if (strlen(wp_json_encode($out)) > 1048576) { return self::invalid('案例库过大，请减少文字或图片链接长度'); }
        return $out;
    }

    private static function invalid(string $field): \WP_Error { return new \WP_Error('showcase_invalid', '请检查“' . $field . '”的内容；原设置未改变。', array('status' => 400)); }

    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        if ($raw === null) { return array('valid' => true, 'value' => self::defaults(), 'revision' => 'new'); }
        $value = self::normalize($raw);
        $valid = !is_wp_error($value) && is_string($raw['revision'] ?? null) && (bool) preg_match('/^[a-f0-9-]{36}$/D', $raw['revision']);
        return array('valid' => $valid, 'value' => $valid ? $value : self::defaults(), 'revision' => $valid ? $raw['revision'] : 'invalid');
    }

    /** Compare-and-swap prevents concurrent editors from overwriting a newer library. */
    public function save($input, string $revision)
    {
        $value = self::normalize($input); if (is_wp_error($value)) { return $value; }
        $current = $this->read();
        if (!$current['valid']) { return new \WP_Error('showcase_storage', '案例库格式异常，已暂停写入，请先恢复备份。', array('status' => 409)); }
        if (!hash_equals($current['revision'], $revision)) { return new \WP_Error('showcase_conflict', '另一个窗口已经保存过案例库。请先导出当前修改，再刷新后合并。', array('status' => 409)); }
        if ($current['value'] === $value) { return $current; }
        $raw = get_option(self::OPTION, null); $next = $value; $next['revision'] = wp_generate_uuid4();
        if (($raw === null ? 'new' : ($raw['revision'] ?? 'invalid')) !== $revision) { return new \WP_Error('showcase_conflict', '案例库已更新，请导出当前修改后刷新核对。', array('status' => 409)); }
        if ($raw === null) { $success = add_option(self::OPTION, $next, '', false); }
        else {
            global $wpdb;
            $success = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize($next), self::OPTION, maybe_serialize($raw))) === 1;
            wp_cache_delete(self::OPTION, 'options'); wp_cache_delete('alloptions', 'options');
        }
        if (!$success) { return new \WP_Error('showcase_conflict', '保存未完成，数据可能已被其他窗口更新。当前修改仍保留，请导出后刷新核对。', array('status' => 409)); }
        return $this->read();
    }

    public static function cards(array $value, string $category = ''): array
    {
        return array_values(array_filter($value['cards'], static function (array $card) use ($category): bool { return $card['enabled'] && ($category === '' || in_array($category, $card['categoryIds'], true)); }));
    }
}
