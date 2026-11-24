# R-59 One Million Businesses: audit, gaps and phased plan

Date: 2026-11-24. Status: audit and plan only. Nothing in this document has been built. The owner's brief is stored verbatim in `R59_ONE_MILLION_BUSINESSES_BRIEF.md`.

Method: three read-only code audits of this repository (billing and entitlements; referrals, points and fraud controls; businesses, directory, onboarding, admin and analytics). They read configuration, migrations, models, services, routes and the existing programme documents. They did not read the production `.env`, the production database or any other Dot repository, so anything that depends on production state or on another repository is marked **unverified**.

## 1. The short version

1. InfoDot already holds most of the licensing and entitlement engine the R100 model needs (seats, licence batches, grace periods, volume bands, a central access rule, a signed payment webhook, admin controls). All of it is dormant: no price is set and the Finance connection flag is off.
2. InfoDot never takes money. Checkout, invoices and renewal charging are meant to live in Dot.Finance, and nothing in this repository proves they do. This is the largest unknown and it gates every revenue figure.
3. Almost everything the Growth Centre needs to show about acquisition does not exist as data: there is no registration source, channel, referrer, campaign or partner attribution anywhere, and no onboarding step events.
4. There is no referral, partner, sponsor or campaign model of any kind. Team invitations (a colleague joining a company) are the only invite system.
5. Points live in an external ledger (Dot.Dopemine) that InfoDot reaches through a retried, signed outbox. Reward values are not in InfoDot, and whether the ledger accepts referral event types is unverified.
6. Several brief metrics can be counted honestly today from existing tables, several need new capture, and a few cannot be shown truthfully until later (MRR, acquisition cost, retention). The Growth Centre must say "not yet measured" for those rather than show a number.

## 2. What exists and can be reused

| Area | What exists | Where | State |
|---|---|---|---|
| Seat licensing | Seat ledger, free allowance, licence batches (monthly or yearly, grace, quantity changes), per-user assignment, volume bands, reminders, sweep job | `config/licensing.php`, `app/Services/Licensing/*`, tables `team_licences`, `licence_batches`, `licence_assignments`, `team_licence_events` | Built and tested; dormant (no price, `LICENCE_FINANCE_CONNECTED=false`) |
| Central access | One rule decides which company and user may open which platform; admin centre for trials, complimentary grants, suspension; outbox that pushes changes to platforms; read endpoint for platforms | `app/Services/Platforms/PlatformAccess.php`, `routes/admin.php`, `platform_entitlements`, `entitlement_outbox` | Live. Enforcement inside each platform is **unverified**; platforms with their own sign-in are not enforced from InfoDot |
| Money in | Signed, idempotent webhook for payment and licence events | `PlatformSubscriptionEventsController`, `platform_payment_confirmations` | Built; refuses everything until its secret is set |
| Money out | Checkout call to Finance, entitlement read from Finance | `FinanceCheckout`, `FinanceEntitlements` | InfoDot side built; Finance side **unverified** |
| Points | Outbox to the Dopemine ledger, signed requests, retry, reversal event, redemption with idempotency | `app/Services/Points/*`, `reward_outbox`, `ForwardKnowledgeEventToPoints` | Live for knowledge events only |
| Business model | Team (company) with uuid and owner code, profile, categories (max 3), services, locations, verification (append-only events) | `teams`, `team_profiles`, `business_categories`, `business_locations`, `business_verifications` | Live |
| Directory and SEO | Search, browse, filters, public pages, sitemap (up to 5,000 businesses), canonical and meta tags | `BusinessDirectory`, `SitemapController`, `/businesses`, `/business/{uuid}` | Live; no JSON-LD on business pages, no slug URLs, no category or location landing pages |
| Admin shell | Platform admin gate, throttled admin routes, tabbed admin pages, `ix-*` components, audit events with required reasons | `routes/admin.php`, `app/Http/Controllers/Admin/Platforms/*`, `resources/views/admin/platforms` | Live; no admin dashboard with charts exists |
| Charts | ApexCharts, loaded only on the Analytics page | `resources/js/analytics-chart.js` | Live (company-scoped only) |
| Daily-count pattern | Privacy-light daily counters | `business_stats_daily`, `ux_daily_counts` | Live; UX signals off by default, consent basis unresolved |
| Fraud and abuse | Email verification, throttles, reserved-domain block, suspension, advisory collusion detector, append-only audit tables | `DeliverableEmailDomain`, `RingDetector`, `AccountSuspension` | Live; no disposable-email check, no IP or device checks, nothing for referral self-dealing |
| Scheduling | Many `withoutOverlapping` scheduled commands; queue is `sync` and the host has no worker | `app/Console/Kernel.php` | Live: new work must be scheduled commands, not queued jobs |
| Roles | Jetstream roles (owner, admin, manager, member), `TeamPolicy`, global `is_platform_admin` and `is_moderator` | `JetstreamServiceProvider`, `app/Policies/TeamPolicy.php` | Live |

