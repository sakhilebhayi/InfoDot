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
| D-01 | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/email/verify`, `/two-factor-challenge`, `/user/confirm-password`, sign-out confirmation | all | both | Not The Yard (U-09): a full-page photograph under two gradients, a white rounded card with a soft drop shadow, a blue button (`#1f78c8`), DM Sans, centred heading. The card pins the light tokens, so the dark scheme is ignored. Copy is the framework's ("Sign in with your credentials", "Sign Up", "Log in", "Log Out", "Email Password Reset Link"). | P1 | open |
| D-02 | 403, 404, 413, 419, 429, 500, 503 | all | both | Not The Yard: Baloo 2 headings, DM Sans, Space Mono uppercase labels, a gold pill button, a rounded icon tile, a row of pill links; always light, whatever the scheme. | P2 | open |
| D-03 | Error pages | all | both | The platform icons are white on gold: 1.6:1 to 2.3:1 on 16 tiles (need 3:1). | P1 | open |
| D-04 | Public pages | all | both | Two different top bars for a visitor: the landing/about family ("Sign in", "Create account", 36px buttons, two tabs) and the directory family ("Sign in", "Join InfoDot", 30px buttons, one tab). The same action has two names and two sizes, and neither bar links to the other family's pages. | P2 | open |
| D-05 | `/about`, `/features`, `/faqs`, `/contact`, `/complains` | 1440, 1024 | both | The text sits in one 46-character column at the left edge and the other two thirds of the page are empty. Reads as unfinished next to the legal pages, which have an index beside the text. | P2 | open |
| D-06 | `/analytics` | all | both | "Platform coverage" is 23 full-width white bars with the name and "Live" run together in the middle. The classes it uses (`ixa-bays`, `ix-bay__text`, `ixa-stamp`) have no rules, so it falls back to the Dot Apps bay: white in the dark scheme, a pointer cursor and a hover lift on something that is not a link. | P1 | open |
| D-07 | `/business/{uuid}` "Similar businesses", directory headings | all | both | A 3px mustard stripe down the left side of a panel. `DESIGN.md` lists side-stripe borders under Don'ts. | P3 | open |
| D-08 | `/teams/create`, team settings, company menu | all | both | One thing has three names in one form (U-14): team, business, company. | P2 | open |
| D-09 | `/user/profile/edit` | all | both | Framework copy: the two-factor text names one authenticator by brand (U-15); "Ensure your account is using a long, random password to stay secure." | P3 | open |

### The demonstration marker

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-10 | `/business/{uuid}` (demonstration business) | all | both | The word appears about fifteen times on one screen: the cover, the name, the chip, the breadcrumb, "From Ledger Works (Demonstration)", four "Demonstration price:" lines, the opening sentence of two paragraphs, and again in the similar-business card. It reads as a watermark rather than a label. | P2 | open |
| D-11 | Wherever the marker is drawn | all | light | The marker itself is 4.27:1 (U-02): `--ix-warn` on its wash. The same chip marks every open state. | P1 | open |

### Colour and contrast (measured)

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-12 | `/business/{uuid}`, "On InfoDot" block | all | both | Chips and dates keep their page colours on the blue field (U-03): "Question" 1.11:1, "Solution" 1.24:1, dates 1.30:1 (light); 2.10, 2.04, 1.71 (dark). | P1 | open |
| D-13 | `/home` | all | dark | References and times in the blue Knowledge field are 4.14:1 (U-04): the dark scheme's blue is lighter and the ink was not adjusted. | P1 | open |
| D-14 | `/business/{uuid}` | all | dark | Section numerals 2.98:1; "Reach them" field labels below 4.5:1 (U-03). | P2 | open |
| D-15 | `/businesses`, business cards | all | dark | The letter that stands in for a missing logo is 2.95:1 (grey on a white tile). | P2 | open |
| D-16 | `/` | all | both | The seven stage titles: all but the current one are dimmed to 3.22:1 and their descriptions to 2.44:1. Suggestion chips over the photograph fall below 4.5:1 where the photograph is pale (U-05). | P2 | open |
| D-17 | An enquiry, the accounts desk | all | light | A time on the sunken thread ground is 4.37:1; the "Active" chip is 4.39:1 (`--ix-ok` on its wash over a tinted row). | P2 | open |
| D-18 | Every page with a band | all | dark | The breadcrumb chevron is 2.66:1 on blue and 2.92:1 on steel (needs 3:1). | P3 | open |
| D-19 | `/moderation/friction` | all | light | Period tabs reuse the top bar's tab and are near invisible on the page (U-06); the off state shows an environment variable and a file path (U-08). | P1 | open |
| D-20 | Long company name, business page | all | both | The owner code on a business with no cover is gold on steel, 2.5:1. Large stencil lettering, but still under 3:1. | P3 | open |

