<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\PostMigration\Posts;
defined('ABSPATH') || exit;
final class PostsMigrationModule implements Module
{
    public function id(): string{return Posts::MODULE;}
    public function name(): string{return '文章导入&导出';}
    public function description(): string{return '按范围打包文章、分类标签和图片，预检后导入，默认草稿并保留覆盖前备份。';}
    public function requires_zibll(): bool{return true;}
    public function boot(): void{}
}
