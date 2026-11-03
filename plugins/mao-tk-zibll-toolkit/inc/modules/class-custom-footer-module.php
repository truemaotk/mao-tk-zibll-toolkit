<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\CustomFooter\Settings;
use MaoTK\ZibllToolkit\CustomFooter\Runtime;
defined('ABSPATH') || exit;
final class CustomFooterModule implements Module
{
    public function id(): string { return Settings::MODULE; }
    public function name(): string { return '自定义页脚'; }
    public function description(): string { return '独立配置栏目、社交图标、二维码与版权信息；手机菜单折叠，支持跟随主题或全宽。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { Runtime::register(); }
}
