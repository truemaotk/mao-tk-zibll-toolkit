<?php
namespace MaoTK\ZibllToolkit\ArticleNote;

use MaoTK\ZibllToolkit\ModuleManager;

defined('ABSPATH') || exit;

final class Settings
{
    public const FIELD = 'article_note';
    public const PROVIDER_FIELD = 'article_note_provider';
    public const OVERRIDE = 'maoTkNoteVisual';
    public static function types(): array { return array('info' => '说明', 'primary' => '提示', 'success' => '推荐', 'warning' => '注意', 'danger' => '风险'); }
    public static function baseline(string $type): array
    {
        $values = array('info' => array('#a54d08', '#ffb76f', 'info'), 'primary' => array('#285fa2', '#97c2ff', 'lightbulb'), 'success' => array('#1b7451', '#80dab2', 'circle-check'), 'warning' => array('#8c620e', '#efcd78', 'triangle-alert'), 'danger' => array('#aa3542', '#ffacb5', 'shield-alert'));
        $value = $values[$type] ?? $values['info'];
        return array('light' => $value[0], 'dark' => $value[1], 'iconKey' => $value[2], 'badgePosition' => 'left', 'iconPosition' => 'before', 'iconSize' => 16, 'showIcon' => true, 'titleColor' => 'type');
    }
    public static function defaults(): array { return array('schema_version' => 1, 'include_pages' => false, 'defaults' => array(), 'types' => array()); }
    public static function field_names(): array { return array_keys(self::baseline('info')); }

    public static function clean_fields($raw, bool $strict = false, bool $known_icons = false): ?array
    {
        if (!is_array($raw)) { return $strict ? null : array(); }
        $result = array(); $icons = Icons::all();
        foreach ($raw as $key => $value) {
            $valid = false;
            if (in_array($key, array('light', 'dark'), true)) { $valid = is_string($value) && (bool) preg_match('/^#[0-9a-fA-F]{6}$/D', $value); }
            elseif ($key === 'iconSize') { $valid = is_int($value) && $value >= 12 && $value <= 28; }
            elseif ($key === 'showIcon') { $valid = is_bool($value); }
            elseif ($key === 'iconKey') { $valid = is_string($value) && preg_match('/^[a-z][a-z0-9-]{0,47}$/D', $value) && (!$known_icons || isset($icons[$value])); }
            elseif ($key === 'badgePosition') { $valid = is_string($value) && in_array($value, array('left', 'center', 'right'), true); }
            elseif ($key === 'iconPosition') { $valid = is_string($value) && in_array($value, array('before', 'after'), true); }
            elseif ($key === 'titleColor') { $valid = is_string($value) && in_array($value, array('type', 'neutral'), true); }
            if (!$valid) { if ($strict) { return null; } continue; }
            $result[$key] = $value;
        }
        return $result;
    }

    public function read(): array
    {
        $manager = new ModuleManager(); $raw = get_option(ModuleManager::OPTION_KEY, ModuleManager::defaults());
        // Own the block when no provider preference exists; provider() still defers
        // to a registered legacy provider and refuses unknown/conflicting blocks.
        $provider = is_array($raw) && array_key_exists(self::PROVIDER_FIELD, $raw)
            ? ($raw[self::PROVIDER_FIELD] === 'toolkit' ? 'toolkit' : 'coexist') : 'toolkit';
        $value = is_array($raw) && array_key_exists(self::FIELD, $raw) ? self::validate($raw[self::FIELD], false) : self::defaults();
        return array('valid' => $manager->storage_is_valid() && $value !== null, 'value' => $value ?? self::defaults(), 'provider' => $provider);
    }

    public static function validate($value, bool $known_icons = true): ?array
    {
        if (!is_array($value) || array_diff(array_keys($value), array('schema_version', 'include_pages', 'defaults', 'types')) || ($value['schema_version'] ?? null) !== 1 || !isset($value['include_pages']) || !is_bool($value['include_pages']) || !isset($value['types']) || !is_array($value['types']) || array_diff(array_keys($value['types']), array_keys(self::types()))) { return null; }
        $defaults = self::clean_fields($value['defaults'] ?? null, true, $known_icons); if ($defaults === null) { return null; }
        $types = array();
        foreach ($value['types'] as $type => $fields) { $clean = self::clean_fields($fields, true, $known_icons); if ($clean === null) { return null; } $types[$type] = $clean; }
        return array('schema_version' => 1, 'include_pages' => $value['include_pages'], 'defaults' => $defaults, 'types' => $types);
    }

    public static function posted($raw): ?array
    {
        if (!is_array($raw) || array_diff(array_keys($raw), array('include_pages', 'defaults', 'types')) || !isset($raw['include_pages']) || !in_array($raw['include_pages'], array('0', '1'), true) || !isset($raw['defaults'], $raw['types']) || !is_array($raw['defaults']) || !is_array($raw['types']) || array_diff(array_keys($raw['types']), array_keys(self::types()))) { return null; }
        $result = self::defaults(); $result['include_pages'] = $raw['include_pages'] === '1';
        foreach (array_merge(array('defaults' => $raw['defaults']), $raw['types']) as $group => $fields) {
            if ($group !== 'defaults' && !isset(self::types()[$group])) { return null; }
            if (!is_array($fields)) { return null; } $converted = array();
            foreach ($fields as $key => $value) {
                if (!in_array($key, self::field_names(), true) || !is_string($value)) { return null; }
                if ($value === '') { continue; }
                if ($key === 'iconSize') { if (!preg_match('/^(?:1[2-9]|2[0-8])$/D', $value)) { return null; } $value = (int) $value; }
                elseif ($key === 'showIcon') { if (!in_array($value, array('true', 'false'), true)) { return null; } $value = $value === 'true'; }
                $converted[$key] = $value;
            }
            if ($group === 'defaults') { $result['defaults'] = $converted; } else { $result['types'][$group] = $converted; }
        }
        return self::validate($result);
    }

    public function save(ModuleManager $manager, array $value, string $provider): bool
    {
        $value = self::validate($value);
        return $value !== null && in_array($provider, array('coexist', 'toolkit'), true) && $manager->set_extension_settings(array(self::FIELD => $value, self::PROVIDER_FIELD => $provider));
    }

    public static function resolve(string $type, array $settings, $override = array()): array
    {
        $type = isset(self::types()[$type]) ? $type : 'info';
        $value = array_replace(self::baseline($type), self::clean_fields($settings['defaults'] ?? array()), self::clean_fields($settings['types'][$type] ?? array()), self::clean_fields($override));
        $icons = Icons::all(); if (!isset($icons[$value['iconKey']])) { $value['iconKey'] = self::baseline($type)['iconKey']; }
        return $value;
    }

    public static function payload(array $settings): array
    {
        $baselines = array(); foreach (self::types() as $type => $label) { $baselines[$type] = self::baseline($type); }
        return array('settings' => $settings, 'baselines' => $baselines, 'icons' => Icons::all(), 'types' => self::types());
    }
}
