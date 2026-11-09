(function () {
    'use strict';
    const translate = text => window.wp?.i18n?.__(text, 'mao-tk-zibll-toolkit') ?? text;
    var root = document.querySelector('[data-system-mail]'), config = window.MaoTKSystemMail;
    if (!root || !config) return;
    function normalize(value) { Object.keys(value.types).forEach(function (type) { value.types[type].overrides = Object.assign({}, value.types[type].overrides); }); return value; }
    var state = normalize(config.state.value), revision = config.state.revision, scope = 'common', sample = 'captcha', busy = false, serial = 0, timer, controller, activeField = 'body';
    var saved = JSON.stringify(state), frame = root.querySelector('[data-mail-frame]');
    function q(s) { return root.querySelector(s); }
    function el(tag, text) { var n = document.createElement(tag); if (text != null) n.textContent = text; return n; }
    function notice(message, error) { var n = q('[data-mail-notice]'); n.textContent = message; n.hidden = !message; n.dataset.error = String(!!error); }
    function mark() { q('[data-mail-dirty]').textContent = JSON.stringify(state) === saved ? translate("设置已同步") : translate("有未保存的修改"); }
    function lock() { q('[data-mail-form]').inert = busy; q('[data-mail-test]').disabled = busy || !config.state.valid; q('[data-mail-save]').disabled = busy || !config.state.valid; }
    function effective(key) { var t = state.types[scope]; return scope !== 'common' && t.mode === 'custom' && Object.hasOwn(t.overrides, key) ? t.overrides[key] : state.common[key]; }
    function set(key, value) { if (scope === 'common') state.common[key] = value; else if (state.types[scope].mode === 'custom') state.types[scope].overrides[key] = value; mark(); previewSoon(); }
    async function request(operation, extras, signal) {
        var body = new FormData(); body.set('action', root.dataset.action); body.set('nonce', root.dataset.nonce); body.set('operation', operation);
        body.set('settings', JSON.stringify(state)); body.set('type', sample); body.set('common_preview', scope === 'common' ? '1' : '0');
        Object.keys(extras || {}).forEach(function (k) { body.set(k, String(extras[k])); });
        var ownController = signal ? null : new AbortController(), deadline = ownController ? setTimeout(function () { ownController.abort(); }, 20000) : null;
        var response;
        try { response = await fetch(root.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: body, signal: signal || ownController.signal }); }
        catch (e) { if (e.name === 'AbortError' && ownController) throw new Error(translate("请求超时，请刷新核对保存结果后再试。")); throw e; }
        finally { if (deadline) clearTimeout(deadline); }
        var data; try { data = await response.json(); } catch (e) { throw new Error(response.status === 403 ? translate("会话已过期，请刷新页面。") : translate("服务器未返回有效数据，请稍后重试。")); }
        if (!response.ok || !data.success) throw new Error(data.data && data.data.message || translate("请求未完成。")); return data.data;
    }
    function fit() {
        try { var d = frame.contentDocument; if (!d || !d.body) return; var rect = d.body.getBoundingClientRect(), css = getComputedStyle(d.body); var h = Math.min(2600, Math.max(440, Math.ceil(rect.height + (parseFloat(css.marginTop) || 0) + (parseFloat(css.marginBottom) || 0)))); if (Math.abs(frame.clientHeight - h) > 2) frame.style.height = h + 'px'; } catch (e) { /* Sandboxed preview stays bounded. */ }
    }
    frame.addEventListener('load', function () {
        fit(); try { frame.contentDocument.addEventListener('click', function (e) { if (e.target.closest('a')) e.preventDefault(); }); frame.contentDocument.querySelectorAll('img').forEach(function (img) { img.addEventListener('load', fit, { once: true }); img.addEventListener('error', fit, { once: true }); }); } catch (e) { /* No script permissions are granted. */ }
    });
    if (window.ResizeObserver) new ResizeObserver(fit).observe(q('[data-mail-stage]'));
    function previewSoon() { ++serial; if (controller) controller.abort(); q('[data-mail-stage]').setAttribute('aria-busy', 'true'); q('[data-mail-preview-state]').textContent = translate("更新预览…"); clearTimeout(timer); timer = setTimeout(preview, 220); }
    async function preview() {
        var id = ++serial; if (controller) controller.abort(); controller = new AbortController(); var abort = setTimeout(function () { controller.abort(); }, 15000);
        q('[data-mail-preview-state]').textContent = translate("更新预览…");
        try { var data = await request('preview', {}, controller.signal); if (id !== serial) return; frame.srcdoc = data.html; q('[data-mail-subject]').textContent = data.subject; q('[data-mail-reply]').textContent = data.reply_to || translate("保留原邮件的回复地址"); q('[data-mail-preview-state]').textContent = data.mode === 'native' ? translate("原生样式 · 结构预览") : translate("正式模板 · 已更新"); }
        catch (error) { if (id !== serial || error.name === 'AbortError') return; q('[data-mail-preview-state]').textContent = error.message; }
        finally { clearTimeout(abort); if (id === serial) q('[data-mail-stage]').setAttribute('aria-busy', 'false'); }
    }
    function field(key, definition) {
        var box = el('div'); box.className = 'mtk-mail-field'; box.dataset.field = key;
        var head = el('div'); head.className = 'mtk-mail-field-head'; var label = el('label', definition.label); label.htmlFor = 'mtk-mail-' + key; head.append(label);
        var custom = scope !== 'common' && state.types[scope].mode === 'custom';
        var overridden = scope !== 'common' && Object.hasOwn(state.types[scope].overrides, key);
        if (scope !== 'common') {
            var inherit = el('label'); inherit.className = 'mtk-mail-inherit'; var toggle = el('input'); toggle.type = 'checkbox'; toggle.checked = custom && overridden; toggle.disabled = !custom;
            toggle.setAttribute('aria-label', definition.label + translate("单独设置")); inherit.append(toggle, el('span', custom && overridden ? translate("独立设置") : translate("继承通用")));
            toggle.addEventListener('change', function () { if (toggle.checked) state.types[scope].overrides[key] = state.common[key]; else delete state.types[scope].overrides[key]; renderFields(); mark(); previewSoon(); }); head.append(inherit);
        }
        box.append(head); var input;
        if (definition.type === 'textarea') { input = el('textarea'); input.rows = key === 'body' ? 5 : 3; }
        else if (definition.type === 'select') { input = el('select'); Object.keys(definition.options).forEach(function (k) { var o = el('option', definition.options[k]); o.value = k; input.append(o); }); }
        else { input = el('input'); input.type = definition.type === 'number' ? 'number' : definition.type === 'color' ? 'color' : definition.type === 'email' ? 'email' : 'text'; if (definition.type === 'number') { input.min = definition.min; input.max = definition.max; input.step = '1'; } if (definition.type === 'url' || definition.type === 'logo') input.inputMode = 'url'; }
        input.id = 'mtk-mail-' + key; input.value = effective(key); input.disabled = scope !== 'common' && (!custom || !overridden); input.autocomplete = 'off';
        input.addEventListener('focus', function () { activeField = key; });
        input.addEventListener(definition.type === 'select' ? 'change' : 'input', function () { set(key, definition.type === 'number' ? Number(input.value) : input.value); if (definition.type === 'color') box.querySelector('code').textContent = input.value.toUpperCase(); });
        if (definition.type === 'color') { var row = el('div'); row.className = 'mtk-mail-color'; row.append(input, el('code', String(effective(key)))); box.append(row); }
        else if (definition.type === 'logo') {
            var row = el('div'); row.className = 'mtk-mail-logo-row'; var button = el('button', translate("选择 PNG")); button.type = 'button'; button.className = 'button'; button.disabled = input.disabled; row.append(input, button); box.append(row);
            button.addEventListener('click', function () {
                if (!window.wp || !wp.media) { notice(translate("媒体库未加载，请填写 PNG 图片地址。"), true); return; }
                var picker = wp.media({ title: translate("选择邮件专用高清 PNG"), button: { text: translate("使用此 PNG") }, library: { type: 'image/png' }, multiple: false });
                picker.on('select', function () { var a = picker.state().get('selection').first().toJSON(); if (a.mime !== 'image/png') { notice(translate("请选择 PNG 图片。"), true); return; } input.value = a.url; set(key, a.url); notice(a.width && a.width < Number(effective('logo_width')) * 2 ? translate("已选择。建议原图宽度至少为显示宽度的两倍，以便高清显示。") : translate("已选择邮件 Logo；网站导航 Logo 保持原设置。")); }); picker.open();
            });
            box.append(el('p', translate("使用原尺寸高清 PNG，建议至少为显示宽度的 2 倍。独立于网站导航 Logo。")));
        } else box.append(input);
        if (definition.help) box.append(el('p', definition.help));
        if (key === 'body') box.append(el('p', translate("可写普通文字并插入变量。验证码、有效期及原始通知详情由系统保留，不会因自定义文案丢失。")));
        return box;
    }
    function renderFields() {
        Object.keys(config.groups).forEach(function (group) {
            var target = q('[data-mail-fields="' + group + '"]'); target.replaceChildren(); var count = 0;
            Object.keys(config.fields).forEach(function (key) { if (config.fields[key].group === group) { target.append(field(key, config.fields[key])); if (scope !== 'common' && Object.hasOwn(state.types[scope].overrides, key)) ++count; } });
            var label = target.closest('[data-mao-tk-disclosure]').querySelector('.mao-tk-disclosure__label'), summary = label.querySelector('.mao-tk-disclosure__summary');
            if (!summary) { summary = el('span'); summary.className = 'mao-tk-disclosure__summary'; label.append(summary); }
            summary.textContent = scope === 'common' ? translate("通用设置") : state.types[scope].mode === 'native' ? translate("原生模式") : state.types[scope].mode === 'custom' && count ? count + translate(" 项独立") : translate("全部继承");
        });
        var copy = q('[data-mail-fields="copy"]'), title = el('p', translate("真正可用的变量（点击插入当前文案）")), tokens = el('div'); tokens.className = 'mtk-mail-tokens';
        var vars = scope === 'common' ? config.commonVariables : config.types[scope].variables;
        vars.forEach(function (token) {
            var button = el('button', '{{' + token + '}}'); button.type = 'button'; button.disabled = scope !== 'common' && state.types[scope].mode !== 'custom'; button.title = translate("插入变量 ") + token;
            button.addEventListener('click', function () {
                var key = ['subject', 'heading', 'body', 'button_label', 'footer_text', 'footer_label'].includes(activeField) ? activeField : 'body';
                if (scope !== 'common' && !Object.hasOwn(state.types[scope].overrides, key)) { state.types[scope].overrides[key] = state.common[key]; renderFields(); }
                var input = q('#mtk-mail-' + key); var start = input.selectionStart == null ? input.value.length : input.selectionStart, end = input.selectionEnd == null ? start : input.selectionEnd;
                input.value = input.value.slice(0, start) + '{{' + token + '}}' + input.value.slice(end); set(key, input.value); input.focus();
            }); tokens.append(button);
        }); copy.append(title, tokens);
    }
    function renderScope() {
        var common = scope === 'common'; q('[data-mail-mode-wrap]').hidden = common; q('[data-mail-sample]').disabled = !common;
        if (!common) { sample = scope; q('[data-mail-sample]').value = sample; q('[data-mail-mode]').value = state.types[scope].mode; }
        q('[data-mail-source]').textContent = common ? translate("通用设置供已接入类型继承。邮件专用 Logo 不修改网站导航 Logo。") : config.types[scope].source;
        q('[data-mail-mode-hint]').textContent = common ? translate("调整后只影响继续继承这些字段的邮件。") : state.types[scope].mode === 'custom' ? translate("仅勾选的项目单独配置，其余继续继承通用设置。") : translate("独立配置已保留，切回“单独配置此类型”即可继续使用。");
        renderFields(); previewSoon();
    }
    q('[data-mail-scope]').addEventListener('change', function (e) { scope = e.target.value; renderScope(); });
    q('[data-mail-mode]').addEventListener('change', function (e) { state.types[scope].mode = e.target.value; mark(); renderScope(); });
    q('[data-mail-sample]').addEventListener('change', function (e) { sample = e.target.value; previewSoon(); });
    root.querySelectorAll('[data-mail-device]').forEach(function (b) { b.addEventListener('click', function () { root.querySelectorAll('[data-mail-device]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); q('[data-mail-stage]').dataset.device = b.dataset.mailDevice; frame.title = b.dataset.mailDevice === 'mobile' ? translate("系统邮件手机预览") : translate("系统邮件桌面预览"); setTimeout(fit, 180); }); });
    q('[data-mail-recipient]').value = config.recipient;
    q('[data-mail-form]').addEventListener('submit', async function (e) {
        e.preventDefault(); if (busy || !config.state.valid) return; busy = true; lock(); notice('');
        try { var result = await request('save', { revision: revision }); state = normalize(result.value); revision = result.revision; saved = JSON.stringify(state); mark(); notice(translate("邮件设置已保存。")); renderScope(); }
        catch (error) { notice(error.message, true); } finally { busy = false; lock(); }
    });
    q('[data-mail-test]').addEventListener('click', async function () {
        if (busy || !config.state.valid) return; var recipient = q('[data-mail-recipient]'); if (!recipient.checkValidity() || !recipient.value) { recipient.reportValidity(); notice(translate("请输入有效的测试邮箱。"), true); return; }
        busy = true; lock(); notice(translate("正在交给原邮件发送系统…"));
        try { var result = await request('test', { recipient: recipient.value }); notice(result.message); } catch (error) { notice(error.message, true); }
        finally { busy = false; lock(); }
    });
    if (!config.state.valid) q('[data-mail-test]').disabled = true;
    renderScope(); mark();
}());
