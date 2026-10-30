<?php
namespace MaoTK\ZibllToolkit\ArticleUI;

defined('ABSPATH') || exit;

final class Catalog
{
    public const SOURCE_VERSION = '1.0.0';
    public const ADAPTER_VERSION = '1.0.2';

    public static function all(): array
    {
        return array(
            'code' => array('name' => '文章 UI · 高亮代码', 'description' => '只增强已包装的原生 Enlighter 外观，保留主题工具、语法颜色与滚动。', 'file' => 'code/code.css'),
            'link-preview' => array('name' => '文章 UI · 链接预览', 'description' => '正文普通链接的悬浮卡、原网址复制与键盘操作；不接管原链接点击。', 'file' => 'link-preview/link.css'),
            'tabs' => array('name' => '文章 UI · TAB 极简线条', 'description' => '仅适配顶部横向 TAB B，切换、状态和面板仍由主题负责。', 'file' => 'tabs/tabs.css'),
            'postsbox' => array('name' => '文章 UI · 文章卡片', 'description' => '原生 postsbox 的局部外观，保留真实文章数据、全部入口和主题图标。', 'file' => 'postsbox/postsbox.css'),
            'reading' => array('name' => '文章 UI · 阅读与折叠', 'description' => '仅在明确标记的正文内应用紧凑间距、图片边界和已确认的折叠交互；标题与目录沿用主题。', 'file' => 'reading/reading.css'),
        );
    }

    public static function module_id(string $key): string { return 'article-ui-' . $key; }
    public static function attribute(string $key): string { return 'data-mao-tk-ui-' . $key; }
}
