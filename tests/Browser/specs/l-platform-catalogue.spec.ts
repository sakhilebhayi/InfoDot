import { addMember, makeBusiness, makeUser, rows, unique } from '../support/app';
import { blocking, contrast, scan } from '../support/a11y';
import { expect, expectNoSidewaysScroll, openContext, screenshot, signIn, test } from '../support/fixtures';

/**
 * L. The Platforms catalogue (R-50). An owner finds a platform by need, reads what switching it on means, switches it
 * on, limits it to selected people and switches it off again. An employee sees the same catalogue with no switch. The
 * pages must read well at desktop and phone width in both colour schemes.
 */
for (const scheme of ['light', 'dark'] as const) {
    test.describe(`L. Platform catalogue, ${scheme} scheme`, () => {
        test.use({ colorScheme: scheme });

        test('an owner searches, switches a platform on, chooses people and switches it off; an employee cannot', async ({ page, browser }) => {
            const owner = makeUser('Owner Lindiwe');
            const employee = makeUser('Employee Thabo');
            const business = makeBusiness(owner, `Catalogue Works ${unique()}`, 'private');
            addMember(business, employee, 'editor');

            await signIn(page, owner.email);

            for (const [size, width, height] of [['desktop', 1440, 900], ['phone', 375, 812]] as const) {
                await page.setViewportSize({ width, height });
                await page.goto('/platforms');
                await expect(page.getByRole('heading', { level: 1 })).toHaveText('Platforms');
                await expect(page.getByRole('heading', { level: 2 }).first()).toContainText("Your company's platforms");

                // Every row carries exactly one action, and no row shows a price: none has been approved.
                const rowCount = await page.locator('.ix-catrow').count();
                expect(rowCount).toBeGreaterThan(10);
                expect(await page.locator('.ix-catrow [data-platform-action]').count()).toBe(rowCount);
                expect(await page.locator('main').innerText()).not.toMatch(/\b(ZAR|R)\s?\d/);
                await expectNoSidewaysScroll(page);

                const found = await scan(page, `platform-catalogue-${size}`, scheme);
                expect(blocking(found), blocking(found).join('\n')).toEqual([]);
                expect(contrast(found), contrast(found).join('\n')).toEqual([]);
                await screenshot(page, size, `platform-catalogue-${scheme}`);
            }

            // Search by need, at phone width.
            await page.getByRole('searchbox', { name: 'Search platforms by name or by what you need' }).fill('leave');
            await expect(page.locator('.ix-catrow[data-platform="hr"]')).toBeVisible();
            await expect(page.locator('.ix-catrow[data-platform="auction"]')).toHaveCount(0);
            await page.getByRole('button', { name: 'Clear the search and filters' }).click();
            await expect(page.locator('.ix-catrow[data-platform="auction"]')).toBeVisible();

            // The page of one platform, then the confirmation.
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.getByRole('link', { name: 'Switch on Dot.Forms' }).click();
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Switch on Dot.Forms for ${business.name}?`);
            await expect(page.getByText('It is free for now.')).toBeVisible();
            await expect(page.getByText('No charge will ever be made without new, explicit consent.')).toBeVisible();
            expect(rows('platform_entitlements', { team_id: business.id })).toHaveLength(0);

            for (const [size, width, height] of [['desktop', 1440, 900], ['phone', 375, 812]] as const) {
                await page.setViewportSize({ width, height });
                await expectNoSidewaysScroll(page);
                const found = await scan(page, `platform-switch-on-${size}`, scheme);
                expect(blocking(found), blocking(found).join('\n')).toEqual([]);
                expect(contrast(found), contrast(found).join('\n')).toEqual([]);
                await screenshot(page, size, `platform-switch-on-${scheme}`);
            }

            await page.getByRole('button', { name: 'Switch on Dot.Forms' }).click();
            await expect(page.getByRole('status')).toContainText(`Dot.Forms is switched on for ${business.name}`);
            expect(rows('platform_entitlements', { team_id: business.id }).map((row) => [row.platform_key, row.status, row.source])).toEqual([['forms', 'active', 'unpriced_free']]);

            // Selected people: the employee is ticked, and the history records it.
            await page.getByRole('radio', { name: /Selected people/ }).check();
            await page.getByRole('checkbox', { name: employee.name }).check();
            await page.getByRole('button', { name: 'Save who may open it' }).click();
            await expect(page.getByRole('status')).toContainText('the 1 person you chose');
            await expect(page.getByRole('cell', { name: `Added ${employee.name}` })).toBeVisible();
            expect(rows('platform_user_access', { team_id: business.id }).map((row) => row.user_id)).toEqual([employee.id]);

            for (const [size, width, height] of [['desktop', 1440, 900], ['phone', 375, 812]] as const) {
                await page.setViewportSize({ width, height });
                await expectNoSidewaysScroll(page);
                const found = await scan(page, `platform-detail-${size}`, scheme);
                expect(blocking(found), blocking(found).join('\n')).toEqual([]);
                expect(contrast(found), contrast(found).join('\n')).toEqual([]);
                await screenshot(page, size, `platform-detail-${scheme}`);
            }

            // The employee: the same catalogue, the platform opens for them, and there is nothing to switch.
            const theirs = await openContext(browser, { colorScheme: scheme });
            const employeePage = await theirs.newPage();
            await signIn(employeePage, employee.email);
            await employeePage.goto('/platforms');
            await expect(employeePage.locator('.ix-catrow[data-platform="forms"] [data-platform-action="open"]')).toBeVisible();
            await expect(employeePage.locator('[data-platform-action="switch_on"]')).toHaveCount(0);
            await employeePage.goto('/platforms/hr');
            await expect(employeePage.getByText(`Ask ${owner.name}`)).toBeVisible();
            await expect(employeePage.getByRole('heading', { name: 'History' })).toHaveCount(0);
            expect((await employeePage.goto('/platforms/hr/switch-on'))?.status()).toBe(403);
            await theirs.close();

            // Switching off, after reading what it means.
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.getByRole('link', { name: 'Switch off Dot.Forms' }).click();
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Switch off Dot.Forms for ${business.name}?`);
            await expect(page.getByText('Nothing is deleted.', { exact: false }).first()).toBeVisible();
            await page.getByRole('button', { name: 'Switch off Dot.Forms' }).click();
            await expect(page.getByRole('status')).toContainText('Nothing was deleted');
            expect(rows('platform_entitlements', { team_id: business.id }).map((row) => row.status)).toEqual(['cancelled']);
        });
    });
}
