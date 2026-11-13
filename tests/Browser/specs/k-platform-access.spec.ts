import { addMember, makeBusiness, makeUser, rows, unique } from '../support/app';
import { blocking, contrast, scan } from '../support/a11y';
import { expect, expectNoSidewaysScroll, openContext, screenshot, signIn, test } from '../support/fixtures';

/**
 * K. A platform the company has not switched on (R-50). The launch is refused with a page that says why and offers
 * the one next step: the owner can switch a free platform on, an employee is told whom to ask. The page must read
 * well at desktop and phone width in both colour schemes.
 */
for (const scheme of ['light', 'dark'] as const) {
    test.describe(`K. Platform access, ${scheme} scheme`, () => {
        test.use({ colorScheme: scheme });

        test('the owner is told why, switches the platform on, and an employee is told whom to ask', async ({ page, browser }) => {
            const owner = makeUser('Owner Lindiwe');
            const employee = makeUser('Employee Thabo');
            const business = makeBusiness(owner, `Access Works ${unique()}`, 'private');
            addMember(business, employee, 'editor');

            // The employee: no button, the owner's name.
            const theirs = await openContext(browser, { colorScheme: scheme });
            const employeePage = await theirs.newPage();
            await signIn(employeePage, employee.email);
            expect((await employeePage.goto('/go/forms'))?.status()).toBe(403);
            await expect(employeePage.getByRole('heading', { level: 1 })).toContainText('has not switched on Dot.Forms');
            await expect(employeePage.getByText(`Ask ${owner.name}`)).toBeVisible();
            await expect(employeePage.getByRole('button', { name: 'Switch on Dot.Forms' })).toHaveCount(0);
            await theirs.close();

            // The owner, at desktop and phone width.
            await signIn(page, owner.email);
            for (const [size, width, height] of [['desktop', 1440, 900], ['phone', 375, 812]] as const) {
                await page.setViewportSize({ width, height });
                expect((await page.goto('/go/forms'))?.status()).toBe(403);
                await expect(page.getByRole('heading', { level: 1 })).toHaveText(`${business.name} has not switched on Dot.Forms`);
                await expect(page.getByText('free while its pricing is being finalised')).toBeVisible();
                await expect(page.getByRole('button', { name: 'Switch on Dot.Forms' })).toBeVisible();
                await expectNoSidewaysScroll(page);

                const found = await scan(page, `platform-access-${size}`, scheme);
                expect(blocking(found), blocking(found).join('\n')).toEqual([]);
                expect(contrast(found), contrast(found).join('\n')).toEqual([]);
                await screenshot(page, size, `platform-access-${scheme}`);
            }

            // A core platform is never refused.
            expect((await page.request.get('/platforms/finance/access', { maxRedirects: 0 })).status()).toBe(302);

            // Switching on returns to the launch: the page that posts to the platform (no request leaves for a live host
            // during a run, so only the entitlement and the launch page's own answer are checked).
            await page.getByRole('button', { name: 'Switch on Dot.Forms' }).click();
            await expect.poll(() => rows('platform_entitlements', { team_id: business.id }).length).toBe(1);
            const launch = await page.request.get('/go/forms');
            expect(launch.status()).toBe(200);
            expect(await launch.text()).toContain('/auth/ecosystem');

            const held = rows('platform_entitlements', { team_id: business.id });
            expect(held.map((row) => [row.platform_key, row.status, row.source])).toEqual([['forms', 'active', 'unpriced_free']]);
        });
    });
}
