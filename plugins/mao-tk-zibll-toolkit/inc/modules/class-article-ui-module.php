<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\ArticleUI\Catalog;
use MaoTK\ZibllToolkit\ArticleUI\Runtime;

defined('ABSPATH') || exit;

final class ArticleUIModule implements Module
{
    private $key;
    public function __construct(string $key) { $this->key = $key; }
    public function id(): string { return Catalog::module_id($this->key); }
    public function name(): string { return Catalog::all()[$this->key]['name']; }
    public function description(): string { return Catalog::all()[$this->key]['description']; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void
    {
        if (!class_exists('WP_HTML_Tag_Processor')) { throw new \RuntimeException('Article UI requires the WordPress 6.2+ HTML API.'); }
        Runtime::instance()->enable($this->key);
    }
}
