# R-49 design audit and first-time experience

Date: 2026-11-13. Branch `r49/design-first-time-pass`, cut from main at #215.

This is the list of what was wrong, written before anything was changed, and then marked row by row with what was done. The identity is The Yard (`PRODUCT.md`, `DESIGN.md`, `resources/css/infodot.css`); nothing here proposes a new look.

## How the pages were looked at

- A local copy served on `127.0.0.1:8135` with a scratch SQLite database, `demo:install --with-moderator`, plus three scratch accounts: one new and empty, one unverified, and one that owns a company with a 60-character name, a 2,000-character description, a 200-character question title and a solution title that contains a 97-character word with no break in it.
- Chromium through Playwright (kept outside the repository). Every page state below was opened at 1440, 1024, 768 and 375 pixels wide, in the light and the dark scheme (the theme follows the operating system: `prefers-color-scheme`). That is 100 page states and 800 screenshots before the fixes, and the same set after.
- On every one of those 800 renders a script measured three things: whether the page scrolls sideways; the contrast of every piece of text against the colour actually behind it (alpha blended through each layer; 4.5:1, or 3:1 for text of 24px and up, 18.66px bold, and icons); and, at 768 and 375, every link, button and field smaller than 44 by 44 pixels (inline links inside a sentence are left out, as WCAG allows).
- Screenshots were read by eye as contact sheets and singly. Not every one of the 800 was opened: the ones read are listed under "What was and was not looked at" at the end.
- The detector (`impeccable detect`) was run on the HTML of every page state.
- `UX_FINDINGS.md` (U-01 to U-18) and `ACCESSIBILITY_SCAN.md` from the browser-test change are treated as input; their numbers are given beside the row that covers them.

Pages: landing, about, what it does, questions people ask, contact, complaints, terms, privacy, cookies, security, public search, the directory (with results, with none), a business page; register, sign in, forgot and reset password, verify email, two-factor, confirm password, sign-out confirmation, each sign-in form with an error; 403, 404, 413, 419, 429, 500, 503; Home, Questions, ask, a question, Solutions, write, a solution, Topics, Library, Seminars and new, Businesses, a business page with its edit, insights, verification, inbox and an enquiry, the personal inbox, Platforms, People, Analytics, Notifications, Points, Collections, account settings, a profile, team settings, create team; the moderation queue, verifications, identity, business trust, the accounts desk and one account. The empty account and the long-content company each opened the pages where their state shows.

Not opened: a seminar's own page, room and invoices (the demonstration content has no seminar), `/progress` (answers 404 in this configuration), the OIDC consent page, and the moderation sub-pages for one verification, one identity conflict and one trust report (no such records in the demonstration content).

## Findings

Severity: P1 breaks a task, fails WCAG AA or would be noticed at once in a demonstration; P2 is an inconsistency or rough edge with a workaround; P3 is polish.

Status is filled in after the work: **fixed**, or **left** with the reason.

