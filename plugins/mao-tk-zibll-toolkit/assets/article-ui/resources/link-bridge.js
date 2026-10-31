/* Reuse the existing Toolkit link controller/CSS; this only marks its inner presentation. */
(() => {
  'use strict';
  if (window.MaoTKResourceLinks) { window.MaoTKResourceLinks.init(document); return; }
  function init(container) {
    const roots = new Set();
    if (container && container.closest) {
      const owner = container.closest('.mtk-legacy-resources');
      if (owner) roots.add(owner);
    }
    if (container && container.querySelectorAll) {
      container.querySelectorAll('.mtk-legacy-resources').forEach(root => roots.add(root));
    }
    roots.forEach(root => {
      const host = root.closest('.maotk-ui-kit');
      const enabled = host && host.dataset.maoTkUiLinkPreview === '1' && window.MaoTKArticleUI;
      root.querySelectorAll('.mtk-native-resource-link').forEach(label => {
        if (enabled) label.setAttribute('data-mao-tk-ui-link', '1');
        else label.removeAttribute('data-mao-tk-ui-link');
      });
    });
  }
  window.MaoTKResourceLinks = { init };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init(document), { once:true });
  else init(document);
  document.addEventListener('mao-tk-toolkit:article-ui:init', event => init(event.detail && event.detail.container || document));
  window.addEventListener('pageshow', () => init(document));
})();
