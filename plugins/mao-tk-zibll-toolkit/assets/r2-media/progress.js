(() => {
    const panel = document.querySelector('[data-mtk-r2-job]');
    if (!panel || panel.dataset.active !== '1') return;

    const status = panel.querySelector('[data-mtk-r2-progress]');
    const message = panel.querySelector('[data-mtk-r2-poll-message]');
    const starts = panel.querySelectorAll('button[value="sync"], button[value="repair"]');
    const pause = panel.querySelector('button[value="pause"]');
    const resume = panel.querySelector('button[value="resume"]');
    const job = panel.querySelector('input[name="job"]');
    let timer;
    let pending = false;
    let active = true;
    let last = status.innerHTML.trim();

    async function poll() {
        if (pending || !active || document.hidden) return;
        pending = true;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 10000);
        try {
            const body = new URLSearchParams({ action: panel.dataset.action, nonce: panel.dataset.nonce });
            const response = await fetch(panel.dataset.ajax, { method: 'POST', body, credentials: 'same-origin', signal: controller.signal });
            if (response.status === 403) {
                active = false;
                message.textContent = '请重新加载后台页面以继续查看进度。';
                return;
            }
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error();
            const html = result.data.html.trim();
            if (html !== last) {
                status.innerHTML = html;
                last = html;
            }
            const { ready, polling, paused, pausing } = result.data;
            active = polling;
            job.value = result.data.job;
            starts.forEach((button) => {
                button.hidden = job.value !== '';
                button.disabled = result.data.active || !ready;
            });
            pause.hidden = !job.value || (paused && !pausing);
            pause.disabled = !ready || pausing;
            pause.textContent = pausing ? '正在暂停…' : '暂停同步';
            resume.hidden = !paused || pausing;
            resume.disabled = !ready;
            message.textContent = '';
        } catch (error) {
            message.textContent = '暂时无法读取进度，将自动重试。';
        } finally {
            window.clearTimeout(timeout);
            pending = false;
            if (active && !document.hidden) timer = window.setTimeout(poll, 4000);
        }
    }

    document.addEventListener('visibilitychange', () => {
        window.clearTimeout(timer);
        if (!document.hidden) void poll();
    });
    timer = window.setTimeout(poll, 2000);
})();
