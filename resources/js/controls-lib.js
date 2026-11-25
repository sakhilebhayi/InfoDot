// Pure helpers for the complex form controls (R-64 phase 4): dates as plain calendar days, file checks, option filtering. No DOM in here, so
// tests/Js/controls-lib.test.mjs can run them under Node.

const pad = (n) => String(n).padStart(2, '0');

/** 'YYYY-MM-DD' for a year, month (1-12) and day. */
export const isoOf = (y, m, d) => `${String(y).padStart(4, '0')}-${pad(m)}-${pad(d)}`;

/** A real calendar day from 'YYYY-MM-DD', or null (so 2026-02-30 and 'nonsense' are both null). */
export function parseIso(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value ?? '').trim());
    if (!match) return null;

    const [y, m, d] = [Number(match[1]), Number(match[2]), Number(match[3])];
    const date = new Date(Date.UTC(y, m - 1, d));

    return date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d ? { y, m, d } : null;
}

/** The day `days` after (or before) an ISO day. Pure calendar arithmetic: no time zone and no daylight saving can shift it. */
export function addDays(iso, days) {
    const p = parseIso(iso);
    const date = new Date(Date.UTC(p.y, p.m - 1, p.d + days));

    return isoOf(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate());
}

/** The first of the month `months` away from an ISO day. */
export function addMonths(iso, months) {
    const p = parseIso(iso);
    const date = new Date(Date.UTC(p.y, p.m - 1 + months, 1));

    return isoOf(date.getUTCFullYear(), date.getUTCMonth() + 1, 1);
}

/** 1 (Monday) to 7 (Sunday). */
export function isoWeekday(iso) {
    const p = parseIso(iso);
    const day = new Date(Date.UTC(p.y, p.m - 1, p.d)).getUTCDay();

    return day === 0 ? 7 : day;
}

/**
 * The six weeks (42 days) a month's calendar shows, from the week start before the 1st. Each day says whether it is in the month, today, chosen,
 * or not allowed (before min, after max, or in the disabled list).
 *
 * @param {{year: number, month: number, weekStart?: number, value?: string, today?: string, min?: string, max?: string, disabled?: string[]}} o
 */
export function monthGrid({ year, month, weekStart = 1, value = '', today = '', min = '', max = '', disabled = [] }) {
    const first = isoOf(year, month, 1);
    const back = (isoWeekday(first) - weekStart + 7) % 7;
    const off = new Set(disabled);
    const days = [];

    for (let i = 0; i < 42; i += 1) {
        const iso = addDays(first, i - back);

        days.push({
            iso,
            day: parseIso(iso).d,
            inMonth: parseIso(iso).m === month,
            today: iso === today,
            selected: iso === value,
            disabled: (min !== '' && iso < min) || (max !== '' && iso > max) || off.has(iso),
        });
    }

    return days;
}

// Some systems report no type for a file the browser does not know (HEIC on Windows, for one). Fall back to what the extension says.
const TYPE_BY_EXTENSION = {
    jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp', heic: 'image/heic', heif: 'image/heif', avif: 'image/avif', svg: 'image/svg+xml', bmp: 'image/bmp',
    pdf: 'application/pdf', mp4: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime', mp3: 'audio/mpeg', wav: 'audio/wav',
};

/** Whether a file passes an `accept` list such as 'image/*,.pdf,application/pdf'. An empty list accepts everything. */
export function acceptsFile(file, accept) {
    const tokens = String(accept ?? '').split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);
    if (tokens.length === 0) return true;

    const name = String(file.name ?? '').toLowerCase();
    const extension = name.includes('.') ? name.split('.').pop() : '';
    const type = String(file.type ?? '').toLowerCase() || (TYPE_BY_EXTENSION[extension] ?? '');

    return tokens.some((t) => (t.startsWith('.') ? name.endsWith(t) : t.endsWith('/*') ? type.startsWith(t.slice(0, -1)) : type === t));
}

/** '3.2 MB', '120 KB', '800 B'. */
export function humanBytes(bytes) {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(bytes >= 10485760 ? 0 : 1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${bytes} B`;
}

const plain = (text) => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/** Options whose label contains every word typed, ignoring case and accents. An empty query keeps them all. */
export function filterOptions(options, query) {
    const words = plain(query).split(/\s+/).filter(Boolean);
    if (words.length === 0) return options;

    return options.filter((o) => {
        const label = plain(o.label);

        return words.every((w) => label.includes(w));
    });
}
