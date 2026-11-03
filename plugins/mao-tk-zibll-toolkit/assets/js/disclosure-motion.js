/* Shared, scoped disclosure motion. No setting, account or business data writes. */
(function () {
    'use strict';
    if (window.MaoTKDisclosureMotion) return;
    var panels = new Map();
    var details = new Map();
    var media = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var duration = 380;
    var easing = 'cubic-bezier(.32,0,.18,1)';
    function enabled() { return !(media && media.matches); }
    function cancel(state) {
        if (state.animation) { state.animation.cancel(); state.animation = null; }
    }
    function finish(state) {
        state.panel.style.height = state.open ? 'auto' : '0px';
        var callback = state.callback; state.callback = null;
        cancel(state);
        if (callback) callback();
    }
    function animate(state, open, options) {
        options = options || {};
        var from = state.panel.hidden ? 0 : state.panel.getBoundingClientRect().height;
        cancel(state);
        state.open = open;
        state.callback = options.onFinish || null;
        state.panel.hidden = false;
        state.panel.setAttribute('data-mao-tk-motion-panel', '1');
        state.panel.style.height = from + 'px';
        var to = open ? state.content.getBoundingClientRect().height : 0;
        state.target = to;
        if (options.instant || !enabled() || !state.panel.animate || Math.abs(from - to) < .5) { finish(state); return; }
        var time = options.duration || duration;
        state.deadline = Date.now() + time;
        var animation = state.panel.animate([{ height: from + 'px' }, { height: to + 'px' }], { duration: time, easing: easing, fill: 'both' });
        state.animation = animation;
        animation.finished.then(function () {
            if (state.animation === animation) finish(state);
        }).catch(function () {}); // Interrupted/reversed animations intentionally reject.
    }
    function set(panel, content, open, options) {
        if (!panel || !content) return;
        var state = panels.get(panel);
        if (!state) {
            state = { panel: panel, content: content, animation: null, open: open, originalHeight: panel.style.height };
            panels.set(panel, state);
            if (typeof window.ResizeObserver === 'function') {
                state.observer = new window.ResizeObserver(function () {
                    if (panel.isConnected === false) { release(panel); return; }
                    if (state.animation && state.open && Math.abs(content.getBoundingClientRect().height - state.target) > 1) {
                        animate(state, true, { duration: Math.max(80, state.deadline - Date.now()), onFinish: state.callback });
                    }
                });
                state.observer.observe(content);
            }
        }
        animate(state, !!open, options);
    }
    function release(panel) {
        var state = panels.get(panel);
        if (!state) return;
        cancel(state);
        if (state.observer) state.observer.disconnect();
        panel.style.height = state.originalHeight;
        panel.removeAttribute('data-mao-tk-motion-panel');
        panels.delete(panel);
    }
    function destroy(container) {
        panels.forEach(function (state, panel) { if (container === panel || container.contains(panel)) release(panel); });
        details.forEach(function (state, element) {
            if (container === element || container.contains(element)) {
                state.summary.removeEventListener('click', state.click);
                element.removeEventListener('toggle', state.toggle);
                state.panel.removeEventListener('invalid', state.invalid, true);
                details.delete(element);
            }
        });
    }
    function rightArrow(trigger) {
        var svg = trigger && trigger.querySelector('svg');
        if (!svg || svg.getAttribute('data-mao-tk-right-arrow') === '1') return;
        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'm9 6 6 6-6 6');
        svg.replaceChildren(path);
        svg.setAttribute('data-mao-tk-right-arrow', '1');
        svg.setAttribute('width', '18'); svg.setAttribute('height', '18');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none');
    }
    function bindDetails(container) {
        if (!container || !container.querySelectorAll) return;
        container.querySelectorAll('.setting-section, .category-editor').forEach(function (element) {
            if (details.has(element)) return;
            var summary = element.querySelector('summary');
            if (!summary) return;
            var panel = element.querySelector('[data-mao-tk-details-panel]');
            if (!panel) {
                panel = document.createElement('div'); panel.setAttribute('data-mao-tk-details-panel', '1');
                var content = document.createElement('div'); content.className = 'mao-tk-details-content';
                Array.from(element.childNodes).forEach(function (node) { if (node !== summary) content.appendChild(node); });
                panel.appendChild(content); element.appendChild(panel);
            }
            var state = { summary: summary, panel: panel, expected: element.open, target: element.open };
            function change(open, instant) {
                state.target = open;
                summary.setAttribute('aria-expanded', String(open));
                panel.inert = !open;
                panel.setAttribute('aria-hidden', String(!open));
                if (open) { state.expected = true; element.open = true; }
                set(panel, panel.firstElementChild, open, { instant: instant, onFinish: function () {
                    state.expected = open; element.open = open;
                } });
            }
            state.click = function (event) { event.preventDefault(); change(!state.target, false); };
            state.toggle = function () { if (element.open !== state.expected) change(element.open, true); };
            state.invalid = function () { change(true, true); };
            state.change = change;
            summary.addEventListener('click', state.click);
            element.addEventListener('toggle', state.toggle);
            panel.addEventListener('invalid', state.invalid, true);
            details.set(element, state);
            change(element.open, true);
        });
    }
    function setDetails(element, open, instant) {
        var state = details.get(element);
        if (state) state.change(!!open, instant !== false);
        else if (element) element.open = !!open;
    }
    function applyPreference() {
        document.documentElement.setAttribute('data-mao-tk-motion', enabled() ? 'on' : 'off');
        if (!enabled()) panels.forEach(finish);
    }
    // Compatibility with older adapters; preference is now exclusively the OS setting.
    function setPreference() { applyPreference(); }
    function mountPreference() {}
    if (media && media.addEventListener) media.addEventListener('change', applyPreference);
    else if (media && media.addListener) media.addListener(applyPreference);
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { applyPreference(); panels.forEach(finish); }
    });
    window.MaoTKDisclosureMotion = { set: set, destroy: destroy, rightArrow: rightArrow, bindDetails: bindDetails, setDetails: setDetails, mountPreference: mountPreference, preference: function () { return 'system'; }, setPreference: setPreference };
    applyPreference();
})();
