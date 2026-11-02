/* Local, bounded loading feedback. Existing content and network ownership stay with each module. */
(function () {
    'use strict';
    if (window.MaoTKLoadingUI) return;
    var active = new Map(), images = new WeakMap(), frames = new Map(), pendingImages = new Map();
    var scope = '.mao-tk-toolkit,.maotk-ui-kit,[data-mao-tk-showcase],#mao-tk-profile-dialog,.mao-tk-oq';
    var media = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
    function motion() {
        var mode; try { mode = window.localStorage.getItem('mao-tk-toolkit-motion-mode'); } catch (error) {}
        return mode === 'on' || mode !== 'off' && !(media && media.matches);
    }
    function finish(ticket) {
        if (!ticket || active.get(ticket.host) !== ticket) return;
        clearTimeout(ticket.timer); ticket.layer.remove(); active.delete(ticket.host);
        ticket.host.classList.remove('mao-tk-loading-host', 'mao-tk-loading-list', 'mao-tk-loading-grid');
        if (ticket.busy === null) ticket.host.removeAttribute('aria-busy'); else ticket.host.setAttribute('aria-busy', ticket.busy);
    }
    function begin(host, variant) {
        if (!host) return null;
        finish(active.get(host));
        var layer = document.createElement('div'); layer.className = 'mao-tk-loading-layer mao-tk-skeleton' + (variant === 'image' ? ' mao-tk-skeleton--image' : '');
        layer.setAttribute('aria-hidden', 'true'); layer.setAttribute('data-animate', String(motion()));
        if (variant !== 'image') { var lines = document.createElement('div'); lines.className = 'mao-tk-skeleton__lines'; for (var i = 0; i < (variant === 'grid' ? 6 : 4); i++) lines.appendChild(document.createElement('i')); layer.appendChild(lines); }
        var ticket = { host: host, layer: layer, busy: host.getAttribute('aria-busy') };
        host.classList.add('mao-tk-loading-host'); if (variant === 'list' || variant === 'grid') host.classList.add('mao-tk-loading-' + variant);
        host.setAttribute('aria-busy', 'true'); host.appendChild(layer); active.set(host, ticket);
        ticket.timer = setTimeout(function () { finish(ticket); }, 20000);
        return ticket;
    }
    function watchImage(image) {
        if (images.has(image)) { images.get(image)(); return; }
        var host = image.closest('.case-shot,.wp-block-image,.item-thumbnail,.mao-tk-profile-dialog__item');
        if (!host || !image.closest(scope)) return;
        var ticket, observer;
        function done() { finish(ticket); ticket = null; }
        function loaded() { return image.complete && image.naturalWidth > 2; }
        function start() {
            if (loaded()) { done(); return; }
            if (!ticket || !active.has(host)) {
                ticket = begin(host, 'image');
                if (host.matches('.wp-block-image')) {
                    var height = image.getBoundingClientRect().height;
                    if (height > 0) { ticket.layer.style.height = height + 'px'; ticket.layer.style.bottom = 'auto'; }
                }
            }
        }
        images.set(image, function () { if (loaded()) done(); else if (!pendingImages.has(image)) start(); });
        image.addEventListener('load', function () { if (loaded()) done(); }); image.addEventListener('error', done);
        if (loaded()) return;
        if (window.IntersectionObserver) { observer = new IntersectionObserver(function (entries) { if (entries.some(function (entry) { return entry.isIntersecting; })) { observer.disconnect(); pendingImages.delete(image); start(); } }, { rootMargin: '80px' }); pendingImages.set(image, observer); observer.observe(host); }
        else start();
    }
    function watchFrame(frame) {
        if (frames.has(frame) || !frame.closest('.mao-tk-toolkit')) return;
        var host = frame.parentElement, ticket;
        if (!host) return;
        function position() {
            if (!ticket) return;
            var rect = frame.getBoundingClientRect(), parent = host.getBoundingClientRect();
            ticket.layer.style.top = (rect.top - parent.top + host.scrollTop) + 'px'; ticket.layer.style.left = (rect.left - parent.left + host.scrollLeft) + 'px';
            ticket.layer.style.width = rect.width + 'px'; ticket.layer.style.height = rect.height + 'px'; ticket.layer.style.bottom = 'auto'; ticket.layer.style.right = 'auto';
        }
        function start() { ticket = begin(host, 'image'); position(); }
        function done() { finish(ticket); ticket = null; }
        var resize = window.ResizeObserver ? new ResizeObserver(position) : null;
        if (resize) resize.observe(host);
        frames.set(frame, { start: start, cleanup: function () { done(); if (resize) resize.disconnect(); } }); frame.addEventListener('load', done); frame.addEventListener('error', done);
        try { if (frame.contentDocument && frame.contentDocument.readyState === 'complete' && frame.contentDocument.body && frame.contentDocument.body.children.length) return; } catch (error) { if (document.readyState === 'complete') return; }
        start();
    }
    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('img')) watchImage(root);
        if (root.matches && root.matches('iframe')) watchFrame(root);
        root.querySelectorAll('img').forEach(watchImage); root.querySelectorAll('.mao-tk-toolkit iframe').forEach(watchFrame);
    }
    function refreshMotion() { active.forEach(function (ticket) { ticket.layer.setAttribute('data-animate', String(motion())); }); }
    function init() {
        scan(document);
        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            var form = event.target, target = form.getAttribute && form.getAttribute('target');
            if (!target || !form.closest('.mao-tk-toolkit')) return;
            document.querySelectorAll('.mao-tk-toolkit iframe').forEach(function (frame) { if (frame.name === target && frames.has(frame)) frames.get(frame).start(); });
        });
        if (window.MutationObserver) new MutationObserver(function (records) {
            records.forEach(function (record) {
                if (record.type === 'attributes') { if (record.target.matches('img')) watchImage(record.target); else if (frames.has(record.target)) frames.get(record.target).start(); }
                else record.addedNodes.forEach(function (node) { if (node.nodeType === 1 && !node.classList.contains('mao-tk-loading-layer')) scan(node); });
            });
            active.forEach(function (ticket) { if (!ticket.host.isConnected) finish(ticket); });
            pendingImages.forEach(function (observer, image) { if (!image.isConnected) { observer.disconnect(); pendingImages.delete(image); } });
            frames.forEach(function (state, frame) { if (!frame.isConnected) { state.cleanup(); frames.delete(frame); } });
        }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'srcset'] });
    }
    if (media && media.addEventListener) media.addEventListener('change', refreshMotion);
    window.addEventListener('storage', refreshMotion);
    window.addEventListener('pageshow', function (event) { if (event.persisted) { active.forEach(finish); scan(document); } });
    window.MaoTKLoadingUI = { begin: begin, finish: finish, clear: function (host) { finish(active.get(host)); }, scan: scan };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
}());
