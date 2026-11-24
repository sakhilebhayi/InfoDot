# Dot.Ehail monetisation audit (read-only)

Evidence base: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Ehail (read-only extract in scratchpad `src/Dot.Ehail`). The local working tree is on stale branch `feat/reverb-realtime-core` with 13 migrations; origin/main has about 45 and the whole R-46 rebuild under `app/Domain/*`. Paths are repo-relative on origin/main. Only `.env.example` read.

Legend: EXISTING (verified), PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Ride-hailing: passenger requests a ride in a ride category, dispatch offers it to nearby drivers, ride state machine to completion (`app/Domain/Dispatch`, `app/Domain/Rides`, `ehail:dispatch-tick` every minute). EXISTING.
- Pricing engine: versioned rule sets, fare quotes with TTL, demand multiplier, zones/airport, promotions, cancellation and waiting rules (`app/Domain/Pricing`, tables `pricing_rule_sets`, `fare_quotes`, `ride_fares`, `promotions`). EXISTING.
- Money: card charge via Paystack (saved reusable authorisation), cash option, refunds, double-entry ledger, driver earnings, scheduled payouts, incentives, receipts (`docs/R46_MONEY.md`, `config/money.php`). EXISTING.
- Fleets (owner, vehicles, drivers, permits, maintenance), business accounts with policies, approvals, budgets and monthly statements, safety (trusted contacts, trip share, incidents), verification, support cases. EXISTING.
- Nobody can book until the owner supplies a routing key, payment, tariff and operator (owner memory note, `docs/R46_REPORT.md`). Routing default is `EHAIL_ROUTING_DRIVER=none`. EXISTING.

## 2. Most valuable features (measurable)
- Completed, paid rides: gross fare, commission, driver net, all journaled (`ledger_journals`, `ledger_entries`, `ride_fares`, `driver_earnings`). EXISTING.
- Business accounts (invoice or card billing, credit limit, per-traveller and per-department monthly spend counters): B2B revenue with a natural capacity unit (`app/Domain/Business`, `business_spend_counters`). EXISTING.
- Fleet management for taxi operators (vehicles, assignments, maintenance, fleet payouts): a natural seat/vehicle capacity unit. EXISTING (`fleet_*` tables, `config/ehail_fleet.php`).
- Incentive programmes and payouts that retain drivers (`incentive_programs`, `incentive_progress`, `payout_batches`, `payouts`). EXISTING.
- Safety and verification features are trust features and should not be gated. PROPOSED.

## 3. Existing limits, quotas, plans, entitlement, billing, fee code
- COMMISSION EXISTS, two layers:
  - Legacy/default `config/money.php` `commission.percent` env `EHAIL_COMMISSION_PERCENT`, DEFAULT 20 (percent). EXISTING.
  - Versioned `commission_plans` (percent in basis points `percent_bp`, `fixed_minor`, `min/max_commission_minor`, per country, currency, service area, ride category, fleet; `commissionable_codes`; draft/published/retired lineage) and the plan chosen is stored on `ride_fares.commission_plan_id` with the `earnings` split JSON (`2026_11_10_500000_create_pricing_and_catalog_tables.php`). EXISTING.
- Other platform fees: booking/service fees kept whole by the platform (`platform_revenue_fees`), cancellation fee with driver share default 80 (`money.cancellation.driver_share_percent`), waiting-time and cancel-free windows in pricing rule sets (`wait_free_minutes` 5, `cancel_free_minutes` 2). EXISTING (`docs/R46_MONEY.md` section 2-3, `config/ehail_pricing.php`).
- Passenger "wallet" is saved cards only (`payment_methods`, `app/Http/Controllers/Money/WalletController.php`); NOT a stored-value balance. A card verification charge of R1.00 is made and refunded (`money.card_setup.amount_minor`=100). EXISTING.
- Driver payouts: schedule day/time, minimum R50 (`payouts.minimum_minor`=5000), Paystack Transfers behind `EHAIL_PAYSTACK_TRANSFERS` (default false, manual payout otherwise), earnings hold hours (default 0). EXISTING.
- Business credit: `business_accounts.credit_limit`, terms days, grace days, status approved by an operator. EXISTING.
- Entitlement (access gate): `app/Http/Middleware/EnsureCompanyEntitled.php` + `app/Services/InfoDot/EntitlementClient.php` + `EHAIL_ENTITLEMENT_CHECK` (`config/ecosystem.php` line 77), plus `app/Support/EntitlementRefusal.php`. EXISTING.
- No subscriptions or plans for drivers, fleets or passengers; no seat or vehicle limits per plan. EXISTING absence. Rate limits: public estimate 20/min, quote 30/min (`config/ehail_pricing.php`); dispatch candidate limit 20 (`ehail_dispatch.php`).
- Payment-due guard: after retries are exhausted passenger is blocked from new rides (HTTP 402, `ehail.payment-clear`). EXISTING.

