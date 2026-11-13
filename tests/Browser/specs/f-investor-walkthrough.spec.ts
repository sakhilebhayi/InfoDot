import fs from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';
import { dataDir, screensDir } from '../support/env';
import { brokenImages, expect, openContext, screenshot, signIn, test, watch, type Watch } from '../support/fixtures';
import { demo, moderatorStops, ownerStops, publicStops, type Stop } from '../support/pages';

/** Open one page of the walk, check it, photograph it. Everything wrong is collected, so one run lists it all. */
async function visit(page: Page, record: Watch, stop: Stop, size: string, widths: Record<string, number>): Promise<void> {
    const response = await page.goto(stop.path);
    const status = response?.status() ?? 0;
    if (status >= 400 || status === 0) {
        record.problems.push(`${stop.path} answered ${status}`);
    }
    await page.waitForLoadState('networkidle');
    record.pages.push(stop.path);

    if (await page.getByRole('heading', { level: 1 }).count() === 0) {
        record.problems.push(`${stop.path} has no main heading`);
    }
    for (const src of await brokenImages(page)) {
        record.problems.push(`broken image on ${stop.path}: ${src}`);
    }

    const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    widths[`${size}:${stop.path}`] = scroll - client;
    await screenshot(page, size, stop.name);
}

test.describe('F. Investor walkthrough', () => {
    test('one continuous visit with nothing broken on any page, photographed at desktop and phone size', async ({ page, browser }) => {
        test.setTimeout(600_000);
        // Last run's pictures go first, so the folder only ever holds this run.
        for (const size of ['desktop', 'phone']) {
            fs.rmSync(path.join(screensDir, size), { recursive: true, force: true });
        }
        const widths: Record<string, number> = {};
        const record = watch(page);

        // A clean browser: the public site and the pages that explain the product.
        for (const stop of publicStops()) {
            await visit(page, record, stop, 'desktop', widths);
        }

        // The demonstration owner signs in and walks the product.
        await signIn(page, demo.owner);
        for (const stop of ownerStops()) {
            await visit(page, record, stop, 'desktop', widths);
        }

        // The search that is on every page.
        await page.goto('/home');
        await page.keyboard.press('ControlOrMeta+k');
        const search = page.getByRole('combobox', { name: /Search questions and solutions/ });
        await expect(search).toBeFocused();
        await search.fill('VAT');
        await expect(page.getByRole('option').filter({ hasText: 'How do I register a small business for VAT?' }).or(page.getByRole('link', { name: /How do I register a small business for VAT\?/ })).first()).toBeVisible();
        await screenshot(page, 'desktop', 'search-open');
        await page.keyboard.press('Escape');

        // Then a moderator's view, in a browser of their own.
        const theirs = await openContext(browser, { viewport: { width: 1440, height: 900 } });
        const moderatorPage = await theirs.newPage();
        const moderatorRecord = watch(moderatorPage);
        await signIn(moderatorPage, demo.moderator);
        for (const stop of moderatorStops()) {
            await visit(moderatorPage, moderatorRecord, stop, 'desktop', widths);
        }
        await theirs.close();

        // The same pages at phone size, for the reviewer's pictures (and held to the same standard).
        const phone = await openContext(browser, { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
        const phonePage = await phone.newPage();
        const phoneRecord = watch(phonePage);
        for (const stop of publicStops()) {
            await visit(phonePage, phoneRecord, stop, 'phone', widths);
        }
        await signIn(phonePage, demo.owner);
        for (const stop of ownerStops()) {
            await visit(phonePage, phoneRecord, stop, 'phone', widths);
        }
        await phone.close();

        const phoneModerator = await openContext(browser, { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
        const phoneModeratorPage = await phoneModerator.newPage();
        const phoneModeratorRecord = watch(phoneModeratorPage);
        await signIn(phoneModeratorPage, demo.moderator);
        for (const stop of moderatorStops()) {
            await visit(phoneModeratorPage, phoneModeratorRecord, stop, 'phone', widths);
        }
        await phoneModerator.close();

        fs.writeFileSync(path.join(dataDir, 'walkthrough-widths.json'), JSON.stringify(widths, null, 2));

        const problems = [
            ...record.problems.map((p) => `[desktop] ${p}`),
            ...moderatorRecord.problems.map((p) => `[desktop, moderator] ${p}`),
            ...phoneRecord.problems.map((p) => `[phone] ${p}`),
            ...phoneModeratorRecord.problems.map((p) => `[phone, moderator] ${p}`),
        ];
        expect(problems, problems.join('\n')).toEqual([]);
        expect(record.pages.length + moderatorRecord.pages.length).toBe(publicStops().length + ownerStops().length + moderatorStops().length);
    });
});
