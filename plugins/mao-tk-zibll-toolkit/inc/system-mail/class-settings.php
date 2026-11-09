<?php
namespace MaoTK\ZibllToolkit\SystemMail;
defined('ABSPATH') || exit;

final class Settings
{
    public const KEY='mao_tk_toolkit_system_mail';
    private static function deployment_defaults(): array
    {
        $host=strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));
        return in_array($host,array('maotk.com','www.maotk.com'),true)?array('reply_to_email'=>'support@maotk.com','reply_to_name'=>'Mao TK 客服','currency_display'=>'cny'):array();
    }
    public static function fields(): array
    {
        $fields=array(
            'logo_url'=>array('label'=>'邮件专用 Logo','group'=>'brand','type'=>'logo','default'=>''),
            'logo_width'=>array('label'=>'Logo 显示宽度','group'=>'brand','type'=>'number','min'=>24,'max'=>240,'default'=>120),
            'logo_position'=>array('label'=>'Logo 位置','group'=>'brand','type'=>'select','options'=>array('left'=>'左对齐','center'=>'居中','right'=>'右对齐'),'default'=>'left'),
            'accent'=>array('label'=>'主题色','group'=>'colors','type'=>'color','default'=>'#FF8A18'),
            'background'=>array('label'=>'邮件背景','group'=>'colors','type'=>'color','default'=>'#F3F5F7'),
            'surface'=>array('label'=>'内容背景','group'=>'colors','type'=>'color','default'=>'#FFFFFF'),
            'text'=>array('label'=>'正文颜色','group'=>'colors','type'=>'color','default'=>'#354052'),
            'border'=>array('label'=>'边框颜色','group'=>'colors','type'=>'color','default'=>'#E2E6EC'),
            'width'=>array('label'=>'内容最大宽度','group'=>'layout','type'=>'number','min'=>480,'max'=>760,'default'=>640),
            'radius'=>array('label'=>'内容边框圆角','group'=>'layout','type'=>'number','min'=>0,'max'=>24,'default'=>12),
            'heading_size'=>array('label'=>'标题字号','group'=>'layout','type'=>'number','min'=>18,'max'=>36,'default'=>24),
            'body_size'=>array('label'=>'正文字号','group'=>'layout','type'=>'number','min'=>12,'max'=>20,'default'=>15),
            'space_top'=>array('label'=>'外部上方间距','group'=>'layout','type'=>'number','min'=>0,'max'=>80,'default'=>28),
            'space_bottom'=>array('label'=>'外部下方间距','group'=>'layout','type'=>'number','min'=>0,'max'=>80,'default'=>28),
            'padding'=>array('label'=>'内容内边距','group'=>'layout','type'=>'number','min'=>16,'max'=>40,'default'=>24),
            'section_gap'=>array('label'=>'段落与区域间距','group'=>'layout','type'=>'number','min'=>8,'max'=>36,'default'=>18),
            'reply_to_email'=>array('label'=>'客服回复邮箱','group'=>'support','type'=>'email','default'=>'','help'=>'直接回复邮件和页脚客服入口使用此邮箱。留空时保留原邮件 Reply-To；不修改 SMTP 发件配置。'),
            'reply_to_name'=>array('label'=>'客服显示名称','group'=>'support','type'=>'text','default'=>'','help'=>'可填写客服团队名称，不影响真实收件地址。'),
            'currency_display'=>array('label'=>'人民币显示方式','group'=>'layout','type'=>'select','options'=>array('native'=>'保留原币种标识','cny'=>'人民币统一显示 CNY'),'default'=>'native','help'=>'只调整可识别的人民币标识，不换算金额，不修改积分、外币或交付凭据。'),
            'button_background'=>array('label'=>'按钮背景','group'=>'footer','type'=>'color','default'=>'#FF8A18'),
            'button_text_color'=>array('label'=>'按钮文字颜色','group'=>'footer','type'=>'color','default'=>'#FFFFFF'),
            'button_radius'=>array('label'=>'按钮圆角','group'=>'footer','type'=>'number','min'=>0,'max'=>16,'default'=>7),
            'footer_text'=>array('label'=>'页脚文案','group'=>'footer','type'=>'textarea','default'=>'此邮件由 {{site_name}} 发送。'),
            'footer_label'=>array('label'=>'页脚链接文案','group'=>'footer','type'=>'text','default'=>'访问网站'),
            'footer_url'=>array('label'=>'页脚链接地址','group'=>'footer','type'=>'url','default'=>'{{site_url}}'),
            'subject'=>array('label'=>'邮件主题','group'=>'copy','type'=>'text','default'=>'{{original_subject}}'),
            'heading'=>array('label'=>'邮件内标题','group'=>'copy','type'=>'text','default'=>'{{original_subject}}'),
            'body'=>array('label'=>'正文文案','group'=>'copy','type'=>'textarea','default'=>'{{original_content}}'),
            'button_label'=>array('label'=>'操作按钮文案','group'=>'copy','type'=>'text','default'=>'查看详情'),
        );
        foreach(self::deployment_defaults() as $key=>$value)$fields[$key]['default']=$value;
        return $fields;
    }
    public static function defaults(): array
    {
        $common=array();foreach(self::fields() as $key=>$field)$common[$key]=$field['default'];$types=array();foreach(Catalog::all() as $id=>$type)$types[$id]=array('mode'=>'common','overrides'=>array());
        return array('version'=>1,'common'=>$common,'types'=>$types);
    }
    public static function groups(): array
    {
        return array(
            'brand' => __('品牌与邮件 Logo', 'mao-tk-zibll-toolkit'),
            'colors' => __('邮件配色', 'mao-tk-zibll-toolkit'),
            'layout' => __('版式与金额显示', 'mao-tk-zibll-toolkit'),
            'support' => __('客服与回复', 'mao-tk-zibll-toolkit'),
            'footer' => __('按钮与页脚', 'mao-tk-zibll-toolkit'),
            'copy' => __('该类型文案与变量', 'mao-tk-zibll-toolkit'),
        );
    }
    public static function placeholders(string $value,array $allowed): void
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/',$value,$m);foreach($m[1] as $token)if(!in_array($token,$allowed,true))throw new \InvalidArgumentException('该类型不支持变量：'.$token);
        $rest=preg_replace('/\{\{\s*[a-z_]+\s*\}\}/','',$value);if(strpos($rest,'{{')!==false||strpos($rest,'}}')!==false)throw new \InvalidArgumentException('变量格式应为 {{variable_name}}。');
    }
    public static function validate($raw): array
    {
        if(!is_array($raw)||($raw['version']??0)!==1||!is_array($raw['common']??null)||!is_array($raw['types']??null)||array_diff(array_keys($raw),array('version','common','types')))throw new \InvalidArgumentException('邮件设置格式不完整。');
        // Introduce only the optional new fields, preserving every existing value.
        foreach(array('reply_to_email','reply_to_name','currency_display') as $key)if(!array_key_exists($key,$raw['common']))$raw['common'][$key]=self::fields()[$key]['default'];
        $out=array('version'=>1,'common'=>array(),'types'=>array());$fields=self::fields();
        foreach($raw['types'] as $id=>$type)if(!isset(Catalog::all()[$id]))throw new \InvalidArgumentException('未知邮件类型。');
        foreach(array_merge(array('common'),array_keys(Catalog::all())) as $scope){
            if($scope==='common')$values=$raw['common'];else{$t=$raw['types'][$scope]??null;if(!is_array($t)||!in_array($t['mode']??'',array('common','custom','native'),true)||!is_array($t['overrides']??null)||array_diff(array_keys($t),array('mode','overrides')))throw new \InvalidArgumentException('邮件模式或独立设置不完整。');$values=$t['overrides'];$out['types'][$scope]=array('mode'=>$t['mode'],'overrides'=>array());}
            if(array_diff(array_keys($values),array_keys($fields)))throw new \InvalidArgumentException('包含未知邮件配置项。');
            if($scope==='common'&&count($values)!==count($fields))throw new \InvalidArgumentException('通用设置缺少字段。');
            foreach($values as $key=>$value){$field=$fields[$key];$type=$field['type'];
                if($type==='number'){if(!is_int($value)||$value<$field['min']||$value>$field['max'])throw new \InvalidArgumentException($field['label'].'超出允许范围。');}
                elseif(!is_string($value)||strlen($value)>($key==='body'?12000:($key==='footer_text'?2000:2000))||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value))throw new \InvalidArgumentException($field['label'].'文本无效。');
                if($type==='color'){if(!preg_match('/^#[a-fA-F0-9]{6}$/D',$value))throw new \InvalidArgumentException('颜色使用完整 HEX，例如 #FF8A18。');$value=strtoupper($value);}
                if($type==='select'&&!isset($field['options'][$value]))throw new \InvalidArgumentException('位置选项无效。');
                if(is_string($value))self::placeholders($value,Catalog::variables($scope));
                if($key==='reply_to_email'&&$value!==''&&(!is_email($value)||preg_match('/[\s,;<>]/u',$value)))throw new \InvalidArgumentException('客服回复邮箱需为一个有效邮箱地址。');
                if(in_array($key,array('reply_to_email','reply_to_name'),true)&&(strpos($value,'{{')!==false||preg_match('/[\x00-\x1F\x7F<>,;\x22]/',$value)||strlen($value)>254))throw new \InvalidArgumentException('客服回复信息不能包含变量、换行或邮件头分隔符。');
                if(in_array($key,array('subject','heading','button_label','footer_label'),true)&&preg_match('/[\r\n<>]/',$value))throw new \InvalidArgumentException($field['label'].'不能包含换行或 HTML。');
                if($key==='logo_url'&&$value!==''){
                    if(!self::url($value)||!preg_match('/\.png(?:[?#].*)?$/iD',$value))throw new \InvalidArgumentException('邮件 Logo 请选择或填写 PNG 图片地址。');
                    $aid=attachment_url_to_postid($value);if($aid&&get_post_mime_type($aid)!=='image/png')throw new \InvalidArgumentException('Logo 媒体格式不是 PNG。');
                }
                if($key==='footer_url'&&$value!==''&&$value!=='{{site_url}}'&&!self::url($value))throw new \InvalidArgumentException('页脚链接需为完整 HTTP/HTTPS 网址或 {{site_url}}。');
                if($scope==='common')$out['common'][$key]=$value;else $out['types'][$scope]['overrides'][$key]=$value;
            }
        }return $out;
    }
    public static function url(string $url): bool{return !preg_match('/[\r\n\x00]/',$url)&&(bool)filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower((string)wp_parse_url($url,PHP_URL_SCHEME)),array('http','https'),true);}
    public static function read(): array
    {
        $raw=get_option(self::KEY,null);if($raw===null)$raw=self::defaults();try{$value=self::validate($raw);return array('valid'=>true,'value'=>$value,'revision'=>hash('sha256',wp_json_encode($raw)));}catch(\Throwable $e){return array('valid'=>false,'value'=>self::defaults(),'revision'=>'invalid');}
    }
    public static function save(array $value,string $revision): array
    {
        $state=self::read();if(!$state['valid']||!hash_equals($state['revision'],$revision))throw new \RuntimeException('设置已被其他页面修改或格式异常，请刷新后核对。');
        $value=self::validate($value);update_option(self::KEY,$value,false);if(get_option(self::KEY)!==$value)throw new \RuntimeException('邮件设置保存失败。');return self::read();
    }
    public static function resolve(array $state,string $type,bool $common_preview=false): array
    {
        $t=$state['types'][$type]??array('mode'=>'common','overrides'=>array());return !$common_preview&&$t['mode']==='custom'?array_replace($state['common'],$t['overrides']):$state['common'];
    }
}
