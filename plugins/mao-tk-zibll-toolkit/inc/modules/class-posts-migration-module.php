<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\PostMigration\Posts;
defined('ABSPATH') || exit;
final class PostsMigrationModule implements Module
{
    public function id(): string{return Posts::MODULE;}
    public function name(): string{return __('文章导入&导出', 'mao-tk-zibll-toolkit');}
    public function description(): string{return __('按范围打包文章、分类标签和图片，预检后导入，默认草稿并保留覆盖前备份。', 'mao-tk-zibll-toolkit');}
    public function requires_zibll(): bool{return true;}
    public function boot(): void{}
}
