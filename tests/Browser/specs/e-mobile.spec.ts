import fs from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';
import { fixture, rows, unique } from '../support/app';
import { dataDir } from '../support/env';
import { expect, register, signIn, signOut, test, verifyByEmailLink } from '../support/fixtures';

type Finding = { page: string; kind: 'width' | 'button'; what: string };

const findingsFile = (project: string): string => path.join(dataDir, `handheld-${project}.json`);

/**
 * On every page the journey stops at: nothing wider than the screen, and every primary button big enough for a
 * thumb (44 by 44 CSS pixels). Findings are collected so that one run reports every page, then asserted together.
 */
async function check(page: Page, findings: Finding[]): Promise<void> {
    await page.waitForLoadState('load');
    const where = new URL(page.url()).pathname.replace(/[0-9a-f]{8}-[0-9a-f-]{27}/, '{uuid}').replace(/\/\d+(?=\/|$)/g, '/{id}');

    const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    if (scroll > client) {
        findings.push({ page: where, kind: 'width', what: `page is ${scroll}px wide on a ${client}px screen` });
    }

    const small = await page.evaluate(() => {
        const out: string[] = [];
        for (const el of Array.from(document.querySelectorAll<HTMLElement>('.ix-btn--primary, .ix-askbar__go'))) {
            const box = el.getBoundingClientRect();
            const style = getComputedStyle(el);
            if (box.width === 0 || box.height === 0 || style.visibility === 'hidden' || el.closest('[hidden], details:not([open]) > :not(summary)')) {
                continue;
            }
            if (box.height < 43.5 || box.width < 43.5) {
                out.push(`"${(el.innerText || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 40)}" is ${Math.round(box.width)}x${Math.round(box.height)}`);
            }
        }

        return Array.from(new Set(out));
    });
    for (const button of small) {
        findings.push({ page: where, kind: 'button', what: `primary button ${button}` });
    }
}

