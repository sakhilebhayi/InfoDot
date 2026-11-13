# R-49 UX findings from the browser run

Things noticed while the real-browser journeys ran (`tests/Browser`, 2026-11-13, on main as of #215 with the demonstration content installed). None of them stops a journey, and none was changed: each needs a design decision, so it is listed here for the design pass. Functional defects were fixed in the same pull request and are not in this list.

Screenshot names refer to `tests/Browser/screens/`, which `npm run test:browser -- --project=desktop-chromium f-investor` writes on your machine (the folder is not in git). Desktop is 1440 x 900; phone is 390 x 844.

## What became of these (design pass, `DESIGN_AUDIT.md`)

The design pass took every item below as input. The list itself is kept as it was written, so the history reads straight; this table says where each one ended.

| No. | Result | Audit row |
|---|---|---|
| U-01 | Closed. Controls are at least 44px on touch and narrow widths; the `test.fail` mark is removed and the test must pass. | D-21 |
| U-02 | Closed. `--ix-warn` darkened; the chip passes on every surface. Mark removed. | D-11 |
| U-03 | Closed. The "On InfoDot" list uses white ink on the blue; numerals and the cover code changed. | D-12, D-14 |
| U-04 | Closed. Full white ink in the Knowledge field. | D-13 |
| U-05 | Closed. See the audit: the chips sit on an opaque panel; what was measured was the entrance fade part-way. | D-16 |
| U-06 | Closed. The period control is the pill strip used on a business's insights page. | D-19 |
| U-07 | Closed. `demo:install` shelves two accepted answers, labelled, removed with the rest. | D-35 |
| U-08 | Closed. Plain-language off state. | D-19 |
| U-09 | Closed. The sign-in pages are The Yard. | D-01 |
| U-10 | Closed. The 429 page, with the wait in minutes; sign-up allows 30 in ten minutes. | D-30 |
| U-11 | Closed for the heading and the terms box. The password fields come back empty on purpose. | D-28, D-29 |
| U-12 | Open. Needs the photo field's upload binding changed. | D-31 |
| U-13 | Open. A change to the structure of two forms. | D-32 |
| U-14 | Closed on the create form ("Create a business"). "Team settings" and "company" elsewhere are left for the owner. | D-08 |
| U-15 | Closed. Any authenticator app. | D-09 |
| U-16 | Closed. The tab row fades at its right edge. | D-25 |
| U-17 | Open. Deciding what leaves the phone top bar is a product decision. | D-25 |
| U-18 | Open. Splitting the editor is a redesign of that screen. | D-26 |

## Held by a test that is marked as a known failure (at the time; both marks are now removed)

Two checks the brief asks for cannot pass until the design system changes. They are in the suite, marked `test.fail` with a pointer to this file, so they report as expected failures and start guarding the result as soon as the mark is removed.

| No. | Page | Viewport | Screenshot | Finding |
|---|---|---|---|---|
| U-01 | Every page with a primary button (17 buttons on 11 pages of the phone journey: `/`, `/register`, `/email/verify`, `/login`, `/people`, `/seminars`, `/user/profile/edit`, `/questions/ask`, `/question/view/{id}`, `/teams/create`, `/business/{uuid}/edit`) | phone, tablet | `phone/public-create-account.png`, `phone/business-profile.png` | Primary buttons are 36px high on touch screens (`.ix-btn` has a fixed `height: 36px`); the target is 44px. `e-mobile.spec.ts`, second test. |
| U-02 | Wherever demonstration content is labelled: `/questions`, `/solution-results`, `/question/view/{id}`, `/solution/view/{id}`, `/user/profile/{id}`, `/business/{uuid}` | desktop, light | `desktop/knowledge-questions.png`, `desktop/business-page.png` | The `.ix-chip--open` chip is below 4.5:1 in the light scheme. The "Demonstration" label is drawn with it (and it also marks open states), so the label that tells a visitor the content is not real is one of the hardest things on the row to read. |
| U-03 | `/business/{uuid}` | desktop, light and dark | `desktop/business-page.png` | Text below 4.5:1 besides the chip: the field labels in "Reach them" (`.ix-biz__field > span`), the dates in the announcement and "On InfoDot" lists, and in dark the section numerals (`.ix-bizsec__num`). |
| U-04 | `/home` | desktop, dark | `desktop/home.png` | Text below 4.5:1 in dark only: the references (`.ix-ref`) inside the blue Knowledge field, and the element with class `.w` beside each. |
| U-05 | `/` | desktop, light and dark | `desktop/public-home.png` | The suggestion chips under the search field sit on the hero photograph; where the photograph is pale behind one, its text is below 4.5:1. |
| U-06 | `/moderation/friction` | desktop, light | `desktop/moderation-friction.png` | The period tabs (7, 14, 30, 90 days) reuse `.ix-tab`, which is drawn for the dark top bar: on the light page they are almost invisible. |

Every element behind U-02 to U-06 is listed with its selector in `ACCESSIBILITY_SCAN.md` (80 elements on 9 pages, counting each colour scheme). `h-accessibility.spec.ts`, "text contrast" tests.

## Demonstration readiness

| No. | Page | Viewport | Screenshot | Finding |
|---|---|---|---|---|
| U-07 | `/library` | all | `desktop/library.png` | The Verified library is empty with the demonstration content installed: `demo:install` writes questions, answers and solutions but adopts none into the library, so that tab opens on "The library is empty so far". |
| U-08 | `/moderation/friction` | all | `desktop/moderation-friction.png` | The empty state shows a setting name and a file path meant for a developer ("UX_SIGNALS=true; see docs/programme/R12_UX_SIGNALS.md"). |
| U-09 | `/login`, `/register`, `/email/verify` | all | `desktop/public-sign-in.png`, `desktop/public-create-account.png` | The sign-in pages are not in The Yard: a full-page photograph, a white card, a blue button, DM Sans and Baloo type. Everything before and after them is The Yard. |
| U-10 | `/register` | all | none (seen in the tablet run) | Sign-up is limited to ten in ten minutes from one address. A room of people joining on one Wi-Fi, at a seminar or a demonstration, reaches that, and the eleventh person gets a bare white page reading "Too many attempts. Please wait a few minutes and try again." with no InfoDot styling and no way back. The limit is deliberate (`ThrottleAccountForms`); the number and the page it shows are the decisions to make. |

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

- No page scrolls sideways: the 44 pages of the walkthrough were measured at 390px and at 1440px in Chromium, and every page of the phone journey was measured in WebKit at iPhone 14 and iPad sizes and in Chromium at Pixel 7 size.
- The public home page reveals its sections as you scroll; a visitor who has asked for reduced motion gets the whole page at once.
