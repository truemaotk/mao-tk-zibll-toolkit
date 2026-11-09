<?php
namespace MaoTK\ZibllToolkit\R2Media;

defined('ABSPATH') || exit;

$action_url = admin_url('admin-post.php');
?>
<div class="wrap mao-tk-toolkit mtk-r2-media" id="mao-tk-toolkit">
    <h1 class="screen-reader-text"><?php esc_html_e('R2 图片同步', 'mao-tk-zibll-toolkit'); ?></h1>
    <header class="mao-tk-toolkit__header">
        <div>
            <div class="mao-tk-toolkit__title-line"><h2><?php esc_html_e('R2 图片同步', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div>
            <p class="mao-tk-toolkit__intro"><?php esc_html_e('保留 WordPress 原图，按访问同步旧图，也可以批量同步和恢复。', 'mao-tk-zibll-toolkit'); ?></p>
        </div>
    </header>
    <?php $switch_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : ''; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if (!empty($unreadable)) : ?>
        <div class="notice inline notice-error"><p><?php echo esc_html(sprintf(
            /* translators: %s: list of credential field names. */
            __('以下已保存的密钥无法解密：%s。通常是 WordPress 安全密钥（AUTH_KEY / AUTH_SALT）被更换，请重新填写这些密钥并保存。', 'mao-tk-zibll-toolkit'),
            implode(' / ', $unreadable)
        )); ?></p></div>
    <?php endif; ?>
    <?php if (is_array($notice)) : ?>
        <div class="notice inline <?php echo $notice['ok'] ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html($notice['message']); ?></p></div>
    <?php endif; ?>
    <section class="mao-tk-toolkit__panel">
        <div class="mao-tk-toolkit__module mtk-r2-media__state">
            <div><div class="mao-tk-toolkit__module-heading"><h2><?php esc_html_e('自动同步', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $running ? 'success' : 'neutral'; ?>"><?php echo $running ? __('已启用', 'mao-tk-zibll-toolkit') : __('未启用', 'mao-tk-zibll-toolkit'); ?></span></div><p><?php echo $running ? __('访客访问时，页面中识别到的旧图进入同步队列。', 'mao-tk-zibll-toolkit') : __('保存媒体桶凭据后即可开启。', 'mao-tk-zibll-toolkit'); ?></p></div>
            <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
        </div>
    </section>
    <div class="mtk-r2-media__layout">
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__section-head"><div><div class="mtk-r2-media__job-line"><h2><?php esc_html_e('连接设置', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $configured ? 'success' : 'neutral'; ?>"><?php echo $configured ? __('已配置', 'mao-tk-zibll-toolkit') : __('待配置', 'mao-tk-zibll-toolkit'); ?></span></div><p><?php esc_html_e('使用独立私有媒体桶及仅限该桶的 Object Read &amp; Write 凭据。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
            <form class="mtk-r2-media__body" action="<?php echo esc_url($action_url); ?>" method="post" autocomplete="off">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="operation" value="save">
                <?php wp_nonce_field(self::ACTION); ?>
                <div class="mtk-r2-media__fields">
                    <?php foreach (Settings::FIELDS as $field => $rules) :
                        if ($field === 'MAOTK_MEDIA_REWRITE') { continue; }
                        $secret = $rules['secret'];
                        $value = Settings::get($field);
                        $placeholder = $secret ? ($value !== '' ? '••••••••••••••••' : __('请输入凭据', 'mao-tk-zibll-toolkit')) : '';
                        if ($field === 'MAOTK_MEDIA_BUCKET') { $placeholder = 'maotk-site-media'; }
                        if ($field === 'MAOTK_MEDIA_ORIGIN') { $placeholder = 'https://media.maotk.com'; }
                        ?>
                        <label><span class="mtk-r2-media__field-label"><?php echo esc_html(translate($rules['label'], 'mao-tk-zibll-toolkit')); ?>
                            <?php if ($secret && $value !== '') : ?><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--success"><?php esc_html_e('已保存', 'mao-tk-zibll-toolkit'); ?></span><?php endif; ?>
                            </span>
                            <input type="<?php echo $secret ? 'password' : ($field === 'MAOTK_MEDIA_ORIGIN' ? 'url' : 'text'); ?>"
                                   name="media[<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($secret ? '' : $value); ?>"
                                   autocomplete="<?php echo $secret ? 'new-password' : 'off'; ?>" autocapitalize="none" spellcheck="false"
                                   maxlength="<?php echo (int) $rules['limit']; ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php disabled(defined($field)); ?>>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mtk-r2-media__rewrite">
                    <label><input type="checkbox" name="media[MAOTK_MEDIA_REWRITE]" value="1" <?php checked(Settings::get('MAOTK_MEDIA_REWRITE'), '1'); ?> <?php disabled(defined('MAOTK_MEDIA_REWRITE')); ?>> <?php esc_html_e('启用前台地址切换', 'mao-tk-zibll-toolkit'); ?></label>
                    <p class="mtk-r2-media__muted"><?php esc_html_e('连接测试通过且文件同步完成后，公开页面才使用媒体域名。', 'mao-tk-zibll-toolkit'); ?></p>
                </div>
                <div class="mao-tk-toolkit__panel-foot mtk-r2-media__actions">
                    <span><?php esc_html_e('签名密钥应与 Worker 的', 'mao-tk-zibll-toolkit'); ?> <code>MAOTK_MEDIA_SIGNING_KEY</code> <?php esc_html_e('一致。', 'mao-tk-zibll-toolkit'); ?></span>
                    <?php submit_button(__('保存连接设置', 'mao-tk-zibll-toolkit'), 'primary', 'submit', false); ?>
                </div>
            </form>
            <form class="mao-tk-toolkit__panel-foot mtk-r2-media__actions" action="<?php echo esc_url($action_url); ?>" method="post">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="operation" value="migrate-credentials">
                <?php wp_nonce_field(self::ACTION); ?>
                <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/credential-migration-view.php'; ?>
            </form>
        </section>
        <div class="mtk-r2-media__side">
            <section class="mao-tk-toolkit__panel">
                <div class="mao-tk-toolkit__section-head"><div><div class="mtk-r2-media__job-line"><h2><?php esc_html_e('连接测试', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $verified ? 'success' : 'neutral'; ?>"><?php echo $verified ? __('已通过', 'mao-tk-zibll-toolkit') : __('待验证', 'mao-tk-zibll-toolkit'); ?></span></div><p><?php esc_html_e('写入测试小图，经 Worker 读回后清理。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
                <form class="mtk-r2-media__body mtk-r2-media__test" action="<?php echo esc_url($action_url); ?>" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                    <input type="hidden" name="operation" value="test">
                    <?php wp_nonce_field(self::ACTION); ?>
                    <button type="submit" class="button" <?php disabled(!$configured); ?>><?php esc_html_e('测试媒体连接', 'mao-tk-zibll-toolkit'); ?></button>
                </form>
            </section>
            <section class="mao-tk-toolkit__panel" data-mtk-r2-job data-active="<?php echo $controls['polling'] ? '1' : '0'; ?>"
                     data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-action="<?php echo esc_attr(self::PROGRESS_ACTION); ?>"
                     data-nonce="<?php echo esc_attr(wp_create_nonce(self::PROGRESS_ACTION)); ?>">
                <div class="mao-tk-toolkit__section-head"><div><h2><?php esc_html_e('历史图片', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('补传跳过已同步且本地未改动的文件。完整核对可检查和修复缺失的副本。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
                <form class="mtk-r2-media__body" action="<?php echo esc_url($action_url); ?>" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                    <input type="hidden" name="job" value="<?php echo esc_attr($controls['job']); ?>">
                    <?php wp_nonce_field(self::ACTION); ?>
                    <div class="mtk-r2-media__job" role="status" data-mtk-r2-progress><?php require __DIR__ . '/progress-view.php'; ?></div>
                    <p class="mtk-r2-media__poll-message" role="status" data-mtk-r2-poll-message></p>
                    <div class="mtk-r2-media__job-actions">
                        <span class="mtk-r2-media__muted"><?php esc_html_e('由 WP-Cron 后台处理，可关闭此页。', 'mao-tk-zibll-toolkit'); ?></span>
                        <div class="mtk-r2-media__job-buttons">
                            <button type="submit" name="operation" value="repair" class="button" <?php disabled(!$controls['ready'] || $controls['active']); ?> <?php echo $controls['job'] !== '' ? 'hidden' : ''; ?>><?php esc_html_e('完整核对', 'mao-tk-zibll-toolkit'); ?></button>
                            <button type="submit" name="operation" value="sync" class="button button-primary" <?php disabled(!$controls['ready'] || $controls['active']); ?> <?php echo $controls['job'] !== '' ? 'hidden' : ''; ?>><?php esc_html_e('仅补传未同步', 'mao-tk-zibll-toolkit'); ?></button>
                            <button type="submit" name="operation" value="pause" class="button" <?php disabled(!$controls['ready'] || $controls['pausing']); ?> <?php echo $controls['job'] === '' || ($controls['paused'] && !$controls['pausing']) ? 'hidden' : ''; ?>><?php echo $controls['pausing'] ? __('正在暂停…', 'mao-tk-zibll-toolkit') : __('暂停同步', 'mao-tk-zibll-toolkit'); ?></button>
                            <button type="submit" name="operation" value="resume" class="button button-primary" <?php disabled(!$controls['ready']); ?> <?php echo !$controls['paused'] || $controls['pausing'] ? 'hidden' : ''; ?>><?php esc_html_e('继续同步', 'mao-tk-zibll-toolkit'); ?></button>
                        </div>
                    </div>
                    <noscript><p><a href="<?php echo esc_url(admin_url('admin.php?page=' . self::SLUG)); ?>"><?php esc_html_e('刷新进度', 'mao-tk-zibll-toolkit'); ?></a></p></noscript>
                </form>
            </section>
        </div>
    </div>
</div>
