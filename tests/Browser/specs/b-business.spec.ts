import type { Page } from '@playwright/test';
import { fixture, makeUser, rows, unique } from '../support/app';
import { expect, openContext, signIn, signOut, test } from '../support/fixtures';

/** Choose a picture in one of the crop forms, move the frame, and put it up. */
async function putPicture(page: Page, section: string, file: string, button: RegExp, done: string): Promise<void> {
    const form = page.locator(`${section} form[data-crop-form]`);
    await form.locator('[data-crop-file]').setInputFiles(fixture(file));
    await expect(form.locator('[data-crop-frame]')).toBeVisible();
    await expect.poll(() => form.locator('[data-crop-img]').evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
    await form.locator('[data-crop-zoom]').fill('1.5');
    await form.getByRole('button', { name: button }).click();
    await expect(page.getByRole('status').filter({ hasText: done })).toBeVisible();
}

async function imageLoaded(page: Page, alt: string): Promise<void> {
    const img = page.getByRole('img', { name: alt });
    await expect(img).toBeVisible();
    await expect.poll(() => img.evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 0)).toBe(true);
}

test.describe('B. A business', () => {
    test('is created, dressed, found in the directory, enquired of, answered and edited', async ({ page, browser }) => {
        const owner = makeUser('Owner Olwethu');
        const customer = makeUser('Customer Cebo');
        const name = `Kestrel Borehole Drilling ${unique()}`;

        // Create the business from the company menu.
        await signIn(page, owner.email);
        await page.getByRole('button', { name: 'Company menu' }).click();
        await page.getByRole('link', { name: 'Create a company' }).click();
        await expect(page).toHaveURL(/\/teams\/create$/);
        await page.getByLabel('Company name').fill(name);
        await page.getByLabel('What the business does').fill('Borehole drilling, pump installation and water testing for farms and homes in Mpumalanga.');
        await page.getByRole('radio', { name: /^Anyone/ }).check();
        await page.getByRole('button', { name: 'Create company' }).click();

        // It lands on the listing, which says where the business stands.
        await expect(page).toHaveURL(/\/business\/[0-9a-f-]{36}\/edit#directory$/);
        await expect(page.getByRole('status').filter({ hasText: `${name} is listed for anyone` })).toBeVisible();
        const team = rows('teams', { name })[0];
        expect(team.personal_team).toBeFalsy();
        expect(rows('team_profiles', { team_id: team.id })[0].visibility).toBe('public');

        // Logo and cover, each through its crop frame.
        await putPicture(page, '#logo', 'logo-1200x1200.png', /Put the logo up/, 'The logo is on the profile');
        await putPicture(page, '#cover', 'cover-3200x1000.jpg', /Put the cover up/, 'The cover photograph is on the profile');

        // The profile itself.
        await page.getByLabel('Trading name').fill('Kestrel Drilling');
        await page.getByLabel('Location', { exact: true }).fill('Hazyview, Mpumalanga');
        await page.getByLabel('Service areas').fill('Mpumalanga, Limpopo');
        await page.getByRole('button', { name: 'Save the profile' }).click();
        await expect(page.getByRole('status').first()).toBeVisible();
        const profile = rows('team_profiles', { team_id: team.id })[0];
        expect(profile.trading_name).toBe('Kestrel Drilling');
        expect(profile.location).toBe('Hazyview, Mpumalanga');

        // Signed out, a visitor finds it in the directory by searching and opens its page.
        await signOut(page);
        await page.goto('/businesses');
        await page.getByRole('searchbox', { name: 'What do you need, and where?' }).fill(name);
        await page.getByRole('search', { name: 'Search businesses' }).getByRole('button', { name: 'Search' }).click();
        await page.getByRole('link', { name: new RegExp(name) }).first().click();
        await expect(page).toHaveURL(new RegExp(`/business/${team.uuid}$`));
        await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
        await imageLoaded(page, `${name} logo`);
        await imageLoaded(page, `${name} — cover photograph`);
        await expect(page.getByText('Hazyview, Mpumalanga').first()).toBeVisible();

        // Another signed-in person sends the business an enquiry.
        const theirs = await openContext(browser);
        const customerPage = await theirs.newPage();
        await signIn(customerPage, customer.email);
        await customerPage.goto(`/business/${team.uuid}`);
        await customerPage.getByRole('link', { name: 'Message business' }).click();
        await customerPage.getByLabel('Subject').fill('Quote for a borehole in Hazyview');
        await customerPage.getByLabel('Your message').fill('We need a borehole drilled on a smallholding outside Hazyview before the end of January. Could you quote for drilling and a pump?');
        await customerPage.getByRole('button', { name: new RegExp(`Send to ${name}`) }).click();
        await expect(customerPage).toHaveURL(/\/inbox\/[0-9a-f-]{36}/);
        await expect(customerPage.getByText('We need a borehole drilled')).toBeVisible();
        const enquiry = rows('enquiries', { team_id: team.id })[0];
        expect(enquiry.subject).toBe('Quote for a borehole in Hazyview');

        // The owner finds it in the business inbox and replies.
        await signIn(page, owner.email);
        await page.goto(`/business/${team.uuid}`);
        await page.getByRole('link', { name: 'Inbox', exact: true }).last().click();
        await expect(page.getByRole('heading', { level: 1, name: `${name} inbox` })).toBeVisible();
        await page.getByRole('link', { name: 'Quote for a borehole in Hazyview' }).click();
        await expect(page.getByText('We need a borehole drilled')).toBeVisible();
        await page.getByLabel('Write').fill('Thank you. We can drill in the second week of January; a site visit comes first. Which day suits you?');
        await page.getByRole('button', { name: /Reply to Customer Cebo/ }).click();
        await expect(page.getByRole('list', { name: 'Conversation' }).getByText('We can drill in the second week of January')).toBeVisible();
        expect(rows('enquiry_messages', { enquiry_id: enquiry.id }).map((m) => m.kind)).toEqual(expect.arrayContaining(['customer', 'business']));

        // The customer sees the answer, from the business.
        await customerPage.reload();
        await expect(customerPage.getByRole('list', { name: 'Conversation' }).getByText('We can drill in the second week of January')).toBeVisible();
        await theirs.close();

        // The owner changes a detail and the public page shows it.
        await page.goto(`/business/${team.uuid}/edit`);
        await page.getByLabel('Location', { exact: true }).fill('White River, Mpumalanga');
        await page.getByRole('button', { name: 'Save the profile' }).click();
        await expect(page.getByRole('status').first()).toBeVisible();
        await signOut(page);
        await page.goto(`/business/${team.uuid}`);
        await expect(page.getByText('White River, Mpumalanga').first()).toBeVisible();
        await expect(page.getByText('Hazyview, Mpumalanga')).toHaveCount(0);
    });
});
