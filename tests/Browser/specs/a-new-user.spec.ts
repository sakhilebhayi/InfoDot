import { fixture, rows } from '../support/app';
import { expect, register, signIn, signOut, test, verifyByEmailLink } from '../support/fixtures';
import { accountLink, goTo } from '../support/nav';

test.describe('A. A new user', () => {
    test('visits, registers, verifies, sets up a profile, moves around, signs out and back in', async ({ page }) => {
        // The public site, walked through its own navigation.
        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        const about = page.getByRole('navigation', { name: 'About InfoDot' });
        await about.getByRole('link', { name: 'Questions people ask' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Questions people ask' })).toBeVisible();
        await page.getByRole('navigation', { name: 'About InfoDot' }).getByRole('link', { name: 'Browse public answers' }).click();
        await expect(page).toHaveURL(/\/solution-results/);
        for (const [link, heading] of [['What it does', 'What InfoDot does'], ['About', 'About InfoDot'], ['Contact', 'Tell us what you need.']] as const) {
            await page.getByRole('contentinfo').getByRole('link', { name: link, exact: true }).click();
            await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
        }

        // Register, then verify with the link from the email.
        await page.getByRole('link', { name: 'Create account' }).click();
        await expect(page).toHaveURL(/\/register$/);
        const email = await register(page, 'Thandi Newcomer');
        expect(rows('users', { email })[0].email_verified_at).toBeNull();
        await verifyByEmailLink(page, email);
        // R-62: a newly confirmed person is taken to set up their company first; Home is one step away.
        await expect(page).toHaveURL(/\/(home|onboarding\/business)/);
        await page.goto('/home');
        await expect(page).toHaveURL(/\/(home|onboarding\/business)/);
        expect(rows('users', { email })[0].email_verified_at).not.toBeNull();
        await expect(page.getByRole('button', { name: 'Account menu' })).toBeVisible();

        // Profile: a new name and a photograph straight off a phone camera.
        await page.getByRole('button', { name: 'Account menu' }).click();
        await page.getByRole('link', { name: 'Settings', exact: true }).click();
        await expect(page).toHaveURL(/\/user\/profile\/edit$/);
        const profile = page.locator('form').filter({ has: page.locator('#photo') });
        await profile.getByLabel('Name').fill('Thandi Mokoena');
        await profile.locator('#photo').setInputFiles(fixture('photo-4032x3024.jpg'));
        await expect(profile.getByRole('button', { name: 'Save' })).toBeEnabled({ timeout: 30_000 });
        await profile.getByRole('button', { name: 'Save' }).click();
        await expect(page.locator('header .dh-avatar img')).toBeVisible({ timeout: 30_000 });
        const saved = rows('users', { email })[0];
        expect(saved.name).toBe('Thandi Mokoena');
        expect(saved.profile_photo_path).toBeTruthy();
        const avatar = page.locator('header .dh-avatar img');
        await expect.poll(() => avatar.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true);

        // The signed-in areas, through the top navigation.
        // (R-52: Home is a link; the others are items inside the bar's groups, which `goTo` opens.)
        for (const [tab, group, heading] of [['Questions', 'Knowledge', 'Questions'], ['Library', 'Knowledge', 'Verified library'], ['Seminars', 'Knowledge', 'Seminars'], ['All platforms', 'Platforms', 'Platforms'], ['Businesses', 'Network', 'Businesses'], ['People', 'Network', 'People'], ['Analytics', 'Platforms', /.+/], ['Home', undefined, /.+/]] as const) {
            await goTo(page, tab, group);
            await expect(page.locator('header.dh [aria-current="page"]').first()).toBeAttached();
            await expect(page.getByRole('heading', { level: 1, name: heading }).first()).toBeVisible();
        }

        // Out, and in again.
        await signOut(page);
        await signIn(page, email);
        await page.getByRole('button', { name: 'Account menu' }).click();
        await expect(page.getByText(email).first()).toBeVisible();
    });
});
