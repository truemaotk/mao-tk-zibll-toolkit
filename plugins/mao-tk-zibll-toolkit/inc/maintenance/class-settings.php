<?php
namespace MaoTK\ZibllToolkit\Maintenance;

defined('ABSPATH') || exit;

final class Settings
{
    public const MODULE = 'site-maintenance';
    public const OPTION = 'mao_tk_zibll_toolkit_maintenance';

    public static function defaults(): array
    {
        return array('schema_version' => 1, 'title' => '网站维护中', 'description' => '<p>我们正在进行更新维护，请稍后再来。</p>', 'logo_url' => '', 'end_at' => '', 'background' => 'blue', 'backup_url' => '', 'backup_label' => '访问备用站点', 'copyright' => '', 'beian' => '', 'retry_after' => 600);
    }

    public static function palettes(): array
    {
        return array('blue' => '蓝青渐变', 'violet' => '蓝紫渐变', 'pink' => '粉紫渐变', 'green' => '青绿渐变', 'orange' => '橙红渐变', 'magenta' => '玫紫渐变');
    }

    public static function allowed_html(): array
    {
        return array('p' => array(), 'br' => array(), 'strong' => array(), 'em' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'a' => array('href' => true, 'title' => true, 'rel' => true));
    }

    public static function url(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') { return ''; }
        $parts = wp_parse_url($raw);
        if (strlen($raw) > 2048 || !is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20]/', $raw)) {
            throw new \InvalidArgumentException('网址必须是完整的 HTTP / HTTPS 地址，且不能包含账号或密码。');
        }
        return esc_url_raw($raw, array('http', 'https'));
    }

    public static function deadline(string $value): ?int
    {
        if ($value === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) { throw new \InvalidArgumentException('预计恢复时间无效，请重新选择日期和时间。'); }
        return $date->getTimestamp();
    }

    public static function normalize(array $raw): array
    {
        $v = self::defaults();
        foreach (array_keys($v) as $key) {
            if (!array_key_exists($key, $raw)) { continue; }
            if ($key === 'schema_version') { if ($raw[$key] !== 1) { throw new \InvalidArgumentException('维护页配置版本无效。'); } continue; }
            if ($key === 'retry_after') {
                if (!is_scalar($raw[$key]) || !preg_match('/^[0-9]{1,5}$/D', (string) $raw[$key]) || (int) $raw[$key] < 60 || (int) $raw[$key] > 86400) { throw new \InvalidArgumentException('重试间隔应为 60—86400 秒。'); }
                $v[$key] = (int) $raw[$key]; continue;
            }
            if (!is_string($raw[$key])) { throw new \InvalidArgumentException('维护页字段格式无效。'); }
            $value = trim($raw[$key]);
            if (strlen($value) > ($key === 'description' ? 12000 : 2048)) { throw new \InvalidArgumentException('维护页内容过长，请缩短后保存。'); }
            if (in_array($key, array('logo_url', 'backup_url'), true)) { $value = self::url($value); }
            elseif ($key === 'description') { $value = wp_kses($value, self::allowed_html()); }
            elseif ($key === 'end_at') { self::deadline($value); }
            elseif ($key === 'background') { if (!isset(self::palettes()[$value])) { throw new \InvalidArgumentException('请选择有效的背景配色。'); } }
            else { $value = sanitize_text_field($value); }
            $v[$key] = $value;
        }
        if ($v['title'] === '') { throw new \InvalidArgumentException('请填写维护标题。'); }
        return $v;
    }

    public function read(): array
    {
        $raw = get_option(self::OPTION, null);
        if ($raw === null) { return array('valid' => true, 'value' => self::defaults(), 'exists' => false); }
        try {
            if (!is_array($raw) || ($raw['schema_version'] ?? null) !== 1) { throw new \InvalidArgumentException(); }
            return array('valid' => true, 'value' => self::normalize($raw), 'exists' => true);
        } catch (\InvalidArgumentException $error) {
            return array('valid' => false, 'value' => self::defaults(), 'exists' => true);
        }
    }

    public function save(array $raw): bool
    {
        if (!$this->read()['valid']) { return false; }
        $value = self::normalize($raw);
        update_option(self::OPTION, $value, false);
        return get_option(self::OPTION) === $value;
    }

    /** Explicit, one-time content import. Never imports the old activation switch. */
    public function legacy(): array
    {
        $old = get_option('VaiPlugin', null);
        if (!is_array($old)) { throw new \InvalidArgumentException('未发现旧维护插件的可导入配置。'); }
        $mapped = array();
        foreach (array('title' => 'maintenance_title', 'description' => 'maintenance_desc', 'logo_url' => 'maintenance_logo', 'backup_url' => 'maintenance_redirectUrl', 'backup_label' => 'maintenance_redirectUrlName', 'copyright' => 'maintenance_copyright', 'beian' => 'maintenance_beian') as $new => $key) {
            if (isset($old[$key]) && is_string($old[$key])) { $mapped[$new] = $old[$key]; }
        }
        if (!empty($old['maintenance_time']) && is_string($old['maintenance_time'])) {
            $date = \DateTimeImmutable::createFromFormat('!Y/m/d H:i', $old['maintenance_time'], wp_timezone());
            if (!$date || $date->format('Y/m/d H:i') !== $old['maintenance_time']) { throw new \InvalidArgumentException('旧配置的恢复时间格式无效，未执行导入。'); }
            $mapped['end_at'] = $date->format('Y-m-d\TH:i');
        }
        return self::normalize($mapped);
    }
}
