<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\Maintenance\Settings;
use MaoTK\ZibllToolkit\Maintenance\Runtime;

defined('ABSPATH') || exit;

final class MaintenanceModule implements Module
{
    public function id(): string { return Settings::MODULE; }
    public function name(): string { return __('站点维护页面', 'mao-tk-zibll-toolkit'); }
    public function description(): string { return __('独立维护页、预计恢复倒计时与备用入口；启用后访客网页返回 503，站点管理员与登录入口保留。', 'mao-tk-zibll-toolkit'); }
    public function requires_zibll(): bool { return false; }
    public function boot(): void { Runtime::register(); }
}
