<?php
namespace MaoTK\ZibllToolkit\OrderQuery;
defined('ABSPATH') || exit;
final class Renderer
{
    public static function card(array $order): string
    {
        if (!Orders::owns($order)) { return ''; }
        $data = is_callable(array('ZibPay', 'get_meta')) ? \ZibPay::get_meta((int) $order['id'], 'order_data') : array();
        $title = is_array($data) && is_string($data['product_title'] ?? null) ? $data['product_title'] : '';
        if ($title === '' && !empty($order['post_id'])) { $title = get_the_title((int) $order['post_id']); }
        $type = function_exists('zibpay_get_pay_type_name') ? zibpay_get_pay_type_name((int) ($order['order_type'] ?? 0)) : '订单';
        if ($title === '') { $title = is_string($type) && $type !== '' ? $type : '订单'; }
        $status = (int) ($order['status'] ?? 0);
        $names = array(-2 => '已退款', -1 => '已关闭', 0 => '待支付', 1 => '已支付');
        $name = $names[$status] ?? '查看详情';
        $mark = ($order['pay_type'] ?? '') === 'points' ? '积分 ' : (function_exists('zibpay_get_pay_mark') ? wp_strip_all_tags((string) zibpay_get_pay_mark()) : '￥');
        $price = $order['pay_price'] ?? $order['order_price'] ?? '';
        $details = function_exists('zib_get_refresh_modal_link') ? zib_get_refresh_modal_link(array('tag' => 'a', 'class' => 'but c-blue', 'data_class' => 'modal-mini full-sm', 'height' => 520, 'mobile_bottom' => true, 'text' => '查看详情', 'query_arg' => array('action' => 'order_details_modal', 'order_id' => (int) $order['id']))) : '';
        $pay = $status === 0 && function_exists('zibpay_get_order_pay_link') ? zibpay_get_order_pay_link($order, 'but c-red', '继续支付') : '';
        ob_start(); ?>
        <article class="mao-tk-oq__card"><div class="mao-tk-oq__card-top"><div><span class="mao-tk-oq__type"><?php echo esc_html((string) $type); ?></span><h3><?php echo esc_html(wp_strip_all_tags($title)); ?></h3></div><span class="mao-tk-oq__status" data-status="<?php echo esc_attr((string) $status); ?>"><?php echo esc_html($name); ?></span></div>
        <dl><div><dt>订单号</dt><dd><?php echo esc_html((string) ($order['order_num'] ?? '')); ?></dd></div><div><dt>创建时间</dt><dd><?php echo esc_html((string) ($order['create_time'] ?? '')); ?></dd></div></dl>
        <div class="mao-tk-oq__card-bottom"><strong><?php echo esc_html((string) $mark . (string) $price); ?></strong><div class="mao-tk-oq__actions"><?php echo $details; // Trusted native helper; ownership is verified above. ?><?php echo $pay; // Native payment modal only; this renderer never initiates a payment. ?></div></div></article>
        <?php return (string) ob_get_clean();
    }
    public static function results(array $result, string $mode = 'search'): string
    {
        $html = '<div class="mao-tk-oq__result-head"><h2>' . ($mode === 'search' ? '查询结果' : '最近查询') . '</h2><span>仅显示有权查看的订单</span></div>';
        $cards = ''; foreach ($result['orders'] as $order) { if (is_array($order)) { $cards .= self::card($order); } }
        if ($cards === '') { return $html . '<div class="mao-tk-oq__empty">' . ($mode === 'clear' ? '记录已清空，原订单保留。' : '暂无可显示的订单。请核对订单账号、查询内容或原浏览器凭据。') . '</div>'; }
        $html .= '<div class="mao-tk-oq__list">' . $cards . '</div>';
        if ($mode === 'search' && ($result['page'] > 1 || $result['has_next'])) {
            $html .= '<nav class="mao-tk-oq__pagination" aria-label="订单结果分页">';
            if ($result['page'] > 1) { $html .= '<button type="button" data-order-page="' . (int) ($result['page'] - 1) . '">上一页</button>'; }
            $html .= '<span>第 ' . (int) $result['page'] . ' 页 · 每页最多 20 条</span>';
            if ($result['has_next']) { $html .= '<button type="button" data-order-page="' . (int) ($result['page'] + 1) . '">下一页</button>'; }
            $html .= '</nav>';
        }
        return $html;
    }
}
