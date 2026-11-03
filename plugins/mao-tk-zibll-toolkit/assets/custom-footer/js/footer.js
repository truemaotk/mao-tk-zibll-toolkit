/* Scoped mobile adapter using the Toolkit's existing disclosure engine. */
(function () {
  'use strict';
  // A missing custom image falls back to its local icon; navigation/QR behaviour is unchanged.
  document.querySelectorAll('.mtk-footer-component .mtk-footer-social-custom-image').forEach(function (image) {
    function restore() {
      var fallback = document.createElement('span');
      fallback.className = 'mtk-footer-social-icon';
      fallback.setAttribute('data-social', image.getAttribute('data-social'));
      fallback.setAttribute('aria-hidden', 'true');
      image.replaceWith(fallback);
    }
    image.addEventListener('error', restore, { once: true });
    if (image.complete && !image.naturalWidth) restore();
  });
  // Keep labels/QR tooltips inside the actual viewport, including a scaled preview iframe.
  var socialItems = document.querySelectorAll('.mtk-footer-component .mtk-footer-social-item');
  function placeTooltip(item) {
    var tooltip = item.querySelector('.mtk-footer-social-tooltip');
    if (!tooltip) return;
    tooltip.style.setProperty('--mtk-footer-tip-offset', '0px');
    var bounds = tooltip.getBoundingClientRect();
    var offset = Math.max(12 - bounds.left, 0) + Math.min(window.innerWidth - 12 - bounds.right, 0);
    tooltip.style.setProperty('--mtk-footer-tip-offset', offset + 'px');
  }
  socialItems.forEach(function (item) {
    placeTooltip(item);
    item.addEventListener('pointerenter', function () { placeTooltip(item); });
    item.addEventListener('focusin', function () { placeTooltip(item); });
  });
  window.addEventListener('resize', function () {
    socialItems.forEach(placeTooltip);
  });
  var motion = window.MaoTKDisclosureMotion;
  if (!motion || typeof window.matchMedia !== 'function') return;
  var mobile = window.matchMedia('(max-width: 768px)');
  document.querySelectorAll('.mtk-footer-component').forEach(function (root) {
    if (root.hasAttribute('data-footer-disclosures-ready')) return;
    root.setAttribute('data-footer-disclosures-ready', '1');
    var states = [];
    root.querySelectorAll('[data-footer-disclosure]').forEach(function (section) {
      var button = section.querySelector('.mtk-footer-toggle');
      var panel = section.querySelector('.mtk-footer-panel');
      var content = panel && panel.querySelector('.mtk-footer-panel-content');
      if (!button || !panel || !content) return;
      var state = { button: button, panel: panel, content: content, mobileOpen: false, open: true };
      function change(open, instant) {
        state.open = open;
        button.setAttribute('aria-expanded', String(open));
        panel.inert = !open;
        panel.setAttribute('aria-hidden', String(!open));
        if (!open && panel.contains(document.activeElement)) button.focus({ preventScroll: true });
        panel.classList.toggle('is-animating', !instant);
        motion.set(panel, content, open, { instant: instant, onFinish: function () {
          panel.hidden = !state.open;
          panel.classList.remove('is-animating');
        } });
      }
      button.addEventListener('click', function () {
        if (!mobile.matches) return;
        state.mobileOpen = !state.mobileOpen;
        change(state.mobileOpen, false);
      });
      state.sync = function () {
        button.disabled = !mobile.matches;
        change(mobile.matches ? state.mobileOpen : true, true);
      };
      states.push(state);
    });
    function sync() {
      root.classList.add('is-updating-layout');
      states.forEach(function (state) { state.sync(); });
      root.getBoundingClientRect();
      window.requestAnimationFrame(function () { root.classList.remove('is-updating-layout'); });
    }
    if (mobile.addEventListener) mobile.addEventListener('change', sync);
    else mobile.addListener(sync);
    sync();
  });
})();
