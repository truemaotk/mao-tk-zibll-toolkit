<?php defined('ABSPATH') || exit; ?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <h1>归档页顶部</h1>
    <p>分类、标签和专题共用一套标题栏。顶部开关可随时切回子比原版。</p>
    <?php if ($notice === 'saved' || $notice === 'save-failed') : ?>
        <div class="notice <?php echo $notice === 'saved' ? 'notice-success' : 'notice-error'; ?> is-dismissible" data-feedback-state="<?php echo esc_attr($notice); ?>"><p><?php echo $notice === 'saved' ? '设置已保存。' : '设置未能保存，请重试。'; ?></p></div>
    <?php endif; ?>
    <div class="mao-tk-toolkit__main">
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__module">
                <div>
                    <h2>启用统一标题栏</h2>
                    <p>适用于已核对的子比 9.2 普通分类、标签、专题模板。</p>
                </div>
                <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
            </div>
        </section>
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__module">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
                    <?php wp_nonce_field($action); ?>
                    <h2>显示设置</h2>
                    <p><label><input type="checkbox" name="show_cover" value="1" <?php checked($settings['show_cover']); ?>> 显示封面图</label></p>
                    <p class="description">关闭后显示紧凑标题栏。右侧搜索继续按当前分类、标签或专题搜索。</p>
                    <p><label><input type="checkbox" name="show_description" value="1" <?php checked($settings['show_description']); ?>> 显示正文描述</label></p>
                    <p class="description">只影响标题下方的描述。SEO 摘要、页面标题和索引规则由主题或 SEO 插件管理。</p>
                    <?php submit_button('保存显示设置'); ?>
                </form>
            </div>
        </section>
    </div>
</div>
