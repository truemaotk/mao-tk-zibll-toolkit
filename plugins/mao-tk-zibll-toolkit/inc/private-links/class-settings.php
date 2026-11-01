<?php
namespace MaoTK\ZibllToolkit\PrivateLinks;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'mao_tk_zibll_toolkit_private_links';
    public const MODULE = 'private-links';

    public static function roles(): array
    {
        $roles = wp_roles();
        return is_object($roles) && is_array($roles->roles) ? $roles->roles : array();
    }

    public static function defaults(): array
    {
        $allowed = array();
        foreach (self::roles() as $key => $role) {
            if (!empty($role['capabilities']['manage_options'])) { $allowed[] = $key; }
        }
        return array('schema_version' => 1, 'allowed_roles' => $allowed, 'user_overrides' => array());
    }

    public static function validate($raw): ?array
    {
        if (!is_array($raw) || ($raw['schema_version'] ?? null) !== 1 || !isset($raw['allowed_roles'], $raw['user_overrides']) || !is_array($raw['allowed_roles']) || !is_array($raw['user_overrides']) || count($raw['allowed_roles']) > 200 || count($raw['user_overrides']) > 10000) { return null; }
        $roles = array(); $overrides = array();
        foreach ($raw['allowed_roles'] as $role) {
            if (!is_string($role) || !preg_match('/^[a-zA-Z0-9_\-]{1,100}$/D', $role)) { return null; }
            $roles[] = $role;
        }
        foreach ($raw['user_overrides'] as $id => $decision) {
            if (!preg_match('/^[1-9][0-9]{0,18}$/D', (string) $id) || !in_array($decision, array('allow', 'deny'), true)) { return null; }
            $overrides[(int) $id] = $decision;
        }
        return array('schema_version' => 1, 'allowed_roles' => array_values(array_unique($roles)), 'user_overrides' => $overrides);
    }

    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        $value = $raw === null ? self::defaults() : self::validate($raw);
        return array('valid' => $value !== null, 'value' => $value ?? array('schema_version' => 1, 'allowed_roles' => array(), 'user_overrides' => array()));
    }

    public function save(array $value): bool
    {
        $next = self::validate($value);
        if ($next === null || !$this->read()['valid']) { return false; }
        if (get_option(self::OPTION, null) === $next) { return true; }
        update_option(self::OPTION, $next, false);
        return get_option(self::OPTION, null) === $next;
    }

    public static function permits($user, array $value): bool
    {
        if (!is_object($user) || empty($user->ID) || !is_array($user->roles)) { return false; }
        $override = $value['user_overrides'][(int) $user->ID] ?? '';
        if ($override !== '') { return $override === 'allow'; }
        return (bool) array_intersect($user->roles, $value['allowed_roles'], array_keys(self::roles()));
    }

    public function allows_current_user(): bool
    {
        $state = $this->read();
        return $state['valid'] && is_user_logged_in() && self::permits(wp_get_current_user(), $state['value']);
    }
}
