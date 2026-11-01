(() => {
    'use strict';
    const root = document.querySelector('body.mao-tk-maintenance');
    if (!root || !root.dataset.deadline) return;
    const deadline = Number(root.dataset.deadline) * 1000;
    const server = Number(root.dataset.serverTime) * 1000;
    if (!Number.isFinite(deadline) || !Number.isFinite(server)) return;
    const start = performance.now();
    const wheels = root.querySelectorAll('[data-seconds-wheel]');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let previousSeconds = null;
    let rotation = 0;
    let timer;
    function tick() {
        const left = Math.max(0, Math.ceil((deadline - server - (performance.now() - start)) / 1000));
        const values = {days: Math.floor(left / 86400), hours: Math.floor(left % 86400 / 3600), minutes: Math.floor(left % 3600 / 60), seconds: left % 60};
        Object.keys(values).forEach(key => {
            const el = root.querySelector('[data-unit="' + key + '"]');
            if (el) el.textContent = String(values[key]).padStart(2, '0');
        });
        if (!reduced.matches) {
            if (previousSeconds === null) rotation = values.seconds * 6;
            else rotation -= (previousSeconds - values.seconds + 60) % 60 * 6;
            wheels.forEach(el => {
                el.style.transition = previousSeconds === null ? 'none' : 'transform 450ms cubic-bezier(.2,.65,.3,1)';
                el.style.transform = 'rotate(' + rotation + 'deg)';
            });
        }
        previousSeconds = values.seconds;
        if (left === 0) {
            const status = root.querySelector('[data-countdown-status]');
            if (status) status.textContent = '预计恢复时间已到，维护仍在进行中。';
            if (timer) clearInterval(timer);
        }
        return left;
    }
    if (tick() > 0) timer = setInterval(tick, 1000);
})();
