<?php
namespace MaoTK\ZibllToolkit\PostMigration;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG='mao-tk-toolkit-posts-migration';
    public const ACTION='mao_tk_toolkit_posts_migration';
    private $manager;private $admin;private $screen='';
    public function __construct(ModuleManager $manager,Admin $admin){$this->manager=$manager;$this->admin=$admin;}
    public function register(): void
    {
        add_action('admin_menu',array($this,'menu'),21);add_action('admin_enqueue_scripts',array($this,'enqueue'));
        add_action('wp_ajax_'.self::ACTION,array($this,'ajax'));add_action('admin_post_'.self::ACTION.'_download',array($this,'download'));
    }
    public function menu(): void
    {
        $this->screen=(string)add_submenu_page(Admin::SLUG,'文章导入&导出','文章导入&导出','manage_options',self::SLUG,array($this,'render'));
        $this->admin->allow_screen($this->screen);
    }
    public function enqueue(string $hook): void
    {
        if($hook!==$this->screen||$this->screen===''||!current_user_can('manage_options'))return;
        wp_enqueue_style('mao-tk-post-migration',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/post-migration/admin.css',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-post-migration',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/post-migration/admin.js',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
    }
    private function active(): bool{foreach($this->manager->all() as $r)if($r['id']===Posts::MODULE)return $r['status']==='loaded';return false;}
    public static function errors(): array
    {
        $out=array();if(!class_exists('ZipArchive'))$out[]='服务器缺少 ZipArchive。';
        if(!class_exists('WP_HTML_Tag_Processor'))$out[]='文章迁移需要 WordPress 6.2 或更新版本的 HTML 接口。';
        try{Store::root();}catch(\Throwable $e){$out[]=$e->getMessage();}return $out;
    }
    public static function inbox(): string
    {
        $path=Store::root().'/inbox';if(is_link($path))throw new \RuntimeException('服务器收件目录不能为符号链接。');
        if(!is_dir($path)&&!mkdir($path,0700))throw new \RuntimeException('不能创建私有收件目录。');return $path;
    }
    public static function server_file(string $name): string
    {
        if($name!==basename($name)||!preg_match('/\.zip$/iD',$name))throw new \RuntimeException('服务器文件名称无效。');
        $path=self::inbox().'/'.$name;$real=realpath($path);
        if(is_link($path)||!$real||!Store::inside($real,(string)realpath(self::inbox()))||!is_file($real)||filesize($real)>Package::MAX_ZIP)throw new \RuntimeException('服务器 ZIP 不存在、越界或超出大小限制。');return $real;
    }
    public static function download_url(string $id,string $kind): string
    {
        return add_query_arg(array('action'=>self::ACTION.'_download','job'=>$id,'kind'=>$kind,'_wpnonce'=>wp_create_nonce(self::ACTION.'_download_'.$id)),admin_url('admin-post.php'));
    }
    public static function present(array $job,int $page=1): array
    {
        $out=Store::summary($job);$total=count($job['items']);$page=max(1,min((int)ceil(max(1,$total)/20),$page));$out['page']=$page;$out['pages']=max(1,(int)ceil($total/20));$out['rows']=array();
        $labels=array('publish'=>'已发布','draft'=>'草稿','pending'=>'待审核','private'=>'私密','future'=>'定时');
        foreach(array_slice($job['items'],($page-1)*20,20,true) as $i=>$p)$out['rows'][]=array('name'=>$p['title']!==''?$p['title']:'（无标题）','url'=>$p['slug']!==''?$p['slug']:'来源 ID '.$p['key'],'visibility'=>$labels[$p['status']]??$p['status'],'logo'=>count($p['assets']).' 张图片','plan'=>$job['preview'][$i]['action']??'export','result'=>$job['results'][$i]??null);
        $out['report']=$out['status']!=='expired'?self::download_url($job['id'],'report'):'';
        $out['download']=$out['status']==='complete'&&$job['type']==='export'&&$out['counts']['success']?self::download_url($job['id'],'zip'):'';
        $out['sha256']=$job['sha256']??'';$out['partial']=!empty($job['partial']);
        $out['counter']=$out['done'].' / '.$total;
        $out['percent']=$total?$out['done']*100/$total:0;
        $out['description']=$job['status']==='preview'?'请核对新增、跳过与覆盖计划。确认后才会写入文章和图片。':'逐篇处理，已完成记录不会重复新增。';
        if($job['type']==='import'&&$job['status']!=='complete'){
            $out['percent']=30+($total?$out['done']*40/$total:0);
            if(($job['phase']??'')==='media'){$n=count($job['media_queue']);$out['percent']=$n?$job['media_cursor']*30/$n:0;$out['counter']='图片 '.$job['media_cursor'].' / '.$n;$out['description']='逐张处理本地图片，完成后再写文章。已成功的图片会直接复用。';}
            if(($job['phase']??'')==='references'){$out['percent']=70+($total?$job['ref_cursor']*30/$total:0);$out['counter']='关联 '.$job['ref_cursor'].' / '.$total;$out['description']='文章已写入，正在核对图片与本批文章的已知关联；完成后再结束任务。';}
            if($job['status']==='preview'){$out['percent']=0;$out['counter']='0 / '.$total;$out['description']='请核对新增、跳过与覆盖计划。确认后才会写入文章和图片。';}
        }
        if($job['status']==='complete')$out['percent']=100;if($job['status']==='invalid')$out['rows']=array();return $out;
    }
    public function ajax(): void
    {
        try{
            Posts::authorize();if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new \RuntimeException('请使用 POST。');check_ajax_referer(self::ACTION,'nonce');
            $op=is_string($_POST['operation']??null)?$_POST['operation']:'';
            if($op==='availability'){wp_send_json_success(array('active'=>$this->active()&&!self::errors(),'nonce'=>wp_create_nonce(self::ACTION)));}
            if($op==='history')wp_send_json_success(Store::history());
            if(!in_array($op,array('inspect'),true)){if(!$this->active())throw new \RuntimeException('文章迁移模块未运行。');$errors=self::errors();if($errors)throw new \RuntimeException(implode(' ',$errors));}
            if($op==='export'){
                $ids=is_string($_POST['ids']??null)?trim(wp_unslash($_POST['ids'])):'';
                if($ids!==''&&!preg_match('/^\d+(?:[\s,，]+\d+)*$/uD',$ids))throw new \RuntimeException('文章 ID 请以逗号或空格分隔。');
                $ids=$ids!==''?array_values(array_unique(array_map('intval',preg_split('/[\s,，]+/u',$ids)))):array();if(count($ids)>Package::MAX_POSTS||in_array(0,$ids,true))throw new \RuntimeException('文章 ID 数量或取值无效。');
                $status=is_string($_POST['status']??null)?$_POST['status']:'';if(!in_array($status,array('','publish','draft','pending','private','future'),true))throw new \RuntimeException('文章状态无效。');
                $after=is_string($_POST['after']??null)?$_POST['after']:'';$before=is_string($_POST['before']??null)?$_POST['before']:'';
                foreach(array($after,$before) as $date)if($date!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$d)||!checkdate((int)$d[2],(int)$d[3],(int)$d[1])))throw new \RuntimeException('日期无效。');
                if($after!==''&&$before!==''&&$after>$before)throw new \RuntimeException('起始日期不能晚于结束日期。');
                $category=is_scalar($_POST['category']??null)?absint($_POST['category']):0;if($category&&!term_exists($category,'category'))throw new \RuntimeException('分类不存在。');
                $job=Posts::export(array('ids'=>$ids,'category'=>$category,'status'=>$status,'after'=>$after,'before'=>$before),!empty($_POST['images']),!empty($_POST['meta']));
            }elseif($op==='upload'){
                if(($_POST['source_mode']??'upload')==='server'){$name=is_string($_POST['serverfile']??null)?wp_unslash($_POST['serverfile']):'';$file=self::server_file($name);}
                else{$f=$_FILES['package']??null;if(!is_array($f)||($f['error']??-1)!==UPLOAD_ERR_OK||!is_string($f['name']??null)||!preg_match('/\.zip$/iD',$f['name'])||!is_uploaded_file($f['tmp_name']??''))throw new \RuntimeException('未收到有效 ZIP，请检查上传上限或改用私有服务器收件目录。');$file=$f['tmp_name'];}
                $policy=is_string($_POST['policy']??null)?$_POST['policy']:'skip';$source=is_string($_POST['legacy_source']??null)?trim(wp_unslash($_POST['legacy_source'])):'';
                $job=Posts::prepare($file,$policy,($_POST['status_policy']??'draft')!=='preserve',$source);
            }else{
                $id=is_string($_POST['job']??null)?$_POST['job']:'';
                if($op==='inspect')$job=Store::load($id,true);
                elseif(in_array($op,array('start','step','pause','retry'),true)){if(!is_scalar($_POST['revision']??null)||!ctype_digit((string)$_POST['revision']))throw new \RuntimeException('任务版本无效。');$job=Posts::command($id,$op,(int)$_POST['revision']);}
                else throw new \RuntimeException('操作无效。');
            }
            $page=is_scalar($_POST['page_number']??null)?absint($_POST['page_number']):1;wp_send_json_success(self::present($job,$page));
        }catch(\Throwable $e){wp_send_json_error(array('message'=>$e->getMessage()),400);}
    }
    public function download(): void
    {
        try{
            Posts::authorize();$id=is_string($_GET['job']??null)?$_GET['job']:'';check_admin_referer(self::ACTION.'_download_'.$id);$job=Store::load($id);
            nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');
            if(($_GET['kind']??'')==='zip'&&$job['type']==='export'&&$job['status']==='complete'){
                $file=Store::dir($id).'/posts.zip';if(!is_file($file)||!hash_equals($job['sha256'],(string)hash_file('sha256',$file)))throw new \RuntimeException('ZIP 已失效。');
                header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="maotk-posts-'.gmdate('Ymd-His',$job['created']).(!empty($job['partial'])?'-partial':'').'.zip"');header('Content-Length: '.filesize($file));readfile($file);exit;
            }
            if(($_GET['kind']??'')!=='report')throw new \RuntimeException('下载类型无效。');
            header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="maotk-posts-report-'.$id.'.json"');
            echo wp_json_encode(array('summary'=>Store::summary($job),'results'=>$job['results'],'before_update'=>$job['backups'],'sha256'=>$job['sha256']??''),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
        }catch(\Throwable $e){wp_die(esc_html($e->getMessage()),'',array('response'=>403));}
    }
    public function render(): void
    {
        Posts::authorize();$errors=self::errors();$active=$this->active()&&!$errors;$categories=get_categories(array('hide_empty'=>false));
        $serverfiles=array();$inbox='';try{$inbox=self::inbox();foreach((array)glob($inbox.'/*.zip') as $f){if(!is_link($f)&&is_file($f)&&filesize($f)<=Package::MAX_ZIP)$serverfiles[]=basename($f);if(count($serverfiles)>=100)break;}}catch(\Throwable $e){$errors[]=$e->getMessage();}
        $module=array('id'=>Posts::MODULE,'name'=>'文章导入&导出','enabled'=>false,'status'=>'disabled');foreach($this->manager->all() as $r)if($r['id']===Posts::MODULE)$module=$r;$module['name']='文章导入&导出';
        require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/migration-posts.php';
    }
}
