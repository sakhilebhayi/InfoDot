import { fixture, makeUser, op, rows, unique } from '../support/app';
import { expect, password, signIn, test, watch } from '../support/fixtures';

/** Nothing on a failure path may be a server error. */
function serverErrors(problems: string[]): string[] {
    return problems.filter((p) => /^5\d\d on |uncaught error/.test(p));
}

test.describe('G. When something goes wrong', () => {
    test('a wrong password is explained and nobody is signed in', async ({ page }) => {
        const person = makeUser('Wrong Password');
        const record = watch(page);
        await page.goto('/login');
        await page.getByLabel('Email').fill(person.email);
        await page.locator('#password').fill(`${password()}-not-it`);
        await page.getByRole('button', { name: 'Sign in' }).click();
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await expect(page.getByLabel('Email')).toHaveValue(person.email);
        expect((await page.request.get('/home', { maxRedirects: 0 })).status()).toBe(302);
        expect(serverErrors(record.problems)).toEqual([]);
    });

    test('registering with an email that already has an account is explained', async ({ page }) => {
        const existing = makeUser('Already Here');
        // Ten sign-ups in ten minutes are allowed from one address and the matrix makes more: start from a clean count.
        op({ op: 'clear-rate-limits' });
        const record = watch(page);
        await page.goto('/register');
        await page.getByLabel('Name').fill('Second Attempt');
        await page.getByLabel('Email').fill(existing.email);
        await page.locator('#password').fill(password());
        await page.locator('#password_confirmation').fill(password());
        await page.getByRole('checkbox', { name: /I agree to the/ }).check();
        await page.getByRole('button', { name: 'Sign Up' }).click();
        await expect(page).toHaveURL(/\/register$/);
        await expect(page.getByText('The email has already been taken.')).toBeVisible();
        expect(rows('users', { email: existing.email })).toHaveLength(1);
        expect(serverErrors(record.problems)).toEqual([]);
    });

    test('a business cannot be created without a name or without saying who can see it', async ({ page }) => {
        const owner = makeUser('Forgetful Founder');
        const record = watch(page);
        await signIn(page, owner.email);
        await page.goto('/teams/create');

        // Nothing filled in.
        await page.getByRole('button', { name: 'Create team' }).click();
        await expect(page.getByText('The name field is required.')).toBeVisible();
        await expect(page.getByText('Choose who can see the business.')).toBeVisible();
        await expect(page).toHaveURL(/\/teams\/create$/);

        // A name, but still no choice of who can see it.
        const name = `Half Made ${unique()}`;
        await page.getByLabel('Team name').fill(name);
        await page.getByRole('button', { name: 'Create team' }).click();
        await expect(page.getByText('Choose who can see the business.')).toBeVisible();
        await expect(page.getByText('The name field is required.')).toHaveCount(0);
        expect(rows('teams', { name })).toHaveLength(0);
        expect(serverErrors(record.problems)).toEqual([]);
    });

    test('a profile photo that is too large or not a picture is turned away with a reason', async ({ page }) => {
        const person = makeUser('Upload Unlucky');
        const record = watch(page);
        await signIn(page, person.email);
        await page.goto('/user/profile/edit');
        const profile = page.locator('form').filter({ has: page.locator('#photo') });

        // Over the limit: the form says so when it is saved, and keeps the old picture.
        await profile.locator('#photo').setInputFiles(fixture('too-large.png'));
        await expect(profile.getByRole('button', { name: 'Save' })).toBeEnabled({ timeout: 60_000 });
        await profile.getByRole('button', { name: 'Save' }).click();
        await expect(profile.getByText('That picture is over 10 MB. Choose a smaller one.')).toBeVisible({ timeout: 30_000 });

        // Not a picture at all.
        await page.reload();
        await profile.locator('#photo').setInputFiles(fixture('not-a-picture.txt'));
        await expect(profile.getByRole('button', { name: 'Save' })).toBeEnabled({ timeout: 30_000 });
        await profile.getByRole('button', { name: 'Save' }).click();
        await expect(profile.getByText('Choose a picture: JPG, PNG or WebP.')).toBeVisible({ timeout: 30_000 });

        expect(rows('users', { email: person.email })[0].profile_photo_path).toBeNull();
        expect(serverErrors(record.problems)).toEqual([]);
    });

    test('an attachment or a logo of the wrong kind or size is turned away with a reason', async ({ page }) => {
        const owner = makeUser('Attachment Unlucky');
        const record = watch(page);
        await signIn(page, owner.email);

        // The question form's uploader.
        await page.goto('/questions/ask');
        await page.getByLabel('Add attachment files').setInputFiles(fixture('not-a-picture.txt'));
        await expect(page.locator('.ix-attach__item.is-error, .ix-field > .ix-error').filter({ hasText: /\S/ }).first()).toBeVisible({ timeout: 30_000 });
        // PHP's development server now and then drops a 12 MB body part-way ("The upload did not arrive intact. Try
        // again."). That is a clear message too, and the test does what it says: takes the file off and tries again.
        const large = page.locator('.ix-attach__item').filter({ hasText: 'too-large.png' });
        for (const attempt of [1, 2, 3]) {
            await page.getByLabel('Add attachment files').setInputFiles(fixture('too-large.png'));
            await expect(large.getByRole('alert')).toHaveText(/\S/, { timeout: 60_000 });
            if (attempt === 3 || !/did not arrive intact/.test(await large.getByRole('alert').innerText())) {
                break;
            }
            await page.getByRole('button', { name: 'Remove too-large.png' }).click();
            await expect(large).toHaveCount(0);
        }
        await expect(large.getByRole('alert')).toHaveText(/10 MB|too large|larger/i);
        expect(rows('attachments', { user_id: owner.id })).toHaveLength(0);

        // A business logo.
        const name = `Logo Trouble ${unique()}`;
        await page.goto('/teams/create');
        await page.getByLabel('Team name').fill(name);
        await page.getByRole('radio', { name: /^Only this business/ }).check();
        await page.getByRole('button', { name: 'Create team' }).click();
        await expect(page).toHaveURL(/\/business\/[0-9a-f-]{36}\/edit/);
        const logo = page.locator('#logo form[data-crop-form]');
        await logo.locator('[data-crop-file]').setInputFiles(fixture('not-a-picture.txt'));
        await logo.getByRole('button', { name: /Put the logo up/ }).click();
        await expect(page.getByRole('alert').filter({ hasText: /\S/ }).first()).toBeVisible();
        await expect(page).toHaveURL(/\/business\/[0-9a-f-]{36}\/edit/);
        const team = rows('teams', { name })[0];
        expect(rows('attachments', { team_id: team.id })).toHaveLength(0);
        expect(serverErrors(record.problems)).toEqual([]);
    });
});
