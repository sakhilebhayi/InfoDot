# R-63 — Business Booking & Appointment Management: audit and phased plan

Status: audit complete (read-only), nothing built. Brief: `R63_BRIEF.md`. Date: 2026-11-25.

## 1. What exists today (verified in the code)

| Area | Finding |
|---|---|
| Public business page | `GET /business/{team:uuid}` (`BusinessProfileController::show`, `resources/views/business/show.blade.php`, Blade with the Yard `ix-*` classes). Guests can view it. The call-to-action cluster already holds "Message business" (signed-in only, `business.message`) and the tracked call, email and website buttons. "Book an Appointment" belongs in this cluster. |
| Business and workspace | A business is a Jetstream team with `personal_team = false`, plus `team_profiles` (visibility, `accepts_enquiries`, contact details), `BusinessService` (name, kind service or product, description, `price_note`: no durations), `BusinessLocation`. `Team::isVisibleTo()` decides who may see a business. |
| Roles | `TeamRoles`: owner, admin, manager, member. `TeamPolicy` has `updateProfile`, `addTeamMember`, `managePlatforms` and so on. The enquiry inbox treats owner plus administrators as the people who answer (`EnquiryService::handlers`). Nothing today gives an individual employee their own schedule. |
| Tenant isolation | `team_id` on every business record, route binding by team uuid, policies, and cross-tenant tests as a standing pattern. |
| Scheduling | **None.** No appointment, booking, slot or working-hours code. Seminars is the only dated feature (`starts_at`, `duration_minutes`, capacity, `seminars:remind` every ten minutes) and it is a different model. |
| Time zones | **None.** `app.timezone` is UTC, and neither team, profile nor user has a time zone. Booking needs a business time zone added. |
| Notifications | `InAppNotices` and `UserNotice` (kinds, dedupe key, a person can turn a kind off), one `Mailable` per domain (`EnquiryActivity`), a scheduled reminder precedent (`business:enquiry-follow-ups`). The queue is `sync`; the scheduler is `app/Console/Kernel.php`. The enquiry flow is the pattern to copy. |
| Feature control and entitlement | InfoDot is a core catalogue entry. `PlatformFeature` (platform key, feature key, state available/disabled/development/retired, kind free/paid) already has an admin screen and a public contract (R-61). A `bookings` feature under platform `infodot` gives the central ON/OFF switch with no new billing mechanism. Paid plans would go through Dot.Finance, which does not take payments yet. |
| Audit trail | Domain event tables are the convention (`BusinessProfileEvent`, `TeamLicenceEvent`, `SeminarTicketEvent`). A `booking_events` table follows it. |
| Rate limiting | Named throttle buckets are mandatory (a test fails an unprefixed `throttle:N,M`). |
| Front end | Blade pages plus Livewire and Alpine. CSP forbids inline handlers. `public/build` is tracked, so a new calendar needs a real asset build committed. |
| Database | Production is MySQL (CI proves migrations there); the test suite runs on SQLite, where row locks do nothing. A double-booking guarantee must be argued from MySQL behaviour and proved by a concurrency test that runs on MySQL. |

## 2. Design (proposed)

- **Own the data in InfoDot**, in new tables keyed by `team_id`: `booking_settings` (one row per business: enabled, calendar published, auto-confirm or approval, time zone, notice minutes, horizon days, cancellation cut-off, instructions, terms), `appointment_types`, `booking_providers` (a team member who offers appointments), `provider_working_hours` and `business_working_hours`, `booking_closures` (holidays and blocks), `bookings`, `booking_events`.
- **One availability service** is the only place that decides what is bookable. The calendar, the slot list and booking creation all call it; the front end never decides.
- **No double booking**: booking creation runs in a transaction that takes a row lock on the provider's schedule row, re-runs the availability check inside the lock, then inserts. A unique index on provider and start time is a second guard. A concurrency test runs against MySQL.
- **Time**: store UTC instants plus the business time zone; compute days and slots in the business zone; the browser shows them in the visitor's zone with the business zone named.
- **Public calendar** returns only a status per day and per slot (available, full, closed, outside window); never names, counts of people, or anything private. It is rate-limited and cached briefly.
- **Notifications** copy the enquiry pattern: in-app notice plus email per event, reminders from a scheduled command with a dedupe key per booking and reminder.
- **Entitlement**: a `bookings` feature of platform `infodot`, free in the first release.

## 3. Phases

| Phase | What | Needs from you |
|---|---|---|
| 1 | Tables, models, policies, the `bookings` feature switch, booking settings screen (enable, publish, approval mode, time zone, notice, horizon, instructions) | D2 to D5, D7 |
| 2 | Appointment types, providers, working hours, breaks, buffers, holidays and blocks, and the availability service with heavy tests (time zones, buffers, notice, horizon, blocks) | D3 |
| 3 | Public: the button, the booking page, the status-only calendar, slot list, booking creation with locking, automatic confirmation or pending approval, "my bookings" for the customer | D2, D11 |
| 4 | Business dashboard: requests, accept, reject, confirm, reschedule, cancel, day, week and month views, assignment, internal notes | D4, D13 |
| 5 | Notifications and reminders (in-app and email), calendar file (.ics) links, scheduler | D8, D9, D12 |
| 6 | Reports and analytics from recorded data only, CSV export, no-show marking | D10 |
| 7 | Hardening: rate limits, an independent security review, mobile and accessibility pass at the nine widths, MySQL concurrency test, retention and privacy tools | D14 |

Each phase is its own PR, tested, formatted and analysed; nothing is deployed without your word.

## 4. Decisions needed (recommendations in the right-hand column)

| # | Decision | Recommendation |
|---|---|---|
| D1 | Where bookings live | In InfoDot, as the brief says; no separate system |
| D2 | Who may book | Signed-in people only (anyone can view availability). Guests cannot book, which keeps spam and identity under control |
| D3 | Who counts as a service provider | Team members who opt in, plus an "anyone available" pool per appointment type. People outside the company as providers later |
| D4 | Permissions | Owner and administrators: everything. Managers: bookings and availability for the business. Members who are providers: their own availability and their own appointments only |
| D5 | Time zone | New business time zone, default Africa/Johannesburg, set in booking settings |
| D6 | Fees | Show a fee as information only; no online payment in this release (Dot.Finance is not taking payments) |
| D7 | Eligibility and entitlement | Any business with a published profile may switch bookings on. Free, under a `bookings` feature of InfoDot so the Control Centre can switch it off or later price it |
| D8 | Online meetings | A meeting link or instructions typed by the business; a Dot.Engage meeting link later |
| D9 | Calendars | A downloadable .ics file in confirmations; no Google or Outlook two-way sync |
| D10 | Completed and no-show | The business marks them; no automatic marking |
| D11 | Business customers | A person may say which company they book for; only the name is stored, no access to their company data |
| D12 | Reminders | 24 hours before to both sides, and 1 hour before to the customer; the business can change this |
| D13 | Reschedule | The customer asks, the business accepts or proposes another time; cancellation cut-off hours set by the business |
| D14 | Retention and privacy | Keep booking records 24 months after the appointment, then remove the personal details; include them in the existing data export and erasure |
| D15 | Public calendar scraping | Status-only data, rate limited, cached for 30 seconds; the business can hide the calendar entirely |

## 5. Not verified

Production data and settings; how the business page looks in a browser at each width; the real MySQL locking behaviour (it is argued here, to be proved by the phase 7 concurrency test); whether InfoDot's own email sender handles .ics attachments; how many businesses are eligible today.
