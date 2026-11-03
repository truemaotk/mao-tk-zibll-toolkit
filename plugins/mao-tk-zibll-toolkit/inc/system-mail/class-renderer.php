<?php
namespace MaoTK\ZibllToolkit\SystemMail;
defined('ABSPATH') || exit;

final class Renderer
{
    private static function cny_context(array $context): bool
    {
        $mark=trim((string)($context['currency_mark']??''));$unit=trim((string)($context['currency_unit']??''));
        if($mark!==''&&!in_array(strtoupper($mark),array('￥','¥','CNY','RMB','人民币'),true))return false;
        return $unit===''||in_array(strtoupper($unit),array('元','人民币','CNY','RMB'),true);
    }
    public static function amount(string $value,array $settings,array $context=array()): string
    {
        if(($settings['currency_display']??'native')!=='cny'||!self::cny_context($context))return $value;
        $n='[+-]?(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]+)?';
        if(preg_match('/^\s*(?:(?:CNY|RMB|人民币|￥|¥)\s*)?('.$n.')\s*(?:元|人民币)?\s*$/uiD',$value,$m))return 'CNY '.$m[1];
        return $value;
    }
    private static function currency_html(string $html): string
    {
        $parts=wp_html_split($html);$protected=0;$n='[+-]?(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]+)?';
        foreach($parts as &$part){
            if(isset($part[0])&&$part[0]==='<'){
                if(preg_match('/^<\s*(pre|code)\b/i',$part))++$protected;
                if(preg_match('/^<\s*\/\s*(pre|code)\b/i',$part))$protected=max(0,$protected-1);
                continue;
            }
            if($protected||strpos($part,'://')!==false||strpos($part,'{{')!==false)continue;
            if(in_array(trim($part),array('￥','¥'),true)){$part='CNY ';continue;}
            $part=preg_replace('/(?<![A-Za-z])(?:CNY|RMB|人民币|￥|¥)\s*('.$n.')\s*(?:元|人民币)?(?![A-Za-z0-9])/ui','CNY $1',$part);
            $part=preg_replace('/(?<![A-Za-z0-9])('.$n.')\s*元/u','CNY $1',$part);
        }unset($part);return implode('',$parts);
    }
    public static function prepare_context(string $type,array $s,array $context): array
    {
        $context['support_email']=(string)($s['reply_to_email']??'');$context['support_name']=(string)($s['reply_to_name']??'');
        if(isset($context['amount']))$context['amount']=self::amount((string)$context['amount'],$s,$context);
        if(($s['currency_display']??'native')==='cny'&&(Catalog::all()[$type]['layout']??'')==='order'&&$type!=='virtual_delivery'&&self::cny_context($context))$context['original_content']=self::currency_html((string)($context['original_content']??''));
        return $context;
    }
    public static function text(string $template,array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/',static function($m)use($context){return wp_strip_all_tags((string)($context[$m[1]]??''));},$template);
    }
    public static function subject(string $template,array $context): string
    {
        return trim(preg_replace('/[\r\n]+/',' ',self::text($template,$context)));
    }
    private static function replace_action(array $context,array $s): array
    {
        // Replace only the native primary action that will be rendered below; other links remain.
        $url=(string)($context['action_url']??'');
        if(!Settings::url($url)||self::text($s['button_label'],$context)===''||!class_exists('WP_HTML_Tag_Processor'))return $context;
        $context['original_content']=preg_replace_callback('~<a\b[^>]*>.*?</a>~is',static function($m)use($url){
            $p=new \WP_HTML_Tag_Processor($m[0]);if(!$p->next_tag('A'))return $m[0];
            return preg_match('/\bbut\b/',(string)$p->get_attribute('class'))&&html_entity_decode((string)$p->get_attribute('href'),ENT_QUOTES,'UTF-8')===$url?'':$m[0];
        },(string)($context['original_content']??''));
        return $context;
    }
    public static function html_content(string $html,array $s): string
    {
        $allowed=wp_kses_allowed_html('post');foreach(array('script','style','iframe','form','input','button','object','video','audio','svg') as $tag)unset($allowed[$tag]);
        $html=wp_kses($html,$allowed);
        if(!preg_match('/<(p|br|div|table|ul|ol|pre|h[1-6])\b/i',$html))$html=nl2br($html);
        if(!class_exists('WP_HTML_Tag_Processor'))return $html;
        $p=new \WP_HTML_Tag_Processor($html);
        while($p->next_tag()){
            $tag=$p->get_tag();$style=(string)$p->get_attribute('style');
            if($tag==='A')$style.=';color:'.$s['accent'].';text-decoration:underline;overflow-wrap:anywhere;word-break:break-word;';
            if(in_array($tag,array('P','DIV','LI'),true))$style.=';line-height:1.7;overflow-wrap:anywhere;';
            if($tag==='P')$style.=';margin:0 0 '.$s['section_gap'].'px;';
            if(in_array($tag,array('H1','H2','H3','H4','H5','H6'),true))$style.=';margin:0 0 12px;font-size:'.$s['body_size'].'px;font-weight:600;line-height:1.6;color:'.$s['text'].';';
            if($tag==='IMG')$style.=';max-width:100%;height:auto;border:0;';
            if($tag==='TABLE')$style.=';max-width:100%;border-collapse:collapse;';
            if($tag==='PRE')$style.=';white-space:pre-wrap;word-break:break-word;';
            if($style!=='')$p->set_attribute('style',$style);
        }return $p->get_updated_html();
    }
    private static function body(string $template,array $context,array $s): string
    {
        $segments=preg_split('/(\{\{\s*original_content\s*\}\})/',$template,-1,PREG_SPLIT_DELIM_CAPTURE);$out='';$included=false;
        foreach($segments as $part){if(preg_match('/^\{\{\s*original_content\s*\}\}$/D',$part)){$out.=self::html_content((string)$context['original_content'],$s);$included=true;}elseif(trim($part)!=='')$out.='<p style="margin:0 0 '.$s['section_gap'].'px;line-height:1.7">'.nl2br(esc_html(self::text($part,$context))).'</p>';}
        // Never let a custom greeting accidentally remove reset links, logistics or delivery payloads.
        if(!$included&&trim((string)($context['original_content']??''))!=='')$out.='<div style="margin-top:'.$s['section_gap'].'px">'.self::html_content($context['original_content'],$s).'</div>';
        // Avoid doubling the final paragraph gap with the next section's padding.
        if(class_exists('WP_HTML_Tag_Processor')){
            $p=new \WP_HTML_Tag_Processor($out);$found=false;
            while($p->next_tag('P')){$p->set_bookmark('last-paragraph');$found=true;}
            if($found&&$p->seek('last-paragraph'))$p->set_attribute('style',(string)$p->get_attribute('style').';margin-bottom:0;');
            $out=$p->get_updated_html();
        }
        return $out;
    }
    /** Derive quiet supporting tones from configured colours, including dark surfaces. */
    private static function mix(string $front,string $back,float $weight): string
    {
        $out='#';for($i=1;$i<7;$i+=2)$out.=str_pad(dechex((int)round(hexdec(substr($front,$i,2))*$weight+hexdec(substr($back,$i,2))*(1-$weight))),2,'0',STR_PAD_LEFT);return strtoupper($out);
    }
    private static function profile(string $type): array
    {
        $profiles=array(
            'captcha'=>array('label'=>'邮箱验证','focus'=>'code'),
            'reset_captcha'=>array('label'=>'密码安全验证','focus'=>'code'),
            'welcome'=>array('label'=>'账号通知','focus'=>'account'),
            'reset_link'=>array('label'=>'密码重置','focus'=>'security'),
            'payment_customer'=>array('label'=>'付款通知','focus'=>'amount'),
            'shipping_seller'=>array('label'=>'发货待办','focus'=>'status'),
            'shipping_customer'=>array('label'=>'配送通知','focus'=>'reference'),
            'virtual_delivery'=>array('label'=>'数字商品交付','focus'=>'delivery'),
            'aftersale_customer'=>array('label'=>'售后通知','focus'=>'status'),
        );return $profiles[$type]??array('label'=>'系统通知','focus'=>'account');
    }
    private static function row(string $label,string $value,array $s,string $muted,bool $last): string
    {
        $line=$last?'':'border-bottom:1px solid '.esc_attr($s['border']).';';
        return '<tr><td width="84" valign="top" style="width:84px;padding:12px 0;'.$line.'font-size:13px;color:'.$muted.'">'.esc_html($label).'</td><td valign="top" style="padding:12px 0 12px 12px;'.$line.'font-size:'.$s['body_size'].'px;color:'.esc_attr($s['text']).';word-break:break-all;overflow-wrap:anywhere">'.esc_html($value).'</td></tr>';
    }
    private static function highlight(array $profile,array $s,array $c,string $muted,string $tint): string
    {
        $accent=esc_attr($s['accent']);$border=esc_attr($s['border']);$text=esc_attr($s['text']);$focus=$profile['focus'];$inside='';
        if($focus==='code'){
            $code=(string)($c['code']??'');$long=strlen($code)>8;
            $inside='<tr><td align="center" style="padding:20px 12px 8px;font-size:12px;color:'.$muted.'">本次验证码</td></tr><tr><td align="center" style="padding:0 12px 10px;font-family:Arial,sans-serif;font-size:'.($long?26:36).'px;font-weight:600;letter-spacing:'.($long?2:5).'px;color:'.$accent.';line-height:1.4;word-break:break-all;overflow-wrap:anywhere;-webkit-user-select:all;user-select:all">'.esc_html($code).'</td></tr><tr><td align="center" style="padding:0 12px 20px;font-size:13px;color:'.$text.'">有效期：'.esc_html((string)($c['expiry']??'')).'</td></tr>';
        }elseif(in_array($focus,array('account','security'),true)){
            $login=(string)($c['user_login']??'');if($login==='')return '';
            $inside='<tr><td style="padding:16px 18px;font-size:12px;color:'.$muted.'">'.($focus==='security'?'操作账号':'您的账号').'<br><span style="display:inline-block;padding-top:4px;font-size:'.$s['body_size'].'px;font-weight:600;color:'.$text.';word-break:break-all;overflow-wrap:anywhere">'.esc_html($login).'</span></td></tr>';
        }else{
            $key=$focus==='amount'?'amount':($focus==='status'?'status':'order_number');$value=(string)($c[$key]??'');
            if($value!==''){
                $label=$key==='amount'?'订单金额':($key==='status'?'通知状态':'订单号');
                $size=$focus==='amount'?max(24,$s['heading_size']):$s['body_size'];
                $inside='<tr><td style="padding:18px 18px 16px;font-size:12px;color:'.$muted.'">'.esc_html($label).'<br><span style="display:inline-block;padding-top:5px;font-size:'.$size.'px;font-weight:600;line-height:1.5;color:'.($focus==='amount'?$accent:$text).';word-break:break-all;overflow-wrap:anywhere">'.esc_html($value).'</span></td></tr>';
            }
        }
        if($inside==='')return '';
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="'.$tint.'" style="width:100%;border-collapse:separate;border:1px solid '.$border.';border-radius:'.min(10,$s['radius']).'px;background:'.$tint.'">'.$inside.'</table>';
    }
    // The fourth argument is retained for older callers; fabricated sample banners are retired.
    public static function render(string $type,array $s,array $context,bool $sample=false,bool $structure=false): string
    {
        $context=self::prepare_context($type,$s,$context);
        if($structure&&($s['currency_display']??'native')==='cny'&&($context['amount']??'')==='{{amount}}')$context['amount']='CNY {{amount}}';
        $context=self::replace_action($context,$s);$profile=self::profile($type);
        $accent=esc_attr($s['accent']);$border=esc_attr($s['border']);$text=esc_attr($s['text']);$surface=esc_attr($s['surface']);
        $muted=self::mix($s['text'],$s['surface'],.68);$tint=self::mix($s['accent'],$s['surface'],.035);
        $subject=self::subject($s['subject'],$context);$heading=self::text($s['heading'],$context);$gap=$s['section_gap'];$pad=$s['padding'];
        $font='-apple-system,BlinkMacSystemFont,Segoe UI,Microsoft YaHei,PingFang SC,Arial,sans-serif';
        $style='font-family:'.$font.';color:'.$text.';font-size:'.$s['body_size'].'px;line-height:1.7;';
        $logo=$s['logo_url']!==''?'<img src="'.esc_url($s['logo_url']).'" alt="'.esc_attr($context['site_name']).'" width="'.$s['logo_width'].'" style="display:inline-block;width:'.$s['logo_width'].'px;max-width:100%;height:auto;border:0;vertical-align:middle">':'<span style="font-size:17px;font-weight:600;color:'.$text.';word-break:break-word;overflow-wrap:anywhere">'.esc_html($context['site_name']).'</span>';
        if(Settings::url((string)$context['site_url']))$logo='<a href="'.esc_url($context['site_url']).'" style="display:inline-block;text-decoration:none;color:'.$text.'">'.$logo.'</a>';
        $brand='<tr><td align="'.esc_attr($s['logo_position']).'" style="padding:0 4px '.$gap.'px">'.$logo.'</td></tr>';
        $main='<tr><td style="padding:'.$pad.'px '.$pad.'px 8px;font-size:12px;font-weight:600;color:'.$accent.'">'.esc_html($profile['label']).'</td></tr>';
        $main.='<tr><td style="padding:0 '.$pad.'px '.$gap.'px"><h1 style="margin:0;font-size:'.$s['heading_size'].'px;line-height:1.45;font-weight:600;color:'.$text.';word-break:break-word;overflow-wrap:anywhere">'.esc_html($heading).'</h1></td></tr>';
        $content=self::body($s['body'],$context,$s);
        if($profile['focus']==='code')$main.='<tr><td style="padding:0 '.$pad.'px '.$gap.'px;word-break:break-word;overflow-wrap:anywhere">'.$content.'</td></tr>';
        $highlight=self::highlight($profile,$s,$context,$muted,$tint);
        if($highlight!=='')$main.='<tr><td style="padding:0 '.$pad.'px '.($profile['focus']==='code'?0:$gap).'px">'.$highlight.'</td></tr>';
        if($profile['focus']==='code')$main.='<tr><td style="padding:12px '.$pad.'px 0;font-size:12px;color:'.$muted.'">选中或长按验证码复制，返回当前操作页面使用。</td></tr>';
        if((Catalog::all()[$type]['layout']??'welcome')==='order'){
            $focus=$profile['focus'];$skip=$focus==='amount'?'amount':($focus==='status'?'status':'order_number');$fields=array('order_number'=>'订单号','amount'=>'订单金额','status'=>'通知状态','product_name'=>'商品');$values=array();
            foreach($fields as $key=>$label)if($key!==$skip&&isset($context[$key])&&(string)$context[$key]!=='')$values[]=array($label,(string)$context[$key]);
            $rows='';foreach($values as $i=>$value)$rows.=self::row($value[0],$value[1],$s,$muted,$i===count($values)-1);
            if($rows!=='')$main.='<tr><td style="padding:0 '.$pad.'px '.$gap.'px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;'.$style.'">'.$rows.'</table></td></tr>';
        }
        if($profile['focus']==='delivery')$content='<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse"><tr><td style="border-left:2px solid '.$accent.';padding:0 0 0 16px;'.$style.'">'.$content.'</td></tr></table>';
        if($profile['focus']!=='code')$main.='<tr><td style="padding:0 '.$pad.'px;word-break:break-word;overflow-wrap:anywhere">'.$content.'</td></tr>';
        $button=self::text($s['button_label'],$context);$url=(string)($context['action_url']??'');
        $hasAction=$button!==''&&Settings::url($url);$placeholderAction=$button!==''&&$structure&&in_array('action_url',Catalog::variables($type),true);
        if($hasAction||$placeholderAction){
            $controlStyle='display:block;padding:13px 22px;border-radius:'.$s['button_radius'].'px;font-family:'.$font.';font-size:'.$s['body_size'].'px;font-weight:600;line-height:1.5;text-decoration:none;color:'.esc_attr($s['button_text_color']).';overflow-wrap:anywhere';
            $control=$hasAction?'<a href="'.esc_url($url).'" style="'.$controlStyle.'">'.esc_html($button).'</a>':'<span aria-disabled="true" title="实际发送时填入原业务操作链接" style="'.$controlStyle.'">'.esc_html($button).'</span>';
            $main.='<tr><td style="padding:'.$gap.'px '.$pad.'px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:separate"><tr><td align="center" bgcolor="'.esc_attr($s['button_background']).'" style="border-radius:'.$s['button_radius'].'px;background:'.esc_attr($s['button_background']).';mso-padding-alt:13px 22px">'.$control.'</td></tr></table></td></tr>';
        }
        if($hasAction&&strpos(html_entity_decode(wp_strip_all_tags($content),ENT_QUOTES,'UTF-8'),$url)===false)$main.='<tr><td style="padding:12px '.$pad.'px 0;font-size:12px;line-height:1.6;color:'.$muted.';word-break:break-all">按钮无法打开时，可复制此地址到浏览器：<br><a href="'.esc_url($url).'" style="color:'.$muted.';text-decoration:underline;word-break:break-all">'.esc_html($url).'</a></td></tr>';
        if($placeholderAction&&!$hasAction)$main.='<tr><td style="padding:10px '.$pad.'px 0;font-size:12px;color:'.$muted.'">{{action_url}} · 由原业务系统在发送时生成</td></tr>';
        $main.='<tr><td height="'.$pad.'" style="height:'.$pad.'px;font-size:0;line-height:0">&nbsp;</td></tr>';
        $card='<tr><td><table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="'.$surface.'" style="width:100%;background:'.$surface.';border:1px solid '.$border.';border-radius:'.$s['radius'].'px;border-collapse:separate;'.$style.'">'.$main.'</table></td></tr>';
        $reply=(string)($s['reply_to_email']??'');$replyEnabled=$reply!==''&&is_email($reply)&&!preg_match('/[\s,;<>]/u',$reply);
        $footerTemplate=$s['footer_text'];if($replyEnabled&&$footerTemplate==='此邮件由 {{site_name}} 系统发送，请勿直接回复。')$footerTemplate='此邮件由 {{site_name}} 发送。';
        $footer=nl2br(esc_html(self::text($footerTemplate,$context)));$footerUrl=self::text($s['footer_url'],$context);$footerLabel=self::text($s['footer_label'],$context);
        if($replyEnabled){$mailto='mailto:'.$reply.'?subject='.rawurlencode('Re: '.$subject);$footer.=($footer!==''?'<br>':'').'如需帮助，可直接回复本邮件，或联系 <a href="'.esc_url($mailto).'" style="color:'.$accent.';text-decoration:underline">'.esc_html($reply).'</a>。';}
        if($footerLabel!==''&&Settings::url($footerUrl))$footer.=($footer!==''?'<br>':'').'<a href="'.esc_url($footerUrl).'" style="display:inline-block;padding-top:8px;color:'.$accent.';text-decoration:underline">'.esc_html($footerLabel).'</a>';
        $footerMuted=self::mix($s['text'],$s['background'],.7);
        $tail=$footer===''?'':'<tr><td style="padding:'.$gap.'px 4px 0;font-size:12px;line-height:1.7;color:'.$footerMuted.';word-break:break-word;overflow-wrap:anywhere">'.$footer.'</td></tr>';
        return '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($subject).'</title></head><body data-maotk-system-mail="1" data-mail-design="'.esc_attr($type).'" style="margin:0;padding:0;background:'.esc_attr($s['background']).';'.$style.'"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="'.esc_attr($s['background']).'" style="width:100%;border-collapse:collapse"><tr><td align="center" style="padding:'.$s['space_top'].'px 12px '.$s['space_bottom'].'px"><!--[if mso]><table role="presentation" width="'.$s['width'].'" cellspacing="0" cellpadding="0"><tr><td><![endif]--><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:'.$s['width'].'px;border-collapse:separate;'.$style.'">'.$brand.$card.$tail.'</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }
}
