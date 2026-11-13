# R-33 — Business Directory and Business Discovery: final report

Brief: `requirements/infodot-business-directory.md` (owner, 2026-11-01). Plan and per-phase notes:
`R33_BUSINESS_DIRECTORY.md`. This report answers brief §14. Everything below is live on
infodot.co.za unless it says otherwise.

| Phase | What | PR | Live |
|---|---|---|---|
| 1 | Directory, search, visibility, categories, services, guest-safe pages | #190 | a5552a1, 2026-11-01 |
| 2 | Profile showcase, contact controls, preview, audit trail | #191 | bf2ddf7, 2026-11-01 |
| 3 | InfoDot Inbox and enquiry management | #192 (+ #193 test fix) | 4d579e3, 2026-11-01 |
| 4a | Reports, investigations, restrictions, appeals (completes R-29 Part D) | #194 | 289779a, 2026-11-01 |
| 4b | Collections, similar businesses, places, featured rules, insights | #195 | fbb5f85, 2026-11-01 |

Each deploy was preceded by a fresh InfoDot backup that was verified (`verify ok`) before merging.

## 1. Architecture reviewed

- **The business** is the Jetstream Team (uuid, owner code), with the R-29 `team_profiles`, media
  (logo, cover, gallery as team attachments with GD-processed derivatives and history), R-29
  verification, protected identities and identity conflicts. R-33 extends these; it adds no second
  business or identity system.
- **Search**: Scout + TNTSearch exists for knowledge content, with host-side index files rebuilt by
  hand. The directory uses explainable SQL plus scoring instead, behind one service
  (`BusinessDirectory`) so a full-text engine can replace it as the directory grows.
- **Messaging**: none existed. The brief's "existing InfoDot Inbox" was built new, as the only
  conversation system. Dot.Engage is a campaigns platform, so nothing competes with it.
- **Notifications**: `user_notices` and `InAppNotices`, plus SMTP mail. Both are reused.
- **Moderation**: the existing `/moderation` desks. Business trust gets its own desk tab. Knowledge
  `content_reports` were deliberately not reused, because they carry a different reason set and feed
  Dot Points events.
- **Layout**: the app layout assumed a signed-in user. A guest top bar was added so business pages
  and the directory open without an account.

## 2. Features implemented

- **Directory `/businesses`**: plain-language search, with the location read out of the query (for
  example "accounting firms in Johannesburg"). Every result says what it matched. Filters: category,
  location, type, size, verified, order. Grid and list views. Browse panels: categories with
  counts, services on offer, places, recently listed, and featured. Pagination.
- **Visibility**: each business chooses Anyone, InfoDot users, or Only this business. The directory,
  pages, sitemap, files, similar businesses, featured placements and enquiries all respect it.
- **Business pages**: identity plate and verification, categories, services and products (with
  pictures and the price as the business wrote it), portfolio, locations, announcements, gallery,
  brochures, and InfoDot activity.
  - Contact actions are Call, Email, Website, and Directions (only when an address is published) or
    On the map, all from the business's own public details.
  - The page also offers Save to a collection, Report, and Similar businesses.
- **Profile management**: listing and type, categories, services and products, portfolio,
  locations, announcements, brochures, per-field public contact switches, accepting enquiries,
  preview as a visitor, an audit trail of every change, standing and appeals, and asking to be
  featured.
- **InfoDot Inbox**:
  - Message Business opens a conversation owned by the business, with a structured enquiry
    (category, subject, message, files).
  - The business side has an inbox with filters, assignment, internal notes, statuses (New to
    Closed), follow-up reminders and recorded numbers. The customer has their own inbox.
  - An unread count sits beside the bell, with notices and spaced emails.
- **Trust**: reports in 10 categories, and a reviewer desk for investigations with an audited trail.
  A restriction takes a business out of discovery without any public label. Appeals go to a
  different reviewer.
- **Discovery extras**: private collections, similar businesses that explain themselves, browse by
  place, and featured placement under published rules.
