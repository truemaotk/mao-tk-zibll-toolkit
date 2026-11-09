<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\ProductMigration\Products;
defined('ABSPATH') || exit;
final class ProductsMigrationModule implements Module
{
    public function id(): string{return Products::MODULE;}
    public function name(): string{return __('商品导入&导出', 'mao-tk-zibll-toolkit');}
    public function description(): string{return __('按范围打包商品、分类标签和图片，预检后导入，默认草稿，仅更新草稿并保存覆盖前备份。', 'mao-tk-zibll-toolkit');}
    public function requires_zibll(): bool{return true;}
    public function boot(): void{}
}
