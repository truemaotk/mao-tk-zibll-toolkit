(() => {
    'use strict';
    const form = document.querySelector('[data-mao-tk-profile-admin]'); if (!form) return;
    const feedback = form.querySelector('[data-mao-tk-profile-admin-feedback]');
    const dirty = () => { if (feedback) feedback.textContent = '有未保存的修改，保存后才会应用到前台。'; };
    form.addEventListener('input', dirty); form.addEventListener('change', dirty);
    function sync(library) {
        library.querySelector('[data-mao-tk-profile-media-ids]').value = [...library.querySelectorAll('[data-media-id]')].map(e => e.dataset.mediaId).join(','); dirty();
    }
    form.addEventListener('click', e => {
        const remove = e.target.closest('[data-mao-tk-profile-remove]');
        if (remove) { const lib = remove.closest('[data-profile-library]'); remove.closest('[data-media-id]').remove(); sync(lib); return; }
        const add = e.target.closest('[data-mao-tk-profile-media]');
        if (!add || !window.wp || typeof window.wp.media !== 'function') return;
        const lib = add.closest('[data-profile-library]');
        const picker = window.wp.media({title:'选择预设图片',button:{text:'添加到预设'},library:{type:'image'},multiple:true});
        picker.on('select', () => {
            picker.state().get('selection').toJSON().forEach(item => {
                if (lib.querySelector('[data-media-id="' + item.id + '"]')) return;
                const row = document.createElement('div'); row.className = 'mao-tk-profile-admin__media-item'; row.dataset.mediaId = String(item.id);
                const image = document.createElement('img'); image.src = item.sizes?.thumbnail?.url || item.url; image.alt = ''; image.width = 48; image.height = 48;
                const name = document.createElement('span'); name.textContent = item.title || ('图片 ' + item.id);
                const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset.maoTkProfileRemove = ''; button.textContent = '移出预设';
                row.append(image,name,button); lib.querySelector('[data-mao-tk-profile-media-list]').append(row);
            }); sync(lib);
        }); picker.open();
    });
})();