## 3. What can be counted honestly today, and what cannot

| Brief metric | Today |
|---|---|
| Registered users, registered businesses (non-personal teams), by month and year | Countable (`users`, `teams`) |
| Verified businesses | Countable (`business_verifications`, verified and unexpired) |
| Listed businesses; by category, industry, business type | Countable |
| Geography | Partial: only where the business entered a country, province or city (optional, free text in places) |
| Platform adoption | Countable (`platform_entitlements`, `sso_platform_sessions`). The "23 of 23" figure on the Analytics page is hard-coded and must not be reused |
| Paying businesses and users | Only after real Finance confirmations exist (`platform_payment_confirmations`); none can be assumed |
| MRR and ARR | Not yet: no approved price ships, and confirmations hold event amounts, not a recurring snapshot |
| Active businesses | Not defined anywhere; a definition is needed (see decisions) |
| Acquisition channel, referral, partner, campaign, cost per channel, abandonment, funnel by source | Not captured at all |
| Retention and churn | Needs a defined "active" and history; not yet |
| Country from visitor location | Not captured; would be a POPIA decision |

## 4. Gaps against the brief

- **Acquisition capture:** no source, channel, referrer, UTM, landing page or campaign stored at registration; `/login` and `/register` are disallowed in `robots.txt` and the page canonical strips query strings.
- **Onboarding:** one step only (name, industry, description, visibility), shown only when a platform sign-in is pending. No objectives, size or stage capture, no recommendation assistant, and no step events (so no abandonment).
- **Referrals:** no codes, links, click or visit table, attribution capture, state machine, reward configuration, referral event types, reversal trigger from refund or cancellation, or referral-specific fraud rules.
- **Partners and sponsors:** no model at all: no tenant type, no portal, no bulk onboarding, no consent records for introduced businesses, no sponsored-seat concept (nearest: `complimentary` and `enterprise` entitlement sources, which work per platform and company, not per seat).
- **Pricing:** no price set; bands currently discount at 90 and 80 percent, so a flat R100 needs flat bands; the free-seat rule (R-53 cutover to one free seat) must be reconciled with "R100 per registered user".
- **Billing completeness:** no seat invoices or credit notes in InfoDot, no billing country or customer VAT number on teams, single configured currency, no price book per currency, no refund initiation or proration, cancel and reduce switched off until Finance has a cancel endpoint, usage limits declared (`platform_plans.limits`) but read by no code.
- **Public network:** business pages lack structured data and per-business social images; categories and locations have no indexable landing pages; "collaboration opportunities" and "advertise services" have no model.
- **Intelligence:** no admin dashboard, no snapshots, no attribution, no targets table.

## 5. Dependencies and unknowns that gate the plan

1. **Dot.Finance (unverified, blocking):** does it deliver hosted checkout, the signed webhook, seat invoices with VAT, renewal charging, and a cancel endpoint? The R-50 notes recorded in November that it had none of these. Until confirmed, R100 cannot be charged and no revenue number can be shown. First action: a read-only audit of the Dot.Finance repository.
2. **Dot.Dopemine (unverified):** does the ledger accept `infodot.referral.*` events, and where are reward values configured? The local checkout looks like a different, older app. First action: read-only audit of the deployed ledger code, or an agreed contract with the owner.
3. **Production state (unverified):** which licensing flags and secrets are set in production, whether `LICENCE_INCLUDED_SEATS=1` was applied, and the production database engine.
4. **Platform enforcement (unverified):** which platforms actually call the entitlement read or accept the push.
5. **Queue:** no worker on the host, so every new background process must be an idempotent scheduled command with `withoutOverlapping`.

