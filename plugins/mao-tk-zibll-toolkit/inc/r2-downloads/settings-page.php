<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

$groups = array(
    'R2 上传凭据' => array('MAOTK_R2_ACCOUNT_ID', 'MAOTK_R2_BUCKET', 'MAOTK_R2_ACCESS_KEY_ID', 'MAOTK_R2_SECRET_ACCESS_KEY'),
    'Worker 下载签名' => array('MAOTK_R2_DOWNLOAD_ORIGIN', 'MAOTK_R2_SIGNING_KEY'),
);
?>
<div class="wrap mao-tk-toolkit mtk-r2" id="mao-tk-toolkit">
    <h1 class="screen-reader-text">R2 文件上传与下载</h1>
    <header class="mao-tk-toolkit__header">
        <div>
            <div class="mao-tk-toolkit__title-line"><h2>R2 文件上传与下载</h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div>
            <p class="mao-tk-toolkit__intro">管理私有 R2 文件，为文章生成可用于子比下载的引用。</p>
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
                        <h2>模块状态</h2>
                        <span id="mtk-r2-runtime" class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $enabled ? 'success' : 'neutral'; ?>"><?php echo $enabled ? '模块运行中' : '模块未运行'; ?></span>
                    </div>
                    <p class="mtk-r2__hint">保存连接设置后，启用模块即可上传文件和生成下载引用。</p>
                </div>
                <?php require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?>
            </div>
        </section>
        <noscript><div class="notice notice-warning inline"><p>连接设置仍可保存。上传文件和生成引用需要启用 JavaScript。</p></div></noscript>
        <div class="mtk-r2__layout">
            <section class="mao-tk-toolkit__panel mtk-r2__settings" id="mtk-r2-settings" aria-labelledby="mtk-r2-settings-heading">
                <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-settings-heading">连接设置</h2><p>在这里填写 Cloudflare 的两组凭据，保存后立即应用。</p></div></div>
                <?php if ($notice !== '') : ?>
                    <div class="mtk-r2__notice" role="<?php echo $save_status === 'saved' ? 'status' : 'alert'; ?>" data-error="<?php echo $save_status === 'saved' ? 'false' : 'true'; ?>"><?php echo esc_html($notice); ?></div>
                <?php endif; ?>
                <form id="mtk-r2-settings-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" autocomplete="off">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(self::SAVE_ACTION)); ?>">
                    <?php foreach ($groups as $title => $fields) :
                        $ready = count($fields) === 4 ? $upload_ready : $download_ready;
                        $configured = !in_array('', array_map(array(Settings::class, 'get'), $fields), true);
                        ?>
                        <fieldset class="mtk-r2__group">
                            <legend><?php echo esc_html($title); ?> <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo $ready ? 'success' : 'warning'; ?>"><?php echo $ready ? '已配置' : ($configured ? '服务器依赖缺失' : '待配置'); ?></span></legend>
                            <div class="mtk-r2__fields">
                                <?php foreach ($fields as $field) :
                                    $rules = Settings::FIELDS[$field];
                                    $id = 'mtk-r2-' . strtolower(str_replace('_', '-', substr($field, 9)));
                                    $value = Settings::get($field);
                                    $locked = defined($field);
                                    $placeholder = $value !== '' ? '••••••••••••••••' : '请输入密钥';
                                    ?>
                                    <div class="mtk-r2__field">
                                        <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($rules['label']); ?>
                                            <?php if ($rules['secret'] && $value !== '') : ?><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--success">已保存</span><?php endif; ?>
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
                                        <small id="<?php echo esc_attr($id . '-hint'); ?>"><?php echo esc_html($locked ? '此项由服务器配置管理。' : $rules['hint']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <?php if (!$can_encrypt) : ?>
                        <p class="mtk-r2__notice" data-error="true">后台加密保存需要 PHP OpenSSL 和 WordPress 安全密钥。</p>
                    <?php endif; ?>
                    <div class="mao-tk-toolkit__panel-foot mtk-r2__actions">
                        <span>密钥加密保存在站点中。</span>
                        <button class="button button-primary" type="submit">保存连接设置</button>
                    </div>
                </form>
            </section>
            <div class="mtk-r2__work">
                <section class="mao-tk-toolkit__panel" aria-labelledby="mtk-r2-upload-heading">
                    <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-upload-heading">上传文件</h2><p>浏览器直接分片上传到 R2，单个文件最大 30 GiB。</p></div></div>
                    <div class="mtk-r2__body">
                        <label class="mtk-r2__field mtk-r2__file" for="mtk-r2-file">
                            <span id="mtk-r2-file-label">选择本机文件</span>
                            <input type="file" id="mtk-r2-file" aria-labelledby="mtk-r2-file-label" aria-describedby="mtk-r2-upload-hint" <?php disabled(!$enabled || !$upload_ready); ?>>
                        </label>
                        <progress id="mtk-r2-progress" aria-label="上传进度" max="100" value="0" hidden></progress>
                        <p class="mtk-r2__hint" id="mtk-r2-upload-hint"><?php echo !$upload_ready ? '保存上传凭据后可以选择文件。' : (!$enabled ? '启用模块后可以选择文件。' : '上传时请保持本页打开，失败后可继续当前任务。'); ?></p>
                        <p class="mtk-r2__feedback" id="mtk-r2-status" role="status"></p>
                    </div>
                    <div class="mao-tk-toolkit__panel-foot mtk-r2__actions">
                        <button class="button" type="button" id="mtk-r2-cancel" disabled>取消任务</button>
                        <button class="button button-primary" type="button" id="mtk-r2-start" disabled>开始上传</button>
                    </div>
                </section>
                <section class="mao-tk-toolkit__panel" aria-labelledby="mtk-r2-reference-heading">
                    <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-reference-heading">生成文章下载引用</h2><p>先选文件并填写文章 ID，生成后即可复制引用。</p></div></div>
                    <div class="mtk-r2__body">
                        <div class="mtk-r2__fields mtk-r2__reference-fields">
                            <div class="mtk-r2__field"><label for="mtk-r2-post">文章 ID</label>
                                <input type="number" id="mtk-r2-post" min="1" step="1" placeholder="例如 31" <?php disabled(!$enabled || !$download_ready || !$upload_ready); ?>></div>
                            <div class="mtk-r2__field"><label for="mtk-r2-key">R2 对象键</label>
                                <input type="text" id="mtk-r2-key" maxlength="1024" placeholder="例如 releases/plugin.zip" <?php disabled(!$enabled || !$download_ready || !$upload_ready); ?>></div>
                        </div>
                        <div class="mtk-r2__field mtk-r2__result" id="mtk-r2-ref-result" hidden>
                            <label for="mtk-r2-reference-url">子比下载链接</label>
                            <div><input type="url" id="mtk-r2-reference-url" readonly>
                                <button class="button button-primary" type="button" id="mtk-r2-copy">复制下载引用</button></div>
                        </div>
                        <p class="mtk-r2__feedback" id="mtk-r2-ref-status" role="status"></p>
                    </div>
                    <div class="mao-tk-toolkit__panel-foot mtk-r2__actions">
                        <span id="mtk-r2-ref-hint"><?php echo !$upload_ready || !$download_ready ? '保存上传与签名设置后可生成引用。' : (!$enabled ? '启用模块后可生成引用。' : '文章 ID 可从编辑地址中的 post= 参数查看。'); ?></span>
                        <button class="button button-primary" id="mtk-r2-reference" type="button" disabled>生成引用</button>
                    </div>
                </section>
            </div>
        </div>
        <section class="mao-tk-toolkit__panel mtk-r2__library" id="mtk-r2-library" aria-labelledby="mtk-r2-library-heading">
            <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-r2-library-heading" tabindex="-1">R2 已上传文件</h2><p>查看通过 Toolkit 上传的文件。删除会同步到 R2，引用该文件的文章将无法下载。</p></div></div>
            <div class="mtk-r2__body">
                <p class="mtk-r2__hint" id="mtk-r2-library-hint">启用模块并保存上传凭据后即可读取文件。</p>
                <p class="mtk-r2__feedback" id="mtk-r2-files-status" role="status"></p>
                <fieldset class="mtk-r2__file-controls" id="mtk-r2-file-controls" disabled>
                    <legend class="screen-reader-text">已上传文件操作</legend>
                    <ul class="mtk-r2__file-list" id="mtk-r2-files"></ul>
                    <div class="mtk-r2__file-nav">
                        <button class="button" type="button" id="mtk-r2-files-reload">刷新文件</button>
                        <div>
                            <button class="button" type="button" id="mtk-r2-files-prev" disabled>上一页</button>
                            <button class="button" type="button" id="mtk-r2-files-next" disabled>下一页</button>
                        </div>
                    </div>
                </fieldset>
            </div>
        </section>
    </div>
</div>