### Identity and consistency

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-01 | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/email/verify`, `/two-factor-challenge`, `/user/confirm-password`, sign-out confirmation | all | both | Not The Yard (U-09): a full-page photograph under two gradients, a white rounded card with a soft drop shadow, a blue button (`#1f78c8`), DM Sans, centred heading. The card pins the light tokens, so the dark scheme is ignored. Copy is the framework's ("Sign in with your credentials", "Sign Up", "Log in", "Log Out", "Email Password Reset Link"). | P1 | **fixed.** The shared shell (top bar, steel `ACCT` band, one panel), the product's buttons and fields, both schemes, plain wording. Photograph, card, blue button and DM Sans removed. |
| D-02 | 403, 404, 413, 419, 429, 500, 503 | all | both | Not The Yard: Baloo 2 headings, DM Sans, Space Mono uppercase labels, a gold pill button, a rounded icon tile, a row of pill links; always light, whatever the scheme. | P2 | **fixed.** Same shell: status as a stencil numeral, the product's buttons, platform names as a plain list; follows the scheme. Baloo 2, DM Sans and Space Mono are no longer loaded. |
| D-03 | Error pages | all | both | The platform icons are white on gold: 1.6:1 to 2.3:1 on 16 tiles (need 3:1). | P1 | **fixed.** The icon tiles are gone with D-02. |
| D-04 | Public pages | all | both | Two different top bars for a visitor: the landing/about family ("Sign in", "Create account", 36px buttons, two tabs) and the directory family ("Sign in", "Join InfoDot", 30px buttons, one tab). The same action has two names and two sizes, and neither bar links to the other family's pages. | P2 | **fixed.** One set of tabs (`layouts/public-tabs`) and one pair of actions, same words and sizes, on both bars; the directory is reachable from the landing family. |
| D-05 | `/about`, `/features`, `/faqs`, `/contact`, `/complains` | 1440, 1024 | both | The text sits in one 46-character column at the left edge and the other two thirds of the page are empty. Reads as unfinished next to the legal pages, which have an index beside the text. | P2 | **fixed** for about, what it does, questions and complaints: each heading stands in a margin column beside its text (`ix-prose--ledger`). Contact was left then and **fixed later**: it uses the same ledger, with the form at a readable width in the text column. |
| D-06 | `/analytics` | all | both | "Platform coverage" is 23 full-width white bars with the name and "Live" run together in the middle. The classes it uses (`ixa-bays`, `ix-bay__text`, `ixa-stamp`) have no rules, so it falls back to the Dot Apps bay: white in the dark scheme, a pointer cursor and a hover lift on something that is not a link. | P1 | **fixed.** A ledger of plates (`ixa-cover`): name and a LIVE plate or "Not live yet", in tokens, not clickable. |
| D-07 | `/business/{uuid}` "Similar businesses", directory headings | all | both | A 3px mustard stripe down the left side of a panel. `DESIGN.md` lists side-stripe borders under Don'ts. | P3 | **fixed.** Eleven side stripes became top rules or were dropped (similar businesses, category tiles, inbox links, preview bar, recommendation card, notes). **Fixed later:** the gold bar on the Home gate is gone; the gate's mark is the company's owner code, stencilled in gold at its far end (`HomeGateTest`). **Left:** the unread and chosen-option indicators, which are states. |
| D-08 | `/teams/create`, team settings, company menu | all | both | One thing has three names in one form (U-14): team, business, company. | P2 | **fixed** on the create form, its menu link and breadcrumb ("Create a business", "Business name"). **Fixed later** with one rule everywhere: "company" for the workspace a person belongs to and manages (so the form now reads "Create a company", "Company name", and the menu "Company settings"), "business" for its public face (business page, directory, enquiries), "team" never shown. Guard: `CompanyAndBusinessWordingTest`. The stored name of a personal workspace ("Nomvula's Team") is data and is unchanged. |
| D-09 | `/user/profile/edit` | all | both | Framework copy: the two-factor text names one authenticator by brand (U-15); "Ensure your account is using a long, random password to stay secure." | P3 | **fixed** for the authenticator wording. **Left:** the password hint, harmless. |

### The demonstration marker

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-10 | `/business/{uuid}` (demonstration business) | all | both | The word appears about fifteen times on one screen: the cover, the name, the chip, the breadcrumb, "From Ledger Works (Demonstration)", four "Demonstration price:" lines, the opening sentence of two paragraphs, and again in the similar-business card. It reads as a watermark rather than a label. | P2 | **fixed.** One chip beside the name; "Announcements" instead of the name again; one line above services and above products instead of a label on each price. The cover, the name and the two paragraphs are the stored content and keep the word. |
| D-11 | Wherever the marker is drawn | all | light | The marker itself is 4.27:1 (U-02): `--ix-warn` on its wash. The same chip marks every open state. | P1 | **fixed.** `--ix-warn` is `#704400`: 5.29:1 to 6.55:1 on its wash over the four surfaces. |

