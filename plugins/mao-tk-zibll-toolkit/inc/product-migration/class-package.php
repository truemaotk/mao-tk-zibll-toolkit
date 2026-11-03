<?php
namespace MaoTK\ZibllToolkit\ProductMigration;

use MaoTK\ZibllToolkit\Migration\Package as LinkPackage;
defined('ABSPATH') || exit;

final class Package
{
    public const MAX_POSTS = 100;
    public const MAX_ZIP = 268435456;
    public const MAX_EXPANDED = 536870912;
    public const MAX_JSON = 16777216;
    public const MAX_IMAGE = 8388608;

    public static function text($value, int $max = 10000): string { return LinkPackage::text($value, $max); }
    public static function url($value): string { return LinkPackage::url($value); }
    public static function media_url($value): string
    {
        if(is_string($value) && strpos($value,'/')===0 && strpos($value,'//')!==0 && !preg_match('/[\x00-\x20<>"\\\\]/',$value))return self::text($value,8192);
        return self::url($value);
    }
    public static function allowed_meta(string $key): bool
    {
        return in_array($key,array('rank_math_title','rank_math_description','rank_math_focus_keyword','rank_math_robots','rank_math_canonical_url','rank_math_facebook_title','rank_math_facebook_description','rank_math_facebook_image','rank_math_twitter_title','rank_math_twitter_description','rank_math_twitter_image'),true);
    }
    public static function value($value, int $depth = 0)
    {
        if ($depth > 16 || is_object($value) || is_resource($value)) { throw new \RuntimeException('扩展字段包含对象或嵌套过深，不能安全迁移。'); }
        if (is_array($value)) {
            if (count($value) > 5000) { throw new \RuntimeException('扩展字段数组过大。'); }
            foreach ($value as $k => $v) { $value[$k] = self::value($v, $depth + 1); }
        } elseif (is_string($value) && strlen($value) > 2097152) { throw new \RuntimeException('单个扩展字段超过 2 MiB。'); }
        return $value;
    }
    public static function image(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE) { throw new \RuntimeException('图片为空或超过 8 MiB。'); }
        $size = @getimagesizefromstring($bytes);
        $types = array('image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif','image/avif'=>'avif','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico');
        if (!$size || !isset($types[$size['mime']]) || $size[0] > 12000 || $size[1] > 12000 || $size[0]*$size[1] > 40000000) { throw new \RuntimeException('图片格式不支持或像素过大。SVG 和可执行文件不导入。'); }
        return array('sha256'=>hash('sha256',$bytes),'ext'=>$types[$size['mime']],'mime'=>$size['mime'],'bytes'=>strlen($bytes));
    }
    public static function post(array $p): array
    {
        $out = array();
        foreach (array('key'=>200,'title'=>10000,'content'=>2097152,'excerpt'=>2097152,'slug'=>200,'date'=>30,'date_gmt'=>30,'modified'=>30,'modified_gmt'=>30,'status'=>20,'author'=>60,'comment'=>10,'ping'=>10,'password'=>255,'permalink'=>8192,'thumbnail'=>240) as $key=>$max) {
            $out[$key] = self::text($p[$key] ?? '', $max);
        }
        if ($out['key'] === '' || !in_array($out['status'],array('publish','draft','pending','private','future'),true)) { throw new \RuntimeException('商品标识或状态无效。'); }
        foreach (array('comment','ping') as $f) { if (!in_array($out[$f],array('open','closed'),true)) { throw new \RuntimeException('评论或引用状态无效。'); } }
        foreach (array('date','date_gmt','modified','modified_gmt') as $f) { if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$out[$f])) { throw new \RuntimeException('商品日期格式无效。'); } }
        if ($out['permalink'] !== '') { self::url($out['permalink']); }
        $out['terms'] = $p['terms'] ?? array(); $out['meta'] = $p['meta'] ?? array(); $out['assets'] = $p['assets'] ?? array();
        if (!is_array($out['terms']) || !is_array($out['meta']) || !is_array($out['assets']) || count($out['meta'])>500 || count($out['terms'])>200 || count($out['assets'])>1000) { throw new \RuntimeException('商品分类、图片或扩展字段格式无效。'); }
        foreach ($out['meta'] as $key=>$values) {
            if (!is_string($key) || !self::allowed_meta($key) || !is_array($values) || count($values)>100) { throw new \RuntimeException('扩展字段名称或取值无效。'); }
            self::value($values);
        }
        $out['config']=Schema::config($p['config']??null); $out['sticky']=false;
        $out['format'] = self::text($p['format'] ?? '',30);
        if (!in_array($out['format'],array('','aside','chat','gallery','link','image','quote','status','video','audio'),true)) { throw new \RuntimeException('商品形式无效。'); }
        return $out;
    }
    public static function read(string $file, string $dir, string $legacy_source = ''): array
    {
        if (!class_exists('ZipArchive') || !is_file($file) || filesize($file)<22 || filesize($file)>self::MAX_ZIP) { throw new \RuntimeException('ZIP 不可用、为空或超过 256 MiB。'); }
        $zip = new \ZipArchive(); if ($zip->open($file,\ZipArchive::CHECKCONS)!==true) { throw new \RuntimeException('ZIP 损坏。'); }
        try {
            if ($zip->numFiles>4001) { throw new \RuntimeException('包内文件过多。'); }
            $seen=array(); $total=0;
            for ($i=0;$i<$zip->numFiles;$i++) {
                $s=$zip->statIndex($i); $name=$s['name'];
                if (substr($name,-1)==='/' && preg_match('#^(images|media)/#',$name) && LinkPackage::path(rtrim($name,'/'))) { continue; }
                if (!LinkPackage::path($name) || isset($seen[strtolower($name)]) || ($name!=='manifest.json' && !preg_match('#^(images|media)/.+\.(png|jpe?g|webp|gif|avif|ico)$#iD',$name))) { throw new \RuntimeException('包内含非法、重复或不支持的文件路径。'); }
                $opsys=0;$attr=0;$zip->getExternalAttributesIndex($i,$opsys,$attr);
                if ((($attr>>16)&0170000)===0120000 || !empty($s['encryption_method'])) { throw new \RuntimeException('不接受加密包或符号链接。'); }
                $total+=(int)$s['size']; if ($total>self::MAX_EXPANDED || $s['size']>($name==='manifest.json'?self::MAX_JSON:self::MAX_IMAGE)) { throw new \RuntimeException('展开内容超过限制。'); }
                $seen[strtolower($name)]=true;
            }
            $raw=$zip->getFromName('manifest.json');$m=is_string($raw)?json_decode($raw,true,64):null;
            if (!is_array($m) || json_last_error()!==JSON_ERROR_NONE) { throw new \RuntimeException('manifest.json 缺失或无效。'); }
            $legacy=false;
            if (($m['format']??'')!=='maotk-zibll-products' || ($m['version']??0)!==1 || ($m['contract']??'')!==Schema::CONTRACT || !is_array($m['products']??null) || !is_array($m['terms']??null) || !is_array($m['assets']??null) || !is_array($m['urls']??null) || !is_array($m['ids']??null)) { throw new \RuntimeException('不是支持的商品迁移格式。'); }
            $source=trailingslashit(self::url($m['source']??''));
            if (!$m['products'] || count($m['products'])>self::MAX_POSTS || count($m['assets'])>2000 || count($m['terms'])>2000 || count($m['urls'])>20000 || count($m['ids'])>5000) { throw new \RuntimeException('迁移范围过大，请每包最多 100 件商品。'); }
            $terms=array();
            foreach ($m['terms'] as $t) {
                if (!is_array($t)) { throw new \RuntimeException('分类标签无效。'); }
                foreach (array('key'=>200,'tax'=>20,'name'=>800,'slug'=>200,'description'=>10000,'parent'=>200) as $k=>$limit) { $t[$k]=self::text($t[$k]??'',$limit); }
                if ($t['key']==='' || $t['name']==='' || !in_array($t['tax'],Schema::TAXONOMIES,true) || isset($terms[$t['key']])) { throw new \RuntimeException('分类标识、名称或类型无效。'); }
                $t['config']=Schema::term_config($t['config']??array(),$t['tax']); $terms[$t['key']]=$t;
            }
            foreach ($terms as $t) { $seen=array();$key=$t['key'];while($key!=='') { if (!isset($terms[$key]) || isset($seen[$key]) || $terms[$key]['tax']!==$t['tax']) { throw new \RuntimeException('分类父级缺失、跨类型或存在循环。'); } $seen[$key]=true;$key=$terms[$key]['parent']; } }
            $assets=array();
            foreach ($m['assets'] as $key=>$a) {
                if (!is_array($a) || !is_string($key) || strlen($key)>240 || !LinkPackage::path((string)($a['file']??'')) || !preg_match('#^(images|media)/#',$a['file'])) { throw new \RuntimeException('图片清单无效。'); }
                $bytes=$zip->getFromName($a['file']); if (!is_string($bytes)) { throw new \RuntimeException('缺少清单中的图片。'); }
                $info=self::image($bytes); if (!$legacy && (!is_string($a['sha256']??null) || !hash_equals($info['sha256'],$a['sha256']))) { throw new \RuntimeException('图片校验值不一致。'); }
                $info['file']=$info['sha256'].'.'.$info['ext'];$info['meta']=array();
                foreach (array('title','alt','caption','description','date') as $f) { $info['meta'][$f]=self::text($a['meta'][$f]??'',10000); }
                if (file_put_contents($dir.'/'.$info['file'],$bytes,LOCK_EX)!==strlen($bytes)) { throw new \RuntimeException('无法保存私有图片文件。'); }
                $assets[$key]=$info;
            }
            $items=array();$keys=array();
            foreach ($m['products'] as $p) {
                if (!is_array($p)) { throw new \RuntimeException('商品记录无效。'); } $p=self::post($p);
                if (isset($keys[$p['key']])) { throw new \RuntimeException('包内商品标识重复。'); } $keys[$p['key']]=true;
                foreach ($p['terms'] as $key) { if (!is_string($key) || !isset($terms[$key])) { throw new \RuntimeException('商品分类引用缺失。'); } }
                foreach ($p['assets'] as $key) { if (!is_string($key) || !isset($assets[$key])) { throw new \RuntimeException('商品图片引用缺失。'); } }
                if ($p['thumbnail']!=='' && !isset($assets[$p['thumbnail']])) { throw new \RuntimeException('主图缺失。'); }
                foreach(Schema::image_ids($p['config']) as $imageId){$asset=$m['ids'][$imageId]??null;if(!is_string($asset)||!isset($assets[$asset])||!in_array($asset,$p['assets'],true))throw new \RuntimeException('商品图集缺少明确的附件映射。');}
                $items[]=$p;
            }
            foreach ($m['urls'] as $url=>$key) { self::media_url($url); if (!is_string($key) || !isset($assets[$key])) { throw new \RuntimeException('图片地址映射无效。'); } }
            foreach ($m['ids'] as $id=>$key) { if (!ctype_digit((string)$id) || !is_string($key) || !isset($assets[$key])) { throw new \RuntimeException('附件编号映射无效。'); } }
            return array('source'=>$source,'items'=>$items,'terms'=>$terms,'assets'=>$assets,'urls'=>$m['urls'],'ids'=>$m['ids'],'legacy'=>$legacy);
        } finally { $zip->close(); }
    }
}
