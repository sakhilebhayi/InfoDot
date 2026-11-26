import fs from 'node:fs';
import path from 'node:path';
import { makeBusiness, makeUser, op, rows } from '../support/app';
import { scan, type Violation } from '../support/a11y';
import { dataDir } from '../support/env';
import { brokenImages, expect, screenshot, signIn, test, watch } from '../support/fixtures';
import { demo, demoCompany, moderatorStops, ownerStops } from '../support/pages';

/**
 * N. R-72 phase 5: every signed-in screen, as the person who uses it, at desktop, tablet and phone width. Each page must
 * answer, have exactly one main heading, no broken picture, no console or script error, no sideways scroll, nothing cut off
 * at the screen's edge, and no serious
 * or critical accessibility finding (contrast included). Everything axe reports, minor too, is written to
 * tests/Browser/.data/sweep/ with a screenshot of every page at every width for the design review.
 *
 * Not here: downloads (CSV exports, the seminar data export), JSON endpoints the pages call (arrivals, mentions, pickers, link checks), redirects that
 * leave InfoDot (open/{resource}, platform launches, sign-in hand-offs), the first-business onboarding (it sends an owner who has one
 * home), and the pages behind switches the run leaves off: paid seminars (payouts, invoices, payments) and reputation (appeals, rings).
 */
type Stop = { who: string; name: string; path: string };
type Finding = { page: string; path: string; width: string; problem: string };

const widths = [['desktop', 1440, 900], ['tablet', 768, 1024], ['phone', 375, 812]] as const;
const sweepDir = path.join(dataDir, 'sweep');

let stops: Stop[] = [];

