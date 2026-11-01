<?php
namespace MaoTK\ZibllToolkit\ProfilePresets;

defined('ABSPATH') || exit;

final class Settings
{
    public const MODULE = 'user-profile-presets';
    public const OPTION = 'mao_tk_toolkit_profile_presets';

    public static function defaults(): array
    {
        return array('schema_version' => 1, 'revision' => 0, 'avatar_enabled' => true, 'cover_enabled' => true, 'random_on_register' => false, 'builtin_avatars' => true, 'builtin_covers' => true, 'avatar_media' => array(), 'cover_media' => array(), 'avatar_label' => '选择预设头像', 'cover_label' => '选择预设背景', 'modal_title' => '头像与背景');
    }

    public static function normalize(array $raw): array
    {
        $v = self::defaults();
        foreach ($v as $key => $default) {
            if (!array_key_exists($key, $raw)) { continue; }
            $value = $raw[$key];
            if ($key === 'schema_version') { if ($value !== 1) { throw new \InvalidArgumentException('预设配置版本无效。'); } }
            elseif ($key === 'revision') { if (!is_int($value) || $value < 0) { throw new \InvalidArgumentException('预设配置版本号无效。'); } $v[$key] = $value; }
            elseif (is_bool($default)) { if (!is_bool($value)) { throw new \InvalidArgumentException('预设开关格式无效。'); } $v[$key] = $value; }
            elseif (is_array($default)) {
                if (!is_array($value) || count($value) > 200) { throw new \InvalidArgumentException('每类最多选择 200 张自定义图片。'); }
                foreach ($value as $id) { if (!is_int($id) || $id < 1) { throw new \InvalidArgumentException('媒体图片编号无效。'); } }
                $v[$key] = array_values(array_unique($value));
            } else {
                if (!is_string($value) || strlen($value) > 180) { throw new \InvalidArgumentException('入口文字应在 60 个汉字以内。'); }
                $v[$key] = trim(sanitize_text_field($value));
                if ($v[$key] === '') { throw new \InvalidArgumentException('请填写预设入口与窗口标题。'); }
            }
        }
        return $v;
    }

    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        if ($raw === null) { return array('valid' => true, 'exists' => false, 'value' => self::defaults()); }
        try {
            if (!is_array($raw) || ($raw['schema_version'] ?? null) !== 1) { throw new \InvalidArgumentException(); }
            return array('valid' => true, 'exists' => true, 'value' => self::normalize($raw));
        } catch (\InvalidArgumentException $error) { return array('valid' => false, 'exists' => true, 'value' => self::defaults()); }
    }

    public function save(array $raw, int $expected_revision): bool
    {
        $state = $this->read();
        if (!$state['valid'] || $state['value']['revision'] !== $expected_revision) { return false; }
        $value = self::normalize($raw);
        foreach (array('avatar_media', 'cover_media') as $key) {
            foreach ($value[$key] as $id) { if (!Catalog::media($id)) { throw new \InvalidArgumentException('所选媒体必须是可用的本地 JPG / PNG / WebP / GIF 图片。'); } }
        }
        $value['revision'] = $expected_revision + 1;
        update_option(self::OPTION, $value, false);
        return get_option(self::OPTION) === $value;
    }
}