## 6. Decisions needed from the owner

| # | Decision | Recommendation |
|---|---|---|
| D1 | Pricing rule: does the R100 apply to every user, or to every user beyond one free seat (the R-53 plan)? Flat R100 or keep volume bands? Annual price? | Keep one free seat for the owner, R100 per additional paid seat, flat (no bands) at launch, annual price decided later. Needs your approval before any price is set |
| D2 | Where billing runs: complete it in Dot.Finance (as designed) or add a payment client to InfoDot? | Keep it in Finance; audit Finance first |
| D3 | Who is credited for a business reward, given points are per person | The business owner's account; reward is for the business, paid once per business |
| D4 | Definitions. **Verified referral:** referred business owner has verified email and a completed business profile. **Activated:** verified, plus a platform opened, plus a second signal inside 30 days (colleague invited, first question or solution, or a platform switched on). **Active business:** an owner or member signed in to InfoDot or opened a platform in the last 30 days | Adopt these, make the windows configurable |
| D5 | Partner model: a new `kind` on Team, or a separate `partners` table with a user pivot | Separate `partners` table: keeps partners out of the business directory and sitemap and makes isolation simpler |
| D6 | Source capture and POPIA: first-party UTM and referral code in a cookie with a notice, no IP geolocation in Phase 1 | Adopt; country comes from business-entered data |
| D7 | Referral rewards: confirm the first tier table (5, 20, 100, 500 referrals for 100, 500, 3,000, 20,000 points) as configurable starting values, with a holding period (for example 30 days) before issue | Adopt as starting values; rewards for businesses acquired, never for recruiting recruiters; no multi-level payouts |
| D8 | Redeeming points for subscription credits: needs a credit mechanism in Finance | Defer to Phase 2 after Finance is confirmed |
| D9 | Public One Million Businesses page: shows only real counts. Until there is something worth showing, the progress bar is hidden | Adopt |

## 7. Phased plan

Every phase ships behind an admin-only or configuration flag until its acceptance criteria pass, with tests that run on SQLite and PostgreSQL, and no mock data.

### Phase 0: pre-work (read-only, about two days)
- Audit Dot.Finance and the deployed Dopemine ledger against the two unknowns above.
- Write the metric definitions (D4) into the Growth Centre spec.
- Acceptance: a written verdict on each unknown; D1 to D9 answered.

### Phase 1: Growth foundation (increments)
- **1A. Acquisition capture.** A first-party, signed cookie records `ref` code, UTM fields, landing path and first referrer on public pages; registration saves one `acquisition_touches` row per user. No IP stored. Consent notice on the cookie. Acceptance: a registration from `?ref=CODE&utm_source=x` is attributed correctly; tampered or expired values are ignored; no personal data beyond the cookie value; tests for each.
- **1B. Onboarding events.** `onboarding_events` (user, team, step, completed_at), the eight-step flow from the brief with objectives, size and stage captured on the profile, and a recommender that suggests three to five platforms from the platform catalogue and the business's answers (no machine learning, explainable rules). Acceptance: every step emits one event once; abandonment per step is queryable; recommendations are deterministic and tested.
- **1C. Referral infrastructure.** `referral_codes` (one per eligible business), `referrals` (state: registered, verified, activated, subscribed, reversed), attribution rules (first valid touch, 30-day window, no self-referral, same-owner and same-email-domain blocks, velocity caps, holding period), `referral_rewards` and an admin-editable tier table. Rewards go out as signed events through the existing outbox once the ledger contract (Phase 0) is confirmed; reversal on refund or cancellation inside the holding period. All reward writes in database transactions and idempotent. Acceptance: a full registered-to-activated-to-rewarded path, a self-referral rejected, a duplicate rejected, a reward reversed, and reward issue never repeated by a retry.
- **1D. Growth Centre (admin).** Read model over real tables plus a daily snapshot table written by a scheduled command; tabs for Overview, Funnel, Channels, Geography, Platforms, Targets. Filters from the brief. Targets live in their own table and are shown beside actuals, never mixed with them; projections are labelled and calculated only from actuals. Metrics with no data show "not yet measured". Reuse the admin shell, `ix-*` components and ApexCharts (loaded only on this page). Acceptance: every figure traces to a query; a test fails if a figure is hard-coded; filters change the numbers; drill-down opens real records; keyboard and screen reader checks pass.
- **1E. R100 seats.** Set the price in configuration only after D1 is approved; flatten bands; add billing country and customer VAT number to teams; keep checkout behind `LICENCE_FINANCE_CONNECTED` until Finance is confirmed. Acceptance: a team with five paid seats is quoted R500 a month, with VAT shown separately, in a test; nothing charges anything in production until the owner flips the flag.

