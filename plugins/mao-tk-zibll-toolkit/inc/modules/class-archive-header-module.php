<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\ArchiveHeader\Runtime;
use MaoTK\ZibllToolkit\ArchiveHeader\Settings;
use MaoTK\ZibllToolkit\Module;

defined('ABSPATH') || exit;

final class ArchiveHeaderModule implements Module
{
    public function id(): string { return Settings::MODULE; }
    public function name(): string { return '归档页顶部'; }
    public function description(): string { return '分类、标签、专题共用一套标题栏，可单独开关封面和正文描述。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { Runtime::register(); }
}
