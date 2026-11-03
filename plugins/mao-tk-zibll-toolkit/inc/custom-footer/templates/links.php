<?php defined('ABSPATH') || exit; ?>
<div class="footer-links" data-footer-disclosure>
    <div class="link-content">
        <button class="mtk-footer-toggle mtk-footer-links-toggle" type="button" aria-expanded="true" aria-controls="mtk-footer-links-panel" disabled><span class="link-heading"><?php echo esc_html($settings['links_heading']); ?></span><span class="mtk-footer-chevron" aria-hidden="true"></span></button>
        <div class="mtk-footer-panel" id="mtk-footer-links-panel"><div class="mtk-footer-panel-content">
            <div class="link-items">
            <?php foreach ($bookmarks as $bookmark): ?>
                <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url(is_string($bookmark->link_url ?? null) ? $bookmark->link_url : ''); ?>" title="<?php echo esc_attr(is_string($bookmark->link_description ?? null) ? $bookmark->link_description : ''); ?>"><?php echo esc_html(is_string($bookmark->link_name ?? null) ? $bookmark->link_name : ''); ?></a>
            <?php endforeach; ?>
            </div>
        </div></div>
    </div>
</div>
