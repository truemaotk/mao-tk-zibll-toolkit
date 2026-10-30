<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\TopInfo\Runtime;
use MaoTK\ZibllToolkit\TopInfo\Settings;

defined('ABSPATH') || exit;

final class TopInfoModule implements Module
{
    public function id(): string { return 'top-info'; }
    public function name(): string { return '文章顶部信息'; }
    public function description(): string { return '保留原版文章横幅效果，展示标题、封面和阅读信息；设置与预览独立于前台开关。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void
    {
        if (Runtime::conflict() || !(new Settings())->read()['valid']) { throw new \RuntimeException('Top info is blocked by conflicting runtime or invalid settings.'); }
        (new Runtime())->register();
    }
}