### Found while fixing

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-40 | Every page with a primary button | all | dark | `--ix-signal` follows `--area`, which resolves on `:root` to a slate (`#232b2f`) one step off the panel (`#191f22`). So in the dark scheme the main action looked like a disabled ghost button, and a ticked box, the text selection and the upload bar were all but invisible. Not caught by a text-contrast check because the label on the button passes. | P1 | **fixed.** In the dark scheme `--ix-signal` is `#e9ecec` with ink `#14181a`: the main action is light on the dark ground, the mirror of the light scheme. |
| D-42 | `/business/{uuid}/edit`, `/business/{uuid}/verification` | all | both | The section headings ("Directory listing", "Logo", "Apply", "Linked businesses") have no style of their own and fall back to body text: 14px, regular weight, no space above. A fourteen-section form reads as one run of fields. Measured in the browser. | P2 | **fixed.** `.ix-settings__sec > h2` is set like the other settings titles (18px, bold) and each section starts under a rule. |
| D-41 | Account and team settings | all | both | The detector's one real finding besides D-02: the section titles set their sizes inline (12, 13, 14, 15, 18px side by side). | P3 | **fixed.** The sizes moved into `ix-settings__title`; the scale itself is unchanged. |

### Colour and contrast (measured)

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-12 | `/business/{uuid}`, "On InfoDot" block | all | both | Chips and dates keep their page colours on the blue field (U-03): "Question" 1.11:1, "Solution" 1.24:1, dates 1.30:1 (light); 2.10, 2.04, 1.71 (dark). | P1 | **fixed.** The block uses the Home field's rows: "Question"/"Solution", title and date in white on the blue (8.14:1 light, 5.05:1 dark). |
| D-13 | `/home` | all | dark | References and times in the blue Knowledge field are 4.14:1 (U-04): the dark scheme's blue is lighter and the ink was not adjusted. | P1 | **fixed.** Full white ink (5.05:1). |
| D-14 | `/business/{uuid}` | all | dark | Section numerals 2.98:1; "Reach them" field labels below 4.5:1 (U-03). | P2 | **fixed.** Numerals use `--ix-text-3` (5.64:1 dark); the cover without a photograph is stencil gold on graphite (7.7:1). |
| D-15 | `/businesses`, business cards | all | dark | The letter that stands in for a missing logo is 2.95:1 (grey on a white tile). | P2 | **fixed.** The tile is white in both schemes, so its ink is fixed too (7.09:1). |
| D-16 | `/` | all | both | The seven stage titles: all but the current one are dimmed to 3.22:1 and their descriptions to 2.44:1. Suggestion chips over the photograph fall below 4.5:1 where the photograph is pale (U-05). | P2 | **fixed** for the stages: the progress bar shows where you are and the words are never dimmed. The chips have an opaque panel behind them; the U-05 finding was the entrance fade caught mid-way, see the browser-suite note below. |
| D-17 | An enquiry, the accounts desk | all | light | A time on the sunken thread ground is 4.37:1; the "Active" chip is 4.39:1 (`--ix-ok` on its wash over a tinted row). | P2 | **fixed.** `--ix-text-3` `#4f5a60` (4.94:1 at worst) and `--ix-ok` `#1a5c3b` (4.98:1 at worst). |
| D-18 | Every page with a band | all | dark | The breadcrumb chevron is 2.66:1 on blue and 2.92:1 on steel (needs 3:1). | P3 | **fixed.** Separator opacity 0.85. |
| D-19 | `/moderation/friction` | all | light | Period tabs reuse the top bar's tab and are near invisible on the page (U-06); the off state shows an environment variable and a file path (U-08). | P1 | **fixed.** The period control is the pill strip the insights page uses; the off state says in plain words what is off, who can switch it on and what it would count. |
| D-20 | Long company name, business page | all | both | The owner code on a business with no cover is gold on steel, 2.5:1. Large stencil lettering, but still under 3:1. | P3 | **fixed** with D-14. |

