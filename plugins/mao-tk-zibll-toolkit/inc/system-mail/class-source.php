<?php
namespace MaoTK\ZibllToolkit\SystemMail;
defined('ABSPATH') || exit;

final class Source
{
    private static $pending=array();
    private static function key(array $mail): string{return hash('sha256',wp_json_encode($mail['to']??'').'|'.(string)($mail['message']??''));}
    private static function base(array $mail): array{return array('site_name'=>get_bloginfo('name'),'site_url'=>home_url('/'),'year'=>wp_date('Y'),'original_subject'=>(string)($mail['subject']??''),'original_content'=>(string)($mail['message']??''));}
    public static function capture_welcome(array $mail,$user,$blog): array
    {
        $context=self::base($mail);$context['user_name']=$user->display_name;$context['user_login']=$user->user_login;$context['action_url']=self::plain_action($mail['message']);
        self::remember($mail,'welcome',$context);return $mail;
    }
    public static function capture_reset(array $mail,$key,$login,$user): array
    {
        $context=self::base($mail);$context['user_name']=$user->display_name;$context['user_login']=$login;$context['action_url']=self::plain_action($mail['message']);
        self::remember($mail,'reset_link',$context);return $mail;
    }
    private static function remember(array $mail,string $type,array $context): void
    {
        if(count(self::$pending)>=16)array_shift(self::$pending);self::$pending[self::key($mail)]=array('type'=>$type,'data'=>$context,'plain'=>true);
    }
    private static function plain_action(string $message): string
    {
        if(preg_match_all('~https?://[^\s<>"\x27]+~',$message,$m))foreach($m[0] as $url){$url=html_entity_decode($url,ENT_QUOTES,'UTF-8');if(strpos($url,'action=rp')!==false&&Settings::url($url))return $url;}
        return '';
    }
    public static function complete(array $mail,array $trace): bool
    {
        $body=(string)($mail['message']??'');if(preg_match('/<!doctype\s+html|<html\b|data-maotk-system-mail=|class=[\x22\x27]zibll-email-box/i',$body))return true;
        foreach($trace as $frame){$class=$frame['class']??'';if($class==='XT_OEM\\Email'||$class==='WC_Email'||($class!==''&&is_subclass_of($class,'WC_Email',true)))return true;}
        $headers=wp_json_encode($mail['headers']??'');return stripos($headers,'multipart/')!==false;
    }
    private static function action(array &$context): void
    {
        if(!class_exists('WP_HTML_Tag_Processor'))return;$p=new \WP_HTML_Tag_Processor($context['original_content']);$urls=array();
        while($p->next_tag('A')){$class=(string)$p->get_attribute('class');$href=$p->get_attribute('href');if(preg_match('/\bbut\b/',$class)&&is_string($href)&&Settings::url(html_entity_decode($href,ENT_QUOTES,'UTF-8')))$urls[]=html_entity_decode($href,ENT_QUOTES,'UTF-8');}
        if($urls)$context['action_url']=end($urls);
        // Keep every original link and payload; the primary CTA is an additional clear action.
    }
    public static function detect(array $mail,array $trace): ?array
    {
        $key=self::key($mail);if(isset(self::$pending[$key])){$out=self::$pending[$key];unset(self::$pending[$key]);$out['data']['original_subject']=(string)$mail['subject'];$out['data']['original_content']=nl2br(esc_html($mail['message']));return $out;}
        $names=array_column($trace,'function');
        foreach($trace as $frame){
            $fn=$frame['function']??'';
            if($fn==='zib_send_captcha'){
                $body=(string)($mail['message']??'');
                if(!preg_match('~<p\s+style="font-size:34px;color:\#3095f1;">\s*<span[^>]*>([A-Za-z0-9]{4,12})</span>\s*</p>~',$body,$code)||!preg_match('/验证码\s*([0-9]+\s*分钟)内有效/u',wp_strip_all_tags($body),$expiry))return null;
                $data=self::base($mail);$data['code']=$code[1];$data['expiry']=$expiry[1];$data['original_content']=str_replace($code[0],'',$body);
                return array('type'=>in_array('zib_ajax_resetpassword_captcha',$names,true)?'reset_captcha':'captcha','data'=>$data);
            }
            $map=array('zibpay_mail_payment_order'=>'payment_customer','zib_shop_notify_shipping_to_author'=>'shipping_seller','zib_shop_manual_shipping_to_user'=>'shipping_customer','zib_shop_virtual_shipping_to_user'=>'virtual_delivery','zib_shop_after_sale_to_end_to_user'=>'aftersale_customer');
            if(isset($map[$fn])){
                $order=$frame['args'][0]??null;$meta=$frame['args'][1]??array();if(is_object($order))$order=(array)$order;
                if(!is_array($order)||!is_scalar($order['order_num']??null)||!is_numeric($order['pay_price']??null))return null;
                $data=self::base($mail);$data['order_number']=(string)$order['order_num'];$data['amount']=(string)$order['pay_price'];
                $data['currency_mark']=function_exists('zibpay_get_pay_mark')?(string)zibpay_get_pay_mark():'';
                $data['currency_unit']=function_exists('zibpay_get_currency_unit')?(string)zibpay_get_currency_unit():'';
                if(($order['pay_type']??'')==='points')$data['amount'].=' 积分';
                elseif($fn==='zibpay_mail_payment_order'&&function_exists('zibpay_get_order_effective_amount')&&isset($order['pay_detail'])&&function_exists('zibpay_format_local_price'))$data['amount']=wp_strip_all_tags((string)zibpay_format_local_price(zibpay_get_order_effective_amount($order)));
                $data['status']=(string)$mail['subject'];$data['product_name']=is_array($meta)&&is_scalar($meta['product_title']??null)?(string)$meta['product_title']:'';
                if($data['product_name']===''&&!empty($order['post_id'])){$post=get_post((int)$order['post_id']);if($post)$data['product_name']=$post->post_title;}
                if($map[$fn]!=='virtual_delivery')self::action($data);
                return array('type'=>$map[$fn],'data'=>$data);
            }
            if(strpos($fn,'zib_shop_')===0||strpos($fn,'zibpay_mail_')===0)return null;
        }return null;
    }
    public static function inventory(): array
    {
        $active=(array)get_option('active_plugins',array());$order=false;$woo=false;foreach($active as $file){if(strpos($file,'xt-order-email-manager/')===0)$order=true;if(strpos($file,'woocommerce/')===0)$woo=true;}
        return array('toolkit'=>MAO_TK_ZIBLL_TOOLKIT_VERSION,'theme'=>(string)wp_get_theme(get_template())->get('Version'),'native_wrapper'=>function_exists('zib_get_mail_content'),'order_email_manager'=>$order||class_exists('XT_OEM\\Email',false),'woocommerce'=>$woo||class_exists('WooCommerce',false));
    }
}
