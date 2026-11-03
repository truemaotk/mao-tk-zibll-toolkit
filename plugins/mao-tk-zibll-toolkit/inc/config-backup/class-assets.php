<?php
namespace MaoTK\ZibllToolkit\ConfigBackup;
defined('ABSPATH') || exit;

final class Assets
{
    public const ONE=5242880;
    public const TOTAL=25165824;
    private static function inside(string $p,string $root): bool{return strpos(strtolower(wp_normalize_path($p)),rtrim(strtolower(wp_normalize_path($root)),'/').'/')===0;}
    public static function inspect(string $bytes): array
    {
        if($bytes===''||strlen($bytes)>self::ONE)throw new \RuntimeException('单张本地图片不得超过 5 MiB。');
        if(preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<svg\b/i',$bytes)){
            $safe=\MaoTK\ZibllToolkit\Showcase\Media::svg($bytes);if($safe===null)throw new \RuntimeException('SVG 图标未通过纯图形安全校验。');return array('bytes'=>$safe,'mime'=>'image/svg+xml','ext'=>'svg','width'=>0,'height'=>0);
        }
        $i=@getimagesizefromstring($bytes);$mimes=array('image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif');
        if(!$i||!isset($mimes[$i['mime']??''])||$i[0]*$i[1]>100000000||max($i[0],$i[1])>50000)throw new \RuntimeException('本地图片内容或尺寸不受支持。');
        return array('bytes'=>$bytes,'mime'=>$i['mime'],'ext'=>$mimes[$i['mime']],'width'=>$i[0],'height'=>$i[1]);
    }
    private static function add(array &$assets,string $file,string $url,int $id,bool $embed): void
    {
        $up=wp_upload_dir();$root=realpath($up['basedir']);$path=realpath($file);
        if(!$root||!$path||is_link($file)||!self::inside($path,$root)||!is_file($path)||filesize($path)>self::ONE)throw new \RuntimeException('本地配置图片缺失、越界或超过大小限制。');
        $a=self::inspect((string)file_get_contents($path));$hash=hash('sha256',$a['bytes']);
        if(!isset($assets[$hash]))$assets[$hash]=array('mime'=>$a['mime'],'ext'=>$a['ext'],'sha256'=>$hash,'ids'=>array(),'urls'=>array(),'title'=>$id?sanitize_text_field(get_the_title($id)):'Toolkit 配置图片','alt'=>$id?sanitize_text_field((string)get_post_meta($id,'_wp_attachment_image_alt',true)):'','data'=>$embed?base64_encode($a['bytes']):null,'size'=>strlen($a['bytes']));
        if($id&&!in_array($id,$assets[$hash]['ids'],true))$assets[$hash]['ids'][]=$id;if(!in_array($url,$assets[$hash]['urls'],true))$assets[$hash]['urls'][]=$url;
    }
    public static function collect(array $sections,bool $embed): array
    {
        $assets=array();$up=wp_upload_dir();$base=rtrim($up['baseurl'],'/').'/';
        $walk=function($value,$key='')use(&$walk,&$assets,$embed,$up,$base){
            if(is_array($value)){foreach($value as $k=>$v){if(in_array($key,array('avatar_media','cover_media'),true)&&is_int($v)){$url=wp_get_attachment_url($v);$file=get_attached_file($v);if(!$url||!$file)throw new \RuntimeException('预设图片记录不存在，请先修正配置。');self::add($assets,$file,$url,$v,$embed);}else $walk($v,(string)$k);}return;}
            if(in_array($key,array('imageId','buttonOneIconId','buttonTwoIconId'),true)&&is_int($value)&&$value>0){$file=get_attached_file($value);$url=wp_get_attachment_url($value);if(!$file||!$url)throw new \RuntimeException('案例图片或图标记录不存在，请先修正配置。');self::add($assets,$file,$url,$value,$embed);}
            if(is_string($value)&&strpos($value,$base)===0){$relative=rawurldecode(substr((string)wp_parse_url($value,PHP_URL_PATH),strlen((string)wp_parse_url($base,PHP_URL_PATH))));if(preg_match('/\.(png|jpe?g|webp|gif|svg)$/i',$relative))self::add($assets,rtrim($up['basedir'],'/').'/'.$relative,$value,0,$embed);}
        };$walk($sections);if(count($assets)>400||array_sum(array_column($assets,'size'))>self::TOTAL)throw new \RuntimeException('本地配置图片合计超过 24 MiB 或 400 张，请分分类导出。');return $assets;
    }
    public static function validate($assets): array
    {
        if(!is_array($assets)||count($assets)>400)throw new \RuntimeException('图片清单无效。');$total=0;$seenIds=array();$seenUrls=array();
        foreach($assets as $hash=>$a){
            if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!is_array($a)||($a['sha256']??'')!==$hash||!is_array($a['ids']??null)||!is_array($a['urls']??null)||count($a['ids'])>1000||count($a['urls'])>1000||!is_int($a['size']??null)||$a['size']<1||$a['size']>self::ONE)throw new \RuntimeException('图片描述格式无效。');
            foreach($a['ids'] as $id)if(!is_int($id)||$id<1)throw new \RuntimeException('图片引用编号无效。');
            foreach($a['urls'] as $url)if(!is_string($url)||strlen($url)>2048||!\MaoTK\ZibllToolkit\SystemMail\Settings::url($url))throw new \RuntimeException('图片来源地址无效。');
            foreach($a['ids'] as $id){if(isset($seenIds[$id])&&$seenIds[$id]!==$hash)throw new \RuntimeException('图片编号指向多个不同文件。');$seenIds[$id]=$hash;}
            foreach($a['urls'] as $url){if(isset($seenUrls[$url])&&$seenUrls[$url]!==$hash)throw new \RuntimeException('图片地址指向多个不同文件。');$seenUrls[$url]=$hash;}
            foreach(array('title','alt') as $key)if(!is_string($a[$key]??null)||strlen($a[$key])>2048)throw new \RuntimeException('图片说明无效。');
            if(!array_key_exists('data',$a)||(!is_null($a['data'])&&!is_string($a['data'])))throw new \RuntimeException('图片数据无效。');
            if($a['data']!==null){$bytes=base64_decode($a['data'],true);if($bytes===false||strlen($bytes)!==$a['size']||!hash_equals($hash,hash('sha256',$bytes)))throw new \RuntimeException('图片数据损坏。');$i=self::inspect($bytes);if($i['mime']!==($a['mime']??'')||$i['ext']!==($a['ext']??''))throw new \RuntimeException('图片类型与内容不一致。');}
            elseif(!in_array(($a['mime']??''),array('image/png','image/jpeg','image/webp','image/gif','image/svg+xml'),true)||!in_array($a['ext']??'',array('png','jpg','webp','gif','svg'),true))throw new \RuntimeException('图片类型无效。');
            $total+=$a['size'];
        }if($total>self::TOTAL)throw new \RuntimeException('图片数据总量超出限制。');return $assets;
    }
    private static function find(array $a): int
    {
        $ids=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','fields'=>'ids','posts_per_page'=>5,'meta_key'=>'_mao_tk_config_asset_sha256','meta_value'=>$a['sha256']));
        foreach($a['urls'] as $url){$id=attachment_url_to_postid($url);if($id)$ids[]=$id;}
        $up=wp_upload_dir();$root=realpath($up['basedir']);foreach(array_unique($ids) as $id){$file=get_attached_file($id);$real=$file?realpath($file):false;if($root&&$real&&self::inside($real,$root)&&is_file($real)&&get_post_mime_type($id)===$a['mime']&&hash_equals($a['sha256'],hash_file('sha256',$real)))return (int)$id;}return 0;
    }
    private static function refs(array $value,array $assets): array
    {
        $ids=array();$urls=array();$walk=function($v,$key='')use(&$walk,&$ids,&$urls){if(is_array($v)){foreach($v as $k=>$x){if(in_array($key,array('avatar_media','cover_media'),true))$ids[]=$x;else $walk($x,(string)$k);}return;}if(is_int($v)&&$v>0&&in_array($key,array('imageId','buttonOneIconId','buttonTwoIconId'),true))$ids[]=$v;if(is_string($v))$urls[]=$v;};$walk($value);
        $needed=array();foreach($assets as $sha=>$a)if(array_intersect($ids,$a['ids'])||array_intersect($urls,$a['urls']))$needed[$sha]=$a;
        $known=array();foreach($needed as $a)$known=array_merge($known,$a['ids']);if(array_diff($ids,$known))throw new \RuntimeException('图片编号缺少来源映射，不能直接沿用旧站编号。');return $needed;
    }
    public static function check(array $section,array $assets): void
    {
        foreach(self::refs($section,$assets) as $a)if($a['data']===null&&!self::find($a))throw new \RuntimeException('缺少本地图片，目标站也没有匹配文件。请重新导出并包含本地图片。');
    }
    public static function restore(array $sections,array $assets,string $source_plugin,array &$created): array
    {
        $needed=self::refs($sections,$assets);$idmap=array();$urlmap=array();
        foreach($needed as $a){$id=self::find($a);if(!$id){if($a['data']===null)throw new \RuntimeException('图片未包含在备份内。');$i=self::inspect(base64_decode($a['data'],true));$name='maotk-config-'.substr($a['sha256'],0,20).'.'.$i['ext'];$allow=static function($m)use($i){$m[$i['ext']]=$i['mime'];return $m;};add_filter('upload_mimes',$allow);
                try{$up=wp_upload_bits($name,null,$i['bytes']);}finally{remove_filter('upload_mimes',$allow);}if(!empty($up['error']))throw new \RuntimeException('配置图片无法写入媒体目录。');
                $id=wp_insert_attachment(array('post_title'=>sanitize_text_field($a['title']),'post_mime_type'=>$i['mime'],'post_status'=>'inherit'),$up['file'],0,true);if(is_wp_error($id)){wp_delete_file($up['file']);throw new \RuntimeException('配置图片媒体记录写入失败。');}
                $created[]=$id;update_post_meta($id,'_mao_tk_config_asset_sha256',hash('sha256',$i['bytes']));update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field($a['alt']));
                if($i['width'])wp_update_attachment_metadata($id,array('width'=>$i['width'],'height'=>$i['height'],'file'=>_wp_relative_upload_path($up['file']),'sizes'=>array()));
            }$url=wp_get_attachment_url($id);foreach($a['ids'] as $old)$idmap[$old]=$id;foreach($a['urls'] as $old)$urlmap[$old]=$url;
        }
        $walk=function($v,$key='')use(&$walk,$idmap,$urlmap,$source_plugin){
            if(is_array($v)){foreach($v as $k=>$x){if(in_array($key,array('avatar_media','cover_media'),true)){if(!isset($idmap[$x]))throw new \RuntimeException('预设图片无法映射。');$v[$k]=$idmap[$x];}else $v[$k]=$walk($x,(string)$k);}return $v;}
            if(is_int($v)&&$v>0&&in_array($key,array('imageId','buttonOneIconId','buttonTwoIconId'),true)){if(!isset($idmap[$v]))throw new \RuntimeException('图片编号无法映射。');return $idmap[$v];}
            if(is_string($v)){if(isset($urlmap[$v]))return $urlmap[$v];if($source_plugin!==''&&strpos($v,$source_plugin)===0){$tail=substr($v,strlen($source_plugin));$file=realpath(MAO_TK_ZIBLL_TOOLKIT_DIR.rawurldecode((string)wp_parse_url($tail,PHP_URL_PATH)));if($file&&self::inside($file,realpath(MAO_TK_ZIBLL_TOOLKIT_DIR))&&preg_match('/\.(svg|png|jpe?g|webp|gif)$/i',$file))return MAO_TK_ZIBLL_TOOLKIT_URL.$tail;}}return $v;
        };return $walk($sections);
    }
}
