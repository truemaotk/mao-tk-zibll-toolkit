<?php
namespace MaoTK\ZibllToolkit\PrivateLinks;

use MaoTK\ZibllToolkit\Admin;

defined('ABSPATH') || exit;
$selected_roles = count(array_intersect(array_keys($roles), $settings['allowed_roles']));
$running = $module['status'] === 'loaded';
$status_label = $running ? '运行中' : ($module['status'] === 'disabled' ? '未启用' : '暂不可用');
$status_tone = $running ? 'success' : ($module['status'] === 'disabled' ? 'neutral' : 'warning');
$can_list_users = current_user_can('list_users');
?>
<div class="wrap mao-tk-toolkit mao-tk-private-links" id="mao-tk-toolkit">
    <h1 class="screen-reader-text">私密链接可见性</h1>
    <header class="mao-tk-toolkit__header">
        <div>
            <div class="mao-tk-toolkit__title-line"><h2>私密链接可见性</h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div>
            <p class="mao-tk-toolkit__intro">选择哪些角色和账号可以在前台查看私密链接。</p>
        </div>
        <div class="mao-tk-private-links__page-actions">
            <a href="<?php echo esc_url(admin_url('link-manager.php')); ?>">管理网站链接</a>
        </div>
    </header>

    <?php $switch_notice = $module_notice; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if ($notice === 'saved') : ?>
        <div class="mao-tk-toolkit__message" role="status">可见范围已保存，刷新前台页面后生效。</div>
    <?php elseif ($notice === 'failed') : ?>
        <div class="mao-tk-toolkit__message" role="alert">保存未成功，请重试。</div>
    <?php endif; ?>
    <?php if (!$state['valid']) : ?>
        <div class="mao-tk-toolkit__message" role="alert">配置异常，私密链接已按不可见处理，原始配置未重置。</div>
    <?php endif; ?>

    <section class="mao-tk-toolkit__panel mao-tk-private-links__status" aria-labelledby="mao-tk-private-status-title">
        <div>
            <div class="mao-tk-private-links__status-title">
                <h2 id="mao-tk-private-status-title">前台私密链接</h2>
                <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($status_tone); ?>" data-mao-tk-runtime-state><?php echo esc_html($status_label); ?></span>
            </div>
            <p>开启后按下方规则显示；关闭后恢复子比原生隐藏方式。游客始终不可见。</p>
        </div>
        <?php $switch_module = $module; $switch_valid = $this->manager->storage_is_valid(); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
    </section>

    <div class="mao-tk-private-links__workspace<?php echo $can_list_users ? '' : ' mao-tk-private-links__workspace--roles-only'; ?>">
        <section class="mao-tk-toolkit__panel mao-tk-private-links__section mao-tk-private-links__role-panel" aria-labelledby="mao-tk-private-role-title">
            <div class="mao-tk-private-links__section-head">
                <div class="mao-tk-private-links__heading-row"><h2 id="mao-tk-private-role-title">可见角色</h2><span class="mao-tk-private-links__meta">可多选</span></div>
                <p id="mao-tk-private-role-description">用户属于任一选中角色，即可查看。</p>
            </div>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-private-form="roles">
                <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>">
                <input type="hidden" name="section" value="roles">
                <?php wp_nonce_field(AdminPage::SAVE); ?>
                <fieldset class="mao-tk-private-links__roles" aria-describedby="mao-tk-private-role-description">
                    <legend class="screen-reader-text">选择可查看私密链接的角色，可多选</legend>
                    <?php foreach ($roles as $key => $role) : ?>
                        <label class="mao-tk-private-links__role" title="<?php echo esc_attr($key); ?>">
                            <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $settings['allowed_roles'], true)); ?>>
                            <span><?php echo esc_html(translate_user_role($role['name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <div class="mao-tk-private-links__role-summary"><span data-private-role-count>已选 <?php echo esc_html((string) $selected_roles); ?> / <?php echo esc_html((string) count($roles)); ?> 项</span><span class="mao-tk-private-links__meta">不选则按账号单独允许</span></div>
                <div class="mao-tk-private-links__actions mao-tk-private-links__actions--roles">
                    <button class="button button-primary" type="submit" <?php disabled(!$state['valid']); ?>>保存角色规则</button>
                    <span class="mao-tk-private-links__save-state" role="status" data-private-form-state>当前规则已保存</span>
                </div>
            </form>
        </section>

        <?php if ($can_list_users) : ?>
        <section class="mao-tk-toolkit__panel mao-tk-private-links__section mao-tk-private-links__account-panel" aria-labelledby="mao-tk-private-account-title">
            <div class="mao-tk-private-links__section-head">
                <div class="mao-tk-private-links__heading-row"><h2 id="mao-tk-private-account-title">账号例外规则</h2><span class="mao-tk-private-links__meta"><?php echo esc_html((string) $total); ?> 位用户</span></div>
                <p>单独禁止优先于允许，单独允许优先于角色规则。</p>
            </div>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="mao-tk-private-links__search">
                <input type="hidden" name="page" value="<?php echo esc_attr(AdminPage::SLUG); ?>">
                <label class="screen-reader-text" for="mao-tk-private-user-search">搜索用户名或显示名称</label>
                <input type="search" id="mao-tk-private-user-search" name="user_search" value="<?php echo esc_attr($search); ?>" placeholder="搜索用户名或显示名称" autocomplete="off">
                <button class="button" type="submit">搜索用户</button>
                <?php if ($search !== '') : ?><a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminPage::SLUG)); ?>">清除搜索</a><?php endif; ?>
            </form>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-private-form="users">
                <input type="hidden" name="action" value="<?php echo esc_attr(AdminPage::SAVE); ?>">
                <input type="hidden" name="section" value="users">
                <input type="hidden" name="user_page" value="<?php echo esc_attr((string) $page); ?>">
                <input type="hidden" name="user_search" value="<?php echo esc_attr($search); ?>">
                <?php wp_nonce_field(AdminPage::SAVE); ?>
                <div class="mao-tk-private-links__table">
                    <table class="widefat">
                        <thead><tr><th scope="col">用户</th><th scope="col">所属角色</th><th scope="col">单独规则</th><th scope="col">已保存结果</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user) : ?>
                            <?php $names = array(); foreach ($user->roles as $key) { $names[] = isset($roles[$key]) ? translate_user_role($roles[$key]['name']) : $key; } $allowed = Settings::permits($user, $settings); ?>
                            <tr>
                                <td class="mao-tk-private-links__user" data-label="用户"><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_login . ' · ID ' . $user->ID); ?></small></td>
                                <td data-label="所属角色"><?php echo esc_html(implode('、', $names) ?: '无角色'); ?></td>
                                <td data-label="单独规则">
                                    <label class="screen-reader-text" for="mao-tk-private-user-<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name); ?>的私密链接规则</label>
                                    <select id="mao-tk-private-user-<?php echo esc_attr((string) $user->ID); ?>" name="user_override[<?php echo esc_attr((string) $user->ID); ?>]">
                                        <?php foreach (array('inherit' => '遵循角色', 'allow' => '单独允许', 'deny' => '单独禁止') as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['user_overrides'][$user->ID] ?? 'inherit', $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td data-label="已保存结果"><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $allowed ? 'success' : 'neutral'; ?>"><?php echo $allowed ? '允许查看' : '不可见'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users) : ?><tr class="mao-tk-private-links__empty"><td colspan="4">没有找到用户，请尝试其他名称。</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mao-tk-private-links__actions">
                    <div class="mao-tk-private-links__save-group"><button class="button button-primary" type="submit" <?php disabled(!$state['valid'] || !$users); ?>>保存本页账号规则</button><span class="mao-tk-private-links__save-state" role="status" data-private-form-state>当前规则已保存</span></div>
                    <span class="mao-tk-private-links__meta">每页 20 位，翻页前请先保存。</span>
                </div>
            </form>
            <?php if ($total > 20) : ?>
            <nav class="mao-tk-private-links__pagination" aria-label="用户列表分页"><?php echo wp_kses_post(paginate_links(array('base' => add_query_arg(array('page' => AdminPage::SLUG, 'user_search' => $search, 'user_page' => '%#%'), admin_url('admin.php')), 'format' => '', 'current' => $page, 'total' => max(1, (int) ceil($total / 20)), 'prev_text' => '上一页', 'next_text' => '下一页'))); ?></nav>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <div class="mao-tk-private-links__help">
        <?php Admin::disclosure_start('mao-tk-private-help', '规则说明与显示范围', '链接保留私密标记，不改变账号原有权限', false, 'private-links-help'); ?>
        <div class="mao-tk-private-links__help-copy">
        <p><strong>权限优先级：</strong>账号单独禁止优先于单独允许，单独允许优先于角色。保存只更新对应区域；账号表单只更新本页用户，其他页的规则保留。</p>
        <p><strong>生效范围：</strong>WordPress 链接列表、子比网址导航和链接详情页。链接仍为私密，原生后台“待审核”计数不变。允许用户的页面禁止共享缓存。</p>
        <p>这里只控制链接显示，不改变用户角色、会员或购买权益。其他扩展直接查询数据库、手写进文章的 URL 和旧的外部缓存不属于此规则。调整权限后请刷新前台页面。</p>
        </div>
        <?php Admin::disclosure_end(); ?>
    </div>
</div>
