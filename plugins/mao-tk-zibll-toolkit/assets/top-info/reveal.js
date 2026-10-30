/* Same-origin, shared visual readiness only. Never changes content or geometry. */
(function () {
    'use strict';
    if (!window.Promise || !window.WeakMap) { return; }
    var states = new WeakMap();
    function start(root, options) {
        if (!root) { return Promise.resolve(); }
        if (states.has(root)) { return states.get(root); }
        var view = root.ownerDocument.defaultView;
        var reduced = view && view.matchMedia && view.matchMedia('(prefers-reduced-motion: reduce)').matches;
        // Flush existing typography; skeleton uses exactly the real content's boxes.
        root.getBoundingClientRect();
        var images = Array.from(root.querySelectorAll('.maotk-top-info__background-image img'));
        var fonts = root.ownerDocument.fonts;
        var late = options && options.late;
        var pending = !reduced && !late && (images.some(function (image) { return !image.complete; }) || (fonts && fonts.status === 'loading'));
        root.setAttribute('data-maotk-reveal-animate', String(!reduced && !late));
        function ready() {
            root.setAttribute('data-maotk-reveal-state', 'ready');
            root.removeAttribute('aria-busy');
        }
        if (!pending) { ready(); var done = Promise.resolve(); states.set(root, done); return done; }
        root.setAttribute('data-maotk-reveal-state', 'loading');
        root.setAttribute('aria-busy', 'true');
        var cleanups = [], timer;
        var imageTasks = images.map(function (image) {
            if (image.complete) { return Promise.resolve(); }
            return new Promise(function (resolve) {
                function finish() { image.removeEventListener('load', finish); image.removeEventListener('error', finish); resolve(); }
                image.addEventListener('load', finish); image.addEventListener('error', finish); cleanups.push(finish);
                if (image.complete) { finish(); }
            });
        });
        if (fonts && fonts.status === 'loading') { imageTasks.push(fonts.ready.catch(function () {})); }
        var result = Promise.race([
            Promise.all(imageTasks),
            new Promise(function (resolve) { timer = window.setTimeout(resolve, 1800); })
        ]).then(function () {
            window.clearTimeout(timer); cleanups.forEach(function (cleanup) { cleanup(); }); ready();
        });
        states.set(root, result);
        return result;
    }
    window.MaoTKTopInfoReveal = Object.freeze({ start: start });
}());
