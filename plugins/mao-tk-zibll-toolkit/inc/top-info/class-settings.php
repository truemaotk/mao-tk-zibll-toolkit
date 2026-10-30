<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'mao_tk_zibll_toolkit_top_info';
    public const LEGACY_OPTION = 'moxing_top_info';

    public static function defaults(): array
    {
        return array_merge(array('schema_version' => 1, 'bg_color' => '#425aef', 'text_color' => '#b3bdf9', 'icon_color' => '#b3bdf9', 'bg_opacity' => 100, 'text_opacity' => 100, 'icon_opacity' => 100, 'properties' => array('原创', '转载')), self::spacing_defaults());
    }

    public static function spacing_defaults(): array
    {
        return array('padding_top_desktop' => 24, 'padding_bottom_desktop' => 12, 'padding_top_tablet' => 24, 'padding_bottom_tablet' => 24, 'padding_top_mobile' => 16, 'padding_bottom_mobile' => 16);
    }

    /** Read compatibility is intentionally side-effect free, including in previews. */
    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        if ($raw !== null) {
            $value = self::validate($raw);
            return array('valid' => $value !== null, 'source' => 'toolkit', 'value' => $value ?? self::defaults());
        }
        $legacy = get_option(self::LEGACY_OPTION, null);
        $value = self::defaults();
        if (is_array($legacy)) {
            foreach (array('bg_color', 'text_color') as $key) {
                $color = $legacy[$key] ?? null;
                $color = is_array($color) ? ($color[$key] ?? null) : $color;
                if (is_string($color) && sanitize_hex_color($color)) {
                    $value[$key] = sanitize_hex_color($color);
                }
            }
            if (isset($legacy['footer_tabbar']) && is_array($legacy['footer_tabbar'])) {
                $labels = array();
                foreach ($legacy['footer_tabbar'] as $property) {
                    if (is_array($property) && isset($property['text']) && is_string($property['text'])) {
                        $text = trim(sanitize_text_field($property['text']));
                        if ($text !== '') { $labels[] = $text; }
                    }
                }
                // Do not silently truncate or drop old labels during compatibility reads.
                $value['properties'] = array_values(array_unique($labels));
            }
        }
        $value['icon_color'] = $value['text_color'];
        $value['icon_opacity'] = $value['text_opacity'];
        return array('valid' => true, 'source' => is_array($legacy) ? 'legacy' : 'defaults', 'value' => $value);
    }

    public static function validate($raw): ?array
    {
        if (!is_array($raw) || ($raw['schema_version'] ?? null) !== 1) { return null; }
        if (!array_key_exists('icon_color', $raw)) { $raw['icon_color'] = $raw['text_color'] ?? null; }
        foreach (array('bg_color', 'text_color', 'icon_color') as $key) {
            if (!isset($raw[$key]) || !is_string($raw[$key]) || !sanitize_hex_color($raw[$key])) { return null; }
        }
        $opacities = array();
        foreach (array('bg_opacity', 'text_opacity', 'icon_opacity') as $key) {
            $opacity = array_key_exists($key, $raw) ? $raw[$key] : ($key === 'icon_opacity' ? $opacities['text_opacity'] : 100);
            if (!(is_int($opacity) || is_string($opacity)) || !preg_match('/^(?:0|[1-9][0-9]?|100)$/D', (string) $opacity)) { return null; }
            $opacities[$key] = (int) $opacity;
        }
        $spacing = array();
        foreach (self::spacing_defaults() as $key => $default) {
            $value = array_key_exists($key, $raw) ? $raw[$key] : $default;
            if (!(is_int($value) || is_string($value)) || !preg_match('/^(?:0|[1-9][0-9]{0,2})$/D', (string) $value) || (int) $value > 200) { return null; }
            $spacing[$key] = (int) $value;
        }
        if (!isset($raw['properties']) || !is_array($raw['properties']) || count($raw['properties']) > 100) { return null; }
        $labels = array();
        foreach ($raw['properties'] as $label) {
            if (!is_string($label) || strlen($label) > 600) { return null; }
            $label = trim(sanitize_text_field($label));
            if ($label !== '') { $labels[] = $label; }
        }
        return array_merge(array('schema_version' => 1, 'bg_color' => sanitize_hex_color($raw['bg_color']), 'text_color' => sanitize_hex_color($raw['text_color']), 'icon_color' => sanitize_hex_color($raw['icon_color']), 'properties' => array_values(array_unique($labels))), $opacities, $spacing);
    }

    public static function from_form($raw): ?array
    {
        if (!is_array($raw) || !isset($raw['properties']) || !is_string($raw['properties']) || strlen($raw['properties']) > 60000) { return null; }
        $raw['schema_version'] = 1;
        $raw['properties'] = preg_split('/\r\n|\r|\n/', $raw['properties']);
        return self::validate($raw);
    }

    public function save(array $value): bool
    {
        $next = self::validate($value);
        if ($next === null || !$this->read()['valid']) { return false; }
        $current = get_option(self::OPTION, null);
        if ($current === null) {
            add_option(self::OPTION, $next, '', false);
        } elseif ($current !== $next) {
            update_option(self::OPTION, $next, false);
        }
        return get_option(self::OPTION, null) === $next;
    }

    public static function hex6(string $color): string
    {
        if (!sanitize_hex_color($color)) { return '#000000'; }
        if (strlen($color) === 4) { return '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3]; }
        return $color;
    }

    public static function text_color(array $settings): string
    {
        return self::color_with_opacity($settings['text_color'], $settings['text_opacity'] ?? 100);
    }

    public static function icon_color(array $settings): string
    {
        return self::color_with_opacity($settings['icon_color'] ?? $settings['text_color'], $settings['icon_opacity'] ?? ($settings['text_opacity'] ?? 100));
    }

    private static function color_with_opacity(string $color, int $opacity): string
    {
        if ($opacity === 100) { return $color; }
        $hex = self::hex6($color);
        return sprintf('rgba(%d,%d,%d,%.2F)', hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $opacity / 100);
    }
}
