import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { dataDir } from './env';

export type Violation = { page: string; path: string; scheme: string; rule: string; impact: string; help: string; count: number; selectors: string[] };

export const a11yDir = path.join(dataDir, 'a11y');

/** WCAG 2.1 and 2.2, levels A and AA. */
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/** Scan the page as it stands and return what axe found, one row per rule. */
export async function scan(page: Page, name: string, scheme: string): Promise<Violation[]> {
    // Icons are a ligature font: until it has loaded they are plain words, and a menu that is still fading in has no
    // contrast yet. Scan the page as a person sees it once it has settled.
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(250);
    // An entrance that is still fading in is not what a person reads (the landing page's first screen takes about two
    // seconds to arrive, and a chip caught part-way reads as low contrast). Wait until no element's opacity is moving.
    await page
        .waitForFunction(
            () => {
                const seen = window as unknown as { settledOpacity?: string };
                const now = Array.from(document.querySelectorAll('body *'), (el) => getComputedStyle(el).opacity).join();
                const still = seen.settledOpacity === now;
                seen.settledOpacity = now;

                return still;
            },
            undefined,
            { polling: 300, timeout: 10_000 },
        )
        .catch(() => undefined);
    const results = await new AxeBuilder({ page }).withTags(tags).analyze();
    const where = new URL(page.url()).pathname.replace(/[0-9a-f]{8}-[0-9a-f-]{27}/, '{uuid}');

    return results.violations.map((violation) => ({
        page: name,
        path: where,
        scheme,
        rule: violation.id,
        impact: violation.impact ?? 'unknown',
        help: violation.help,
        count: violation.nodes.length,
        selectors: violation.nodes.map((node) => node.target.join(' ')).slice(0, 6),
    }));
}

export function record(group: string, scheme: string, violations: Violation[], scanned: string[]): void {
    fs.mkdirSync(a11yDir, { recursive: true });
    fs.writeFileSync(path.join(a11yDir, `${group}-${scheme}.json`), JSON.stringify({ group, scheme, scanned, violations }, null, 2));
}

const describe = (v: Violation): string => `${v.impact} ${v.rule} on ${v.path} (${v.scheme}), ${v.count}: ${v.selectors[0]}`;

/** Serious and critical violations other than colour contrast: these fail the scan. */
export function blocking(violations: Violation[]): string[] {
    return violations.filter((v) => (v.impact === 'serious' || v.impact === 'critical') && v.rule !== 'color-contrast').map(describe);
}

/** Colour contrast is serious too, and is asserted on its own so that the palette's known debts do not hide anything else. */
export function contrast(violations: Violation[]): string[] {
    return violations.filter((v) => v.rule === 'color-contrast').map(describe);
}

export function recorded(scheme: string): Violation[] {
    return fs.readdirSync(a11yDir).filter((f) => f.endsWith(`-${scheme}.json`))
        .flatMap((f) => JSON.parse(fs.readFileSync(path.join(a11yDir, f), 'utf8')).violations as Violation[]);
}
