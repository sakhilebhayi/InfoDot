# Browser tests

Real-browser journeys through InfoDot, written with Playwright. They click and type the way a person does, on desktop, phone and tablet sizes, in Chromium, Firefox and WebKit (the engine Safari uses), and they scan the pages for accessibility problems with axe-core.

They are not part of `php artisan test` and CI does not run them: three browsers are a heavy download. Run them on your own machine before a demonstration or after a change to a journey.

## Run them

Once:

```bash
cp .env.example .env && php artisan key:generate   # if you have no .env yet
composer install
npm ci
npx playwright install chromium firefox webkit
```

Each time:

```bash
npm run build          # the tests use the built assets in public/build
npm run test:browser
```

One project, one file or one test:

```bash
npm run test:browser -- --project=desktop-chromium
npm run test:browser -- --project=phone-webkit e-mobile
npm run test:browser -- --project=desktop-chromium -g "Investor walkthrough"
```

The HTML report is in `tests/Browser/playwright-report/` (`npx playwright show-report tests/Browser/playwright-report`). A failed test keeps a screenshot and a trace in `tests/Browser/test-results/`.

## What a run does to your machine

- It starts its own copy of the app on `http://127.0.0.1:8132` (`php artisan serve`, set `E2E_PORT` to change the port) and stops it at the end.
- It uses its own SQLite file, `tests/Browser/.data/e2e.sqlite`, which it empties, migrates and seeds (`DemoSeeder`, then `demo:install --with-moderator`) at the start of every run. The run sets the database, mail, session, cache and queue settings itself (`support/env.ts`), and those win over your `.env`, so your development database is not touched.
- Mail goes to `storage/logs/laravel.log`. Uploaded files go to `storage/app`, as they do in development.
- No request goes to the live Dot hosts: the browsers block `*.infodot.co.za`, and the per-platform secrets are blanked for the run.
- The current build loads nothing from another host. Should a page ever do so, the first run fetches that file once per browser engine and keeps it in `tests/Browser/.data/elsewhere/`; later runs replay the kept copy, so a slow third-party host cannot stall a run. Delete that folder to fetch the files again.
- Test accounts share one password, generated for each run and kept in `tests/Browser/.data/run.json`. It is never written into a spec.

Everything a run writes inside `tests/Browser` (`.data/`, `screens/`, `test-results/`, `playwright-report/`) is ignored by git.

## Getting past email verification

Registration is tested for real: the app writes the verification email to the log (`MAIL_MAILER=log`), the test reads the newest link for that address out of `storage/logs/laravel.log` and opens it in the same browser (`verificationLink()` in `support/app.ts`). Scenarios A and E do this. Those people register with an address on `example.com`, because the app never mails a `.test` address.

Accounts that a scenario only needs as a starting point (an owner, a customer, a moderator) are made by `support/e2e.php` through `php artisan tinker`, using the same actions the sign-up and "Create a business" forms use, and are marked verified there. That helper refuses to run against anything but the scratch SQLite file.

## Projects

| Project | Browser and size | Runs |
|---|---|---|
| `desktop-chromium` | Chromium, 1440 x 900 | everything except E (A to D, F to K) |
| `desktop-firefox` | Firefox, 1440 x 900 | A, B, D, G |
| `desktop-webkit` | WebKit, 1440 x 900 | A, B, D, G |
| `phone-webkit` | WebKit, iPhone 14 | E |
| `phone-chromium` | Chromium, Pixel 7 | E |
| `tablet` | WebKit, iPad (gen 7) | E |

## Specs

| File | What it proves |
|---|---|
| `a-new-user.spec.ts` | A visitor walks the public pages, registers, verifies by email, edits the profile, uploads a 4032 x 3024 photo, moves through the signed-in areas, signs out and in. |
| `b-business.spec.ts` | "Create a company", logo and cover through the crop frame, profile, found in the directory by search, an enquiry from another person, the owner's reply, an edit that shows on the public page. |
| `c-knowledge.spec.ts` | Library and an existing solution, a question with a picture, an answer from a colleague, 404 for an outsider, a solution with steps, a report, and a moderator hiding the reported item. |
| `d-permissions.spec.ts` | Private business pages, another person's attachment, the moderation area and another business's edit page are refused with 403/404 or the sign-in page, never a server error. |
| `e-mobile.spec.ts` | The main journey on a phone and a tablet, with no sideways scrolling on any page. A second test checks that primary buttons are 44 x 44; it is marked as a known failure (see `docs/programme/r49/UX_FINDINGS.md`, U-01). |
| `f-investor-walkthrough.spec.ts` | One continuous visit through the labelled demonstration content (`php artisan demo:install --with-moderator`, which the run installs), as its owner and then its moderator: fails on any console error, script error, failed request to the app, 4xx/5xx or broken image. Saves a full-page picture of every page at desktop and phone size in `screens/`. |
| `g-failure-paths.spec.ts` | Wrong password, an email already registered, a business with fields missing, uploads that are too large or the wrong type: each gets a clear message and no server error. |
| `h-accessibility.spec.ts` | axe-core, WCAG 2.0/2.1/2.2 A and AA, light and dark colour schemes. Fails on serious or critical violations and writes `docs/programme/r49/ACCESSIBILITY_SCAN.md` when the whole scan has run. Text contrast is asserted in a test of its own, marked as a known failure (U-02 to U-06). |
| `j-case-pages.spec.ts` | The pages no demonstration content reaches: a seminar's own page and the moderators' case pages (a verification application, an identity conflict, a report about a business, its investigation, the appeal). Each answers, has a heading and no broken image, passes axe in both schemes with no contrast finding, and does not scroll sideways at 375px. |
| `k-platform-access.spec.ts` | A platform the company has not switched on (R-50): the launch answers 403 with the reason, an employee is told whom to ask, the owner switches a free platform on. axe and contrast in both schemes, no sideways scroll at 1440 and 375, a picture of each in `screens/`. |
| `i-keyboard.spec.ts` | Sign-in, registration and business creation with the keyboard only; tab order, visible focus, Ctrl/Cmd+K, and menus and dialogs that close with Escape and give focus back. |

## Known failures

None. Two checks were marked `test.fail` until the design pass: primary buttons of 44 x 44 on touch screens (`e-mobile.spec.ts`) and text contrast (`h-accessibility.spec.ts`). The marks are gone and both must pass. If a later change needs one again, record the reason in `docs/programme/r49/UX_FINDINGS.md`: Playwright reports a marked test as an expected failure, not as a pass.

## Adding a test

Import `test` and `expect` from `support/fixtures.ts`, not from `@playwright/test`, and open a second person's browser with `openContext(browser)`, not `browser.newContext()`: that is where the live hosts are blocked. Make people with `makeUser()` and businesses with `makeBusiness()` so that names are unique and several projects can run at once against the one database. Read saved rows back with `rows('table', { column: value })`.