### Touch and narrow widths

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-21 | Every page | 768, 375 | both | Controls are below 44px on touch (U-01): buttons and fields 36px, small buttons and pills 30px, the search arrow 34px, the avatar 32px, the bell and inbox 36px, the company menu 36px. Between 4 and 18 undersized targets per page; 83 on the business editor. | P1 | **fixed.** One block at the end of the stylesheet: at `pointer: coarse` or 768px and below, buttons, fields, pills, tabs and icon controls are at least 44px. A mouse keeps 36px. |
| D-22 | A solution, Home | 375 | both | A title with a long unbroken word does not wrap: on a solution's page it pushes the whole page wider than the screen (the page draws zoomed out); on Home it is cut off at the edge of the list. | P1 | **fixed.** `overflow-wrap: anywhere` on page titles, detail titles, table cells, field rows and the breadcrumb. |
| D-23 | `/business/{uuid}`, "On InfoDot" | 375 | both | Chip, title and date stay on one row, so a long title is squeezed into a column a few words wide. | P2 | **fixed** with D-12: the rows restack below 640px as they do on Home. |
| D-24 | Home, recent solutions | 375 | both | The restacked table shows a bare number under each title; the "Steps" heading is gone, so nothing says what it counts. | P3 | **fixed.** The restacked cell reads "5 steps". |
| D-25 | Signed-in pages | 375 | both | The tab row scrolls sideways with nothing to show it (U-16); the top bar is about 190px tall before content (U-17). | P2 | **fixed** for the cue: the row fades at its right edge and leaves room for the last tab. **Left:** the height of the top bar on a phone (U-17), which means deciding what moves into a menu. |
| D-26 | `/business/{uuid}/edit` | 375 | both | One page of more than a dozen sections (U-18). | P3 | **fixed later** without splitting the screen: below 900px the section index is a two-column numbered register, and a "Sections" plate stays at the foot of the screen to go back to it from any of the sections (U-18). Tick-box lines are 44px targets. |

