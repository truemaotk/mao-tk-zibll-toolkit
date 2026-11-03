<?php defined('ABSPATH') || exit; ?>
<?php if (($settings['show_copyright'] && $settings['copyright'] !== '') || ($settings['show_icp'] && $settings['icp_label'] !== '') || ($settings['show_performance'] && $performance['queries'] !== null && $performance['seconds'] !== null)): ?>
        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <?php if ($settings['show_copyright'] && $settings['copyright'] !== ''): ?><span class="foot-copyright"><?php echo esc_html($settings['copyright']); ?></span><?php endif; ?>
                <div class="footer-bottom-right">
                        <?php if ($settings['show_icp'] && $settings['icp_label'] !== ''): ?>
                        <?php if ($settings['icp_url'] !== '' && $settings['icp_url'] !== '#'): ?><a rel="nofollow noopener noreferrer" target="_blank" href="<?php echo esc_url($settings['icp_url']); ?>" class="icp-info"><?php else: ?><span class="icp-info"><?php endif; ?>
                        <?php if ($settings['icp_icon_url'] !== ''): ?><img src="<?php echo esc_url($settings['icp_icon_url']); ?>" width="16" height="16" alt="备案标识"><?php endif; ?><?php echo esc_html($settings['icp_label']); ?>
                        <?php if ($settings['icp_url'] !== '' && $settings['icp_url'] !== '#'): ?></a><?php else: ?></span><?php endif; ?>
                        <?php endif; ?>
                    <?php if ($settings['show_performance'] && $performance['queries'] !== null && $performance['seconds'] !== null): ?>
                    <span>数据库查询：<?php echo esc_html((string) $performance['queries']); ?>次 加载耗时<?php echo esc_html($performance['seconds']); ?> 秒</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endif; ?>
