<?php
namespace MaoTK\ZibllToolkit\ProfilePresets;
use MaoTK\ZibllToolkit\Admin;
defined('ABSPATH') || exit;
?>
<div class="wrap mao-tk-toolkit mao-tk-profile-admin" id="mao-tk-toolkit">
    <h1 class="screen-reader-text">头像与背景预设</h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2 class="mao-tk-profile-admin__title">头像与背景预设</h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">统一管理可选图片，为个人主页和用户中心添加预设入口。</p></div></header>
    <?php $switch_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : ''; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if ($notice === 'saved' || $notice === 'conflict') : ?><div class="notice <?php echo $notice === 'saved' ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html($notice === 'saved' ? '预设设置已保存。现有用户的头像和背景未改变。' : '设置已在其他窗口更新或格式异常，本次未覆盖。请刷新后重试。'); ?></p></div><?php endif; ?>
    <?php if (!$state['valid'] || Runtime::conflict() || !Runtime::available()) : ?><div class="notice notice-warning"><p><?php echo esc_html(!$state['valid'] ? '配置格式异常，前台预设功能与写入暂停，原值保留。' : (Runtime::conflict() ? '检测到旧预设头像插件，先停用旧插件再开启本模块。旧图片与旧设置不会被删除。' : '当前主题用户资料接口未就绪，前台预设功能暂不加载。')); ?></p></div><?php endif; ?>
    <section class="mao-tk-toolkit__panel mao-tk-profile-admin__status"><div><h2>用户头像与背景预设 <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $module['status'] === 'loaded' ? 'success' : 'neutral'; ?>"><?php echo esc_html($module['status'] === 'loaded' ? '运行中' : ($module['enabled'] ? '等待就绪' : '未启用')); ?></span></h2><p>仅用户本人确认后保存。原生上传入口和已有图片保留，关闭模块不删除资料。</p></div><?php $switch_module = $module; $switch_valid = $this->manager->storage_is_valid(); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?></section>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-mao-tk-profile-admin>
        <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>"><input type="hidden" name="presets_revision" value="<?php echo esc_attr((string) $settings['revision']); ?>"><?php wp_nonce_field(AdminPage::SAVE); ?>
        <fieldset <?php disabled(!$state['valid']); ?>><div class="mao-tk-profile-admin__columns">
            <div>
                <?php foreach (array('avatar' => '头像', 'cover' => '背景') as $kind => $label) : ?>
                <section class="mao-tk-toolkit__panel mao-tk-profile-admin__library" data-profile-library="<?php echo esc_attr($kind); ?>">
                    <div class="mao-tk-profile-admin__head"><h2><?php echo esc_html($label); ?>预设</h2><span><?php echo esc_html((string) count(Catalog::all($kind, $settings))); ?> 张可选</span></div>
                    <div class="mao-tk-profile-admin__content">
                        <label class="mao-tk-profile-admin__check"><input type="hidden" name="presets[<?php echo esc_attr($kind); ?>_enabled]" value="0"><input type="checkbox" name="presets[<?php echo esc_attr($kind); ?>_enabled]" value="1" <?php checked($settings[$kind . '_enabled']); ?>>开启<?php echo esc_html($label); ?>选择入口</label>
                        <?php $builtin_key = $kind === 'avatar' ? 'builtin_avatars' : 'builtin_covers'; ?>
                        <label class="mao-tk-profile-admin__check"><input type="hidden" name="presets[<?php echo esc_attr($builtin_key); ?>]" value="0"><input type="checkbox" name="presets[<?php echo esc_attr($builtin_key); ?>]" value="1" <?php checked($settings[$builtin_key]); ?>>使用内置图片库（<?php echo esc_html((string) count(Catalog::builtin($kind))); ?> 张）</label>
                        <div class="mao-tk-profile-admin__grid mao-tk-profile-admin__grid--<?php echo esc_attr($kind); ?>"><?php foreach (array_slice(Catalog::builtin($kind), 0, 24) as $item) : ?><img src="<?php echo esc_url($item['thumb']); ?>" alt="<?php echo esc_attr($item['name']); ?>" width="<?php echo esc_attr((string) $item['width']); ?>" height="<?php echo esc_attr((string) $item['height']); ?>" loading="lazy"><?php endforeach; ?></div>
                        <p class="description"><?php echo esc_html($kind === 'avatar' ? '展示前 24 张；前台每页 24 张，可翻页浏览完整图片库。' : '前台按背景比例展示，不裁改原始文件。'); ?></p>
                        <div class="mao-tk-profile-admin__media-head"><strong>补充自定义图片</strong><?php if (current_user_can('upload_files')) : ?><button type="button" class="button" data-mao-tk-profile-media="<?php echo esc_attr($kind); ?>">从媒体库添加</button><?php endif; ?></div>
                        <input type="hidden" name="presets[<?php echo esc_attr($kind); ?>_media]" value="<?php echo esc_attr(implode(',', $settings[$kind . '_media'])); ?>" data-mao-tk-profile-media-ids>
                        <div class="mao-tk-profile-admin__media-list" data-mao-tk-profile-media-list><?php foreach ($settings[$kind . '_media'] as $id) : ?><?php $item = Catalog::media($id); ?><div class="mao-tk-profile-admin__media-item" data-media-id="<?php echo esc_attr((string) $id); ?>"><?php if ($item) : ?><img src="<?php echo esc_url($item['thumb']); ?>" alt="" width="48" height="48"><?php endif; ?><span><?php echo esc_html($item ? $item['name'] : '图片不可用 · ' . $id); ?></span><button type="button" class="button" data-mao-tk-profile-remove>移出预设</button></div><?php endforeach; ?></div><p class="description">仅引用媒体库图片；移出预设不会删除原图。支持 JPG、PNG、WebP、GIF。</p>
                    </div>
                </section><?php endforeach; ?>
            </div>
            <aside>
                <section class="mao-tk-toolkit__panel mao-tk-profile-admin__content"><h2>显示与注册规则</h2>
                    <label class="mao-tk-profile-admin__label" for="mao-tk-profile-avatar-label">头像入口文字</label><input id="mao-tk-profile-avatar-label" name="presets[avatar_label]" type="text" maxlength="60" required value="<?php echo esc_attr($settings['avatar_label']); ?>">
                    <label class="mao-tk-profile-admin__label" for="mao-tk-profile-cover-label">背景入口文字</label><input id="mao-tk-profile-cover-label" name="presets[cover_label]" type="text" maxlength="60" required value="<?php echo esc_attr($settings['cover_label']); ?>">
                    <label class="mao-tk-profile-admin__label" for="mao-tk-profile-modal-title">选择窗口标题</label><input id="mao-tk-profile-modal-title" name="presets[modal_title]" type="text" maxlength="60" required value="<?php echo esc_attr($settings['modal_title']); ?>">
                    <hr><label class="mao-tk-profile-admin__check"><input type="hidden" name="presets[random_on_register]" value="0"><input type="checkbox" name="presets[random_on_register]" value="1" <?php checked($settings['random_on_register']); ?>>新用户注册时随机分配头像</label><p class="description">默认关闭。开启后仅为新注册且没有自定义头像的用户分配；不会批量修改已有用户，不会自动分配背景。</p>
                    <div class="mao-tk-profile-admin__save"><button class="button button-primary" type="submit">保存预设设置</button><p class="description" data-mao-tk-profile-admin-feedback>修改后保存，前台刷新生效。</p></div>
                </section>
                <?php Admin::disclosure_start('mao-tk-profile-help', '使用与兼容说明', '独立开关，保留原生上传', false, 'profile-presets'); ?><div class="mao-tk-profile-admin__content"><p>用户中心头部和自己的个人主页菜单中显示预设入口。先选图，再确认；取消不改变资料。</p><p>关闭内置图库后，可以只使用媒体库中的自定义图片。没有可用图片时，前台显示空状态。</p><p>旧头像插件若仍启用，本模块暂停前台接入，避免重复菜单和写入。停用旧插件即可，不需要删除旧文件或设置。</p><p>预设文件跟随 Toolkit 保存，卸载前请先处理使用中的头像 / 背景引用；停用不会删除用户资料。</p></div><?php Admin::disclosure_end(); ?>
            </aside>
        </div></fieldset>
    </form>
</div>
