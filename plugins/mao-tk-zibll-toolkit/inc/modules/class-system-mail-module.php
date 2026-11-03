<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\SystemMail\Runtime;
use MaoTK\ZibllToolkit\SystemMail\Catalog;
defined('ABSPATH') || exit;
final class SystemMailModule implements Module
{
    public function id(): string{return Catalog::MODULE;}
    public function name(): string{return '系统邮件外观';}
    public function description(): string{return '为已识别的系统通知配置通用与独立模板，保留原发送、验证、订单及 SMTP 逻辑。';}
    public function requires_zibll(): bool{return true;}
    public function boot(): void{Runtime::register();}
}
