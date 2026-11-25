# R-64 audit and phased plan (phase 1 of the programme)

Read-only audit of InfoDot on 2026-11-25, against `R64_BRIEF.md`. Nothing was changed in the app. Counts are line counts over `resources/views` unless marked "est."; tokens, motion and progress sources were read directly, the forms and notification inventories were gathered by two read-only reviewers reading the code (not rendered in a browser). Items marked *unverified* need a look in a running browser before the phase that depends on them.

## 1. Headline findings

1. **There is no single form language.** Form fields are `ix-input` (36 px, 3 px focus wash), but the two "search bars" the brief calls the reference are different controls (header pill `.dsr` from the shared dot-ui package; hero `.lp-search` pill in `infodot.css`). Fields match neither. The redesign must first decide which of the two is the reference.
2. **State coverage is thin.** Fields have base, hover, `:focus` and a border-only invalid state (input and textarea, not select). Missing: `:focus-visible`, disabled, read-only, valid, loading, required marker, select arrow and height, reserved error space. Errors are not linked to fields (`aria-describedby` on 12 files, `aria-invalid` on 5).
3. **Native controls everywhere.** 46 of 47 checkboxes, all 22 radios, every date and time input, all selects and file inputs are browser-native. There is no toggle/switch, searchable select, multi-select, date picker or rich text. One real uploader exists (`attachments/_uploader.blade.php`, drag and drop; upload progress hook exists in `resources/js/attachments.js`).
4. **No field components.** `app/View/Components` has only two layouts; the only wrappers are Jetstream's (`x-jet-input`, `x-jet-label`, `x-jet-input-error`, `x-jet-button`), which are a single change point worth keeping.
5. **Legacy CSS in places:** `.ix-error` is defined twice (`infodot.css:553`, `:3900`; the later wins); six page-specific `.ix-input` overrides (one at 56 px, one at 36 px); a global rule strips the focus ring from search inputs (`:6646-6660`); `@tailwindcss/forms` still resets unclassed controls; Tailwind classes remain on the team forms; 299 inline `style=` lines (26 on form elements). No inline event handlers (good for the CSP).
6. **Mobile touch targets are under 44 px** (fields and buttons 36 px, 30 px small, native checkboxes 13-16 px), and field text is the body-small token (est. 14 px) so iOS zooms on focus (verify the token value).
7. **Motion is real but fragmented.** One easing token (`--ix-ease`), three keyframes, about 48 transitions, durations ranging 0.1 s to 0.6 s with no scale. Reduced motion is handled globally (`app.css`) and in the two GSAP files. GSAP + ScrollTrigger already ship for the landing page (`landing.js`), the tools page (`tools.js`) and the public inner pages (`pages.js`: reveals, clip-path openings, scrubbed photo drift). There are no page transitions, no skeletons, no shared toast, no consistent loading state (14 views use `wire:loading`; button loading exists only in `x-auth.submit`).
8. **Progress today:** a Getting Started block (`App\Support\GettingStarted`, five steps read from the database, nothing stored), achievements bars (`ix-ach__bar`), `ix-meter` bars (insights), and `<progress>` on the new booking reports page, which **has no CSS and shows the browser default** (fix in the progress phase). Real data exists for profile, onboarding events, company setup, platform activation, licences and seats, booking utilisation; project and task progress live in other platforms.
9. **Notifications: one system, and it is sound.** `UserNotice` + `InAppNotices` (ids only, no text stored, unique per user+kind+ref, actor never notified, visibility re-checked when read, service kinds always delivered). Gaps are in the experience, not the foundation: the bell is a plain link to `/notifications`; the page has no categories, actions, dismiss or archive; only `read_at` exists. The unread count is one indexed, uncached query per page.
10. **Email is branded but synchronous.** Markdown mail theme (palette, 570 px, system font not Roboto), eight Mailables, no queue (`QUEUE_CONNECTION=sync`, no `ShouldQueue`), no unsubscribe or preferences link, no global cap (bookings cap 20 a day, enquiries a quiet window, licences and points dedupe in the database). Security events are silent (password changed, new sign-in, 2FA, other sessions logged out). Team invitations are email only, with no in-app notice and no accept/decline from the bell. Team membership changes notify nobody.
11. **Preferences:** one switch (`users.notices_in_app`, silences contribution kinds only) and the points digest. No channel, category or frequency choice. The rule that security notices cannot be switched off exists only as `SERVICE_KINDS`.
12. **Shared package:** `dot-ui` (checksummed manifest) carries the platform switcher, logos and Tailwind preset. It does **not** carry form CSS; each platform keeps its own. So "one language across every platform" means porting InfoDot's finished tokens and components into each platform's stylesheet, one at a time, not a package bump.

