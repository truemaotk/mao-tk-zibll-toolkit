<?php defined('ABSPATH') || exit; ?>
<section id="mtk-footer-social" class="widget_text widget widget_custom_html mg-b zibtf-radius mtk-footer-always-open">
    <h2 class="widget-title footer-h"><?php echo esc_html($settings['social_title']); ?></h2>
    <div class="mtk-footer-static-content">
        <div class="textwidget custom-html-widget">
            <div class="row qr-container">
            <?php foreach ($settings['qrcodes'] as $qr): ?>
                <div class="qr-item">
                    <?php if ($qr['link'] !== ''): ?><a class="mtk-footer-qr-link" href="<?php echo esc_url($qr['link']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($qr['label']); ?>"><?php endif; ?>
                    <div class="qr-wrapper"><img width="90" height="90" alt="<?php echo esc_attr($qr['label']); ?>" src="<?php echo esc_url($qr['url']); ?>" class="qr-img"></div>
                    <p class="qr-tips"><?php echo esc_html($qr['label']); ?></p>
                    <?php if ($qr['link'] !== ''): ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
