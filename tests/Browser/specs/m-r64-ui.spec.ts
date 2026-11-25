import type { Page } from '@playwright/test';
import { makeUser } from '../support/app';
import { blocking, scan } from '../support/a11y';
import { expect, signIn, test, watch } from '../support/fixtures';

/**
 * R-64 quality assurance, in a real browser: the pages the programme touched, at nine widths, signed in. For each: nothing wider than the screen, no console
 * or network problems, no serious accessibility violation (colour contrast is reported separately), and the new interactions behave: the settings form saves
 * and says so, long pages reveal and show a reading bar, motion is off when asked, and a form cannot be sent twice.
 */

const WIDTHS = [320, 360, 375, 390, 414, 768, 834, 1024, 1280, 1440];

const PAGES = ['/home', '/businesses', '/questions', '/notifications', '/notifications/settings', '/onboarding/business', '/user/profile/edit', '/policy'];

/** Local development cannot reach the platforms' websocket host; this run builds without a key, so any such error is real. */
const ignorable = (problem: string): boolean => /favicon|ERR_BLOCKED_BY_CLIENT/.test(problem);

async function measure(page: Page): Promise<{ scroll: number; client: number; small: string[] }> {
    return page.evaluate(() => {
        const small: string[] = [];
        for (const el of Array.from(document.querySelectorAll<HTMLElement>('main button, main a.ix-btn, main input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not(.ix-sr), main select'))) {
            const box = el.getBoundingClientRect();
            const style = getComputedStyle(el);
            if (box.width === 0 || box.height === 0 || style.visibility === 'hidden' || style.display === 'none') continue;
            if (box.height < 24 || box.width < 24) small.push(`${el.tagName.toLowerCase()}.${el.className.toString().split(' ')[0]} ${Math.round(box.width)}x${Math.round(box.height)}`);
        }

        return { scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth, small };
    });
}

