# R-49 UX findings from the browser run

Things noticed while the real-browser journeys ran (`tests/Browser`, 2026-11-13). None of them stops a journey, and none was changed: each needs a design decision, so it is listed here for the design pass. Functional defects were fixed in the same pull request and are not in this list.

Screenshot names refer to `tests/Browser/screens/`, which `npm run test:browser -- --project=desktop-chromium f-investor` writes on your machine (the folder is not in git). Desktop is 1440 x 900; phone is 390 x 844.

## Held by a test that is marked as a known failure

Two checks the brief asks for cannot pass until the design system changes. They are in the suite, marked `test.fail` with a pointer to this file, so they report as expected failures and start guarding the result as soon as the mark is removed.

| No. | Page | Viewport | Screenshot | Finding |
|---|---|---|---|---|
| U-01 | Every page with a primary button (17 buttons on 11 pages of the phone journey: `/`, `/register`, `/email/verify`, `/login`, `/people`, `/seminars`, `/user/profile/edit`, `/questions/ask`, `/question/view/{id}`, `/teams/create`, `/business/{uuid}/edit`) | phone, tablet | `phone/public-create-account.png`, `phone/business-profile.png` | Primary buttons are 36px high on touch screens (`.ix-btn` has a fixed `height: 36px`); the target is 44px. `e-mobile.spec.ts`, second test. |
| U-02 | `/business/{uuid}` | desktop, light and dark | `desktop/business-page.png` | Text below 4.5:1: the field labels in "Reach them" (`.ix-biz__field > span`), the status chips (`.ix-chip--open`, `.ix-chip--done`) and the dates in the blue "On InfoDot" panel; in dark also the section numeral (`.ix-bizsec__num`). |
| U-03 | `/home` | desktop, dark | `desktop/home.png` | Text below 4.5:1 in dark only: the reference (`.ix-ref`, for example DETR-Q-000001) inside the blue Knowledge field, and one element with class `.w` beside it. |
| U-04 | `/` | desktop, light and dark | `desktop/public-home.png` | The suggestion chips under the search field sit on the hero photograph; where the photograph is pale behind them ("Find customers", "Manage cash") their text is below 4.5:1. |
| U-05 | `/moderation/friction` | desktop, light | `desktop/moderation-friction.png` | The period tabs (7, 14, 30, 90 days) reuse `.ix-tab`, which is drawn for the dark top bar: on the light page they are almost invisible. |

Every element behind U-02 to U-05 is listed with its selector in `ACCESSIBILITY_SCAN.md`. `h-accessibility.spec.ts`, "text contrast" tests.

## Demonstration readiness

| No. | Page | Viewport | Screenshot | Finding |
|---|---|---|---|---|
| U-06 | `/library` | all | `desktop/library.png` | The Verified library is empty after `php artisan migrate:fresh --seed`: the seeder adds public solutions but adopts none into the library, so a demonstration opens on "The library is empty so far". |
| U-07 | `/business/{uuid}` (Demo Traders) | all | `desktop/business-page.png` | The seeded business has no description, logo, cover or contact details, so its page is a grey plate with the owner code and little else. |
| U-08 | `/moderation/friction` | all | `desktop/moderation-friction.png` | The empty state shows a setting name and a file path meant for a developer ("UX_SIGNALS=true; see docs/programme/R12_UX_SIGNALS.md"). |
| U-09 | `/login`, `/register`, `/email/verify` | all | `desktop/public-sign-in.png`, `desktop/public-create-account.png` | The sign-in pages are not in The Yard: a server-room photograph, a blue button, DM Sans and Baloo type, and Font Awesome loaded for no icon on the page. Everything before and after them is The Yard. |
| U-10 | `/login`, `/register` | all | `desktop/public-sign-in.png` | The background is a 2,400px photograph hot-linked from `images.unsplash.com`. On a cold cache the page took 8.5 seconds to finish loading in the run, and if that host is slow or blocked at the venue the page waits on it. |

| U-19 | `/register` | all | none (seen in the tablet run) | Sign-up is limited to ten in ten minutes from one address. A room of people joining on one Wi-Fi, at a seminar or a demonstration, reaches that, and the eleventh person gets a bare white page reading "Too many attempts. Please wait a few minutes and try again." with no InfoDot styling and no way back. The limit is deliberate (`ThrottleAccountForms`); the number and the page it shows are the decisions to make. |

## Forms and feedback

| No. | Page | Viewport | Screenshot | Finding |
|---|---|---|---|---|
| U-11 | `/register` | all | `desktop/public-create-account.png` | A validation error is headed "Something went wrong." above the real message ("The terms field is required."), and both password fields come back empty. The terms box is not marked required, so the browser does not stop the form first. |
| U-12 | `/user/profile/edit` | all | `desktop/account-settings.png` | A photo over 10 MB or of the wrong type is accepted into the preview circle; the reason it is refused only appears after "Save". |
| U-13 | `/questions/ask`, `/solution/create` | all | `desktop/knowledge-ask.png` | Topics are compulsory, but the group sits below the attachments as a list of nearly forty checkboxes, and the line "Choose 1 to 3" is the only sign that the form will not post without one. |
| U-14 | `/teams/create`, company menu | all | `desktop/team-create.png` | The same thing is called a team ("Create new team", "Team name", "Create team"), a business ("What the business does") and a company (top bar) within one form. |
| U-15 | `/user/profile/edit` | all | `desktop/account-settings.png` | The two-factor text names one authenticator app by brand; any authenticator works. |

## Phone layout

| No. | Page | Viewport | Screenshot | Finding |
|---|---|---|---|---|
| U-16 | Every signed-in page | phone | `phone/home.png` | The tab row scrolls sideways but nothing shows that it does: the row ends mid-word at "Files". (The row now starts at the current tab; that part was fixed.) |
| U-17 | Every signed-in page | phone | `phone/home.png` | The top bar takes about 190px before any content: brand and company menu, a row of four icons, the tab row, then the search field. |
| U-18 | `/business/{uuid}/edit` | phone | `phone/business-profile.png` | The profile editor is one page of more than a dozen sections; on a phone the section index scrolls away and the page is very long. |

## Not design, but worth knowing

- No page scrolls sideways: the 43 pages of the walkthrough were measured at 390px and at 1440px in Chromium, and every page of the phone journey was measured in WebKit at iPhone 14 and iPad sizes and in Chromium at Pixel 7 size.
- The public home page reveals its sections as you scroll; a visitor who has asked for reduced motion gets the whole page at once.
