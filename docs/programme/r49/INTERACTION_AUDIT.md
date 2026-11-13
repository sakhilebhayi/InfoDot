# R-49 Interaction audit: no dead link, button or form

Date: 2026-11-13. Branch `r49/interaction-image-audit`, cut from `main` at `dd18954`.

Every number below comes from the sweep's own output (`R49_SWEEP_REPORT=/path/to/file.json php artisan test tests/Feature/Stabilisation/InteractionSweepTest.php` writes it). Nothing is estimated. Nothing was requested from the production site.

## What was checked, and how

`tests/Feature/Stabilisation/InteractionSweepTest.php` builds one populated copy of the product (`Support/BuildsTheYard.php`: a business with logo, cover, gallery, offerings, a project, a location, an announcement, a brochure, a verification application, an enquiry, a saved collection, reports, an investigation, an appeal, an identity conflict; a question with an answer, a comment and a picture; a solution with steps and pictures; a library entry; a seminar with a registration and a report; a notice; a team invitation). It then walks the product as each kind of viewer: it opens the entry pages, follows every in-app link it finds, and repeats on every page reached until there is nothing new.

On every page `Support/PageInspector.php` reads the HTML with PHP's HTML5 parser and applies these rules:

| Element | Rule |
|---|---|
| `<a>` | Needs a real `href`, or an Alpine/Livewire click handler. `#`, empty and `javascript:` are refused without a handler. An in-page `#anchor` needs its target id. Every in-app target is requested as that viewer and must not answer 403, 404, 419 or 5xx, and must not send a signed-in person to sign in. |
| `<button>`, submit inputs | Must sit in a form (or name one with `form=`), or carry an Alpine/Livewire handler, or be wrapped by one (Jetstream's confirm-password span), or have an id or `data-` attribute that a script in `resources/js` names. A `popovertarget` needs its target. |
| `<form>` | Its address and method (after `_method`) must match a registered route. A form that is not GET must carry a CSRF field. An outside address must be https (the policy's `form-action`). |
| `wire:click`, `wire:submit`, `wire:change`, `wire:keydown`, `wire:model`, `$wire.x()` | The method (or property) must exist and be public on the component the element sits in. |
| `x-data="name(...)"` | A script must define that Alpine component. |
| `<label for>` | The id must exist. |
| ids | No id twice on a page. |
| inline `on*=` handlers, inline `<script>` without a nonce | Refused: the Content-Security-Policy (`script-src-attr 'none'`, nonce-based `script-src`) blocks them, so they never run. |
| `<img>`, `srcset`, `<source>`, `poster`, CSS `url()`, icons, scripts, styles, frames | See `IMAGE_AUDIT.md`. |

A visitor may be shown a link that asks them to sign in only if it is on a closed list (`VISITOR_SIGN_IN_WALLS`: ask a question, browse or open a question or solution, write a solution, a topic, write to or report a business, a seminar's short link). Any other link that sends a visitor to sign in fails the test.

## Pages and viewers covered

| Viewer | Addresses opened | HTML pages inspected | Files and hand-offs | Distinct page shapes | Links not followed (cap) |
|---|---|---|---|---|---|
| Visitor | 107 | 105 | 2 | 19 | 0 |
| Signed-in member (verified, personal workspace) | 260 | 257 | 3 | 84 | 0 |
| Business owner (public business profile) | 270 | 266 | 4 | 89 | 0 |
| Moderator | 295 | 288 | 7 | 95 | 0 |
| Host, every optional feature on | 268 | 263 | 5 | 83 | 0 |
| Attendee, every optional feature on | 254 | 250 | 4 | 76 | 0 |
| Moderator, every optional feature on | 294 | 286 | 8 | 89 | 0 |
| **Total** | **1,748** | **1,715** | **33** | **113 across all viewers** | **0** |

"Page shape" is an address with its ids replaced (`/question/view/{id}`). The first four walks use the production defaults for feature switches (paid seminars, built-in seminar rooms, small rooms, reputation are off). The last three turn all of them on, so the pages behind those switches are walked too.

A further 10 pages are inspected outside the walk (`test_the_way_in_and_the_error_pages_have_no_dead_control`): verify-your-email, choose a new password, confirm password, sign-out-everywhere, the 404 page as a visitor and as a member, the 403 page (two cases), a missing question, and the platform-unavailable page (503). Each link on them is followed.

165 of the 217 Blade views were rendered by the walks. The 52 that were not are listed under "Remaining exceptions".

## Interactive elements found

Counted two ways: every occurrence on every page of every walk (a navigation link is counted on each page it appears on), and distinct controls over all walks (the same control on two records or two pages counts once).

| Type | Occurrences | Distinct | How it is verified |
|---|---|---|---|
| In-app link | 73,347 | 367 | Target requested as the viewer |
| In-page anchor link | 2,157 | 69 | Target id exists |
| Link with a script handler | 426 | 1 | Handler present |
| External link | 93 | 9 | Identified, not fetched |
| mailto / tel link | 127 | 4 | Identified |
| Form with a route | 5,685 | 97 | Route matched for the method, CSRF field present |
| Form handled by script only | 176 | 5 | Handler present; Livewire method resolved |
| Form posting to another host (platform hand-off) | 6 | 2 | https, allowed by `form-action` |
| Button inside a checked form | 6,395 | 109 | Its form is checked |
| Button with a handler | 52,499 | 154 | Handler present; Livewire method resolved where it is one |
| Livewire action or binding | 47,150 | 158 | Public method or property exists on the component |
| Alpine component reference | 1,360 | 6 | Defined in a script |
| Label | 16,799 | 313 | Target id exists |
| Disclosure (`<summary>`) | 801 | 31 | Native control |
| **Total** | **207,021** | **1,325** | |

Of the 1,325 distinct controls: 1,119 are verified end to end by the machine (the target was requested, the route matched, the method resolved or the id was found), 160 are verified to have a handler but the handler is not executed, and 46 are native or lead off the site and are only identified.

## Defects found and fixed

Line numbers are on `main` at `dd18954`. Each row names the test that now fails if the defect returns. "Sweep" is `InteractionSweepTest`; "Image" is `ImageIntegrityTest`.

| # | Where | What was wrong | Root cause | Fix | Guard |
|---|---|---|---|---|---|
| 1 | `resources/views/questions/view.blade.php:41`, `solutions/view.blade.php:84,86`, `livewire/answers.blade.php:41`, `livewire/solution-confirmations.blade.php:75`, `livewire/moderation/queue.blade.php:80`, `partials/mention-chips.blade.php:8`, `seminars/show.blade.php:28`, `livewire/question.blade.php:12` | A person's name was a link to their profile for everyone. The profile page answers 404 unless the viewer is that person or shares a company with them, so on every public question, answer, solution, seminar and in the moderation queue the author link was a 404. | The link was written without the rule the page enforces (`PagesController::show`). | New `User::profileOpenTo()` holds the rule once; the controller uses it; new `<x-ix.person>` renders a link only when it is true, otherwise the plain name. | Sweep (member, owner, moderator walks) |
| 2 | `resources/views/livewire/search.blade.php:65-67` | On every public page the search panel offered a visitor "People", "Files" and "Documents". People sent them to sign in; the two buttons did nothing at all (`Search::openPlatform` returns for a visitor). | The panel was written for signed-in people and reused on the public layout. | The three shortcuts are inside `@auth`. | Sweep `test_a_visitor_is_not_offered_what_only_a_signed_in_person_can_use`, and the closed sign-in-wall list |
| 3 | `resources/views/business/verification.blade.php:113` | "Withdraw the application" asked nothing and withdrew at once. | The confirmation was an inline `onsubmit=`; the policy's `script-src-attr 'none'` blocks inline handlers, so it never ran. | Alpine `@submit="confirm(...) \|\| $event.preventDefault()"`, the pattern the seminar pages use. | Sweep; Image `test_no_view_carries_an_inline_handler_or_a_placeholder_link` |
| 4 | `resources/views/seminars/show.blade.php:167` | Clicking the share link field did not select it. | Inline `onfocus=`, blocked the same way. | Alpine `@focus="$el.select()"`. | Same two |
| 5 | `resources/views/profile/update-password-form.blade.php:19` | The "New password" label did not focus its field. | The field's id `password` is also the id of the page section around it (`profile/edit.blade.php:27`), so the label pointed at the section. | Field id is `new_password`. | Sweep (duplicate id) |
| 6 | `resources/views/teams/update-team-name-form.blade.php:35` | Same for "Team name": id `name` twice on team settings. | Section id `name` in `teams/show.blade.php:37`. | Field id is `team_name`. | Sweep |
| 7 | `resources/views/teams/team-member-manager.blade.php:30` | The "Role" label pointed at an id that does not exist. | The role chooser is a group of buttons, not a field. | The label is the group's name (`role="group" aria-labelledby`). | Sweep (label target) |
| 8 | `resources/views/seminars/form.blade.php:60` | With paid seminars on and no payout account, "Ticket price" was a label for a field that is not shown. | The label sat outside the condition that shows the field. | The label is inside it; the other branch has a plain heading. | Sweep (everything-on walks) |

Defects 9 to 12 are about pictures and are in `IMAGE_AUDIT.md` (a blocked outside avatar on Analytics, pictures whose files are gone, people shown three different ways).

## Found and not fixed

| Item | Why it was left |
|---|---|
| `public/favicon.ico` is a zero-byte file | Which mark becomes the icon is a brand decision. See `IMAGE_AUDIT.md`, decision 1. The sweep names this one file as a known exception and fails on any other empty file. |
| Six pages under `/sub-services/*` say "has no workspace inside InfoDot yet" | No link anywhere leads to them (the sweep finds none), so nobody is shown a placeholder. They are walked as entry pages and their own links work. Removing the routes is an owner decision. |
| `resources/views/seminars/form.blade.php:63` tells a host that "Paystack's processing fee comes out of your share" | Shown only with paid seminars on (off in production). Whether the sentence is still right now that payments go through Dot.Finance is for the owner. |
| Names of people outside your company are plain text | This follows the existing rule that a profile is for the person and their colleagues. Opening profiles to every signed-in person would be a product decision. |

## Remaining exceptions

- **Handlers are checked for presence, not run.** A button with an Alpine or Livewire handler is verified to have one, and a Livewire method is verified to exist. The sweep does not press it. Defect 2 (a method that exists but does nothing for a visitor) was found by reading the component, and now has its own test.
- **Pages not walked.** The two-factor challenge (needs a half-finished sign-in), a seminar receipt (needs a paid ticket), `/open/{resource}` (needs a registered resource on another platform), accepting a team invitation (a signed link that changes membership). Their routes exist and have their own tests.
- **Views no walk rendered (52).** 22 are email templates (not pages). 7 are error pages rendered through the framework's own error namespace (404, 403 and 503 are inspected in the way-in test; 413, 419, 429 and 500 are not). The other 23 are not mounted anywhere or need a state the walk does not create: `livewire/question`, `livewire/question-list`, `livewire/solution-list`, `partials/aside-left`, `partials/aside-right`, `layouts/footer`, `notifications/index`, `api/*` (the API-token feature is off), Jetstream's `welcome`, `dropdown`, `nav-link` and similar stock components, `auth/oauth/authorize` (first-party platforms skip consent), `auth/two-factor-challenge`, `reputation/off`, `seminars/receipt`. The unmounted ones still contain old markup; they are covered by the static checks in `ImageIntegrityTest` (no inline handler, no link to nowhere, no hand-built profile picture).
- **External links (9 distinct) are not fetched.** The sweep never leaves the app.
- **JavaScript is not executed.** A script error in `resources/js` would not be seen here; `FrontendScriptsTest` covers the scripts.

## What ran

- `InteractionSweepTest`: 8 tests, 384 assertions, about 20 seconds.
- `ImageIntegrityTest`: 12 tests, 1,342 assertions, about 3 seconds.
- Whole suite: 1,586 passed, 4 skipped (1,566 passed before this change).
- Pint and PHPStan pass. The PHPStan baseline went from 136 to 135 (the entry for the removed `PagesController::sharesATeam`).
- Defects 1 and 3 to 8 were reported by the sweep before they were fixed. Defect 2 was found by reading the component and its test was written with the fix. The missing-file guards were checked by switching the fallback off and watching four tests fail.
- Not done: the changed pages were not looked at in a browser. The markup uses the same `ix-` classes as before and no CSS changed.