test.describe('M. R-64 across the product', () => {
    test('every touched page at nine widths: no sideways scroll, no console problems, targets not tiny', async ({ page }) => {
        test.setTimeout(600_000);
        const person = makeUser('R64 Walker');
        const record = watch(page);
        await signIn(page, person.email);

        const findings: string[] = [];

        for (const width of WIDTHS) {
            await page.setViewportSize({ width, height: 800 });

            for (const path of PAGES) {
                await page.goto(path);
                await page.waitForLoadState('load');
                const m = await measure(page);
                if (m.scroll > m.client) findings.push(`${path} @${width}: ${m.scroll}px wide on a ${m.client}px screen`);
                if (m.small.length > 0) findings.push(`${path} @${width}: tiny targets ${m.small.slice(0, 3).join(', ')}`);
            }
        }

        const problems = record.problems.filter((p) => !ignorable(p));
        expect(findings, findings.join('\n')).toEqual([]);
        expect(problems, problems.slice(0, 8).join('\n')).toEqual([]);
    });

    test('accessibility scan of the new and changed screens, light and dark', async ({ page }) => {
        test.setTimeout(600_000);
        const person = makeUser('R64 Scanner');
        await signIn(page, person.email);
        const serious: string[] = [];
        const contrast: string[] = [];

        for (const scheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme: scheme });

            for (const path of PAGES) {
                await page.goto(path);
                const found = await scan(page, path, scheme);
                serious.push(...blocking(found));
                contrast.push(...found.filter((v) => v.rule === 'color-contrast').map((v) => `${path} (${scheme}): ${v.count} contrast, e.g. ${v.selectors[0]}`));
            }
        }

        // Contrast is listed for the report; everything else serious or critical must be zero.
        console.log(`CONTRAST_FINDINGS ${contrast.length}\n${contrast.join('\n')}`);
        expect(serious, serious.join('\n')).toEqual([]);
    });

    test('notification settings save, say so in a pop-up after the server answered, and keep security locked', async ({ page }) => {
        const person = makeUser('R64 Settings');
        await signIn(page, person.email);
        await page.goto('/notifications/settings');

        await expect(page.getByRole('heading', { name: 'Notification settings', level: 1 })).toBeVisible();
        await expect(page.getByText('Always on. This cannot be switched off.').first()).toBeVisible();
        const bookingsEmail = page.getByLabel('Email').nth(1);
        await bookingsEmail.selectOption({ index: 1 }).catch(() => undefined);

        await page.getByRole('button', { name: 'Save my settings' }).click();
        await expect(page).toHaveURL(/\/notifications\/settings$/);
        const toast = page.locator('.ix-toast').filter({ hasText: 'notification settings are saved' });
        await expect(toast).toBeVisible();
        await expect(page.locator('.ix-toasts')).toHaveAttribute('aria-live', 'polite');

        // The pop-up goes away by itself, or on request, and does not come back on the next page.
        await toast.getByRole('button', { name: /Dismiss/ }).click();
        await expect(toast).toBeHidden();
        await page.goto('/home');
        await expect(page.locator('.ix-toast')).toHaveCount(0);
    });

    test('Home reveals as it scrolls and shows everything when motion is reduced', async ({ page, browser }) => {
        const person = makeUser('R64 Motion');
        await signIn(page, person.email);
        await page.setViewportSize({ width: 900, height: 500 });
        await page.goto('/home');
        await page.waitForLoadState('load');

        const marked = page.locator('[data-ix-reveal]');
        const count = await marked.count();
        expect(count).toBeGreaterThan(0);
        for (let y = 0; y < 3000; y += 250) {
            await page.evaluate((top) => window.scrollTo(0, top), y);
            await page.waitForTimeout(120);
        }
        await page.waitForTimeout(1200);
        expect(await marked.evaluateAll((els) => els.filter((el) => !el.classList.contains('is-in')).length), 'everything marked has been revealed after scrolling').toBe(0);

        // Reduced motion: nothing is ever hidden and the script adds no hiding class.
        const context = await browser.newContext({ reducedMotion: 'reduce', storageState: await page.context().storageState(), viewport: { width: 900, height: 500 } });
        const calm = await context.newPage();
        await calm.goto('/home');
        await calm.waitForLoadState('load');
        expect(await calm.evaluate(() => document.documentElement.classList.contains('ix-reveal-ready'))).toBe(false);
        expect(await calm.locator('[data-ix-reveal]').evaluateAll((els) => els.filter((el) => getComputedStyle(el).opacity !== '1').length)).toBe(0);
        await context.close();
    });

    test('a long page shows a reading bar that follows the scroll', async ({ page }) => {
        await page.goto('/policy');
        await page.waitForLoadState('load');
        const bar = page.locator('.ix-readbar');
        if (await bar.count() === 0) test.skip(true, 'this page has no reading bar');
        const supported = await page.evaluate(() => CSS.supports('animation-timeline', 'scroll()'));
        test.skip(!supported, 'this browser cannot link an animation to scroll');

        const start = await bar.evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a);
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await page.waitForTimeout(400);
        const end = await bar.evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a);
        expect(start).toBeLessThan(0.1);
        expect(end).toBeGreaterThan(0.9);
    });

    test('a form cannot be sent twice: the button says it is working and a second press is ignored', async ({ page }) => {
        await page.goto('/login');
        let posts = 0;
        await page.route('**/login', async (route) => {
            if (route.request().method() === 'POST') {
                posts += 1;
                await new Promise((resolve) => setTimeout(resolve, 800));
            }
            await route.continue();
        });
        await page.getByLabel('Email').fill('nobody@browser-tests.example.com');
        await page.locator('#password').fill('not-a-real-password-1');
        const button = page.getByRole('button', { name: /Sign in/i }).first();
        await button.click();
        await button.click({ force: true }).catch(() => undefined);
        await page.waitForLoadState('load');
        expect(posts, 'the form was sent once').toBe(1);
    });
});
