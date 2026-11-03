<?php
namespace MaoTK\ZibllToolkit\PostMigration;
defined('ABSPATH') || exit;

final class Posts
{
    public const MODULE='data-migration-posts';
    private const FIELDS=array('post_title','post_content','post_excerpt','post_name','post_status','post_date','post_date_gmt','post_modified','post_modified_gmt','post_author','post_password','comment_status','ping_status');
    public static function authorize(bool $write=false): void
    {
        if (!current_user_can('manage_options') || !current_user_can('edit_others_posts')) { throw new \RuntimeException('需要管理员和管理他人文章的权限。'); }
        if ($write && !current_user_can('unfiltered_html')) { throw new \RuntimeException('完整保留文章 HTML 需要 unfiltered_html 权限。'); }
    }
    public static function hash(array $data): string { return hash('sha256',wp_json_encode($data)); }
    public static function metadata(int $id): array
    {
        $out=array();foreach(get_post_meta($id) as $key=>$values) { if(Package::allowed_meta($key)) { $out[$key]=array_map(static function($value){return is_serialized($value)?unserialize(trim($value),array('allowed_classes'=>false)):$value;},$values);Package::value($out[$key]); } }ksort($out);return $out;
    }
    public static function snapshot(int $id): array
    {
        $p=get_post($id,ARRAY_A);if(!$p || $p['post_type']!=='post')return array();
        $out=array_intersect_key($p,array_flip(self::FIELDS));$out['meta']=self::metadata($id);
        foreach(array('category','post_tag') as $tax) { $ids=wp_get_object_terms($id,$tax,array('fields'=>'ids'));if(is_wp_error($ids))throw new \RuntimeException('分类读取失败。');$out[$tax]=array_map('intval',$ids);sort($out[$tax]); }
        $out['thumbnail']=(int)get_post_thumbnail_id($id);$out['format']=get_post_format($id)?:'';$out['sticky']=is_sticky($id);return $out;
    }
    public static function existing(string $source, array $item): array
    {
        $origin=hash('sha256',$source.'|'.$item['key']);
        $ids=get_posts(array('post_type'=>'post','post_status'=>array('publish','draft','pending','private','future','trash'),'numberposts'=>3,'meta_key'=>'_mao_tk_post_migration_origin','meta_value'=>$origin,'fields'=>'ids','suppress_filters'=>true));
        if(!$ids && $item['slug']!=='') {
            global $wpdb;
            $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_name=%s AND post_status!='auto-draft' LIMIT 3",$item['slug']));
        }
        return array_map('intval',$ids);
    }
    public static function export(array $filters, bool $images, bool $meta): array
    {
        self::authorize();$args=array('post_type'=>'post','post_status'=>$filters['status']?:array('publish','draft','pending','private','future'),'posts_per_page'=>Package::MAX_POSTS+1,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>true);
        if($filters['ids'])$args['post__in']=$filters['ids'];if($filters['category'])$args['cat']=$filters['category'];
        if($filters['after']!=='' || $filters['before']!=='') { $d=array('inclusive'=>true);if($filters['after']!==''){$date=explode('-',$filters['after']);$d['after']=array('year'=>(int)$date[0],'month'=>(int)$date[1],'day'=>(int)$date[2]);}if($filters['before']!==''){$date=explode('-',$filters['before']);$d['before']=array('year'=>(int)$date[0],'month'=>(int)$date[1],'day'=>(int)$date[2]);}$args['date_query']=array($d); }
        $rows=get_posts($args);if(!$rows)throw new \RuntimeException('筛选范围没有文章。');if(count($rows)>Package::MAX_POSTS)throw new \RuntimeException('超过 100 篇，请按日期、分类或文章 ID 分批。');
        $items=array();$terms=array();
        foreach($rows as $p) {
            if(!current_user_can('edit_post',$p->ID))throw new \RuntimeException('没有访问所选文章的权限。');
            $keys=array();foreach(array('category','post_tag') as $tax) {
                $ts=wp_get_object_terms($p->ID,$tax);if(is_wp_error($ts))throw new \RuntimeException('分类读取失败。');
                foreach($ts as $t) { $keys[]=$tax.':'.$t->term_id;$seen=array();while($t && !is_wp_error($t)) { $key=$tax.':'.$t->term_id;if(isset($seen[$key]))throw new \RuntimeException('分类层级循环。');$seen[$key]=true;$terms[$key]=array('key'=>$key,'tax'=>$tax,'name'=>$t->name,'slug'=>$t->slug,'description'=>$t->description,'parent'=>$t->parent?$tax.':'.$t->parent:'');$t=$t->parent?get_term($t->parent,$tax):null; } }
            }
            $author=get_userdata($p->post_author);
            $items[]=Package::post(array('key'=>(string)$p->ID,'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'slug'=>$p->post_name,'status'=>$p->post_status,'date'=>$p->post_date,'date_gmt'=>$p->post_date_gmt,'modified'=>$p->post_modified,'modified_gmt'=>$p->post_modified_gmt,'author'=>$author?$author->user_login:'','password'=>$p->post_password,'comment'=>$p->comment_status,'ping'=>$p->ping_status,'permalink'=>get_permalink($p->ID),'thumbnail'=>'','terms'=>$keys,'meta'=>$meta?self::metadata($p->ID):array(),'format'=>get_post_format($p->ID)?:'','sticky'=>is_sticky($p->ID)));
            $items[count($items)-1]['source_thumbnail']=(int)get_post_thumbnail_id($p->ID);
        }
        if(strlen((string)wp_json_encode($items))>Package::MAX_JSON)throw new \RuntimeException('文章正文与扩展字段超过 16 MiB，请缩小范围。');
        $job=Store::create('export',$items,array('source'=>trailingslashit(home_url('/')),'terms'=>$terms,'assets'=>array(),'urls'=>array(),'ids'=>array(),'images'=>$images,'with_meta'=>$meta,'entity'=>'posts'));
        $job['status']='ready';Store::save($job);return $job;
    }
    private static function asset(array &$job, string $path, int $id=0): string
    {
        $real=realpath($path);$uploads=wp_upload_dir();$base=realpath($uploads['basedir']);
        if(!$real || !$base || !Store::inside($real,$base) || !is_file($real) || filesize($real)>Package::MAX_IMAGE)throw new \RuntimeException('本地图片缺失、越界或超过 8 MiB。');
        $bytes=file_get_contents($real);if(!is_string($bytes))throw new \RuntimeException('本地图片不可读。');$a=Package::image($bytes);$key=$a['sha256'];
        if(!isset($job['assets'][$key])) {
            $sum=array_sum(array_column($job['assets'],'bytes'));if($sum+strlen($bytes)>200*1024*1024)throw new \RuntimeException('图片总量超过 200 MiB，请缩小导出范围。');
            $a['file']='media/'.$key.'.'.$a['ext'];$post=$id?get_post($id):null;
            $a['meta']=array('title'=>$post?$post->post_title:basename($real),'alt'=>$id?(string)get_post_meta($id,'_wp_attachment_image_alt',true):'','caption'=>$post?$post->post_excerpt:'','description'=>$post?$post->post_content:'','date'=>$post?$post->post_date:'');
            if(file_put_contents(Store::dir($job['id']).'/'.basename($a['file']),$bytes,LOCK_EX)!==strlen($bytes))throw new \RuntimeException('图片暂存失败。');$job['assets'][$key]=$a;
        }
        if($id) {
            $job['ids'][(string)$id]=$key;$url=wp_get_attachment_url($id);if($url)$job['urls'][$url]=$key;
            $md=wp_get_attachment_metadata($id);foreach((array)($md['sizes']??array()) as $size) { if(!empty($size['file']) && $url)$job['urls'][dirname($url).'/'.$size['file']]=$key; }
        }
        return $key;
    }
    private static function block_ids(array $blocks): array
    {
        $out=array();foreach($blocks as $block) { if(in_array($block['blockName'],array('core/image','core/gallery','core/cover','core/media-text'),true)) { $a=$block['attrs'];foreach(array('id','mediaId') as $k)if(!empty($a[$k]))$out[]=(int)$a[$k];foreach((array)($a['ids']??array()) as $id)$out[]=(int)$id; }if($block['innerBlocks'])$out=array_merge($out,self::block_ids($block['innerBlocks'])); }return $out;
    }
    private static function original_file(int $id): string { return (string)(function_exists('wp_get_original_image_path')?wp_get_original_image_path($id):get_attached_file($id)); }
    private static function image_matches(int $id,string $hash): bool
    {
        $file=self::original_file($id);return get_post_type($id)==='attachment'&&is_file($file)&&hash_equals($hash,(string)hash_file('sha256',$file));
    }
    private static function export_item(array &$job,int $i): array
    {
        $p=&$job['items'][$i];$assets=array();
        if($job['images']) {
            $ids=self::block_ids(parse_blocks($p['content']));$thumb=(int)($p['source_thumbnail']??0);if($thumb)$ids[]=$thumb;
            foreach(array_unique($ids) as $id) { $key=self::asset($job,self::original_file($id),$id);$assets[]=$key;if($thumb===$id)$p['thumbnail']=$key; }
            $upload=wp_upload_dir();$prefix=trailingslashit($upload['baseurl']);$html=new \WP_HTML_Tag_Processor($p['content']);
            while($html->next_tag('IMG')) {
                foreach(array('src','data-src','data-original') as $attr) {
                    $url=$html->get_attribute($attr);if(!is_string($url) || $url==='')continue;
                    $absolute=strpos($url,'/')===0?home_url($url):$url;
                    if(strpos($absolute,$prefix)!==0)continue;
                    if(isset($job['urls'][$absolute])) { $assets[]=$job['urls'][$absolute];continue; }
                    $relative=rawurldecode(explode('?',substr($absolute,strlen($prefix)),2)[0]);
                    $id=(int)attachment_url_to_postid(preg_replace('/-\d+x\d+(?=\.[^.]+$)/','',explode('?',$absolute,2)[0]));
                    $key=self::asset($job,$id?self::original_file($id):$upload['basedir'].'/'.$relative,$id);$job['urls'][$absolute]=$key;$assets[]=$key;
                    if($url!==$absolute)$job['urls'][$url]=$key;
                }
            }
        }
        $p['assets']=array_values(array_unique($assets));return array('state'=>'success','message'=>'正文、区块与文章字段已准备','id'=>(int)$p['key']);
    }
    private static function finish_export(array &$job): void
    {
        $good=array();foreach($job['items'] as $i=>$item)if(($job['results'][$i]['state']??'')==='success')$good[]=$item;
        $m=array('format'=>'maotk-posts','version'=>1,'source'=>$job['source'],'toolkit'=>MAO_TK_ZIBLL_TOOLKIT_VERSION,'posts'=>$good,'terms'=>array_values($job['terms']),'assets'=>$job['assets'],'urls'=>$job['urls'],'ids'=>$job['ids']);
        $raw=wp_json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($raw)||strlen($raw)>Package::MAX_JSON)throw new \RuntimeException('清单超过 16 MiB，请分批。');
        $file=Store::dir($job['id']).'/posts.zip';$z=new \ZipArchive();if($z->open($file,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('ZIP 创建失败。');
        $z->addFromString('manifest.json',$raw);foreach($job['assets'] as $a)$z->addFile(Store::dir($job['id']).'/'.basename($a['file']),$a['file']);if(!$z->close())throw new \RuntimeException('ZIP 写入失败。');
        $job['sha256']=hash_file('sha256',$file);$job['partial']=count($good)!==count($job['items']);
    }
    public static function prepare(string $file,string $policy,bool $draft,string $source=''): array
    {
        self::authorize(true);if(!in_array($policy,array('skip','update'),true))throw new \RuntimeException('重复处理方式无效。');
        $job=Store::create('import',array(),array('policy'=>$policy,'draft'=>$draft,'entity'=>'posts','phase'=>'posts','ref_cursor'=>0,'media_map'=>array(),'preview'=>array()));
        try {
            $job=array_merge($job,Package::read($file,Store::dir($job['id']),$source));$seen=array();
            foreach($job['items'] as $i=>$item) { $ids=self::existing($job['source'],$item);$identity=$item['slug']!==''?'slug:'.$item['slug']:'id:'.$item['key'];$conflict=count($ids)>1||isset($seen[$identity]);$seen[$identity]=true;
                $target=$ids?$ids[0]:0;if($target && get_post_status($target)==='trash')$conflict=true;
                $job['preview'][$i]=array('action'=>$conflict?'conflict':($target?$policy:'add'),'target'=>$target,'hash'=>$target?self::hash(self::snapshot($target)):'');
            }
            $queue=array();foreach($job['items'] as $i=>$item){if(in_array($job['preview'][$i]['action'],array('conflict','skip'),true))continue;$queue=array_merge($queue,$item['assets']);if($item['thumbnail']!=='')$queue[]=$item['thumbnail'];}
            $job['media_queue']=array_values(array_unique($queue));$job['media_cursor']=0;$job['media_errors']=array();$job['phase']=$queue?'media':'posts';
            $job['warnings'][]='仅迁移文章、图片与扩展字段。评论、订单、已购权益、站点设置、账号权限及下载文件实体不在本次范围；特殊扩展字段中的业务 ID 保留原值，需要另行核对。';
            if($job['legacy'])$job['warnings'][]='旧包无原始哈希，已验证实际图片；不读取旧包 subdir，不从任意服务器路径补文件。';
            if($draft)$job['warnings'][]='本次所有写入文章均存为草稿，原始状态仍保留在包内。';
            Store::save($job);return $job;
        }catch(\Throwable $e){$job['status']='invalid';$job['warnings'][]=$e->getMessage();Store::save($job);throw $e;}
    }
    private static function media(array &$job,string $key): array
    {
        if(isset($job['media_map'][$key])&&self::image_matches((int)$job['media_map'][$key]['id'],$job['assets'][$key]['sha256']))return $job['media_map'][$key];
        $a=$job['assets'][$key];$lockkey='image:'.$a['sha256'];Store::recover($lockkey);$lock=Store::lock($lockkey);
        try {
            $ids=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','numberposts'=>50,'meta_key'=>'_mao_tk_post_migration_sha256','meta_value'=>$a['sha256'],'fields'=>'ids','suppress_filters'=>true));
            $id=0;foreach($ids as $candidate){if(self::image_matches((int)$candidate,$a['sha256'])){$id=(int)$candidate;break;}}
            if(!$id || !self::image_matches($id,$a['sha256'])) {
                if(!current_user_can('upload_files'))throw new \RuntimeException('图片入库需要媒体上传权限。');
                $src=Store::dir($job['id']).'/'.$a['file'];if(!is_file($src)||!hash_equals($a['sha256'],(string)hash_file('sha256',$src)))throw new \RuntimeException('图片丢失或校验不一致。');
                require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
                $tmp=Store::dir($job['id']).'/sideload-'.$a['sha256'].'.'.$a['ext'];if(!copy($src,$tmp))throw new \RuntimeException('复制图片失败。');
                $fields=array('post_title'=>$a['meta']['title'],'post_excerpt'=>$a['meta']['caption'],'post_content'=>$a['meta']['description']);
                if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$a['meta']['date']))$fields['post_date']=$a['meta']['date'];
                $id=media_handle_sideload(array('name'=>$a['sha256'].'.'.$a['ext'],'tmp_name'=>$tmp),0,null,wp_slash($fields));if(is_wp_error($id)){@unlink($tmp);throw new \RuntimeException('图片入库失败：'.$id->get_error_message());}
                update_post_meta($id,'_mao_tk_post_migration_sha256',$a['sha256']);update_post_meta($id,'_wp_attachment_image_alt',wp_slash($a['meta']['alt']));
            }
            $job['media_map'][$key]=array('id'=>$id,'url'=>wp_get_attachment_url($id));Store::save($job);return $job['media_map'][$key];
        }finally{delete_option($lock);}
    }
    private static function terms(array $terms,array $keys,array &$cache=array()): array
    {
        $out=array('category'=>array(),'post_tag'=>array());
        foreach($keys as $key) {
            $t=$terms[$key];if(isset($cache[$key])){$out[$t['tax']][]=$cache[$key];continue;}$parent=0;
            if($t['parent']!==''){$p=self::terms($terms,array($t['parent']),$cache);$parent=$p[$t['tax']][0];}
            $term=term_exists($t['slug']!==''?$t['slug']:$t['name'],$t['tax'],$parent);
            if(!$term)$term=wp_insert_term($t['name'],$t['tax'],array('slug'=>$t['slug'],'description'=>$t['description'],'parent'=>$parent));
            if(is_wp_error($term))throw new \RuntimeException('分类写入失败：'.$t['name']);$id=(int)(is_array($term)?$term['term_id']:$term);$check=get_term($id,$t['tax']);
            if(!$check||is_wp_error($check)||(int)$check->parent!==$parent)throw new \RuntimeException('目标分类的父级冲突。');$cache[$key]=$id;$out[$t['tax']][]=$id;
        }return $out;
    }
    private static function maps(array $job): array
    {
        $urls=array();$ids=array();foreach($job['urls'] as $url=>$key)if(isset($job['media_map'][$key]))$urls[$url]=$job['media_map'][$key]['url'];
        foreach($job['ids'] as $id=>$key)if(isset($job['media_map'][$key]))$ids[(string)$id]=$job['media_map'][$key]['id'];return array($urls,$ids);
    }
    private static function write(array $data,array $item): int
    {
        $token=$data['meta_input']['_mao_tk_post_migration_write']??null;
        $filter=static function($row,$arr)use($token,$item){if(($arr['meta_input']['_mao_tk_post_migration_write']??null)===$token){$row['post_modified']=$item['modified'];$row['post_modified_gmt']=$item['modified_gmt'];}return $row;};
        add_filter('wp_insert_post_data',$filter,99,2);
        try{$id=isset($data['ID'])?wp_update_post(wp_slash($data),true):wp_insert_post(wp_slash($data),true);}finally{remove_filter('wp_insert_post_data',$filter,99);}
        if(is_wp_error($id)||!$id)throw new \RuntimeException('文章写入失败。');return (int)$id;
    }
    private static function import_item(array &$job,int $i): array
    {
        $item=$job['items'][$i];$preview=$job['preview'][$i];$origin=hash('sha256',$job['source'].'|'.$item['key']);$key='post:'.$origin;Store::recover($key);$lock=Store::lock($key);
        try {
            $ids=self::existing($job['source'],$item);if($preview['action']==='conflict'||count($ids)>1)throw new \RuntimeException('来源或别名冲突，请处理后重新预检。');$id=$ids?$ids[0]:0;
            $done=$job['written'][$i]??null;
            if($done && $id===(int)$done['id']) { if(self::hash(self::snapshot($id))!==$done['hash'])throw new \RuntimeException('写入后文章发生修改，请重新预检。');return array('state'=>'success','message'=>'本任务已写入，未重复处理','id'=>$id); }
            $token=$job['id'].':'.$i;
            $recover=$id && get_post_meta($id,'_mao_tk_post_migration_write',true)===$token && isset($job['journal'][$i]);
            if($id && $job['policy']==='skip' && !$recover)return array('state'=>'skip','message'=>'已存在，正文与媒体未改动','id'=>$id);
            if(!$recover && ($id!==(int)$preview['target'] || ($id && self::hash(self::snapshot($id))!==$preview['hash'])))throw new \RuntimeException('预检后文章已变化，请重新预检。');
            if($id && !current_user_can('edit_post',$id))throw new \RuntimeException('没有编辑目标文章的权限。');
            if(!$job['draft'] && in_array($item['status'],array('publish','future'),true) && !current_user_can('publish_posts'))throw new \RuntimeException('保持发布状态需要发布权限。');
            foreach($item['assets'] as $asset){if(isset($job['media_errors'][$asset]))throw new \RuntimeException($job['media_errors'][$asset]);self::media($job,$asset);}if($item['thumbnail']!=='')self::media($job,$item['thumbnail']);
            list($urls,$images)=self::maps($job);$content=Markup::rewrite($item['content'],$urls,$images);$terms=self::terms($job['terms'],$item['terms']);
            if($id && !$recover && !isset($job['backups'][$id])){$job['backups'][$id]=self::snapshot($id);Store::save($job);}
            $u=$item['author']!==''?get_user_by('login',$item['author']):false;
            $data=array('post_type'=>'post','post_title'=>$item['title'],'post_content'=>$content,'post_excerpt'=>$item['excerpt'],'post_name'=>$item['slug'],'post_status'=>$job['draft']?'draft':$item['status'],'post_date'=>$item['date'],'post_date_gmt'=>$item['date_gmt'],'post_password'=>$item['password'],'comment_status'=>$item['comment'],'ping_status'=>$item['ping'],'post_author'=>$u&&is_user_member_of_blog($u->ID)?$u->ID:get_current_user_id(),'meta_input'=>array('_mao_tk_post_migration_origin'=>$origin,'_mao_tk_post_migration_write'=>$token));
            if($id)$data['ID']=$id;
            if($recover) { foreach(self::FIELDS as $field) { if(!array_key_exists($field,$data))continue;if((string)get_post_field($field,$id,'raw')!==(string)$data[$field])throw new \RuntimeException('中断文章已被修改，请重新预检。'); } }
            $job['journal'][$i]=array('id'=>$id,'data'=>$data);Store::save($job);
            $id=self::write($data,$item);
            foreach($terms as $tax=>$values){$r=wp_set_object_terms($id,$values,$tax,false);if(is_wp_error($r))throw new \RuntimeException('文章已写入，分类未保存。');}
            foreach($item['meta'] as $name=>$values){delete_post_meta($id,$name);foreach($values as $value){if(add_post_meta($id,$name,wp_slash($value),false)===false)throw new \RuntimeException('文章已写入，扩展字段保存失败。');}}
            if($item['thumbnail']!=='')set_post_thumbnail($id,$job['media_map'][$item['thumbnail']]['id']);else delete_post_thumbnail($id);
            set_post_format($id,$item['format']);if($item['sticky'])stick_post($id);else unstick_post($id);
            $saved=self::snapshot($id);if($saved['post_content']!==$content)throw new \RuntimeException('文章 HTML 被其他过滤器改写，请核对后重新预检。');
            $job['written'][$i]=array('id'=>$id,'hash'=>self::hash($saved));Store::save($job);
            return array('state'=>'success','message'=>$preview['target']?'已更新，原文已备份':'已新增，等待关联核对','id'=>$id);
        }finally{delete_option($lock);}
    }
    private static function references(array &$job,int $i): void
    {
        $r=$job['results'][$i]??null;if(!$r||$r['state']!=='success')return;$id=(int)$r['id'];
        if(self::hash(self::snapshot($id))!==($job['written'][$i]['hash']??''))throw new \RuntimeException('关联核对前文章已被修改，未覆盖，请重新预检。');
        list($urls,$images)=self::maps($job);$posts=array();foreach($job['items'] as $n=>$item){$target=$job['results'][$n]??null;if($target && in_array($target['state'],array('success','skip'),true)){$posts[$item['key']]=$target['id'];if($item['permalink']!=='')$urls[$item['permalink']]=get_permalink($target['id']);}}
        $item=$job['items'][$i];$before=get_post_field('post_content',$id,'raw');$after=Markup::rewrite($item['content'],$urls,$images,$posts);
        if($after!==$before){$data=array('ID'=>$id,'post_content'=>$after,'meta_input'=>array('_mao_tk_post_migration_write'=>$job['id'].':'.$i));self::write($data,$item);if(get_post_field('post_content',$id,'raw')!==$after)throw new \RuntimeException('关联更新被其他过滤器改写。');}
        $job['written'][$i]['hash']=self::hash(self::snapshot($id));$job['results'][$i]['message']='已完成，图片与已迁入文章关联已核对';
    }
    public static function command(string $id,string $op,int $revision): array
    {
        self::authorize();$key='job:'.$id;Store::recover($key);$lock=Store::lock($key);
        try {
            $job=Store::load($id);if($job['type']==='import')self::authorize(true);if($job['revision']!==$revision)return $job;
            if($op==='pause'){if($job['status']==='running'){$job['status']='paused';Store::save($job);}return $job;}
            if($op==='retry'){if(!in_array($job['status'],array('complete','paused'),true))throw new \RuntimeException('请先暂停或等待结束。');foreach($job['results'] as $i=>$r)if($r['state']==='failed')unset($job['results'][$i]);$job['cursor']=0;$job['phase']=!empty($job['media_queue'])?'media':'posts';$job['media_cursor']=0;$job['media_errors']=array();$job['ref_cursor']=0;$job['status']='paused';Store::save($job);return $job;}
            if($op==='start'){if(in_array($job['status'],array('preview','ready','paused','running'),true)){$job['status']='running';Store::save($job);}return $job;}
            if($op!=='step'||$job['status']!=='running')return $job;
            if(($job['phase']??'posts')==='media'){
                $key=$job['media_queue'][$job['media_cursor']]??null;if($key!==null){try{self::media($job,$key);}catch(\Throwable $e){$job['media_errors'][$key]=$e->getMessage();}$job['media_cursor']++;}
                if($job['media_cursor']>=count($job['media_queue']))$job['phase']='posts';Store::save($job);return $job;
            }
            if(($job['phase']??'posts')==='references') {
                $i=$job['ref_cursor'];if($i<count($job['items'])){try{self::references($job,$i);}catch(\Throwable $e){$job['results'][$i]=array('state'=>'failed','message'=>$e->getMessage(),'id'=>$job['results'][$i]['id']??0);}$job['ref_cursor']++;}
                if($job['ref_cursor']>=count($job['items']))$job['status']='complete';Store::save($job);return $job;
            }
            while($job['cursor']<count($job['items'])&&isset($job['results'][$job['cursor']]))$job['cursor']++;
            if($job['cursor']<count($job['items'])){$i=$job['cursor'];try{$job['results'][$i]=$job['type']==='export'?self::export_item($job,$i):self::import_item($job,$i);}catch(\Throwable $e){$job['results'][$i]=array('state'=>'failed','message'=>$e->getMessage(),'id'=>0);}$job['cursor']++;Store::save($job);}
            if($job['cursor']>=count($job['items'])){if($job['type']==='export'){self::finish_export($job);$job['status']='complete';}else{$job['phase']='references';$job['ref_cursor']=0;}Store::save($job);}return $job;
        }finally{delete_option($lock);}
    }
}
