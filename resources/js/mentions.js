// R-16 phase 2: the @mention autocomplete and the recommend-a-solution picker. Both are debounced, abort stale
// requests, and never fire for every keystroke. Registered on alpine:init like the attachments widget.

const jsonHeaders = { Accept: 'application/json' };

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // Wraps a textarea (events bubble to the wrapper): typing @name opens a picker; chosen identities become chips
    // and a JSON payload. The inline @Name stays plain text; the chips are the resolved identities.
    Alpine.data('mentionPicker', (opts = {}) => ({
        items: [],
        results: [],
        open: false,
        highlight: 0,
        loading: false,
        note: '',
        term: '',
        lastTerm: null,
        timer: null,
        controller: null,

        onInput(event) {
            const area = event.target;
            if (area.tagName !== 'TEXTAREA' && area.tagName !== 'INPUT') return;
            const upto = area.value.slice(0, area.selectionStart ?? area.value.length);
            const match = upto.match(/@([\p{L}\p{N} .&'-]{2,40})$/u);
            if (!match) {
                this.open = false;
                return;
            }
            this.term = match[1].trimEnd();
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.lookup(area), opts.debounce ?? 300);
        },

        async lookup(area) {
            const term = this.term;
            if (term.length < 2) return;
            if (term === this.lastTerm && this.results.length) { this.open = true; return; }
            this.lastTerm = term;
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            try {
                const r = await fetch(opts.url + '?q=' + encodeURIComponent(term), { headers: jsonHeaders, signal: this.controller.signal });
                if (!r.ok) { this.open = false; return; }
                const rows = await r.json();
                this.results.splice(0, this.results.length, ...rows);
                this.highlight = 0;
                this.open = this.results.length > 0;
                this.area = area;
            } catch { /* aborted or offline: keep quiet */ }
            finally { this.loading = false; }
        },

        onKey(event) {
            if (!this.open) return;
            if (event.key === 'ArrowDown') { event.preventDefault(); this.highlight = (this.highlight + 1) % this.results.length; }
            else if (event.key === 'ArrowUp') { event.preventDefault(); this.highlight = (this.highlight - 1 + this.results.length) % this.results.length; }
            else if (event.key === 'Enter' || event.key === 'Tab') { event.preventDefault(); this.choose(this.results[this.highlight], event.target); }
            else if (event.key === 'Escape') { this.open = false; }
        },

        choose(result, area) {
            if (!result) return;
            area = area && area.tagName === 'TEXTAREA' ? area : this.area ?? this.$root.querySelector('textarea');
            this.open = false;
            this.note = '';

            if (this.items.length >= (opts.max ?? 5) && !this.items.some((i) => i.type === result.type && i.id === result.id)) {
                this.note = 'At most ' + (opts.max ?? 5) + ' mentions on one post.';
                return;
            }

            // Replace the trailing @term before the caret with the chosen name, keeping the text readable.
            if (area) {
                const caret = area.selectionStart ?? area.value.length;
                const before = area.value.slice(0, caret).replace(/@([\p{L}\p{N} .&'-]{0,40})$/u, '@' + result.name + ' ');
                area.value = before + area.value.slice(caret);
                area.dispatchEvent(new Event('input', { bubbles: true }));
                area.focus();
                area.selectionStart = area.selectionEnd = before.length;
            }

            if (!this.items.some((i) => i.type === result.type && i.id === result.id)) {
                this.items.push({ type: result.type, id: result.id, name: result.name });
                this.sync();
            }
        },

        remove(item) {
            this.items.splice(this.items.indexOf(item), 1);
            this.note = '';
            this.sync();
        },

        clearAll() {
            this.items.splice(0, this.items.length);
            this.note = '';
            this.sync();
        },

        sync() {
            const payload = this.$refs.payload;
            if (!payload) return;
            payload.value = JSON.stringify(this.items.map((i) => ({ type: i.type, id: i.id })));
            payload.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));

    // A small search box that picks existing solutions to recommend with an answer.
    Alpine.data('solutionPicker', (opts = {}) => ({
        items: [],
        results: [],
        query: '',
        open: false,
        searching: false,
        note: '',
        timer: null,
        controller: null,
        lastTerm: null,

        onInput() {
            clearTimeout(this.timer);
            const term = this.query.trim();
            if (term.length < 2) { this.open = false; return; }
            this.timer = setTimeout(() => this.lookup(term), opts.debounce ?? 300);
        },

        async lookup(term) {
            if (term === this.lastTerm && this.results.length) { this.open = true; return; }
            this.lastTerm = term;
            this.controller?.abort();
            this.controller = new AbortController();
            this.searching = true;
            try {
                const r = await fetch(opts.url + '?q=' + encodeURIComponent(term), { headers: jsonHeaders, signal: this.controller.signal });
                if (!r.ok) { this.open = false; return; }
                const rows = await r.json();
                this.results.splice(0, this.results.length, ...rows);
                this.open = true;
            } catch { /* aborted or offline */ }
            finally { this.searching = false; }
        },

        choose(result) {
            this.open = false;
            this.query = '';
            if (this.items.some((i) => i.id === result.id)) return;
            if (this.items.length >= (opts.max ?? 3)) {
                this.note = 'Recommend at most ' + (opts.max ?? 3) + ' solutions with one answer.';
                return;
            }
            this.note = '';
            this.items.push(result);
            this.sync();
        },

        remove(item) {
            this.items.splice(this.items.indexOf(item), 1);
            this.note = '';
            this.sync();
        },

        clearAll() {
            this.items.splice(0, this.items.length);
            this.query = '';
            this.note = '';
            this.sync();
        },

        sync() {
            const payload = this.$refs.payload;
            if (!payload) return;
            payload.value = JSON.stringify(this.items.map((i) => ({ id: i.id })));
            payload.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));
});

// R-16 phase 3: watches a composer's title and details and quietly asks whether similar content already exists.
// Debounced well above typing speed, deduped by text, stale responses dropped. Guidance only — never blocks posting.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('similarFinder', (opts = {}) => ({
        results: [],
        checked: false,
        timer: null,
        controller: null,
        lastText: null,

        init() {
            this.$root.closest('form')?.addEventListener('input', (event) => {
                if (event.target.matches(opts.watch ?? 'input[type=text], textarea')) this.plan();
            });
        },

        text() {
            const form = this.$root.closest('form');
            if (!form) return '';
            return (opts.fields ?? [])
                .map((name) => form.querySelector('[name="' + name + '"]')?.value ?? '')
                .join(' ')
                .trim();
        },

        plan() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.check(), opts.debounce ?? 700);
        },

        async check() {
            const text = this.text();
            if (text.length < 12 || text === this.lastText) return;
            this.lastText = text;
            this.controller?.abort();
            this.controller = new AbortController();
            try {
                const r = await fetch(opts.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            ?? document.querySelector('input[name="_token"]')?.value ?? '',
                    },
                    body: JSON.stringify({ kind: opts.kind, text }),
                    signal: this.controller.signal,
                });
                if (!r.ok) return;
                // Replace in place: reassigning the array can leave effects registered on the root element stale.
                const rows = await r.json();
                this.results.splice(0, this.results.length, ...rows);
                this.checked = true;
            } catch { /* aborted or offline: say nothing */ }
        },
    }));
});
