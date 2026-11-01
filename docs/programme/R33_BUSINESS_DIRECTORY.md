# R-33 — Business directory and discovery: audit and phased plan

Brief: `requirements/infodot-business-directory.md` (verbatim, 2026-11-01). Inspected before
any code, per brief §12 and §14.

## What exists (build on this, not beside it)

| Need | Existing foundation | State |
|---|---|---|
| The business | Jetstream **Team** (uuid, 4-letter owner code) + R-29 `team_profiles` (trading name, description, industry, category *free text*, products/services *free text*, location, regions, website, contact email/phone, registration no./country, established, size, links) | Profile exists; categories and services are unstructured text |
| Public page | `/business/{uuid}` (R-29 Phase 1) — Yard design, logo/cover/gallery, registry and contact rail | **Signed-in users only** — the whole app sits behind `auth:sanctum` |
| Branding media | R-15 attachments + `TeamMediaService` (GD crop, history, fingerprints) | Reusable for service images and portfolio |
| Trust | R-29 Phase 2 verification + approval plate + trust card; Phase 3 protected identities, conflicts, linked businesses | Reusable as-is: directory filters on it, badge appears on listings |
| Identity vs marketing | Phase 2/3: name, trading name, registration no./country and website changes send a verified business back for review | Already enforces §6 "registered identity is not editable marketing" |
| Search | Scout + **TNTSearch** on Questions/Solutions/User/Comment/Obj; index files live on the host and need a manual `scout:import` | Not used for businesses (see below) |
| Messaging | **None.** No inbox, conversation or message model exists anywhere in InfoDot (a code comment says so); Dot.Engage is a campaigns platform, not messaging | The brief's "existing InfoDot Inbox" has to be **built** — the first and only conversation system, so nothing competes |
| Notifications | `user_notices` + `InAppNotices` (kinds, service kinds, unread bell) | Reused for enquiries |
| Reports / moderation | `content_reports` (questions/solutions), `/moderation` desks, appeals models | Extended for business reports in Phase 4 |
| Layout | `layouts/app.blade.php` + `navigation-menu` assume a signed-in user | Needs a guest top bar for public business pages |

## Decisions

