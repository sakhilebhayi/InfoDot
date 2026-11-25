// The complex form controls (R-64 phase 4): a searchable select (single and multiple), a date picker and a file dropzone. Each one is an
// enhancement of a real form control that is still in the page: the native <select>, <input type="date"> and <input type="file"> carry the value
// and submit with the form, and work on their own when this file has not loaded. Registered on alpine:init, like the attachments widget, so
// they work whether Livewire or app.js starts Alpine.
import { acceptsFile, addDays, addMonths, filterOptions, humanBytes, isoOf, monthGrid, parseIso } from './controls-lib.js';

let counter = 0;
const uid = (prefix) => `${prefix}-${(counter += 1)}`;

/**
 * Puts a fixed-position panel under (or, when there is no room, over) its anchor. Fixed positioning means no ancestor with overflow can clip it.
 * Called when the panel opens and again while it is open and the page scrolls or resizes.
 */
function place(anchor, panel, { matchWidth = true } = {}) {
    const rect = anchor.getBoundingClientRect();
    const gap = 4;
    const room = 8;
    const below = window.innerHeight - rect.bottom - room;
    const above = rect.top - room;
    const wanted = Math.min(panel.scrollHeight || 0, 320);
    const up = below < Math.min(wanted, 220) && above > below;
    const width = matchWidth ? rect.width : Math.max(rect.width, panel.offsetWidth || 0);
    const left = Math.max(room, Math.min(rect.left, window.innerWidth - width - room));

    Object.assign(panel.style, {
        position: 'fixed',
        left: `${left}px`,
        width: matchWidth ? `${width}px` : '',
        top: up ? 'auto' : `${rect.bottom + gap}px`,
        bottom: up ? `${window.innerHeight - rect.top + gap}px` : 'auto',
        maxHeight: `${Math.max(120, (up ? above : below) - gap)}px`,
    });
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // ------------------------------------------------------------------------------------------------------------------ searchable select
    Alpine.data('ixCombobox', (cfg = {}) => ({
        multiple: Boolean(cfg.multiple),
        ready: false,
        open: false,
        typed: false,
        query: '',
        options: [],
        chosen: [],
        active: -1,
        status: '',
        uid: uid('ixc'),
        watch: null,
        form: null,
        onReset: null,

        init() {
            const native = this.$refs.native;

            this.read();
            // The label now points at the search box, and the plain select steps out of the way.
            if (native.id) {
                this.$refs.input.id = native.id;
                native.id = `${native.id}-native`;
            }
            native.tabIndex = -1;
            native.removeAttribute('required'); // a hidden control cannot take the browser's "fill this in" prompt; the server still checks
            native.setAttribute('aria-hidden', 'true');
            this.ready = true;

            native.addEventListener('change', () => this.read());
            // A form reset puts the select back without a change event: read it again once the browser has done so.
            this.form = native.closest('form');
            this.onReset = () => setTimeout(() => this.read(), 0);
            this.form?.addEventListener('reset', this.onReset);
            this.watch = () => this.open && this.$nextTick(() => place(this.$refs.box, this.$refs.list));
        },

        destroy() {
            this.form?.removeEventListener('reset', this.onReset);
            window.removeEventListener('scroll', this.watch, true);
            window.removeEventListener('resize', this.watch);
        },

        read() {
            this.options = [...this.$refs.native.options].map((o, i) => ({ i, value: o.value, label: o.textContent.trim(), disabled: o.disabled, blank: o.value === '' }));
            this.chosen = this.options.filter((o) => this.$refs.native.options[o.i].selected && !o.blank).map((o) => o.value);
        },

        get listed() {
            return this.options.filter((o) => !o.blank);
        },

        get shown() {
            return this.typed ? filterOptions(this.listed, this.query) : this.listed;
        },

        get hasBlank() {
            return this.options.some((o) => o.blank);
        },

        get label() {
            const one = this.listed.find((o) => this.chosen.includes(o.value));

            return one ? one.label : '';
        },

        get chips() {
            return this.listed.filter((o) => this.chosen.includes(o.value));
        },

        get text() {
            return this.open && this.typed ? this.query : this.multiple ? this.query : this.label;
        },

        optionId(i) {
            return `${this.uid}-o${i}`;
        },

        isChosen(o) {
            return this.chosen.includes(o.value);
        },

        show() {
            if (this.open || this.$refs.native.disabled) return;
            this.open = true;
            this.typed = false;
            this.query = '';
            const at = this.shown.findIndex((o) => this.isChosen(o) && !o.disabled);
            this.active = at >= 0 ? at : this.shown.findIndex((o) => !o.disabled);
            this.$nextTick(() => {
                place(this.$refs.box, this.$refs.list);
                this.scrollActive();
            });
            window.addEventListener('scroll', this.watch, true);
            window.addEventListener('resize', this.watch);
            this.say();
        },

        hide() {
            if (!this.open) return;
            this.open = false;
            this.typed = false;
            this.query = '';
            window.removeEventListener('scroll', this.watch, true);
            window.removeEventListener('resize', this.watch);
        },

        toggle() {
            this.open ? this.hide() : this.show();
            this.$refs.input.focus();
        },

        typing(event) {
            this.query = event.target.value;
            this.typed = true;
            if (!this.open) this.show();
            this.active = this.shown.findIndex((o) => !o.disabled);
            this.say();
        },

        say() {
            const n = this.shown.length;
            this.status = n === 0 ? (cfg.empty ?? 'No matches') : `${n} ${n === 1 ? 'option' : 'options'} available`;
        },

        move(step) {
            if (!this.open) return this.show();
            const n = this.shown.length;
            if (n === 0) return;
            let i = this.active;
            for (let tries = 0; tries < n; tries += 1) {
                i = (i + step + n) % n;
                if (!this.shown[i].disabled) break;
            }
            this.active = i;
            this.scrollActive();
        },

        jump(end) {
            const list = this.shown;
            const i = end ? list.map((o) => o.disabled).lastIndexOf(false) : list.findIndex((o) => !o.disabled);
            if (i >= 0) {
                this.active = i;
                this.scrollActive();
            }
        },

        scrollActive() {
            this.$nextTick(() => document.getElementById(this.optionId(this.active))?.scrollIntoView({ block: 'nearest' }));
        },

        enter(event) {
            if (!this.open) return;
            event.preventDefault();
            const o = this.shown[this.active];
            if (o && !o.disabled) this.pick(o);
        },

        pick(o) {
            const native = this.$refs.native;

            if (this.multiple) {
                native.options[o.i].selected = !native.options[o.i].selected;
                this.query = '';
                this.typed = false;
            } else {
                native.value = o.value;
                this.hide();
            }
            native.dispatchEvent(new Event('input', { bubbles: true }));
            native.dispatchEvent(new Event('change', { bubbles: true }));
            this.read();
            this.$refs.input.focus();
        },

        drop(o) {
            this.$refs.native.options[o.i].selected = false;
            this.$refs.native.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.native.dispatchEvent(new Event('change', { bubbles: true }));
            this.read();
            this.$refs.input.focus();
        },

        clear() {
            const native = this.$refs.native;
            [...native.options].forEach((o) => { o.selected = false; });
            const blank = this.options.find((o) => o.blank);
            if (blank && !this.multiple) native.value = '';
            native.dispatchEvent(new Event('input', { bubbles: true }));
            native.dispatchEvent(new Event('change', { bubbles: true }));
            this.read();
            this.$refs.input.focus();
        },

        backspace() {
            if (this.multiple && this.query === '' && this.chips.length) this.drop(this.chips[this.chips.length - 1]);
        },
    }));

    // ------------------------------------------------------------------------------------------------------------------ date picker
    Alpine.data('ixDatePicker', (cfg = {}) => {
    // Intl formatters live outside the reactive data: a proxy around one cannot be called.
    let fmtHeading;
    let fmtFull;

    return {
        open: false,
        ready: false,
        form: null,
        onReset: null,
        value: '',
        text: '',
        year: 0,
        month: 0,
        focused: '',
        uid: uid('ixd'),
        weekStart: cfg.weekStart ?? 1,
        min: cfg.min ?? '',
        max: cfg.max ?? '',
        disabledDays: cfg.disabled ?? [],
        names: [],
        today: '',
        watch: null,
        title: '',

        init() {
            const input = this.$refs.input;
            const lang = document.documentElement.lang || 'en';

            this.today = this.localToday();
            this.names = Array.from({ length: 7 }, (_, i) => {
                const iso = addDays('2026-11-23', (this.weekStart - 1 + i)); // 23 Nov 2026 is a Monday
                return new Intl.DateTimeFormat(lang, { weekday: 'short', timeZone: 'UTC' }).format(new Date(`${iso}T00:00:00Z`));
            });
            fmtHeading = new Intl.DateTimeFormat(lang, { month: 'long', year: 'numeric', timeZone: 'UTC' });
            fmtFull = new Intl.DateTimeFormat(lang, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });

            // The native date control becomes a text box that takes an ISO date; the value it already holds is kept.
            input.type = 'text';
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('placeholder', 'yyyy-mm-dd');
            input.setAttribute('maxlength', '10');
            this.text = input.value;
            this.value = parseIso(this.text) ? this.text : '';
            this.ready = true;
            this.form = input.closest('form');
            this.onReset = () => setTimeout(() => this.reread(), 0);
            this.form?.addEventListener('reset', this.onReset);
            this.watch = () => this.open && this.$nextTick(() => place(this.$refs.input, this.$refs.panel, { matchWidth: false }));
        },

        destroy() {
            this.form?.removeEventListener('reset', this.onReset);
            window.removeEventListener('scroll', this.watch, true);
            window.removeEventListener('resize', this.watch);
        },

        reread() {
            const raw = this.$refs.input.value.trim();
            this.value = parseIso(raw) ? raw : '';
            this.$refs.input.setCustomValidity('');
            this.$refs.input.removeAttribute('aria-invalid');
        },

        localToday() {
            const n = new Date();

            return isoOf(n.getFullYear(), n.getMonth() + 1, n.getDate());
        },

        get days() {
            if (!this.year) return []; // the calendar is built when it opens
            return monthGrid({ year: this.year, month: this.month, weekStart: this.weekStart, value: this.value, today: this.today, min: this.min, max: this.max, disabled: this.disabledDays });
        },

        get monthLabel() {
            return this.year ? fmtHeading.format(new Date(Date.UTC(this.year, this.month - 1, 1))) : '';
        },

        label(d) {
            return fmtFull.format(new Date(`${d.iso}T00:00:00Z`));
        },

        allowed(iso) {
            return !((this.min !== '' && iso < this.min) || (this.max !== '' && iso > this.max) || this.disabledDays.includes(iso));
        },

        show() {
            if (this.open || this.$refs.input.disabled || this.$refs.input.readOnly) return;
            this.today = this.localToday();
            const start = this.value || (this.allowed(this.today) ? this.today : this.min || this.today);
            const p = parseIso(start);
            this.year = p.y;
            this.month = p.m;
            this.focused = start;
            this.open = true;
            this.$nextTick(() => {
                place(this.$refs.input, this.$refs.panel, { matchWidth: false });
                this.focusCell();
            });
            window.addEventListener('scroll', this.watch, true);
            window.addEventListener('resize', this.watch);
        },

        hide(returnFocus = true) {
            if (!this.open) return;
            this.open = false;
            window.removeEventListener('scroll', this.watch, true);
            window.removeEventListener('resize', this.watch);
            if (returnFocus) this.$refs.input.focus();
        },

        focusCell() {
            this.$nextTick(() => this.$refs.panel.querySelector(`[data-iso="${this.focused}"]`)?.focus());
        },

        go(iso) {
            const p = parseIso(iso);
            if (!p) return;
            this.focused = iso;
            this.year = p.y;
            this.month = p.m;
            this.focusCell();
        },

        key(event) {
            const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            const k = event.key;

            if (k in steps) {
                event.preventDefault();
                this.go(addDays(this.focused, steps[k]));
            } else if (k === 'PageUp' || k === 'PageDown') {
                event.preventDefault();
                const dir = k === 'PageUp' ? -1 : 1;
                const p = parseIso(this.focused);
                const target = addMonths(this.focused, event.shiftKey ? dir * 12 : dir);
                const last = parseIso(addDays(addMonths(target, 1), -1)).d;
                this.go(`${target.slice(0, 8)}${String(Math.min(p.d, last)).padStart(2, '0')}`);
            } else if (k === 'Home' || k === 'End') {
                event.preventDefault();
                const offset = (new Date(`${this.focused}T00:00:00Z`).getUTCDay() + 6 + (1 - this.weekStart) + 7) % 7;
                this.go(addDays(this.focused, k === 'Home' ? -offset : 6 - offset));
            } else if (k === 'Escape') {
                event.preventDefault();
                event.stopPropagation(); // closes the calendar, not the dialog around it
                this.hide();
            } else if (k === 'Tab') {
                this.hide(false);
            }
        },

        step(by) {
            const first = addMonths(isoOf(this.year, this.month, 1), by);
            const p = parseIso(first);
            this.year = p.y;
            this.month = p.m;
            this.focused = first;
        },

        choose(d) {
            if (d.disabled) return;
            this.set(d.iso);
            this.hide();
        },

        set(iso) {
            this.value = iso;
            this.text = iso;
            this.$refs.input.value = iso;
            this.$refs.input.removeAttribute('aria-invalid');
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        clear() {
            this.value = '';
            this.text = '';
            this.$refs.input.value = '';
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.hide();
        },

        now() {
            if (this.allowed(this.today)) this.set(this.today);
            this.hide();
        },

        // While typing, a real allowed date is taken as the value; nothing is flagged until the person leaves the field.
        typed() {
            const raw = this.$refs.input.value.trim();

            this.value = parseIso(raw) && this.allowed(raw) ? raw : this.value;
            if (raw === '') this.value = '';
        },

        // On leaving the field: accept 2026/11/25 or 2026.11.25 as 2026-11-25, and say plainly when what is there is not a date that can be chosen.
        // The browser's own validation message blocks the submit, which a text box does not do by itself.
        check() {
            const input = this.$refs.input;
            const raw = input.value.trim().replace(/[/.]/g, '-');

            if (raw === '') {
                this.value = '';
                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
                return;
            }

            if (parseIso(raw) && this.allowed(raw)) {
                this.value = raw;
                input.value = raw;
                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
                return;
            }

            const range = [this.min && `from ${this.min}`, this.max && `up to ${this.max}`].filter(Boolean).join(' ');
            input.setCustomValidity(parseIso(raw) ? `That date cannot be chosen${range ? ` (${range})` : ''}.` : 'Enter a real date as yyyy-mm-dd, for example 2026-11-25.');
            input.setAttribute('aria-invalid', 'true');
        },
    };
    });

    // ------------------------------------------------------------------------------------------------------------------ file dropzone
    Alpine.data('ixDropzone', (cfg = {}) => ({
        ready: false,
        over: false,
        files: [],
        quiet: false,
        form: null,
        onReset: null,
        error: '',
        multiple: Boolean(cfg.multiple),
        accept: cfg.accept ?? '',
        maxBytes: cfg.maxBytes ?? 0,
        max: cfg.max ?? 0,

        init() {
            this.ready = true;
            this.sync();
            this.form = this.$refs.input.closest('form');
            this.onReset = () => setTimeout(() => this.sync(), 0);
            this.form?.addEventListener('reset', this.onReset);
        },

        destroy() {
            this.form?.removeEventListener('reset', this.onReset);
            this.files.forEach((f) => f.url && URL.revokeObjectURL(f.url));
        },

        human: humanBytes,

        sync() {
            this.files.forEach((f) => f.url && URL.revokeObjectURL(f.url));
            this.files = [...(this.$refs.input.files ?? [])].map((file) => ({
                file, name: file.name, size: file.size, url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            }));
        },

        // Chosen with the browser's own picker (which has already replaced what the input held): check them the same way as dropped ones.
        picked() {
            if (this.quiet) return;
            this.take([...this.$refs.input.files]);
        },

        drop(event) {
            this.over = false;
            this.take([...(event.dataTransfer?.files ?? [])]);
        },

        take(incoming) {
            const problems = [];
            const good = [];

            const seen = new Set(this.files.map((f) => `${f.name}:${f.size}:${f.file.lastModified}`));

            for (const file of incoming) {
                const key = `${file.name}:${file.size}:${file.lastModified}`;
                if (this.multiple && seen.has(key)) continue; // the same file twice is the same file
                seen.add(key);
                if (!acceptsFile(file, this.accept)) problems.push(`${file.name} is not an allowed kind of file.`);
                else if (this.maxBytes > 0 && file.size > this.maxBytes) problems.push(`${file.name} is larger than ${humanBytes(this.maxBytes)}.`);
                else good.push(file);
            }

            const kept = this.files.map((f) => f.file);
            let next = this.multiple ? [...kept, ...good] : (good.length > 0 ? good.slice(0, 1) : kept);

            if (this.multiple && this.max > 0 && next.length > this.max) {
                problems.push(`Only ${this.max} files can be added.`);
                next = next.slice(0, this.max);
            }

            this.apply(next);
            this.error = problems.join(' ');
        },

        apply(list) {
            try {
                const transfer = new DataTransfer();
                list.forEach((f) => transfer.items.add(f));
                this.$refs.input.files = transfer.files;
            } catch {
                // An old browser cannot set the files itself: the input keeps what the picker gave it, and the list shows that.
            }
            this.sync();
            // The form (or Livewire) hears about the change once; our own event must not be taken for a new choice.
            this.quiet = true;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.quiet = false;
        },

        remove(index) {
            this.apply(this.files.map((f) => f.file).filter((_, i) => i !== index));
            this.error = '';
        },
    }));
});
