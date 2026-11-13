import { fixture, makeBusiness, makeUser, rows, unique } from '../support/app';
import { expect, openContext, refusal, signIn, test } from '../support/fixtures';

test.describe('D. Permissions', () => {
    test('what belongs to someone else is refused with "not found" or the sign-in page, never a server error', async ({ page, browser }) => {
        const ordinary = makeUser('Ordinary Onke');
        const owner = makeUser('Owner Ofentse');
        const rival = makeUser('Rival Rethabile');
        const moderator = makeUser('Moderator Mbali', { moderator: true });
        const privateBusiness = makeBusiness(owner, `Private Holdings ${unique()}`, 'private');
        const rivalBusiness = makeBusiness(rival, `Rival Trading ${unique()}`, 'public');

        // The owner asks a company-only question with a picture: that picture is the "attachment of another user".
        await signIn(page, owner.email);
        const question = `Where is the signed lease kept ${unique()}?`;
        await page.goto('/questions/ask');
        await page.getByLabel('Question', { exact: true }).fill(question);
        await page.getByLabel('Details').fill('The landlord wants a copy of the signed lease and I cannot find the original.');
        await page.getByLabel('Add attachment files').setInputFiles(fixture('evidence-1600x1200.jpg'));
        await expect(page.getByLabel('Caption for evidence-1600x1200.jpg')).toBeVisible({ timeout: 30_000 });
        await page.getByRole('group', { name: 'Topics' }).getByRole('checkbox', { name: 'Legal and compliance' }).check();
        await page.getByRole('button', { name: 'Post question' }).click();
        await expect(page).toHaveURL(/\/questions$/);
        const saved = rows('questions', { question })[0];
        const attachment = rows('attachments', { attachable_id: saved.id, attachable_type: 'questions' })[0];

        // Controls: the owner does reach all of it, so a refusal below is a refusal and not a broken page.
        for (const address of [`/business/${privateBusiness.uuid}`, `/business/${privateBusiness.uuid}/edit`, `/business/${privateBusiness.uuid}/inbox`, `/question/view/${saved.id}`, `/attachments/${attachment.uuid}`]) {
            expect((await page.request.get(address)).status(), `the owner opens ${address}`).toBe(200);
        }

        // An ordinary signed-in person.
        const theirs = await openContext(browser);
        const ordinaryPage = await theirs.newPage();
        await signIn(ordinaryPage, ordinary.email);
        const notFound = [
            `/business/${privateBusiness.uuid}`,
            `/business/${privateBusiness.uuid}/edit`,
            `/business/${privateBusiness.uuid}/inbox`,
            `/business/${privateBusiness.uuid}/message`,
            `/question/view/${saved.id}`,
            `/attachments/${attachment.uuid}`,
            `/attachments/${attachment.uuid}/thumb`,
            `/business/${rivalBusiness.uuid}/edit`,
            `/business/${rivalBusiness.uuid}/inbox`,
            `/business/${rivalBusiness.uuid}/insights`,
            `/business/${rivalBusiness.uuid}/verification`,
            '/moderation',
            '/moderation/library',
            '/moderation/topics',
            '/moderation/trust',
            '/moderation/verifications',
            '/moderation/identity',
        ];
        for (const address of notFound) {
            const answer = await refusal(ordinaryPage, address);
            expect([403, 404], `${address} answered ${answer.status}`).toContain(answer.status);
        }
        // What is public stays open to them.
        expect((await refusal(ordinaryPage, `/business/${rivalBusiness.uuid}`)).status).toBe(200);
        await theirs.close();

        // The owner of one business is nobody at another.
        for (const address of [`/business/${rivalBusiness.uuid}/edit`, `/business/${rivalBusiness.uuid}/inbox`, '/moderation']) {
            const answer = await refusal(page, address);
            expect([403, 404], `${address} answered ${answer.status} for another owner`).toContain(answer.status);
        }

        // A moderator reaches the moderation area, and still not a company's private things.
        const mods = await openContext(browser);
        const moderatorPage = await mods.newPage();
        await signIn(moderatorPage, moderator.email);
        expect((await refusal(moderatorPage, '/moderation')).status).toBe(200);
        await expect(moderatorPage.getByRole('heading', { level: 1, name: 'Moderation' })).toBeVisible();
        for (const address of [`/business/${privateBusiness.uuid}/edit`, `/business/${privateBusiness.uuid}/inbox`, `/attachments/${attachment.uuid}`]) {
            const answer = await refusal(moderatorPage, address);
            expect([403, 404], `${address} answered ${answer.status} for a moderator`).toContain(answer.status);
        }
        await mods.close();

        // Signed out: private things do not exist, and anything that needs an account goes to the sign-in page.
        const nobody = await openContext(browser);
        const guestPage = await nobody.newPage();
        for (const address of [`/business/${privateBusiness.uuid}`, `/attachments/${attachment.uuid}`]) {
            const answer = await refusal(guestPage, address);
            expect(answer.status === 404 || answer.path === '/login', `${address} answered ${answer.status} at ${answer.path} for a visitor`).toBe(true);
        }
        for (const address of [`/business/${rivalBusiness.uuid}/edit`, `/question/view/${saved.id}`, '/moderation', '/home', '/user/profile/edit']) {
            const answer = await refusal(guestPage, address);
            expect(answer.path, `${address} sends a visitor to sign in`).toBe('/login');
        }
        expect((await refusal(guestPage, `/business/${rivalBusiness.uuid}`)).status).toBe(200);
        await nobody.close();
    });
});
