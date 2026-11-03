<?php
namespace MaoTK\ZibllToolkit\SystemMail;
defined('ABSPATH') || exit;

final class Catalog
{
    public const MODULE='system-mail-appearance';
    public static function all(): array
    {
        $account=array('site_name','site_url','year','original_subject','original_content','support_email','support_name');
        $order=array_merge($account,array('order_number','amount','status','product_name','action_url'));
        return array(
            'captcha'=>array('label'=>'操作验证码','group'=>'账户邮件','layout'=>'code','variables'=>array_merge($account,array('code','expiry')),'source'=>'子比 zib_send_captcha；从原始通知读取验证码与有效期，识别失败时回退原生。'),
            'reset_captcha'=>array('label'=>'找回密码 · 验证码','group'=>'账户邮件','layout'=>'code','variables'=>array_merge($account,array('code','expiry')),'source'=>'子比 resetpassword_captcha → zib_send_captcha；沿用原校验与频率限制。'),
            'welcome'=>array('label'=>'注册／账号创建通知','group'=>'账户邮件','layout'=>'welcome','variables'=>array_merge($account,array('user_name','user_login','action_url')),'source'=>'WordPress wp_new_user_notification_email；子比前台注册未发现独立欢迎邮件，不额外发送。'),
            'reset_link'=>array('label'=>'找回密码 · 重置链接','group'=>'账户邮件','layout'=>'welcome','variables'=>array_merge($account,array('user_name','user_login','action_url')),'source'=>'WordPress retrieve_password_notification_email；仅在原生重置链接邮件实际发送时接管外观。'),
            'payment_customer'=>array('label'=>'付款成功 · 买家','group'=>'订单邮件','layout'=>'order','variables'=>$order,'source'=>'子比 zibpay_mail_payment_order；保留原开关、付款明细与原始通知。'),
            'shipping_seller'=>array('label'=>'待发货提醒 · 商家','group'=>'订单邮件','layout'=>'order','variables'=>$order,'source'=>'子比 zib_shop_notify_shipping_to_author；真实订单数据来自原通知函数参数。'),
            'shipping_customer'=>array('label'=>'发货通知 · 买家','group'=>'订单邮件','layout'=>'order','variables'=>$order,'source'=>'子比 zib_shop_manual_shipping_to_user；保留快递单号、物流公司与发货备注。'),
            'virtual_delivery'=>array('label'=>'虚拟商品交付 · 买家','group'=>'订单邮件','layout'=>'order','variables'=>array_values(array_diff($order,array('action_url'))),'source'=>'子比 zib_shop_virtual_shipping_to_user；原始交付内容必定保留，不记录到设置或日志。'),
            'aftersale_customer'=>array('label'=>'售后处理结果 · 买家','group'=>'订单邮件','layout'=>'order','variables'=>$order,'source'=>'子比 zib_shop_after_sale_to_end_to_user；不是所有售后结果都代表退款成功，状态沿用原通知。'),
        );
    }
    public static function unsupported(): array
    {
        return array('下单但尚未付款：本次未找到独立邮件入口，不新增发送。','独立“退款到账成功”邮件：本次仅接入售后结果，不把售后完成等同于退款到账。','子比前台注册成功独立欢迎信：未发现原始发送行为；仅美化确实发生的 WordPress 账号通知。','管理员支付通知、推广佣金、分成、评论、私信等其他邮件：保留原生，后续可分别适配。','WooCommerce 与 Order Email Manager：独立完整模板，自动排除，暂不在此模块编辑。');
    }
    public static function variables(string $type): array
    {
        return $type==='common'?array('site_name','site_url','year','original_subject','original_content','support_email','support_name'):(array_values(self::all()[$type]['variables']??array()));
    }
    /** Template inspection uses tokens, never fabricated codes, customers or orders. */
    public static function structure(string $type): array
    {
        if(!isset(self::all()[$type]))throw new \InvalidArgumentException('未知邮件类型。');
        $c=array('site_name'=>get_bloginfo('name'),'site_url'=>home_url('/'),'year'=>wp_date('Y'),'original_subject'=>'{{original_subject}}','original_content'=>'<p>{{original_content}}</p>','action_url'=>'','currency_mark'=>'￥','currency_unit'=>'元');
        foreach(self::variables($type) as $key)if(!array_key_exists($key,$c))$c[$key]='{{'.$key.'}}';
        return $c;
    }
    /** Backward-compatible name; test contexts now contain variables, never fake business data. */
    public static function sample(string $type): array{return self::structure($type);}
}
