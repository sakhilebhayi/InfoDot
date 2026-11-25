// The pop-up area (R-64 phase 6). New notifications appear here while a page is open, and any script can show one with an 'ix-toast' event. Polling is
// slow and polite: every 45 seconds, only while the page is visible, backing off when the server does not answer, and stopping for good when the
// session has ended. Registered on alpine:init like the other widgets.

const POLL_MS = 45000;
const MAX_VISIBLE = 3;
const SHOW_MS = 9000;
const ACTIONABLE_MS = 20000;

/** A link a pop-up may carry: a path on this site, or a full address on this site. Anything else is dropped. */
const safeHref = (href) => {
    if (typeof href !== 'string' || href === '') return '';
    try {
        const url = new URL(href, window.location.origin);

        return url.origin === window.location.origin ? url.pathname + url.search + url.hash : '';
    } catch {
        return '';
    }
};

/** Keeps the bell's badge and name equal to the real unread number. The bell itself is the shell's; only its count is touched. */
function setBell(unread) {
    const bell = document.querySelector('a.dh-bell');
    if (!bell) return;

    bell.setAttribute('aria-label', unread > 0 ? `Notifications, ${unread} unread` : 'Notifications');
    let badge = bell.querySelector('.dh-badge');

    if (unread > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'dh-badge';
            badge.setAttribute('aria-hidden', 'true');
            bell.appendChild(badge);
        }
        badge.textContent = unread > 99 ? '99+' : String(unread);
    } else {
        badge?.remove();
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('ixToasts', (cfg = {}) => ({
        items: [],
        last: cfg.last ?? 0,
        n: 0,
        timer: null,
        failures: 0,
        stopped: false,
        onVisible: null,

        init() {
            if (!cfg.url) return;

            this.onVisible = () => {
                if (!document.hidden) this.poll();
            };
            document.addEventListener('visibilitychange', this.onVisible);
            this.schedule();
        },

        destroy() {
            clearTimeout(this.timer);
            document.removeEventListener('visibilitychange', this.onVisible);
            this.items.forEach((t) => clearTimeout(t.timer));
        },

        schedule() {
            clearTimeout(this.timer);
            if (this.stopped) return;
            // After failures the wait doubles, up to five minutes.
            this.timer = setTimeout(() => this.poll(), Math.min(POLL_MS * 2 ** this.failures, 300000));
        },

        async poll() {
            if (this.stopped || document.hidden) return this.schedule();

            try {
                const response = await fetch(`${cfg.url}?after=${this.last}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });

                if ([401, 403, 419].includes(response.status)) {
                    this.stopped = true; // the session ended: stop asking
                    return;
                }
                if (!response.ok) throw new Error(String(response.status));

                const data = await response.json();
                this.failures = 0;
                this.last = Math.max(this.last, Number(data.last) || 0);
                setBell(Number(data.unread) || 0);
                (data.notices ?? []).forEach((notice) => this.push({ key: `n${notice.id}`, label: notice.label, text: notice.text, href: notice.href, action: notice.actionable ? 'Review' : 'Open', ms: notice.actionable ? ACTIONABLE_MS : SHOW_MS }));
            } catch {
                this.failures = Math.min(this.failures + 1, 3);
            }

            this.schedule();
        },

        push(detail = {}) {
            const text = String(detail.text ?? '').trim();
            if (text === '') return;

            const key = detail.key ?? `t${(this.n += 1)}`;
            if (this.items.some((t) => t.key === key)) return;

            const toast = { key, text, label: String(detail.label ?? 'Update'), href: safeHref(detail.href), action: String(detail.action ?? 'Open'), tone: ['info', 'ok', 'warn'].includes(detail.tone) ? detail.tone : 'info', ms: Number(detail.ms) || SHOW_MS, timer: null };
            this.items.push(toast);

            while (this.items.length > MAX_VISIBLE) this.dismiss(this.items[0]);

            // Work through the reactive copy so the timer lives on the object Alpine watches.
            this.release(this.items[this.items.length - 1]);
        },

        hold(toast) {
            clearTimeout(toast.timer);
        },

        release(toast) {
            clearTimeout(toast.timer);
            toast.timer = setTimeout(() => this.dismiss(toast), toast.ms);
        },

        dismiss(toast) {
            clearTimeout(toast.timer);
            this.items = this.items.filter((t) => t.key !== toast.key);
        },
    }));
});
