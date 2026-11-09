(function () {
    'use strict';
    const translate = text => window.wp?.i18n?.__(text, 'mao-tk-zibll-toolkit') ?? text;
    const format = (text, value) => translate(text).replace('%s', () => value);

    function initialize() {
        var root = document.getElementById('mao-tk-toolkit');
        if (!root) { return; }
        var motion = window.MaoTKDisclosureMotion;
        var disclosures = Array.from(root.querySelectorAll('[data-mao-tk-disclosure]'));
        function expand(section, open, instant) {
            var trigger = section.querySelector('.mao-tk-disclosure__trigger');
            var panel = section.querySelector('.mao-tk-disclosure__panel');
            if (!trigger || !panel) { return; }
            trigger.setAttribute('aria-expanded', String(open));
            panel.setAttribute('aria-hidden', String(!open));
            panel.inert = !open;
            section.classList.toggle('is-open', open);
            if (motion) motion.set(panel, section.querySelector('.mao-tk-disclosure__inner'), open, { instant: !!instant });
        }
        disclosures.forEach(function (section) {
            var trigger = section.querySelector('.mao-tk-disclosure__trigger');
            if (!trigger || !section.querySelector('.mao-tk-disclosure__panel')) { return; }
            if (motion) motion.rightArrow(trigger);
            expand(section, section.getAttribute('data-initial-open') === 'true', true);
            section.setAttribute('data-disclosure-ready', '1');
            trigger.addEventListener('click', function () {
                var open = trigger.getAttribute('aria-expanded') !== 'true';
                var group = section.getAttribute('data-accordion-group');
                if (open && group) {
                    disclosures.forEach(function (other) {
                        if (other !== section && other.getAttribute('data-accordion-group') === group) { expand(other, false); }
                    });
                }
                expand(section, open);
            });
        });
        // Invalid required controls must be visible before the browser focuses them.
        root.addEventListener('invalid', function (event) {
            var section = event.target.closest('[data-mao-tk-disclosure]');
            if (section) { expand(section, true, true); }
        }, true);
        var feedback = root.querySelector('[data-mao-tk-switch-feedback]');
        var feedbackTimer;
        var queue = [];
        var running = false;
        var supportsAsync = ['fetch', 'DOMParser', 'FormData', 'URLSearchParams', 'AbortController'].every(function (key) { return typeof window[key] === 'function'; });
        function hideFeedback() {
            window.clearTimeout(feedbackTimer);
            if (feedback) { feedback.hidden = true; feedback.textContent = ''; }
        }
        function showFeedback(text, state) {
            window.clearTimeout(feedbackTimer);
            if (!feedback) { return; }
            feedback.setAttribute('data-feedback-state', state);
            feedback.textContent = text;
            feedback.hidden = false;
            if (state === 'saved') { feedbackTimer = window.setTimeout(hideFeedback, 6000); }
        }
        if (feedback && feedback.getAttribute('data-feedback-state') === 'saved') {
            feedbackTimer = window.setTimeout(hideFeedback, 6000);
        }

        function field(form, name) { return form.querySelector('input[name="' + name + '"]'); }
        async function request(url, options, timeout) {
            var target = new URL(url, window.location.href);
            if (target.origin !== window.location.origin) { throw new Error('Invalid origin'); }
            var controller = new window.AbortController();
            var timer = window.setTimeout(function () { controller.abort(); }, timeout);
            try {
                var response = await window.fetch(target.href, Object.assign({ credentials: 'same-origin', cache: 'no-store', signal: controller.signal }, options));
                if (!response.ok || new URL(response.url).origin !== window.location.origin) { throw new Error('Save request rejected'); }
                return await response.text();
            } finally { window.clearTimeout(timer); }
        }
        function record(html, id) {
            var source = new window.DOMParser().parseFromString(html, 'text/html').getElementById('mao-tk-toolkit');
            if (!source) { throw new Error('Missing authenticated module page'); }
            var match = Array.from(source.querySelectorAll('[data-mao-tk-module-form]')).find(function (form) { return field(form, 'module_id') && field(form, 'module_id').value === id; });
            var button = match && match.querySelector('button[role="switch"]');
            var checked = button && button.getAttribute('aria-checked');
            if (!button || ['true', 'false'].indexOf(checked) === -1 || !field(match, '_wpnonce') || !field(match, '_wpnonce').value || !field(match, 'enabled') || field(match, 'enabled').value !== (checked === 'true' ? '0' : '1')) { throw new Error('Invalid module response'); }
            return { root: source, form: match, checked: checked === 'true', disabled: button.disabled === true, notice: source.querySelector('[data-mao-tk-switch-feedback]') };
        }
        function badge(form, source) {
            var row = form.closest('.mao-tk-toolkit__module');
            return row ? row.querySelector('.mao-tk-toolkit__badge') : source.querySelector('[data-mao-tk-runtime-state]');
        }
        function syncWelcome(source) {
            var destination = root.querySelector('[data-mao-tk-dashboard-content]');
            if (!destination) { return; }
            var previous = destination.querySelector('[data-mao-tk-welcome-output]');
            var incoming = source.querySelector('[data-mao-tk-welcome-output]');
            if (incoming && (!incoming.querySelector('strong') || !incoming.querySelector('p'))) { return; }
            if (previous) { previous.remove(); }
            if (!incoming) { return; }
            // Copy only the built-in module's text; never execute returned page scripts/HTML.
            var wrapper = document.createElement('div'); wrapper.className = 'mao-tk-toolkit__welcome'; wrapper.setAttribute('role', 'status'); wrapper.setAttribute('data-mao-tk-welcome-output', '');
            var icon = document.createElement('span'); icon.className = 'dashicons dashicons-yes-alt'; icon.setAttribute('aria-hidden', 'true');
            var body = document.createElement('div'); var title = document.createElement('strong'); var text = document.createElement('p');
            title.textContent = incoming.querySelector('strong').textContent; text.textContent = incoming.querySelector('p').textContent;
            body.appendChild(title); body.appendChild(text); wrapper.appendChild(icon); wrapper.appendChild(body); destination.appendChild(wrapper);
        }
        function synchronize(job, reply) {
            job.button.setAttribute('aria-checked', String(reply.checked));
            field(job.form, 'enabled').value = reply.checked ? '0' : '1';
            field(job.form, '_wpnonce').value = field(reply.form, '_wpnonce').value;
            var currentBadge = badge(job.form, root), incomingBadge = badge(reply.form, reply.root);
            if (currentBadge && incomingBadge) {
                var tone = incomingBadge.className.match(/mao-tk-toolkit__badge--(success|neutral|warning|error)\b/);
                currentBadge.className = 'mao-tk-toolkit__badge mao-tk-toolkit__badge--' + (tone ? tone[1] : 'error');
                currentBadge.textContent = incomingBadge.textContent;
            }
            var row = job.form.closest('.mao-tk-toolkit__module'), sourceRow = reply.form.closest('.mao-tk-toolkit__module');
            if (row && sourceRow) {
                var note = row.querySelector('.mao-tk-toolkit__module-note'), sourceNote = sourceRow.querySelector('.mao-tk-toolkit__module-note');
                if (note && sourceNote) { note.textContent = sourceNote.textContent; }
            }
            var count = root.querySelector('.mao-tk-toolkit__count'), sourceCount = reply.root.querySelector('.mao-tk-toolkit__count');
            if (count && sourceCount) { count.textContent = sourceCount.textContent; }
            if (job.id === 'welcome-note') { syncWelcome(reply.root); }
            job.confirm(reply.checked, reply.disabled);
        }
        async function persist(job) {
            var reply;
            try {
                reply = record(await request(job.form.getAttribute('action'), { method: 'POST', body: job.payload }, 15000), job.id);
                if (!reply.notice || reply.notice.getAttribute('data-feedback-state') !== 'saved') { throw new Error('Save not confirmed'); }
            } catch (error) {
                // A lost POST response may already have committed. Read back before rollback.
                try { reply = record(await request(job.form.getAttribute('data-state-url'), { method: 'GET' }, 5000), job.id); }
                catch (readError) { reply = null; }
            }
            if (reply) {
                synchronize(job, reply);
                if (reply.disabled) { showFeedback(translate("模块开关配置异常，操作已暂停；请检查配置后刷新页面。"), 'save-failed'); }
                else if (reply.checked === job.target) {
                    showFeedback(format(reply.checked ? '“%s”已开启，设置已保存。' : '“%s”已关闭，设置已保存。', job.name), 'saved');
                } else {
                    showFeedback(format('“%s”未能保存，开关已恢复为服务器确认的状态。', job.name), 'save-failed');
                }
            } else {
                job.button.setAttribute('aria-checked', String(job.previous));
                field(job.form, 'enabled').value = job.previous ? '0' : '1';
                showFeedback(format('无法确认“%s”的保存结果，已恢复显示。请刷新页面核对。', job.name), 'save-failed');
            }
        }
        async function drain() {
            if (running || !queue.length) { return; }
            running = true;
            var job = queue.shift();
            var timer = window.setTimeout(function () { showFeedback(format('正在保存“%s”的开关设置…', job.name), 'saving'); }, 450);
            try { await persist(job); }
            catch (error) {
                job.button.setAttribute('aria-checked', String(job.previous));
                field(job.form, 'enabled').value = job.previous ? '0' : '1';
                showFeedback(translate("保存结果未确认，请刷新页面核对后重试。"), 'save-failed');
            } finally {
                window.clearTimeout(timer);
                job.finish();
                running = false;
                drain();
            }
        }

        root.querySelectorAll('[data-mao-tk-module-form]').forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            if (!button) { return; }
            var initiallyDisabled = button.disabled;
            var confirmed = button.getAttribute('aria-checked') === 'true';
            var pending = false;
            function restore(event) {
                if (pending) { return; }
                var wasBusy = form.getAttribute('aria-busy') === 'true';
                form.removeAttribute('aria-busy');
                button.removeAttribute('aria-busy');
                button.disabled = initiallyDisabled;
                button.setAttribute('aria-checked', String(confirmed));
                if (wasBusy || (event && event.persisted)) { hideFeedback(); }
            }
            form.addEventListener('submit', function (event) {
                if (form.getAttribute('aria-busy') === 'true') {
                    event.preventDefault();
                    return;
                }
                if (event.defaultPrevented || initiallyDisabled || !form.checkValidity()) { return; }
                var target = field(form, 'enabled').value === '1';
                var payload = supportsAsync ? new window.URLSearchParams(new window.FormData(form)) : null;
                var focused = document.activeElement === button;
                form.setAttribute('aria-busy', 'true');
                button.setAttribute('aria-busy', 'true');
                button.setAttribute('aria-checked', String(target));
                button.disabled = true;
                hideFeedback();
                if (!supportsAsync) { showFeedback(translate("正在保存模块开关设置…"), 'saving'); return; }
                event.preventDefault();
                pending = true;
                queue.push({ form: form, button: button, id: field(form, 'module_id').value, name: form.getAttribute('data-module-label') || translate("模块"), previous: confirmed, target: target, payload: payload,
                    confirm: function (value, disabled) { confirmed = value; initiallyDisabled = disabled; },
                    finish: function () {
                        pending = false; form.removeAttribute('aria-busy'); button.removeAttribute('aria-busy'); button.disabled = initiallyDisabled;
                        if (focused && document.activeElement === document.body) { button.focus({ preventScroll: true }); }
                    }
                });
                drain();
            });
            // Restore a form returned from the browser's back-forward cache.
            window.addEventListener('pageshow', restore);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
