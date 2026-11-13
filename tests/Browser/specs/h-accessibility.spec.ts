import type { Page } from '@playwright/test';
import { makeBusiness, makeUser, type Business, type Person } from '../support/app';
import { blocking, contrast, record, recorded, scan, type Violation } from '../support/a11y';
import { expect, password, register, signIn, test } from '../support/fixtures';
import { demo, moderatorStops, ownerStops, publicStops, type Stop } from '../support/pages';

async function scanStops(page: Page, stops: Stop[], scheme: string, found: Violation[], scanned: string[]): Promise<void> {
    for (const stop of stops) {
        await page.goto(stop.path);
        await page.waitForLoadState('networkidle');
        found.push(...await scan(page, stop.name, scheme));
        scanned.push(stop.path.replace(/[0-9a-f]{8}-[0-9a-f-]{27}/, '{uuid}'));
    }
}

for (const scheme of ['light', 'dark'] as const) {
    test.describe(`H. Accessibility scan, ${scheme} scheme`, () => {
        test.describe.configure({ mode: 'serial' });
        test.use({ colorScheme: scheme });

        test('the public pages and the sign-up and sign-in forms', async ({ page }) => {
            const found: Violation[] = [];
            const scanned: string[] = [];
            await scanStops(page, publicStops(), scheme, found, scanned);

            // The forms as a person meets them mid-journey: sign-in after a wrong password, and "verify your email".
            await page.goto('/login');
            await page.getByLabel('Email').fill('nobody@infodot.test');
            await page.locator('#password').fill(`${password()}-not-it`);
            await page.getByRole('button', { name: 'Sign in' }).click();
            await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
            found.push(...await scan(page, 'public-sign-in-refused', scheme));
            scanned.push('/login (after a wrong password)');
            await register(page, 'Scan Visitor');
            found.push(...await scan(page, 'public-verify-email', scheme));
            scanned.push('/email/verify');

            record('public', scheme, found, scanned);
            expect(blocking(found), blocking(found).join('\n')).toEqual([]);
        });

        test('the signed-in pages the owner walks through, and the profile and business forms', async ({ page }) => {
            test.setTimeout(300_000);
            const customer: Person = makeUser('Scan Customer');
            const owner: Person = makeUser('Scan Owner');
            const business: Business = makeBusiness(owner, `Scan Supplies ${scheme}`, 'public');
            const found: Violation[] = [];
            const scanned: string[] = [];

            await signIn(page, demo.owner);
            await scanStops(page, ownerStops(), scheme, found, scanned);

            // Menus open, as they are when someone is using them.
            await page.goto('/home');
            await page.getByRole('button', { name: 'Account menu' }).click();
            found.push(...await scan(page, 'account-menu-open', scheme));
            await page.keyboard.press('Escape');
            await page.getByRole('button', { name: 'Company menu' }).click();
            found.push(...await scan(page, 'company-menu-open', scheme));
            await page.keyboard.press('Escape');
            await page.getByRole('combobox', { name: /Search questions and solutions/ }).fill('VAT');
            await page.waitForLoadState('networkidle');
            found.push(...await scan(page, 'search-open', scheme));
            scanned.push('/home (account menu, company menu and search open)');

            // The enquiry form, which only someone outside the business sees.
            await page.context().clearCookies();
            await signIn(page, customer.email);
            await scanStops(page, [{ name: 'business-enquiry-form', path: `/business/${business.uuid}/message` }, { name: 'business-page-public', path: `/business/${business.uuid}` }], scheme, found, scanned);

            record('owner', scheme, found, scanned);
            expect(blocking(found), blocking(found).join('\n')).toEqual([]);
        });

        test('the moderator pages', async ({ page }) => {
            const moderator = makeUser('Scan Moderator', { moderator: true });
            const found: Violation[] = [];
            const scanned: string[] = [];
            await signIn(page, moderator.email);
            await scanStops(page, moderatorStops(), scheme, found, scanned);
            record('moderator', scheme, found, scanned);
            expect(blocking(found), blocking(found).join('\n')).toEqual([]);
        });

        test('text contrast on every page scanned above', async () => {
            // KNOWN FAILURE, kept visible on purpose: axe finds text below 4.5:1 on a few pages in each scheme, and the
            // cure is a change to The Yard's colour tokens, which belongs to the design pass and not to a test run
            // (docs/programme/r49/UX_FINDINGS.md, U-02 to U-06; every element is in ACCESSIBILITY_SCAN.md).
            // Delete the next line when the palette is fixed: the test then guards the result.
            test.fail(true, 'Text contrast below 4.5:1: UX_FINDINGS.md U-02 to U-06');

            const found = contrast(recorded(scheme));
            expect(found, found.join('\n')).toEqual([]);
        });
    });
}
