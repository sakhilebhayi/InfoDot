// R-15 attachments: the composer uploader and the image lightbox. Registered on alpine:init so the same
// components work whether Alpine is started by Livewire (most pages) or by app.js (pages without it).

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content
    ?? document.querySelector('input[name="_token"]')?.value ?? '';

const human = (bytes) => {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(bytes >= 10485760 ? 0 : 1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('attachmentUploader', (opts = {}) => ({
        // One entry per chosen file. Items live in the array the caller passed (so a re-rendered step keeps its
        // uploads) or in a private one.
        items: opts.items ?? [],
        max: opts.max ?? 10,
        over: false,
        note: '',

        // After a failed validation the page re-renders: the uploads still exist server-side, so put them back.
        init() {
            if ((opts.preload ?? []).length && !this.items.length) {
                for (const it of opts.preload) {
                    this.items.push({
                        key: it.uuid, uuid: it.uuid, kind: it.kind, name: it.name, size: it.size,
                        thumb: it.thumb, preview: null, progress: 100, error: null, caption: it.caption ?? '', xhr: null,
                    });
                }
                this.sync();
            }
        },

        accept: '.jpg,.jpeg,.png,.webp,.gif,.pdf,.mp4,.m4v,.webm,image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/webm',

        get ready() {
            return this.items.filter((it) => it.uuid && !it.error);
        },

        pick() {
            this.$refs.input.click();
        },

        drop(event) {
            this.over = false;
            this.add(event.dataTransfer?.files ?? []);
        },

        add(files) {
            this.note = '';
            for (const file of files) this.upload(file);
        },

        upload(file) {
            if (this.items.filter((it) => !it.error).length >= this.max) {
                this.note = 'At most ' + this.max + ' attachments here. Remove one first.';
                return;
            }

            const item = {
                key: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random())),
                uuid: null, kind: null, name: file.name, size: file.size, thumb: null,
                preview: file.type?.startsWith('image/') ? URL.createObjectURL(file) : null,
                progress: 0, error: null, caption: '', xhr: null,
            };
            this.items.push(item);

            const data = new FormData();
            data.append('file', file);

            const xhr = new XMLHttpRequest();
            item.xhr = xhr;
            xhr.open('POST', opts.url);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) item.progress = Math.round((e.loaded / e.total) * 100);
            };
            xhr.onload = () => {
                item.xhr = null;
                if (xhr.status === 201) {
                    try {
                        const d = JSON.parse(xhr.responseText);
                        Object.assign(item, { uuid: d.uuid, kind: d.kind, name: d.name, thumb: d.thumb, progress: 100 });
                        this.sync();
                        return;
                    } catch { /* fall through to the error below */ }
                }
                let message = 'The upload failed. Try again.';
                try {
                    const d = JSON.parse(xhr.responseText);
                    message = d.errors?.file?.[0] ?? d.message ?? message;
                } catch { /* keep the generic message */ }
                item.error = message;
                item.progress = 0;
            };
            xhr.onerror = () => {
                item.xhr = null;
                item.error = 'The upload failed. Check your connection and try again.';
            };
            xhr.send(data);
        },

        remove(item) {
            if (item.xhr) item.xhr.abort();
            if (item.preview) URL.revokeObjectURL(item.preview);
            if (item.uuid) {
                fetch(opts.url + '/' + item.uuid, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                }).catch(() => {});
            }
            this.items.splice(this.items.indexOf(item), 1);
            this.note = '';
            this.sync();
        },

        move(item, dir) {
            const i = this.items.indexOf(item);
            const j = i + dir;
            if (j < 0 || j >= this.items.length) return;
            this.items.splice(i, 1);
            this.items.splice(j, 0, item);
            this.sync();
        },

        // Livewire forms read the uploads from one hidden JSON field; plain forms read the hidden inputs instead.
        sync() {
            const payload = this.$refs.payload;
            if (!payload) return;
            payload.value = JSON.stringify(this.ready.map((it) => ({ uuid: it.uuid, caption: it.caption })));
            payload.dispatchEvent(new Event('input', { bubbles: true }));
        },

        clearAll() {
            this.items.splice(0, this.items.length);
            this.note = '';
            this.sync();
        },

        icon(it) {
            return it.kind === 'pdf' ? 'picture_as_pdf' : (it.kind === 'video' ? 'movie' : 'image');
        },

        fmt: human,
    }));

    // The full-screen viewer the image grids open. One dialog per list; arrow keys and Escape work.
    Alpine.data('attachmentLightbox', (images = []) => ({
        images,
        at: 0,

        open(i) {
            this.at = i;
            this.$refs.dialog.showModal();
        },

        close() {
            this.$refs.dialog.close();
        },

        step(dir) {
            this.at = (this.at + dir + this.images.length) % this.images.length;
        },
    }));
});
