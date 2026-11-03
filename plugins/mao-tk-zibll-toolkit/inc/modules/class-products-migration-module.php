<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\ProductMigration\Products;
defined('ABSPATH') || exit;
final class ProductsMigrationModule implements Module
{
    public function id(): string{return Products::MODULE;}
    public function name(): string{return '商品导入&导出';}
    public function description(): string{return '按范围打包商品、分类标签和图片，预检后导入，默认草稿，仅更新草稿并保存覆盖前备份。';}
    public function requires_zibll(): bool{return true;}
    public function boot(): void{}
}
