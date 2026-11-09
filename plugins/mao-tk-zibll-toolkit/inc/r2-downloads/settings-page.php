<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

$groups = array(
    __('R2 上传凭据', 'mao-tk-zibll-toolkit') => array('MAOTK_R2_ACCOUNT_ID', 'MAOTK_R2_BUCKET', 'MAOTK_R2_ACCESS_KEY_ID', 'MAOTK_R2_SECRET_ACCESS_KEY'),
    __('Worker 下载签名', 'mao-tk-zibll-toolkit') => array('MAOTK_R2_DOWNLOAD_ORIGIN', 'MAOTK_R2_SIGNING_KEY'),
);
?>
<div class="wrap mao-tk-toolkit mtk-r2" id="mao-tk-toolkit">
    <h1 class="screen-reader-text"><?php esc_html_e('R2 文件上传与下载', 'mao-tk-zibll-toolkit'); ?></h1>
    <header class="mao-tk-toolkit__header">
        <div>
            <div class="mao-tk-toolkit__title-line"><h2><?php esc_html_e('R2 文件上传与下载', 'mao-tk-zibll-toolkit'); ?></h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div>
            <p class="mao-tk-toolkit__intro"><?php esc_html_e('管理私有 R2 文件，为文章生成可用于子比下载的引用。', 'mao-tk-zibll-toolkit'); ?></p>
        </div>
    </header>
    <?php $switch_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : ''; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <div id="mao-tk-r2-uploads"
         data-upload-ready="<?php echo $upload_ready ? '1' : '0'; ?>"
         data-download-ready="<?php echo $download_ready ? '1' : '0'; ?>"
         data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-action="<?php echo esc_attr(self::ACTION); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce(self::ACTION)); ?>"
         data-part-size="<?php echo esc_attr((string) Uploads::PART_SIZE); ?>"
         data-max-size="<?php echo esc_attr((string) Uploads::MAX_SIZE); ?>">
        <section class="mao-tk-toolkit__panel">
            <div class="mao-tk-toolkit__module mtk-r2__state">
                <div>
                    <div class="mao-tk-toolkit__module-heading">
                        <h2><?php esc_html_e('模块状态', 'mao-tk-zibll-toolkit'); ?></h2>
                        <span id="mtk-r2-runtime" class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $enabled ? 'success' : 'neutral'; ?>"><?php echo $enabled ? __('模块运行中', 'mao-tk-zibll-toolkit') : __('模块未运行', 'mao-tk-zibll-toolkit'); ?></span>
                    </div>
                    <p class="mtk-r2__hint"><?php esc_html_e('保存连接设置后，启用模块即可上传文件和生成下载引用。', 'mao-tk-zibll-toolkit'); ?></p>
                </div>
                <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
            </div>
        </section>
        <noscript><div class="notice notice-warning inline"><p><?php esc_html_e('连接设置仍可保存。上传文件和生成引用需要启用 JavaScript。', 'mao-tk-zibll-toolkit'); ?></p></div></noscript>
        <div class="mtk-r2__layout">
            <section class="mao-tk-toolkit__panel mtk-r2__settings" id="mtk-r2-settings" aria-labelledby="mtk-r2-settings-heading">
                <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-settings-heading"><?php esc_html_e('连接设置', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('在这里填写 Cloudflare 的两组凭据，保存后立即应用。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
                <?php if ($unreadable) : ?>
                    <div class="mtk-r2__notice" role="alert" data-error="true"><?php echo esc_html(sprintf(
                        /* translators: %s: list of credential field names. */
                        __('以下已保存的密钥无法解密：%s。通常是 WordPress 安全密钥（AUTH_KEY / AUTH_SALT）被更换，请重新填写这些密钥并保存。', 'mao-tk-zibll-toolkit'),
                        implode(' / ', $unreadable)
                    )); ?></div>
                <?php endif; ?>
                <?php if ($notice !== '' && strpos($save_status, 'resign') !== 0) : ?>
                    <div class="mtk-r2__notice" role="<?php echo $save_status === 'saved' ? 'status' : 'alert'; ?>" data-error="<?php echo $save_status === 'saved' ? 'false' : 'true'; ?>"><?php echo esc_html($notice); ?></div>
                <?php endif; ?>
                <?php if (is_array($credential_notice)) : ?>
                    <div class="mtk-r2__notice" role="<?php echo $credential_notice['ok'] ? 'status' : 'alert'; ?>" data-error="<?php echo $credential_notice['ok'] ? 'false' : 'true'; ?>"><?php echo esc_html($credential_notice['message']); ?></div>
                <?php endif; ?>
                <form id="mtk-r2-settings-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" autocomplete="off">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(self::SAVE_ACTION)); ?>">
                    <?php foreach ($groups as $title => $fields) :
                        $ready = count($fields) === 4 ? $upload_ready : $download_ready;
                        $configured = !in_array('', array_map(array(Settings::class, 'get'), $fields), true);
                        ?>
                        <fieldset class="mtk-r2__group">
                            <legend><?php echo esc_html($title); ?> <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $ready ? 'success' : 'warning'; ?>"><?php echo $ready ? __('已配置', 'mao-tk-zibll-toolkit') : ($configured ? __('服务器依赖缺失', 'mao-tk-zibll-toolkit') : __('待配置', 'mao-tk-zibll-toolkit')); ?></span></legend>
                            <div class="mtk-r2__fields">
                                <?php foreach ($fields as $field) :
                                    $rules = Settings::FIELDS[$field];
                                    $id = 'mtk-r2-' . strtolower(str_replace('_', '-', substr($field, 9)));
                                    $value = Settings::get($field);
                                    $locked = defined($field);
                                    $placeholder = $value !== '' ? '••••••••••••••••' : __('请输入密钥', 'mao-tk-zibll-toolkit');
                                    ?>
                                    <div class="mtk-r2__field">
                                        <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html(translate($rules['label'], 'mao-tk-zibll-toolkit')); ?>
                                            <?php if ($rules['secret'] && $value !== '') : ?><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--success"><?php esc_html_e('已保存', 'mao-tk-zibll-toolkit'); ?></span><?php endif; ?>
                                        </label>
                                        <input id="<?php echo esc_attr($id); ?>" name="mao_tk_r2[<?php echo esc_attr($field); ?>]"
                                               type="<?php echo $rules['secret'] ? 'password' : ($field === 'MAOTK_R2_DOWNLOAD_ORIGIN' ? 'url' : 'text'); ?>"
                                               maxlength="<?php echo esc_attr((string) $rules['limit']); ?>"
                                               aria-describedby="<?php echo esc_attr($id . '-hint'); ?>"
                                               autocomplete="<?php echo $rules['secret'] ? 'new-password' : 'off'; ?>"
                                               autocapitalize="none" spellcheck="false"
                                               <?php disabled($locked || ($rules['secret'] && !$can_encrypt)); ?>
                                               <?php if ($rules['secret']) : ?>
                                                   placeholder="<?php echo esc_attr($placeholder); ?>"
                                               <?php else : ?>
                                                   value="<?php echo esc_attr($value); ?>"
                                               <?php endif; ?>>
                                        <small id="<?php echo esc_attr($id . '-hint'); ?>"><?php echo esc_html($locked ? __('此项由服务器配置管理。', 'mao-tk-zibll-toolkit') : translate($rules['hint'], 'mao-tk-zibll-toolkit')); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <?php if (!$can_encrypt) : ?>
                        <p class="mtk-r2__notice" data-error="true"><?php esc_html_e('后台加密保存需要 PHP OpenSSL 和 WordPress 安全密钥。', 'mao-tk-zibll-toolkit'); ?></p>
                    <?php endif; ?>
                    <div class="mao-tk-toolkit__panel-foot mtk-r2__actions">
                        <span><?php esc_html_e('密钥加密保存在站点中。', 'mao-tk-zibll-toolkit'); ?></span>
                        <button class="button button-primary" type="submit"><?php esc_html_e('保存连接设置', 'mao-tk-zibll-toolkit'); ?></button>
                    </div>
                </form>
                <form class="mao-tk-toolkit__panel-foot mtk-r2__actions" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::MIGRATE_CREDENTIALS_ACTION); ?>">
                    <?php wp_nonce_field(self::MIGRATE_CREDENTIALS_ACTION); ?>
                    <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/credential-migration-view.php'; ?>
                </form>
            </section>
            <div class="mtk-r2__work">
                <section class="mao-tk-toolkit__panel" aria-labelledby="mtk-r2-upload-heading">
                    <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-upload-heading"><?php esc_html_e('上传文件', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('浏览器直接分片上传到 R2，单个文件最大 30 GiB。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
                    <div class="mtk-r2__body">
                        <label class="mtk-r2__field mtk-r2__file" for="mtk-r2-file">
                            <span id="mtk-r2-file-label"><?php esc_html_e('选择本机文件', 'mao-tk-zibll-toolkit'); ?></span>
                            <input type="file" id="mtk-r2-file" aria-labelledby="mtk-r2-file-label" aria-describedby="mtk-r2-upload-hint" <?php disabled(!$enabled || !$upload_ready); ?>>
                        </label>
                        <progress id="mtk-r2-progress" aria-label="<?php esc_attr_e('上传进度', 'mao-tk-zibll-toolkit'); ?>" max="100" value="0" hidden></progress>
                        <p class="mtk-r2__hint" id="mtk-r2-upload-hint"><?php echo !$upload_ready ? __('保存上传凭据后可以选择文件。', 'mao-tk-zibll-toolkit') : (!$enabled ? __('启用模块后可以选择文件。', 'mao-tk-zibll-toolkit') : __('上传时请保持本页打开，失败后可继续当前任务。', 'mao-tk-zibll-toolkit')); ?></p>
                        <p class="mtk-r2__feedback" id="mtk-r2-status" role="status"></p>
                    </div>
                    <div class="mao-tk-toolkit__panel-foot mtk-r2__actions">
                        <button class="button" type="button" id="mtk-r2-cancel" disabled><?php esc_html_e('取消任务', 'mao-tk-zibll-toolkit'); ?></button>
                        <button class="button button-primary" type="button" id="mtk-r2-start" disabled><?php esc_html_e('开始上传', 'mao-tk-zibll-toolkit'); ?></button>
                    </div>
                </section>
                <section class="mao-tk-toolkit__panel" aria-labelledby="mtk-r2-reference-heading">
                    <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-reference-heading"><?php esc_html_e('生成文章下载引用', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('先选文件并填写文章 ID，生成后即可复制引用。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
                    <div class="mtk-r2__body">
                        <div class="mtk-r2__fields mtk-r2__reference-fields">
                            <div class="mtk-r2__field"><label for="mtk-r2-post"><?php esc_html_e('文章 ID', 'mao-tk-zibll-toolkit'); ?></label>
                                <input type="number" id="mtk-r2-post" min="1" step="1" placeholder="<?php esc_attr_e('例如 31', 'mao-tk-zibll-toolkit'); ?>" <?php disabled(!$enabled || !$download_ready || !$upload_ready); ?>></div>
                            <div class="mtk-r2__field"><label for="mtk-r2-key"><?php esc_html_e('R2 对象键', 'mao-tk-zibll-toolkit'); ?></label>
                                <input type="text" id="mtk-r2-key" maxlength="1024" placeholder="<?php esc_attr_e('例如 releases/plugin.zip', 'mao-tk-zibll-toolkit'); ?>" <?php disabled(!$enabled || !$download_ready || !$upload_ready); ?>></div>
                        </div>
                        <div class="mtk-r2__field">
                            <label for="mtk-r2-reference-mode"><?php esc_html_e('下载方式', 'mao-tk-zibll-toolkit'); ?></label>
                            <select id="mtk-r2-reference-mode" aria-describedby="mtk-r2-reference-mode-hint" <?php disabled(!$enabled || !$download_ready || !$upload_ready); ?>>
                                <option value="native"><?php esc_html_e('子比原生权限', 'mao-tk-zibll-toolkit'); ?></option>
                                <option value="public"><?php esc_html_e('公开下载 · 正文卡片', 'mao-tk-zibll-toolkit'); ?></option>
                            </select>
                            <small id="mtk-r2-reference-mode-hint"><?php esc_html_e('沿用文章设置的登录、购买和下载次数规则。', 'mao-tk-zibll-toolkit'); ?></small>
                        </div>
                        <div class="mtk-r2__field mtk-r2__result" id="mtk-r2-ref-result" hidden>
                            <label for="mtk-r2-reference-url" id="mtk-r2-reference-label"><?php esc_html_e('子比资源下载地址（R2 引用）', 'mao-tk-zibll-toolkit'); ?></label>
                            <div><input type="url" id="mtk-r2-reference-url" readonly>
                                <button class="button button-primary" type="button" id="mtk-r2-copy"><?php esc_html_e('复制下载引用', 'mao-tk-zibll-toolkit'); ?></button></div>
                        </div>
                        <p class="mtk-r2__feedback" id="mtk-r2-ref-status" role="status"></p>
                    </div>
                    <div class="mao-tk-toolkit__panel-foot mtk-r2__actions">
                        <span id="mtk-r2-ref-hint"><?php echo !$upload_ready || !$download_ready ? __('保存上传与签名设置后可生成引用。', 'mao-tk-zibll-toolkit') : (!$enabled ? __('启用模块后可生成引用。', 'mao-tk-zibll-toolkit') : __('文章 ID 可从编辑地址中的 post= 参数查看。', 'mao-tk-zibll-toolkit')); ?></span>
                        <button class="button button-primary" id="mtk-r2-reference" type="button" disabled><?php esc_html_e('生成引用', 'mao-tk-zibll-toolkit'); ?></button>
                    </div>
                </section>
            </div>
        </div>
        <section class="mao-tk-toolkit__panel mtk-r2__library" id="mtk-r2-library" aria-labelledby="mtk-r2-library-heading">
            <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-library-heading" tabindex="-1"><?php esc_html_e('R2 已上传文件', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('查看通过 Toolkit 上传的文件。删除会同步到 R2，引用该文件的文章将无法下载。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
            <div class="mtk-r2__body">
                <p class="mtk-r2__hint" id="mtk-r2-library-hint"><?php esc_html_e('启用模块并保存上传凭据后即可读取文件。', 'mao-tk-zibll-toolkit'); ?></p>
                <p class="mtk-r2__feedback" id="mtk-r2-files-status" role="status"></p>
                <fieldset class="mtk-r2__file-controls" id="mtk-r2-file-controls" disabled>
                    <legend class="screen-reader-text"><?php esc_html_e('已上传文件操作', 'mao-tk-zibll-toolkit'); ?></legend>
                    <ul class="mtk-r2__file-list" id="mtk-r2-files"></ul>
                    <div class="mtk-r2__file-nav">
                        <button class="button" type="button" id="mtk-r2-files-reload"><?php esc_html_e('刷新文件', 'mao-tk-zibll-toolkit'); ?></button>
                        <div>
                            <button class="button" type="button" id="mtk-r2-files-prev" disabled><?php esc_html_e('上一页', 'mao-tk-zibll-toolkit'); ?></button>
                            <button class="button" type="button" id="mtk-r2-files-next" disabled><?php esc_html_e('下一页', 'mao-tk-zibll-toolkit'); ?></button>
                        </div>
                    </div>
                </fieldset>
            </div>
        </section>
            <section class="mao-tk-toolkit__panel" id="mtk-r2-references" aria-labelledby="mtk-r2-references-heading">
            <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-references-heading"><?php esc_html_e('下载链接签名', 'mao-tk-zibll-toolkit'); ?></h2><p><?php esc_html_e('新生成的下载引用使用独立签名密钥，更换 WordPress 安全密钥不会让它们失效。1.24.0-rc.11 之前生成的引用仍可使用，但更换安全密钥前应先在这里重新签名。', 'mao-tk-zibll-toolkit'); ?></p></div></div>
            <div class="mtk-r2__body">
                <?php if ($notice !== '' && strpos($save_status, 'resign') === 0) : ?>
                    <div class="mtk-r2__notice" role="<?php echo $save_status === 'resign-failed' ? 'alert' : 'status'; ?>" data-error="<?php echo $save_status === 'resign-failed' ? 'true' : 'false'; ?>"><?php echo esc_html($notice); ?></div>
                <?php endif; ?>
                <?php
                $scan_mode = $reference_status['mode'] ?? '';
                $scan_done = !empty($reference_status['done']);
                $scan_total = (int) ($reference_status['total'] ?? 0);
                ?>
                <p class="mtk-r2__hint"><?php
                    if ($scan_mode === '') {
                        esc_html_e('尚未扫描下载引用。点击下方按钮后分批处理。', 'mao-tk-zibll-toolkit');
                    } else {
                        echo esc_html(sprintf(
                            /* translators: 1: scan state, 2: total references processed. */
                            __('%1$s · 本轮已转换 %2$d 个下载引用', 'mao-tk-zibll-toolkit'),
                            $scan_done ? __('已扫到末尾', 'mao-tk-zibll-toolkit') : __('扫描未完成', 'mao-tk-zibll-toolkit'),
                            $scan_total
                        ));
                    }
                ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::RESIGN_ACTION); ?>">
                    <?php wp_nonce_field(self::RESIGN_ACTION); ?>
                    <?php if ($scan_mode !== 'legacy' || $scan_done) : ?>
                        <p><button class="button button-primary" type="submit" name="r2_command" value="<?php echo $scan_mode === 'current' && $scan_done ? 'current-restart' : 'current'; ?>"><?php
                            echo esc_html($scan_mode === 'current' && !$scan_done ? __('继续重新签名', 'mao-tk-zibll-toolkit') : __('扫描并更新旧签名', 'mao-tk-zibll-toolkit'));
                        ?></button></p>
                    <?php endif; ?>
                    <details<?php echo $scan_mode === 'legacy' && !$scan_done ? ' open' : ''; ?>>
                        <summary><?php esc_html_e('准备回退旧版', 'mao-tk-zibll-toolkit'); ?></summary>
                        <p class="mtk-r2__hint"><?php esc_html_e('先备份数据库。使用旧版前，分批把下载引用改回当前 WordPress 安全密钥签名；处理完成后再覆盖安装旧版，并清理页面缓存。回退后不要同时更换 WordPress 安全密钥。', 'mao-tk-zibll-toolkit'); ?></p>
                        <p><button class="button" type="submit" name="r2_command" value="<?php echo $scan_mode === 'legacy' && $scan_done ? 'legacy-restart' : 'legacy'; ?>"><?php
                            echo esc_html($scan_mode === 'legacy' && !$scan_done ? __('继续准备回退', 'mao-tk-zibll-toolkit') : __('改回旧版可读签名', 'mao-tk-zibll-toolkit'));
                        ?></button></p>
                    </details>
                    <p class="mtk-r2__hint"><?php esc_html_e('写入前备份原引用，正文其他内容、下载设置与 R2 文件保持原样。迁移期间请暂缓编辑下载链接，结束后可重新扫描。', 'mao-tk-zibll-toolkit'); ?></p>
                </form>
            </div>
        </section>
</div>
</div>