- **Insights**: page views, search appearances, search terms, services matched, contact actions,
  enquiries and first-reply times, all from recorded daily totals.

## 3. Database changes

All additive; the only data change was Phase 1 moving profiles that had content to "InfoDot users".

- **`team_profiles`** gains `visibility`, `business_type`, `public_contact` (json),
  `industries_served`, `accepts_enquiries` and `restricted_at`.
- **Directory**: `business_categories` (19 seeded), `business_category_team`, `business_services`
  (with `kind` and `price_note`).
- **Showcase**: `business_projects`, `business_locations`, `business_announcements`,
  `business_profile_events` (append-only audit).
- **Inbox**: `enquiries`, `enquiry_messages` (customer, business, note or event), `enquiry_reads`,
  `enquiry_blocks`.
- **Trust**: `business_reports`, `business_investigations`, `business_investigation_events`,
  `business_appeals`.
- **Discovery**: `business_collections`, `business_collection_items`, `business_features`,
  `business_stats_daily`, `business_search_terms_daily`.
- **Morph types**: `enquiry_message`, `business_report` and `business_appeal`, so files attach under
  the existing permission-checked attachment door.

## 4. UI/UX improvements

- Built in the existing Yard design system (`infodot.css`, `ix-*`), extending its vocabulary rather
  than introducing a new one. The design skills (impeccable, design-taste-frontend) were applied
  within it, and the dataviz rules were applied to the insights chart: one series per mark type,
  zero drawn as nothing, hover values and a table view.
- A large question-first search, explanations on every result, grid and list views, and filters
  that fold away on phones unless one is in use.
- Business pages present very different businesses (contractor, accounting firm, supplier) through
  optional sections that appear only when filled in.
- Every page was checked live at 1280 and 375 widths, with no horizontal scroll on phones.
- Fixes found along the way:
  - The icon font no longer flashes ligature words while it loads.
  - Raw Blade text no longer appears on two pages that showed it in production.
  - A unit test now guards every view against that second bug.

## 5. Inbox integration

- Conversations belong to the Team. The owner and administrators handle all of them; a member
  handles only those assigned to them.
- Replies go out as the business. Customers never see notes, events or which person answered.
- Bell notices count as service notices, so a business cannot miss an enquiry by muting
  contribution notices.
- Emails are spaced to one per conversation per 30 minutes and never contain the message text.
- Files go through the standard upload checks. Message files are visible only to the people in the
  conversation; files on internal notes only to the business side.
- Insights and the inbox numbers both report enquiries received and the median first reply.

## 6. Privacy and security controls

- **Business-controlled visibility**, enforced everywhere. Hidden and restricted businesses return a
  plain 404, never a label or a reason.
- **Contact details**: only what the business publishes, field by field. Team members' accounts,
  emails and phone numbers never appear on a business page.
- **Managing a business**: only the owner and administrators can manage its profile, items, inbox,
  standing and insights. Every item route checks that the item belongs to the business in the URL;
  anything else is 404.
- **Reviewers**:
  - Reviewers must be outside the business to act.
  - An appeal is decided by someone other than the original decider, enforced on the server.
- **Audit trails** cover profile changes, investigation steps, appeal decisions and enquiry events.
- **Abuse limits**:
  - Enquiries: per-person and per-business daily limits, duplicate refusal, a minimum length, and
    per-business blocking.
  - Reports: one open report per person per business, and 5 a day.
  - Route throttles throughout.
- **Analytics**:
  - Stored as daily totals only, with no visitor, account or IP data.
  - The business's own people, reviewers, previews and crawlers are excluded.
  - Search terms are shown only after they have been seen 3 times.
- **Crawlers**: members-only and private business pages carry `noindex`, and the sitemap lists
  public businesses only.

## 7. Tests performed and results

