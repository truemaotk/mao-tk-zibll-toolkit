<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
/** @var Admin $this View variables are created by Admin::render(). */
?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <h1 class="screen-reader-text">Mao TK Zibll Toolkit</h1>
    <header class="mao-tk-toolkit__header">
        <div class="mao-tk-toolkit__identity">
            <?php if ($icon_exists) : ?>
                <img class="mao-tk-toolkit__brand" src="<?php echo esc_url(MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/images/maotk-icon.svg'); ?>" alt="" width="48" height="48">
            <?php endif; ?>
            <div>
                <div class="mao-tk-toolkit__title-line">
                    <h2>Mao TK Zibll Toolkit</h2>
                    <span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span>
                </div>
                <p class="mao-tk-toolkit__intro"><?php esc_html_e('集中管理你的主题扩展，按需启用每一项能力。', 'mao-tk-zibll-toolkit'); ?></p>
            </div>
        </div>
        <a class="mao-tk-toolkit__language" href="<?php echo esc_url(get_edit_profile_url() . '#locale'); ?>">
            <?php echo esc_html(get_user_locale() === 'zh_CN' ? __('界面语言 · 简体中文', 'mao-tk-zibll-toolkit') : __('界面语言', 'mao-tk-zibll-toolkit')); ?>
        </a>
    </header>
    <?php $switch_notice = $notice; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>

    <div class="mao-tk-toolkit__feedback" aria-live="polite" aria-atomic="true">
        <?php if (!$theme['is_zibll']) : ?>
            <div class="mao-tk-toolkit__message mao-tk-toolkit__message--warning" role="status">
                <span class="dashicons dashicons-info" aria-hidden="true"></span>
                <div><strong><?php esc_html_e('尚未识别到 Zibll 主题', 'mao-tk-zibll-toolkit'); ?></strong>
                <p><?php esc_html_e('工作台仍可使用。依赖主题的模块暂不加载，启用 Zibll 或其子主题后再查看状态。', 'mao-tk-zibll-toolkit'); ?></p></div>
            </div>
        <?php endif; ?>
        <?php if (!$storage_valid || $registration_failed) : ?>
            <div class="mao-tk-toolkit__message mao-tk-toolkit__message--error" role="alert">
                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                <span><?php echo esc_html(!$storage_valid
                    ? __('配置格式异常，模块加载和设置写入已暂停；原有数据保持不变，请先检查配置来源。', 'mao-tk-zibll-toolkit')
                    : __('有扩展模块未能完成注册。已注册模块仍会独立加载，请检查最近添加的模块。', 'mao-tk-zibll-toolkit')); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="mao-tk-toolkit__layout">
        <div class="mao-tk-toolkit__main">
            <section class="mao-tk-toolkit__panel" aria-labelledby="mao-tk-modules-title">
                <div class="mao-tk-toolkit__section-head">
                    <div>
                        <h2 id="mao-tk-modules-title"><?php esc_html_e('扩展模块', 'mao-tk-zibll-toolkit'); ?></h2>
                        <p><?php esc_html_e('只启用需要的功能，各模块独立管理。', 'mao-tk-zibll-toolkit'); ?></p>
                    </div>
                    <span class="mao-tk-toolkit__count"><?php echo esc_html(sprintf(
                        __('%1$d / %2$d 运行中', 'mao-tk-zibll-toolkit'), $loaded, count($modules)
                    )); ?></span>
                </div>
                <div class="mao-tk-toolkit__module-list">
                    <?php
                    $groups = array(
                        'display' => array(__('文章展示', 'mao-tk-zibll-toolkit'), array('top-info', 'article-freshness', 'archive-header')),
                        'content' => array(__('正文组件', 'mao-tk-zibll-toolkit'), array('article-ui-code', 'article-ui-link-preview', 'article-ui-tabs', 'article-ui-postsbox', 'article-ui-reading', 'article-ui-resources', 'article-note')),
                        'pages' => array(__('页面与链接', 'mao-tk-zibll-toolkit'), array('page-showcase', 'private-links', 'user-profile-presets', 'page-order-query')),
                        'site' => array(__('站点功能', 'mao-tk-zibll-toolkit'), array('site-maintenance', 'site-not-found', 'system-mail-appearance', 'site-custom-footer', 'r2-downloads', 'r2-media')),
                        'migration' => array(__('导入&导出', 'mao-tk-zibll-toolkit'), array('data-migration-links','data-migration-posts','data-migration-products')),
                        'workspace' => array(__('工作台与扩展', 'mao-tk-zibll-toolkit'), array()),
                    );
                    $known = array_merge($groups['display'][1], $groups['content'][1], $groups['pages'][1], $groups['site'][1], $groups['migration'][1]);
                    foreach ($groups as $group_id => $group) :
                        $group_modules = array_filter($modules, static function (array $module) use ($group, $group_id, $known): bool {
                            return $group_id === 'workspace' ? !in_array($module['id'], $known, true) : in_array($module['id'], $group[1], true);
                        });
                        if (!$group_modules) { continue; }
                        Admin::disclosure_start('mao-tk-group-' . $group_id, $group[0], count($group_modules) . __(' 项模块', 'mao-tk-zibll-toolkit'), false, 'dashboard-modules');
                    ?>
                    <?php foreach ($group_modules as $module) : ?>
                        <?php $state = $this->state($module['status']); ?>
                        <article class="mao-tk-toolkit__module" aria-labelledby="<?php echo esc_attr('mao-tk-module-' . $module['id']); ?>">
                            <div class="mao-tk-toolkit__module-copy">
                                <div class="mao-tk-toolkit__module-heading">
                                    <h3 id="<?php echo esc_attr('mao-tk-module-' . $module['id']); ?>"><?php echo esc_html($module['name']); ?></h3>
                                </div>
                                <p><?php echo esc_html($module['description']); ?></p>
                                <p class="mao-tk-toolkit__module-note" id="<?php echo esc_attr('mao-tk-note-' . $module['id']); ?>"><?php echo esc_html($state[2]); ?></p>
                                <?php if ($module['id'] === 'top-info') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . TopInfo\AdminPage::SLUG)); ?>"><?php esc_html_e('设置与预览', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'article-freshness') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . Freshness\AdminPage::SLUG)); ?>"><?php esc_html_e('设置与预览', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if (strpos($module['id'], 'article-ui-') === 0) : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . ArticleUI\AdminPage::SLUG)); ?>"><?php esc_html_e('组件与使用说明', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'article-note') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . ArticleNote\AdminPage::SLUG)); ?>"><?php esc_html_e('设置与预览', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'page-showcase') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . Showcase\AdminPage::SLUG)); ?>"><?php esc_html_e('管理案例、分类与外观', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'private-links') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . PrivateLinks\AdminPage::SLUG)); ?>"><?php esc_html_e('管理角色与账号可见范围', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'site-maintenance') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . Maintenance\AdminPage::SLUG)); ?>"><?php esc_html_e('编辑维护页面与预览', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'user-profile-presets') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . ProfilePresets\AdminPage::SLUG)); ?>"><?php esc_html_e('管理头像、背景与注册规则', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'page-order-query') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . OrderQuery\AdminPage::SLUG)); ?>"><?php esc_html_e('设置与创建查询页面', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'site-not-found') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . NotFound\AdminPage::SLUG)); ?>"><?php esc_html_e('设置与预览 404 公益页面', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'site-custom-footer') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . CustomFooter\AdminPage::SLUG)); ?>"><?php esc_html_e('页脚栏目、社交图标与预览', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'archive-header') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . ArchiveHeader\AdminPage::SLUG)); ?>"><?php esc_html_e('封面和正文描述设置', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'r2-downloads') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . R2Downloads\AdminPage::SLUG)); ?>"><?php esc_html_e('上传文件和生成下载引用', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'r2-media') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . R2Media\AdminPage::SLUG)); ?>"><?php esc_html_e('设置媒体连接与历史图片同步', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                                <?php if ($module['id'] === 'system-mail-appearance') : ?><p class="mao-tk-toolkit__module-note"><a href="<?php echo esc_url(admin_url('admin.php?page=' . SystemMail\AdminPage::SLUG)); ?>"><?php esc_html_e('邮件通用模板、类型设置与预览', 'mao-tk-zibll-toolkit'); ?></a></p><?php endif; ?>
                            </div>
                            <div class="mao-tk-toolkit__module-actions">
                                <?php $switch_module = $module; $switch_valid = $storage_valid; $switch_return_page = self::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
                                <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($state[0]); ?>"><?php echo esc_html($state[1]); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php Admin::disclosure_end(); endforeach; ?>
                    <?php if (!$modules) : ?>
                        <p class="mao-tk-toolkit__empty"><?php esc_html_e('当前没有已注册模块。添加模块后，它会显示在这里。', 'mao-tk-zibll-toolkit'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="mao-tk-toolkit__panel-foot"><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php esc_html_e('停用模块会保留设置。此工作台不会修改主题文件。', 'mao-tk-zibll-toolkit'); ?></div>
            </section>

            <div data-mao-tk-dashboard-content>
            <?php
            try {
                do_action('mao_tk_zibll_toolkit_dashboard_content');
            } catch (\Throwable $error) {
                echo '<div class="mao-tk-toolkit__message mao-tk-toolkit__message--error" role="alert">';
                echo esc_html__('一个模块未能完成页面输出，工作台其它区域仍可使用。请检查最近启用的模块。', 'mao-tk-zibll-toolkit');
                echo '</div>';
            }
            ?>
            </div>

        </div>

        <aside class="mao-tk-toolkit__aside" aria-labelledby="mao-tk-environment-title">
            <section class="mao-tk-toolkit__environment">
                <h2 id="mao-tk-environment-title"><?php esc_html_e('运行环境', 'mao-tk-zibll-toolkit'); ?></h2>
                <div class="mao-tk-toolkit__theme-state">
                    <span class="dashicons <?php echo $theme['is_zibll'] ? 'dashicons-yes-alt' : 'dashicons-info'; ?>" aria-hidden="true"></span>
                    <div><strong><?php echo esc_html($theme['is_zibll'] ? __('已识别 Zibll', 'mao-tk-zibll-toolkit') : __('主题未就绪', 'mao-tk-zibll-toolkit')); ?></strong>
                    <span><?php echo esc_html($theme['is_child'] ? __('当前使用子主题', 'mao-tk-zibll-toolkit') : __('以父主题目录识别依赖', 'mao-tk-zibll-toolkit')); ?></span></div>
                </div>
                <dl class="mao-tk-toolkit__environment-list">
                    <div><dt><?php esc_html_e('主题版本', 'mao-tk-zibll-toolkit'); ?></dt><dd><?php echo esc_html($theme['version'] !== '' ? $theme['version'] : __('未获取', 'mao-tk-zibll-toolkit')); ?></dd></div>
                    <div><dt><?php esc_html_e('父主题目录', 'mao-tk-zibll-toolkit'); ?></dt><dd><?php echo esc_html($theme['template'] !== '' ? $theme['template'] : __('未获取', 'mao-tk-zibll-toolkit')); ?></dd></div>
                    <div><dt>WordPress</dt><dd><?php echo esc_html((string) $wp_version); ?></dd></div>
                    <div><dt>PHP</dt><dd><?php echo esc_html(PHP_VERSION); ?></dd></div>
                </dl>
                <p class="mao-tk-toolkit__environment-note"><?php esc_html_e('版本号来自当前环境；主题识别不等于已完成该版本的兼容性测试。', 'mao-tk-zibll-toolkit'); ?></p>
            </section>
            <section class="mao-tk-toolkit__guide" aria-labelledby="mao-tk-guide-title">
                <h2 id="mao-tk-guide-title"><?php esc_html_e('使用说明', 'mao-tk-zibll-toolkit'); ?></h2>
                <ol>
                    <li><?php esc_html_e('在模块列表中启用需要的功能。', 'mao-tk-zibll-toolkit'); ?></li>
                    <li><?php esc_html_e('状态变为“运行中”后，检查模块效果。', 'mao-tk-zibll-toolkit'); ?></li>
                    <li><?php esc_html_e('停用模块只关闭功能，保留原有设置。', 'mao-tk-zibll-toolkit'); ?></li>
                </ol>
                <p><a href="<?php echo esc_url(MAO_TK_ZIBLL_TOOLKIT_URL . 'README.md'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('完整文档与模块开发', 'mao-tk-zibll-toolkit'); ?><span class="screen-reader-text"><?php esc_html_e('（在新标签页打开）', 'mao-tk-zibll-toolkit'); ?></span></a></p>
            </section>
            <section class="mao-tk-toolkit__about" aria-labelledby="mao-tk-about-title">
                <h2 id="mao-tk-about-title"><?php esc_html_e('关于工具箱', 'mao-tk-zibll-toolkit'); ?></h2>
                <p><?php esc_html_e('一个轻量、可逐步扩展的 Zibll 工作台。功能按需加载，设置由你掌握。', 'mao-tk-zibll-toolkit'); ?></p>
                <a href="https://www.maotk.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Mao TK 作者主页', 'mao-tk-zibll-toolkit'); ?><span class="dashicons dashicons-external" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e('（在新标签页打开）', 'mao-tk-zibll-toolkit'); ?></span></a>
            </section>
        </aside>
    </div>
    <footer class="mao-tk-toolkit__footer"><span>Mao TK Zibll Toolkit</span><span>v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></footer>
</div>
