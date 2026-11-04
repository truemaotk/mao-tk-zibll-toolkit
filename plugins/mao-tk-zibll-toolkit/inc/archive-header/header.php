<?php defined('ABSPATH') || exit; ?>
<header class="mtk-archive-header zib-widget" aria-labelledby="mtk-archive-title"<?php if ($page === 1) : ?> win-ajax-replace="page-cover"<?php endif; ?>>
    <?php if ($cover !== '') : ?>
        <img class="mtk-archive-header__cover" src="<?php echo esc_url($cover); ?>" alt="" decoding="async">
    <?php endif; ?>
    <div class="mtk-archive-header__body">
        <div class="mtk-archive-header__row">
            <div class="mtk-archive-header__identity">
                <i class="mtk-archive-header__icon fa <?php echo esc_attr($config['icon']); ?>" aria-hidden="true"></i>
                <h1 id="mtk-archive-title"><span><?php echo esc_html($term->name); ?></span></h1>
                <?php if ($show_count) : ?>
                    <span class="mtk-archive-header__meta"><?php echo esc_html(sprintf(__('共%s篇', 'zib_language'), zib_get_the_found_posts())); ?></span>
                <?php elseif ($page > 1) : ?>
                    <span class="mtk-archive-header__meta"><?php echo esc_html(sprintf(__('第%s页', 'zib_language'), $page)); ?></span>
                <?php endif; ?>
            </div>
            <div class="mtk-archive-header__actions">
                <?php echo $restriction; // Native category access affordance. ?>
                <?php echo $search; // Native scoped search control. ?>
            </div>
        </div>
        <?php if ($description !== '') : ?>
            <p class="mtk-archive-header__description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </div>
</header>
