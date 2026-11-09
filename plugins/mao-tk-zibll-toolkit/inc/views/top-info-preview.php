<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;
$asset_url = MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/top-info/';
$version = '?ver=' . rawurlencode(MAO_TK_ZIBLL_TOOLKIT_VERSION);
?>
<!doctype html><html lang="<?php echo esc_attr(str_replace('_', '-', get_user_locale())); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php esc_html_e('文章顶部信息 · 独立预览', 'mao-tk-zibll-toolkit'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url($asset_url . 'iconfont/iconfont.css' . $version); ?>"><link rel="stylesheet" href="<?php echo esc_url($asset_url . 'top-info.css' . $version); ?>"><link rel="stylesheet" href="<?php echo esc_url($asset_url . 'preview.css' . $version); ?>">
</head><body class="single-post mao-tk-top-info-ready moxing-top-info-fullwidth mao-tk-top-info-preview">
<p class="mao-tk-preview-caption"><?php echo esc_html($sample ? __('样例文章 · 数值为演示数据', 'mao-tk-zibll-toolkit') : __('内容组件预览 · 不计入阅读量', 'mao-tk-zibll-toolkit')); ?></p>
<?php echo $html; ?>
<main class="container"><div class="content-layout"><article class="article"><h2><?php esc_html_e('正文区域示意', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('此页面只展示组件排版，不执行主题文章模板，不保存配置，不更新阅读量或统计缓存。', 'mao-tk-zibll-toolkit'); ?></p><p><?php esc_html_e('横幅与真实主题导航、正文、侧栏的最终位置需要在目标主题环境验收。', 'mao-tk-zibll-toolkit'); ?></p></article></div></main>
</body></html>
