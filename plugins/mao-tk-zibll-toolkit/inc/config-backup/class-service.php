<?php
namespace MaoTK\ZibllToolkit\ConfigBackup;
defined('ABSPATH') || exit;

final class Service
{
    public static function make(array $ids,bool $images): array
    {
        $sections=Registry::collect($ids);$up=wp_upload_dir();$p=array('format'=>'maotk-toolkit-settings','schema'=>1,'toolkit_version'=>MAO_TK_ZIBLL_TOOLKIT_VERSION,'created'=>gmdate('c'),'source'=>array('site'=>home_url('/'),'plugin_url'=>MAO_TK_ZIBLL_TOOLKIT_URL,'uploads_url'=>$up['baseurl']),'sections'=>$sections,'users'=>Registry::portable_users($sections),'assets'=>Assets::collect($sections,$images));
        $p['checksum']=hash('sha256',wp_json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return self::validate($p);
    }
    public static function validate(array $p): array
    {
        if(array_diff(array_keys($p),array('format','schema','toolkit_version','created','source','sections','users','assets','checksum'))||($p['format']??'')!=='maotk-toolkit-settings'||($p['schema']??0)!==1||!is_array($p['sections']??null)||!is_array($p['users']??null)||!is_array($p['source']??null))throw new \RuntimeException('不是支持的 Toolkit 配置备份。');
        if(!is_string($p['toolkit_version']??null)||!preg_match('/^\d+\.\d+\.\d+(?:-rc\.\d+)?$/D',$p['toolkit_version'])||version_compare($p['toolkit_version'],MAO_TK_ZIBLL_TOOLKIT_VERSION,'>'))throw new \RuntimeException('备份来自更新的插件版本，请先核对并升级插件。');
        foreach(array('site','plugin_url','uploads_url') as $key)if(!is_string($p['source'][$key]??null)||strlen($p['source'][$key])>2048||!\MaoTK\ZibllToolkit\SystemMail\Settings::url($p['source'][$key]))throw new \RuntimeException('来源信息无效。');
        $hash=$p['checksum']??'';unset($p['checksum']);if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals($hash,hash('sha256',wp_json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))))throw new \RuntimeException('配置文件校验失败，可能已损坏或被编辑。');$p['checksum']=$hash;
        Registry::select(array_keys($p['sections']));foreach($p['sections'] as $id=>$value)Registry::clean($id,$value);Assets::validate($p['assets']??null);return $p;
    }
    public static function rows(array $p): array
    {
        $rows=array();foreach($p['sections'] as $id=>$value){$error='';try{Registry::map_users(array($id=>$value),$p['users']);Assets::check(array($id=>$value),$p['assets']);}catch(\Throwable $e){$error=$e->getMessage();}$rows[]=array('id'=>$id,'label'=>Registry::catalog()[$id]['label'],'error'=>$error,'selected'=>$error===''&&$id!=='modules');}return $rows;
    }
    public static function export(array $ids,bool $images): array{$lock=Store::lock();try{return Store::create('export',self::make($ids,$images));}finally{delete_option($lock);}}
    public static function preview(string $bytes): array
    {
        if(strlen($bytes)>33554432)throw new \RuntimeException('文件超过 32 MiB。');$p=json_decode($bytes,true,48);if(!is_array($p))throw new \RuntimeException('请选择有效的 JSON 配置备份。');$p=self::validate($p);$before=Registry::raw(array_keys($p['sections']));
        $expected=array();foreach($before as $key=>$record)$expected[$key]=Registry::fingerprint($record);
        $lock=Store::lock();try{return Store::create('import',$p,array('expected'=>$expected,'rows'=>self::rows($p)));}finally{delete_option($lock);}
    }
    public static function apply(string $id,array $ids,bool $confirm): array
    {
        if(!$confirm)throw new \RuntimeException('请先确认覆盖所选配置。');$ids=Registry::select($ids);$lock=Store::lock();$written=array();$plan=null;$job=null;$created=array();
        try{
            $job=Store::load($id);if($job['status']==='applied')return $job;if($job['type']!=='import'||$job['status']!=='preview')throw new \RuntimeException('请重新上传并预检后导入。');
            $p=self::validate(Store::read($job,'package'));$sections=array();foreach($ids as $key){if(!isset($p['sections'][$key]))throw new \RuntimeException('备份中没有所选分类。');$sections[$key]=$p['sections'][$key];}
            $raw=Registry::raw($ids);foreach($raw as $key=>$v)if(!isset($job['expected'][$key])||!hash_equals($job['expected'][$key],Registry::fingerprint($v)))throw new \RuntimeException('预检后配置已变更，请重新预检，不会覆盖其他窗口的新设置。');
            $sections=Registry::map_users($sections,$p['users']);Assets::check($sections,$p['assets']);
            $job['selected']=$ids;$job['status']='processing';$job['files']['before']=Store::write($id,'before',array('format'=>'maotk-toolkit-options-snapshot','options'=>$raw));Store::save($job);
            $sections=Assets::restore($sections,$p['assets'],$p['source']['plugin_url'],$created);$job['created_media']=$created;
            $plan=Registry::plan($sections,$ids);if($plan['before']!==$raw)throw new \RuntimeException('准备图片期间配置发生变化，请重新预检。');
            $job['files']['plan']=Store::write($id,'plan',$plan);Store::save($job);
            foreach($plan['after'] as $key=>$v){Registry::write($key,$v,$raw[$key]);$written[]=$key;}
            $job['status']='applied';$job['result']='所选配置已导入，刷新对应设置页后查看。恢复前快照已保留；新图片未重新压缩。';Store::save($job);return $job;
        }catch(\Throwable $e){
            $restored=true;if($plan)foreach(array_reverse($written) as $key){try{Registry::write($key,$plan['before'][$key],$plan['after'][$key]);}catch(\Throwable $rollback){$restored=false;}}
            if($job&&$job['status']==='processing'){$job['created_media']=$created;$job['status']='failed';$job['result']=$e->getMessage().($restored?'；已恢复本次已写入的配置。':'；部分配置被其他窗口修改，未强行覆盖，请核对快照。');try{Store::save($job);}catch(\Throwable $ignored){}}
            throw new \RuntimeException($e->getMessage().($restored?'':' 部分配置需要核对恢复前快照。'));
        }finally{delete_option($lock);}
    }
    public static function undo(string $id,bool $confirm): array
    {
        if(!$confirm)throw new \RuntimeException('请先确认撤销本次导入。');$lock=Store::lock();$done=array();$job=null;$old=null;$now=null;
        try{
            $job=Store::load($id);if($job['status']==='undone')return $job;if(!in_array($job['status'],array('applied','processing','failed'),true)||empty($job['files']['plan']))throw new \RuntimeException('此任务不能恢复，请下载快照核对。');$old=Store::read($job,'before')['options'];$after=Store::read($job,'plan')['after'];$now=Registry::raw($job['selected']);
            foreach($now as $key=>$v)if($job['status']==='applied'?$v!==$after[$key]:($v!==$after[$key]&&$v!==$old[$key]))throw new \RuntimeException('导入后配置已再次修改，未覆盖新修改；请核对恢复前快照。');
            foreach($old as $key=>$v){Registry::write($key,$v,$now[$key]);$done[]=$key;}$job['status']='undone';$job['result']='已撤销本次配置导入。导入的媒体保留，不删除或改动现有文件。';Store::save($job);return $job;
        }catch(\Throwable $e){if($job&&$old&&$now)foreach(array_reverse($done) as $key){try{Registry::write($key,$now[$key],$old[$key]);}catch(\Throwable $ignored){}}throw $e;}finally{delete_option($lock);}
    }
}