1. **Visibility** (§11) is one setting on the profile: `public` (listed; guests can open the
   page), `members` (listed for signed-in InfoDot users only), `private` (not listed; the page is
   for the business's own members). New businesses start **private** — discovery is opted into.
   Businesses that already filled in a profile were told "everyone on InfoDot sees" it (R-29
   Phase 1), so the migration keeps them visible as `members`.
2. **Search** is explainable SQL scoring over real profile data (name, trading name,
   description, industry, categories, services, location, regions): every result says what it
   matched. "in X" / "near X" in a query becomes a location filter. No AI and no index to
   rebuild by hand; the query layer is isolated so a full-text engine can replace it as the
   directory grows.
3. **Categories** are a central table (the brief's 19), chosen many-to-many; moderators manage
   them. The old free-text `category` stays as the business's own wording.
4. **Services** become structured rows (name, description; images, pricing notes and
   documents in Phase 2), searchable on their own.
5. **Inbox** (Phase 3) is business-to-person conversations owned by the business (Team), not an
   employee: any owner/admin — or the member a conversation is assigned to — answers; customers
   never see internal notes or other conversations.
6. **Featured placement** has transparent rules shown on the page and is never confused with
   verification; it ships only when its rules are agreed (Phase 4). Nothing is ranked by payment
   before then.

## Phases (one PR each; owner approval per merge/deploy)

1. **Directory and discovery** — visibility setting + migration, central categories, structured
   services, business type, `/businesses` directory (natural search with explanations, filters:
   category, location, region, size, type, verified; grid/list; browse popular categories,
   recently listed, by service; pagination), guest-safe public pages for `public` businesses,
   contact actions from the business's own public fields (call, email, website, directions),
   `Businesses` navigation, sitemap entries for public businesses, tests.
2. **Profile showcase** — products/services with images and published pricing notes,
   portfolio/projects, operating locations and service areas, announcements, brochures (PDF),
   per-field control of public contact details and address, "preview as a visitor".
3. **InfoDot Inbox and enquiries** — Message Business, structured enquiries, business inbox
   (statuses New → Closed, assignment, internal notes, attachments, unread, notices, follow-up
   reminders, response times), customer inbox, rate limits and spam protection.
4. **Trust, reports and discovery extras** — business reports (impersonation, incorrect
   information, suspicious, misleading, fraud, inappropriate) into a review workflow with appeals
   (this absorbs R-29 Phase 4), collections, similar businesses, featured placement with
   published rules, map view where locations are reliable, analytics from recorded events only
   (views, search appearances, contact actions, enquiries, response times).

## Phase 1 — shipped in this PR (2026-11-01)

- **Visibility** on `team_profiles` (`public` / `members` / `private`, default `private`). The
  migration moved every profile that already had content to `members`, which is what those
  businesses were promised under R-29. Owners and administrators choose it on the edit page
  (*Directory listing*); editors and outsiders cannot.
- **Listed** means: not a personal workspace, visibility the viewer may see, and a description.
  An empty profile is never listed, and the edit page says so.
- **Business page** `/business/{uuid}` is outside sign-in. Who may open it: public → anyone;
  members → signed-in people; private → the business's own people and moderators. Everyone else
  gets 404, so a hidden business cannot be confirmed by guessing.
- **Branding files** (`/attachments/{uuid}`) are outside sign-in too. Current logo, cover and
  gallery of a business follow the page's rule; replaced branding stays with owners/admins and
  moderators. Every other file still sends a signed-out visitor to sign in, exactly as before.
- **Directory** `/businesses`: one search box that reads plain questions ("accounting firms in
  Johannesburg" → `accounting` located in/serving Johannesburg), filters (category, location,
  type, size, verified, order), grid/list, 24 per page, browse panels (categories with counts,
  services on offer, recently listed). Each result says what it matched on. Visitors see a note
  that signed-in people see more.
- **Categories** (the brief's 19, up to three per business) and **services** (name +
  description, up to 30) are structured and searchable on their own.
- **Contact actions** on the business page use only what the business published: Call
  (`tel:`), Email (`mailto:`), Website, and "On the map" (a Google Maps search for the business
  name and its stated location — not a pinned address). No team member's account details appear.
- **SEO**: public business pages and `/businesses` are in `sitemap.xml`; members-only and private
  pages carry `noindex`; business pages carry the business's name as their title.
- **Fixes found on the way**: `Team::profileOrNew()` did not remember the row it created, so a
  second call on the same model tried to insert another; the icon font now uses
  `display=block`, so icon names no longer flash as words while it loads.

Not in Phase 1 (by plan): Message Business / Inbox (Phase 3), per-field contact controls and
preview-as-visitor (Phase 2), reports, featured placement, collections, similar businesses and
analytics (Phase 4). A moderator page for managing categories is not built; the 19 are seeded and
can be changed in the database until it is.

## Phase 2 — shipped in this PR (2026-11-01)

- **Services and products**: each entry is a service or a product, with an optional picture
  (one current; a replacement keeps the old one on the record) and an optional price written by
  the business and shown exactly as written. Nothing about price is inferred.
- **Portfolio**: up to 12 projects (title, what was done, year, where), up to 6 captioned
  photographs each. Removing a project takes its pictures off the page; they stay on the record.
- **Locations**: up to 10 (office, branch, workshop, store). The street address shows only when
  the business ticks it; otherwise town and province. A published address drives the
  **Directions** button; otherwise "On the map" searches the town. Directory location search now
  also matches these towns and provinces.
- **Announcements**: up to 5 showing at once, with an optional end date. Withdrawing ends one
  now; the row stays as the business's history.
- **Brochures**: up to 5 PDFs, checked to be real PDFs within the attachment size limit, stored as
  uploaded (a PDF cannot be re-encoded like an image) and streamed under the same visibility rule
  as the page. *No malware scanning exists on the host yet; that owner decision from R-15 applies
  here too.*
- **Public contact details** (§5B): phone, email, website and social links each have "Show on the
  page". Hidden details stay on record and never reach the page; the business's managers see a
  line naming what is kept off. Profiles that never chose show what they filled in, as before.
- **Industries served** on the profile; "Operating regions" is now labelled **Service areas**.
- **Preview as a visitor** (`?as=visitor`, managers only): the page exactly as an outsider sees
  it, with a banner saying who can open it right now. Anyone else's `?as=visitor` is ignored.
- **Audit trail** (§11): every change to the public presence (details, listing, contact choices,
  pictures, offerings, projects, locations, announcements, brochures) is written to
  `business_profile_events` with who made it, and the latest 15 show on the edit page. It is
  append-only; there is no route that edits or deletes it.
- **Who manages this profile**: the edit page says plainly that the owner and administrators do,
  and links to team roles.

Still to come: Phase 3 (InfoDot Inbox and enquiries), Phase 4 (reports, featured placement,
collections, similar businesses, analytics).

## Phase 3 — shipped in this PR (2026-11-01)

The InfoDot Inbox, built new (InfoDot had no messaging), as the one conversation system.

- **Message business** on every business page a person may open (sign-in required; guests are
  sent to sign in and back). The enquiry has a category, a subject, the message and up to 3
  files (same upload checks as all InfoDot files). A business can switch enquiries off on its
  edit page; members of a business cannot message it.
- **Owned by the business** (`enquiries.team_id`). The owner and administrators handle every
  enquiry; the owner or an administrator can give one to any member, who then sees and answers
  only what they were given. Replies go out as the business — the customer never sees which
  person wrote them, and never sees internal notes or recorded events.
- **Statuses** New → Open → In progress → Awaiting response → Resolved → Closed. A business reply
  moves New/Open/In progress to Awaiting response; a customer reply reopens Awaiting/Resolved.
  A closed enquiry takes no more customer messages.
- **Enquiry management**: filters (status, who has it, subject), internal notes, assignment,
  follow-up dates (reminder notice at 07:30 server time via `business:enquiry-follow-ups`),
  history of status/assignment changes in the thread, and numbers from recorded data only — not
  yet answered, not yet resolved, received in 30 days, median time to first reply over 90 days.
- **Notifications**: one bell notice per recipient per message (service kinds, so a business
  cannot miss an enquiry by switching off contribution notices), and at most one email per
  person per conversation per 30 minutes. Emails say who and about what and link to InfoDot;
  they never contain the message.
- **Unread**: per person, per conversation; an Inbox icon with a count sits beside the bell.
- **Abuse prevention**: 10 new enquiries a day per person, 2 a day per business, the same words
  refused for 24 hours, a 20-character minimum, route throttles, and a per-business block that
  stops new enquiries (the person is not told who blocked them; existing conversations stay).
- **Isolation**: every business route checks the enquiry belongs to the business in the URL and
  that the person handles it; anything else is 404. Files on messages are visible only to the
  people in the conversation; files on internal notes only to the business side.

Also fixed: two live pages printed raw Blade (`here@if (...)`, `businesses@if (...)`) because a
directive glued to a word is not compiled; a unit test now fails if any view does that.

## Phase 4a — trust: reports, investigations, appeals (2026-11-02)

This also completes R-29 Part D (§20–23, scenarios 8–10). Phase 4 is split: 4a (trust) here, 4b
(collections, similar businesses, places, featured rules, analytics) next.

- **Decision: own tables, not `content_reports`.** Knowledge reports carry their own reason set and
  feed the Dot Points events; business reports need R-33/R-29 categories, priority, evidence and
  investigation links. `business_reports`, `business_investigations` (+ append-only
  `business_investigation_events`) and `business_appeals` keep them apart.
- **Reports**: signed-in people report a business they can see, in 10 categories (R-33 §7 and
  R-29 §21 merged), with detail and up to 3 files that only outside reviewers and the reporter can
  open. One open report per person per business, 5 a day, never about one's own business. Serious
  categories are high priority; urgent when the business holds a badge or several people have
  reported it. A report changes nothing about the business by itself.
- **Investigations** (moderators outside the business): open from reports or from an identity
  conflict; link more reports; notes; write to the business; take it out of discovery (requires a
  written message) or return it; conclude as cleared, corrected, confirmed impersonation or
  confirmed misconduct. Confirmed outcomes keep it out of discovery and revoke any verification.
  Every step is on the trail with who took it.
- **Restriction** = `team_profiles.restricted_at`. A restricted business is not listed, not in the
  sitemap, takes no enquiries, and its page is a plain 404 to everyone but its own people and
  reviewers. There is no public label anywhere.
- **The business** gets a notice and a *Standing and appeals* page (owner and administrators) with
  InfoDot's explanation — never the reporter or the internal summary — and a banner on its page
  and edit page while restricted.
- **Appeals**: one per decision (restriction, confirmed outcome, or a verification rejection,
  suspension or revocation within 90 days), with evidence. Decided by a moderator outside the
  business who did not make the original decision (separation of duties, enforced server-side).
  Upheld: an investigation is cleared and the restriction lifted; a suspension is reinstated; after
  a rejection or revocation the business is told it may apply again.
- **Reporters** learn only whether action was taken.

## Phase 4b — discovery extras and insights (2026-11-02). R-33 complete.

- **Collections** (§8): private lists per person (Suppliers, Potential partners, Service providers,
  Contractors, To contact later suggested; any name allowed), a note per business, rename/delete.
  A saved business that stops being visible to the person shows as "no longer available" — never
  by name. Limits: 20 collections, 200 businesses each.
- **Similar businesses** (§8): up to 4 on each business page, listed for the viewer, sharing a
  category or service words, each saying what it shares ("Same category: …", "Also offers: …").
- **Browse by place** (§8): towns and provinces from what listed businesses gave (structured
  locations plus the town in the profile location), each a link to a location search. **No map:**
  addresses are not verified and there is no geocoding, so drawing pins would invent precision.
- **Featured placement** (§8): rules published at `/businesses/featured` (listed for anyone,
  described, ≥1 service, a logo, not under review); a business's owner/admin asks; a reviewer
  outside it grants up to 30 days or declines with a reason; at most 3 show, daily-rotating, under
  the label "a placement … not a rating, a recommendation or a verification". It stops showing the
  moment the business stops meeting the rules. **Not sold:** paid featuring waits for live billing
  and an owner decision on price; the rules page says it will be labelled as paid if introduced.
- **Insights** (§9) at `/business/{uuid}/insights` for owner/admins, 30 or 90 days: page views,
  search appearances, contact actions (call, email, website, map), enquiries received and typical
  first reply, day-by-day chart with a table view, searches that showed the business, and services
  searches matched. **Recorded events only**, stored as daily totals with nothing about the
  visitor; one view per visitor per day; the business's people, reviewers, previews and crawlers
  are not counted; a search term shows only after it has been seen 3 times. Contact actions are
  reported by the browser (sendBeacon) and the page says so. Counting starts at deploy; the page
  states the date it started.
- Fixes in passing: none of the earlier phases needed changes; one Phase 4b bug (desk link without
  a uuid) was caught in the live round and is covered by a test.
