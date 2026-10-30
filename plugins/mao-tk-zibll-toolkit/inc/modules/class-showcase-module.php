<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\Showcase\Settings;
use MaoTK\ZibllToolkit\Showcase\Runtime;

defined('ABSPATH') || exit;

final class ShowcaseModule implements Module
{
    public function id(): string { return Settings::MODULE; }
    public function name(): string { return '页面组件 · 案例展示'; }
    public function description(): string { return '后台集中管理案例与分类，页面选择模板和分类；保留v0.4浏览器卡片、长图及图标设置。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { if (!\MaoTK\ZibllToolkit\Showcase\Integration::supported() || !(new Settings())->read()['valid']) { throw new \RuntimeException('Showcase requires WordPress 6.3+ and a valid library.'); } Runtime::enable(); }
}
