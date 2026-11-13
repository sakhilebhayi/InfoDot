import { makeBusiness, makeUser, op } from '../support/app';
import { blocking, contrast, scan, type Violation } from '../support/a11y';
import { brokenImages, expect, expectNoSidewaysScroll, signIn, test, watch } from '../support/fixtures';

/**
 * J. The pages no demonstration content reaches: a seminar's own page and the moderators' case pages (one
 * verification application, one identity conflict, one report about a business, its investigation and the appeal).
 * The records are made once through the services the forms call (`make-cases` in support/e2e.php). Each page must
 * answer, carry a main heading and no broken image, pass the accessibility scan in both colour schemes and not
 * scroll sideways at phone width.
 *
 * Not here, because each needs a switch the browser run leaves off or a service it does not start: a paid seminar,
 * its receipt, payouts and invoices (paid seminars and Dot.Finance), the two kinds of seminar room, /progress
 * (REPUTATION_ENABLED) and the consent page (shown only to a client that is not an active Dot platform).
 */
type Cases = { seminar: number; verification: number; conflict: number; report: number; investigation: number; appeal: number };

let cases: Cases;
let people: { attendee: string; reviewer: string; second: string };

test.describe('J. Seminar and moderation case pages', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        const owner = makeUser('Case Owner');
        const other = makeUser('Case Other');
        const reporter = makeUser('Case Reporter');
        const reviewer = makeUser('Case Reviewer', { moderator: true });
        const second = makeUser('Case Second Reviewer', { moderator: true });
        const host = makeUser('Case Host');
        const attendee = makeUser('Case Attendee');
        const business = makeBusiness(owner, 'Case Boreholes', 'public');
        const copy = makeBusiness(other, 'Case Boreholes Cape', 'public');

        cases = op<Cases>({
            op: 'make-cases', owner: owner.email, other: other.email, reporter: reporter.email, reviewer: reviewer.email,
            second_reviewer: second.email, host: host.email, attendee: attendee.email, business_id: business.id, other_business_id: copy.id,
        });
        people = { attendee: attendee.email, reviewer: reviewer.email, second: second.email };
    });

    const stops = (): { who: string; name: string; path: string; says: RegExp }[] => [
        { who: people.attendee, name: 'seminar-own-page', path: `/seminars/${cases.seminar}`, says: /You are registered/ },
        { who: people.reviewer, name: 'moderation-verification-case', path: `/moderation/verifications/${cases.verification}`, says: /What the business sent/ },
        { who: people.reviewer, name: 'moderation-identity-conflict', path: `/moderation/identity/${cases.conflict}`, says: /Case Boreholes/ },
        { who: people.reviewer, name: 'moderation-trust-report', path: `/moderation/trust/reports/${cases.report}`, says: /Case Boreholes/ },
        { who: people.reviewer, name: 'moderation-trust-investigation', path: `/moderation/trust/investigations/${cases.investigation}`, says: /Investigation #/ },
        { who: people.second, name: 'moderation-trust-appeal', path: `/moderation/trust/appeals/${cases.appeal}`, says: /traded under this name since 2019/ },
    ];

    for (const scheme of ['light', 'dark'] as const) {
        test.describe(`${scheme} scheme`, () => {
            test.use({ colorScheme: scheme });

            test('every page answers, has a heading and no broken image, and passes the accessibility scan', async ({ page }) => {
                test.setTimeout(240_000);
                const record = watch(page);
                const found: Violation[] = [];
                let signedIn = '';

                for (const stop of stops()) {
                    if (stop.who !== signedIn) {
                        await page.context().clearCookies();
                        await signIn(page, stop.who);
                        signedIn = stop.who;
                    }
                    const response = await page.goto(stop.path);
                    expect(response?.status(), stop.path).toBe(200);
                    await page.waitForLoadState('networkidle');
                    await expect(page.getByRole('heading', { level: 1 }), stop.path).toHaveCount(1);
                    await expect(page.locator('main, body').first(), stop.path).toContainText(stop.says);
                    expect(await brokenImages(page), stop.path).toEqual([]);
                    found.push(...await scan(page, stop.name, scheme));
                }

                expect(blocking(found), blocking(found).join('\n')).toEqual([]);
                expect(contrast(found), contrast(found).join('\n')).toEqual([]);
                expect(record.problems, record.problems.join('\n')).toEqual([]);
            });
        });
    }

    test('none of them scrolls sideways at phone width, and the report disclosures open', async ({ page }) => {
        test.setTimeout(240_000);
        await page.setViewportSize({ width: 375, height: 812 });
        let signedIn = '';

        for (const stop of stops()) {
            if (stop.who !== signedIn) {
                await page.context().clearCookies();
                await signIn(page, stop.who);
                signedIn = stop.who;
            }
            await page.goto(stop.path);
            await page.waitForLoadState('networkidle');
            await expectNoSidewaysScroll(page);
        }

        // The person's own place comes before the report disclosures on a seminar's page, and a disclosure opens in place.
        await page.context().clearCookies();
        await signIn(page, people.attendee);
        await page.goto(`/seminars/${cases.seminar}`);
        const place = await page.getByText('You are registered').boundingBox();
        const report = page.locator('details.ix-disclose > summary', { hasText: 'Report this seminar' });
        expect(place!.y).toBeLessThan((await report.boundingBox())!.y);
        expect((await report.boundingBox())!.height).toBeGreaterThanOrEqual(44);
        await report.click();
        await expect(page.locator('details.ix-disclose[open]').getByRole('button', { name: /report/i })).toBeVisible();
    });
});
