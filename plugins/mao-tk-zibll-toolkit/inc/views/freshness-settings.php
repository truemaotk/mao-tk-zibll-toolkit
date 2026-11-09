<?php
namespace MaoTK\ZibllToolkit\Freshness;

use MaoTK\ZibllToolkit\Admin;

defined('ABSPATH') || exit;
$labels = array('loaded' => __('运行中', 'mao-tk-zibll-toolkit'), 'disabled' => __('未启用', 'mao-tk-zibll-toolkit'), 'blocked' => __('等待 Zibll 主题', 'mao-tk-zibll-toolkit'), 'invalid' => __('配置异常', 'mao-tk-zibll-toolkit'), 'error' => __('加载已阻止', 'mao-tk-zibll-toolkit'));
$tones = array('loaded' => 'success', 'disabled' => 'neutral', 'blocked' => 'warning', 'error' => 'error', 'invalid' => 'error');
?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <h1 class="screen-reader-text"><?php esc_html_e('文章时效提示', 'mao-tk-zibll-toolkit'); ?></h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2><?php esc_html_e('文章时效提示', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro"><?php esc_html_e('超过设定更新天数后，在文章正文前提醒读者。', 'mao-tk-zibll-toolkit'); ?></p></div></header>
    <?php $switch_notice = $module_notice; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if ($notice === 'saved') : ?><div class="mao-tk-toolkit__message" role="status"><?php esc_html_e('时效提示设置已保存。', 'mao-tk-zibll-toolkit'); ?></div><?php elseif ($notice === 'failed') : ?><div class="mao-tk-toolkit__message" role="alert"><?php esc_html_e('设置未能保存，原有配置保持不变。', 'mao-tk-zibll-toolkit'); ?></div><?php endif; ?>
    <?php if ($conflict) : ?><div class="mao-tk-toolkit__message" role="alert"><?php esc_html_e('检测到旧的 article_time_update 提示仍在运行。请先自行移除旧提示的挂钩，避免重复；本插件不会修改主题文件。', 'mao-tk-zibll-toolkit'); ?></div><?php endif; ?>
    <?php if (!$state['valid']) : ?><div class="mao-tk-toolkit__message" role="alert"><?php esc_html_e('配置格式异常，正式输出和保存已暂停，原数据未重置。', 'mao-tk-zibll-toolkit'); ?></div><?php endif; ?>
    <section class="mao-tk-toolkit__panel mao-tk-freshness__status"><div><h2><?php esc_html_e('前台模块', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('关闭只停止正式提示，设置与预览仍可用。', 'mao-tk-zibll-toolkit'); ?></p><p class="mao-tk-toolkit__runtime-status"><?php esc_html_e('当前状态：', 'mao-tk-zibll-toolkit'); ?><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($tones[$module['status']] ?? 'error'); ?>" data-mao-tk-runtime-state><?php echo esc_html($labels[$module['status']] ?? __('未知', 'mao-tk-zibll-toolkit')); ?></span></p></div><?php $switch_module = $module; $switch_valid = $this->manager->storage_is_valid(); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?></section>
    <div class="mao-tk-freshness__layout">
        <section class="mao-tk-toolkit__panel"><div class="mao-tk-toolkit__section-head"><div><h2><?php esc_html_e('提示设置', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('按 WordPress 站点时区显示更新时间，不改变全局时区。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
            <form id="mao-tk-freshness-settings" class="mao-tk-freshness__fields" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>"><input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(AdminPage::SAVE)); ?>">
                <label for="mao-tk-freshness-days"><?php esc_html_e('超过多少天显示', 'mao-tk-zibll-toolkit'); ?></label><input type="number" id="mao-tk-freshness-days" name="freshness[days]" min="1" max="3650" step="1" required value="<?php echo esc_attr((string) $settings['days']); ?>">
                <p class="description"><?php esc_html_e('默认 1 天。按实际经过的小时判断；文章更新后重新计算。', 'mao-tk-zibll-toolkit'); ?></p>
                <label for="mao-tk-freshness-message"><?php esc_html_e('提示文字', 'mao-tk-zibll-toolkit'); ?></label><textarea id="mao-tk-freshness-message" name="freshness[message]" rows="4" maxlength="600" required><?php echo esc_textarea($settings['message']); ?></textarea>
                <label for="mao-tk-freshness-contact"><?php esc_html_e('站长联系链接（可选）', 'mao-tk-zibll-toolkit'); ?></label><input type="url" id="mao-tk-freshness-contact" name="freshness[contact_url]" maxlength="2048" value="<?php echo esc_attr($settings['contact_url']); ?>" placeholder="https://example.com/contact/">
                <p class="description"><?php esc_html_e('留空则不显示“联系站长”。“留言反馈”指向文章评论区。', 'mao-tk-zibll-toolkit'); ?></p>
                <div class="mao-tk-freshness__actions"><button type="submit" class="button button-primary" <?php disabled(!$state['valid']); ?>><?php esc_html_e('保存设置', 'mao-tk-zibll-toolkit'); ?></button><span class="description" id="mao-tk-freshness-note" role="status"><?php esc_html_e('预览不会保存设置。', 'mao-tk-zibll-toolkit'); ?></span></div>
            </form>
        </section>
        <section class="mao-tk-toolkit__panel"><div class="mao-tk-toolkit__section-head"><div><h2><?php esc_html_e('实时样例预览', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('输入有效设置后自动更新；演示日期不来自真实文章。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
            <form id="mao-tk-freshness-preview-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" target="mao-tk-freshness-preview" data-action="<?php echo esc_attr(AdminPage::PREVIEW); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(AdminPage::PREVIEW)); ?>"><button type="submit" class="button"><?php esc_html_e('更新预览', 'mao-tk-zibll-toolkit'); ?></button></form>
            <iframe id="mao-tk-freshness-preview" name="mao-tk-freshness-preview" title="<?php esc_attr_e('文章时效提示预览', 'mao-tk-zibll-toolkit'); ?>" sandbox="allow-same-origin" referrerpolicy="no-referrer"></iframe>
        </section>
    </div>
</div>
