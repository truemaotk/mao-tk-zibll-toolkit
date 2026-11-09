<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
defined('ABSPATH') || exit;
final class NotFoundModule implements Module
{
    public function id(): string { return 'site-not-found'; }
    public function name(): string { return __('404 公益页面', 'mao-tk-zibll-toolkit'); }
    public function description(): string { return __('独立替换 404 模板，保留子比页头、页脚和搜索，可显示带缓存的第三方公益寻亲信息。', 'mao-tk-zibll-toolkit'); }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { \MaoTK\ZibllToolkit\NotFound\Runtime::enable(); }
}
