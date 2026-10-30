<?php
namespace MaoTK\ZibllToolkit\Freshness;

use MaoTK\ZibllToolkit\Admin;

defined('ABSPATH') || exit;
$labels = array('loaded' => '运行中', 'disabled' => '未启用', 'blocked' => '等待 Zibll 主题', 'invalid' => '配置异常', 'error' => '加载已阻止');
$tones = array('loaded' => 'success', 'disabled' => 'neutral', 'blocked' => 'warning', 'error' => 'error', 'invalid' => 'error');
?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h1>文章时效提示</h1><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">超过设定更新天数后，在文章正文前提醒读者。</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . Admin::SLUG)); ?>">返回工作台</a></header>
    <?php $switch_notice = $module_notice; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if ($notice === 'saved') : ?><div class="mao-tk-toolkit__message" role="status">时效提示设置已保存。</div><?php elseif ($notice === 'failed') : ?><div class="mao-tk-toolkit__message" role="alert">设置未能保存，原有配置保持不变。</div><?php endif; ?>
    <?php if ($conflict) : ?><div class="mao-tk-toolkit__message" role="alert">检测到旧的 article_time_update 提示仍在运行。请先自行移除旧提示的挂钩，避免重复；本插件不会修改主题文件。</div><?php endif; ?>
    <?php if (!$state['valid']) : ?><div class="mao-tk-toolkit__message" role="alert">配置格式异常，正式输出和保存已暂停，原数据未重置。</div><?php endif; ?>
    <section class="mao-tk-toolkit__panel mao-tk-freshness__status"><div><h2>前台模块</h2><p>关闭只停止正式提示，设置与预览仍可用。</p><p class="mao-tk-toolkit__runtime-status">当前状态：<span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($tones[$module['status']] ?? 'error'); ?>" data-mao-tk-runtime-state><?php echo esc_html($labels[$module['status']] ?? '未知'); ?></span></p></div><?php $switch_module = $module; $switch_valid = $this->manager->storage_is_valid(); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?></section>
    <div class="mao-tk-freshness__layout">
        <section class="mao-tk-toolkit__panel"><div class="mao-tk-toolkit__section-head"><div><h2>提示设置</h2><p>按 WordPress 站点时区显示更新时间，不改变全局时区。</p></div></div>
            <form id="mao-tk-freshness-settings" class="mao-tk-freshness__fields" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>"><input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(AdminPage::SAVE)); ?>">
                <label for="mao-tk-freshness-days">超过多少天显示</label><input type="number" id="mao-tk-freshness-days" name="freshness[days]" min="1" max="3650" step="1" required value="<?php echo esc_attr((string) $settings['days']); ?>">
                <p class="description">默认 1 天。按实际经过的小时判断；文章更新后重新计算。</p>
                <label for="mao-tk-freshness-message">提示文字</label><textarea id="mao-tk-freshness-message" name="freshness[message]" rows="4" maxlength="600" required><?php echo esc_textarea($settings['message']); ?></textarea>
                <label for="mao-tk-freshness-contact">站长联系链接（可选）</label><input type="url" id="mao-tk-freshness-contact" name="freshness[contact_url]" maxlength="2048" value="<?php echo esc_attr($settings['contact_url']); ?>" placeholder="https://example.com/contact/">
                <p class="description">留空则不显示“联系站长”。“留言反馈”指向文章评论区。</p>
                <div class="mao-tk-freshness__actions"><button type="submit" class="button button-primary" <?php disabled(!$state['valid']); ?>>保存设置</button><span class="description" id="mao-tk-freshness-note" role="status">预览不会保存设置。</span></div>
            </form>
        </section>
        <section class="mao-tk-toolkit__panel"><div class="mao-tk-toolkit__section-head"><div><h2>实时样例预览</h2><p>输入有效设置后自动更新；演示日期不来自真实文章。</p></div></div>
            <form id="mao-tk-freshness-preview-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" target="mao-tk-freshness-preview" data-action="<?php echo esc_attr(AdminPage::PREVIEW); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(AdminPage::PREVIEW)); ?>"><button type="submit" class="button">更新预览</button></form>
            <iframe id="mao-tk-freshness-preview" name="mao-tk-freshness-preview" title="文章时效提示预览" sandbox="allow-same-origin" referrerpolicy="no-referrer"></iframe>
        </section>
    </div>
</div>
