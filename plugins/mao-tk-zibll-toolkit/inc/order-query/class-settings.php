<?php
namespace MaoTK\ZibllToolkit\OrderQuery;
defined('ABSPATH') || exit;

final class Settings
{
    public const MODULE = 'page-order-query';
    public const TEMPLATE = 'maotk-order-query.php';
    public const OPTION = 'mao_tk_toolkit_order_query';
    public static function defaults(): array
    {
        return array('schema_version' => 1, 'revision' => 0, 'title' => '订单查询', 'description' => '查询自己的订单，查看支付状态与订单详情。', 'help' => '登录后可按订单号、支付单号或下单时填写的联系方式搜索自己的订单。免登录购买请使用下单时的同一浏览器；凭据失效时请联系网站客服。', 'contact_url' => '', 'history_enabled' => true);
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
            else {
                if (!is_string($v) || strlen($v) > ($key === 'help' ? 1800 : 600)) { throw new \InvalidArgumentException('文字过长或格式无效。'); }
                if ($key === 'contact_url') {
                    if ($v !== '' && (!wp_http_validate_url($v) || !in_array(strtolower((string) wp_parse_url($v, PHP_URL_SCHEME)), array('http','https'), true))) { throw new \InvalidArgumentException('客服链接应为完整的 HTTP 或 HTTPS 地址。'); }
                    $value[$key] = esc_url_raw($v);
                } else { $value[$key] = trim(sanitize_textarea_field($v)); }
            }
        }
        if ($value['title'] === '') { throw new \InvalidArgumentException('请填写页面标题。'); }
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
        $state = $this->read();
        if (!$state['valid'] || $state['value']['revision'] !== $revision) { return false; }
        $value = self::normalize($raw); $value['revision'] = $revision + 1;
        update_option(self::OPTION, $value, false);
        return get_option(self::OPTION) === $value;
    }
}