### Feedback, forms, states

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-27 | Sign-in pages | all | both | Success messages ("A new verification link has been sent", the reset status, "We could not sign you in from that link") are plain text with no `role="status"`, coloured with a utility green that is not a token. | P2 | **fixed.** `ix-alert` with `role="status"`, or `role="alert"` for the failed mail. |
| D-28 | Every form | all | both | 36 field errors (`.ix-error`) are not announced: no `role`, and only the summary box on some pages is. The summary is headed "Something went wrong." above the real message (U-11), which reads as a system fault when it is a missed field. | P2 | **fixed.** Every `.ix-error` without an id has `role="alert"` (the ones with an id are tied to their field); the summary reads "Check the following and try again." |
| D-29 | `/register` | all | both | The terms box is not marked required, so the browser lets the form go and the server answers; both password fields come back empty (U-11). | P2 | **fixed** for the terms box (required). **Left:** the password fields come back empty on purpose; a password is not echoed into a page. |
| D-30 | `/register`, password reset | all | both | Over the sign-up limit the answer is a bare white page of text (U-10), and ten sign-ups in ten minutes from one address is reachable by a room on one Wi-Fi. | P1 | **fixed.** The limit raises a 429 that draws the error page (JSON for JSON) with Retry-After, and the page says how many minutes. Sign-up allows 30 in ten minutes per address. |
| D-31 | `/user/profile/edit` | all | both | A photo that is too large is previewed and only refused after Save (U-12). | P3 | **fixed later.** The field is no longer bound to the upload: the browser checks type and size (the server's own limit), says what is wrong and sends nothing; a picture that passes is uploaded as before. The server check stays (U-12). |
| D-32 | `/questions/ask`, `/solution/create` | all | both | Topics are compulsory but sit below the attachments as nearly forty checkboxes (U-13). | P3 | **fixed later.** The picker sits straight after the title on both forms and its hint starts "Required"; on a touch screen each topic is a 44px target (U-13). |

### First-time experience and empty states

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-33 | `/home`, new account | all | both | No guidance. A person who has just verified lands on a gate reading "Nomvula's Team", other companies' public questions and solutions, and nothing that says what to do first. | P1 | **fixed.** "Getting started" on Home: see the section below. |
| D-34 | After verifying an email | all | both | The redirect goes to `/home?verified=1`, which is the right page, but nothing on it acknowledges the verification. | P3 | **fixed.** "Your email address is confirmed. You are in." as a status line on that arrival. |
| D-35 | `/library` | all | both | Empty even with the demonstration content installed (U-07): `demo:install` adopts nothing into the library. | P2 | **fixed.** `demo:install` shelves two accepted public answers; each library row carries the marker; removal takes them. |
| D-36 | `/moderation` and its desks | all | both | "No open reports." as a bare line of text: no panel, no word on what would appear. | P3 | **fixed** for the queue: each empty tab says what would be listed. **Left:** the trust and identity desks' one-line empties, which sit inside titled panels. |
| D-37 | `/notifications`, new account | all | both | "Nothing yet" explains what will arrive but offers no next step. | P3 | **fixed.** Says what will arrive and offers "Ask a question" and "Find a question to answer". |
| D-38 | `/people`, personal workspace | all | both | "Invite and manage" leads to team settings, where a personal workspace cannot invite anyone; nothing says a business is needed first. | P2 | **fixed.** A personal workspace says it holds one person and offers "Create a business to invite people". |
| D-39 | `/` | all | both | Five-second test: the headline says what you can do ("Find practical business solutions.") but the first screen never says what InfoDot is or who it is for; the supporting line repeats the headline. | P2 | **fixed.** The line under the headline now says what InfoDot is and who it is for: "InfoDot is where people who run a business ask each other questions and write down what worked. Search it, or ask your own." |

## The first-time experience, as built

Walked as a person who has never seen InfoDot, signed out, at 1440 and 375 pixels:

1. **Landing.** The headline says what you can do, the line under it now says what InfoDot is and who it is for, and the search field and "Create account" are the two next steps. Every statement on the page is one the application can back; nothing was added.
2. **Create your account.** One panel in The Yard: name, email, password twice, the terms box (now required in the browser as well as on the server), "Create account". The line under the heading says it is free and that a link will be emailed.
3. **Verify your email.** Says which address the link went to, why the address is checked, and offers "Send the link again" and "Sign out".
4. **Arriving.** The verification link lands on Home (`/home?verified=1`, as before), which now confirms it in a status line and shows **Getting started**.

**Getting started** is a ledger on Home, under the gate and above the Knowledge field, with the gold top rule Home uses. Rows are numbered in the stencil face like the settings index; a finished row carries a stamped DONE plate, an unfinished one a button to the page where it is done.

| Row | Done when | Opens |
|---|---|---|
| Add a profile picture | `users.profile_photo_path` is set | Account settings |
| Ask a first question or write a first solution | a question or a solution with this `user_id` exists | Ask a question |
| Create your business and list it | a business this person owns or administers has `Team::listing()['listed']` true. With no business the row offers to create one; with an unlisted one it says what `listing()` reports missing | Create a business, or the business editor |
| Invite a colleague (only for someone who manages a business) | that business has a member besides the owner, or an invitation out | Team settings, People and roles |
| Open a platform | `users.platform_opened_at` is set | Platforms |

Nothing about the steps is stored. The block is absent when every row is done, comes back if one is undone, and can be put away for good ("Put this away" writes `users.getting_started_dismissed_at`).

`platform_opened_at` is the one fact that had to be recorded: the hand-off token is deleted the moment a platform uses it, so nothing else in the database remembers that a person opened one. It is written once, when the person is actually sent to a platform (the launch page, or the redirect for a platform with its own sign-in), never by a page view.

Empty states that were blank, bare or a dead end now say what the page is for and offer the next action: Notifications, a personal workspace's People page, Home's recent solutions, the business inbox, the moderation queue's three tabs, and the Library with demonstration content installed.

## Measured before and after

The same 100 page states at four widths in two schemes, 800 renders each time, on the same data.

| Measure | Before | After |
|---|---|---|
| Renders that scroll sideways | 0 | 0 |
| Text below its contrast target (element and colour pair, counted per render) | 1,180 | 0 |
| Controls under 44 by 44 at 768 and 375 (counted per render) | 3,990 | 16 |

The before-figure for sideways scrolling is 0 although D-22 is real: with a touch viewport Chromium widens the layout to fit an unbreakable word, so the page drew zoomed out and the measure of "wider than the window" did not see it. It was found by looking at the screenshot.

The 16 remaining small targets are three things, each on four renders or fewer, and none is a control that needs changing: a link inside a sentence ("the inbox"), which WCAG 2.5.8 exempts; the subject of an inbox row, whose whole row is the target (a pseudo-element covers it); and a one-word breadcrumb that is 44px tall and 34px wide.

**How contrast was measured.** In the page, for every element that holds text of its own: the computed colour, composited over each background layer beneath it up to the first opaque one (opacity on the way up included), then the WCAG ratio. Text over a photograph is not judged by this method and was read by eye (the landing hero's headline and line sit on a veil; the chips have an opaque panel). The browser suite's axe scan is a second, independent measure and now reports no contrast finding on any page it scans, in either scheme.

Token pairs after the change, worst surface of the four (panel, ground, sunken, hover):

| Pair | Light | Dark |
|---|---|---|
| `--ix-text-3` on the surface | 5.18:1 | 5.13:1 |
| Warning chip (`--ix-warn` on its wash) | 5.29:1 | 5.26:1 |
| Done chip (`--ix-ok` on its wash) | 4.98:1 | 5.26:1 |
| `--ix-danger` on the surface | 5.09:1 | 6.64:1 |
| White on the Knowledge blue | 8.14:1 | 5.05:1 |
| Stencil gold on graphite | 7.7:1 | 7.7:1 |

No pair measured is under its target.

## The detector

`impeccable detect` on the HTML of every page state, as the local server sent it (and, for the six states reached by submitting a form, the page as the browser held it afterwards).

| | Before | After |
|---|---|---|
| Findings in total | 18 | 15 |
| Page states with a finding | 15 of 100 | 13 of 100 |
| Page states where the count went up | | none |

Changed: `/teams/create` 1 to 0, `/teams/create` with errors 2 to 1, `/user/profile/edit` 1 to 0. The other 97 are unchanged, 85 of them at 0.

What remains, and what it is:

- `dark-glow`, 7 page states, one each. All seven are the pages captured from the browser after a form was sent, and the rule it matches is in the style block Livewire adds to a live page for its progress bar (`box-shadow: 0 0 10px #29d`). It is not in the HTML the server sends, not in the stylesheet, and the bar is not shown anywhere in InfoDot.
- `broken-image`, 2 on the business editor. The two `<img data-crop-img>` inside the crop dialogs have no `src` until a person chooses a picture; they are inside a closed dialog and never drawn empty.
- `flat-type-hierarchy`, 4 page states (three team settings pages and a profile). The detector reads only the sizes written in the HTML (`text-sm`, `text-xs`, an inline 15px on an avatar) and cannot see the stylesheet, where the page title is 26px and the section titles 18px. The inline section-title sizes it did have a point about were moved into the stylesheet (D-41).

**The `broken-image` finding on about 22 elements of every signed-in page is an artefact of how the page was dumped, not a defect.** It appears when the HTML comes from a PHPUnit request, because `Tests\TestCase` calls `withoutVite()`, which makes every `Vite::asset()` return an empty string: the platform logos in the Dot Apps panel and the brand mark come out as `<img src="">`. Reproduced here with a throwaway test (since deleted): 23 to 45 per page before the changes and the same after. The same pages fetched from the running application have real addresses under `/build/assets/` on every one of those images and the detector reports none.

## What was and was not looked at

- **Measured:** all 100 page states at 1440, 1024, 768 and 375, light and dark, before and after (1,600 renders).
- **Read by eye, before:** landing, sign in, 404, a business page, Home at 1440 and 375 light; contact sheets of Questions, a question, Solutions, a solution; nine public pages at 1440 light and twelve at 375 light; nine signed-in pages at 1440 dark and nine more at 1440 light; fourteen signed-in pages at 375 dark; the empty account on eight pages at 1440 light; the long-content company on four pages at 1440 light and five at 375 light; crops of Analytics and verification.
- **Read by eye, after:** sign in, register, forgot password, the register form with errors, verify email, two-factor, 404, 429, Home for a new account and for a demonstration owner, a business page, the business editor, verification, Analytics, Questions, Solutions, account settings, team settings, People, Notifications, the moderation queue, the directory, the reading pages, public search, and the long-content business and solution, between them at 1440 and 375 in both schemes.
- **Not read by eye:** most pages at 1024 and 768 (those widths were measured, and the only layout change in that range is the tab row); the dark scheme at 375 for the public pages other than register and 404; every state of every moderation desk.
- **Not opened at all:** a seminar's own page, its room and its invoices; `/progress`; the OIDC consent page (restyled with the other way-in pages, never rendered here); the moderation pages for one verification, one identity conflict and one trust report; the library with entries in a browser (the two demonstration entries are covered by a test, the scratch data here was installed before that change).
- **Not tested:** a screen reader; a real phone (touch was emulated in Chromium, and the browser suite adds WebKit at iPhone and iPad sizes); hover and focus states were checked in the stylesheet and by the keyboard spec, not photographed.