test.describe('N. Signed-in sweep at three widths', () => {
    test.beforeAll(() => {
        // The records are made once per run; a later test in the file (a new worker after a failure) reads the same list back.
        const saved = path.join(sweepDir, 'stops.json');
        if (fs.existsSync(saved)) {
            stops = JSON.parse(fs.readFileSync(saved, 'utf8'));

            return;
        }
        const company = rows('teams', { name: demoCompany })[0];
        const owner = rows('users', { email: demo.owner })[0];
        const outsider = rows('users', { email: demo.outsider })[0];
        const enquiry = rows('enquiries', { team_id: company.id })[0];
        const topic = rows('topics')[0];

        const booking = op<{ booking: number; reference: string; type: number; provider: number }>({ op: 'make-booking', business_id: company.id, customer: demo.outsider });
        const admin = makeUser('Sweep Platform Admin', { platform_admin: true });

        // The case pages (as in J), made once.
        const caseOwner = makeUser('Sweep Case Owner');
        const caseOther = makeUser('Sweep Case Other');
        const reporter = makeUser('Sweep Reporter');
        const reviewer = makeUser('Sweep Reviewer', { moderator: true });
        const second = makeUser('Sweep Second Reviewer', { moderator: true });
        const host = makeUser('Sweep Host');
        const attendee = makeUser('Sweep Attendee');
        const caseBusiness = makeBusiness(caseOwner, 'Sweep Boreholes', 'public');
        const caseCopy = makeBusiness(caseOther, 'Sweep Boreholes Cape', 'public');
        const cases = op<{ seminar: number; verification: number; conflict: number; report: number; investigation: number; appeal: number }>({
            op: 'make-cases', owner: caseOwner.email, other: caseOther.email, reporter: reporter.email, reviewer: reviewer.email,
            second_reviewer: second.email, host: host.email, attendee: attendee.email, business_id: caseBusiness.id, other_business_id: caseCopy.id,
        });

        const biz = `/business/${company.uuid}`;
        const plan = rows('platform_plans')[0];
        const bundle = rows('platform_bundles')[0];
        const branded = rows('dot_platforms')[0];

        stops = [
            ...ownerStops().map((s) => ({ who: demo.owner, ...s })),
            ...[
                ['business-insights', `${biz}/insights`], ['business-standing', `${biz}/standing`], ['business-verification', `${biz}/verification`],
                ['business-enquiry', enquiry ? `${biz}/inbox/${enquiry.uuid}` : ''],
                ['booking-setup', `${biz}/booking`], ['booking-hours', `${biz}/booking/hours`], ['booking-closures', `${biz}/booking/closures`],
                ['booking-providers', `${biz}/booking/providers`], ['booking-provider-hours', `${biz}/booking/providers/${booking.provider}/hours`],
                ['booking-types', `${biz}/booking/types`], ['booking-type-new', `${biz}/booking/types/new`], ['booking-type-edit', `${biz}/booking/types/${booking.type}/edit`],
                ['appointments', `${biz}/appointments`], ['appointments-calendar', `${biz}/appointments/calendar`], ['appointments-upcoming', `${biz}/appointments/upcoming`],
                ['appointments-past', `${biz}/appointments/past`], ['appointments-reports', `${biz}/appointments/reports`], ['appointment', `${biz}/appointments/${booking.booking}`],
                ['notification-settings', '/notifications/settings'], ['referrals', '/referrals'],
                ['onboarding-recommendations', '/onboarding/recommendations'], ['platform-page', '/platforms/forms'], ['platform-switch-on', '/platforms/forms/switch-on'],
                ['licences-buy', `/teams/${company.uuid}/licences/buy`], ['topic', topic ? `/topics/${topic.slug}` : ''], ['user-profile-legacy', '/user/profile'],
            ].filter(([, p]) => p !== '').map(([name, p]) => ({ who: demo.owner, name, path: p })),
            ...[
                ['customer-bookings', '/my-bookings'], ['customer-booking', `/my-bookings/${booking.reference}`], ['customer-inbox', '/inbox'],
                ['customer-enquiry', enquiry ? `/inbox/${enquiry.uuid}` : ''], ['customer-message', `${biz}/message`], ['customer-report', `${biz}/report`],
                ['customer-request-appointment', `${biz}/request-appointment`], ['customer-seminar', `/seminars/${cases.seminar}`],
            ].filter(([, p]) => p !== '').map(([name, p]) => ({ who: name === 'customer-seminar' ? attendee.email : demo.outsider, name, path: p })),
            ...moderatorStops().map((s) => ({ who: demo.moderator, ...s })),
            ...[
                ['moderation-account', `/moderation/accounts/${outsider.id}`],
            ].map(([name, p]) => ({ who: demo.moderator, name, path: p })),
            ...[
                ['moderation-verification-case', `/moderation/verifications/${cases.verification}`], ['moderation-identity-conflict', `/moderation/identity/${cases.conflict}`],
                ['moderation-trust-report', `/moderation/trust/reports/${cases.report}`], ['moderation-trust-investigation', `/moderation/trust/investigations/${cases.investigation}`],
            ].map(([name, p]) => ({ who: reviewer.email, name, path: p })),
            { who: second.email, name: 'moderation-trust-appeal', path: `/moderation/trust/appeals/${cases.appeal}` },
            ...[
                ['admin-catalogue', '/admin/platforms'], ['admin-catalogue-entry', '/admin/platforms/catalogue/forms'], ['admin-companies', '/admin/platforms/companies'],
                ['admin-company', `/admin/platforms/companies/${company.uuid}`], ['admin-bundles', '/admin/platforms/bundles'], ['admin-bundle', bundle ? `/admin/platforms/bundles/${bundle.id}` : ''],
                ['admin-plan', plan ? `/admin/platforms/plans/${plan.id}` : ''], ['admin-payments', '/admin/platforms/payments'], ['admin-outbox', '/admin/platforms/outbox'],
                ['admin-audit', '/admin/platforms/audit'], ['admin-infrastructure', '/admin/platforms/infrastructure'], ['admin-branding', '/admin/platforms/branding'],
                ['admin-branding-edit', branded ? `/admin/platforms/branding/${branded.slug}` : ''], ['admin-growth', '/admin/growth'], ['admin-growth-funnel', '/admin/growth/funnel'],
            ].filter(([, p]) => p !== '').map(([name, p]) => ({ who: admin.email, name, path: p })),
        ];
        fs.mkdirSync(sweepDir, { recursive: true });
        fs.writeFileSync(saved, JSON.stringify(stops));
    });

    for (const [width, w, h] of widths) {
        test(`every signed-in screen at ${width} width`, async ({ page }) => {
            test.setTimeout(900_000);
            await page.setViewportSize({ width: w, height: h });
            const record = watch(page);
            const findings: Finding[] = [];
            const axe: Violation[] = [];
            let signedIn = '';

            for (const stop of stops) {
                if (stop.who !== signedIn) {
                    await page.context().clearCookies();
                    await signIn(page, stop.who);
                    signedIn = stop.who;
                }
                const before = record.problems.length;
                const add = (problem: string) => findings.push({ page: stop.name, path: stop.path, width, problem });
                let status = 0;
                try {
                    status = (await page.goto(stop.path))?.status() ?? 0;
                } catch (error) {
                    add(`did not open: ${(error as Error).message.split('\n')[0]}`);
                    continue;
                }

                if (status >= 400) {
                    add(`answered ${status}`);
                    continue;
                }
                if (!new URL(page.url()).pathname.startsWith(stop.path.split('?')[0])) {
                    add(`redirected to ${new URL(page.url()).pathname}`);
                }

                const headings = await page.locator('h1').count();
                if (headings !== 1) {
                    add(`${headings} main headings`);
                }
                const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
                if (scroll > client) {
                    add(`scrolls sideways: ${scroll}px on a ${client}px screen`);
                }
                // The page clips its own overflow (overflow-x: clip), so something too wide is cut off rather than scrolled to:
                // look for it directly, leaving out what sits inside a strip that scrolls or a frame that crops on purpose (a photograph).
                for (const wide of await page.evaluate(() => {
                    const vw = document.documentElement.clientWidth;
                    const inScroller = (el: Element) => {
                        for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
                            if (['auto', 'scroll', 'hidden', 'clip'].includes(getComputedStyle(p).overflowX)) {
                                return true;
                            }
                        }

                        return false;
                    };

                    return Array.from(document.querySelectorAll('main *, header *'))
                        .filter((el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.right > vw + 1 && !inScroller(el); })
                        .slice(0, 3).map((el) => `${el.tagName.toLowerCase()}.${String(el.className).trim().split(/\s+/)[0]} reaches ${Math.round(el.getBoundingClientRect().right)}px`);
                })) {
                    add(`cut off at the edge: ${wide}`);
                }
                for (const src of await brokenImages(page)) {
                    add(`broken picture ${src}`);
                }
                const found = await scan(page, stop.name, 'light');
                axe.push(...found.map((v) => ({ ...v, scheme: width })));
                for (const v of found.filter((v) => v.impact === 'serious' || v.impact === 'critical')) {
                    add(`${v.impact} ${v.rule} (${v.count}): ${v.selectors[0]}`);
                }
                for (const problem of record.problems.slice(before)) {
                    add(problem);
                }
                await screenshot(page, `sweep-${width}`, stop.name);
            }

            fs.writeFileSync(path.join(sweepDir, `${width}.json`), JSON.stringify({ width, pages: stops.length, findings, axe }, null, 2));
            expect(findings.map((f) => `${f.page} ${f.path}: ${f.problem}`), `signed-in sweep at ${width}`).toEqual([]);
        });
    }
});
