<?php
namespace MaoTK\ZibllToolkit\SystemMail;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;

final class Runtime
{
    private static $registered=false;
    private static $native=false;
    public static function enabled(): bool
    {
        $raw=get_option(ModuleManager::OPTION_KEY,null);return get_template()==='zibll'&&is_array($raw)&&is_array($raw['modules']??null)&&($raw['modules'][Catalog::MODULE]??false)===true;
    }
    public static function register(): void
    {
        if(self::$registered)return;self::$registered=true;
        self::replace_native();add_action('init',array(__CLASS__,'replace_native'),999);
        add_filter('wp_mail',array(__CLASS__,'filter'),10);
        add_filter('wp_new_user_notification_email',array(Source::class,'capture_welcome'),PHP_INT_MAX,3);
        add_filter('retrieve_password_notification_email',array(Source::class,'capture_reset'),PHP_INT_MAX,4);
    }
    public static function replace_native(): void
    {
        $priority=has_filter('wp_mail','zib_get_mail_content');if($priority!==false){self::$native=true;remove_filter('wp_mail','zib_get_mail_content',$priority);}
    }
    private static function original(array $mail): array{return self::$native&&function_exists('zib_get_mail_content')?(array)zib_get_mail_content($mail):$mail;}
    public static function filter($mail)
    {
        if(!is_array($mail))return $mail;
        if(!self::enabled())return self::original($mail);
        $trace=debug_backtrace(0,20);if(Source::complete($mail,$trace))return $mail;
        $state=Settings::read();if(!$state['valid']||!preg_match('/^9\.2(?:\.|$)/',(string)wp_get_theme(get_template())->get('Version')))return self::original($mail);
        $context=Source::detect($mail,$trace);unset($trace);if(!$context)return self::original($mail);
        $type=$context['type'];if(($state['value']['types'][$type]['mode']??'native')==='native')return self::original($mail);
        $before=$mail;try{
            $s=Settings::resolve($state['value'],$type);$data=Renderer::prepare_context($type,$s,$context['data']);$html=Renderer::render($type,$s,$data);$mail['subject']=Renderer::subject($s['subject'],$data);$mail['message']=$html;$mail['headers']=self::html_headers($mail['headers']??array(),$s);
        }catch(\Throwable $e){return self::original($before);}return $mail;
    }
    public static function html_headers($headers,array $settings=array()): array
    {
        $reply=(string)($settings['reply_to_email']??'');$useReply=$reply!==''&&is_email($reply)&&!preg_match('/[\s,;<>]/u',$reply);$name=(string)($settings['reply_to_name']??'');
        if(preg_match('/[\x00-\x1F\x7F<>,;\x22]/',$name))$useReply=false;
        $rows=is_array($headers)?$headers:preg_split('/\r?\n/',(string)$headers);$out=array();foreach($rows as $line)if(is_string($line)&&trim($line)!==''&&!preg_match('/^\s*Content-Type\s*:/i',$line)&&!($useReply&&preg_match('/^\s*Reply-To\s*:/i',$line)))$out[]=$line;
        if($useReply)$out[]='Reply-To: '.($name!==''?$name.' <'.$reply.'>':$reply);
        $out[]='Content-Type: text/html; charset=UTF-8';return $out;
    }
    public static function structure_preview(string $type,array $settings,bool $common=false): array
    {
        $context=Catalog::structure($type);$mode=$common?'common':$settings['types'][$type]['mode'];$s=Settings::resolve($settings,$type,$common);if($mode!=='native')$context=Renderer::prepare_context($type,$s,$context);
        if($mode==='native'){
            if(Catalog::all()[$type]['layout']==='code')$context['original_content'].='<p>{{code}}</p><p>有效期：{{expiry}}</p>';
            $mail=array('to'=>'','subject'=>$context['original_subject'],'message'=>$context['original_content'],'headers'=>array());
            $html=function_exists('zib_get_mail_content')?(string)zib_get_mail_content($mail)['message']:'<!doctype html><html lang="zh-CN"><body>'.$context['original_content'].'</body></html>';
        }else $html=Renderer::render($type,$s,$context,false,true);
        return array('html'=>$html,'subject'=>$mode==='native'?$context['original_subject']:Renderer::subject($s['subject'],$context),'mode'=>$mode,'reply_to'=>$mode==='native'?'':$s['reply_to_email'],'structure'=>true);
    }
    public static function preview(string $type,array $settings,bool $common=false): array
    {
        return self::structure_preview($type,$settings,$common);
    }
    public static function test(string $to,string $type,array $settings,bool $common=false): bool
    {
        $preview=self::preview($type,$settings,$common);$native=has_filter('wp_mail','zib_get_mail_content');if($native!==false)remove_filter('wp_mail','zib_get_mail_content',$native);
        $own=has_filter('wp_mail',array(__CLASS__,'filter'));if($own!==false)remove_filter('wp_mail',array(__CLASS__,'filter'),$own);
        $s=Settings::resolve($settings,$type,$common);$headers=self::html_headers(array('X-MaoTK-System-Mail-Test: 1'),$preview['mode']==='native'?array():$s);
        $notice='<p style="margin:0;padding:14px 16px;font-family:Arial,Microsoft YaHei,sans-serif;font-size:12px;line-height:1.7">模板测试邮件：{{变量}} 表示真实发送时的数据位置，这不是实际业务通知。</p>';
        $html=preg_replace_callback('/<body\b[^>]*>/i',static function($m)use($notice){return $m[0].$notice;},$preview['html'],1);
        $subject='[测试邮件] '.Catalog::all()[$type]['label'].' · 模板验证';
        try{return (bool)wp_mail($to,$subject,$html,$headers);}
        finally{if($native!==false)add_filter('wp_mail','zib_get_mail_content',$native);if($own!==false)add_filter('wp_mail',array(__CLASS__,'filter'),$own);}
    }
}
