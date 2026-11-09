<?php
namespace MaoTK\ZibllToolkit\ProductMigration;

use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG='mao-tk-toolkit-products-migration';
    public const ACTION='mao_tk_toolkit_products_migration';
    private $manager;private $admin;private $screen='';
    public function __construct(ModuleManager $manager,Admin $admin){$this->manager=$manager;$this->admin=$admin;}
    public function register(): void
    {
        add_action('admin_menu',array($this,'menu'),21);add_action('admin_enqueue_scripts',array($this,'enqueue'));
        add_action('wp_ajax_'.self::ACTION,array($this,'ajax'));add_action('admin_post_'.self::ACTION.'_download',array($this,'download'));
    }
    public function menu(): void
    {
        $this->screen=(string)add_submenu_page(Admin::SLUG,__('商品导入&导出', 'mao-tk-zibll-toolkit'),__('商品导入&导出', 'mao-tk-zibll-toolkit'),'manage_options',self::SLUG,array($this,'render'));
        $this->admin->allow_screen($this->screen);
    }
    public function enqueue(string $hook): void
    {
        if($hook!==$this->screen||$this->screen===''||!current_user_can('manage_options'))return;
        wp_enqueue_style('mao-tk-product-migration',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/product-migration/admin.css',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-product-migration',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/product-migration/admin.js',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
        wp_set_script_translations('mao-tk-product-migration', 'mao-tk-zibll-toolkit', MAO_TK_ZIBLL_TOOLKIT_DIR . 'languages');
    }
    private function active(): bool{foreach($this->manager->all() as $r)if($r['id']===Products::MODULE)return $r['status']==='loaded';return false;}
    public static function errors(): array
    {
        $out=Schema::errors();if(!class_exists('ZipArchive'))$out[]=__('服务器缺少 ZipArchive。', 'mao-tk-zibll-toolkit');
        if(!class_exists('WP_HTML_Tag_Processor'))$out[]=__('商品迁移需要 WordPress 6.2 或更新版本的 HTML 接口。', 'mao-tk-zibll-toolkit');
        try{Store::root();}catch(\Throwable $e){$out[]=translate($e->getMessage(), 'mao-tk-zibll-toolkit');}return $out;
    }
    public static function inbox(): string
    {
        $path=Store::root().'/inbox';if(is_link($path))throw new \RuntimeException(__('服务器收件目录不能为符号链接。', 'mao-tk-zibll-toolkit'));
        if(!is_dir($path)&&!mkdir($path,0700))throw new \RuntimeException(__('不能创建私有收件目录。', 'mao-tk-zibll-toolkit'));return $path;
    }
    public static function server_file(string $name): string
    {
        if($name!==basename($name)||!preg_match('/\.zip$/iD',$name))throw new \RuntimeException(__('服务器文件名称无效。', 'mao-tk-zibll-toolkit'));
        $path=self::inbox().'/'.$name;$real=realpath($path);
        if(is_link($path)||!$real||!Store::inside($real,(string)realpath(self::inbox()))||!is_file($real)||filesize($real)>Package::MAX_ZIP)throw new \RuntimeException(__('服务器 ZIP 不存在、越界或超出大小限制。', 'mao-tk-zibll-toolkit'));return $real;
    }
    public static function download_url(string $id,string $kind): string
    {
        return add_query_arg(array('action'=>self::ACTION.'_download','job'=>$id,'kind'=>$kind,'_wpnonce'=>wp_create_nonce(self::ACTION.'_download_'.$id)),admin_url('admin-post.php'));
    }
    public static function present(array $job,int $page=1): array
    {
        $out=Store::summary($job);$total=count($job['items']);$page=max(1,min((int)ceil(max(1,$total)/20),$page));$out['page']=$page;$out['pages']=max(1,(int)ceil($total/20));$out['rows']=array();
        $labels=array('publish'=>__('已发布', 'mao-tk-zibll-toolkit'),'draft'=>__('草稿', 'mao-tk-zibll-toolkit'),'pending'=>__('待审核', 'mao-tk-zibll-toolkit'),'private'=>__('私密', 'mao-tk-zibll-toolkit'),'future'=>__('定时', 'mao-tk-zibll-toolkit'));
        foreach(array_slice($job['items'],($page-1)*20,20,true) as $i=>$p)$out['rows'][]=array('name'=>$p['title']!==''?$p['title']:__('（无标题）', 'mao-tk-zibll-toolkit'),'url'=>$p['slug']!==''?$p['slug']:__('来源 ID ', 'mao-tk-zibll-toolkit').$p['key'],'visibility'=>$labels[$p['status']]??$p['status'],'logo'=>(($p['config']['pay_modo']??'0')==='points'?__('积分 ', 'mao-tk-zibll-toolkit'):__('金额 ', 'mao-tk-zibll-toolkit')).($p['config']['start_price']??'0').' · '.count($p['assets']).__(' 图', 'mao-tk-zibll-toolkit'),'plan'=>$job['preview'][$i]['action']??'export','reason'=>$job['preview'][$i]['reason']??'','result'=>$job['results'][$i]??null);
        foreach ($out['rows'] as &$row) {
            if (isset($row['result']['message'])) {
                $row['result']['message'] = translate($row['result']['message'], 'mao-tk-zibll-toolkit');
            }
            if ($row['reason'] !== '') { $row['reason'] = translate($row['reason'], 'mao-tk-zibll-toolkit'); }
        }
        unset($row);
        $out['report']=$out['status']!=='expired'?self::download_url($job['id'],'report'):'';
        $out['download']=$out['status']==='complete'&&$job['type']==='export'&&$out['counts']['success']?self::download_url($job['id'],'zip'):'';
        $out['sha256']=$job['sha256']??'';$out['partial']=!empty($job['partial']);
        $out['counter']=$out['done'].' / '.$total;
        $out['percent']=$total?$out['done']*100/$total:0;
        $out['description']=$job['status']==='preview'?__('请核对新增、跳过与覆盖计划。确认后才会写入商品和图片。', 'mao-tk-zibll-toolkit'):__('逐件处理，已完成记录不会重复新增。', 'mao-tk-zibll-toolkit');
        if($job['type']==='import'&&$job['status']!=='complete'){
            $out['percent']=30+($total?$out['done']*40/$total:0);
            if(($job['phase']??'')==='media'){$n=count($job['media_queue']);$out['percent']=$n?$job['media_cursor']*30/$n:0;$out['counter']=__('图片 ', 'mao-tk-zibll-toolkit').$job['media_cursor'].' / '.$n;$out['description']=__('逐张处理本地图片，完成后再写商品。已成功的图片会直接复用。', 'mao-tk-zibll-toolkit');}
            if(($job['phase']??'')==='references'){$out['percent']=70+($total?$job['ref_cursor']*30/$total:0);$out['counter']=__('关联 ', 'mao-tk-zibll-toolkit').$job['ref_cursor'].' / '.$total;$out['description']=__('商品已写入，正在核对图片与本批商品的已知关联；完成后再结束任务。', 'mao-tk-zibll-toolkit');}
            if($job['status']==='preview'){$out['percent']=0;$out['counter']='0 / '.$total;$out['description']=__('请核对新增、跳过与覆盖计划。确认后才会写入商品和图片。', 'mao-tk-zibll-toolkit');}
        }
        if($job['status']==='complete')$out['percent']=100;if($job['status']==='invalid')$out['rows']=array();return $out;
    }
    public function ajax(): void
    {
        try{
            Products::authorize();if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new \RuntimeException(__('请使用 POST。', 'mao-tk-zibll-toolkit'));check_ajax_referer(self::ACTION,'nonce');
            $op=is_string($_POST['operation']??null)?$_POST['operation']:'';
            if($op==='availability'){wp_send_json_success(array('active'=>$this->active()&&!self::errors(),'nonce'=>wp_create_nonce(self::ACTION)));}
            if($op==='history')wp_send_json_success(Store::history());
            if(!in_array($op,array('inspect'),true)){if(!$this->active())throw new \RuntimeException(__('商品迁移模块未运行。', 'mao-tk-zibll-toolkit'));$errors=self::errors();if($errors)throw new \RuntimeException(implode(' ',$errors));}
            if($op==='export'){
                $ids=is_string($_POST['ids']??null)?trim(wp_unslash($_POST['ids'])):'';
                if($ids!==''&&!preg_match('/^\d+(?:[\s,，]+\d+)*$/uD',$ids))throw new \RuntimeException(__('商品 ID 请以逗号或空格分隔。', 'mao-tk-zibll-toolkit'));
                $ids=$ids!==''?array_values(array_unique(array_map('intval',preg_split('/[\s,，]+/u',$ids)))):array();if(count($ids)>Package::MAX_POSTS||in_array(0,$ids,true))throw new \RuntimeException(__('商品 ID 数量或取值无效。', 'mao-tk-zibll-toolkit'));
                $status=is_string($_POST['status']??null)?$_POST['status']:'';if(!in_array($status,array('','publish','draft','pending','private','future'),true))throw new \RuntimeException(__('商品状态无效。', 'mao-tk-zibll-toolkit'));
                $after=is_string($_POST['after']??null)?$_POST['after']:'';$before=is_string($_POST['before']??null)?$_POST['before']:'';
                foreach(array($after,$before) as $date)if($date!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$d)||!checkdate((int)$d[2],(int)$d[3],(int)$d[1])))throw new \RuntimeException(__('日期无效。', 'mao-tk-zibll-toolkit'));
                if($after!==''&&$before!==''&&$after>$before)throw new \RuntimeException(__('起始日期不能晚于结束日期。', 'mao-tk-zibll-toolkit'));
                $category=is_scalar($_POST['category']??null)?absint($_POST['category']):0;if($category&&!term_exists($category,'shop_cat'))throw new \RuntimeException(__('分类不存在。', 'mao-tk-zibll-toolkit'));
                $job=Products::export(array('ids'=>$ids,'category'=>$category,'status'=>$status,'after'=>$after,'before'=>$before),true,!empty($_POST['meta']));
            }elseif($op==='upload'){
                if(($_POST['source_mode']??'upload')==='server'){$name=is_string($_POST['serverfile']??null)?wp_unslash($_POST['serverfile']):'';$file=self::server_file($name);}
                else{$f=$_FILES['package']??null;if(!is_array($f)||($f['error']??-1)!==UPLOAD_ERR_OK||!is_string($f['name']??null)||!preg_match('/\.zip$/iD',$f['name'])||!is_uploaded_file($f['tmp_name']??''))throw new \RuntimeException(__('未收到有效 ZIP，请检查上传上限或改用私有服务器收件目录。', 'mao-tk-zibll-toolkit'));$file=$f['tmp_name'];}
                $policy=is_string($_POST['policy']??null)?$_POST['policy']:'skip';$source=is_string($_POST['legacy_source']??null)?trim(wp_unslash($_POST['legacy_source'])):'';
                $job=Products::prepare($file,$policy,true,$source);
            }else{
                $id=is_string($_POST['job']??null)?$_POST['job']:'';
                if($op==='inspect')$job=Store::load($id,true);
                elseif(in_array($op,array('start','step','pause','retry'),true)){if(!is_scalar($_POST['revision']??null)||!ctype_digit((string)$_POST['revision']))throw new \RuntimeException(__('任务版本无效。', 'mao-tk-zibll-toolkit'));$job=Products::command($id,$op,(int)$_POST['revision']);}
                else throw new \RuntimeException(__('操作无效。', 'mao-tk-zibll-toolkit'));
            }
            $page=is_scalar($_POST['page_number']??null)?absint($_POST['page_number']):1;wp_send_json_success(self::present($job,$page));
        }catch(\Throwable $e){wp_send_json_error(array('message'=>translate($e->getMessage(), 'mao-tk-zibll-toolkit')),400);}
    }
    public function download(): void
    {
        try{
            Products::authorize();$id=is_string($_GET['job']??null)?$_GET['job']:'';check_admin_referer(self::ACTION.'_download_'.$id);$job=Store::load($id);
            nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');
            if(($_GET['kind']??'')==='zip'&&$job['type']==='export'&&$job['status']==='complete'){
                $file=Store::dir($id).'/products.zip';if(!is_file($file)||!hash_equals($job['sha256'],(string)hash_file('sha256',$file)))throw new \RuntimeException(__('ZIP 已失效。', 'mao-tk-zibll-toolkit'));
                header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="maotk-zibll-products-'.gmdate('Ymd-His',$job['created']).(!empty($job['partial'])?'-partial':'').'.zip"');header('Content-Length: '.filesize($file));readfile($file);exit;
            }
            if(($_GET['kind']??'')!=='report')throw new \RuntimeException(__('下载类型无效。', 'mao-tk-zibll-toolkit'));
            header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="maotk-zibll-products-report-'.$id.'.json"');
            echo wp_json_encode(array('summary'=>Store::summary($job),'results'=>$job['results'],'before_update'=>$job['backups'],'sha256'=>$job['sha256']??''),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
        }catch(\Throwable $e){wp_die(esc_html(translate($e->getMessage(), 'mao-tk-zibll-toolkit')),'',array('response'=>403));}
    }
    public function render(): void
    {
        Products::authorize();$errors=self::errors();$active=$this->active()&&!$errors;$categories=get_terms(array('taxonomy'=>'shop_cat','hide_empty'=>false));if(is_wp_error($categories))$categories=array();
        $serverfiles=array();$inbox='';try{$inbox=self::inbox();foreach((array)glob($inbox.'/*.zip') as $f){if(!is_link($f)&&is_file($f)&&filesize($f)<=Package::MAX_ZIP)$serverfiles[]=basename($f);if(count($serverfiles)>=100)break;}}catch(\Throwable $e){$errors[]=translate($e->getMessage(), 'mao-tk-zibll-toolkit');}
        $module=array('id'=>Products::MODULE,'name'=>__('商品导入&导出', 'mao-tk-zibll-toolkit'),'enabled'=>false,'status'=>'disabled');foreach($this->manager->all() as $r)if($r['id']===Products::MODULE)$module=$r;$module['name']=__('商品导入&导出', 'mao-tk-zibll-toolkit');
        require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/migration-products.php';
    }
}
