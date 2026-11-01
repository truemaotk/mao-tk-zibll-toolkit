<?php
namespace MaoTK\ZibllToolkit\Maintenance;
use MaoTK\ZibllToolkit\Admin;
defined('ABSPATH') || exit;
?>
<div class="wrap mao-tk-toolkit mao-tk-maintenance-admin" id="mao-tk-toolkit">
    <h1 class="screen-reader-text">站点维护页面</h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2 class="mao-tk-maintenance-admin__title">站点维护页面</h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">配置维护说明与预计恢复时间。先预览，确认后再开启维护。</p></div></header>
    <?php $switch_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : ''; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if (in_array($notice, array('saved', 'imported', 'failed'), true)) : ?><div class="notice <?php echo $notice === 'failed' ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html($notice === 'saved' ? '维护页设置已保存。维护开关保持原状。' : ($notice === 'imported' ? '已导入旧维护页内容，未导入开启状态；请核对 Logo、备用站点和页脚信息。' : '保存失败，请重试。')); ?></p></div><?php endif; ?>
    <?php if (!$state['valid'] || $conflict) : ?><div class="notice notice-error"><p><?php echo esc_html($conflict ? '旧维护插件仍在运行。请先关闭旧维护模式，再启用 Toolkit；不会自动停用或删除旧插件。' : '配置格式异常，维护拦截已跳过，原始配置保持不变。请检查配置后继续。'); ?></p></div><?php endif; ?>
    <section class="mao-tk-toolkit__panel mao-tk-maintenance-admin__status">
        <div><h2>访客维护模式 <span class="mao-tk-toolkit__badge <?php echo $module['status'] === 'loaded' ? 'mao-tk-toolkit__badge--warning' : 'mao-tk-toolkit__badge--neutral'; ?>"><?php echo esc_html($module['status'] === 'loaded' ? '已开启' : ($module['status'] === 'error' ? '加载失败' : '未开启')); ?></span></h2><p>开启后，普通访客网页返回维护页与 HTTP 503。管理员可正常浏览，后台与登录入口保留。</p><p class="description">保存设置或打开预览不会开启维护。关闭后保留全部内容。</p></div>
        <?php $switch_module = $module; $switch_valid = $this->manager->storage_is_valid() && ($module['enabled'] || ($state['valid'] && !$conflict)); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
    </section>
    <div class="mao-tk-maintenance-admin__columns">
        <div>
            <form class="mao-tk-toolkit__panel mao-tk-maintenance-admin__form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-mao-tk-maintenance-form>
                <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>"><?php wp_nonce_field(AdminPage::SAVE); ?>
                <fieldset <?php disabled(!$state['valid']); ?>>
                    <div class="mao-tk-maintenance-admin__section"><h2>维护内容</h2>
                        <label for="mao-tk-maintenance-title">维护标题</label><input id="mao-tk-maintenance-title" name="maintenance[title]" type="text" value="<?php echo esc_attr($settings['title']); ?>" required maxlength="180">
                        <label for="mao-tk-maintenance-description">说明正文</label><textarea id="mao-tk-maintenance-description" name="maintenance[description]" rows="4"><?php echo esc_textarea($settings['description']); ?></textarea><p class="description">支持 p、br、strong、em、列表和普通链接，脚本与内嵌样式会被移除。</p>
                        <label for="mao-tk-maintenance-end">预计恢复时间</label><input id="mao-tk-maintenance-end" name="maintenance[end_at]" type="datetime-local" value="<?php echo esc_attr($settings['end_at']); ?>"><p class="description">站点时区：<?php echo esc_html(wp_timezone_string()); ?>。留空显示“恢复时间待确认”；到期后仍保持维护，需手动关闭。</p>
                    </div>
                    <?php Admin::disclosure_start('mao-tk-maintenance-appearance', 'Logo 与背景', '本地图片、六种渐变', false, 'maintenance'); ?>
                    <div class="mao-tk-maintenance-admin__section">
                        <label for="mao-tk-maintenance-logo">Logo 图片网址</label><input id="mao-tk-maintenance-logo" name="maintenance[logo_url]" type="url" value="<?php echo esc_attr($settings['logo_url']); ?>" placeholder="https://" autocomplete="off">
                        <?php if (current_user_can('upload_files')) : ?><button type="button" class="button" data-mao-tk-maintenance-media>从媒体库选择</button><?php endif; ?><p class="description">支持直接填写图片网址。留空使用站点 Logo / 图标；都没有时显示站点名称。</p>
                        <label for="mao-tk-maintenance-background">背景配色</label><select id="mao-tk-maintenance-background" name="maintenance[background]"><?php foreach (Settings::palettes() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($settings['background'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                    </div><?php Admin::disclosure_end(); ?>
                    <?php Admin::disclosure_start('mao-tk-maintenance-footer', '备用入口与页脚', '按需显示，不填则隐藏', false, 'maintenance'); ?>
                    <div class="mao-tk-maintenance-admin__section">
                        <label for="mao-tk-maintenance-backup">备用站点网址</label><input id="mao-tk-maintenance-backup" name="maintenance[backup_url]" type="url" value="<?php echo esc_attr($settings['backup_url']); ?>" placeholder="https://" autocomplete="off">
                        <label for="mao-tk-maintenance-backup-label">入口文字</label><input id="mao-tk-maintenance-backup-label" name="maintenance[backup_label]" type="text" value="<?php echo esc_attr($settings['backup_label']); ?>" maxlength="180">
                        <label for="mao-tk-maintenance-copyright">版权文字</label><input id="mao-tk-maintenance-copyright" name="maintenance[copyright]" type="text" value="<?php echo esc_attr($settings['copyright']); ?>" placeholder="留空显示年份与站点名称" maxlength="500">
                        <label for="mao-tk-maintenance-beian">备案号</label><input id="mao-tk-maintenance-beian" name="maintenance[beian]" type="text" value="<?php echo esc_attr($settings['beian']); ?>" maxlength="120">
                        <label for="mao-tk-maintenance-retry">建议重试间隔（秒）</label><input id="mao-tk-maintenance-retry" name="maintenance[retry_after]" type="number" min="60" max="86400" value="<?php echo esc_attr((string) $settings['retry_after']); ?>"><p class="description">用于维护响应的 Retry-After 请求头；不会定时刷新或自动跳转。</p>
                    </div><?php Admin::disclosure_end(); ?>
                    <div class="mao-tk-maintenance-admin__actions"><button type="submit" class="button button-primary" name="maintenance_operation" value="save">保存设置</button><button type="submit" class="button" name="maintenance_operation" value="preview" formtarget="mao-tk-maintenance-preview">预览当前输入</button><span class="description" data-mao-tk-maintenance-feedback>预览不会保存或改变开关。</span></div>
                </fieldset>
            </form>
            <?php if (!$state['exists'] && is_array(get_option('VaiPlugin', null))) : ?><form class="mao-tk-toolkit__panel mao-tk-maintenance-admin__section" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><h2>旧插件内容导入</h2><p>仅导入标题、说明、Logo、恢复时间和页脚，不导入开启状态，不覆盖或删除旧配置。</p><input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>"><?php wp_nonce_field(AdminPage::SAVE); ?><button class="button" name="maintenance_operation" value="import" type="submit">导入旧维护页内容</button></form><?php endif; ?>
        </div>
        <aside class="mao-tk-toolkit__panel mao-tk-maintenance-admin__preview"><div class="mao-tk-maintenance-admin__preview-head"><div><h2>维护页预览</h2><p class="description">此处可在关闭状态预览。</p></div><a class="button" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener">完整预览</a></div><?php if ($state['valid']) : ?><iframe title="维护页面预览" name="mao-tk-maintenance-preview" src="<?php echo esc_url($preview_url); ?>" sandbox="allow-scripts allow-same-origin" referrerpolicy="same-origin"></iframe><?php endif; ?></aside>
    </div>
    <?php Admin::disclosure_start('mao-tk-maintenance-boundaries', '启用、访问与缓存说明', '管理员放行，接口保持独立', false, 'maintenance-help'); ?><div class="mao-tk-maintenance-admin__section"><p>维护模式只替换普通前台网页。WordPress 后台、原生登录、子比登录模板、REST / AJAX、计划任务、订阅与 robots 请求不由本模块拦截。它不是私密内容权限系统，不能用来保护原文件或接口。</p><p>开启或关闭后，请按网站原有流程清除页面缓存与 CDN 缓存；直接命中 CDN 的旧缓存可能不会执行 WordPress。管理员预览始终使用私有、不缓存的响应。</p><p>若无法进入子比登录页，可使用 <a href="<?php echo esc_url(site_url('wp-login.php', 'login')); ?>" target="_blank" rel="noopener">WordPress 登录入口</a>。维护页不依赖主题资源，停用模块立即恢复正常渲染，内容配置继续保留。</p></div><?php Admin::disclosure_end(); ?>
</div>
