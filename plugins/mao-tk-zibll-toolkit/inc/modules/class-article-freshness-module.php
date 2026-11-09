<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\Freshness\Runtime;
use MaoTK\ZibllToolkit\Freshness\Settings;

defined('ABSPATH') || exit;

final class ArticleFreshnessModule implements Module
{
    public function id(): string { return 'article-freshness'; }
    public function name(): string { return __('文章时效提示', 'mao-tk-zibll-toolkit'); }
    public function description(): string { return __('在超过设定更新天数的文章正文前提醒读者，显示最后更新时间与反馈入口。', 'mao-tk-zibll-toolkit'); }
    public function requires_zibll(): bool { return true; }
    public function boot(): void
    {
        if (Runtime::conflict() || !(new Settings())->read()['valid']) { throw new \RuntimeException('Freshness notice has invalid settings or a legacy conflict.'); }
        (new Runtime())->register();
    }
}
