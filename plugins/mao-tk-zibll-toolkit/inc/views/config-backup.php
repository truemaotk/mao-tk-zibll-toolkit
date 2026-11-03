<?php
namespace MaoTK\ZibllToolkit\ConfigBackup;
use MaoTK\ZibllToolkit\Admin;
defined('ABSPATH') || exit;
?>
<div class="wrap mao-tk-toolkit mtk-config" id="mao-tk-toolkit" data-config-backup data-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(AdminPage::ACTION)); ?>" data-action="<?php echo esc_attr(AdminPage::ACTION); ?>" data-available="<?php echo $available['available']?'1':'0'; ?>">
    <h1 class="screen-reader-text">配置导入&amp;导出</h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2>配置导入&amp;导出</h2><span class="mao-tk-toolkit__version"><?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">将 Toolkit 配置带到新安装，先预检，再恢复。</p></div></header>
    <?php if(!$available['available']): ?><div class="notice notice-error inline"><p><?php echo esc_html($available['message']); ?></p></div><?php endif; ?>
    <noscript><div class="notice notice-warning inline"><p>配置备份需要 JavaScript；当前不会执行导入。</p></div></noscript>
    <div class="mtk-config-notice" data-config-notice role="status" aria-live="polite" hidden></div>
    <div class="mtk-config-layout">
        <section class="mao-tk-toolkit__panel"><form data-config-export>
            <div class="mao-tk-toolkit__section-head"><div><h2>导出当前配置</h2><p>按分类保存为 JSON，本地自定义图片可一起带走。</p></div></div>
            <div class="mtk-config-fields"><fieldset><legend class="screen-reader-text">导出范围</legend><div class="mtk-config-checks"><?php foreach(Registry::catalog() as $id=>$entry): ?><label><input type="checkbox" name="section" value="<?php echo esc_attr($id); ?>" checked> <span><?php echo esc_html($entry['label']); ?></span></label><?php endforeach; ?></div></fieldset><label class="mtk-config-images"><input type="checkbox" name="images" checked> 包含本地自定义图片与图标</label><p class="description">保留原图片；外部图片地址保留，不主动抓取。备份可能含内部链接、账号规则和业务联系文案，请勿公开分享。</p></div>
            <div class="mtk-config-actions"><span>不含文章、商品、订单、用户密码或 SMTP 密钥</span><button type="submit" class="button button-primary" <?php disabled(!$available['available']); ?>>导出配置</button></div>
        </form></section>
        <section class="mao-tk-toolkit__panel"><form data-config-upload>
            <div class="mao-tk-toolkit__section-head"><div><h2>导入已有配置</h2><p>上传后只预检，不会立刻覆盖设置或写入图片。</p></div></div>
            <div class="mtk-config-fields"><label for="mtk-config-file">选择 Toolkit 配置备份</label><input id="mtk-config-file" type="file" name="package" accept=".json,application/json" required><p class="description">模块上限 32 MiB；当前服务器上限 <?php echo esc_html(size_format(wp_max_upload_size())); ?>。文章、链接与商品迁移包请在对应入口使用。</p></div>
            <div class="mtk-config-actions"><span>导入前自动保留快照，可在任务内恢复</span><button type="submit" class="button button-primary" <?php disabled(!$available['available']); ?>>上传并预检</button></div>
        </form><div class="mtk-config-boundary"><p>只恢复所选 Toolkit 配置，不覆盖子比全局设置。私密链接账号规则按用户名与邮箱摘要核对；新站不会直接沿用旧用户或图片 ID。</p></div></section>
    </div>
    <section class="mao-tk-toolkit__panel mtk-config-preview" data-config-preview hidden>
        <div class="mao-tk-toolkit__section-head"><div><h2 data-config-title>配置预检</h2><p data-config-meta></p></div><span data-config-state></span></div>
        <div class="mtk-config-fields"><div data-config-rows></div><div data-config-ack-wrap><label class="mtk-config-ack"><input type="checkbox" data-config-ack> 我确认覆盖勾选的插件配置</label><p class="description">模块开关默认不恢复；勾选“模块开关”可能开启维护模式或邮件外观，请核对后操作。</p></div><p data-config-result></p></div>
        <div class="mtk-config-actions"><div class="mtk-config-links"><a class="button" data-config-download>下载配置包</a><a class="button" data-config-before hidden>下载恢复前快照</a><button type="button" class="button" data-config-undo hidden>恢复此任务前配置</button></div><button type="button" class="button button-primary" data-config-apply>确认导入所选配置</button></div>
    </section>
    <section class="mao-tk-toolkit__panel mtk-config-history"><div class="mao-tk-toolkit__section-head"><div><h2>最近的配置备份</h2><p>仅当前账号可查看；最多保留 10 条记录，文件有效期 7 天。</p></div><button type="button" class="button" data-config-refresh>刷新记录</button></div><div class="mtk-config-fields" data-config-history aria-live="polite">正在读取记录…</div></section>
    <section class="mao-tk-toolkit__panel mtk-config-help"><?php Admin::disclosure_start('mtk-config-help','备份与恢复说明','配置与业务数据分开',false,'config-help'); ?><div class="mao-tk-disclosure__copy"><p>配置文件可在新站安装相同或更新版本 Toolkit 后预检导入。本地图片按文件内容去重；图片尺寸和像素不改变。导入新增的图片在撤销时保留，不删除现有媒体。</p><p>案例库会包含分类和案例，但不创建展示页面；新站仍需在页面中选择模板与案例分类。文章、商品、友情链接本身，以及账号、订单、权限权益和主题设置不在这个备份里。</p><p>恢复前快照用于当前任务核对与撤销，不作为跨站导入包。如果导入后又修改了同一配置，撤销会停止，避免覆盖新修改。</p><p>如请求意外中断，15 分钟后可恢复过期任务锁，再从最近记录核对或恢复该任务。</p><button type="button" class="button" data-config-recover>恢复过期任务锁</button></div><?php Admin::disclosure_end(); ?></section>
</div>
