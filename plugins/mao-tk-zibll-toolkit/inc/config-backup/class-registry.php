<?php
namespace MaoTK\ZibllToolkit\ConfigBackup;
use MaoTK\ZibllToolkit\ModuleManager;
use MaoTK\ZibllToolkit\ArticleNote;
defined('ABSPATH') || exit;

/** Explicit configuration allowlist. Never exports arbitrary prefixed options or business tables. */
final class Registry
{
    public static function catalog(): array
    {
        return array(
            'modules'=>array('label'=>__('模块开关', 'mao-tk-zibll-toolkit'),'option'=>ModuleManager::OPTION_KEY),
            'article-note'=>array('label'=>__('正文提示框配色与接入设置', 'mao-tk-zibll-toolkit'),'option'=>ModuleManager::OPTION_KEY),
            'top-info'=>array('label'=>__('文章顶部信息', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\TopInfo\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\TopInfo\Settings::class,'validator'=>'validate'),
            'freshness'=>array('label'=>__('文章时效提示', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\Freshness\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\Freshness\Settings::class,'validator'=>'validate'),
            'showcase'=>array('label'=>__('案例展示设置与案例库', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\Showcase\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\Showcase\Settings::class,'validator'=>'normalize'),
            'private-links'=>array('label'=>__('私密链接可见性规则', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\PrivateLinks\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\PrivateLinks\Settings::class,'validator'=>'validate'),
            'maintenance'=>array('label'=>__('维护页面设置', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\Maintenance\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\Maintenance\Settings::class,'validator'=>'normalize'),
            'profile-presets'=>array('label'=>__('预设头像与背景', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\ProfilePresets\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\ProfilePresets\Settings::class,'validator'=>'normalize'),
            'order-query'=>array('label'=>__('订单查询页面设置', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\OrderQuery\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\OrderQuery\Settings::class,'validator'=>'normalize'),
            'not-found'=>array('label'=>__('404 公益页面设置', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\NotFound\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\NotFound\Settings::class,'validator'=>'normalize'),
            'custom-footer'=>array('label'=>__('自定义页脚与社交图标', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\CustomFooter\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\CustomFooter\Settings::class,'validator'=>'normalize'),
            'archive-header'=>array('label'=>__('归档页顶部', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\ArchiveHeader\Settings::OPTION,'class'=>\MaoTK\ZibllToolkit\ArchiveHeader\Settings::class,'validator'=>'normalize'),
            'system-mail'=>array('label'=>__('系统邮件外观与各类模板', 'mao-tk-zibll-toolkit'),'option'=>\MaoTK\ZibllToolkit\SystemMail\Settings::KEY,'class'=>\MaoTK\ZibllToolkit\SystemMail\Settings::class,'validator'=>'validate'),
        );
    }
    public static function modules(): array
    {
        return array_merge(array('welcome-note','top-info','article-freshness'),array_keys(ModuleManager::defaults()['modules']));
    }
    public static function select($ids): array
    {
        if(!is_array($ids)||!$ids||count($ids)>count(self::catalog()))throw new \InvalidArgumentException(__('请选择至少一类配置。', 'mao-tk-zibll-toolkit'));
        foreach($ids as $id)if(!is_string($id)||!isset(self::catalog()[$id]))throw new \InvalidArgumentException(__('配置分类无效。', 'mao-tk-zibll-toolkit'));
        return array_values(array_unique($ids));
    }
    public static function clean(string $id,$value): array
    {
        if(!is_array($value))throw new \InvalidArgumentException(__('配置内容必须是对象。', 'mao-tk-zibll-toolkit'));
        if($id==='modules'){
            if(array_diff(array_keys($value),self::modules()))throw new \InvalidArgumentException(__('备份中包含当前版本不支持的模块。', 'mao-tk-zibll-toolkit'));
            foreach($value as $flag)if(!is_bool($flag))throw new \InvalidArgumentException(__('模块开关格式无效。', 'mao-tk-zibll-toolkit'));return $value;
        }
        if($id==='article-note'){
            if(array_diff(array_keys($value),array('settings','provider'))||!in_array($value['provider']??'',array('toolkit','coexist'),true))throw new \InvalidArgumentException(__('提示框提供者配置无效。', 'mao-tk-zibll-toolkit'));
            $clean=ArticleNote\Settings::validate($value['settings']??null);if($clean===null)throw new \InvalidArgumentException(__('提示框配置无效。', 'mao-tk-zibll-toolkit'));return array('settings'=>$clean,'provider'=>$value['provider']);
        }
        $entry=self::catalog()[$id]??null;if(!$entry)throw new \InvalidArgumentException(__('未知配置项。', 'mao-tk-zibll-toolkit'));
        $clean=call_user_func(array($entry['class'],$entry['validator']),$value);
        if(is_wp_error($clean)||$clean===null||!is_array($clean))throw new \InvalidArgumentException($entry['label'].__('格式无效。', 'mao-tk-zibll-toolkit'));
        $allowed=array_keys($clean);if($id==='showcase')$allowed[]='revision';
        if(array_diff(array_keys($value),$allowed))throw new \InvalidArgumentException($entry['label'].__('含有未知字段。', 'mao-tk-zibll-toolkit'));
        return $clean;
    }
    public static function collect(array $ids): array
    {
        $out=array();$manager=get_option(ModuleManager::OPTION_KEY,ModuleManager::defaults());
        foreach(self::select($ids) as $id){
            if($id==='modules'){
                if(!(new ModuleManager())->storage_is_valid())throw new \RuntimeException(__('当前模块开关格式异常，不能导出。', 'mao-tk-zibll-toolkit'));
                $flags=array();foreach(self::modules() as $key)$flags[$key]=$manager['modules'][$key]??(ModuleManager::defaults()['modules'][$key]??false);$out[$id]=self::clean($id,$flags);
            }elseif($id==='article-note'){
                $s=(new ArticleNote\Settings())->read();if(!$s['valid'])throw new \RuntimeException(__('当前提示框设置异常。', 'mao-tk-zibll-toolkit'));$out[$id]=self::clean($id,array('settings'=>$s['value'],'provider'=>$s['provider']));
            }else{
                $entry=self::catalog()[$id];$s=$id==='system-mail'?\MaoTK\ZibllToolkit\SystemMail\Settings::read():(new $entry['class']())->read();
                if(!$s['valid'])throw new \RuntimeException($entry['label'].__('当前格式异常，未导出默认值代替原配置。', 'mao-tk-zibll-toolkit'));
                $out[$id]=self::clean($id,$s['value']);
            }
        }return $out;
    }
    public static function raw(array $ids): array
    {
        $records=array();foreach(self::select($ids) as $id){$key=self::catalog()[$id]['option'];$missing='mao-missing-'.wp_generate_uuid4();$v=get_option($key,$missing);self::json_value($v);$records[$key]=array('exists'=>$v!==$missing,'value'=>$v===$missing?null:$v);}return $records;
    }
    private static function json_value($v): void
    {
        if(is_array($v)){foreach($v as $item)self::json_value($item);}elseif(is_object($v)||is_resource($v))throw new \RuntimeException(__('当前配置包含非 JSON 数据，已停止恢复，避免丢失原值。', 'mao-tk-zibll-toolkit'));
        if(json_encode($v)===false)throw new \RuntimeException(__('当前配置编码异常，已停止恢复，避免丢失原值。', 'mao-tk-zibll-toolkit'));
    }
    public static function fingerprint(array $raw): string{return hash('sha256',wp_json_encode($raw));}
    public static function portable_users(array $sections): array
    {
        $users=array();if(!isset($sections['private-links']))return $users;
        foreach($sections['private-links']['user_overrides'] as $id=>$decision){$u=get_userdata($id);if(!$u)throw new \RuntimeException(__('私密链接规则中存在已删除账号，请先清理该规则。', 'mao-tk-zibll-toolkit'));$users[(string)$id]=array('login'=>$u->user_login,'email_hash'=>hash('sha256',strtolower($u->user_email)));}
        return $users;
    }
    public static function map_users(array $sections,array $users): array
    {
        if(!isset($sections['private-links']))return $sections;
        foreach($sections['private-links']['allowed_roles'] as $role)if(!isset(wp_roles()->roles[$role]))throw new \RuntimeException(__('目标站不存在权限角色：', 'mao-tk-zibll-toolkit').$role.__('；请取消此分类或先配置相应角色。', 'mao-tk-zibll-toolkit'));
        $rules=array();foreach($sections['private-links']['user_overrides'] as $id=>$decision){$identity=$users[$id]??null;
            if(!is_array($identity)||!is_string($identity['login']??null)||strlen($identity['login'])>180||!is_string($identity['email_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$identity['email_hash']))throw new \RuntimeException(__('账号规则缺少可核对的身份信息。', 'mao-tk-zibll-toolkit'));
            $u=get_user_by('login',$identity['login']);if(!$u||!hash_equals($identity['email_hash'],hash('sha256',strtolower($u->user_email))))throw new \RuntimeException(__('账号“', 'mao-tk-zibll-toolkit').$identity['login'].__('”无法核对身份；不会按旧用户 ID 套用规则。请取消此分类或先核对目标账号。', 'mao-tk-zibll-toolkit'));$rules[$u->ID]=$decision;
        }$sections['private-links']['user_overrides']=$rules;return $sections;
    }
    public static function plan(array $sections,array $ids): array
    {
        if(in_array('article-note',$ids,true)&&!in_array('modules',$ids,true)&&!(new ModuleManager())->storage_is_valid())throw new \RuntimeException(__('工作台开关配置异常，请同时勾选模块开关后重新预检，避免隐式重置开关。', 'mao-tk-zibll-toolkit'));
        $raw=self::raw($ids);$next=$raw;
        foreach(self::select($ids) as $id){if(!isset($sections[$id]))throw new \RuntimeException(__('备份中没有所选配置。', 'mao-tk-zibll-toolkit'));$value=self::clean($id,$sections[$id]);$key=self::catalog()[$id]['option'];
            if(in_array($id,array('modules','article-note'),true)){
                $current=is_array($next[$key]['value'])?$next[$key]['value']:ModuleManager::defaults();$current['schema_version']=1;
                if($id==='modules')$current['modules']=array_replace(is_array($current['modules']??null)?$current['modules']:array(),$value);
                else{$current[ArticleNote\Settings::FIELD]=$value['settings'];$current[ArticleNote\Settings::PROVIDER_FIELD]=$value['provider'];}
                foreach(($current['modules']??array()) as $module=>$flag)if(!is_string($module)||!preg_match('/^[a-z][a-z0-9-]{0,63}$/D',$module)||!is_bool($flag))throw new \RuntimeException(__('工作台仍含无效的扩展开关，请先核对原配置。', 'mao-tk-zibll-toolkit'));$value=$current;
            }elseif($id==='showcase')$value['revision']=wp_generate_uuid4();
            elseif(in_array($id,array('profile-presets','order-query','not-found'),true)){$revision=max(0,(int)($raw[$key]['value']['revision']??0));if($revision>=PHP_INT_MAX-1)throw new \RuntimeException(__('配置修订号超出范围。', 'mao-tk-zibll-toolkit'));$value['revision']=$revision+1;}
            $next[$key]=array('exists'=>true,'value'=>$value);
        }return array('before'=>$raw,'after'=>$next);
    }
    /** Compare-and-swap every option; never write arbitrary keys from an uploaded file. */
    public static function write(string $key,array $wanted,array $expected): void
    {
        $allow=array_column(self::catalog(),'option');if(!in_array($key,$allow,true))throw new \RuntimeException(__('配置键不在允许范围内。', 'mao-tk-zibll-toolkit'));
        $missing='mao-missing-'.wp_generate_uuid4();$v=get_option($key,$missing);$now=array('exists'=>$v!==$missing,'value'=>$v===$missing?null:$v);if($now!==$expected)throw new \RuntimeException(__('配置已被其他窗口修改，请重新预检。', 'mao-tk-zibll-toolkit'));if($now===$wanted)return;
        global $wpdb;
        if(!$expected['exists'])$ok=add_option($key,$wanted['value'],'',false);
        elseif(!$wanted['exists'])$ok=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,maybe_serialize($expected['value'])))===1;
        else $ok=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s",maybe_serialize($wanted['value']),$key,maybe_serialize($expected['value'])))===1;
        wp_cache_delete($key,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');
        if(!$ok)throw new \RuntimeException(__('配置写入失败，原记录可能已被其他窗口更新。', 'mao-tk-zibll-toolkit'));
    }
}
