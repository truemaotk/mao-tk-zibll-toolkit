<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;
// $data is collected explicitly for one article; settings were validated by Settings.
?>
<div class="maotk-top-info head_box_mx" data-maotk-top-info-version="<?php echo esc_attr(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?>" style="--maotk-top-info-bg:<?php echo esc_attr($settings['bg_color']); ?>;--maotk-top-info-bg-opacity:<?php echo esc_attr((string) (($settings['bg_opacity'] ?? 100) / 100)); ?>;--maotk-top-info-text:<?php echo esc_attr(Settings::text_color($settings)); ?>;--maotk-top-info-icon:<?php echo esc_attr(Settings::icon_color($settings)); ?>;<?php foreach (Settings::spacing_defaults() as $spacing_key => $spacing_default) { echo '--maotk-top-info-' . str_replace('_', '-', $spacing_key) . ':' . esc_attr((string) ($settings[$spacing_key] ?? $spacing_default)) . 'px;'; } ?>">
    <div class="maotk-top-info__background moxing_background" aria-hidden="true"><div class="maotk-top-info__background-wrap b-wrap"><i class="maotk-top-info__background-image img"><?php echo wp_kses_post($data['thumbnail']); ?></i></div></div>
    <div class="maotk-top-info__labels mxbk_tags-container maotk-top-info-fade-in">
        <div class="maotk-top-info__primary-labels mulu_one_two">
            <?php if ($data['property'] !== '') : ?><span><?php echo esc_html($data['property']); ?></span><?php endif; ?>
            <?php if ($data['category'] !== '') : ?><a href="<?php echo esc_url($preview ? '#' : $data['category_url']); ?>" <?php if ($preview) : ?>tabindex="-1" aria-disabled="true"<?php endif; ?>><span class="maotk-top-info__category category"><i class="maotk-top-info-icon icon-folder-5-fill" aria-hidden="true"></i><?php echo esc_html($data['category']); ?></span></a><?php endif; ?>
        </div>
        <?php if ($data['tags']) : ?><div class="maotk-top-info__tags tags" aria-label="文章标签"><?php foreach ($data['tags'] as $tag) : ?><span class="maotk-top-info__tag"><?php echo esc_html($tag); ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
    <h1 class="maotk-top-info__title moxing_head_info_post_title maotk-top-info-fade-in"><?php echo esc_html($data['title']); ?></h1>
    <div class="maotk-top-info__meta mx_footer_meta maotk-top-info-fade-in">
        <span class="post-meta-separator" aria-hidden="true"></span><i class="maotk-top-info-icon icon-file-word-fill" aria-hidden="true"></i><span class="post-meta-label"><?php echo esc_html(number_format_i18n($data['stats']['word_count'])); ?>字</span>
        <span class="post-meta-separator" aria-hidden="true"></span><i class="maotk-top-info-icon icon-time-fill" aria-hidden="true"></i><span class="post-meta-label"><?php echo esc_html(number_format_i18n($data['stats']['read_time'])); ?>分钟</span>
        <span class="post-meta-separator" aria-hidden="true"></span><i class="maotk-top-info-icon icon-calendar-todo-fill" aria-hidden="true"></i><span class="post-meta-label"><?php echo esc_html($data['date']); ?></span>
        <span class="post-meta-separator" aria-hidden="true"></span><a href="<?php echo esc_url($preview ? '#' : $data['author_url']); ?>" <?php if ($preview) : ?>tabindex="-1" aria-disabled="true"<?php endif; ?>><i class="maotk-top-info-icon icon-shield-user-fill" aria-hidden="true"></i><span class="post-meta-label"><?php echo esc_html($data['author']); ?></span></a>
        <span class="post-meta-separator" aria-hidden="true"></span><i class="maotk-top-info-icon icon-fire-fill" aria-hidden="true"></i><span class="post-meta-label"><?php echo esc_html($data['views']); ?></span>
        <span class="post-meta-separator" aria-hidden="true"></span><a href="#respond" class="maotk-top-info__smooth-scroll" <?php if ($preview) : ?>tabindex="-1" aria-disabled="true"<?php endif; ?>><i class="maotk-top-info-icon icon-chat-1-fill" aria-hidden="true"></i><span class="post-meta-label"><?php echo esc_html($data['comments']); ?></span></a>
        <span class="post-meta-separator" aria-hidden="true"></span><i class="maotk-top-info-icon icon-book-mark-fill" aria-hidden="true"></i><span class="post-meta-label">该作者已发布<?php echo esc_html(number_format_i18n($data['author_posts'])); ?><?php echo esc_html($data['author_unit'] ?? '篇文章'); ?></span>
    </div>
    <div class="maotk-top-info__breadcrumb-slot"><?php if ($preview) { require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-breadcrumb.php'; } ?></div>
</div>
<?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-breadcrumb.php'; ?>
