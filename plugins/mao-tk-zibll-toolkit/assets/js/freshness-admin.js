(function () {
    'use strict';
    function initialize() {
        var settings = document.getElementById('mao-tk-freshness-settings');
        var preview = document.getElementById('mao-tk-freshness-preview-form');
        var note = document.getElementById('mao-tk-freshness-note');
        if (!settings || !preview) { return; }
        var timer;
        function refresh() { if (settings.checkValidity() && preview.requestSubmit) { preview.requestSubmit(); } }
        settings.addEventListener('input', function () { if (note) { note.textContent = '有未保存的修改，预览不影响正式文章。'; } window.clearTimeout(timer); timer = window.setTimeout(refresh, 350); });
        preview.addEventListener('submit', function (event) {
            window.clearTimeout(timer);
            if (!settings.reportValidity()) { event.preventDefault(); return; }
            preview.querySelectorAll('[data-preview-payload]').forEach(function (input) { input.remove(); });
            function append(name, value) { var input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; input.setAttribute('data-preview-payload', ''); preview.appendChild(input); }
            new FormData(settings).forEach(function (value, name) { if (name.indexOf('freshness[') === 0) { append(name, value); } });
            append('action', preview.getAttribute('data-action')); append('_wpnonce', preview.getAttribute('data-nonce'));
        });
        refresh();
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initialize, { once: true }); } else { initialize(); }
}());
