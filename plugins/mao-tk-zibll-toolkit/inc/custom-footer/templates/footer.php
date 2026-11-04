<?php defined('ABSPATH') || exit; ?>
<div class="mtk-footer-component" data-footer-width="<?php echo esc_attr($settings['width_mode']); ?>">
    <div role="group" aria-label="网站页脚" id="mtk-footer-outer" class="onecad_new_footer bg_img">
        <div id="mtk-footer-inner" class="footer">
            <div id="mtk-footer-background">
                <div class="tfbk-footer">
                    <div class="wrapper">
                        <div class="tfbk-footer-widget-in">
                            <?php include __DIR__ . '/columns.php'; ?>
                            <?php if ($settings['show_friends']) { include __DIR__ . '/links.php'; } ?>
                            <?php if ($settings['show_follow']) { include __DIR__ . '/follow.php'; } ?>
                        </div>
                    </div>
                </div>
                <?php include __DIR__ . '/bottom.php'; ?>
            </div>
        </div>
    </div>
</div>
