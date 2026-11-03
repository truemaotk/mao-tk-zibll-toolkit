<?php
namespace MaoTK\ZibllToolkit\ConfigBackup;
use MaoTK\ZibllToolkit\Admin;
defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG='mao-tk-toolkit-config-backup';
    public const ACTION='mao_tk_toolkit_config_backup';
    public const DOWNLOAD='mao_tk_toolkit_config_download';
    private $admin;private $screen='';
    public function __construct(Admin $admin){$this->admin=$admin;}
    public function register(): void{add_action('admin_menu',array($this,'menu'),21);add_action('admin_enqueue_scripts',array($this,'enqueue'));add_action('wp_ajax_'.self::ACTION,array($this,'ajax'));add_action('admin_post_'.self::DOWNLOAD,array($this,'download'));}
    public function menu(): void{$this->screen=(string)add_submenu_page(Admin::SLUG,'配置导入&导出','配置导入&导出','manage_options',self::SLUG,array($this,'render'));$this->admin->allow_screen($this->screen);}
    public function enqueue(string $hook): void
    {
        if($hook!==$this->screen||$this->screen===''||!current_user_can('manage_options'))return;
        wp_enqueue_style('mao-tk-config-backup',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/config-backup/admin.css',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-config-backup',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/config-backup/admin.js',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
    }
    public static function availability(): array
    {
        try{Store::root();return array('available'=>true,'message'=>'');}catch(\Throwable $e){return array('available'=>false,'message'=>$e->getMessage());}
    }
    private static function summary(array $job): array
    {
        $p=Store::read($job,'package');$out=array_intersect_key($job,array_flip(array('id','type','status','result','rows','created')));$out['date']=wp_date('Y-m-d H:i:s',$job['created']);$out['source']=$p['source']['site'];$out['version']=$p['toolkit_version'];$out['images']=count($p['assets']);$out['download']=self::url($job['id'],'package');$out['before']=empty($job['files']['before'])?'':self::url($job['id'],'before');$out['canUndo']=in_array($job['status'],array('applied','processing','failed'),true)&&!empty($job['files']['plan']);return $out;
    }
    public static function url(string $id,string $kind): string{return add_query_arg(array('action'=>self::DOWNLOAD,'id'=>$id,'kind'=>$kind,'_wpnonce'=>wp_create_nonce(self::DOWNLOAD.'-'.$id.'-'.$kind)),admin_url('admin-post.php'));}
    private static function selection(): array
    {
        $json=is_string($_POST['sections']??null)?wp_unslash($_POST['sections']):'';if(strlen($json)>2048)throw new \InvalidArgumentException('选择范围过大。');return Registry::select(json_decode($json,true,4));
    }
    public function ajax(): void
    {
        if(!current_user_can('manage_options'))wp_send_json_error(array('message'=>'需要管理员权限。'),403);
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_send_json_error(array('message'=>'请使用 POST。'),405);check_ajax_referer(self::ACTION,'nonce');
        try{
            $op=is_string($_POST['operation']??null)?$_POST['operation']:'';$id=is_string($_POST['id']??null)?$_POST['id']:'';
            if($op==='history'){$rows=array();foreach(Store::history() as $job){try{$rows[]=self::summary($job);}catch(\Throwable $e){}}wp_send_json_success(array('jobs'=>$rows));}
            if($op==='inspect')wp_send_json_success(self::summary(Store::load($id)));
            if($op==='export')wp_send_json_success(self::summary(Service::export(self::selection(),($_POST['images']??'1')==='1')));
            if($op==='preview'){
                $f=$_FILES['package']??null;if(!is_array($f)||($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']??''))throw new \RuntimeException('请选择 JSON 配置文件，并确认没有超过服务器上传限制。');
                if(!preg_match('/\.json$/i',(string)($f['name']??''))||filesize($f['tmp_name'])>33554432)throw new \RuntimeException('只接受 32 MiB 以内的 JSON 配置备份。');wp_send_json_success(self::summary(Service::preview((string)file_get_contents($f['tmp_name']))));
            }
            if($op==='apply')wp_send_json_success(self::summary(Service::apply($id,self::selection(),($_POST['confirm']??'')==='1')));
            if($op==='undo')wp_send_json_success(self::summary(Service::undo($id,($_POST['confirm']??'')==='1')));
            if($op==='recover'){Store::recover();wp_send_json_success(array('message'=>'过期任务锁已恢复，请从记录中查看任务，再核对或恢复快照。'));}
            throw new \RuntimeException('操作不支持。');
        }catch(\Throwable $e){wp_send_json_error(array('message'=>$e->getMessage()),400);}
    }
    public function download(): void
    {
        if(!current_user_can('manage_options'))wp_die('需要管理员权限。','',array('response'=>403));
        $id=is_string($_GET['id']??null)?$_GET['id']:'';$kind=is_string($_GET['kind']??null)?$_GET['kind']:'';if(!in_array($kind,array('package','before'),true))wp_die('文件类型无效。','',array('response'=>400));check_admin_referer(self::DOWNLOAD.'-'.$id.'-'.$kind);
        try{$job=Store::load($id);$file=Store::file($job,$kind);}catch(\Throwable $e){wp_die(esc_html($e->getMessage()),'',array('response'=>400));}
        nocache_headers();header('Content-Type: application/json; charset=UTF-8');header('X-Content-Type-Options: nosniff');header('X-Robots-Tag: noindex, nofollow');header('Content-Disposition: attachment; filename="maotk-toolkit-'.($kind==='before'?'before-import-snapshot':'settings').'-'.gmdate('Ymd-His',$job['created']).'-'.substr($id,0,8).'.json"');header('Content-Length: '.filesize($file));readfile($file);exit;
    }
    public function render(): void
    {
        if(!current_user_can('manage_options'))wp_die('需要管理员权限。','',array('response'=>403));$available=self::availability();require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/config-backup.php';
    }
}
