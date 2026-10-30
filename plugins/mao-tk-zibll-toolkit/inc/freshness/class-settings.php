<?php
namespace MaoTK\ZibllToolkit\Freshness;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'mao_tk_zibll_toolkit_article_freshness';

    public static function defaults(): array
    {
        return array('schema_version' => 1, 'days' => 1, 'message' => '部分内容可能具有时效性，请结合当前情况判断。如有错误或失效，欢迎反馈。', 'contact_url' => '');
    }

    public static function validate($raw): ?array
    {
        if (!is_array($raw) || ($raw['schema_version'] ?? null) !== 1) { return null; }
        $days = $raw['days'] ?? null;
        if (!(is_int($days) || is_string($days)) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string) $days) || (int) $days > 3650) { return null; }
        if (!isset($raw['message'], $raw['contact_url']) || !is_string($raw['message']) || !is_string($raw['contact_url']) || strlen($raw['message']) > 1800 || strlen($raw['contact_url']) > 2048) { return null; }
        $message = trim(sanitize_text_field($raw['message']));
        if ($message === '') { return null; }
        $contact = trim($raw['contact_url']);
        if ($contact !== '') {
            if (!preg_match('~^https?://~i', $contact)) { return null; }
            $contact = esc_url_raw($contact, array('http', 'https'));
            if ($contact === '' || !wp_parse_url($contact, PHP_URL_HOST)) { return null; }
        }
        return array('schema_version' => 1, 'days' => (int) $days, 'message' => $message, 'contact_url' => $contact);
    }

    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        if ($raw === null) { return array('valid' => true, 'value' => self::defaults()); }
        $value = self::validate($raw);
        return array('valid' => $value !== null, 'value' => $value ?? self::defaults());
    }

    public function save(array $value): bool
    {
        $value = self::validate($value);
        if ($value === null || !$this->read()['valid']) { return false; }
        if (get_option(self::OPTION, null) === null) { add_option(self::OPTION, $value, '', false); }
        elseif (get_option(self::OPTION) !== $value) { update_option(self::OPTION, $value, false); }
        return get_option(self::OPTION, null) === $value;
    }
}
