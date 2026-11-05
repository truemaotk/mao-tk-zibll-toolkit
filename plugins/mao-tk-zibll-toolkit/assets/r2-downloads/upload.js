(() => {
    'use strict';

    const root = document.getElementById('mao-tk-r2-uploads');
    if (!root) return;

    const field = (name) => root.querySelector('#mtk-r2-' + name);
    const fileInput = field('file');
    const start = field('start');
    const cancel = field('cancel');
    const status = field('status');
    const progress = field('progress');
    const objectKey = field('key');
    const postId = field('post');
    const reference = field('reference');
    const refStatus = field('ref-status');
    const refResult = field('ref-result');
    const refUrl = field('reference-url');
    const switchForm = root.querySelector('[data-mao-tk-module-form]');
    const settingsForm = field('settings-form');
    const settingsSave = settingsForm.querySelector('button[type="submit"]');
    const runtime = field('runtime');
    const fileControls = field('file-controls');
    const fileList = field('files');
    const filesStatus = field('files-status');
    const filesPrevious = field('files-prev');
    const filesNext = field('files-next');
    const partSize = Number(root.dataset.partSize);
    const parallelParts = 3;
    const requests = new Set();
    let job = null;
    let busy = false;
    let finishing = false;
    let cancelling = false;
    let cancelRequested = false;
    let referenceBusy = false;
    let filesRequested = false;
    let filesBusy = false;
    let deleting = false;
    let pageCursors = [''];
    let nextCursor = '';
    let files = [];
    let lastUpload = null;

    function feedback(target, message, state = '') {
        target.textContent = message;
        target.dataset.state = state;
    }

    async function api(operation, values = {}) {
        const body = new URLSearchParams({ action: root.dataset.action, nonce: root.dataset.nonce, operation, ...values });
        const reply = await fetch(root.dataset.ajax, { method: 'POST', credentials: 'same-origin', body });
        const data = await reply.json().catch(() => null);
        if (!reply.ok || !data?.success) throw new Error(data?.data?.message || '后台请求失败，请刷新登录状态或稍后重试。');
        return data.data;
    }

    function refresh() {
        const enabled = runtime.textContent.trim() === '模块运行中';
        const uploadConfigured = root.dataset.uploadReady === '1';
        const referenceConfigured = uploadConfigured && root.dataset.downloadReady === '1';
        const uploadReady = enabled && uploadConfigured && switchForm.getAttribute('aria-busy') !== 'true';
        const referenceReady = uploadReady && referenceConfigured;
        field('upload-hint').textContent = !uploadConfigured ? '保存上传凭据后可以选择文件。'
            : !enabled ? '启用模块后可以选择文件。' : '上传时请保持本页打开，失败后可继续当前任务。';
        field('ref-hint').textContent = !referenceConfigured ? '保存上传与签名设置后可生成引用。'
            : !enabled ? '启用模块后可生成引用。' : '文章 ID 可从编辑地址中的 post= 参数查看。';
        settingsSave.disabled = busy || Boolean(job) || deleting;
        fileInput.disabled = !uploadReady || busy || Boolean(job);
        start.disabled = !uploadReady || busy || cancelling || !(job || fileInput.files.length);
        start.setAttribute('aria-busy', String(busy));
        const uploaded = job && job.completed.size === Math.ceil(job.file.size / partSize);
        start.textContent = job ? (uploaded ? '重试确认' : '继续上传') : '开始上传';
        cancel.disabled = !job || cancelling || finishing || cancelRequested || uploaded;
        objectKey.disabled = postId.disabled = !referenceReady || referenceBusy || deleting;
        reference.disabled = postId.disabled || !objectKey.value.trim() || !postId.value;
        reference.setAttribute('aria-busy', String(referenceBusy));
        field('library-hint').hidden = uploadReady;
        fileControls.disabled = !uploadReady || filesBusy || referenceBusy;
        fileControls.setAttribute('aria-busy', String(filesBusy));
        fileList.querySelectorAll('[data-use-file]').forEach((button) => { button.disabled = !referenceReady; });
        if (uploadReady && !filesRequested) void loadFiles(['']);
    }

    function useFile(key, focus = false) {
        objectKey.value = key;
        objectKey.dispatchEvent(new Event('input'));
        if (focus) postId.focus();
    }

    function renderFiles() {
        const shown = lastUpload
            ? [lastUpload, ...files.filter((file) => file.key !== lastUpload.key)]
            : files;
        fileList.replaceChildren();
        for (const file of shown) {
            const row = document.createElement('li');
            row.className = 'mtk-r2__file-row';
            const meta = document.createElement('div');
            meta.className = 'mtk-r2__file-meta';
            const name = document.createElement('strong');
            name.textContent = file.key.split('/').pop();
            const key = document.createElement('small');
            key.textContent = file.key;
            meta.append(name, key);
            const actions = document.createElement('div');
            actions.className = 'mtk-r2__file-buttons';
            const use = document.createElement('button');
            use.type = 'button';
            use.className = 'button';
            use.dataset.useFile = '';
            use.textContent = '用此文件生成引用';
            use.addEventListener('click', () => useFile(file.key, true));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button mtk-r2__delete';
            remove.textContent = '从 R2 删除';
            remove.addEventListener('click', () => deleteFile(file));
            actions.append(use, remove);
            row.append(meta, actions);
            fileList.append(row);
        }
        filesPrevious.disabled = pageCursors.length < 2;
        filesNext.disabled = !nextCursor;
    }

    async function loadFiles(cursors, clearLatest = false) {
        if (filesBusy) return;
        filesRequested = filesBusy = true;
        feedback(filesStatus, '正在读取 R2 文件…');
        refresh();
        try {
            const page = await api('list_files', { cursor: cursors[cursors.length - 1] });
            files = page.files;
            nextCursor = page.next;
            pageCursors = cursors;
            if (clearLatest) lastUpload = null;
            renderFiles();
            feedback(filesStatus, fileList.children.length ? '' : (nextCursor ? '本页没有 Toolkit 文件，可查看下一页。' : '暂无 Toolkit 上传文件。'));
        } catch (error) {
            feedback(filesStatus, error.message, 'error');
        } finally {
            filesBusy = false;
            refresh();
        }
    }

    async function deleteFile(file) {
        if (fileControls.disabled) return;
        if (!window.confirm('永久删除这个 R2 文件？\n' + file.key + '\n引用此文件的文章将无法下载。')) return;
        deleting = filesBusy = true;
        feedback(filesStatus, '正在从 R2 删除文件…');
        refresh();
        try {
            await api('delete_file', { object_key: file.key, delete_nonce: file.delete_nonce });
            files = files.filter((entry) => entry.key !== file.key);
            if (lastUpload?.key === file.key) {
                lastUpload = null;
                feedback(status, '');
            }
            if (objectKey.value === file.key) useFile('');
            renderFiles();
            feedback(filesStatus, '文件已从 R2 删除。', 'success');
            field('library-heading').focus();
        } catch (error) {
            feedback(filesStatus, error.message + ' 可刷新列表核对。', 'error');
        } finally {
            deleting = filesBusy = false;
            refresh();
        }
    }

    function uploadPart(url, blob, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url);
            xhr.timeout = 600000;
            xhr.upload.onprogress = (event) => onProgress(Math.min(blob.size, event.loaded));
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) resolve();
                else reject(new Error('R2 上传返回 HTTP ' + xhr.status + '。'));
            };
            xhr.onerror = () => reject(new Error('直传连接中断。请检查网络和 R2 的 CORS 配置。'));
            xhr.ontimeout = () => reject(new Error('当前分片上传超时。'));
            xhr.onabort = () => reject(new Error('上传已停止。'));
            xhr.onloadend = () => requests.delete(xhr);
            requests.add(xhr);
            try {
                xhr.send(blob);
            } catch (error) {
                requests.delete(xhr);
                reject(error);
            }
        });
    }

    async function uploadPendingParts({ file, ticket, completed }) {
        const partCount = Math.ceil(file.size / partSize);
        const activeBytes = new Map();
        let confirmedBytes = 0;
        for (const number of completed) {
            confirmedBytes += Math.min(partSize, file.size - (number - 1) * partSize);
        }
        let nextPart = 1;
        let failure;

        function renderProgress() {
            const inFlight = [...activeBytes.values()].reduce((sum, loaded) => sum + loaded, 0);
            const percent = Math.floor((confirmedBytes + inFlight) / file.size * 100);
            progress.value = Math.min(completed.size === partCount ? 100 : 99, percent);
        }

        async function sendParts() {
            while (!cancelRequested && !failure && nextPart <= partCount) {
                const number = nextPart++;
                if (completed.has(number)) continue;
                const offset = (number - 1) * partSize;
                const blob = file.slice(offset, offset + partSize);
                for (let attempt = 1; !cancelRequested; attempt += 1) {
                    feedback(status, attempt > 1 ? '正在重试分片 ' + number + '。' : '已上传 ' + completed.size + ' / ' + partCount + ' 个分片。');
                    try {
                        const { url } = await api('part', { ticket, number });
                        if (cancelRequested) return;
                        await uploadPart(url, blob, (loaded) => {
                            activeBytes.set(number, loaded);
                            renderProgress();
                        });
                        completed.add(number);
                        confirmedBytes += blob.size;
                        break;
                    } catch (error) {
                        if (cancelRequested) return;
                        if (attempt >= 3) {
                            failure ??= error;
                            return;
                        }
                    } finally {
                        activeBytes.delete(number);
                        renderProgress();
                    }
                    await new Promise((resolve) => window.setTimeout(resolve, attempt * 1000));
                }
            }
        }

        await Promise.all(Array.from({ length: Math.min(parallelParts, partCount - completed.size) }, sendParts));
        if (failure && !cancelRequested) throw failure;
    }

    async function cancelJob() {
        if (!job || cancelling) return;
        cancelling = true;
        refresh();
        feedback(status, '正在取消上传任务…');
        try {
            await api('cancel', { ticket: job.ticket });
            job = null;
            fileInput.value = '';
            progress.hidden = true;
            feedback(status, '已取消上传。');
        } catch (error) {
            feedback(status, error.message + ' 可重试取消。', 'error');
        } finally {
            cancelRequested = cancelling = false;
            refresh();
        }
    }

    async function sendFile() {
        if (busy || cancelling) return;
        busy = true;
        refresh();
        try {
            if (!job) {
                const file = fileInput.files[0];
                if (!file || file.size < 1 || file.size > Number(root.dataset.maxSize)) {
                    throw new Error('请选择大于 0 字节、最大 30 GiB 的文件。');
                }
                feedback(status, '正在创建 R2 上传任务…');
                const { ticket } = await api('start', { filename: file.name, size: file.size });
                job = { ticket, file, completed: new Set() };
                progress.value = 0;
                progress.hidden = false;
                refresh();
            }
            await uploadPendingParts(job);
            if (cancelRequested) {
                await cancelJob();
                return;
            }
            finishing = true;
            refresh();
            feedback(status, '正在让 R2 合并分片并核对文件大小…');
            const result = await api('finish', { ticket: job.ticket });
            useFile(result.key);
            lastUpload = result;
            renderFiles();
            if (filesStatus.textContent === '暂无 Toolkit 上传文件。') feedback(filesStatus, '');
            job = null;
            fileInput.value = '';
            feedback(status, '上传已完成。请在下方填入文章 ID 并生成引用。', 'success');
        } catch (error) {
            if (cancelRequested) await cancelJob();
            else feedback(status, error.message + (job ? ' 当前任务可重试。' : ''), 'error');
        } finally {
            busy = finishing = false;
            refresh();
        }
    }

    fileInput.addEventListener('change', () => {
        progress.hidden = true;
        feedback(status, '');
        refresh();
    });
    field('files-reload').addEventListener('click', () => loadFiles([''], true));
    filesPrevious.addEventListener('click', () => loadFiles(pageCursors.slice(0, -1)));
    filesNext.addEventListener('click', () => loadFiles([...pageCursors, nextCursor]));
    start.addEventListener('click', sendFile);
    cancel.addEventListener('click', () => {
        if (!busy) {
            void cancelJob();
            return;
        }
        cancelRequested = true;
        cancel.disabled = true;
        requests.forEach((xhr) => xhr.abort());
    });
    function guardUpload(event) {
        if (deleting) {
            event.preventDefault();
            return;
        }
        if (busy || job) {
            event.preventDefault();
            feedback(status, '请先完成或取消当前上传任务。', 'error');
        }
    }
    switchForm.addEventListener('submit', guardUpload, true);
    settingsForm.addEventListener('submit', guardUpload);
    const stateObserver = new MutationObserver(refresh);
    stateObserver.observe(runtime, { childList: true, subtree: true });
    stateObserver.observe(switchForm, { attributes: true, attributeFilter: ['aria-busy'] });
    window.addEventListener('beforeunload', (event) => {
        if (busy || job) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    [postId, objectKey].forEach((input) => input.addEventListener('input', () => {
        refResult.hidden = true;
        feedback(refStatus, '');
        refresh();
    }));
    reference.addEventListener('click', async () => {
        if (!postId.reportValidity()) return;
        referenceBusy = true;
        refResult.hidden = true;
        feedback(refStatus, '正在检查对象并生成引用…');
        refresh();
        try {
            const { url } = await api('reference', { post_id: postId.value, object_key: objectKey.value });
            refUrl.value = url;
            refResult.hidden = false;
            feedback(refStatus, '引用已生成。请贴到该文章的子比下载链接字段。', 'success');
        } catch (error) {
            feedback(refStatus, error.message, 'error');
        } finally {
            referenceBusy = false;
            refresh();
        }
    });
    field('copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(refUrl.value);
            feedback(refStatus, '引用已复制。', 'success');
        } catch {
            refUrl.focus();
            refUrl.select();
            feedback(refStatus, '已选中链接，可以手动复制。');
        }
    });
    refresh();
})();
