# R-50: platform catalogue, entitlements and commercial architecture

Status: approved direction, 2026-11-13. Owner decisions are recorded in section 2. Audit evidence is in
`AUDIT_INFODOT_NOTES.md`, `AUDIT_ANALYTICS_PAYMENTS_NOTES.md` and `AUDIT_FLEET_NOTES.md` beside this file.

## 1. What is wrong today

- A platform being in the list and switched on is the only thing checked. Any signed-in, verified person can open
  every platform; no company, role or plan is consulted.
- InfoDot's own Analytics page shows "N live / N registered" from that list, which reads as "your business has all
  of these".
- Platforms have a name, address, icon and colour only: no description, category, price or plan. Groupings are
  hard-coded in two places. Every sibling app carries its own copy of the list and they have drifted.
- No platform reads a plan or entitlement from InfoDot.

## 2. Owner decisions (2026-11-13)

1. **Four separate concepts**, never interchangeable: registration, operational status, business entitlement,
   commercial status.
2. **Free core for every company, with no activation step:** InfoDot, Dot.Files, Dot.docs, Dot.Tasks, Dot.Finance.
   Dot.Finance must stay reachable in every state, because it is where a company pays.
3. **Existing companies start with the free core only**, like a new company, and activate the rest themselves.
4. **Unpriced platforms can be activated free by the company itself**, shown as "free while pricing is being
   finalised". Nothing is ever charged without a new, explicit consent step once a price exists. No price is
   invented: a platform without an approved price is flagged "pricing not configured" for administrators.
5. **A new platform administrator role** (granted only by a command on the host) manages the catalogue, prices,
   bundles and complimentary access. Moderators get no commercial powers. Every change is audited.
6. **Discontinued platforms** (three, removed 2026-11-13) never appear. **Payments go through Dot.Finance**, which
   has no payment code yet: paid activation is built as a state machine that only a verified, signed confirmation
   from Dot.Finance can complete. Until Finance can send one, no paid plan can be activated and nothing simulates
   a payment.
7. No business milestone ever gates a platform.

## 3. Sources of truth

| Concern | Where it lives | Notes |
|---|---|---|
| Registration (technical) | `config/ecosystem.php` | key, address, sign-in mode, secrets. The key is the stable identifier everywhere. |
| Catalogue (what it is) | table `platform_catalogue` | one row per key: name, tagline, description, benefit, who it is for, capabilities, categories, connections, discoverable. Seeded by `platforms:sync`; edited by platform administrators. A test fails if a visible key has no row or a row has no key. |
| Operational status | `platform_catalogue.operational_status` | operational, degraded, maintenance, unavailable, development, not_launched. Set by administrators; shown to businesses only as "temporarily unavailable" or "not launched". |
| Commercial configuration | tables `platform_plans`, `platform_bundles`, `platform_bundle_items` | prices are nullable; a plan with no approved price cannot be sold. |
| Business entitlement | table `platform_entitlements` | one row per company and platform. The only thing that says a company may use a platform. |
| User permission | `platform_entitlements.access_mode` + table `platform_user_access` | everyone in the company, or selected people. Owners and admins always. |
| History | tables `platform_entitlement_events`, `platform_catalogue_events` | append-only. |
| Sync to platforms | table `entitlement_outbox` | same shape and retry rules as the existing outboxes. |

The OIDC registry (`dot_platforms`) keeps its own job; its `slug` must equal a catalogue key, and a test pins that.

## 4. Data model

`platform_catalogue`: id, key (unique), name, tagline, description, benefit, audience, capabilities (json list),
categories (json list of category keys), connects_with (json list of keys), operational_status, discoverable
(bool), is_core (bool), sort, timestamps.

`platform_plans`: id, platform_key, slug, name, model (free, included, subscription, per_user, usage, enterprise),
interval (month, year, null), amount_minor (nullable), currency (nullable), included_users (nullable),
extra_user_amount_minor (nullable), trial_days (nullable), limits (json), features (json), tax_treatment
(nullable), approved_at (nullable), active (bool), timestamps. Unique (platform_key, slug). A plan is sellable
only when `active`, `approved_at` is set and `amount_minor` and `currency` are set.

`platform_bundles`: id, slug, name, description, amount_minor (nullable), currency (nullable), interval,
approved_at (nullable), active. `platform_bundle_items`: bundle_id, platform_key, plan_slug (nullable).

