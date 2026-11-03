<?php
namespace MaoTK\ZibllToolkit\CustomFooter;
use MaoTK\ZibllToolkit\Admin;
use MaoTK\ZibllToolkit\ModuleManager;
defined('ABSPATH') || exit;
final class AdminPage
{
    public const SLUG='mao-tk-toolkit-custom-footer';
    public const ACTION='mao_tk_toolkit_custom_footer';
    private $manager;private $admin;private $screen='';
    public function __construct(ModuleManager $manager,Admin $admin) { $this->manager=$manager;$this->admin=$admin; }
    public function register(): void
    {
        add_action('admin_menu',array($this,'menu'),21);add_action('admin_enqueue_scripts',array($this,'enqueue'));
        add_action('wp_ajax_'.self::ACTION,array($this,'ajax'));
    }
    public function menu(): void
    {
        $this->screen=(string)add_submenu_page(Admin::SLUG,'页脚设置','页脚设置','manage_options',self::SLUG,array($this,'render'));
        $this->admin->allow_screen($this->screen);
    }
    public function enqueue(string $hook): void
    {
        if ($hook!==$this->screen||$this->screen===''||!current_user_can('manage_options')) return;
        wp_enqueue_media();
        wp_enqueue_style('mao-tk-footer-admin',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/admin.css',array('mao-tk-zibll-toolkit-admin'),MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-footer-admin',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/admin.js',array('mao-tk-toolkit-disclosure-motion'),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
        wp_add_inline_script('mao-tk-footer-admin','window.MaoTKFooter='.wp_json_encode(array('endpoint'=>admin_url('admin-ajax.php'),'action'=>self::ACTION,'nonce'=>wp_create_nonce(self::ACTION),'assets'=>MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/','valid'=>(new Settings())->read()['valid']),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public function ajax(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'需要管理员权限。'),403);
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') wp_send_json_error(array('message'=>'请使用 POST。'),405);
        check_ajax_referer(self::ACTION,'nonce');
        try {
            $operation=is_string($_POST['operation']??null)?$_POST['operation']:'';$settings=new Settings();
            if ($operation==='read') { $state=$settings->read();wp_send_json_success(array('settings'=>$state['value'],'revision'=>$state['revision'],'valid'=>$state['valid'],'preset'=>Settings::theme_preset())); }
            $json=is_string($_POST['settings']??null)?wp_unslash($_POST['settings']):'';
            if (strlen($json)>65536) throw new \InvalidArgumentException('页脚配置过大。');
            $raw=json_decode($json,true,20);if (!is_array($raw)) throw new \InvalidArgumentException('页脚配置格式无效。');
            $value=Settings::normalize($raw);
            if ($operation==='preview') wp_send_json_success(array('html'=>self::preview($value)));
            if ($operation==='save') { $saved=$settings->save($value,is_string($_POST['revision']??null)?$_POST['revision']:'');wp_send_json_success(array('settings'=>$saved['value'],'revision'=>$saved['revision'])); }
            throw new \InvalidArgumentException('不支持此操作。');
        } catch (\Throwable $error) { wp_send_json_error(array('message'=>$error->getMessage()),400); }
    }
    public static function preview(array $value): string
    {
        ob_start();require __DIR__.'/preview.php';return (string)ob_get_clean();
    }
    public function render(): void
    {
        if (!current_user_can('manage_options')) wp_die('需要管理员权限。','',array('response'=>403));
        $module=array('id'=>Settings::MODULE,'name'=>'自定义页脚','enabled'=>false,'status'=>'disabled');
        foreach ($this->manager->all() as $row) if ($row['id']===Settings::MODULE) $module=$row;
        $state=(new Settings())->read();$valid=$this->manager->storage_is_valid();
        require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/custom-footer-settings.php';
    }
}
