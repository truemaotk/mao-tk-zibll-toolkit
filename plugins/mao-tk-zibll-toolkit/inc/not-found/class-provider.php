<?php
namespace MaoTK\ZibllToolkit\NotFound;
defined('ABSPATH') || exit;

/** A fixed, server-side public feed. No visitor parameters, relaxed TLS, or arbitrary URLs. */
final class Provider
{
    public const API = 'https://api.xunjinlu.fun/api/babygome/index.php?type=json';
    public const ALTERNATE = 'https://apis.uctb.cn/api/babyHome';
    public const CACHE = 'mao_tk_nf_public_feed_v1';
    public const STALE = 'mao_tk_nf_public_stale_v1';
    public const RETRY = 'mao_tk_nf_public_retry_v1';
    public const STATUS = 'mao_tk_nf_public_status_v1';
    public static function source_label(string $provider): string { return $provider === 'uctb' ? '让爱回家公益寻人网' : '宝贝回家'; }
    public static function text($value, int $limit = 240): string
    {
        if (!is_string($value) && !is_int($value)) { return ''; }
        $text = sanitize_text_field((string) $value);
        return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
    }
    public static function url($url): string
    {
        if (!is_string($url) || strlen($url) > 1500) { return ''; }
        $p = wp_parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || !in_array(strtolower($p['host'] ?? ''), array('rangaihuijia.com', 'www.rangaihuijia.com', 'baobeihuijia.com', 'www.baobeihuijia.com', 'so.baobeihuijia.com'), true) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) { return ''; }
        return wp_http_validate_url($url) ? esc_url_raw($url) : '';
    }
    public static function normalize($result): array
    {
        if (!is_array($result) || !in_array($result['code'] ?? null, array(200, '200'), true) || !is_array($result['data'] ?? null)) { return array(); }
        $d = $result['data'];
        $name = self::text($d['name'] ?? '', 60); $url = self::url($d['detailUrl'] ?? $d['detail_url'] ?? $d['source_url'] ?? '');
        if ($name === '' || $url === '') { return array(); }
        $fields = array();
        foreach (array('性别' => $d['gender'] ?? $d['sex'] ?? '', '出生日期' => $d['birthDay'] ?? $d['birth_date'] ?? '', '年龄' => $d['now_age'] ?? '', '失踪时间' => $d['lostDay'] ?? $d['missing_date'] ?? '', '失踪地点' => $d['lostAddress'] ?? $d['missing_place'] ?? $d['missing_address'] ?? '', '特征描述' => $d['feature'] ?? $d['missing_feature'] ?? '') as $label => $raw) {
            $text = self::text($raw); if ($text !== '') { $fields[$label] = $text; }
        }
        $source = strpos((string) wp_parse_url($url, PHP_URL_HOST), 'baobeihuijia.com') !== false ? '宝贝回家' : '让爱回家公益寻人网';
        return array('name' => $name, 'photo' => self::url($d['photoUrl'] ?? $d['image'] ?? $d['photo'] ?? ''), 'url' => $url, 'fields' => $fields, 'source' => $source);
    }
    private static function cached($raw): array
    {
        if (!is_array($raw) || !is_array($raw['record'] ?? null) || !is_int($raw['time'] ?? null) || $raw['time'] < time() - 6 * HOUR_IN_SECONDS || $raw['time'] > time() + MINUTE_IN_SECONDS) { return array(); }
        $r = $raw['record'];
        if (!is_string($r['name'] ?? null) || $r['name'] === '' || self::url($r['url'] ?? '') === '' || !is_array($r['fields'] ?? null) || count($r['fields']) > 6) { return array(); }
        $fields = array();
        foreach ($r['fields'] as $label => $text) { if (in_array($label, array('性别','出生日期','年龄','失踪时间','失踪地点','特征描述'), true) && (is_string($text) || is_int($text))) { $fields[$label] = self::text($text); } }
        $source = strpos((string) wp_parse_url($r['url'], PHP_URL_HOST), 'baobeihuijia.com') !== false ? '宝贝回家' : '让爱回家公益寻人网';
        return array('time' => $raw['time'], 'provider' => is_string($raw['provider'] ?? null) ? $raw['provider'] : 'uctb', 'record' => array('name' => self::text($r['name'], 60), 'url' => self::url($r['url']), 'photo' => self::url($r['photo'] ?? ''), 'fields' => $fields, 'source' => $source));
    }
    public static function get(int $timeout = 2): array
    {
        $state = (new Settings())->read(); if (!$state['valid'] || !$state['value']['charity_enabled']) { return array('record' => array(), 'stale' => false); }
        $provider = $state['value']['provider']; $api = $provider === 'uctb' ? self::ALTERNATE : self::API;
        $fresh = self::cached(get_transient(self::CACHE));
        if ($fresh && $fresh['provider'] !== $provider) { $fresh = array(); }
        if ($fresh && $fresh['time'] >= time() - 15 * MINUTE_IN_SECONDS) { return array('record' => $fresh['record'], 'stale' => false); }
        $old = self::cached(get_transient(self::STALE));
        if ($old && $old['provider'] !== $provider) { $old = array(); }
        if (!get_transient(self::RETRY)) {
            // A short retry gate also limits repeated requests when the upstream is unavailable.
            set_transient(self::RETRY, 1, MINUTE_IN_SECONDS);
            try { $response = wp_safe_remote_get($api, array('timeout' => min(5, max(1, $timeout)), 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 131072, 'headers' => array('Accept' => 'application/json'))); }
            catch (\Throwable $error) { $response = new \WP_Error('mao_tk_nf_upstream_unavailable'); }
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response); $record = is_string($body) && strlen($body) <= 131072 ? self::normalize(json_decode($body, true, 12)) : array();
                if ($record) {
                    $cache = array('time' => time(), 'provider' => $provider, 'record' => $record);
                    set_transient(self::CACHE, $cache, 15 * MINUTE_IN_SECONDS); set_transient(self::STALE, $cache, 6 * HOUR_IN_SECONDS);
                    set_transient(self::STATUS, array('time' => time(), 'provider' => $provider, 'result' => 'success'), DAY_IN_SECONDS);
                    return array('record' => $record, 'stale' => false);
                }
            }
            $reason = 'unavailable';
            if (is_wp_error($response)) { $message = $response->get_error_message(); if (stripos($message, 'timed out') !== false || strpos($message, 'cURL error 28') !== false) { $reason = 'timeout'; } elseif (stripos($message, 'SSL') !== false || stripos($message, 'certificate') !== false) { $reason = 'tls'; } }
            elseif (wp_remote_retrieve_response_code($response) !== 200) { $reason = 'http'; }
            else { $reason = 'schema'; }
            set_transient(self::STATUS, array('time' => time(), 'provider' => $provider, 'result' => $reason), DAY_IN_SECONDS);
        }
        return array('record' => $old ? $old['record'] : array(), 'stale' => (bool) $old);
    }
}