- **Automated tests**: 63 tests across these files.
  - R-33: `BusinessDirectoryTest` (11), `BusinessShowcaseTest` (9), `EnquiryInboxTest` (10),
    `BusinessTrustTest` (8), `BusinessDiscoveryTest` (6), `BladeDirectiveGlueTest` (1).
  - R-29 tests adjusted for the private default: `BusinessProfileTest` (8),
    `BusinessVerificationTest` (10).
- **Brief §13 coverage**: discovery (enable, appear, search by name, industry and service, filter by
  location, open, private hidden); profile (admin edits, logo and cover, services, public contact
  details, outsiders see only public information); contact (start a conversation, team receives it,
  business responds, notices delivered, permissions enforced, phone and email actions); security
  (Business A cannot edit B, ordinary users cannot see private information or manage enquiries,
  conversations stay isolated, hidden businesses cannot be found).
- **Full suite at the last PR**: 1423 passed, 4 skipped. PHPStan 0 errors and Pint clean on every
  PR. CI was green on every PR, including the MySQL migration job.
- **Live rounds** on a dev copy with the mail driver set to log:
  - guest and signed-in directory and business pages;
  - every edit section;
  - a real enquiry between two accounts, with the emails checked for leaked text;
  - a report worked into a restricting investigation and back;
  - a featuring request granted;
  - a collection saved;
  - counted views, searches and calls shown on insights.
  - Phone and desktop widths were checked throughout.
- **Bugs caught and fixed before shipping**:
  - `profileOrNew()` created duplicate rows on a second call.
  - The logo relation could pick a gallery image.
  - The desk lost a business's uuid.
  - A zero-day chart drew stub bars.
- **Deploy flake**: one deploy failed when a random CSP nonce spelled part of a name the public-pages
  test looks for. The deploy was re-run, and #193 made that test robust.
- **Post-deploy checks after every phase**: `/up`, `/ready`, `/login` and `/businesses` answered 200,
  the migration's DONE line was present, and the new styles and scripts were being served.

## 8. Outstanding issues (honest state)

- **The public directory is empty until businesses opt in.** Existing profiles were moved to
  "InfoDot users", as R-29 had promised them. Visitors see only businesses that choose "Anyone".
- **Paid featuring is not offered.** Payments are not live, and the price is the owner's decision. The
  rules page says paid placements would be labelled.
- **No malware scanning on uploads.** This applies to enquiry files, brochures and report or appeal
  evidence (the R-15 owner decision). Backups also exclude uploads (`uploads=none`).
- **No map.** Addresses are unverified and there is no geocoding, so places are listed instead.
- **No moderator screen for categories.** The 19 categories are seeded; changes need a database
  edit.
- **Insights start counting at deploy (2026-11-01).** History before that does not exist.
  Contact-action counts depend on the browser reporting the tap.
- **Follow-up reminders run at 07:30 server time.**
- **The Inbox is not real-time.** New messages appear on reload or through the bell, not as live
  pushes.
- **Owner launch gates from earlier programmes still stand**: domain renewal before 27 Nov, the
  restore drill, and the Paystack test window.

## 9. Recommended future enhancements

1. **Business opportunities (§8)**: request-for-quotation and procurement workflows built on the
   Inbox. For example, one structured request goes to several matching businesses, each reply
   tracked separately.
2. **Saved searches and alerts**: tell a person when a new business matches a search they saved.
3. **Category management screen** for moderators, with merge and retire.
4. **Real-time Inbox** through the ecosystem notification layer, with canned replies and enquiry
   templates for businesses.
5. **Verified addresses**, so a real map is possible: confirm a location with a utility bill or a
   site visit, then geocode only confirmed addresses.
6. **Paid featuring** once payments are live, clearly labelled as paid, with the price on the rules
   page.
7. **Insights export (CSV)** and a monthly emailed summary for business owners.
8. **Full-text search engine** behind `BusinessDirectory` once the directory grows past a few
   thousand businesses.
9. **Reviews or endorsements**, only after a design that resists manipulation; deliberately not
   built here.
