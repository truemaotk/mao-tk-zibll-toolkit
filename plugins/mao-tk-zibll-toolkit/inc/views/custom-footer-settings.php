<?php
namespace MaoTK\ZibllToolkit\CustomFooter;
defined('ABSPATH') || exit;
?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit" data-mao-tk-motion-system="1">
 <h1 class="screen-reader-text">页脚设置</h1>
 <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2>页脚设置</h2><span class="mao-tk-toolkit__version"><?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">管理栏目、社交入口和底部信息，统一桌面与手机展示。</p></div></header>
 <section class="mao-tk-toolkit__panel mtkf-module-status"><div><h2>自定义页脚</h2><p>配置并保存后开启；关闭恢复子比原生页脚，保留已有设置。</p></div><?php $switch_module=$module;$switch_valid=$valid;$switch_return_page=AdminPage::SLUG;require MAO_TK_ZIBLL_TOOLKIT_DIR.'inc/views/module-switch.php'; ?></section>
 <?php if (!$state['valid']): ?><div class="notice notice-error inline"><p>已保存配置格式异常，前台保留原生页脚，当前预览为默认值。请先通过配置备份核对原设置。</p></div><?php endif; ?>
 <?php if (!Runtime::compatible()): ?><div class="notice notice-warning inline"><p>当前未检测到已核实的子比 9.2 页脚结构，前台使用原生页脚；设置与预览仍可使用。</p></div><?php endif; ?>
 <div id="mtk-footer-admin"><div class="mtkf-page">
 <div class="mtkf-toolbar"><span id="mtkf-save-status" role="status" aria-live="polite">正在读取页脚配置…</span><div><button type="button" class="mtkf-text-button" id="mtkf-load-preset" disabled>读取主题资料</button><button type="button" class="mtkf-button" id="mtkf-export-config" disabled>导出配置</button><button type="button" class="mtkf-button" id="mtkf-revert-config" disabled>撤销修改</button><button type="button" class="mtkf-button mtkf-primary" id="mtkf-save-config" disabled>保存设置</button></div></div>
 <div class="mtkf-workspace">
  <section class="mtkf-settings mtkf-card" aria-label="页脚内容设置"><div class="mtkf-card-heading"><h2>内容与显示</h2><span id="mtkf-changed-label">已保存</span></div><form id="mtkf-settings-form" novalidate><div id="mtkf-settings-sections" aria-busy="true">正在加载设置…</div></form><div class="mtkf-settings-note">关闭模块保留配置；前台跟随主题配色。</div></section>
  <section class="mtkf-preview mtkf-card" aria-label="页脚实时预览">
   <div class="mtkf-card-heading"><div><h2>实时预览</h2><p id="mtkf-preview-status" role="status">修改后自动更新</p></div><div class="mtkf-segment" aria-label="显示模式"><button type="button" data-theme="light" aria-pressed="true">浅色</button><button type="button" data-theme="dark" aria-pressed="false">深色</button></div></div>
   <div class="mtkf-preview-toolbar"><div class="mtkf-segment" aria-label="预览设备"><button type="button" data-device="desktop" aria-pressed="true">桌面</button><button type="button" data-device="mobile" aria-pressed="false">手机</button></div><div class="mtkf-preview-controls"><label for="mtkf-preview-width" class="mtkf-sr-only">桌面预览宽度</label><span class="mtkf-select-shell"><select id="mtkf-preview-width"><option value="1440">1440px</option><option value="1920">1920px</option></select></span><label for="mtkf-preview-zoom" class="mtkf-sr-only">预览缩放</label><span class="mtkf-select-shell"><select id="mtkf-preview-zoom"><option value="fit">适应画布</option><option value="actual">100% 实际大小</option></select></span><span id="mtkf-preview-scale">1440px</span></div></div>
   <div class="mtkf-viewport"><div id="mtkf-frame-stage"><iframe title="页脚预览" id="mtkf-footer-frame"></iframe></div></div>
   <div class="mtkf-preview-note">按真实设备宽度渲染；背景、文字和强调色跟随子比主题。手机端关注区位于版权上方。</div>
  </section>
 </div>
</div></div>
</div>
