(() => {
    'use strict';
    const root = document.querySelector('.mao-tk-maintenance-admin');
    if (!root) return;
    const form = root.querySelector('[data-mao-tk-maintenance-form]');
    const feedback = root.querySelector('[data-mao-tk-maintenance-feedback]');
    if (form) {
        form.addEventListener('input', () => { if (feedback) feedback.textContent = '有未保存的修改，可先预览再保存。'; });
        form.addEventListener('submit', e => {
            if (e.submitter && e.submitter.value === 'preview' && feedback) feedback.textContent = '已提交预览；当前输入尚未保存。';
        });
    }
    const media = root.querySelector('[data-mao-tk-maintenance-media]');
    if (media) media.addEventListener('click', () => {
        if (!window.wp || typeof window.wp.media !== 'function') return;
        const picker = window.wp.media({title:'选择维护页 Logo',button:{text:'使用此图片'},library:{type:'image'},multiple:false});
        picker.on('select', () => {
            const item = picker.state().get('selection').first().toJSON();
            const field = root.querySelector('#mao-tk-maintenance-logo');
            if (field && item.url) { field.value = item.url; field.dispatchEvent(new Event('input', {bubbles:true})); }
        });
        picker.open();
    });
})();