test.describe('E. On a phone or tablet', () => {
    test.describe.configure({ mode: 'serial' });

    test('register, sign in, use the menus, upload a photo, ask with a file, run a business, search the directory', async ({ page }, testInfo) => {
        const findings: Finding[] = [];

        // Register and verify.
        await page.goto('/');
        await check(page, findings);
        await page.goto('/register');
        await check(page, findings);
        const email = await register(page, 'Zanele Mobile');
        await check(page, findings);
        await verifyByEmailLink(page, email);
        await expect(page).toHaveURL(/\/home/);
        await check(page, findings);

        // Out and in again.
        await signOut(page);
        await page.goto('/login');
        await check(page, findings);
        await signIn(page, email);

        // The menus: the company menu, the tab row, the account menu.
        await page.getByRole('button', { name: 'Company menu' }).click();
        await page.getByRole('link', { name: 'People' }).first().click();
        await expect(page.getByRole('heading', { level: 1, name: 'People' })).toBeVisible();
        await check(page, findings);
        const primary = page.getByRole('navigation', { name: 'Primary' });
        for (const [tab, heading] of [['Knowledge', 'Questions'], ['Library', 'Verified library'], ['Seminars', 'Seminars'], ['Platforms', 'Platforms'], ['Analytics', /.+/]] as const) {
            await primary.getByRole('link', { name: tab, exact: true }).click();
            await expect(page.getByRole('heading', { level: 1, name: heading }).first()).toBeVisible();
            await expect(primary.getByRole('link', { name: tab, exact: true })).toBeInViewport();
            await check(page, findings);
        }
        await page.getByRole('link', { name: /^Notifications/ }).click();
        await expect(page).toHaveURL(/\/notifications$/);
        await check(page, findings);

        // A profile picture, straight from the camera roll.
        await page.getByRole('button', { name: 'Account menu' }).click();
        await page.getByRole('link', { name: 'Settings' }).click();
        await check(page, findings);
        const profile = page.locator('form').filter({ has: page.locator('#photo') });
        await profile.locator('#photo').setInputFiles(fixture('photo-4032x3024.jpg'));
        await expect(profile.getByRole('button', { name: 'Save' })).toBeEnabled({ timeout: 30_000 });
        await profile.getByRole('button', { name: 'Save' }).click();
        await expect(page.locator('header .ix-avatar img')).toBeVisible({ timeout: 30_000 });
        expect(rows('users', { email })[0].profile_photo_path).toBeTruthy();

        // A question with a file attached.
        const question = `Which till roll fits the card machine ${unique()}?`;
        await primary.getByRole('link', { name: 'Knowledge', exact: true }).click();
        await page.getByLabel('Ask', { exact: true }).fill(question);
        await page.getByRole('button', { name: 'Ask a question' }).click();
        await expect(page).toHaveURL(/\/questions\/ask\?question=/);
        await check(page, findings);
        await page.getByLabel('Details').fill('The card machine came without a spare roll and the label has worn off. The photograph shows the old roll.');
        await page.getByLabel('Add attachment files').setInputFiles(fixture('evidence-1600x1200.jpg'));
        await expect(page.getByLabel('Caption for evidence-1600x1200.jpg')).toBeVisible({ timeout: 30_000 });
        await check(page, findings);
        await page.getByRole('group', { name: 'Topics' }).getByRole('checkbox', { name: 'Technology and tools' }).check();
        await page.getByRole('button', { name: 'Post question' }).click();
        await expect(page).toHaveURL(/\/questions$/);
        const saved = rows('questions', { question })[0];
        expect(rows('attachments', { attachable_id: saved.id, attachable_type: 'questions' })).toHaveLength(1);
        await page.getByRole('link', { name: question }).first().click();
        await expect(page.getByRole('heading', { level: 1, name: question })).toBeVisible();
        await check(page, findings);

        // A business: created, then managed.
        const name = `Zanele Spaza ${unique()}`;
        await page.getByRole('button', { name: 'Company menu' }).click();
        await page.locator('.ix-pop__panel').getByRole('link', { name: 'Create a company' }).click();   // the company menu's link; Getting started on Home offers the same
        await check(page, findings);
        await page.getByLabel('Company name').fill(name);
        await page.getByLabel('What the business does').fill('A spaza shop selling groceries, airtime and prepaid electricity in Soshanguve.');
        await page.getByRole('radio', { name: /^Anyone/ }).check();
        await page.getByRole('button', { name: 'Create company' }).click();
        await expect(page).toHaveURL(/\/business\/[0-9a-f-]{36}\/edit/);
        await check(page, findings);
        const team = rows('teams', { name })[0];
        const logo = page.locator('#logo form[data-crop-form]');
        await logo.locator('[data-crop-file]').setInputFiles(fixture('logo-1200x1200.png'));
        await expect(logo.locator('[data-crop-frame]')).toBeVisible();
        await logo.getByRole('button', { name: /Put the logo up/ }).click();
        await expect(page.getByRole('status').filter({ hasText: 'The logo is on the profile' })).toBeVisible();
        await page.getByLabel('Location', { exact: true }).fill('Soshanguve, Gauteng');
        await page.getByRole('button', { name: 'Save the profile' }).click();
        await expect(page.getByRole('status').first()).toBeVisible();
        expect(rows('team_profiles', { team_id: team.id })[0].location).toBe('Soshanguve, Gauteng');

        // The directory: browse, search, open.
        await primary.getByRole('link', { name: 'Businesses', exact: true }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Businesses' })).toBeVisible();
        await check(page, findings);
        await page.getByRole('searchbox', { name: 'What do you need, and where?' }).fill(name);
        await page.getByRole('search', { name: 'Search businesses' }).getByRole('button', { name: 'Search' }).click();
        await check(page, findings);
        await page.getByRole('link', { name: new RegExp(name) }).first().click();
        await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
        await expect(page.getByText('Soshanguve, Gauteng').first()).toBeVisible();
        await check(page, findings);
        await page.getByRole('link', { name: 'Inbox', exact: true }).last().click();
        await expect(page.getByRole('heading', { level: 1, name: `${name} inbox` })).toBeVisible();
        await check(page, findings);

        fs.writeFileSync(findingsFile(testInfo.project.name), JSON.stringify(findings, null, 2));

        // No page of the journey may scroll sideways.
        const wide = Array.from(new Set(findings.filter((f) => f.kind === 'width').map((f) => `${f.page}: ${f.what}`)));
        expect(wide, wide.join('\n')).toEqual([]);
    });

    test('every primary button met on that journey is at least 44 by 44', async ({}, testInfo) => {
        // The Yard keeps its 36px buttons for a mouse and makes every control at least 44px on a touch screen or a narrow
        // window (resources/css/infodot.css, the touch block at the end). This guards that: UX_FINDINGS.md U-01, closed.
        const findings: Finding[] = JSON.parse(fs.readFileSync(findingsFile(testInfo.project.name), 'utf8'));
        const small = Array.from(new Set(findings.filter((f) => f.kind === 'button').map((f) => `${f.page}: ${f.what}`)));
        expect(small, small.join('\n')).toEqual([]);
    });
});
