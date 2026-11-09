<?php defined('ABSPATH') || exit; ?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <h1><?php esc_html_e('归档页顶部', 'mao-tk-zibll-toolkit'); ?></h1>
    <p><?php esc_html_e('分类、标签和专题共用一套标题栏。顶部开关可随时切回子比原版。', 'mao-tk-zibll-toolkit'); ?></p>
    <?php if ($notice === 'saved' || $notice === 'save-failed') : ?>
        <div class="notice <?php echo $notice === 'saved' ? 'notice-success' : 'notice-error'; ?> is-dismissible" data-feedback-state="<?php echo esc_attr($notice); ?>"><p><?php echo $notice === 'saved' ? __('设置已保存。', 'mao-tk-zibll-toolkit') : __('设置未能保存，请重试。', 'mao-tk-zibll-toolkit'); ?></p></div>
    <?php endif; ?>
    <div class="mao-tk-toolkit__main">
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__module">
                <div>
                    <h2><?php esc_html_e('启用统一标题栏', 'mao-tk-zibll-toolkit'); ?></h2>
                    <p><?php esc_html_e('适用于已核对的子比 9.2 普通分类、标签、专题模板。', 'mao-tk-zibll-toolkit'); ?></p>
                </div>
                <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
            </div>
        </section>
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__module">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
                    <?php wp_nonce_field($action); ?>
                    <h2><?php esc_html_e('显示设置', 'mao-tk-zibll-toolkit'); ?></h2>
                    <p><label><input type="checkbox" name="show_cover" value="1" <?php checked($settings['show_cover']); ?>> <?php esc_html_e('显示封面图', 'mao-tk-zibll-toolkit'); ?></label></p>
                    <p class="description"><?php esc_html_e('关闭后显示紧凑标题栏。右侧搜索继续按当前分类、标签或专题搜索。', 'mao-tk-zibll-toolkit'); ?></p>
                    <p><label><input type="checkbox" name="show_description" value="1" <?php checked($settings['show_description']); ?>> <?php esc_html_e('显示正文描述', 'mao-tk-zibll-toolkit'); ?></label></p>
                    <p class="description"><?php esc_html_e('只影响标题下方的描述。SEO 摘要、页面标题和索引规则由主题或 SEO 插件管理。', 'mao-tk-zibll-toolkit'); ?></p>
                    <?php submit_button(__('保存显示设置', 'mao-tk-zibll-toolkit')); ?>
                </form>
            </div>
        </section>
    </div>
</div>