## 2. What must be preserved (behaviour that works)

Jetstream wrappers as the single change point; `x-auth.submit` loading pattern; `.ix-pw` password toggle; the attachments uploader; `mention-box`; `x-dot.search` and `x-dot.filter-search` (keyboard model, combobox ARIA, `dsr-*` hooks used by `R52ShellTest`); `ix-form__row` stacking; `ix-choice` fieldset and legend groups; `--ix-*` tokens, the `.ix :focus-visible` policy, dark theme variables; zero inline handlers; notice idempotency and privacy properties; the 20-a-day booking cap and enquiry quiet window; `try/catch` around every send; `notices:prune`.

## 3. Risks

- `public/build` is tracked: every CSS phase commits a rebuild (and the icon font is a subset, so new icons need the font rebuilt; fonttools is not installed here).
- Unclassed controls currently pick up the Tailwind forms reset; classing them changes their look everywhere at once (intended, but it needs a screenshot sweep).
- Mail is sent inline: more notification volume without a queue slows requests. Queue before volume.
- Notice `kind` is `string(32)`: longer new kind names fail at insert.
- A removed team member may still see old notice text naming the company (describe() for `business` has no membership check) — unverified for the exact text; fix in the notification phases.
- 11,075-line `infodot.css` with three `:root` blocks: tokens must be consolidated carefully, not duplicated.
- Inline `style=` is a risk if `style-src` is ever tightened (not checked).

## 4. Phased plan (adapted from the brief, with the audit's order)

Each phase is one PR, merged on green, nothing deployed without instruction. InfoDot first; other platforms later, one at a time.

| # | Phase | What | Notes |
|---|---|---|---|
| 2 | Tokens and motion system | Consolidate spacing, radius, height, focus ring, duration and easing tokens (CSS-first); `prefers-reduced-motion` alternatives; no new dependency | Pure CSS, build committed |
| 3 | Core form components | Blade components: field (label, hint, error with reserved space), input, textarea, select, checkbox, radio group, toggle; all states; fix `.ix-error`, global search ring, 44 px touch height; keep Jetstream wrappers delegating to them | Biggest visual change; screenshot sweep at nine widths |
| 4 | Complex controls | Searchable select, date and time picker, uploader polish (real progress), password feedback, rich text only if a form needs it | Alpine, no new dependency |
| 5 | Progress components | linear, circular, step, milestone, compact; real sources only (Getting Started, profile completeness, onboarding, platform activation, licence seats, booking utilisation); style the bare `<progress>` | `role=progressbar` + live announcements |
| 6 | Notification centre | Bell dropdown with unread count, categories, dismiss and archive, actionable invitation accept/decline, preferences hook | Extend `UserNotice`; no second system |
| 7 | Email | Queue the mail (with failure handling), template pass (Roboto-safe stack, logo, dark mode check), unsubscribe/manage link, security and invitation mails | Sending identity is a decision |
| 8 | Preferences and digests | Channel × category matrix, unswitchable security rule, digest, grouping and dedupe | Needs the notification categories decided |
| 9 | Scroll and page motion, micro-interactions | Reuse GSAP already shipped for marketing pages; CSS for the app; page transitions only if cheap | Real browser performance check |
| 10 | Loading, success, error, empty states | Skeletons, button loading, toasts, confirmation, empty states | |
| 11 | Rollout and alignment | Fix per-page overrides and inline styles across all forms; then other platforms one at a time | Order is a decision |
| 12 | QA | Nine widths in a real browser, accessibility, low-power performance, tests for delivery, permissions, unread counts, progress calculations, reduced motion | |

## 5. Decisions needed from the owner before phase 2

1. **Reference control.** Make the header search pill (`.dsr`) or the hero pill (`.lp-search`) the reference for field proportions? Recommended: the header pill's finish (focus ring, surface) with the form-field height and radius that suit dense forms, so fields are related to search but not pills.
2. **Motion tooling.** CSS only for the app, with the GSAP already shipped confined to marketing pages? Recommended: yes.
3. **Notification categories and defaults.** Proposed: account and security (always on), business workspace, team invitations, bookings, documents and files, billing and subscriptions, platform activity, announcements. Defaults: security instant and unswitchable; invitations and bookings instant; everything else daily digest by email, in-app instant. Confirm or change.
4. **Email identity and queue.** Confirm the sender name and address, and that production may run a queue worker (cron or supervised) so mail can leave the request.
5. **Rollout order** for the other platforms, and who owns shared styling changes (dot-ui carries no form CSS today).
6. **Touch size.** Move all fields and buttons to 44 px on touch screens (recommended) while keeping 36 px on desktop?
