/* Article-only FAQ/copy enhancement. Native headings, TOC and link scrolling remain theme-owned. */
(function () {
  'use strict';
  if (window.MaoTKArticleReading) return;
  var selector = '.maotk-ui-kit.mtk-article-reading[data-mao-tk-ui-reading="1"]';
  var copyTimers = new Map();
  function setOpen(item, open, instant) {
    var trigger = item.querySelector('.mtk-faq-trigger');
    var panel = item.querySelector('.mtk-faq-panel');
    if (!trigger || !panel) return;
    if (instant) item.classList.add('mtk-faq-instant');
    trigger.setAttribute('aria-expanded', String(open));
    item.dataset.mtkOpen = String(open);
    panel.hidden = false;
    panel.inert = !open;
    panel.setAttribute('aria-hidden', String(!open));
    if (instant) { void panel.offsetHeight; item.classList.remove('mtk-faq-instant'); }
  }
  function openItem(item, instant) {
    var group = item.closest('.mtk-faqs');
    if (!group) return;
    group.querySelectorAll('[data-mtk-faq]').forEach(function (other) {
      if (other !== item) setOpen(other, false, instant);
    });
    setOpen(item, true, instant);
  }
  function openHash(hash) {
    var id;
    try { id = decodeURIComponent(hash.slice(1)); } catch (error) { return; }
    var target = id && document.getElementById(id);
    var item = target && target.closest('[data-mtk-faq]');
    if (item && item.closest(selector)) openItem(item, true);
  }
  function init(container) {
    var roots = [];
    if (container && container.matches && container.matches(selector)) roots.push(container);
    if (container && container.querySelectorAll) roots = roots.concat(Array.from(container.querySelectorAll(selector)));
    roots.forEach(function (root) {
      if (root.dataset.mtkReadingReady === '1') return;
      root.dataset.mtkReadingReady = '1';
      root.querySelectorAll('[data-mtk-faq]').forEach(function (item) { setOpen(item, false, true); });
    });
    openHash(location.hash);
  }
  function destroy(container) {
    if (!container) return;
    copyTimers.forEach(function (timer, button) {
      if (container === button || container.contains(button)) { clearTimeout(timer); copyTimers.delete(button); }
    });
    var roots = container.matches && container.matches(selector) ? [container] : Array.from(container.querySelectorAll(selector));
    roots.forEach(function (root) {
      delete root.dataset.mtkReadingReady;
      root.querySelectorAll('[data-mtk-faq]').forEach(function (item) { setOpen(item, true, true); });
    });
  }
  document.addEventListener('click', async function (event) {
    if (!(event.target instanceof Element)) return;
    var root = event.target.closest(selector);
    if (!root) return;
    var trigger = event.target.closest('.mtk-faq-trigger');
    if (trigger) {
      var item = trigger.closest('[data-mtk-faq]');
      if (!item) return;
      if (trigger.getAttribute('aria-expanded') === 'true') setOpen(item, false, false);
      else openItem(item, false);
      return;
    }
    var button = event.target.closest('[data-mtk-copy]');
    if (!button) return;
    var token = button.getAttribute('data-mtk-copy');
    var status = root.querySelector('[data-mtk-copy-status]');
    var label = button.querySelector('[data-mtk-copy-label]');
    var original = label ? label.textContent : '';
    if (button.dataset.mtkCopyBusy === '1') return;
    button.dataset.mtkCopyBusy = '1';
    try {
      if (!navigator.clipboard || !navigator.clipboard.writeText) throw new Error('Unavailable');
      await navigator.clipboard.writeText(token);
      if (!button.isConnected || !root.matches(selector)) return;
      clearTimeout(copyTimers.get(button));
      button.dataset.copied = 'true';
      if (label) label.textContent = '已复制';
      if (status) status.textContent = '已复制 ' + token + '，可以粘贴到邮件文字字段。';
      copyTimers.set(button, setTimeout(function () {
        delete button.dataset.copied;
        if (label) label.textContent = original === '已复制' ? '复制' : original;
        copyTimers.delete(button);
      }, 1800));
    } catch (error) {
      if (status) status.textContent = '未能自动复制。请选中 ' + token + ' 后手动复制。';
    } finally { delete button.dataset.mtkCopyBusy; }
  });
  // Expand before native TOC scrolling, without preventing or replacing its action.
  document.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) return;
    var link = event.target.closest('a[href]');
    if (!link) return;
    var url;
    try { url = new URL(link.href, location.href); } catch (error) { return; }
    if (url.origin === location.origin && url.pathname === location.pathname) openHash(url.hash);
  }, true);
  window.addEventListener('hashchange', function () { openHash(location.hash); });
  window.addEventListener('pageshow', function () { init(document); });
  window.addEventListener('pagehide', function () { copyTimers.forEach(clearTimeout); copyTimers.clear(); });
  document.addEventListener('mao-tk-toolkit:article-ui:init', function (event) { init(event.detail && event.detail.container || document); });
  window.MaoTKArticleReading = { init: init, destroy: destroy };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }, { once: true });
  else init(document);
})();
