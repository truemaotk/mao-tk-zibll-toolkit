(function () {
    'use strict';
    function initialize() {
        var settings = document.getElementById('mao-tk-top-info-settings');
        var form = document.getElementById('mao-tk-top-info-preview-form');
        var frame = document.getElementById('mao-tk-top-info-preview');
        if (!settings || !form || !frame) { return; }
        var note = document.getElementById('mao-tk-preview-state');
        var dirty = document.getElementById('mao-tk-dirty-state');
        var postId = document.getElementById('mao-tk-preview-post');
        var search = document.getElementById('mao-tk-preview-search');
        var results = document.getElementById('mao-tk-preview-results');
        var selection = document.getElementById('mao-tk-preview-selection');
        var autoTimer, searchTimer, searchAbort, searchVersion = 0, items = [], active = -1;
        var pickerStates = new WeakMap();
        var previewWatch, previewWatchTimer;
        var viewport = document.getElementById('mao-tk-preview-viewport');
        var scroll = document.getElementById('mao-tk-preview-scroll');
        function fit() {
            if (!viewport || !scroll) { return; }
            var device = frame.getAttribute('data-device');
            var width = device === 'mobile' ? 390 : (device === 'tablet' ? 900 : 1280);
            var height = device === 'mobile' ? 820 : 780;
            var scale = Math.min(1, Math.max(1, scroll.clientWidth - 32) / width);
            viewport.style.width = (width * scale) + 'px';
            viewport.style.height = (height * scale) + 'px';
            frame.style.transform = 'scale(' + scale + ')';
        }
        function hex6(value) {
            if (!/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) { return null; }
            return value.length === 4 ? '#' + value.slice(1).split('').map(function (char) { return char + char; }).join('') : value;
        }
        function colors() {
            var values = {};
            settings.querySelectorAll('[data-color-field]').forEach(function (field) {
                var key = field.getAttribute('data-color-field');
                var hex = hex6(field.querySelector('[data-color-value]').value.trim());
                var opacity = Number(field.querySelector('[data-color-opacity]').value);
                if (hex && Number.isFinite(opacity) && opacity >= 0 && opacity <= 100) { values[key] = { hex: hex, alpha: opacity / 100 }; }
            });
            return values;
        }
        function toHSV(hex, previousHue) {
            var rgb = [1, 3, 5].map(function (start) { return parseInt(hex.slice(start, start + 2), 16) / 255; });
            var max = Math.max.apply(null, rgb), min = Math.min.apply(null, rgb), delta = max - min, hue = previousHue || 0;
            if (delta) {
                if (max === rgb[0]) { hue = 60 * (((rgb[1] - rgb[2]) / delta) % 6); }
                else if (max === rgb[1]) { hue = 60 * ((rgb[2] - rgb[0]) / delta + 2); }
                else { hue = 60 * ((rgb[0] - rgb[1]) / delta + 4); }
            }
            return { h: (hue + 360) % 360, s: max ? delta / max : 0, v: max, locked: false, pointer: null };
        }
        function fromHSV(state) {
            var h = (state.h % 360) / 60, chroma = state.v * state.s;
            var x = chroma * (1 - Math.abs(h % 2 - 1)), m = state.v - chroma;
            var rgb = [[chroma, x, 0], [x, chroma, 0], [0, chroma, x], [0, x, chroma], [x, 0, chroma], [chroma, 0, x]][Math.floor(h)];
            return '#' + rgb.map(function (channel) { return Math.round((channel + m) * 255).toString(16).padStart(2, '0'); }).join('');
        }
        function syncField(field, source) {
            var value = field.querySelector('[data-color-value]');
            var hex = hex6(value.value.trim());
            if (hex) {
                var state = pickerStates.get(field);
                if (!state || (!state.locked && state.hex !== hex.toLowerCase())) { state = toHSV(hex, state && state.h); pickerStates.set(field, state); }
                state.hex = hex.toLowerCase();
                field.style.setProperty('--mao-tk-picker-color', hex);
                field.style.setProperty('--mao-tk-picker-hue', String(state.h));
                var handle = field.querySelector('[data-color-handle]');
                handle.style.left = (state.s * 100) + '%'; handle.style.top = ((1 - state.v) * 100) + '%';
                field.querySelector('[data-color-hue]').value = String(Math.min(359, Math.round(state.h)));
                var label = 'H ' + Math.round(state.h) + '° · S ' + Math.round(state.s * 100) + '% · V ' + Math.round(state.v * 100) + '%';
                field.querySelector('[data-color-hsv]').textContent = label;
                field.querySelector('[data-color-sv]').setAttribute('aria-label', field.querySelector('legend').textContent + '色板，' + label + '，' + hex);
            }
            field.querySelector('[data-color-output]').textContent = field.querySelector('[data-color-opacity]').value + '%';
            field.querySelectorAll('[data-color-swatch]').forEach(function (button) {
                button.setAttribute('aria-pressed', String(Boolean(hex && hex.toLowerCase() === button.getAttribute('data-color-swatch'))));
            });
        }
        function spacingNumber(value) {
            return /^\d{1,3}$/.test(value) && Number(value) <= 200 ? Number(value) : null;
        }
        function syncSpacing(control, source) {
            var value = control.querySelector('[data-spacing-value]');
            var range = control.querySelector('[data-spacing-range]');
            if (source === range) { value.value = range.value; }
            var number = spacingNumber(value.value);
            value.setCustomValidity(number === null ? '请输入 0—200 的整数。' : '');
            value.setAttribute('aria-invalid', String(number === null));
            if (number !== null) { value.value = String(number); range.value = String(number); }
        }
        function applyLiveSettings() {
            var values = colors();
            try {
                var banner = frame.contentDocument && frame.contentDocument.querySelector('.maotk-top-info');
                if (!banner) { return; }
                function cssColor(color) {
                    var rgb = [1, 3, 5].map(function (start) { return parseInt(color.hex.slice(start, start + 2), 16); });
                    return color.alpha === 1 ? color.hex : 'rgba(' + rgb.join(',') + ',' + color.alpha + ')';
                }
                var colorsValid = values.bg_color && values.text_color && values.icon_color;
                if (colorsValid) {
                    banner.style.setProperty('--maotk-top-info-bg', values.bg_color.hex);
                    banner.style.setProperty('--maotk-top-info-bg-opacity', String(values.bg_color.alpha));
                    banner.style.setProperty('--maotk-top-info-text', cssColor(values.text_color));
                    banner.style.setProperty('--maotk-top-info-icon', cssColor(values.icon_color));
                }
                var spacingValid = true;
                settings.querySelectorAll('[data-spacing-key]').forEach(function (control) {
                    var number = spacingNumber(control.querySelector('[data-spacing-value]').value);
                    if (number === null) { spacingValid = false; return; }
                    banner.style.setProperty('--maotk-top-info-' + control.getAttribute('data-spacing-key').replace(/_/g, '-'), number + 'px');
                });
                if (note) { note.textContent = !colorsValid ? '填写有效的十六进制颜色后，预览会继续自动更新。' : (!spacingValid ? '留白请输入 0—200 的整数；无效项保留上一次有效预览。' : '预览已实时更新，正式页面未改变。'); }
            } catch (error) { /* Same-origin preview may be navigating or unavailable. */ }
        }
        function refresh() {
            window.clearTimeout(autoTimer);
            if (settings.checkValidity() && form.requestSubmit) { form.requestSubmit(); }
        }
        function stopPreviewWatch() { window.cancelAnimationFrame(previewWatch); window.clearTimeout(previewWatchTimer); }
        function watchPreview() {
            stopPreviewWatch();
            var previous = frame.contentDocument;
            function inspect() {
                try {
                    var doc = frame.contentDocument;
                    var banner = doc && doc !== previous && doc.querySelector('.maotk-top-info');
                    if (banner && banner.querySelector('.maotk-top-info__breadcrumb-slot')) {
                        applyLiveSettings();
                        if (window.MaoTKTopInfoReveal) { window.MaoTKTopInfoReveal.start(banner); }
                        stopPreviewWatch(); return;
                    }
                } catch (error) { /* A navigation may temporarily replace the document. */ }
                previewWatch = window.requestAnimationFrame(inspect);
            }
            previewWatch = window.requestAnimationFrame(inspect);
            previewWatchTimer = window.setTimeout(stopPreviewWatch, 3000);
        }
        settings.querySelectorAll('[data-color-field]').forEach(function (field) {
            syncField(field);
            var toggle = field.querySelector('[data-color-toggle]');
            var panel = field.querySelector('[data-color-panel]');
            var board = field.querySelector('[data-color-sv]');
            var hue = field.querySelector('[data-color-hue]');
            function commit() {
                var state = pickerStates.get(field);
                var input = field.querySelector('[data-color-value]');
                state.locked = true;
                input.value = fromHSV(state);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                state.locked = false;
            }
            toggle.addEventListener('click', function () {
                var opening = panel.hidden;
                settings.querySelectorAll('[data-color-field]').forEach(function (other) {
                    other.querySelector('[data-color-panel]').hidden = true;
                    other.querySelector('[data-color-toggle]').setAttribute('aria-expanded', 'false');
                });
                panel.hidden = !opening; toggle.setAttribute('aria-expanded', String(opening));
            });
            function drag(event) {
                var rect = board.getBoundingClientRect(), state = pickerStates.get(field);
                if (!rect.width || !rect.height) { return; }
                state.s = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                state.v = 1 - Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
                commit();
            }
            board.addEventListener('pointerdown', function (event) {
                if (event.button !== 0) { return; }
                event.preventDefault(); board.focus({ preventScroll: true });
                pickerStates.get(field).pointer = event.pointerId;
                board.setPointerCapture(event.pointerId); drag(event);
            });
            board.addEventListener('pointermove', function (event) { if (pickerStates.get(field).pointer === event.pointerId) { drag(event); } });
            function stopDrag(event) {
                pickerStates.get(field).pointer = null;
                if (board.hasPointerCapture(event.pointerId)) { board.releasePointerCapture(event.pointerId); }
            }
            board.addEventListener('pointerup', stopDrag); board.addEventListener('pointercancel', stopDrag);
            board.addEventListener('lostpointercapture', function () { pickerStates.get(field).pointer = null; });
            board.addEventListener('keydown', function (event) {
                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) { return; }
                event.preventDefault(); var state = pickerStates.get(field), step = event.shiftKey ? 0.1 : 0.01;
                if (event.key === 'ArrowLeft') { state.s = Math.max(0, state.s - step); }
                if (event.key === 'ArrowRight') { state.s = Math.min(1, state.s + step); }
                if (event.key === 'ArrowUp') { state.v = Math.min(1, state.v + step); }
                if (event.key === 'ArrowDown') { state.v = Math.max(0, state.v - step); }
                commit();
            });
            hue.addEventListener('input', function (event) { event.stopPropagation(); pickerStates.get(field).h = Number(hue.value); commit(); });
            field.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
            });
            field.querySelectorAll('[data-color-swatch]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var input = field.querySelector('[data-color-value]');
                    input.value = button.getAttribute('data-color-swatch');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
        });
        settings.querySelectorAll('[data-spacing-key]').forEach(function (control) { syncSpacing(control); });
        var spacingReset = settings.querySelector('[data-spacing-reset]');
        if (spacingReset) {
            spacingReset.addEventListener('click', function () {
                settings.querySelectorAll('[data-spacing-key]').forEach(function (control) {
                    control.querySelector('[data-spacing-value]').value = control.querySelector('[data-spacing-value]').getAttribute('data-spacing-default');
                    syncSpacing(control);
                });
                var value = settings.querySelector('[data-spacing-value]');
                if (value) { value.dispatchEvent(new Event('input', { bubbles: true })); }
            });
        }
        settings.addEventListener('input', function (event) {
            if (dirty) { dirty.textContent = '有未保存的修改；实时预览不会发布修改。'; }
            var field = event.target.closest('[data-color-field]');
            var spacing = event.target.closest('[data-spacing-key]');
            if (field) { syncField(field, event.target); applyLiveSettings(); }
            else if (spacing) { syncSpacing(spacing, event.target); applyLiveSettings(); }
            else { window.clearTimeout(autoTimer); autoTimer = window.setTimeout(refresh, 450); }
        });
        form.addEventListener('submit', function (event) {
            window.clearTimeout(autoTimer);
            if (!settings.reportValidity()) { event.preventDefault(); return; }
            form.querySelectorAll('[data-preview-payload]').forEach(function (input) { input.remove(); });
            function append(name, value) {
                var input = document.createElement('input');
                input.type = 'hidden'; input.name = name; input.value = value;
                input.setAttribute('data-preview-payload', ''); form.appendChild(input);
            }
            // Copy values into a separate POST form. Never submit or mutate the save form.
            new FormData(settings).forEach(function (value, name) {
                if (name.indexOf('top_info[') === 0) { append(name, value); }
            });
            append('action', form.getAttribute('data-preview-action'));
            append('_wpnonce', form.getAttribute('data-preview-nonce'));
            if (note) { note.textContent = '正在加载预览；正式设置未改变。'; }
            watchPreview();
        });
        form.querySelectorAll('[data-preview-device]').forEach(function (button) {
            button.addEventListener('click', function () {
                var device = button.getAttribute('data-preview-device');
                frame.setAttribute('data-device', ['desktop', 'tablet', 'mobile'].indexOf(device) !== -1 ? device : 'desktop');
                form.querySelectorAll('[data-preview-device]').forEach(function (item) { item.setAttribute('aria-pressed', String(item === button)); });
                fit();
            });
        });
        function closeResults() {
            if (!results || !search) { return; }
            window.clearTimeout(searchTimer); searchVersion++;
            if (searchAbort) { searchAbort.abort(); }
            search.removeAttribute('aria-busy');
            results.hidden = true; search.setAttribute('aria-expanded', 'false'); search.removeAttribute('aria-activedescendant');
        }
        function activate(index) {
            active = index;
            results.querySelectorAll('[role="option"]').forEach(function (option, position) { option.setAttribute('aria-selected', String(position === index)); });
            var selected = document.getElementById('mao-tk-preview-option-' + index);
            if (selected) { search.setAttribute('aria-activedescendant', selected.id); selected.scrollIntoView({ block: 'nearest' }); }
        }
        function choose(item) {
            if (searchAbort) { searchAbort.abort(); }
            window.clearTimeout(searchTimer); searchVersion++;
            search.removeAttribute('aria-busy');
            postId.value = String(item.id);
            search.value = item.id ? item.title : '';
            if (selection) { selection.textContent = item.id ? '当前：' + item.type + ' #' + item.id + ' · ' + item.title : '当前：样例文章（演示数据）'; }
            closeResults(); refresh();
        }
        async function lookup() {
            if (!search || !results) { return; }
            if (searchAbort) { searchAbort.abort(); }
            searchAbort = new AbortController();
            var version = ++searchVersion;
            var payload = new FormData();
            payload.set('action', form.getAttribute('data-search-action'));
            payload.set('_wpnonce', form.getAttribute('data-search-nonce'));
            payload.set('query', search.value.trim());
            search.setAttribute('aria-busy', 'true');
            try {
                // Named form controls can shadow form.action after a preview POST.
                var response = await fetch(form.getAttribute('action'), { method: 'POST', body: payload, credentials: 'same-origin', signal: searchAbort.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) { throw new Error('Search denied'); }
                var data = await response.json();
                if (version !== searchVersion || document.activeElement !== search) { return; }
                if (!data.success || !data.data || !Array.isArray(data.data.items)) { throw new Error('Invalid search response'); }
                items = data.data.items;
                results.textContent = '';
                items.forEach(function (item, index) {
                    var option = document.createElement('li');
                    option.id = 'mao-tk-preview-option-' + index; option.setAttribute('role', 'option');
                    var title = document.createElement('span'); title.textContent = item.title;
                    var meta = document.createElement('small'); meta.textContent = item.type + ' #' + item.id + ' · ' + item.status;
                    option.appendChild(title); option.appendChild(meta);
                    option.addEventListener('mousedown', function (event) { event.preventDefault(); });
                    option.addEventListener('click', function () { choose(item); });
                    results.appendChild(option);
                });
                if (!items.length) { var empty = document.createElement('li'); empty.setAttribute('role', 'presentation'); empty.textContent = '没有匹配内容，请尝试 ID 或更具体的标题。'; results.appendChild(empty); }
                results.hidden = false; search.setAttribute('aria-expanded', 'true'); activate(items.length ? 0 : -1);
                if (note && data.data.limited) { note.textContent = '最多显示 20 项，请继续输入以缩小范围。'; }
            } catch (error) {
                if (error.name !== 'AbortError' && version === searchVersion && note) { note.textContent = '搜索暂不可用，请刷新页面后重试。'; closeResults(); }
            } finally { if (version === searchVersion) { search.removeAttribute('aria-busy'); } }
        }
        if (search && results) {
            search.addEventListener('input', function () { window.clearTimeout(searchTimer); searchVersion++; if (searchAbort) { searchAbort.abort(); } searchTimer = window.setTimeout(lookup, 250); });
            search.addEventListener('focus', lookup);
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { closeResults(); return; }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (results.hidden) { lookup(); }
                    else if (items.length) { activate((active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length); }
                }
                if (event.key === 'Enter') { event.preventDefault(); if (!results.hidden && items[active]) { choose(items[active]); } else { lookup(); } }
            });
            document.addEventListener('click', function (event) { if (!search.parentNode.contains(event.target)) { closeResults(); } });
            search.addEventListener('blur', function () { window.setTimeout(function () { if (document.activeElement !== search) { closeResults(); } }, 150); });
        }
        var sample = document.getElementById('mao-tk-preview-sample');
        if (sample) { sample.addEventListener('click', function () { choose({ id: 0 }); }); }
        frame.addEventListener('load', function () {
            stopPreviewWatch();
            var banner = frame.contentDocument && frame.contentDocument.querySelector('.maotk-top-info');
            if (banner) {
                applyLiveSettings();
                if (window.MaoTKTopInfoReveal) { window.MaoTKTopInfoReveal.start(banner, { late: true }); }
            }
            else if (note) { note.textContent = '预览未能加载，请查看画布提示或刷新页面重试。'; }
        });
        if (window.ResizeObserver && scroll) { new ResizeObserver(fit).observe(scroll); }
        window.addEventListener('resize', fit, { passive: true });
        if (window.matchMedia && window.matchMedia('(max-width: 782px)').matches) {
            var mobileButton = form.querySelector('[data-preview-device="mobile"]');
            if (mobileButton) { mobileButton.click(); }
        }
        fit();
        // Automatic first preview uses the same authenticated, non-persistent POST endpoint.
        if (form.requestSubmit) { form.requestSubmit(); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initialize, { once: true }); }
    else { initialize(); }
}());
