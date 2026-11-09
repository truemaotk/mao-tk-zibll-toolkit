(function () {
    'use strict';
    const translate = text => window.wp?.i18n?.__(text, 'mao-tk-zibll-toolkit') ?? text;
    var root = document.querySelector('[data-mao-tk-migration]');
    if (!root) return;
    var current = null, looping = false, wantPause = false, creating = false;
    var states = { preview: translate("待确认"), ready: translate("准备就绪"), running: translate("正在处理"), paused: translate("已暂停"), complete: translate("已完成"), expired: translate("已过期"), invalid: translate("包校验失败") };
    var plans = { add: translate("新增"), skip: translate("跳过已有"), update: translate("更新已有"), conflict: translate("存在冲突"), export: translate("导出") };
    function q(s) { return root.querySelector(s); }
    function text(s, value) { q(s).textContent = value; }
    function node(tag, value) { var el = document.createElement(tag); if (value != null) el.textContent = value; return el; }
    function notice(message, error) { var el = q('.mtk-migration-notice'); el.textContent = message; el.dataset.error = error ? 'true' : 'false'; el.hidden = !message; }
    function busy() {
        var unavailable = root.dataset.available !== '1';
        root.querySelectorAll('#mtk-export-form button[type=submit],#mtk-import-form button[type=submit]').forEach(function (b) { b.disabled = unavailable || creating || looping; b.setAttribute('aria-busy', creating ? 'true' : 'false'); });
        root.querySelectorAll('[data-history-list] button').forEach(function (b) { b.disabled = creating || looping; });
        q('[data-task-start]').disabled = unavailable || creating || looping;
        q('[data-task-refresh]').disabled = creating || looping;
        q('[data-task-retry]').disabled = unavailable || creating || looping;
    }
    async function request(operation, fields, form) {
        var body = form ? new FormData(form) : new FormData();
        body.set('action', root.dataset.action); body.set('nonce', root.dataset.nonce); body.set('operation', operation);
        Object.keys(fields || {}).forEach(function (key) { body.set(key, String(fields[key])); });
        var controller = new AbortController(); var timer = setTimeout(function () { controller.abort(); }, 35000);
        try {
            var response = await fetch(root.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: body, signal: controller.signal });
            var data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.data && data.data.message || translate("请求未完成，请刷新任务核对。"));
            return data.data;
        } finally { clearTimeout(timer); }
    }
    function render(job) {
        current = job; q('#mtk-task-panel').hidden = false;
        text('#mtk-task-title', job.type === 'import' ? translate("导入预检与执行结果") : translate("导出任务"));
        text('[data-task-description]', job.status === 'preview' ? translate("请核对下表，确认后才会写入链接和媒体库。") : translate("一条一条处理，已完成项不会重复执行。"));
        text('[data-task-state]', states[job.status] || job.status);
        text('[data-task-count]', job.done + ' / ' + job.total);
        q('progress').value = job.total ? job.done * 100 / job.total : 0;
        text('[data-task-totals]', translate("成功 ") + job.counts.success + translate(" · 跳过 ") + job.counts.skip + translate(" · 失败 ") + job.counts.failed);
        var warnings = q('[data-task-warnings]'); warnings.replaceChildren();
        job.warnings.forEach(function (w) { warnings.append(node('p', w)); });
        var tbody = q('[data-task-rows]'); tbody.replaceChildren();
        job.rows.forEach(function (r) {
            var tr = node('tr'), name = node('td'), state = node('td'), logo = node('td', r.logo), result = node('td');
            name.append(node('strong', r.name), node('small', r.url));
            state.append(node('span', r.private ? translate("私密") : translate("公开")));
            result.textContent = r.result ? r.result.message : plans[r.plan] || r.plan;
            if (r.result) result.dataset.tone = r.result.state;
            tr.append(name, state, logo, result); tbody.append(tr);
        });
        text('[data-page-label]', translate("第 ") + job.page + ' / ' + job.pages + translate(" 页 · 每页 20 条"));
        q('[data-page-prev]').disabled = job.page <= 1 || looping; q('[data-page-next]').disabled = job.page >= job.pages || looping;
        var canStart = ['preview', 'ready', 'paused', 'running'].indexOf(job.status) !== -1;
        q('[data-task-start]').hidden = !canStart || looping;
        q('[data-task-start]').disabled = root.dataset.available !== '1';
        text('[data-task-start]', job.status === 'running' || job.status === 'paused' ? translate("继续任务") : translate("确认并开始"));
        q('[data-task-pause]').hidden = !looping;
        q('[data-task-pause]').disabled = wantPause;
        q('[data-task-retry]').hidden = !job.counts.failed || ['complete', 'paused'].indexOf(job.status) === -1;
        q('[data-task-retry]').disabled = looping;
        q('[data-task-report]').hidden = !job.done || !job.report; q('[data-task-report]').href = job.report || '#';
        q('[data-task-download]').hidden = !job.download; q('[data-task-download]').href = job.download || '#';
        text('[data-task-download]', job.partial ? translate("下载成功部分 ZIP") : translate("下载完整 ZIP"));
        q('[data-task-sha]').hidden = !job.sha256; text('[data-task-sha]', 'SHA-256  ' + (job.sha256 || ''));
        if (job.partial) { warnings.append(node('p', translate("导出含失败项：当前 ZIP 仅包含成功部分。可以先重试失败项，再下载完整包。"))); }
        busy();
    }
    async function inspect(id, page) { if (creating || looping) return; render(await request('inspect', { job: id, page_number: page || 1 })); }
    async function history() {
        var list = q('[data-history-list]'); list.replaceChildren(node('p', translate("正在读取任务…")));
        var rows = await request('history'); list.replaceChildren();
        if (!rows.length) list.append(node('p', translate("还没有迁移任务。")));
        rows.forEach(function (r) {
            var row = node('div'), copy = node('div'), button = node('button', translate("查看任务")); row.className = 'mtk-migration-history-row';
            copy.append(node('strong', (r.type === 'import' ? translate("导入链接") : translate("导出链接")) + ' · ' + (states[r.status] || r.status)), node('small', r.created + ' · ' + r.done + ' / ' + r.total + translate(" 条")));
            button.type = 'button'; button.className = 'button'; button.disabled = creating || looping;
            button.addEventListener('click', function () { inspect(r.id).catch(failed); }); row.append(copy, button); list.append(row);
        });
    }
    function failed(error) { notice(error.name === 'AbortError' ? translate("请求超时，已停止自动发送；请刷新任务核对已保存进度后继续。") : error.message, true); }
    async function run() {
        if (!current || creating || looping) return;
        looping = true; wantPause = false; notice(''); render(current);
        var id = current.id;
        try {
            render(await request('start', { job: id, revision: current.revision, page_number: current.page }));
            while (current.id === id && current.status === 'running' && !wantPause) {
                render(await request('step', { job: id, revision: current.revision, page_number: current.page }));
                // Yield to the browser so progress and pause remain interactive on fast local requests.
                await new Promise(function (resolve) { setTimeout(resolve, 80); });
            }
            if (wantPause && current.status === 'running') render(await request('pause', { job: id, revision: current.revision, page_number: current.page }));
            if (current.status === 'complete') notice(current.counts.failed ? translate("任务处理结束，请检查失败项后重试。") : translate("任务已完成。"));
        } catch (error) { failed(error); }
        finally { looping = false; wantPause = false; render(current); }
    }
    async function create(operation, form) {
        if (creating || looping) return;
        creating = true; current = null; q('#mtk-task-panel').hidden = true; busy(); notice('');
        try { render(await request(operation, {}, form)); q('#mtk-task-panel').scrollIntoView({ block: 'nearest' }); }
        catch (error) { failed(error); }
        finally { creating = false; busy(); }
    }
    q('#mtk-export-form').addEventListener('submit', function (e) { e.preventDefault(); create('export', e.currentTarget); });
    q('#mtk-import-form').addEventListener('submit', function (e) { e.preventDefault(); create('upload', e.currentTarget); });
    q('[data-task-start]').addEventListener('click', run);
    q('[data-task-pause]').addEventListener('click', function () { wantPause = true; q('[data-task-pause]').disabled = true; });
    q('[data-task-retry]').addEventListener('click', async function () {
        try { render(await request('retry', { job: current.id, revision: current.revision, page_number: current.page })); notice(translate("失败项已准备好，点击“继续任务”重试。")); } catch (error) { failed(error); }
    });
    q('[data-task-refresh]').addEventListener('click', function () { if (current && !looping) inspect(current.id, current.page).catch(failed); });
    ['prev', 'next'].forEach(function (direction) { q('[data-page-' + direction + ']').addEventListener('click', function () { if (current && !looping) inspect(current.id, current.page + (direction === 'next' ? 1 : -1)).catch(failed); }); });
    q('[data-history-refresh]').addEventListener('click', function () { history().catch(failed); });
    var tabs = Array.from(root.querySelectorAll('[role=tab]'));
    function activate(tab) {
        tabs.forEach(function (t) { var selected = t === tab; t.setAttribute('aria-selected', String(selected)); t.tabIndex = selected ? 0 : -1; q('#' + t.getAttribute('aria-controls')).hidden = !selected; });
        if (tab.dataset.tab === 'history') history().catch(failed);
    }
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { activate(tab); });
        tab.addEventListener('keydown', function (e) {
            var idx = e.key === 'ArrowRight' ? (i + 1) % tabs.length : e.key === 'ArrowLeft' ? (i + tabs.length - 1) % tabs.length : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : -1;
            if (idx >= 0) { e.preventDefault(); activate(tabs[idx]); tabs[idx].focus(); }
        });
    });
    q('[data-link-search]').addEventListener('input', function (e) { var value = e.target.value.toLowerCase(); root.querySelectorAll('.mtk-migration-select-row').forEach(function (row) { row.hidden = row.textContent.toLowerCase().indexOf(value) === -1; }); });
    q('#mtk-export-form').addEventListener('change', function () { text('[data-selected-count]', translate("已选择 ") + root.querySelectorAll('input[name="ids[]"]:checked').length + translate(" 条")); });
    var moduleSwitch = root.querySelector('button[role=switch]');
    if (moduleSwitch && typeof MutationObserver === 'function') {
        new MutationObserver(function (records) {
            if (moduleSwitch.disabled || !records.some(function (r) { return r.attributeName === 'disabled'; })) return;
            request('availability').then(function (data) {
                root.dataset.available = data.active ? '1' : '0'; root.dataset.nonce = data.nonce;
                text('[data-migration-availability]', data.active ? translate("运行中 · 仅管理员后台使用，不影响前台链接显示。") : translate("未运行 · 请开启模块，并确认当前为 Zibll 或其子主题。"));
                busy(); if (current) render(current);
            }).catch(failed);
        }).observe(moduleSwitch, { attributes:true, attributeFilter:['disabled'] });
    }
    busy();
}());


