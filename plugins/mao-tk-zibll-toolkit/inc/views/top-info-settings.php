<?php
namespace MaoTK\ZibllToolkit\TopInfo;

use MaoTK\ZibllToolkit\Admin;

defined('ABSPATH') || exit;
$labels = array('disabled' => __('未启用', 'mao-tk-zibll-toolkit'), 'loaded' => __('运行中', 'mao-tk-zibll-toolkit'), 'blocked' => __('等待 Zibll 主题', 'mao-tk-zibll-toolkit'), 'error' => __('运行已阻止', 'mao-tk-zibll-toolkit'), 'invalid' => __('开关配置异常', 'mao-tk-zibll-toolkit'));
$tones = array('loaded' => 'success', 'disabled' => 'neutral', 'blocked' => 'warning', 'error' => 'error', 'invalid' => 'error');
?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <h1 class="screen-reader-text"><?php esc_html_e('文章顶部信息', 'mao-tk-zibll-toolkit'); ?></h1>
    <header class="mao-tk-toolkit__header">
        <div><div class="mao-tk-toolkit__title-line"><h2><?php esc_html_e('文章顶部信息', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro"><?php esc_html_e('沿用原版前台横幅；关闭模块后仍能设置和预览。', 'mao-tk-zibll-toolkit'); ?></p></div>
    </header>
    <?php $switch_notice = $module_notice; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if ($notice === 'saved') : ?><div class="mao-tk-toolkit__message" role="status"><?php esc_html_e('设置已保存。旧配置和原文章属性保持不变。', 'mao-tk-zibll-toolkit'); ?></div><?php elseif ($notice === 'save-failed') : ?><div class="mao-tk-toolkit__message" role="alert"><?php esc_html_e('保存失败。原有配置保持不变，请检查数据格式及数据库连接。', 'mao-tk-zibll-toolkit'); ?></div><?php endif; ?>
    <?php if ($conflict) : ?><div class="mao-tk-toolkit__message" role="alert"><?php esc_html_e('检测到独立版顶部信息插件或旧版回调。本模块不会接管前台；请自行停用旧插件后再使用，避免重复横幅。设置与预览仍可使用。', 'mao-tk-zibll-toolkit'); ?></div><?php endif; ?>
    <?php if (!$state['valid']) : ?><div class="mao-tk-toolkit__message" role="alert"><?php esc_html_e('模块配置格式异常，已暂停前台及设置写入。原数据没有重置；当前以默认值供预览。', 'mao-tk-zibll-toolkit'); ?></div><?php endif; ?>
    <section class="mao-tk-toolkit__panel mao-tk-top-info__status">
        <div><h2><?php esc_html_e('前台模块', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('开关只影响正式文章页。停用保留所有配置及文章属性；已有整页缓存需按站点缓存策略刷新。', 'mao-tk-zibll-toolkit'); ?></p><p class="mao-tk-toolkit__runtime-status"><?php esc_html_e('当前状态：', 'mao-tk-zibll-toolkit'); ?><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($tones[$module['status']] ?? 'error'); ?>" data-mao-tk-runtime-state><?php echo esc_html($labels[$module['status']] ?? __('未获取', 'mao-tk-zibll-toolkit')); ?></span></p></div>
        <?php $switch_module = array_merge(array('id' => 'top-info', 'name' => __('文章顶部信息', 'mao-tk-zibll-toolkit')), $module); $switch_valid = $this->manager->storage_is_valid(); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
    </section>
    <div class="mao-tk-top-info__settings-layout">
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__section-head"><div><h2><?php esc_html_e('外观与文章属性', 'mao-tk-zibll-toolkit'); ?></h2><p><?php echo esc_html($state['source'] === 'legacy' ? __('当前兼容读取旧插件配置。保存后独立存储，旧记录仍保留。', 'mao-tk-zibll-toolkit') : __('颜色作用于横幅背景与信息文字，原版封面和动画效果保留。', 'mao-tk-zibll-toolkit')); ?></p></div></div>
            <form id="mao-tk-top-info-settings" class="mao-tk-top-info__fields" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>"><input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(AdminPage::SAVE)); ?>">
                <?php Admin::disclosure_start('mao-tk-top-colors', __('颜色与透明度', 'mao-tk-zibll-toolkit'), __('背景、文字与图标', 'mao-tk-zibll-toolkit'), true, 'top-settings'); ?><div class="mao-tk-disclosure__fields">
                <?php foreach (array('bg_color' => __('背景颜色', 'mao-tk-zibll-toolkit'), 'text_color' => __('信息文字颜色', 'mao-tk-zibll-toolkit'), 'icon_color' => __('信息图标颜色', 'mao-tk-zibll-toolkit')) as $color_key => $color_label) { require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-color-field.php'; } ?>
                </div><?php Admin::disclosure_end(); ?>
                <?php Admin::disclosure_start('mao-tk-top-spacing', __('各设备留白', 'mao-tk-zibll-toolkit'), __('桌面、平板与手机', 'mao-tk-zibll-toolkit'), false, 'top-settings'); ?><div class="mao-tk-disclosure__fields"><?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-spacing.php'; ?></div><?php Admin::disclosure_end(); ?>
                <?php Admin::disclosure_start('mao-tk-top-properties', __('文章属性', 'mao-tk-zibll-toolkit'), __('原创、转载等标签', 'mao-tk-zibll-toolkit'), false, 'top-settings'); ?><div class="mao-tk-disclosure__fields">
                <label for="mao-tk-properties"><?php esc_html_e('文章属性列表', 'mao-tk-zibll-toolkit'); ?></label><textarea id="mao-tk-properties" name="top_info[properties]" rows="6" maxlength="60000" aria-describedby="mao-tk-property-help"><?php echo esc_textarea(implode("\n", $settings['properties'])); ?></textarea>
                <p id="mao-tk-property-help" class="description"><?php esc_html_e('每行一个，例如“原创”“转载”。在编辑文章时选择；历史属性保留，未选择时不显示属性徽标。', 'mao-tk-zibll-toolkit'); ?></p>
                </div><?php Admin::disclosure_end(); ?>
                <div class="mao-tk-top-info__actions"><button type="submit" class="button button-primary" <?php disabled(!$state['valid']); ?>><?php esc_html_e('保存设置', 'mao-tk-zibll-toolkit'); ?></button><span class="description" id="mao-tk-dirty-state" role="status"><?php esc_html_e('实时预览不会保存设置。', 'mao-tk-zibll-toolkit'); ?></span></div>
            </form>
        </section>
        <section class="mao-tk-toolkit__panel mao-tk-top-info__preview-panel" aria-labelledby="mao-tk-preview-heading">
            <div class="mao-tk-toolkit__section-head"><div><h2 id="mao-tk-preview-heading"><?php esc_html_e('实时预览', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('颜色、透明度和上下留白立即更新；保存前不改变正式页面。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
            <form id="mao-tk-top-info-preview-form" class="mao-tk-top-info__preview-controls" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" target="mao-tk-top-info-preview" data-preview-action="<?php echo esc_attr(AdminPage::PREVIEW); ?>" data-preview-nonce="<?php echo esc_attr(wp_create_nonce(AdminPage::PREVIEW)); ?>" data-search-action="<?php echo esc_attr(AdminPage::SEARCH); ?>" data-search-nonce="<?php echo esc_attr(wp_create_nonce(AdminPage::SEARCH)); ?>">
                <input type="hidden" name="post_id" id="mao-tk-preview-post" value="0">
                <div class="mao-tk-top-info__search">
                    <label for="mao-tk-preview-search"><?php esc_html_e('预览内容', 'mao-tk-zibll-toolkit'); ?></label><input type="search" id="mao-tk-preview-search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mao-tk-preview-results" autocomplete="off" maxlength="100" placeholder="<?php esc_attr_e('搜索文章/产品标题，或输入 ID', 'mao-tk-zibll-toolkit'); ?>">
                    <ul id="mao-tk-preview-results" class="mao-tk-top-info__results" role="listbox" aria-label="<?php esc_attr_e('预览内容搜索结果', 'mao-tk-zibll-toolkit'); ?>" hidden></ul>
                    <span id="mao-tk-preview-selection" class="description"><?php esc_html_e('当前：样例文章（演示数据）', 'mao-tk-zibll-toolkit'); ?></span>
                </div>
                <button class="button" type="button" id="mao-tk-preview-sample"><?php esc_html_e('使用样例', 'mao-tk-zibll-toolkit'); ?></button>
                <button class="button" type="submit"><?php esc_html_e('更新预览', 'mao-tk-zibll-toolkit'); ?></button>
                <div class="mao-tk-top-info__devices" role="group" aria-label="<?php esc_attr_e('预览设备', 'mao-tk-zibll-toolkit'); ?>"><button type="button" class="button" data-preview-device="desktop" aria-pressed="true"><?php esc_html_e('桌面 1280px', 'mao-tk-zibll-toolkit'); ?></button><button type="button" class="button" data-preview-device="tablet" aria-pressed="false"><?php esc_html_e('平板 900px', 'mao-tk-zibll-toolkit'); ?></button><button type="button" class="button" data-preview-device="mobile" aria-pressed="false"><?php esc_html_e('手机 390px', 'mao-tk-zibll-toolkit'); ?></button></div>
            </form>
            <p class="mao-tk-top-info__preview-note" id="mao-tk-preview-state" role="status"><?php esc_html_e('可按文章或已注册产品的 ID、标题搜索。实际内容预览不调用主题阅读量接口。', 'mao-tk-zibll-toolkit'); ?></p>
            <div class="mao-tk-top-info__preview-scroll" id="mao-tk-preview-scroll"><div id="mao-tk-preview-viewport" class="mao-tk-top-info__preview-viewport"><iframe name="mao-tk-top-info-preview" id="mao-tk-top-info-preview" title="<?php esc_attr_e('文章顶部信息预览', 'mao-tk-zibll-toolkit'); ?>" sandbox="allow-same-origin" referrerpolicy="no-referrer" data-device="desktop"></iframe></div></div>
            <noscript><p class="mao-tk-top-info__preview-note"><?php esc_html_e('保存设置不需要 JavaScript；独立预览与设备切换需要启用 JavaScript。', 'mao-tk-zibll-toolkit'); ?></p></noscript>
        </section>
    </div>
    <footer class="mao-tk-toolkit__footer"><span>Mao TK Zibll Toolkit</span><span><?php esc_html_e('文章顶部信息 · 原配置保留', 'mao-tk-zibll-toolkit'); ?></span></footer>
</div>
