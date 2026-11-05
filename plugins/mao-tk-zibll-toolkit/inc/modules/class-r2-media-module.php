<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\R2Media\Runtime;

defined('ABSPATH') || exit;

final class R2MediaModule implements Module
{
    public function id(): string { return Runtime::MODULE; }
    public function name(): string { return 'R2 图片同步'; }
    public function description(): string { return '访问时同步旧图，也可一键批量同步。保留 WordPress 原图和私有恢复副本。'; }
    public function requires_zibll(): bool { return false; }
    public function boot(): void { Runtime::register(); }
}
