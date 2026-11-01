<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\PrivateLinks\Settings;
use MaoTK\ZibllToolkit\PrivateLinks\Runtime;

defined('ABSPATH') || exit;

final class PrivateLinksModule implements Module
{
    public function id(): string { return Settings::MODULE; }
    public function name(): string { return '链接导航 · 私密链接可见性'; }
    public function description(): string { return '按角色与指定账号显示私密链接，默认仅站点管理员；保留子比导航与详情页样式。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { Runtime::register(); }
}
