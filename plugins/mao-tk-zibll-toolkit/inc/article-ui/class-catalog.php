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
            'code' => array('name' => __('文章 UI · 高亮代码', 'mao-tk-zibll-toolkit'), 'description' => __('只增强已包装的原生 Enlighter 外观，保留主题工具、语法颜色与滚动。', 'mao-tk-zibll-toolkit'), 'file' => 'code/code.css'),
            'link-preview' => array('name' => __('文章 UI · 链接预览', 'mao-tk-zibll-toolkit'), 'description' => __('正文普通链接的悬浮卡、原网址复制与键盘操作；不接管原链接点击。', 'mao-tk-zibll-toolkit'), 'file' => 'link-preview/link.css'),
            'tabs' => array('name' => __('文章 UI · TAB 极简线条', 'mao-tk-zibll-toolkit'), 'description' => __('仅适配顶部横向 TAB B，切换、状态和面板仍由主题负责。', 'mao-tk-zibll-toolkit'), 'file' => 'tabs/tabs.css'),
            'postsbox' => array('name' => __('文章 UI · 文章卡片', 'mao-tk-zibll-toolkit'), 'description' => __('原生 postsbox 的局部外观，保留真实文章数据、全部入口和主题图标。', 'mao-tk-zibll-toolkit'), 'file' => 'postsbox/postsbox.css'),
            'resources' => array('name' => __('文章 UI · 推荐与工具入口', 'mao-tk-zibll-toolkit'), 'description' => __('复用已确认的推荐卡与外部工具入口、手机间距和居中图标；不接管原生文件下载或支付。', 'mao-tk-zibll-toolkit'), 'file' => 'resources/components.css'),
            'reading' => array('name' => __('文章 UI · 阅读与折叠', 'mao-tk-zibll-toolkit'), 'description' => __('仅在明确标记的正文内应用紧凑间距、图片边界和已确认的折叠交互；标题与目录沿用主题。', 'mao-tk-zibll-toolkit'), 'file' => 'reading/reading.css'),
        );
    }

    public static function module_id(string $key): string { return 'article-ui-' . $key; }
    public static function attribute(string $key): string { return 'data-mao-tk-ui-' . $key; }
}
