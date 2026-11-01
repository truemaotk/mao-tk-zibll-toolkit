<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
defined('ABSPATH') || exit;
final class NotFoundModule implements Module
{
    public function id(): string { return 'site-not-found'; }
    public function name(): string { return '404 公益页面'; }
    public function description(): string { return '独立替换 404 模板，保留子比页头、页脚和搜索，可显示带缓存的第三方公益寻亲信息。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { \MaoTK\ZibllToolkit\NotFound\Runtime::enable(); }
}
