<?php
namespace MaoTK\ZibllToolkit\OrderQuery;
defined('ABSPATH') || exit;

/** Read-only adapter. Never calls get_order_status(): Zibll may close expired orders in that function. */
final class Orders
{
    public const PER_PAGE = 20;
    public const HISTORY = 'mao_tk_order_query_history';
    public static function available(): bool
    {
        return class_exists('ZibPay') && is_callable(array('ZibPay', 'order_query')) && is_callable(array('ZibPay', 'get_row')) && is_callable(array('ZibPay', 'get_order'));
    }
    public static function owns(array $order): bool
    {
        $owner = (int) ($order['user_id'] ?? 0); $user = get_current_user_id();
        if ($user > 0 && $owner === $user) { return true; }
        // A cookie name/ID alone is not authority. The theme verifies its signed, expiring credential.
        $id = (int) ($order['id'] ?? 0);
        return $owner === 0 && $id > 0 && isset($_COOKIE['order_hmac_' . $id]) && is_string($_COOKIE['order_hmac_' . $id]) && function_exists('zibpay_verify_order_permission') && (bool) zibpay_verify_order_permission($order);
    }
    public static function keyword($raw): string
    {
        if (!is_string($raw) || strlen($raw) > 240 || preg_match('/[\x00-\x1f\x7f]/', $raw)) { throw new \InvalidArgumentException('查询内容格式无效，请缩短后重试。'); }
        return trim(sanitize_text_field($raw));
    }
    private static function base(int $page): array
    {
        return array('user_id' => get_current_user_id(), 'paged' => $page, 'per_page' => self::PER_PAGE, 'orderby' => 'id', 'order' => 'DESC');
    }
    public static function search(string $keyword, int $page = 1): array
    {
        if (!self::available()) { throw new \RuntimeException('子比订单接口暂不可用，请稍后再试。'); }
        if ($page < 1 || $page > 1000) { throw new \InvalidArgumentException('页码无效。'); }
        $args = self::base($page); $user = get_current_user_id(); $rows = array();
        if ($keyword !== '') { $args['s'] = $keyword; $args['search_columns'] = array('order_num', 'pay_num', 'order_data'); }
        if (!$user) {
            $ids = array();
            foreach (array_keys($_COOKIE) as $key) {
                if (is_string($key) && preg_match('/^order_hmac_([1-9][0-9]{0,17})$/D', $key, $m)) { $ids[] = (int) $m[1]; if (count($ids) >= 20) { break; } }
            }
            if (!$ids) { return array('orders' => array(), 'has_next' => false, 'page' => $page); }
            // Revalidate before querying/counting. Fabricated cookies cannot expose order existence or totals.
            $allowed = array();
            foreach ($ids as $id) { $row = \ZibPay::get_order($id); if (is_array($row) && self::owns($row)) { $allowed[] = $id; } }
            if (!$allowed) { return array('orders' => array(), 'has_next' => false, 'page' => $page); }
            $args['id'] = $allowed;
        }
        // Resolve the payment header first, then use the same scoped query for every page.
        if (preg_match('/^520[0-9]{12,30}$/D', $keyword) && is_callable(array('ZibPay', 'get_payment'))) {
            $payment = \ZibPay::get_payment($keyword);
            if (is_array($payment) && !empty($payment['id'])) { unset($args['s'], $args['search_columns']); $args['payment_id'] = (int) $payment['id']; }
        }
        $result = \ZibPay::order_query($args);
        foreach (is_array($result['orders'] ?? null) ? $result['orders'] : array() as $order) {
            if (is_object($order)) { $order = (array) $order; }
            if (is_array($order) && self::owns($order)) { $rows[(int) $order['id']] = $order; }
        }
        krsort($rows, SORT_NUMERIC);
        return array('orders' => array_values(array_slice($rows, 0, self::PER_PAGE, true)), 'has_next' => count($rows) === self::PER_PAGE && (int) ($result['total'] ?? 0) > $page * self::PER_PAGE, 'page' => $page);
    }
    public static function history(bool $clear = false): array
    {
        $user = get_current_user_id(); if (!$user) { return array(); }
        if ($clear) { delete_user_meta($user, self::HISTORY); return array(); }
        $ids = get_user_meta($user, self::HISTORY, true); $rows = array();
        foreach (is_array($ids) ? array_slice($ids, 0, 10) : array() as $id) {
            if (!is_int($id) || $id < 1) { continue; }
            $order = \ZibPay::get_order($id);
            if (is_array($order) && self::owns($order)) { $rows[] = $order; }
        }
        return $rows;
    }
    public static function remember(array $orders): void
    {
        $user = get_current_user_id(); if (!$user || !$orders) { return; }
        $ids = array(); foreach ($orders as $order) { if (is_array($order) && self::owns($order)) { $ids[] = (int) $order['id']; } }
        $old = get_user_meta($user, self::HISTORY, true);
        if (is_array($old)) { foreach ($old as $id) { if (is_int($id) && $id > 0) { $ids[] = $id; } } }
        update_user_meta($user, self::HISTORY, array_slice(array_values(array_unique($ids)), 0, 10));
    }
}
