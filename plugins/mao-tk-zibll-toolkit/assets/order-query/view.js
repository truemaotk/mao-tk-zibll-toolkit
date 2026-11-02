/* Read-only order UI. Native details and payment modals remain owned by Zibll. */
(function () {
    'use strict';
    function init() {
        document.querySelectorAll('[data-order-query]').forEach(function (form) {
            if (form.dataset.orderBound) return;
            form.dataset.orderBound = '1';
            var root = form.closest('.mao-tk-oq__panel'), results = root.querySelector('[data-order-results]'), feedback = root.querySelector('[data-order-feedback]');
            var busy = false, lastKeyword = '';
            async function run(mode, page) {
                if (busy) return;
                busy = true;
                var controls = Array.from(form.querySelectorAll('button')).concat(Array.from(results.querySelectorAll('button')));
                controls.forEach(function (b) { b.disabled = true; });
                results.setAttribute('aria-busy', 'true'); feedback.textContent = '正在查询…'; feedback.dataset.state = 'loading';
                var loadingTicket = window.MaoTKLoadingUI && window.MaoTKLoadingUI.begin(results, 'list');
                var body = new FormData(form), controller = new AbortController(), timer = setTimeout(function () { controller.abort(); }, 18000);
                body.set('mode', mode); body.set('result_page', String(page || 1));
                if (mode === 'search') { if (page === 1) lastKeyword = form.elements.keyword.value.trim(); body.set('keyword', lastKeyword); }
                try {
                    // A named input "action" shadows HTMLFormElement.action in real browsers.
                    var response = await fetch(form.getAttribute('action'), {method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
                    if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('服务器响应异常，请刷新页面后重试或联系网站客服。');
                    var json = await response.json();
                    if (!response.ok || !json.success) throw new Error(json.data && json.data.message ? json.data.message : '查询失败，请稍后再试。');
                    results.innerHTML = json.data.html; feedback.textContent = json.data.message; feedback.dataset.state = 'ready';
                } catch (error) { feedback.textContent = error.name === 'AbortError' ? '请求超时，请重试。' : (error.message || '网络异常，请稍后重试。'); feedback.dataset.state = 'error'; }
                finally { if (window.MaoTKLoadingUI) window.MaoTKLoadingUI.finish(loadingTicket); clearTimeout(timer); busy = false; results.setAttribute('aria-busy', 'false'); controls.forEach(function (b) { b.disabled = false; }); }
            }
            form.addEventListener('submit', function (event) { event.preventDefault(); run('search', 1); });
            form.addEventListener('click', function (event) { var b = event.target.closest('[data-order-mode]'); if (b) run(b.dataset.orderMode, 1); });
            results.addEventListener('click', function (event) { var b = event.target.closest('[data-order-page]'); if (b) run('search', Number(b.dataset.orderPage)); });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    document.addEventListener('pjax:complete', init);
}());
