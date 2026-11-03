<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\Migration\Links;

defined('ABSPATH') || exit;

final class LinksMigrationModule implements Module
{
    public function id(): string { return Links::MODULE; }
    public function name(): string { return '链接导入&导出'; }
    public function description(): string { return '打包链接与 LOGO，导入前预检，保留推荐参数与私密状态；分批执行并记录结果。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { /* Admin-only transfer handlers are registered by the control plane. */ }
}
