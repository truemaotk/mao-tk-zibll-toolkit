(function (wp, model, payload) {
  'use strict';
  if (!wp || !wp.hooks || !model || !payload || window.MaoTKNoteEditorReady) { return; }
  window.MaoTKNoteEditorReady = true;
  var el = wp.element.createElement, Fragment = wp.element.Fragment;
  var c = wp.components, Inspector = wp.blockEditor.InspectorControls;
  var overrideKey = 'maoTkNoteVisual';
  wp.hooks.addFilter('blocks.registerBlockType', 'mao-tk-toolkit/note-attributes', function (settings, name) {
    if (name !== 'maotk/note') { return settings; }
    return Object.assign({}, settings, { attributes: Object.assign({}, settings.attributes, { maoTkNoteVisual: { type: 'object' } }) });
  });
  wp.hooks.addFilter('editor.BlockEdit', 'mao-tk-toolkit/note-inspector', function (BlockEdit) {
    return function NoteEdit(props) {
      if (props.name !== 'maotk/note') { return el(BlockEdit, props); }
      var type = Object.prototype.hasOwnProperty.call(payload.baselines, props.attributes.type) ? props.attributes.type : 'info';
      var own = model.clean(props.attributes[overrideKey], payload.icons);
      var effective = model.resolve(payload, type, own);
      function set(key, value) { var next = Object.assign({}, own); if (value === undefined) { delete next[key]; } else { next[key] = value; } var update = {}; update[overrideKey] = Object.keys(next).length ? next : undefined; props.setAttributes(update); }
      function reset() { var update = {}; update[overrideKey] = undefined; props.setAttributes(update); }
      var controls = [];
      ['light', 'dark'].forEach(function (key) {
        var explicit = Object.prototype.hasOwnProperty.call(own, key);
        controls.push(el('div', { key: key }, el('p', null, (key === 'light' ? '浅色模式' : '深色模式') + ' · ' + (explicit ? '单块覆盖' : '继承') + ' ' + effective[key]), el(c.ColorPicker, { color: effective[key], enableAlpha: false, onChange: function (color) { if (typeof color === 'string' && /^#[0-9a-fA-F]{6}$/.test(color)) { set(key, color); } } }), el(c.Button, { variant: 'tertiary', onClick: function () { set(key, undefined); } }, '恢复该颜色继承')));
      });
      function select(key, label, options) {
        controls.push(el(c.SelectControl, { key: key, label: label, value: Object.prototype.hasOwnProperty.call(own, key) ? String(own[key]) : '', options: [{ label: '继承（当前：' + String(effective[key]) + '）', value: '' }].concat(options), onChange: function (value) { set(key, value === '' ? undefined : key === 'showIcon' ? value === 'true' : value); } }));
      }
      select('badgePosition', '标签位置', [{ label: '左', value: 'left' }, { label: '中', value: 'center' }, { label: '右', value: 'right' }]);
      select('iconPosition', '图标位置', [{ label: '文字前', value: 'before' }, { label: '文字后', value: 'after' }]);
      select('iconKey', '本地图标', Object.keys(payload.icons).map(function (key) { return { label: payload.icons[key].label, value: key }; }));
      controls.push(el(c.RangeControl, { key: 'size', label: '图标尺寸 · ' + (Object.prototype.hasOwnProperty.call(own, 'iconSize') ? '单块覆盖' : '继承'), value: effective.iconSize, min: 12, max: 28, onChange: function (value) { if (Number.isInteger(value) && value >= 12 && value <= 28) { set('iconSize', value); } } }), el(c.Button, { key: 'inherit-size', variant: 'tertiary', onClick: function () { set('iconSize', undefined); } }, '恢复尺寸继承'));
      select('showIcon', '显示图标', [{ label: '显示', value: 'true' }, { label: '隐藏', value: 'false' }]);
      select('titleColor', '内部标题颜色', [{ label: '跟随类型色', value: 'type' }, { label: '正文中性色', value: 'neutral' }]);
      controls.push(el(c.Button, { key: 'inherit', variant: 'secondary', onClick: reset }, '全部恢复继承'), el(c.Button, { key: 'sealed', variant: 'tertiary', onClick: function () { var update = {}; update[overrideKey] = Object.assign({}, payload.baselines[type]); props.setAttributes(update); } }, '使用封板默认（单块覆盖）'));
      return el(Fragment, null, el(BlockEdit, props), el(Inspector, null, el(c.PanelBody, { title: 'Toolkit 提示框外观', initialOpen: false }, !payload.enabled && el('p', null, '前台外观增强未启用或此文章类型未纳入范围。配置仍可保存，画布保留基础外观。'), el('p', null, '标签和标题的显示开关仍在原提示框设置中；内部标题不加入文章目录。'), controls)));
    };
  });
  wp.hooks.addFilter('editor.BlockListBlock', 'mao-tk-toolkit/note-preview', function (BlockListBlock) {
    return function NoteBlockList(props) {
      if (props.name !== 'maotk/note' || !payload.enabled) { return el(BlockListBlock, props); }
      var value = model.resolve(payload, props.attributes.type, props.attributes[overrideKey]);
      var visual = model.props(value, payload.icons);
      var wrapper = Object.assign({}, props.wrapperProps, visual, { style: Object.assign({}, props.wrapperProps && props.wrapperProps.style, visual.style) });
      return el(BlockListBlock, Object.assign({}, props, { wrapperProps: wrapper }));
    };
  });
  // No blocks.getSaveElement / extraProps filter: the original save markup stays byte-compatible.
}(window.wp, window.MaoTKNoteModel, window.MaoTKNoteEditor));
