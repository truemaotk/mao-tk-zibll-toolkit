<?php
namespace MaoTK\ZibllToolkit\SystemMail;
use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;

final class AdminPage
{
    public const SLUG='mao-tk-toolkit-system-mail';
    public const ACTION='mao_tk_toolkit_system_mail';
    private $manager;private $admin;private $screen='';
    public function __construct(ModuleManager $manager,Admin $admin){$this->manager=$manager;$this->admin=$admin;}
    public function register(): void{add_action('admin_menu',array($this,'menu'),21);add_action('admin_enqueue_scripts',array($this,'enqueue'));add_action('wp_ajax_'.self::ACTION,array($this,'ajax'));}
    public function menu(): void{$this->screen=(string)add_submenu_page(Admin::SLUG,'系统邮件外观','系统邮件外观','manage_options',self::SLUG,array($this,'render'));$this->admin->allow_screen($this->screen);}
    public function enqueue(string $hook): void
    {
        if($hook!==$this->screen||$this->screen===''||!current_user_can('manage_options'))return;
        wp_enqueue_media();wp_enqueue_style('mao-tk-system-mail',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/system-mail/admin.css',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-system-mail',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/system-mail/admin.js',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
        $state=Settings::read();$u=wp_get_current_user();
        $payload=array('state'=>$state,'fields'=>Settings::fields(),'types'=>Catalog::all(),'groups'=>Settings::groups(),'commonVariables'=>Catalog::variables('common'),'inventory'=>Source::inventory(),'recipient'=>$u->user_email);
        wp_add_inline_script('mao-tk-system-mail','window.MaoTKSystemMail='.wp_json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public function ajax(): void
    {
        try{
            if(!current_user_can('manage_options'))wp_send_json_error(array('message'=>'需要管理员权限。'),403);
            if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new \RuntimeException('请使用 POST。');check_ajax_referer(self::ACTION,'nonce');
            $operation=is_string($_POST['operation']??null)?$_POST['operation']:'';
            if($operation==='read')wp_send_json_success(Settings::read());
            $json=is_string($_POST['settings']??null)?wp_unslash($_POST['settings']):'';if(strlen($json)>262144)throw new \InvalidArgumentException('设置内容过大。');$settings=Settings::validate(json_decode($json,true,32));
            if($operation==='save'){
                $revision=is_string($_POST['revision']??null)?$_POST['revision']:'';wp_send_json_success(Settings::save($settings,$revision));
            }
            $type=is_string($_POST['type']??null)?$_POST['type']:'';if(!isset(Catalog::all()[$type]))throw new \InvalidArgumentException('请选择已支持的邮件类型。');$common=($_POST['common_preview']??'0')==='1';
            if($operation==='preview'){nocache_headers();wp_send_json_success(Runtime::structure_preview($type,$settings,$common));}
            if($operation==='test'){
                if(!Settings::read()['valid'])throw new \RuntimeException('已保存配置格式异常，请先核对。');
                $to=is_string($_POST['recipient']??null)?trim(wp_unslash($_POST['recipient'])):'';if(!is_email($to)||preg_match('/[\r\n,;]/',$to))throw new \InvalidArgumentException('请输入一个有效测试邮箱。');
                $key='mao_tk_system_mail_test_'.get_current_user_id();if(get_transient($key))throw new \RuntimeException('请等待 60 秒再发送下一封测试邮件。');set_transient($key,1,60);
                $sent=Runtime::test($to,$type,$settings,$common);if(!$sent)throw new \RuntimeException('wp_mail 未接受测试邮件，请检查原系统 SMTP 配置；本模块未修改 SMTP。');
                wp_send_json_success(array('message'=>'测试邮件已交给原邮件发送系统，请到目标邮箱检查实际收件效果。'));
            }throw new \InvalidArgumentException('操作不支持。');
        }catch(\Throwable $e){wp_send_json_error(array('message'=>$e->getMessage()),400);}
    }
    public function render(): void
    {
        if(!current_user_can('manage_options'))wp_die('需要管理员权限。','',array('response'=>403));
        $module=array('id'=>Catalog::MODULE,'name'=>'系统邮件外观','enabled'=>false,'status'=>'disabled');foreach($this->manager->all() as $row)if($row['id']===Catalog::MODULE)$module=$row;
        $state=Settings::read();$inventory=Source::inventory();$valid=$this->manager->storage_is_valid();require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/system-mail-settings.php';
    }
}
