<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\ArticleNote\Integration;
use MaoTK\ZibllToolkit\ArticleNote\Settings;

defined('ABSPATH') || exit;

final class ArticleNoteModule implements Module
{
    public function id(): string { return 'article-note'; }
    public function name(): string { return '正文提示框'; }
    public function description(): string { return '五种提示框的封板外观与配置继承；保留旧 maotk/note 区块、段落和列表。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void
    {
        if (!Integration::supported() || !(new Settings())->read()['valid']) { throw new \RuntimeException('Article note requires WordPress 6.3+ and valid settings.'); }
        Integration::instance()->enable();
    }
}
