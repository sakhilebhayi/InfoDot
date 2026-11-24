# InfoDot monetisation audit (read-only)

Evidence base: /Users/sakhilebhayi/Dot/InfoDot, branch `main`, equal to origin/main (HEAD e31d3c0, PR #256). Paths repo-relative. Only `.env.example` and config read; no `.env`. Note: working tree has uncommitted `.mcp.json` and an untracked `.claude/skills/perch/` (not relevant).

Legend: EXISTING (verified), PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- The hub and identity provider of the Dot ecosystem: single sign-in (OIDC provider), company ("team") workspaces, SSO handoff into every platform (`app/Services/Sso/*`, `app/Http/Controllers/Api/EcosystemRedeemController`). EXISTING.
- Knowledge network: questions, solutions, steps, answers, tried-it validation, reputation, badges, Dot Points rewards (`app/Services/Knowledge`, `Reputation`, `Points`, `config/knowledge.php`). EXISTING.
- Business directory / profiles / enquiries, verification, collections (`app/Services/Directory`, `Enquiries`, `Verification`, `config/enquiries.php`). EXISTING.
- Seminars: free and paid live seminars with host payouts, platform commission, optional video (LiveKit) and peer-to-peer relay (`app/Services/Seminars`, `config/seminars.php`). EXISTING (paid disabled by default, `SEMINARS_PAID_ENABLED=false`).
- Entitlement manager: platform catalogue, per-company entitlements, plans, bundles, per-user access, licensing seats, and the read endpoint other platforms call (`app/Services/Platforms/*`, `app/Services/Licensing/*`). EXISTING.

## 2. Most valuable features (measurable)
- Company licences (seats): the only fully built paid mechanism; each licence is a counted, assignable, lapsing unit (`licence_batches`, `licence_assignments`, `SeatLedger`). EXISTING.
- Platform entitlements: turn each Dot platform on per company, with trial/grace/suspend/complimentary states and an audit trail (`platform_entitlements`, `platform_entitlement_events`). EXISTING.
- Paid seminars with commission (measurable GMV and take: `seminar_ticket_sales`, `seminar_commission_invoices`). EXISTING (flagged off).
- Verified business profile and directory discovery (trust, enquiries counted per day). EXISTING.
- Dot Points ledger (Dot.Dopemine) with redemption for credits/capacity: an incentive currency (see internal.md). EXISTING.

## 3. Existing licensing / entitlement / fee code (the core of this audit)

### 3a. Licensing seat engine
- Tables: `team_licences` (included_seats nullable, purchased_seats, payment_status free|active|past_due|cancelled, grace_until, payment_ref, grandfathered_until), `team_licence_events`, `licence_batches` (uuid, quantity, interval month|year, unit_price_minor, total_minor, currency, status pending|active|grace|ended|refunded, period_start/end, grace_until, quantity_next, payment_ref, price_snapshot), `licence_assignments` (team_id, user_id, batch_id, state active|reserved|lapsed|released), `licence_notices_sent` (`2026_10_29_100001`, `2026_11_16_100000/110000/120000`). EXISTING.
- `app/Services/Licensing/SeatLedger.php`: seats = free allowance (config `licensing.included_seats`, default 3) + purchased seats; owner holds a seat; live invitations reserve a seat; `guard()` locks the team row and refuses with `SeatLimitReached` when capacity is exceeded. Personal workspaces are exempt (`applies()`). EXISTING.
- R-53 direction: `licensing.target_included_seats` = 1 and grandfathering window `licensing.grandfather_days` 90 (`Grandfathering.php`, `GrandfatherLicences` command). `PlatformAccess::decideFor` already refuses `NOT_LICENSED` for a non-licensed member of a company team. EXISTING in code; policy awaiting owner (memory R-53 note says investigate and present only).
- `LicencePricing.php`: quote = unit price from config (`LICENCE_SEAT_PRICE_MINOR`, `LICENCE_SEAT_PRICE_ANNUAL_MINOR`, `LICENCE_CURRENCY`), optional volume bands, VAT; refuses with "no price has been set" when unset; max 100 people then "custom agreement". Prices are NOT set in code or config defaults (all env, null). EXISTING; no price invented.
- Lifecycle: `LicenceLifecycle`, `LicenceBatches`, `LicenceReminders`, `SweepLicences`, `SendLicenceReminders`; Finance connection flags `LICENCE_FINANCE_CONNECTED` (default false) and `can_buy` in the summary. Checkout/cancel go to Dot.Finance (`FinanceCheckout.php`, `FinanceEntitlements.php`, `FinanceCompanies.php`; `SyncCompaniesToFinance`). Payment confirmations come back via `app/Http/Controllers/Api/PlatformSubscriptionEventsController.php` and `LicenceBatchEvents.php`. EXISTING.

### 3b. Platform entitlements and plans
- Tables (`2026_11_14_100000_create_platform_catalogue_and_entitlements.php`): `platform_catalogue` (key, status, discoverable, is_core), `platform_plans` (model free|included|subscription|per_user|usage|enterprise, interval, amount_minor, currency, included_users, extra_user_amount_minor, trial_days, `limits` JSON, `features` JSON, tax_treatment, approved_at, active), `platform_bundles` + `platform_bundle_items`, `platform_entitlements` (team_id, platform_key, status pending|active|trial|payment_required|expired|suspended|cancelled; source core|free|unpriced_free|included|complimentary|trial|paid|enterprise; plan_id, bundle_id, access_mode everyone|selected, trial/period/grace dates, reference), `platform_user_access` (per-user within a company), `platform_entitlement_events`, `platform_payment_confirmations`. EXISTING.
- `app/Services/Platforms/Entitlements.php`: activate (free/unpriced only; a priced platform is refused with "paid plans cannot be started yet"), deactivate, grantComplimentary, suspend, restore, setAccessMode, grantUser/revokeUser, startTrial, subscribe, cancel, `applyPaymentEvent`, `runLifecycle`. EXISTING.
- IMPORTANT FINDING: `platform_plans.limits` is stored and cast to array (`app/Models/PlatformPlan.php`) but is NEVER read by any code (grep for `limits` outside rate limiters returns only the cast). Plan "usage" model exists only as an allowed value (`CatalogueAdmin::MODELS`). So InfoDot can describe a quantity limit but cannot enforce or read one. EXISTING absence; NEEDS DEVELOPMENT.
- `PlatformAccess::decideFor` (`app/Services/Platforms/PlatformAccess.php`): decision order is not signed in, not launched, not a member, not licensed, personal workspace (core only), not activated, entitlement refusal, per-user access if mode `selected`, platform operational status. Core platforms are free for everyone. EXISTING.
- How other platforms read entitlements (EXISTING): `GET /api/ecosystem/companies/{uuid}/platforms` (`routes/api.php`, `app/Http/Controllers/Api/CompanyPlatformsController.php`), authenticated by per-platform service secret; returns per platform only `key, name, status, entitled (bool), is_core` and the caller's own `access_mode`. It returns NO quantities, limits, credits or balances. Consumers: an `EnsureCompanyEntitled` middleware + `EntitlementClient` with a 10-minute cache and fail-open on outage, present in Auction, Emall, Ehail, Central (verified on origin/main of each). Also `EntitlementOutbox` pushes entitlement change events (`FlushEntitlementOutbox` command) and a resource registry API (`POST /api/ecosystem/resources`).
- Per-platform landing and branding records (`platform_branding`, `PlatformBranding`) support the "branded login per platform" work (R-54). EXISTING.

### 3c. Seminar fees and commission
- `config/seminars.php` `paid`: `platform_percent` 10 (env `SEMINARS_PLATFORM_PERCENT`; VAT-inclusive via `commission_includes_vat`), `min_price_cents` 500, `max_price_cents` 5,000,000, currency ZAR, refund window 24 h, `SEMINARS_PAID_ENABLED` false, host agreement versioned and not approved by default (`agreement_approved` false). EXISTING.
- Money moves via Dot.Finance marketplace (`app/Services/Seminars/FinanceMarketplace.php` calls `/api/seminar-tickets`, `/api/payout-accounts`, refunds; Finance holds Paystack). Commission invoices monthly (`CommissionInvoices.php`, command `IssueSeminarCommissionInvoices`), host debt ledger (`HostDebtLedger.php`, `seminar_host_debts`), payout accounts (`seminar_payout_accounts`), sales ledger `seminar_ticket_sales` (amount_cents, refunded_cents, state). EXISTING.
- Host limits: max 5 upcoming seminars per host (`seminars.max_upcoming_per_host`), capacity max 5000, duration 15 to 480 min, video participants 100, P2P viewers 200. EXISTING (quality/abuse caps).
- Free seminars never charged. Host Dot Points awarded when thresholds met (`SeminarHostPoints`, `points.*`). EXISTING.

### 3d. Other limits (abuse brakes, not allowances)
- Attachments: 10 per item, per-person daily 40 files / 300 MB, image 10 MB, PDF 20 MB, video 100 MB (`config/attachments.php`). Enquiries 10 per person per day, 2 per business per day (`config/enquiries.php`). Referral daily cap per code 25 (`config/referrals.php`). EXISTING.

## 4. Resources consumed or stored and where a count can be read today
| Resource | Where |
|---|---|
| Seats used / capacity per company | `SeatLedger::summary()` (reads `team_user`, `team_invitations`, `licence_batches`, `team_licences`) |
| Licence batches, paid period, renewals | `licence_batches`, `licence_assignments`, `team_licence_events` |
| Platforms switched on per company | `platform_entitlements` |
| Entitlement changes (audit) | `platform_entitlement_events` |
| Per-user platform access | `platform_user_access` |
| Seminar attendance, tickets, refunds, commission | `seminar_registrations`, `seminar_ticket_sales`, `seminar_commission_invoices`, `seminar_host_debts` |
| P2P / LiveKit video minutes | not recorded as usage; capacity model only (`P2pCapacity.php`). NEEDS DEVELOPMENT |
| Attachment storage | `attachments` table (bytes per user derivable); daily brake only |
| Enquiries sent | `enquiries`, `enquiry_messages` |
| Emails / notices | `user_notices`, Laravel notifications; mail volume not metered |
| Dot Points | Dot.Dopemine `ledger_entries` (see internal.md) |
| Company key | `teams.uuid` (exposed to platforms), `teams.personal_team` |
No per-company usage meter exists for any non-seat resource. NEEDS DEVELOPMENT.

## 5. Third parties and cost drivers
- Payments: Paystack via Dot.Finance (InfoDot has no direct Paystack client; the string appears only in controllers/seminar text). EXISTING.
- LiveKit (video seminars; cost per participant-minute if hosted by vendor) and TURN/STUN for P2P (`config/seminars.php`); both off by default. EXISTING.
- Email (Mailgun/Postmark/SES configurable, `config/services.php`), Dot.Notify, Dopemine points API. EXISTING. SMS: none found. No maps, no AI model calls in app code (grep for anthropic/openai found none).
- Storage and image processing for attachments, profile and branding images (own `app/Services/Attachments`, `BrandingImages`). EXISTING.
- Search via Laravel Scout (`config/scout.php`, default driver env `SCOUT_DRIVER`, algolia unless overridden; production driver not verified from code). EXISTING config.

## 6. Candidate purchase units
- CAPACITY, already built: seats (licences). Direction in code is 1 free user plus paid licences (R-53). Keep the engine; the price remains an owner input. PROPOSED to keep per-user access (`platform_user_access`) as a free seat-allocation tool within the paid seat count.
- ENTITLEMENT per platform (PROPOSED): InfoDot should remain the entitlement and, later, the wallet front door, but the monetisation is the resource inside each platform, not access. Existing `source` values already include `free`, `unpriced_free`, `included` and `complimentary`, so a "platform on, resource allowance applies" state needs only a quantity layer.
- RESOURCE/CREDIT (PROPOSED): a company credit wallet (ZAR first) that platforms debit; plan `limits` JSON is the natural place to publish per-platform included quantities; `usage` plan model already allowed.
- TRANSACTION (PROPOSED): seminar commission (built, 10 percent, flagged off); possibly enquiry/lead credits for directory businesses (per business per day cap exists).
- FEATURE (PROPOSED): verification badge tiers, directory promotion, seminar video/P2P capacity per seminar (host buys capacity), larger attachments.
- Keep FREE (PROPOSED): identity and SSO, knowledge Q&A and solutions, reputation, Dot Points, personal workspace, core platforms, one free user per company, basic directory profile, free seminars, safety/privacy tools (data export, deletion).
- When an allocation runs out (PROPOSED): never block sign-in, existing content or data export; refuse only new consuming actions (new seat invite already works that way: `SeatLimitReached` keeps current people, `Grandfathering` freezes headcount). Lapsed licence keeps the account and own content (stated in `PlatformAccess` comment).

## 7. Cross-platform dependencies
- InfoDot is the dependency of every platform (identity, company uuid, entitlement). EXISTING.
- Dot.Finance is the payment and subscription bridge (licence checkout, seminar tickets, payment confirmations, `SyncCompaniesToFinance`). Billing is discontinued. EXISTING (Finance connection flag off by default).
- Dot.Dopemine (points), Dot.Memory (knowledge recommendations, participation), Dot.Notify, Dot.Analytics (platform branding/metrics), Dot.Brain. EXISTING.
- Shareable across the ecosystem (PROPOSED): wallet/credit balance and its ledger, seat licences (one licence unlocks the person on every platform), AI credits, email/notification credits, storage credits, payment rail, payout rail, Dot Points as a non-cash incentive. Platform-specific: listings, rides, bids, machine slots, agent tokens, seminar capacity.

## 8. Gaps
- Meter: no quantity layer. `platform_plans.limits` is unread; the entitlement API returns booleans only. Needs: a usage_events/credit ledger (idempotency key, signed reversals), per-company balances, a platform-reported usage endpoint (the resource registry endpoint `POST /api/ecosystem/resources` shows an existing signed-service pattern to copy). NEEDS DEVELOPMENT.
- Enforce: extend `CompanyPlatformsController` payload with allowances and remaining balance, or add `POST /api/ecosystem/usage/reserve` and `/commit`; each platform must gate server-side. All 4 audited platforms use a 10-minute cache and fail open, which is wrong for money: a balance check must be live or reserved. NEEDS DEVELOPMENT.
- Audit: reuse `platform_entitlement_events` and `team_licence_events` style append-only logs; Dopemine's ledger (idempotency key, lot_id, expiry) is the strongest in-house template. NEEDS DEVELOPMENT.
- Billing: Finance connection and a set price are required before anything can be sold (`LICENCE_FINANCE_CONNECTED=false`, price env null, `Entitlements::activate` refuses priced platforms: "paid plans cannot be started yet"). Owner decisions needed: price, VAT treatment (`licensing.vat.rate_percent` unset), seminar host agreement approval, grandfathering policy.
- Dependency note: the owner's R-50/R-53/R-54 notes show InfoDot as entitlement manager and R-53 as present-only; this audit does not change that.
