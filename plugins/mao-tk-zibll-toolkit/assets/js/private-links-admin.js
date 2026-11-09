(function () {
    'use strict';
    const translate = text => window.wp?.i18n?.__(text, 'mao-tk-zibll-toolkit') ?? text;
    function initialize() {
        var root = document.querySelector('.mao-tk-private-links');
        if (!root) { return; }
        root.querySelectorAll('[data-private-form]').forEach(function (form) {
            var fields = Array.from(form.querySelectorAll('input[type="checkbox"], select[name^="user_override["]'));
            var saved = fields.map(function (field) { return field.type === 'checkbox' ? field.checked : field.value; });
            var state = form.querySelector('[data-private-form-state]');
            var counter = form.querySelector('[data-private-role-count]');
            function synchronize() {
                var dirty = fields.some(function (field, index) { return (field.type === 'checkbox' ? field.checked : field.value) !== saved[index]; });
                if (state) {
                    state.textContent = dirty ? translate("有未保存的修改") : translate("当前规则已保存");
                    state.setAttribute('data-dirty', String(dirty));
                }
                if (counter) {
                    counter.textContent = translate("已选 ") + fields.filter(function (field) { return field.checked; }).length + ' / ' + fields.length + translate(" 项");
                }
            }
            form.addEventListener('change', synchronize);
            window.addEventListener('pageshow', synchronize);
            synchronize();
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initialize, { once: true }); }
    else { initialize(); }
}());
