<?php
namespace MaoTK\ZibllToolkit\ArchiveHeader;

defined('ABSPATH') || exit;

final class Settings
{
    public const MODULE = 'archive-header';
    public const OPTION = 'mao_tk_toolkit_archive_header';

    public static function defaults(): array
    {
        return array('show_cover' => false, 'show_description' => true);
    }

    public static function normalize(array $values): array
    {
        $defaults = self::defaults();
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $defaults) || !is_bool($value)) {
                throw new \InvalidArgumentException('归档页顶部设置无效。');
            }
        }
        return array_replace($defaults, $values);
    }

    public static function read(): array
    {
        $saved = get_option(self::OPTION, array());
        try {
            return self::normalize(is_array($saved) ? $saved : array());
        } catch (\InvalidArgumentException $error) {
            return self::defaults();
        }
    }
}
