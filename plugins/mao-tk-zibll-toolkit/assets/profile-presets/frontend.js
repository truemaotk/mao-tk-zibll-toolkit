(() => {
    'use strict';
    if (window.maoTkProfilePresetsBound) return;
    window.maoTkProfilePresetsBound = true;
    let kind = 'avatar', page = 1, pages = 1, total = 0, selected = null, busy = false, saving = false, controller = null, opener = null, loadGeneration = 0;
    const dialog = () => document.getElementById('mao-tk-profile-dialog');
    const el = name => dialog()?.querySelector('[data-mao-tk-profile-' + name + ']');
    function feedback(message) { if (el('feedback')) el('feedback').textContent = message; }
    function controls() {
        const d = dialog(); if (!d) return;
        d.querySelectorAll('[data-mao-tk-profile-kind]').forEach(b => { b.disabled = busy; b.setAttribute('aria-selected', String(b.dataset.maoTkProfileKind === kind)); b.tabIndex = b.dataset.maoTkProfileKind === kind ? 0 : -1; });
        el('prev').disabled = busy || page <= 1;
        el('next').disabled = busy || page >= pages;
        el('random').disabled = busy || total < 1;
        el('save').disabled = busy || !selected;
        el('grid').setAttribute('aria-busy', String(busy));
    }
    async function request(operation, extra = {}) {
        const d = dialog(); if (!d) throw new Error('选择窗口不可用，请刷新页面。');
        const body = new URLSearchParams({action: d.dataset.actionName, nonce: d.dataset.nonce, operation, kind, ...extra});
        const activeController = controller;
        const timeout = setTimeout(() => activeController?.abort(), 20000);
        try {
            const response = await fetch(d.dataset.endpoint, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body,signal:activeController?.signal});
            let result;
            try { result = await response.json(); } catch { throw new Error('请求未完成，请刷新页面后重试。'); }
            if (!response.ok || !result.success) throw new Error(result.data?.message || '请求失败，请稍后重试。');
            return result.data;
        } finally { clearTimeout(timeout); }
    }
    function choose(item) {
        selected = item;
        el('grid').querySelectorAll('[data-preset-id]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.presetId === item.id)));
        feedback('已选中，点击“确认使用”后保存。'); controls();
    }
    async function load(targetPage = 1, randomIndex = null) {
        const generation = ++loadGeneration;
        controller?.abort(); controller = new AbortController(); busy = true; controls(); feedback('');
        el('status').textContent = '正在加载预设图片…';
        el('grid').replaceChildren();
        const loadingTicket = window.MaoTKLoadingUI?.begin(el('grid'), 'grid');
        try {
            const result = await request('list', {page:String(targetPage)});
            if (generation !== loadGeneration) return;
            page = result.page; pages = result.pages; total = result.total;
            window.MaoTKLoadingUI?.finish(loadingTicket);
            el('grid').dataset.kind = kind;
            result.items.forEach(item => {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'mao-tk-profile-dialog__item'; b.dataset.presetId = item.id;
                b.setAttribute('aria-label', item.name); b.setAttribute('aria-pressed', String(selected?.id === item.id));
                const img = document.createElement('img'); img.src = item.thumb; img.alt = ''; img.width = item.width; img.height = item.height; img.loading = 'lazy'; img.decoding = 'async';
                b.append(img); b.addEventListener('click', () => { if (!busy) choose(item); }); el('grid').append(b);
            });
            el('status').textContent = total ? '共 ' + total + ' 张，选择后再确认。' : '暂无可用预设，请联系管理员添加图片。';
            el('page').textContent = page + ' / ' + pages;
            if (randomIndex !== null && result.items.length) choose(result.items[randomIndex % 24] || result.items[0]);
        } catch (error) {
            if (generation !== loadGeneration) return;
            el('status').textContent = error.name === 'AbortError' ? '加载超时，请关闭后重试。' : error.message; el('page').textContent = ''; total = 0;
        } finally { window.MaoTKLoadingUI?.finish(loadingTicket); if (generation === loadGeneration) { busy = false; controls(); } }
    }
    function close() {
        if (saving) return;
        ++loadGeneration; controller?.abort(); busy = false; window.MaoTKLoadingUI?.clear(el('grid')); if (el('grid')) el('grid').replaceChildren(); controls(); dialog()?.close(); opener?.focus();
    }
    function bind(d) {
        if (d.dataset.maoTkProfileReady) return;
        d.dataset.maoTkProfileReady = 'true';
        d.querySelectorAll('[data-mao-tk-profile-close]').forEach(b => b.addEventListener('click', close));
        d.addEventListener('cancel', e => { e.preventDefault(); close(); });
        d.addEventListener('click', e => { const r = d.getBoundingClientRect(); if (e.target === d && (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom)) close(); });
        d.querySelectorAll('[data-mao-tk-profile-kind]').forEach(b => {
            b.addEventListener('click', () => { kind = b.dataset.maoTkProfileKind; selected = null; load(1); });
            b.addEventListener('keydown', e => {
                if (!['ArrowLeft','ArrowRight','Home','End'].includes(e.key)) return;
                e.preventDefault(); const tabs = [...d.querySelectorAll('[data-mao-tk-profile-kind]')]; const i = tabs.indexOf(b);
                const next = e.key === 'Home' ? tabs[0] : e.key === 'End' ? tabs[tabs.length - 1] : tabs[(i + (e.key === 'ArrowLeft' ? -1 : 1) + tabs.length) % tabs.length];
                next.click(); next.focus();
            });
        });
        el('prev').addEventListener('click', () => load(page - 1)); el('next').addEventListener('click', () => load(page + 1));
        el('random').addEventListener('click', () => { const n = Math.floor(Math.random() * total); load(Math.floor(n / 24) + 1, n); });
        el('save').addEventListener('click', async () => {
            if (busy || !selected) return;
            saving = true; busy = true; controller = new AbortController(); controls(); feedback('正在保存…');
            try {
                const result = await request('save', {preset_id:selected.id});
                const selector = result.kind === 'avatar' ? '.avatar-id-' : '.user-cover-id-';
                document.querySelectorAll('img' + selector + result.user_id).forEach(img => { img.src = result.url; if (img.hasAttribute('data-src')) img.setAttribute('data-src', result.url); img.removeAttribute('srcset'); img.removeAttribute('data-srcset'); });
                feedback(result.message);
            } catch (error) { feedback(error.name === 'AbortError' ? '响应超时，请刷新后核对当前图片。' : error.message); }
            finally { saving = false; busy = false; controls(); }
        });
    }
    document.addEventListener('click', e => {
        const button = e.target.closest('[data-mao-tk-profile-open]'); if (!button) return;
        const d = dialog(); if (!d || typeof d.showModal !== 'function') return;
        e.preventDefault(); opener = button; kind = button.dataset.maoTkProfileOpen;
        if (!d.querySelector('[data-mao-tk-profile-kind="' + kind + '"]')) return;
        bind(d); selected = null; page = 1; total = 0;
        if (!d.open) d.showModal(); load(1);
    });
})();
