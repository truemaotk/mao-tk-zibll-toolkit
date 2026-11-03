<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

final class ModuleManager
{
    public const OPTION_KEY = 'mao_tk_zibll_toolkit_settings';
    private $modules = array();
    private $registration_errors = 0;
    private $started = false;

    public static function defaults(): array
    {
        return array('schema_version' => 1, 'modules' => array(
            'article-ui-code' => true,
            'article-ui-link-preview' => true,
            'article-ui-tabs' => true,
            'article-ui-postsbox' => true,
            'article-note' => true,
            'article-ui-reading' => true,
            'article-ui-resources' => true,
            'page-showcase' => true,
            'private-links' => true,
            'site-maintenance' => false,
            'user-profile-presets' => true,
            'page-order-query' => true,
            'site-not-found' => true,
            'data-migration-links' => true,
            'data-migration-posts' => true,
            'data-migration-products' => true,
            'system-mail-appearance' => false,
            'site-custom-footer' => false,
        ));
    }

    private function read_settings(): array
    {
        $raw = get_option(self::OPTION_KEY, null);
        if ($raw === null) {
            return array('valid' => true, 'value' => self::defaults());
        }
        $valid = is_array($raw)
            && isset($raw['schema_version'], $raw['modules'])
            && $raw['schema_version'] === 1
            && is_array($raw['modules']);
        if ($valid) {
            foreach ($raw['modules'] as $id => $enabled) {
                if (!is_string($id) || !preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id) || !is_bool($enabled)) {
                    $valid = false;
                    break;
                }
            }
        }
        return array('valid' => $valid, 'value' => $valid ? $raw : self::defaults());
    }

    public function storage_is_valid(): bool
    {
        return $this->read_settings()['valid'];
    }

    public function register(Module $module): bool
    {
        try {
            $id = $module->id();
            if ($this->started || !preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id) || isset($this->modules[$id])) {
                $this->registration_errors++;
                return false;
            }
            $this->modules[$id] = array(
                'instance' => $module,
                'id' => $id,
                'name' => $module->name(),
                'description' => $module->description(),
                'requires_zibll' => $module->requires_zibll(),
                'enabled' => false,
                'status' => 'disabled',
            );
            return true;
        } catch (\Throwable $error) {
            $this->registration_errors++;
            return false;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    public function registration_errors(): int
    {
        return $this->registration_errors;
    }

    public function start(array $theme): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;
        $settings = $this->read_settings();
        foreach ($this->modules as $id => &$record) {
            // Only missing keys inherit defaults. An explicit false remains off.
            $record['enabled'] = $settings['value']['modules'][$id] ?? (self::defaults()['modules'][$id] ?? false);
            if (!$settings['valid']) {
                $record['status'] = 'invalid';
            } elseif (!$record['enabled']) {
                $record['status'] = 'disabled';
            } elseif ($record['requires_zibll'] && empty($theme['is_zibll'])) {
                $record['status'] = 'blocked';
            } else {
                try {
                    $record['instance']->boot();
                    $record['status'] = 'loaded';
                } catch (\Throwable $error) {
                    // Do not send exception details to the browser or system log.
                    $record['status'] = 'error';
                }
            }
        }
        unset($record);
    }

    public function all(): array
    {
        $records = array();
        foreach ($this->modules as $record) {
            unset($record['instance']);
            $records[] = $record;
        }
        return $records;
    }

    public function set_enabled(string $id, bool $enabled): bool
    {
        if (!$this->has($id)) {
            return false;
        }
        // Re-read on each write; no stale static option cache.
        $settings = $this->read_settings();
        if (!$settings['valid']) {
            return false;
        }
        $next = $settings['value'];
        $next['modules'][$id] = $enabled;
        if (get_option(self::OPTION_KEY, null) === $next) {
            return true;
        }
        update_option(self::OPTION_KEY, $next, false);
        // update_option(false) can mean unchanged or failure; verify persisted data.
        return get_option(self::OPTION_KEY, null) === $next;
    }

    /** Trusted module code only. Reserved manager fields cannot be replaced. */
    public function set_extension_settings(array $fields): bool
    {
        foreach ($fields as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key) || in_array($key, array('schema_version', 'modules'), true)) { return false; }
        }
        $settings = $this->read_settings();
        if (!$settings['valid']) { return false; }
        $next = array_replace($settings['value'], $fields);
        if (get_option(self::OPTION_KEY, null) === $next) { return true; }
        update_option(self::OPTION_KEY, $next, false);
        return get_option(self::OPTION_KEY, null) === $next;
    }
}
