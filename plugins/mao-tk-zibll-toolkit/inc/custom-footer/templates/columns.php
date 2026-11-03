<?php defined('ABSPATH') || exit; ?>
            <?php if ($settings['show_about']): ?>
            <section id="mtk-footer-about" class="widget widget_text mg-b zibtf-radius mtk-footer-always-open">
                <h2 class="widget-title footer-h"><?php echo esc_html($settings['about_title']); ?></h2>
                <div class="mtk-footer-static-content">
                <div class="textwidget"><p><?php echo esc_html($settings['about_description']); ?></p></div>
                <?php if ($social_links): ?>
                <div class="mtk-footer-social-links" role="group" aria-label="社交媒体与联系方式" data-social-size="<?php echo esc_attr((string) $settings['social_icon_size']); ?>" data-social-base="<?php echo $settings['social_base_enabled'] ? '1' : '0'; ?>" data-social-radius="<?php echo esc_attr((string) $settings['social_base_radius']); ?>">
                    <?php foreach ($social_links as $index => $social):
                        $tooltip_id = 'mtk-footer-social-tip-' . $index;
                        $label = $social['label'];
                        $configured = $social['url'] !== '';
                        $has_qr = $social['qr_url'] !== '';
                        $tip = ($configured || $has_qr) ? $label : $label . ' · 待设置链接';
                        $external = $configured && preg_match('~\Ahttps?://~i', $social['url']);
                        if ($configured): ?>
                        <a class="mtk-footer-social-item" href="<?php echo esc_url($social['url']); ?>" aria-label="<?php echo esc_attr($external ? $label . '（新窗口打开）' : $label); ?>" aria-describedby="<?php echo esc_attr($tooltip_id); ?>"<?php if ($external): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                        <?php else: ?>
                        <button class="mtk-footer-social-item" type="button"<?php if (!$has_qr): ?> aria-disabled="true"<?php endif; ?> aria-label="<?php echo esc_attr($tip); ?>" aria-describedby="<?php echo esc_attr($tooltip_id); ?>">
                        <?php endif; ?>
                            <?php if ($social['icon_url'] !== ''): ?>
                            <img class="mtk-footer-social-icon mtk-footer-social-custom-image" data-social="<?php echo esc_attr($social['icon']); ?>" src="<?php echo esc_url($social['icon_url']); ?>" alt="" aria-hidden="true" width="<?php echo esc_attr((string) $settings['social_icon_size']); ?>" height="<?php echo esc_attr((string) $settings['social_icon_size']); ?>">
                            <?php else: ?>
                            <span class="mtk-footer-social-icon" data-social="<?php echo esc_attr($social['icon']); ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="mtk-footer-social-tooltip" id="<?php echo esc_attr($tooltip_id); ?>" role="tooltip"><?php if ($has_qr): ?><img src="<?php echo esc_url($social['qr_url']); ?>" alt="<?php echo esc_attr($label . '二维码'); ?>" width="112" height="112"><?php endif; ?><?php echo esc_html($tip); ?></span>
                        <?php if ($configured): ?></a><?php else: ?></button><?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php foreach ($menu_groups as $index => $group): ?>
            <section id="mtk-footer-menu-<?php echo esc_attr((string) ($index + 2)); ?>" class="widget widget_nav_menu mg-b zibtf-radius" data-footer-disclosure>
                <h2 class="widget-title footer-h"><button class="mtk-footer-toggle" type="button" aria-expanded="true" aria-controls="mtk-footer-menu-panel-<?php echo esc_attr((string) ($index + 1)); ?>" disabled><span><?php echo esc_html($group['title']); ?></span><span class="mtk-footer-chevron" aria-hidden="true"></span></button></h2>
                <div class="mtk-footer-panel" id="mtk-footer-menu-panel-<?php echo esc_attr((string) ($index + 1)); ?>"><div class="mtk-footer-panel-content">
                <div class="menu-footer-container">
                    <ul id="mtk-footer-menu-items-<?php echo esc_attr((string) ($index + 1)); ?>" class="menu">
                    <?php foreach ($group['links'] as $link): ?>
                        <?php if ($link['label'] === '' || $link['url'] === '') { continue; } ?>
                        <li><a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($link['label']); ?></a></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                </div></div>
            </section>
            <?php endforeach; ?>
