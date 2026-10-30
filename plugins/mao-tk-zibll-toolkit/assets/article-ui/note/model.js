(function (scope) {
  'use strict';
  var fields = ['light', 'dark', 'iconKey', 'badgePosition', 'iconPosition', 'iconSize', 'showIcon', 'titleColor'];
  function clean(raw, icons, strict) {
    var result = {};
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) { return result; }
    fields.forEach(function (key) {
      var value = raw[key], valid = false;
      if (key === 'light' || key === 'dark') { valid = typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value); }
      else if (key === 'iconSize') { valid = Number.isInteger(value) && value >= 12 && value <= 28; }
      else if (key === 'showIcon') { valid = typeof value === 'boolean'; }
      else if (key === 'iconKey') { valid = typeof value === 'string' && /^[a-z][a-z0-9-]{0,47}$/.test(value) && (!strict || Object.prototype.hasOwnProperty.call(icons, value)); }
      else { valid = ({ badgePosition: ['left', 'center', 'right'], iconPosition: ['before', 'after'], titleColor: ['type', 'neutral'] }[key] || []).indexOf(value) !== -1; }
      if (valid) { result[key] = value; }
    });
    return result;
  }
  function resolve(payload, type, override) {
    type = Object.prototype.hasOwnProperty.call(payload.baselines, type) ? type : 'info';
    var settings = payload.settings;
    var value = Object.assign({}, payload.baselines[type], clean(settings.defaults, payload.icons), clean(settings.types[type], payload.icons), clean(override, payload.icons));
    if (!Object.prototype.hasOwnProperty.call(payload.icons, value.iconKey)) { value.iconKey = payload.baselines[type].iconKey; }
    return value;
  }
  function props(value, icons) {
    return { 'data-mao-tk-note-visual': '1', 'data-maotk-note-badge-position': value.badgePosition, 'data-maotk-note-icon-position': value.iconPosition, 'data-maotk-note-icon': value.showIcon ? 'show' : 'none', 'data-maotk-note-title-color': value.titleColor, style: { '--maotk-note-color-light': value.light, '--maotk-note-color-dark': value.dark, '--maotk-note-icon-size': value.iconSize + 'px', '--maotk-note-icon': 'url("' + (icons[value.iconKey] ? icons[value.iconKey].url : '') + '")' } };
  }
  function apply(element, value, icons) {
    var attrs = props(value, icons);
    Object.keys(attrs).forEach(function (key) {
      if (key === 'style') { Object.keys(attrs.style).forEach(function (name) { element.style.setProperty(name, attrs.style[name]); }); }
      else { element.setAttribute(key, attrs[key]); }
    });
  }
  scope.MaoTKNoteModel = Object.freeze({ fields: fields, clean: clean, resolve: resolve, props: props, apply: apply });
}(typeof window === 'object' ? window : globalThis));
