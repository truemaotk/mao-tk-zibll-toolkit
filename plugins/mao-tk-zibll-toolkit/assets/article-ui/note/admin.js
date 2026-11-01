(function () {
  'use strict';
  function init() {
    var root = document.querySelector('[data-mao-tk-note-settings]');
    var model = window.MaoTKNoteModel, payload = window.MaoTKNoteSettings;
    if (!root || !model || !payload) { return; }
    var form = root.querySelector('.mao-tk-note-form'), tabs = Array.from(root.querySelectorAll('[data-note-tab]'));
    var tabList = root.querySelector('[data-note-tablist]'), tabIndicator = root.querySelector('[data-note-tab-indicator]');
    var currentTab = form.elements.note_ui_tab.value, previewType = payload.types[currentTab] ? currentTab : 'info';
    var allTypes = false, device = 'desktop', theme = 'light';
    var palette = root.querySelector('#mao-tk-note-palette'), board = palette.querySelector('[data-note-sv]');
    var activeColor = null, activeTrigger = null, hsv, dragging = null, colorLock = false, positionFrame;
    function input(group, key) { return root.querySelector('[data-note-group="' + group + '"][data-note-field="' + key + '"]'); }
    function draft() {
      var settings = { schema_version: 1, include_pages: form.elements['note[include_pages]'].value === '1', defaults: {}, types: {} };
      root.querySelectorAll('[data-note-field]').forEach(function (field) {
        var group = field.dataset.noteGroup, key = field.dataset.noteField, value = field.value;
        if (value === '') { return; }
        if (key === 'iconSize') { value = Number(value); } else if (key === 'showIcon') { value = value === 'true'; }
        var fields = group === 'defaults' ? settings.defaults : (settings.types[group] || (settings.types[group] = {})); fields[key] = value;
      });
      return Object.assign({}, payload, { settings: settings });
    }
    function resolved(current, group) {
      var config = group === 'defaults' ? Object.assign({}, current, { settings: Object.assign({}, current.settings, { types: {} }) }) : current;
      return model.resolve(config, group === 'defaults' ? 'info' : group, {});
    }
    function snapshot() { return JSON.stringify({ settings: draft().settings, provider: form.elements.note_provider.value }); }
    var original = snapshot();
    function update() {
      var current = draft(), selected = model.resolve(current, previewType, {});
      root.querySelector('[data-note-canvas]').dataset.noteView = allTypes ? 'all' : 'current';
      root.querySelectorAll('[data-note-preview]').forEach(function (note) {
        model.apply(note, model.resolve(current, note.dataset.notePreview, {}), current.icons);
        note.hidden = !allTypes && note.dataset.notePreview !== previewType;
      });
      root.querySelectorAll('[data-note-size-range]').forEach(function (range) {
        var group = range.dataset.noteGroup, value = resolved(current, group);
        range.value = value.iconSize;
        ['light', 'dark', 'iconSize'].forEach(function (key) {
          var field = input(group, key); field.placeholder = String(value[key]);
          if (key === 'iconSize') { return; }
          root.querySelector('[data-note-color-trigger="' + field.id + '"]').style.setProperty('--mao-tk-note-swatch', value[key]);
        });
      });
      var label = root.querySelector('[data-note-preview-label]'); label.textContent = allTypes ? '五种类型' : payload.types[previewType];
      root.querySelector('[data-note-preview-summary]').textContent = selected[theme === 'dark' ? 'dark' : 'light'].toUpperCase() + ' / 图标 ' + selected.iconSize + 'px / ' + ({left:'左对齐',center:'居中',right:'右对齐'})[selected.badgePosition];
      var dirty = root.querySelector('[data-note-dirty]'), changed = snapshot() !== original;
      dirty.textContent = changed ? '未保存' : '已同步'; dirty.dataset.changed = String(changed);
      if (activeColor && !colorLock) { syncPalette(); }
    }
    function positionTabIndicator() {
      if (!tabIndicator) { return; }
      var selected = tabs.find(function (tab) { return tab.getAttribute('aria-selected') === 'true'; });
      var label = selected && selected.querySelector('.mao-tk-note-tab-label');
      if (!label || !tabList.clientWidth) { return; }
      var rect = label.getBoundingClientRect(), listRect = tabList.getBoundingClientRect();
      tabIndicator.style.width = rect.width + 'px';
      tabIndicator.style.transform = 'translateX(' + (rect.left - listRect.left + tabList.scrollLeft - tabList.clientLeft) + 'px)';
      tabList.dataset.noteIndicatorReady = '1';
    }
    function activate(key, focus) {
      if (!tabs.some(function (tab) { return tab.dataset.noteTab === key; })) { return; }
      closePalette(false); currentTab = key; form.elements.note_ui_tab.value = key;
      if (payload.types[key]) { previewType = key; }
      root.querySelectorAll('[data-note-panel]').forEach(function (panel) { panel.hidden = panel.dataset.notePanel !== key; });
      tabs.forEach(function (tab) {
        var selected = tab.dataset.noteTab === key; tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1;
        if (selected && focus) { tab.focus({preventScroll:true}); }
        if (selected) {
          var rect = tab.getBoundingClientRect(), listRect = tabList.getBoundingClientRect();
          if (rect.left < listRect.left + 12) { tabList.scrollLeft += rect.left - listRect.left - 12; }
          else if (rect.right > listRect.right - 12) { tabList.scrollLeft += rect.right - listRect.right + 12; }
        }
      });
      update();
      positionTabIndicator();
    }
    function press(group, key, value) {
      root.querySelectorAll('button[' + group + ']').forEach(function (button) { button.setAttribute('aria-pressed', String(button.getAttribute(key) === value)); });
    }
    // Reuse the HSV math already exercised by the Toolkit banner color controls.
    function toHSV(hex, previousHue) {
      var rgb = [1,3,5].map(function (start) { return parseInt(hex.slice(start, start + 2), 16) / 255; });
      var max = Math.max.apply(null, rgb), min = Math.min.apply(null, rgb), delta = max - min, hue = previousHue || 0;
      if (delta) {
        if (max === rgb[0]) { hue = 60 * (((rgb[1] - rgb[2]) / delta) % 6); }
        else if (max === rgb[1]) { hue = 60 * ((rgb[2] - rgb[0]) / delta + 2); }
        else { hue = 60 * ((rgb[0] - rgb[1]) / delta + 4); }
      }
      return { h:(hue + 360) % 360, s:max ? delta / max : 0, v:max };
    }
    function fromHSV(state) {
      var h = (state.h % 360) / 60, chroma = state.v * state.s, x = chroma * (1 - Math.abs(h % 2 - 1)), m = state.v - chroma;
      var rgb = [[chroma,x,0],[x,chroma,0],[0,chroma,x],[0,x,chroma],[x,0,chroma],[chroma,0,x]][Math.floor(h)];
      return '#' + rgb.map(function (channel) { return Math.round((channel + m) * 255).toString(16).padStart(2,'0'); }).join('');
    }
    function paintPalette() {
      var hex = fromHSV(hsv), text = 'S ' + Math.round(hsv.s * 100) + '%  V ' + Math.round(hsv.v * 100) + '%';
      board.style.setProperty('--mao-tk-note-hue', String(hsv.h));
      var handle = board.querySelector('[data-note-sv-handle]'); handle.style.left = (hsv.s * 100) + '%'; handle.style.top = ((1 - hsv.v) * 100) + '%';
      board.setAttribute('aria-valuenow', String(Math.round(hsv.v * 100))); board.setAttribute('aria-valuetext', hex + ', ' + text);
      palette.querySelector('[data-note-hue]').value = Math.round(hsv.h);
      palette.querySelector('[data-note-color-value]').textContent = hex.toUpperCase(); palette.querySelector('[data-note-color-hsv]').textContent = text;
    }
    function syncPalette() {
      if (!activeColor) { return; }
      var value = resolved(draft(), activeColor.dataset.noteGroup)[activeColor.dataset.noteField];
      hsv = toHSV(value, hsv && hsv.h); paintPalette();
    }
    function commitColor() {
      if (!activeColor) { return; }
      colorLock = true; activeColor.value = fromHSV(hsv); update(); paintPalette(); colorLock = false;
    }
    function positionPalette() {
      positionFrame = null;
      if (!activeTrigger || palette.hidden) { return; }
      var rect = activeTrigger.closest('.mao-tk-note-color-control').getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > innerHeight) { closePalette(false); return; }
      var bar = document.getElementById('wpadminbar'), inset = bar ? Math.max(12, bar.getBoundingClientRect().bottom + 8) : 12;
      var width = palette.offsetWidth, height = palette.offsetHeight, top = rect.bottom + 8;
      if (top + height > innerHeight - 12 && rect.top - height - 8 >= inset) { top = rect.top - height - 8; }
      palette.style.left = Math.max(12, Math.min(rect.left, innerWidth - width - 12)) + 'px';
      palette.style.top = Math.max(inset, Math.min(top, innerHeight - height - 12)) + 'px';
    }
    function closePalette(restore) {
      var trigger = activeTrigger;
      if (dragging !== null && board.hasPointerCapture && board.hasPointerCapture(dragging)) { board.releasePointerCapture(dragging); }
      dragging = null; palette.hidden = true; activeColor = null; activeTrigger = null;
      if (trigger) { trigger.setAttribute('aria-expanded','false'); if (restore) { trigger.focus(); } }
    }
    function openPalette(trigger) {
      if (trigger === activeTrigger && !palette.hidden) { closePalette(true); return; }
      closePalette(false); activeTrigger = trigger; activeColor = document.getElementById(trigger.dataset.noteColorTrigger);
      trigger.setAttribute('aria-expanded','true'); palette.hidden = false;
      palette.querySelector('#mao-tk-note-palette-title').textContent = activeColor.dataset.noteField === 'light' ? '浅色模式' : '深色模式';
      var presets = palette.querySelector('[data-note-color-presets]'); presets.replaceChildren();
      Object.keys(payload.baselines).forEach(function (type) {
        var color = payload.baselines[type][activeColor.dataset.noteField], button = document.createElement('button');
        button.type = 'button'; button.style.backgroundColor = color; button.dataset.notePreset = color;
        button.setAttribute('aria-label', payload.types[type] + '默认色 ' + color); presets.appendChild(button);
      });
      syncPalette(); positionPalette(); board.focus({preventScroll:true});
    }
    root.addEventListener('input', function (event) {
      if (event.target.matches('[data-note-hue]')) { hsv.h = Number(event.target.value); commitColor(); return; }
      if (event.target.matches('[data-note-size-range]')) { input(event.target.dataset.noteGroup, 'iconSize').value = event.target.value; }
      update();
    });
    root.addEventListener('change', update);
    root.addEventListener('click', function (event) {
      var target = event.target, tab = target.closest('[data-note-tab]');
      if (tab) { activate(tab.dataset.noteTab, false); return; }
      var color = target.closest('[data-note-color-trigger]'); if (color) { openPalette(color); return; }
      var inherit = target.closest('[data-note-color-inherit]'); if (inherit) { document.getElementById(inherit.dataset.noteColorInherit).value = ''; update(); return; }
      if (target.closest('[data-note-color-close]')) { closePalette(true); return; }
      var preset = target.closest('[data-note-preset]'); if (preset) { hsv = toHSV(preset.dataset.notePreset, hsv.h); commitColor(); return; }
      var reset = target.closest('[data-note-reset]');
      if (reset) { root.querySelectorAll('[data-note-field]').forEach(function (field) { if (reset.dataset.noteReset === 'all' || reset.dataset.noteReset === field.dataset.noteGroup) { field.value = ''; } }); update(); }
      var width = target.closest('[data-note-width]');
      if (width) { device = width.dataset.noteWidth; root.querySelector('[data-note-canvas]').dataset.noteDevice = device; press('data-note-width', 'data-note-width', device); }
      var mode = target.closest('[data-note-view]'); if (mode) { allTypes = mode.dataset.noteView === 'all'; press('data-note-view','data-note-view',mode.dataset.noteView); }
      var scheme = target.closest('[data-note-theme]');
      if (scheme) { theme = scheme.dataset.noteTheme; root.querySelector('[data-note-canvas]').classList.toggle('theme-dark', theme === 'dark'); root.querySelector('.mao-tk-note-stage').classList.toggle('theme-dark', theme === 'dark'); press('data-note-theme','data-note-theme',theme); }
      update();
    });
    root.querySelector('[data-note-tablist]').addEventListener('keydown', function (event) {
      var index = tabs.indexOf(event.target); if (index < 0) { return; }
      var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : -1;
      if (next >= 0) { event.preventDefault(); activate(tabs[next].dataset.noteTab, true); }
    });
    function drag(event) {
      var rect = board.getBoundingClientRect(); if (!rect.width || !rect.height) { return; }
      hsv.s = Math.max(0,Math.min(1,(event.clientX - rect.left) / rect.width)); hsv.v = 1 - Math.max(0,Math.min(1,(event.clientY - rect.top) / rect.height)); commitColor();
    }
    board.addEventListener('pointerdown', function (event) { if (event.button !== 0) { return; } event.preventDefault(); dragging = event.pointerId; board.setPointerCapture(dragging); board.focus({preventScroll:true}); drag(event); });
    board.addEventListener('pointermove', function (event) { if (event.pointerId === dragging) { drag(event); } });
    ['pointerup','pointercancel','lostpointercapture'].forEach(function (name) { board.addEventListener(name,function () { dragging = null; }); });
    board.addEventListener('keydown',function (event) {
      var step = event.shiftKey ? .1 : .01;
      if (event.key === 'ArrowLeft') { hsv.s = Math.max(0,hsv.s - step); }
      else if (event.key === 'ArrowRight') { hsv.s = Math.min(1,hsv.s + step); }
      else if (event.key === 'ArrowUp') { hsv.v = Math.min(1,hsv.v + step); }
      else if (event.key === 'ArrowDown') { hsv.v = Math.max(0,hsv.v - step); }
      else if (event.key === 'Home') { hsv.s = 0; hsv.v = 1; }
      else if (event.key === 'End') { hsv.v = 0; }
      else { return; }
      event.preventDefault(); commitColor();
    });
    document.addEventListener('keydown',function (event) { if (event.key === 'Escape' && !palette.hidden) { event.preventDefault(); closePalette(true); } });
    document.addEventListener('pointerdown',function (event) { if (!palette.hidden && !palette.contains(event.target) && !event.target.closest('[data-note-color-trigger]')) { closePalette(false); } });
    function reposition() { if (!palette.hidden && !positionFrame) { positionFrame = requestAnimationFrame(positionPalette); } }
    window.addEventListener('resize',reposition); window.addEventListener('scroll',reposition,{passive:true,capture:true});
    // Native validation emits invalid synchronously for every failing field. Keep
    // the first error visible so later hidden tabs cannot steal its focus.
    var validating = false;
    form.addEventListener('invalid',function (event) {
      if (validating) { return; }
      validating = true;
      var panel = event.target.closest('[data-note-panel]');
      if (panel) { activate(panel.dataset.notePanel,false); }
      window.setTimeout(function () { validating = false; }, 0);
    },true);
    root.dataset.noteUiReady = '1'; root.querySelector('[data-note-canvas]').dataset.noteDevice = device; activate(currentTab,false);
    window.addEventListener('resize', positionTabIndicator);
    if (typeof ResizeObserver === 'function') { new ResizeObserver(positionTabIndicator).observe(tabList); }
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(positionTabIndicator); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded',init,{once:true}); } else { init(); }
}());
