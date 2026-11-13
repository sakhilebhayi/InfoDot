import { addMember, fixture, makeBusiness, makeUser, rows, unique } from '../support/app';
import { expect, openContext, signIn, test } from '../support/fixtures';

test.describe('C. Knowledge', () => {
    test('library, a question with a picture, a solution with steps, an answer, who can see it, and a reported item', async ({ page, browser }) => {
        const asker = makeUser('Asker Ayanda');
        const colleague = makeUser('Colleague Chipo');
        const outsider = makeUser('Outsider Odwa');
        const moderator = makeUser('Moderator Mpho', { moderator: true });
        const company = makeBusiness(asker, `Ayanda Bakery ${unique()}`, 'private');
        addMember(company, colleague);
        const tag = unique();

        // The library, and an existing solution opened from it.
        await signIn(page, asker.email);
        await page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Library', exact: true }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Verified library' })).toBeVisible();
        await page.getByRole('navigation', { name: 'Knowledge' }).getByRole('link', { name: 'Solutions' }).click();
        await page.getByRole('link', { name: 'Demo: month-end reconciliation routine' }).first().click();
        await expect(page).toHaveURL(/\/solution\/view\/\d+$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Demo: month-end reconciliation routine' })).toBeVisible();
        await expect(page.getByText('Match the bank statement').first()).toBeVisible();

        // Ask a question, for the company only, with a photograph attached.
        const question = `Why does the proving cabinet trip the power ${tag}?`;
        await page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Knowledge', exact: true }).click();
        await page.getByLabel('Ask', { exact: true }).fill(question);
        await page.getByRole('button', { name: 'Ask a question' }).click();
        await expect(page).toHaveURL(/\/questions\/ask\?question=/);
        await expect(page.getByLabel('Question', { exact: true })).toHaveValue(question);
        await page.getByLabel('Details').fill('Since Monday the proving cabinet trips the earth leakage after about ten minutes. We replaced the door seal. The photograph shows the element.');
        await page.getByLabel('Add attachment files').setInputFiles(fixture('evidence-1600x1200.jpg'));
        await expect(page.getByLabel('Caption for evidence-1600x1200.jpg')).toBeVisible({ timeout: 30_000 });
        await page.getByLabel('Caption for evidence-1600x1200.jpg').fill('The heating element, from below');
        await page.getByRole('group', { name: 'Topics' }).getByRole('checkbox', { name: 'Technology and tools' }).check();
        await page.getByRole('button', { name: 'Post question' }).click();
        await expect(page).toHaveURL(/\/questions$/);
        await expect(page.getByText('Your question was posted.')).toBeVisible();

        const saved = rows('questions', { question })[0];
        expect(saved.visibility).toBe('company');
        expect(saved.team_id).toBe(company.id);
        const pictures = rows('attachments', { attachable_id: saved.id, attachable_type: 'questions' });
        expect(pictures).toHaveLength(1);
        expect(pictures[0].caption).toBe('The heating element, from below');

        await page.getByRole('link', { name: question }).first().click();
        await expect(page).toHaveURL(new RegExp(`/question/view/${saved.id}$`));
        const picture = page.locator(`main img[src*="/attachments/${pictures[0].uuid}"]`).first();
        await expect(picture).toBeVisible();
        await expect.poll(() => picture.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true);

        // A colleague in the same company sees it and answers; an outsider is told it does not exist.
        const colleagueContext = await openContext(browser);
        const colleaguePage = await colleagueContext.newPage();
        await signIn(colleaguePage, colleague.email);
        await colleaguePage.goto(`/question/view/${saved.id}`);
        await expect(colleaguePage.getByRole('heading', { level: 1, name: question })).toBeVisible();
        await colleaguePage.getByLabel('Your answer').fill('The element has cracked and is leaking to earth once it is hot. Measure it with an insulation tester; ours read under 1 megohm and a new element fixed it.');
        await colleaguePage.getByRole('button', { name: 'Post your answer' }).click();
        await expect(colleaguePage.getByRole('heading', { name: '1 answer' })).toBeVisible();
        await expect(colleaguePage.getByText('The element has cracked')).toBeVisible();
        expect(rows('answers', { question_id: saved.id })).toHaveLength(1);
        await colleagueContext.close();

        const outsiderContext = await openContext(browser);
        const outsiderPage = await outsiderContext.newPage();
        await signIn(outsiderPage, outsider.email);
        const refused = await outsiderPage.goto(`/question/view/${saved.id}`);
        expect(refused?.status()).toBe(404);
        await expect(outsiderPage.getByText(question)).toHaveCount(0);

        // The asker sees the answer, then writes up a solution with steps and publishes it.
        await page.reload();
        await expect(page.getByText('The element has cracked')).toBeVisible();
        const title = `Test a proving cabinet element for earth leakage ${tag}`;
        await page.goto('/solutions');
        await page.getByRole('link', { name: 'Write a solution' }).first().click();
        await expect(page).toHaveURL(/\/solution\/create$/);
        await page.locator('#solution_title').fill(title);
        await page.locator('#solution_description').fill('How we found a cracked heating element that tripped the earth leakage only when hot.');
        await page.locator('#tags-input').fill('bakery,electrical,maintenance');
        await page.locator('#duration').fill('1');
        await page.locator('#heading-0').fill('Isolate the cabinet');
        await page.locator('#body-0').fill('Switch off at the wall and unplug it. Let it cool for thirty minutes.');
        await page.getByRole('button', { name: 'Add step' }).click();
        await page.locator('#heading-1').fill('Measure the element');
        await page.locator('#body-1').fill('With an insulation tester, measure from each element terminal to the cabinet body. Anything under 1 megohm means the element must be replaced.');
        await page.getByRole('group', { name: 'Topics' }).getByRole('checkbox', { name: 'Technology and tools' }).check();
        await page.getByRole('checkbox', { name: /Publish/ }).check();
        await page.getByRole('button', { name: 'Publish solution' }).click();
        await expect(page).toHaveURL(/\/solutions$/);
        await expect(page.getByRole('status').filter({ hasText: 'Your solution was saved.' })).toBeVisible();
        const solution = rows('solutions', { solution_title: title })[0];
        expect(solution.visibility).toBe('public');
        expect(rows('solutions_step', { solution_id: solution.id }).map((s) => JSON.parse(s.solution_heading))).toEqual(['Isolate the cabinet', 'Measure the element']);

        // The outsider can read the public solution, and reports it.
        await outsiderPage.goto(`/solution/view/${solution.id}`);
        await expect(outsiderPage.getByRole('heading', { level: 1, name: title })).toBeVisible();
        await expect(outsiderPage.getByText('Measure the element')).toBeVisible();
        await outsiderPage.getByRole('button', { name: 'Report this solution' }).click();
        await outsiderPage.getByLabel('What is wrong with it?').selectOption('harmful');
        await outsiderPage.getByRole('button', { name: 'Send report' }).click();
        await expect(outsiderPage.getByText('You have reported this. A moderator will look at it.')).toBeVisible();
        await outsiderContext.close();

        // A moderator finds the report in the queue and hides the item, with a reason the author will read.
        const moderatorContext = await openContext(browser);
        const moderatorPage = await moderatorContext.newPage();
        await signIn(moderatorPage, moderator.email);
        await moderatorPage.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Moderation', exact: true }).click();
        await expect(moderatorPage.getByRole('heading', { level: 1, name: 'Moderation' })).toBeVisible();
        const item = moderatorPage.locator('.ix-mod__item').filter({ hasText: title });
        await expect(item).toBeVisible();
        await expect(item.getByText('Harmful or misleading')).toBeVisible();
        await item.locator('summary', { hasText: 'Hide' }).click();
        await item.getByLabel('Why is it hidden? The author will read this.').fill('Mains testing instructions need a qualified electrician; hidden until that is stated.');
        await item.getByRole('button', { name: 'Hide it' }).click();
        await expect(moderatorPage.locator('.ix-mod__item').filter({ hasText: title })).toHaveCount(0);
        expect(rows('content_reports', { reportable_id: solution.id, reporter_id: outsider.id })[0].status).toBe('actioned');
        await moderatorContext.close();

        // The author is shown that it is hidden, and why.
        await page.goto(`/solution/view/${solution.id}`);
        await expect(page.getByText('Mains testing instructions need a qualified electrician')).toBeVisible();
    });
});
