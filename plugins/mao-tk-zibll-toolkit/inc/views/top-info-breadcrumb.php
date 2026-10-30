<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;
?>
<ul class="breadcrumb maotk-top-info-breadcrumb<?php echo $preview ? ' mao-tk-preview-breadcrumb-mobile' : ''; ?>" data-maotk-fallback-breadcrumb aria-label="面包屑导航">
    <li><a href="<?php echo esc_url($preview ? '#' : home_url('/')); ?>" <?php if ($preview) : ?>tabindex="-1"<?php endif; ?>>首页</a></li>
    <?php if ($data['category'] !== '') : ?><li><?php echo esc_html($data['category']); ?></li><?php endif; ?>
    <li aria-current="page">正文</li>
</ul>