### Touch and narrow widths

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-21 | Every page | 768, 375 | both | Controls are below 44px on touch (U-01): buttons and fields 36px, small buttons and pills 30px, the search arrow 34px, the avatar 32px, the bell and inbox 36px, the company menu 36px. Between 4 and 18 undersized targets per page; 83 on the business editor. | P1 | open |
| D-22 | A solution, Home | 375 | both | A title with a long unbroken word does not wrap: on a solution's page it pushes the whole page wider than the screen (the page draws zoomed out); on Home it is cut off at the edge of the list. | P1 | open |
| D-23 | `/business/{uuid}`, "On InfoDot" | 375 | both | Chip, title and date stay on one row, so a long title is squeezed into a column a few words wide. | P2 | open |
| D-24 | Home, recent solutions | 375 | both | The restacked table shows a bare number under each title; the "Steps" heading is gone, so nothing says what it counts. | P3 | open |
| D-25 | Signed-in pages | 375 | both | The tab row scrolls sideways with nothing to show it (U-16); the top bar is about 190px tall before content (U-17). | P2 | open |
| D-26 | `/business/{uuid}/edit` | 375 | both | One page of more than a dozen sections (U-18). | P3 | open |

### Feedback, forms, states

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-27 | Sign-in pages | all | both | Success messages ("A new verification link has been sent", the reset status, "We could not sign you in from that link") are plain text with no `role="status"`, coloured with a utility green that is not a token. | P2 | open |
| D-28 | Every form | all | both | 36 field errors (`.ix-error`) are not announced: no `role`, and only the summary box on some pages is. The summary is headed "Something went wrong." above the real message (U-11), which reads as a system fault when it is a missed field. | P2 | open |
| D-29 | `/register` | all | both | The terms box is not marked required, so the browser lets the form go and the server answers; both password fields come back empty (U-11). | P2 | open |
| D-30 | `/register`, password reset | all | both | Over the sign-up limit the answer is a bare white page of text (U-10), and ten sign-ups in ten minutes from one address is reachable by a room on one Wi-Fi. | P1 | open |
| D-31 | `/user/profile/edit` | all | both | A photo that is too large is previewed and only refused after Save (U-12). | P3 | open |
| D-32 | `/questions/ask`, `/solution/create` | all | both | Topics are compulsory but sit below the attachments as nearly forty checkboxes (U-13). | P3 | open |

### First-time experience and empty states

| No. | Page | Width | Theme | What is wrong | Sev. | Status |
|---|---|---|---|---|---|---|
| D-33 | `/home`, new account | all | both | No guidance. A person who has just verified lands on a gate reading "Nomvula's Team", other companies' public questions and solutions, and nothing that says what to do first. | P1 | open |
| D-34 | After verifying an email | all | both | The redirect goes to `/home?verified=1`, which is the right page, but nothing on it acknowledges the verification. | P3 | open |
| D-35 | `/library` | all | both | Empty even with the demonstration content installed (U-07): `demo:install` adopts nothing into the library. | P2 | open |
| D-36 | `/moderation` and its desks | all | both | "No open reports." as a bare line of text: no panel, no word on what would appear. | P3 | open |
| D-37 | `/notifications`, new account | all | both | "Nothing yet" explains what will arrive but offers no next step. | P3 | open |
| D-38 | `/people`, personal workspace | all | both | "Invite and manage" leads to team settings, where a personal workspace cannot invite anyone; nothing says a business is needed first. | P2 | open |
| D-39 | `/` | all | both | Five-second test: the headline says what you can do ("Find practical business solutions.") but the first screen never says what InfoDot is or who it is for; the supporting line repeats the headline. | P2 | open |

## What was and was not looked at

Filled in after the work, with the after-numbers.
