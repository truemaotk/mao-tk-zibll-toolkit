<?php
namespace MaoTK\ZibllToolkit\NotFound;
defined('ABSPATH') || exit;

final class Settings
{
    public const MODULE = 'site-not-found';
    public const OPTION = 'mao_tk_toolkit_not_found';
    public static function defaults(): array
    {
        return array('schema_version' => 1, 'revision' => 0, 'title' => '页面走丢了', 'description' => '你访问的页面可能已移动或不存在，可以返回首页，或试试站内搜索。', 'charity_enabled' => true, 'charity_title' => '公益寻亲信息', 'provider' => 'xunjinlu', 'show_photo' => true, 'show_search' => true);
    }
    public static function normalize(array $raw): array
    {
        $value = self::defaults();
        foreach ($value as $key => $default) {
            if (!array_key_exists($key, $raw)) { continue; }
            $v = $raw[$key];
            if ($key === 'schema_version') { if ($v !== 1) { throw new \InvalidArgumentException('配置版本无效。'); } }
            elseif ($key === 'revision') { if (!is_int($v) || $v < 0) { throw new \InvalidArgumentException('配置修订号无效。'); } $value[$key] = $v; }
            elseif (is_bool($default)) { if (!is_bool($v)) { throw new \InvalidArgumentException('开关格式无效。'); } $value[$key] = $v; }
            elseif ($key === 'provider') { if (!is_string($v) || !in_array($v, array('xunjinlu','uctb'), true)) { throw new \InvalidArgumentException('请选择已核实的公益接口。'); } $value[$key] = $v; }
            else { if (!is_string($v) || strlen($v) > ($key === 'description' ? 900 : 180)) { throw new \InvalidArgumentException('文字过长或格式无效。'); } $value[$key] = trim(sanitize_text_field($v)); }
        }
        if ($value['title'] === '' || $value['charity_title'] === '') { throw new \InvalidArgumentException('请填写页面和公益区域标题。'); }
        return $value;
    }
    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        if ($raw === null) { return array('valid' => true, 'value' => self::defaults()); }
        try { if (!is_array($raw) || ($raw['schema_version'] ?? null) !== 1) { throw new \InvalidArgumentException(); } return array('valid' => true, 'value' => self::normalize($raw)); }
        catch (\InvalidArgumentException $e) { return array('valid' => false, 'value' => self::defaults()); }
    }
    public function save(array $raw, int $revision): bool
    {
        $state = $this->read(); if (!$state['valid'] || $state['value']['revision'] !== $revision) { return false; }
        $value = self::normalize($raw); $value['revision'] = $revision + 1; update_option(self::OPTION, $value, false);
        return get_option(self::OPTION) === $value;
    }
}