### Phase 2: Distribution
Partner portal (separate `partners` table, users pivot, own gate and route file), applications and approval workflow, agreements, referral links per partner, bulk invitation by CSV with consent records and rate limits, a client directory showing only what the business has consented to share, campaign management (campaigns, codes, budgets, targets, landing page), institutional sponsorship (sponsor, beneficiary, seat pool, expiry, budget, with a seat source of sponsored versus paid versus free). Depends on Phase 1 attribution and the Finance sponsor-billing contract.

### Phase 3: Business network
Public business pages with structured data and slug URLs, discovery categories and location pages, supplier and service-provider search, enquiries (existing table), collaboration opportunities (new, moderated, no social mechanics). Reuse directory, verification and reporting.

### Phase 4: Organic acquisition
Guides mapped to real questions and to the relevant Dot platform, internal linking, structured data, canonical and indexing rules, per-platform landing pages, acquisition measured through Phase 1A. Content is reviewed and sourced, not generated in bulk (existing R-14 pattern).

### Phase 5: International expansion
Country field normalised on businesses, price book per currency, per-country tax rules, local payment providers through Finance, language scaffolding, data residency review. Starts only after Finance, Dopemine and Phase 1 are proven.

### Phase 6: Growth intelligence
Attribution reports, cohort retention and churn (needs the active definition and months of history), cancellation reasons capture, revenue analytics once real confirmations exist, evidence-based recommendations with the underlying numbers shown, forecasts labelled as projections.

## 8. Cross-cutting rules for every phase
- Tenant isolation: partners and sponsors never read a business's private data; introduced-business visibility is limited to consented fields and tested.
- Audit: every money, reward, referral and configuration change writes an append-only event with a reason.
- Idempotency: scheduled commands use `withoutOverlapping`, keyed writes and unique constraints.
- Privacy by design: no IP storage in Phase 1, consent notice for the attribution cookie, retention periods written down, POPIA inventory updated.
- Rate limits and validation on every public endpoint; bot protection on referral landing pages.
- No hard-coded or demonstration figures anywhere; tests assert it.
- Design: existing Yard identity, accessible, responsive; the Awwwards and impeccable skills guide the public page and the Growth Centre.

## 9. Risks
- Finance not ready: revenue, paying-user counts and refund-driven reversals cannot be shown or relied on.
- Reward design: any reward that pays for recruiting recruiters is out of scope; programme terms need legal review (South African consumer and advertising rules) before launch.
- Scale: one million businesses implies pagination, indexes and daily snapshots from the start; the sitemap cap of 5,000 businesses must become a sitemap index.
- Data quality: free-text location and size must be normalised before geography and size filters are trustworthy.
- Production state unknown: flags and secrets must be confirmed before anything is switched on.

## 10. Order of work after approval
Phase 0, then 1A, 1B and 1D in parallel (independent), then 1C (needs 1A and the ledger contract), then 1E (needs D1 and Finance). Phase 2 begins only when Phase 1's acceptance criteria are met.
