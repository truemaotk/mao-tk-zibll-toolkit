(function () {
  'use strict';
  // Adaptation of the sealed link preview. Native TAB/code/business handlers are untouched.
  if (window.MaoTKArticleUI) { window.MaoTKArticleUI.init(document); return; }
  var instances = new Map();
  var tabLabels = new Set();
  var excluded = 'dialog, .modal, [role="dialog"], .enlighter-default, pre, code, .post-tab, .mtk-native-postsbox, .article-postsbox, .posts-pay, .pay-box, .download-box, .article-download, .wp-block-zibllblock-file, .product-box, .productbox, .dplayer, .comments-area, #respond';
  function roots(container, selector) {
    var list = [];
    if (!container || !container.querySelectorAll) { return list; }
    if (container.matches && container.matches(selector)) { list.push(container); }
    return list.concat(Array.from(container.querySelectorAll(selector)));
  }
  function url(value) {
    try { var parsed = new URL(value, location.href); return /^https?:$/.test(parsed.protocol) ? parsed : null; }
    catch (error) { return null; }
  }
  function destination(link) {
    var direct = url(link.href);
    if (!direct) { return null; }
    var encoded = direct.origin === location.origin && direct.searchParams.get('golink');
    if (encoded) {
      try {
        var bytes = Uint8Array.from(atob(encoded), function (c) { return c.charCodeAt(0); });
        var decoded = typeof TextDecoder === 'function' ? new TextDecoder('utf-8', { fatal: true }).decode(bytes) : atob(encoded);
        return url(decoded) || direct;
      } catch (error) { /* Invalid golink retains the original native URL. */ }
    }
    return direct;
  }
  function initLinks(root) {
    if (instances.has(root) || root.dataset.mtkLinkReady === '1' || root.querySelector('[data-mtk-link-preview]') || root.closest(excluded)) { return; }
    var template = document.getElementById('mao-tk-ui-link-template');
    if (!template || !template.content) { return; }
    var fragment = template.content.cloneNode(true);
    var card = fragment.querySelector('[data-mtk-link-preview]');
    if (!card) { return; }
    var open = card.querySelector('[data-mtk-link-open]'), copy = card.querySelector('[data-mtk-link-copy]');
    var domain = card.querySelector('[data-mtk-link-domain]'), title = card.querySelector('[data-mtk-link-title]');
    var address = card.querySelector('[data-mtk-link-address]'), image = card.querySelector('[data-mtk-link-image]');
    var status = card.querySelector('[data-mtk-link-status]');
    if (![open, copy, domain, title, address, image, status].every(Boolean)) { return; }
    card.setAttribute('data-mao-tk-ui-owned', '1');
    card.removeAttribute('id');
    var current = null, currentURL = '', showTimer, hideTimer, copyTimer, suppressFocus;
    var listeners = [], decorated = new Set(), labels = new Map(), zoomLabels = new Map(), sequence = 0;
    var hover = window.matchMedia('(hover: hover) and (pointer: fine)');
    function listen(target, name, handler, options) {
      target.addEventListener(name, handler, options);
      listeners.push(function () { target.removeEventListener(name, handler, options); });
    }
    function eligible(element) {
      if (!element || !element.closest) { return null; }
      var link = element.closest('a[href]');
      if (!link || !root.contains(link) || link.closest('.maotk-ui-kit') !== root || link.closest(excluded)) { return null; }
      if (link.matches('[data-link-preview="off"], [download], [data-download-file], .cashier-link, [data-toggle], [data-target], [zibajax], .wp-ajax-submit, .but, .button')) { return null; }
      var href = link.getAttribute('href').trim();
      var target = url(link.href);
      if (!href || href.charAt(0) === '#' || !target || target.searchParams.get('action') === 'download_file') { return null; }
      return link;
    }
    function reserveZoom(label) {
      if (!label || zoomLabels.has(label)) { return; }
      var reserve = label.cloneNode(true); reserve.className = 'mtk-link-reserve';
      reserve.removeAttribute('id'); reserve.setAttribute('aria-hidden', 'true'); reserve.inert = true;
      reserve.querySelectorAll('[id]').forEach(function (node) { node.removeAttribute('id'); });
      var text = document.createElement('span'); text.className = 'mtk-link-text';
      while (label.firstChild) { text.appendChild(label.firstChild); }
      label.appendChild(text); label.appendChild(reserve);
      zoomLabels.set(label, { text: text, reserve: reserve });
    }
    function decorate() {
      root.querySelectorAll('a[href]').forEach(function (link) {
        if (!eligible(link)) { return; }
        link.setAttribute('data-mao-tk-ui-link', '1'); decorated.add(link);
        if (!labels.has(link) && !link.closest('.mtk-link-demo') && link.closest('p,li,td,th') && link.textContent.trim() && !link.querySelector('img,svg,button,.mtk-icon')) {
          var label = document.createElement('span'); label.className = 'mtk-link-label';
          while (link.firstChild) { label.appendChild(link.firstChild); }
          var icon = document.createElement('span'); icon.className = 'mtk-link-affordance'; icon.setAttribute('aria-hidden', 'true');
          link.classList.add('mtk-inline-link'); link.appendChild(label); link.appendChild(icon);
          labels.set(link, { label: label, icon: icon });
        }
        reserveZoom(link.querySelector('.mtk-link-label'));
      });
    }
    function hide() {
      clearTimeout(showTimer); clearTimeout(hideTimer); clearTimeout(copyTimer);
      sequence++; card.hidden = true; current = null; currentURL = '';
    }
    function alive() {
      if (root.isConnected) { return true; }
      destroy(); return false;
    }
    function position() {
      if (!current || !alive()) { return; }
      var anchor = current.getBoundingClientRect();
      if (!current.isConnected || anchor.bottom < 0 || anchor.top > innerHeight) { hide(); return; }
      var box = card.getBoundingClientRect();
      card.style.left = Math.max(12, Math.min(anchor.left, innerWidth - box.width - 12)) + 'px';
      var top = anchor.bottom + 8;
      if (top + box.height > innerHeight - 12) { top = anchor.top - box.height - 8; }
      card.style.top = Math.max(12, Math.min(top, innerHeight - box.height - 12)) + 'px';
    }
    function show(link) {
      if (!alive() || !eligible(link)) { return; }
      instances.forEach(function (instance, other) { if (other !== root) { instance.hide(); } });
      clearTimeout(hideTimer); clearTimeout(copyTimer); sequence++;
      var target = destination(link);
      if (!target) { return; }
      current = link; currentURL = target.href;
      domain.textContent = target.hostname;
      var thumb = link.querySelector('img[alt]');
      var visualText = link.querySelector('.mtk-link-text');
      title.textContent = link.getAttribute('data-link-preview-title') || (visualText ? visualText.textContent : link.textContent).trim() || (thumb && thumb.alt) || target.hostname;
      address.textContent = target.host + target.pathname + target.search + target.hash; address.title = target.href;
      open.href = link.href; // Preserve native golink/open action, copy only the resolved destination.
      copy.dataset.copied = 'false'; copy.setAttribute('aria-label', '复制链接');
      status.textContent = ''; card.dataset.copyFeedback = 'false';
      image.hidden = true; image.removeAttribute('src');
      var picture = link.getAttribute('data-link-preview-image');
      var pictureURL = picture && url(picture);
      if (pictureURL) { image.src = pictureURL.href; image.hidden = false; }
      card.hidden = false; position();
    }
    function hideSoon() {
      clearTimeout(showTimer); clearTimeout(hideTimer);
      hideTimer = setTimeout(function () {
        if (!alive()) { return; }
        if (card.matches(':hover') || card.contains(document.activeElement) || current === document.activeElement) { return; }
        hide();
      }, 240);
    }
    function destroy() {
      hide(); listeners.forEach(function (remove) { remove(); });
      zoomLabels.forEach(function (parts, label) {
        while (parts.text.firstChild) { label.insertBefore(parts.text.firstChild, parts.text); }
        parts.text.remove(); parts.reserve.remove();
      });
      labels.forEach(function (parts, link) {
        while (parts.label.firstChild) { link.insertBefore(parts.label.firstChild, parts.label); }
        parts.label.remove(); parts.icon.remove(); link.classList.remove('mtk-inline-link');
      });
      decorated.forEach(function (link) { link.removeAttribute('data-mao-tk-ui-link'); });
      card.remove(); root.removeAttribute('data-mtk-link-ready'); instances.delete(root);
    }
    root.dataset.mtkLinkReady = '1'; document.body.appendChild(card);
    instances.set(root, { destroy: destroy, hide: hide, decorate: decorate }); decorate();
    listen(root, 'mouseover', function (event) {
      if (!hover.matches) { return; }
      var link = eligible(event.target);
      if (!link || (event.relatedTarget && link.contains(event.relatedTarget)) || current === link) { return; }
      hide(); showTimer = setTimeout(function () { show(link); }, 180);
    });
    listen(root, 'mouseout', function (event) {
      var link = eligible(event.target);
      if (link && !(event.relatedTarget && link.contains(event.relatedTarget))) { hideSoon(); }
    });
    listen(root, 'focusin', function (event) { var link = eligible(event.target); if (link && link !== suppressFocus) { show(link); } });
    listen(root, 'focusout', hideSoon);
    listen(root, 'keydown', function (event) {
      var link = eligible(event.target);
      if (event.key === 'ArrowDown' && link) { event.preventDefault(); show(link); open.focus(); }
    });
    listen(card, 'mouseenter', function () { clearTimeout(hideTimer); });
    listen(card, 'mouseleave', hideSoon); listen(card, 'focusout', hideSoon);
    listen(image, 'load', position); listen(image, 'error', function () { image.hidden = true; position(); });
    listen(copy, 'click', async function () {
      var payload = currentURL, ticket = sequence;
      if (!payload) { return; }
      try {
        await navigator.clipboard.writeText(payload);
        if (ticket !== sequence || !alive()) { return; }
        clearTimeout(copyTimer); copy.dataset.copied = 'true'; copy.setAttribute('aria-label', '已复制链接');
        status.textContent = '链接已复制'; card.dataset.copyFeedback = 'true';
        copyTimer = setTimeout(function () { copy.dataset.copied = 'false'; copy.setAttribute('aria-label', '复制链接'); status.textContent = ''; card.dataset.copyFeedback = 'false'; }, 1600);
      } catch (error) {
        if (ticket !== sequence || !alive()) { return; }
        status.textContent = '请选中下方地址手动复制'; card.dataset.copyFeedback = 'true';
      }
    });
    listen(document, 'keydown', function (event) {
      if (!alive() || event.key !== 'Escape' || card.hidden) { return; }
      var restore = current, within = card.contains(document.activeElement); hide();
      if (within && restore && restore.isConnected) { suppressFocus = restore; restore.focus(); setTimeout(function () { suppressFocus = null; }, 0); }
    });
    listen(document, 'pointerdown', function (event) {
      if (!alive() || card.hidden || card.contains(event.target) || (current && current.contains(event.target))) { return; }
      hide();
    });
    listen(window, 'scroll', function () {
      if (!alive() || !current) { return; }
      if (card.contains(document.activeElement) || current === document.activeElement) { position(); } else { hide(); }
    }, { passive: true });
    listen(window, 'resize', hide);
  }
  function init(container) {
    instances.forEach(function (instance, root) { if (!root.isConnected) { instance.destroy(); } });
    roots(container, '.maotk-ui-kit[data-mao-tk-ui-tabs="1"]').forEach(function (root) {
      if (root.closest(excluded)) { return; }
      root.querySelectorAll('.mtk-native-tabs .post-tab.nav-top > .tab-nav-theme > li > .post-tab-toggle').forEach(function (link) {
        if (link.closest('.maotk-ui-kit') !== root || link.querySelector('.mtk-tab-label')) { return; }
        var label = document.createElement('span'); label.className = 'mtk-tab-label';
        label.setAttribute('data-mao-tk-ui-label', '1');
        while (link.firstChild) { label.appendChild(link.firstChild); }
        link.appendChild(label); tabLabels.add(label);
      });
    });
    roots(container, '.maotk-ui-kit[data-mao-tk-ui-link-preview="1"]').forEach(function (root) {
      initLinks(root); if (instances.has(root)) { instances.get(root).decorate(); }
    });
    tabLabels.forEach(function (label) { if (!label.isConnected) { tabLabels.delete(label); } });
  }
  function destroy(container) {
    instances.forEach(function (instance, root) { if (root === container || (container.contains && container.contains(root))) { instance.destroy(); } });
    tabLabels.forEach(function (label) {
      if (label.isConnected && (container === label || (container.contains && container.contains(label)))) {
        while (label.firstChild) { label.parentNode.insertBefore(label.firstChild, label); }
        label.remove(); tabLabels.delete(label);
      } else if (!label.isConnected) { tabLabels.delete(label); }
    });
  }
  window.MaoTKArticleUI = Object.freeze({ init: init, destroy: destroy, version: '1.0.0' });
  document.addEventListener('mao-tk-toolkit:article-ui:init', function (event) { init(event.detail && event.detail.container || document); });
  window.addEventListener('pagehide', function () { destroy(document); });
  window.addEventListener('pageshow', function (event) { if (event.persisted) { init(document); } });
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(document); }, { once: true }); }
  else { init(document); }
}());
