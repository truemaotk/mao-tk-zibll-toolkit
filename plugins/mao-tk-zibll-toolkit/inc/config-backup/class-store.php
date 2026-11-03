<?php
namespace MaoTK\ZibllToolkit\ConfigBackup;
defined('ABSPATH') || exit;

final class Store
{
    public const PREFIX='mao_tk_toolkit_config_job_';
    public const INDEX='mao_tk_toolkit_config_jobs';
    public const TTL=604800;
    public static function root(): string
    {
        $root=\MaoTK\ZibllToolkit\Migration\Store::root().'/toolkit-settings';
        if(is_link($root))throw new \RuntimeException('配置备份目录不能是符号链接。');if(!is_dir($root)&&!mkdir($root,0700))throw new \RuntimeException('无法创建站外配置备份目录。');return $root;
    }
    public static function write(string $id,string $kind,array $data): string
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!in_array($kind,array('package','before','plan'),true))throw new \RuntimeException('备份文件标识无效。');
        $bytes=wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($bytes)||strlen($bytes)>33554432)throw new \RuntimeException('配置文件超过 32 MiB，请分分类导出。');
        $file=self::root().'/'.$id.'-'.$kind.'.json';$h=@fopen($file,'x');if(!$h)throw new \RuntimeException('备份文件已存在或无法创建。');$written=0;try{while($written<strlen($bytes)){$n=fwrite($h,substr($bytes,$written,1048576));if(!$n)throw new \RuntimeException('备份文件写入不完整。');$written+=$n;}}finally{fclose($h);}@chmod($file,0600);return hash('sha256',$bytes);
    }
    public static function file(array $job,string $kind): string
    {
        if(!in_array($kind,array('package','before','plan'),true)||empty($job['files'][$kind]))throw new \RuntimeException('该文件不存在。');$file=self::root().'/'.$job['id'].'-'.$kind.'.json';
        if(is_link($file)||!is_file($file)||filesize($file)>33554432||!hash_equals($job['files'][$kind],hash_file('sha256',$file)))throw new \RuntimeException('备份文件缺失或校验失败。');return $file;
    }
    public static function read(array $job,string $kind): array{$data=json_decode((string)file_get_contents(self::file($job,$kind)),true,48);if(!is_array($data))throw new \RuntimeException('备份文件内容异常。');return $data;}
    public static function create(string $type,array $data,array $extra=array()): array
    {
        $id=bin2hex(random_bytes(16));$job=array_merge(array('id'=>$id,'owner'=>get_current_user_id(),'created'=>time(),'expires'=>time()+self::TTL,'type'=>$type,'status'=>$type==='export'?'exported':'preview','files'=>array(),'result'=>''),$extra);
        $job['files']['package']=self::write($id,'package',$data);if(!add_option(self::PREFIX.$id,$job,'',false))throw new \RuntimeException('任务记录保存失败。');
        $index=(array)get_user_meta(get_current_user_id(),self::INDEX,true);array_unshift($index,$id);$index=array_values(array_unique($index));
        foreach(array_slice($index,10) as $old){if(!is_string($old)||!preg_match('/^[a-f0-9]{32}$/D',$old))continue;$record=get_option(self::PREFIX.$old);if(!is_array($record)||($record['owner']??0)!==get_current_user_id())continue;foreach(array('package','before','plan') as $kind){$path=self::root().'/'.$old.'-'.$kind.'.json';if(!is_link($path)&&is_file($path))unlink($path);}delete_option(self::PREFIX.$old);}
        update_user_meta(get_current_user_id(),self::INDEX,array_slice($index,0,10));return $job;
    }
    public static function save(array $job): void{update_option(self::PREFIX.$job['id'],$job,false);if(get_option(self::PREFIX.$job['id'])!==$job)throw new \RuntimeException('任务状态保存失败。');}
    public static function load(string $id): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new \RuntimeException('任务标识无效。');$job=get_option(self::PREFIX.$id);
        if(!is_array($job)||($job['owner']??0)!==get_current_user_id())throw new \RuntimeException('任务不存在或不属于当前账号。');if(($job['expires']??0)<time())throw new \RuntimeException('文件已超过 7 天有效期，请重新导出或预检。');return $job;
    }
    public static function history(): array
    {
        $out=array();foreach(array_slice((array)get_user_meta(get_current_user_id(),self::INDEX,true),0,10) as $id){try{$out[]=self::load((string)$id);}catch(\Throwable $e){}}return $out;
    }
    public static function lock(): string
    {
        $key='mao_tk_toolkit_config_backup_lock';if(!add_option($key,array('at'=>time(),'owner'=>get_current_user_id()),'',false))throw new \RuntimeException('配置导入正在处理，请勿重复提交。若请求意外中断，请在 15 分钟后恢复锁。');return $key;
    }
    public static function recover(): void
    {
        $key='mao_tk_toolkit_config_backup_lock';$v=get_option($key);if(!is_array($v)||($v['at']??time())>time()-900)throw new \RuntimeException('没有可恢复的过期锁。');global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,maybe_serialize($v)));wp_cache_delete($key,'options');wp_cache_delete('alloptions','options');
    }
}