## 4. Resources consumed or stored and where countable today
| Resource | Where |
|---|---|
| Rides (requested, completed, cancelled) | `rides` + `ride_events` (state machine v2) |
| Fares, commission, driver net | `ride_fares`, `ledger_entries`, `ledger_journals`, `driver_earnings` |
| Card charges / refunds | `ride_payments`, `refunds`, `payment_webhook_events` |
| Payouts | `payouts`, `payout_batches`, `payout_accounts` |
| Quotes (routing/geocode calls behind them) | `fare_quotes`, `route_cache`, `ride_routes`, `trip_points`, `driver_locations` |
| Dispatch offers | `ride_offers` |
| Fleet vehicles / drivers | `vehicles`, `driver_profiles`, `fleet_members`, `fleet_vehicle_assignments` |
| Business seats and spend | `business_members`, `business_trips`, `business_charges`, `business_spend_counters` |
| Messages by channel | `notification_deliveries` (push, email, SMS), `notification_sms_numbers` |
| Documents stored | `driver_documents`, `verification_documents` |
| Audit trail | `audit_logs`, `idempotency_keys` |
| Daily metrics | `app/Services/Analytics/MetricsCollector.php` |
Company key: `teams.infodot_team_uuid`. EXISTING.
A per-API-call counter for routing/geocoding is NOT stored (only a cache and circuit breaker, `config/ehail-geo.php`). NEEDS DEVELOPMENT.

## 5. Third parties and cost drivers
- Maps/routing: pluggable (`EHAIL_ROUTING_DRIVER`: google Routes API, ORS, Mapbox, OSRM, Valhalla; default none). Geocoding default Nominatim (free, rate-limited policy), alternatives photon/ors/google/mapbox (`config/ehail-geo.php`). Routing per quote and per trip is the biggest variable cost once a paid provider is on. EXISTING.
- Paystack: card charges, refunds, transfers (payout), the R1 card setup; fees on every ride. EXISTING.
- SMS: BulkSMS driver, `EHAIL_SMS_DRIVER=null` by default, per-message cost. EXISTING.
- Web Push (VAPID, free), email via Dot.Notify and Laravel mail. EXISTING.
- Reverb realtime (self-hosted), driver location posting (`trip_points`/`driver_locations`) is a storage/write-load driver. EXISTING.

## 6. Candidate purchase units
- TRANSACTION (PROPOSED, built): commission per completed ride (percent_bp + fixed + min/max in `commission_plans`). Already the right shape; the open decision is the rate (default 20 percent is a placeholder in `config/money.php`, owner has given no input).
- CAPACITY (PROPOSED): fleet vehicles/active drivers under a fleet; business-account seats (members) and monthly credit limit; concurrent online drivers. These are quantities the code already counts.
- RESOURCE/CREDIT (PROPOSED): SMS credits (per-message real cost), routing/quote credits for businesses that run large scheduled batches, receipts/statement exports, document storage.
- FEATURE (PROPOSED): business policy engine, approvals and cost-centre reporting; fleet maintenance and analytics; priority dispatch/promotions.
- Free tier (PROPOSED): passenger booking, safety tools (trusted contacts, trip share, incidents), driver onboarding and verification, receipts, in-app/push notifications, basic business account with a small seat count, cash option.
- When an allocation runs out (PROPOSED): never cancel a ride in progress or block safety/SOS; block only NEW bookings on a business account that exceeds its seat/credit capacity (the code already refuses with `DispatchRefused('business_<code>')`), stop SMS and fall back to push/email, stop new quotes with a clear message.

## 7. Cross-platform dependencies
- InfoDot identity, SSO (`Http/Controllers/Auth/EcosystemAuthController.php`), entitlement, team link for business accounts. EXISTING.
- Dot.Notify (`app/Services/Notify/`, `EhailNotifyEvents.php`), Dot.Analytics, DKP/Guardian health. EXISTING.
- Dot.Finance: ledger here is internal double-entry; no export to Finance exists. NEEDS DEVELOPMENT.
- Shareable (PROPOSED): wallet balance and credits, SMS/email/notification credits, the Paystack rail, payout rail, storage. Platform-specific: ride commission, routing/quote credits, fleet vehicle slots, business seats.

## 8. Gaps
- Meter: rides and money are metered; SMS (partially in `notification_deliveries`), routing/geocoding calls, document storage and quote volume are not. Add a usage ledger. NEEDS DEVELOPMENT.
- Enforce: seat/vehicle caps at fleet vehicle create and business member invite; SMS send gate; all server side. NEEDS DEVELOPMENT.
- Wallet: the passenger "wallet" holds cards only. A stored-value wallet or credit balance does not exist (the double-entry ledger in `app/Domain/Ledger` is the template and chart of accounts exists, see `docs/R46_MONEY.md`). NEEDS DEVELOPMENT.
- Audit: ledger and `audit_logs` exist; reconciliation via `finance_issues` and `money:sweep`. Credit/usage events need the same journal pattern. NEEDS DEVELOPMENT.
- Live money: Paystack test mode default, `EHAIL_PAYSTACK_TRANSFERS=false`, cash off, routing none, operator legal details empty (`config/money.php operator`), VAT not declared. Owner inputs required before any price is set.
