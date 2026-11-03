<?php
namespace MaoTK\ZibllToolkit\Migration;
use MaoTK\ZibllToolkit\Admin;
defined('ABSPATH') || exit;
$nonce = wp_create_nonce(AdminPage::ACTION);
?>
<div class="wrap mao-tk-toolkit mtk-migration" id="mao-tk-toolkit" data-mao-tk-motion-system="1" data-mao-tk-migration data-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-action="<?php echo esc_attr(AdminPage::ACTION); ?>" data-available="<?php echo $active && $storage_error === '' ? '1' : '0'; ?>">
    <h1 class="screen-reader-text">链接导入&amp;导出</h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2>链接导入&amp;导出</h2><span class="mao-tk-toolkit__version">验收版 <?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">从旧站带走完整链接，在新站预检后再写入。</p></div></header>
    <?php $switch_notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? $_GET['mao_tk_notice'] : ''; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <section class="mao-tk-toolkit__panel mtk-migration-enable"><div><h2>链接导入&amp;导出</h2><p class="description" data-migration-availability><?php echo $active ? '运行中 · 仅管理员后台使用，不影响前台链接显示。' : '未运行 · 请开启模块，并确认当前为 Zibll 或其子主题。'; ?></p></div><?php $switch_module = $module; $switch_valid = $this->manager->storage_is_valid(); $switch_return_page = AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?></section>
    <?php if ($storage_error !== '') : ?><div class="notice notice-error inline"><p><?php echo esc_html($storage_error); ?></p></div><?php endif; ?>
    <noscript><div class="notice notice-warning inline"><p>批量迁移需要 JavaScript。请启用后刷新；当前不会自动执行任何导入。</p></div></noscript>
    <div class="mtk-migration-notice" role="status" aria-live="polite" hidden></div>
    <div class="mtk-migration-tabs" role="tablist" aria-label="迁移方式">
        <button type="button" role="tab" id="mtk-export-tab" aria-controls="mtk-export-panel" aria-selected="true" data-tab="export">导出链接</button>
        <button type="button" role="tab" id="mtk-import-tab" aria-controls="mtk-import-panel" aria-selected="false" tabindex="-1" data-tab="import">导入链接</button>
        <button type="button" role="tab" id="mtk-history-tab" aria-controls="mtk-history-panel" aria-selected="false" tabindex="-1" data-tab="history">任务记录</button>
    </div>
    <div class="mtk-migration-layout">
        <main>
            <section role="tabpanel" id="mtk-export-panel" aria-labelledby="mtk-export-tab" class="mao-tk-toolkit__panel">
                <div class="mao-tk-toolkit__section-head"><div><h2>选择导出范围</h2><p>当前共 <?php echo $link_total; ?> 条链接。私密标记、推荐参数与备注一起保留。<?php if ($link_total > Package::MAX_LINKS) { echo '指定列表仅显示前 1,000 条；其余请按分类导出。'; } ?></p></div></div>
                <form id="mtk-export-form" class="mtk-migration-form">
                    <div class="mtk-migration-grid"><label>链接分类<select name="category"><option value="0">全部分类</option><?php foreach ($categories as $c) : ?><option value="<?php echo (int) $c->term_id; ?>"><?php echo esc_html($c->name); ?></option><?php endforeach; ?></select></label><label>可见范围<select name="visibility"><option value="all">公开与私密</option><option value="public">仅公开链接</option><option value="private">仅私密链接</option></select></label></div>
                    <label class="mtk-migration-check"><input type="checkbox" name="images" value="1" checked> 包含本地 LOGO 图片</label>
                    <label class="mtk-migration-check"><input type="checkbox" name="remote" value="1"> 尝试下载外部 LOGO</label><p class="description">外部下载会请求对应图片网址；失败项目会单独列出，不会伪装成已完成。</p>
                    <?php Admin::disclosure_start('mtk-link-selection', '指定链接', '不选择时按上面的分类与可见范围导出', false, 'migration-export'); ?>
                    <div class="mtk-migration-selection"><input type="search" placeholder="筛选链接名称或网址" aria-label="筛选指定链接" autocomplete="off" data-link-search><div class="mtk-migration-scroll">
                    <?php foreach ($links as $l) : ?><label class="mtk-migration-select-row"><input type="checkbox" name="ids[]" value="<?php echo (int) $l['link_id']; ?>"><span><strong><?php echo esc_html($l['link_name']); ?></strong><small><?php echo esc_html($l['link_url']); ?></small></span><span class="mtk-migration-tag"><?php echo $l['link_visible'] === 'Y' ? '公开' : '私密'; ?></span></label><?php endforeach; ?>
                    </div><p class="description" data-selected-count>已选择 0 条</p></div><?php Admin::disclosure_end(); ?>
                    <div class="mtk-migration-footer"><span>单次最多 1,000 条</span><button type="submit" class="button button-primary">创建导出任务</button></div>
                </form>
            </section>
            <section role="tabpanel" id="mtk-import-panel" aria-labelledby="mtk-import-tab" class="mao-tk-toolkit__panel" hidden>
                <div class="mao-tk-toolkit__section-head"><div><h2>上传迁移包</h2><p>支持 Toolkit 新格式与旧版 links-export.zip。上传后先预检，不会直接写入。</p></div></div>
                <form id="mtk-import-form" class="mtk-migration-form" enctype="multipart/form-data">
                    <label class="mtk-migration-file">选择 ZIP 文件<input type="file" name="package" accept=".zip" required><span class="description">模块上限 32 MiB；当前服务器上传上限 <?php echo esc_html(size_format(wp_max_upload_size())); ?>。</span></label>
                    <fieldset><legend>发现已有链接时</legend><label class="mtk-migration-check"><input type="radio" name="policy" value="skip" checked> 跳过已有链接（推荐）</label><label class="mtk-migration-check"><input type="radio" name="policy" value="update"> 更新已有链接，并备份原记录</label></fieldset>
                    <p class="description">按完整网址精确判重，保留邀请码和推荐参数。包内重复或目标站多条同网址记录会提示冲突。</p>
                    <div class="mtk-migration-footer"><span>预检不会新增链接或媒体</span><button type="submit" class="button button-primary">上传并预检</button></div>
                </form>
            </section>
            <section role="tabpanel" id="mtk-history-panel" aria-labelledby="mtk-history-tab" class="mao-tk-toolkit__panel" hidden><div class="mao-tk-toolkit__section-head"><div><h2>最近任务</h2><p>仅显示当前账号最近 20 个任务；迁移包有效期 24 小时。</p></div><button type="button" class="button" data-history-refresh>刷新</button></div><div data-history-list class="mtk-migration-history"></div></section>
            <section id="mtk-task-panel" class="mao-tk-toolkit__panel" hidden aria-labelledby="mtk-task-title">
                <div class="mao-tk-toolkit__section-head"><div><h2 id="mtk-task-title">任务预检</h2><p data-task-description></p></div><span class="mtk-migration-tag" data-task-state></span></div>
                <div class="mtk-migration-task"><div class="mtk-migration-progress"><progress max="100" value="0" aria-label="迁移进度"></progress><span data-task-count></span></div><p data-task-totals></p><div data-task-warnings></div></div>
                <div class="mtk-migration-table-wrap"><table class="mtk-migration-table"><thead><tr><th>链接</th><th>状态</th><th>LOGO</th><th>处理结果</th></tr></thead><tbody data-task-rows></tbody></table></div>
                <div class="mtk-migration-pagination"><span data-page-label></span><div><button class="button" type="button" data-page-prev>上一页</button><button class="button" type="button" data-page-next>下一页</button></div></div>
                <div class="mtk-migration-footer"><div class="mtk-migration-actions"><button type="button" class="button button-primary" data-task-start>确认并开始</button><button type="button" class="button" data-task-pause hidden>暂停</button><button type="button" class="button" data-task-retry hidden>重试失败项</button><button type="button" class="button" data-task-refresh>刷新状态</button></div><div class="mtk-migration-actions"><a class="button" data-task-report hidden>下载报告／覆盖前备份</a><a class="button button-primary" data-task-download hidden>下载 ZIP</a></div></div>
                <p class="mtk-migration-checksum" data-task-sha hidden></p>
            </section>
        </main>
        <aside class="mtk-migration-aside"><h2>迁移规则</h2><p>先预检，再确认。暂停或关掉页面后，可以从任务记录继续。</p><p>公开链接与私密链接按原状态迁移；本站角色和账号可见性规则不会被覆盖。</p><p>未打包的外部 LOGO 保留原网址。图片采用原文件，不重新压缩。</p><?php Admin::disclosure_start('mtk-migration-help', '文件与恢复说明', '', false, 'migration-help'); ?><div class="mao-tk-disclosure__copy"><p>包内最多 1,000 条链接、单张 LOGO 2 MiB、解压总量 64 MiB。支持 PNG、JPEG、WebP、GIF 和可识别的 ICO。</p><p>覆盖前备份随任务报告下载，保留原链接字段和分类 ID。第一版不提供一键撤销，恢复前需要先核对之后的修改。</p><p>任务仅对创建账号可见。私密迁移包放在网站目录之外，下载需要登录与权限验证。</p></div><?php Admin::disclosure_end(); ?></aside>
    </div>
</div>