`platform_entitlements`: id, team_id, platform_key, status, source, plan_id (nullable), bundle_id (nullable),
access_mode (everyone, selected), started_at, trial_ends_at, current_period_ends_at, grace_until, suspended_at,
cancelled_at, ended_at, reference (nullable: the payment service's subscription reference), granted_by (nullable),
note (nullable), timestamps. Unique (team_id, platform_key).

- `status`: pending, active, trial, payment_required, expired, suspended, cancelled.
- `source`: core, free, unpriced_free, included, complimentary, trial, paid, enterprise.

`platform_user_access`: id, team_id, platform_key, user_id, platform_role (nullable), granted_by, timestamps.
Unique (team_id, platform_key, user_id).

`platform_entitlement_events`: id, team_id, platform_key, actor_id (nullable), type, from_status, to_status,
details (json), created_at.

`platform_catalogue_events`: id, actor_id, subject_type (platform, plan, bundle), subject_key, type, before (json),
after (json), created_at.

`platform_recommendation_dismissals`: id, team_id, platform_key, user_id, created_at. Unique (team_id, platform_key).

`entitlement_outbox`: event_id (unique), platform_key, team_uuid, payload (json), attempts, next_attempt_at,
sent_at, failed_at, last_error.

`users.is_platform_admin` (bool, default false).

Rules of the house: `users.id` is INT UNSIGNED, so user keys are `unsignedInteger` with an explicit `foreign()`;
migrations are guarded and reversible; CI runs `migrate:fresh` on MySQL.

## 5. The access decision

One class, `App\Services\Platforms\PlatformAccess`, answers for (person, company, platform key):

1. Is the person signed in, verified and not suspended?
2. Is the platform registered, discoverable and not internal?
3. Which company is acting (the person's current company)? Are they a member?
4. Personal workspace: the free core only.
5. Does the company hold an entitlement whose status grants access (active; trial before its end; within grace)?
   Core platforms are always granted.
6. If the entitlement's `access_mode` is `selected`: is the person an owner or admin, or listed in
   `platform_user_access`?
7. Is the platform operational (not unavailable, maintenance, development or not launched)?

It returns allowed, or one reason: `not_signed_in`, `not_a_member`, `not_activated`, `no_personal_access`,
`payment_required`, `trial_ended`, `expired`, `suspended`, `cancelled`, `not_permitted_for_you`,
`temporarily_unavailable`, `not_launched`. Each reason has its own plain-language page with the right next step
(activate, ask your owner, go to Dot.Finance, try later). A refusal is never the 503 "unavailable" page.

It is enforced at every door, server-side:

- `EcosystemHandoff::launchForm` and `launchUrl` (the single choke point behind `/go/{platform}`, the switcher, the
  Platforms page, Home, search, `/open/{resource}`).
- `POST /api/ecosystem/redeem`: refuses a code whose company is not entitled, and the answer marks each company
  with `entitled` for that platform so the platform syncs only entitled companies.
- `/oauth/authorize`: refuses a client whose platform the person's company is not entitled to.
- A signed read for platforms, `GET /api/ecosystem/companies/{uuid}/platforms` (bearer: the caller's platform
  secret), so a platform can check on its own login path and Dot.Analytics can show "Your business platforms".
- Platforms with their own sign-in (Charts, Mines) cannot be enforced by InfoDot. They are listed, opened by link,
  and marked as "managed in the platform" until they adopt InfoDot sign-in.

Failure rule: entitlement records live in InfoDot, so an outage of Dot.Finance never locks anyone out. A payment
that fails moves the entitlement to `payment_required` with a grace period; it does not delete anything.

## 6. Lifecycle

| Event | Who | Result |
|---|---|---|
| Company created | system | core entitlements exist implicitly (no rows needed) |
| Activate a free or unpriced platform | owner or admin | `active`, source `free` or `unpriced_free`, at once |
| Start a trial | owner or admin, if the plan has `trial_days` | `trial` until `trial_ends_at`, then `payment_required` if a paid plan was chosen, else `expired` |
| Subscribe to a paid plan | owner | `pending`; becomes `active` only when Dot.Finance posts a signed confirmation to `POST /api/finance/platform-subscription-events` (same signature scheme and idempotency as the ticket events) |
| Payment failed or reversed | Dot.Finance (signed) | `payment_required`, grace set; after grace, `expired` |
| Renewal confirmed | Dot.Finance (signed) | period extended, `active` |
| Upgrade or downgrade | owner | plan changed on confirmation; history kept |
| Cancel | owner | `cancelled`, access to the end of the paid period; data kept |
| Grant complimentary access | platform administrator | `active`, source `complimentary`, optional end date, audited |
| Suspend or restore | platform administrator | `suspended` and back, audited |
| Deactivate a free platform | owner | `cancelled`; can be reactivated at once |

Every change writes an event, queues an outbox message to the platform (`entitlement.changed`, signed, retried,
idempotent by event id) and notifies the company's owner and admins in-app where it matters. Nothing destroys
data: after cancellation or expiry a platform should give read-only access and export for a retention period
that each platform documents; InfoDot's part is to keep sending the entitlement state and never to send a
delete.

## 7. Catalogue and discovery

Categories (a platform may sit in several): Business Management; Finance and Administration; Productivity and
Collaboration; Sales and Commerce; Marketing and Design; Data and Intelligence; Human Resources; Learning and
Development; Operations and Industry; Automation and AI.

Access states shown to a business, each derived from records: Included (core), Active, Trial (with end date),
Activation pending, Free to activate, Free while pricing is being finalised, Available for purchase, Trial
available, Payment required, Subscription expired, Suspended, Temporarily unavailable, Not launched, Managed in
the platform, Recommended (a flag beside any of the non-active states).

The Platforms page: search by name or by need, category filter, a Recommended section, the company's own
platforms first, a detail page per platform (what it does, who it is for, what it solves, capabilities,
connections, plans and prices or "pricing being finalised", what activating involves), and one next action per
platform. Browsing works with recommendations switched off.

## 8. Recommendations

Rules first, in `config/platform_recommendations.php`: each rule names signals and a reason sentence. Signals
come only from data InfoDot already has: `teams.industry`, the business profile (type, categories, services,
size, regions), number of members, platforms already active, and the company's own activity counts in InfoDot.
Each recommendation shows its reason ("You listed 12 people: Dot.HR keeps their records and leave in one place").
A company can dismiss one; it is not shown again. No sequence, no milestones, no invented insight. Dot.Analytics
may add recommendations later from the aggregates it really holds, with the same explainability rule.

## 9. Administration

`/admin/platforms` for platform administrators only: catalogue entries and operational status; plans and prices
(with an explicit "approve price" step, never a default); bundles; each company's entitlements; grant, suspend
and restore; the audit trails; a revenue view that shows only what Dot.Finance has confirmed (empty until then).
Infrastructure health (which platforms answer) lives here, not on a business's Analytics page.

## 10. Other platforms

- Each platform reads `entitled` from the redeem answer and the platforms read endpoint, and stops carrying its
  own copy of the platform list (or fetches it).
- Dot.Analytics: remove discontinued platforms from its configuration and engine; show "Your business platforms"
  from the read endpoint; keep health in its staff-only operate area.
- Platforms with their own plans (Mines, Agents, Tutor's course access, Analytics' dormant plans) keep working;
  reconciling them with the central catalogue is a per-platform decision recorded when that platform is done.
- Landing experiences: see `LANDING_BRIEF.md`.

## 11. Payments (blocked on Dot.Finance)

Dot.Finance needs: plans and subscriptions per platform, checkout with the payment provider, verified webhooks,
invoices with VAT, renewals and failed-payment handling, refunds, the seats page, and signed confirmations to
InfoDot. Most of this exists, tested, in the retired payments app and can be moved; per-platform plans, automatic
renewal charging and subscription refunds exist nowhere. It can all be built against the provider's test mode.
Real money needs the owner: a live provider account and keys, bank verification, VAT registration details,
approved prices, and the proration, grace and refund policy.

## 12. Build order

1. InfoDot core: data model, `PlatformAccess`, enforcement at every door, refusal pages, platform administrator
   role, events and outbox, the read endpoint.
2. InfoDot catalogue: Platforms page and detail pages, activation, people access, recommendations, the Analytics
   page fix, switcher and navigation showing only what the company has.
3. InfoDot administration and commercial state machine: admin centre, plans, bundles, trials, complimentary,
   suspension, the signed confirmation endpoint.
4. Dot.Analytics clean-up and "Your business platforms".
5. Landing experiences, platform by platform.
6. Each platform honours `entitled`.
7. Payments in Dot.Finance.
8. The twenty scenarios, then merge and deploy.
