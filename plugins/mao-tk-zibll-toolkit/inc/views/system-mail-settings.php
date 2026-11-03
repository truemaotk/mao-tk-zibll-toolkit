<?php
namespace MaoTK\ZibllToolkit\SystemMail;
use MaoTK\ZibllToolkit\Admin;
defined('ABSPATH') || exit;
?>
<div class="wrap mao-tk-toolkit mtk-mail" id="mao-tk-toolkit" data-mao-tk-motion-system="1" data-system-mail data-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-action="<?php echo esc_attr(AdminPage::ACTION); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(AdminPage::ACTION)); ?>">
    <h1 class="screen-reader-text">系统邮件外观</h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2>系统邮件外观</h2><span class="mao-tk-toolkit__version"><?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">一套通用设置，按邮件类型继承。只调整外观，继续使用原系统发送。</p></div></header>
    <section class="mao-tk-toolkit__panel mtk-mail-status"><div><h2>外观模块</h2><p>关闭后恢复原邮件样式，独立配置仍然保留。预览与设置可以在关闭时使用。</p></div><?php $switch_module=$module;$switch_valid=$valid;$switch_return_page=AdminPage::SLUG;require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/module-switch.php'; ?></section>
    <?php if(!$state['valid']): ?><div class="notice notice-error inline"><p>已保存的邮件设置格式异常，运行时使用原生样式；当前预览为默认值，不会覆盖旧设置。</p></div><?php endif; ?>
    <?php if(!preg_match('/^9\.2(?:\.|$)/',$inventory['theme'])): ?><div class="notice notice-warning inline"><p>当前仅核对了子比 9.2 的通知入口。此主题版本运行时保留原生邮件，设置与示例预览仍可使用。</p></div><?php endif; ?>
    <noscript><div class="notice notice-warning inline"><p>设置与预览需要 JavaScript。当前不会自动发送任何测试邮件。</p></div></noscript>
    <div class="mtk-mail-notice" data-mail-notice role="status" aria-live="polite" hidden></div>
    <div class="mtk-mail-layout">
        <section class="mao-tk-toolkit__panel mtk-mail-controls"><form data-mail-form>
            <div class="mtk-mail-control-head"><label for="mtk-mail-scope">正在配置</label><select id="mtk-mail-scope" data-mail-scope><option value="common">通用邮件模板</option><?php $group='';foreach(Catalog::all() as $id=>$type):if($group!==$type['group']){if($group!=='')echo '</optgroup>';$group=$type['group'];echo '<optgroup label="'.esc_attr($group).'">';}?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($type['label']); ?></option><?php endforeach;if($group!=='')echo '</optgroup>'; ?></select>
                <div data-mail-mode-wrap hidden><label for="mtk-mail-mode">该类型使用方式</label><select id="mtk-mail-mode" data-mail-mode><option value="common">使用通用模板</option><option value="custom">单独配置此类型</option><option value="native">使用原生邮件样式</option></select></div>
                <p class="description" data-mail-source>通用设置供已接入类型继承。邮件专用 Logo 不修改网站导航 Logo。</p><p class="mtk-mail-scope-hint" data-mail-mode-hint></p>
            </div>
            <div class="mtk-mail-settings-groups"><?php foreach(Settings::groups() as $id=>$label):Admin::disclosure_start('mtk-mail-group-'.$id,$label,'',$id==='brand','system-mail-settings'); ?><div class="mtk-mail-fields" data-mail-fields="<?php echo esc_attr($id); ?>"></div><?php Admin::disclosure_end();endforeach; ?></div>
            <div class="mtk-mail-save"><span data-mail-dirty>设置已同步</span><button type="submit" class="button button-primary" data-mail-save <?php disabled(!$state['valid']); ?>>保存邮件设置</button></div>
        </form></section>
        <section class="mao-tk-toolkit__panel mtk-mail-preview"><div class="mtk-mail-preview-head"><div><h2>正式邮件模板</h2><p>预览显示变量位置；实际发送时填入真实数据，不读取客户验证码或订单。</p></div><div class="mtk-mail-device" role="group" aria-label="预览设备"><button type="button" aria-pressed="true" data-mail-device="desktop">桌面</button><button type="button" aria-pressed="false" data-mail-device="mobile">手机</button></div></div>
            <div class="mtk-mail-preview-toolbar"><label>预览类型<select data-mail-sample><?php foreach(Catalog::all() as $id=>$type): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($type['label']); ?></option><?php endforeach; ?></select></label><span data-mail-preview-state aria-live="polite">准备预览</span></div>
            <div class="mtk-mail-subject">主题：<span data-mail-subject></span><br>回复邮箱：<span data-mail-reply></span></div><div class="mtk-mail-preview-stage" data-mail-stage><iframe title="系统邮件桌面预览" data-mail-frame sandbox="allow-same-origin" referrerpolicy="no-referrer"></iframe></div>
            <div class="mtk-mail-test"><div><h3>发送测试邮件</h3><p>仅点击后发送，沿用现有 SMTP。测试邮件会明确标记，不生成真实验证码或订单；不会保存当前未保存的设置。</p></div><div class="mtk-mail-test-row"><input type="email" aria-label="测试收件邮箱" autocomplete="email" data-mail-recipient placeholder="输入测试收件邮箱"><button type="button" class="button" data-mail-test>发送测试邮件</button></div></div>
        </section>
    </div>
    <section class="mao-tk-toolkit__panel mtk-mail-inventory"><?php Admin::disclosure_start('mtk-mail-provider','邮件来源与接入范围','按已核实的入口分类，不新增通知',false,'system-mail-info'); ?><div class="mao-tk-disclosure__copy"><p>当前 Toolkit <?php echo esc_html($inventory['toolkit']); ?>；父主题版本 <?php echo esc_html($inventory['theme']); ?>。原生邮件包装器：<?php echo $inventory['native_wrapper']?'已加载':'当前运行环境未加载'; ?>。</p><p>Order Email Manager：<?php echo $inventory['order_email_manager']?'检测到运行／启用，保持其完整模板':'当前本地环境未启用；已检查现有源码为独立 WooCommerce 模板'; ?>。WooCommerce：<?php echo $inventory['woocommerce']?'已启用／加载':'当前环境未启用'; ?>。</p><p>验证码由原生成函数提供，不能识别验证码／有效期时回退原生。订单字段来自发送通知函数的真实参数；原始通知详情仍保留。未知来源、完整 HTML 和 multipart 邮件不套第二层模板。</p><ul><?php foreach(Catalog::unsupported() as $text): ?><li><?php echo esc_html($text); ?></li><?php endforeach; ?></ul><p>手机采用流式表格与内联 CSS，不依赖网站前端样式。不同邮箱对圆角等细节支持可能不同，实际收件效果需用测试邮件核对。</p></div><?php Admin::disclosure_end(); ?></section>
</div>
