# Dot.Mines monetisation audit (R-60 reference model)

Repo: /Users/sakhilebhayi/Dot/mines (HEAD 2b839ae). Read-only. Paths are relative to the repo. EXISTING = verified in code. UNVERIFIED = not confirmed in code or needs runtime/production data.

## 1. What Dot.Mines does
- EXISTING: Fleet record for a mining operation: machines, positions, telemetry, fuel, maintenance, geofences, operators and assignments (README.md:3).
- EXISTING: Exposed through the Livewire web app, a versioned REST API (routes/api_v1.php), scheduled OEM sync and signed outbound webhooks (README.md:3; routes/api_v1.php:250-260).
- EXISTING: OEM integrations: Volvo, CAT, Komatsu, Bell, Hitachi, JCB (config/integrations.php).
- EXISTING: Multi-tenant by team (Jetstream teams; global scope trait app/Traits/HasTeamFilters.php).
- EXISTING: Real-time via Laravel Reverb; AI/analytics Livewire screens (app/Livewire/AI*.php).

## 2. Monetisation architecture

### 2.1 What is purchased, and the unit
- EXISTING: The purchased resource is a MACHINE ALLOCATION: one allocation lets one machine be registered and live. Two classes: `adt` (articulated dump truck) and `heavy` (excavator, dozer, loader, grader). See database/migrations/2026_08_22_053050_add_allocation_plans_and_dynamic_paystack_plans.php:32-48 (max_machines = 1, "one allocation = one machine").
- EXISTING: Class mapping in config/billing.php:25-33. Unmapped types fall back to `adt` (cheaper) at config/billing.php:23.
- EXISTING: Price rows live in `subscription_plans`, slugs `adt-allocation` (R1,500/month, R16,200/year) and `heavy-allocation` (R2,500/month, R27,000/year); yearly is 12 months less 10% (migration lines 37-47). Seeded in a migration with `updateOrInsert`. These are the initial seeded values; whether production values were later edited in the database is UNVERIFIED.
- EXISTING: Legacy tier plans `basic`, `professional`, `enterprise` are deactivated, not deleted (migration lines 65-69). Legacy `PAYSTACK_*_PLAN_CODE` env keys remain in config/services.php:54-60 and .env.example.
- EXISTING: Currency ZAR (PaystackService.php:246; payments/invoices default ZAR).

### 2.2 How allocation is stored
- EXISTING: `machine_allocations`, an append-only ledger (migration 2026_08_22_050624_create_machine_allocations_table.php:17-30): id, team_id (FK cascade), class (adt|heavy), delta (signed int), source (purchase|refund|cancellation|expiry|restore|admin), payment_id, subscription_id, reason, created_by, timestamps; index (team_id, class). Model app/Models/MachineAllocation.php ("rows are never edited or deleted", lines 10-14). Only `purchase` (webhook) and `admin` (command) are written by code today. Writers: PaystackWebhookProcessor.php:176 and app/Console/Commands/AdjustMachineAllocation.php:65. The refund, cancellation, expiry and restore sources are declared in a comment only (no writer found).
- EXISTING: Balance = SUM(delta) per class (MachineEntitlementService.php:132-137). The balance is computed, not stored.
- EXISTING: Consumption/occupancy is a column on the consuming entity: `machines.allocation_state` (occupying | pending_activation | released), default `occupying` (migration 2026_08_22_050624_add_allocation_state_to_machines_table.php:25). Kept separate from operational `status` (same file, lines 7-19). A machine in maintenance still occupies.
- EXISTING: Invariant "Available = Purchased - Active" computed in MachineEntitlementService::summary (lines 51-98).

### 2.3 Trial
- EXISTING: A team with an EMPTY ledger gets a class-agnostic trial allowance of 2 machines (config/billing.php:13, env BILLING_TRIAL_MACHINE_ALLOWANCE). The first ledger row switches the team to purchased rules (MachineEntitlementService.php:54-72).

### 2.4 How an allocation unlocks features and data
- EXISTING: The gate is on machine CREATION and ACTIVATION, not on viewing data. Every creation path calls MachineProvisioningService::provision: Fleet UI (app/Livewire/Fleet.php:219), REST API (app/Http/Controllers/Api/MachineController.php:117), OEM sync via provisionOrPend (app/Services/Integration/IntegrationService.php:588). Activation: Fleet.php:259. Release: Fleet.php:276 (decommission keeps the record and history).
- EXISTING: OEM discovery beyond capacity creates the machine as `pending_activation`: visible, not billable, activatable later (MachineProvisioningService.php:75-98) plus a notification (type billing.machine_pending_allocation).
- EXISTING: Machine data (telemetry, fuel, maintenance and so on) is unlocked by the machine existing in `occupying` state. I found no gating of telemetry reads or sync by allocation_state elsewhere in app/ (grep for occupying/pending_activation outside Services/Billing returns only the Machine model, SyncController.php:167 and MachineResource, which only expose the value). Whether pending machines receive or store telemetry is UNVERIFIED (not traced through the sync jobs).
- EXISTING: There is no feature flag per plan. All plan features, limits and `features` JSON are 999/empty for allocation plans (migration lines 53-58). Mines monetises capacity (number of machines), not feature tiers.

### 2.5 Usage and consumption measurement
- EXISTING: Consumption is a stock count (machines currently `occupying`), computed live by a GROUP BY on `machines` (MachineEntitlementService.php:156-176). Not metered over time. No usage events or time-based draw-down. A legacy `usage_records` table is created in the original subscription migration (database/migrations/2026_01_22_110000_create_subscription_tables.php:141 drops it), but whether the app writes to it is UNVERIFIED (no writer found in app/).
- EXISTING: Capacity-tight notifications when 1 or 0 slots remain (MachineProvisioningService.php:140-162).

### 2.6 Exhaustion, lapse, recurring vs once-off, grace, refunds
- EXISTING: Exhaustion: provision throws InsufficientAllocationException when `available[class] <= 0` (MachineProvisioningService.php:45-55). The Fleet UI shows an error and a "Purchase Machine Allocation" link; the API returns 422 (MachineController.php:122-127). Existing machines keep running.
- EXISTING: Over-allocation (more occupying than purchased, e.g. legacy) is permitted: machines are never disabled, only adding is blocked (resources/views/livewire/fleet.blade.php:35-45; test MachineAllocationEnforcementTest::test_over_allocated_legacy_team_keeps_machines_but_cannot_add).
- EXISTING: Recurring: purchases are Paystack SUBSCRIPTIONS, monthly or annual, on a dynamically created plan for the exact total (PaystackService.php:152-223, 230-266; table `paystack_dynamic_plans`, unique (amount, interval)). Once-off purchases do not exist. Admin grants are the only non-subscription path.
- EXISTING: Lapse: if the team's LATEST subscription is not active/trial and its paid period has ended, purchased capacity is "suspended" (available=0) but machines are untouched, the ledger is not rewritten, and renewal restores capacity (MachineEntitlementService.php:74-125; tests/Feature/AllocationLifecycleTest.php:64-127). Canceled-but-paid stays entitled until current_period_end (lines 122-124). A ledger with no subscription row (admin grants) stays entitled (lines 107-116).
- EXISTING: Grace: no explicit grace window or dunning code. The only grace is the paid-through period. Paystack `invoice.payment_failed` produces an in-app warning and grants nothing (PaystackWebhookProcessor.php:217-242). Paystack's own retry behaviour is UNVERIFIED.
- EXISTING (gap): Refunds: `payments.status` enum includes `refunded` (subscription migration line 79) and ledger source `refund` is declared, but NO refund webhook case exists in WebhookController (switch at lines 74-103 handles only subscription.create, subscription.disable/not_renew, charge.success, invoice.update, invoice.payment_failed). No code reverses an allocation on refund or chargeback. Refunds are therefore manual (admin command with a negative delta).
- EXISTING: Cancel/resume subscription via Paystack API (PaystackService.php:318-383).
- EXISTING (gap): Mid-term quantity change is not supported. Each purchase creates a NEW Paystack subscription at the new total (initializeAllocationPurchase); adding capacity later makes a second subscription. Entitlement only checks the latest subscription (summary()'s subscriptionEntitled uses `latest('id')->first()`), while charge.success/Subscription lookups elsewhere use `->first()` (PaystackWebhookProcessor.php:123; PaystackService.php:116, BillingPortal.php:177). Behaviour with multiple concurrent subscriptions (double billing, wrong subscription governing lapse, cancelling only one) is UNVERIFIED by test and looks like a real risk.

## 3. Billing integration

- EXISTING: Provider is PAYSTACK, called directly from Mines (app/Services/PaystackService.php; base https://api.paystack.co at line 54). Dot.Finance and InfoDot are NOT involved (see section 5).
- EXISTING initiation: BillingPortal::purchase validates qty 0-100 per class and cycle (BillingPortal.php:128-166), checks policy `update` on the Team, calls initializeAllocationPurchase. The server recomputes the total from plan rows (no price from the client). It creates or gets the Paystack customer, creates or reuses the dynamic plan, then POST /transaction/initialize with metadata {team_id, billing_cycle, customer_code, allocation_adt, allocation_heavy}, and redirects the user to Paystack's hosted checkout.
- EXISTING confirmation: Paystack webhook POST /webhooks/paystack (routes/web.php:260-263; no auth, `throttle:webhooks`; CSRF exemption tested in tests/Feature/WebhookCsrfExemptionTest.php). Verification: HMAC-SHA512 of the raw body with the secret key, `hash_equals` against X-Paystack-Signature (WebhookController.php:32-38). An empty secret returns 500 (lines 26-30). Replay window: rejects events whose data.createdAt/created_at is older than 5 min (lines 50-63). Caveat: only applies if the payload carries that field. Whether Paystack charge.success includes it is UNVERIFIED, and a 5-minute limit would reject legitimate Paystack retries.
- EXISTING: Grant path is webhook-only: BillingPortal and /billing/success grant nothing (routes/web.php:218-228; PaystackService docblock lines 140-148). The `/billing/success` page only redirects with a message.
- EXISTING idempotency: (1) `payments.paystack_reference` is UNIQUE and `Payment::firstOrCreate` on it (PaystackWebhookProcessor.php:128); (2) a guard `MachineAllocation where payment_id exists` before granting (line 170). The guard is a check-then-insert with no DB unique constraint on machine_allocations.payment_id, so two truly concurrent first deliveries could theoretically double-grant (UNVERIFIED by test; the docblock claims race coverage at lines 156-158 but nothing enforces it at DB level).
- EXISTING risk: handlers catch and log exceptions and the controller still returns 200 for those (processor try/catch at lines 70-75, 144-148). A failed grant after a recorded payment would not be retried by Paystack. UNVERIFIED whether any reconciliation job exists (none found).
- EXISTING: A renewal charge.success also runs grantAllocationsForPayment if its metadata contains allocation_adt/heavy. Whether Paystack carries the original transaction metadata onto renewal charges is UNVERIFIED. If it does, each renewal would add another delta (double-counting), and if it does not, renewals correctly grant nothing. This matters for any shared wallet design.
- EXISTING tables: `subscription_plans`, `subscriptions` (paystack_subscription_code unique; status, current_period_start/end, canceled_at, ends_at), `payments` (paystack_reference unique, amount decimal(10,2) in rands, currency, status, metadata JSON), `invoices` (invoice_number unique, subtotal, tax, total, status, line_items JSON), `paystack_dynamic_plans`, `machine_allocations` (see 2.2). Migrations: 2026_01_22_110000_create_subscription_tables.php, 2026_06_03_202640_migrate_stripe_to_paystack_billing.php (Stripe to Paystack column conversion), 2026_08_22_*.
- EXISTING invoices: mirrored from Paystack `invoice.update` into `invoices` (processor lines 245-296) with tax hard-coded 0, total = subtotal, `pdf_url` null, so invoice download shows "PDF not available" (BillingPortal.php:291-305). Mines does not generate tax invoices.
- EXISTING VAT: none. `tax` = 0 everywhere in billing code; no VAT rate, VAT number or tax-invoice logic found (grep). Whether Paystack-displayed amounts are VAT-inclusive is UNVERIFIED.
- EXISTING backend entitlement checks: the service layer (MachineProvisioningService / MachineEntitlementService) is the single enforcement point, invoked by Livewire, API and integration code. There is no middleware or Gate for entitlement, and no policy ties to allocation. Authorisation of purchases uses `authorize('update', $team)` (BillingPortal.php:145, 216, 250, 275). Frontend only displays banners and links (fleet.blade.php:27-45, billing-portal.blade.php:28-33). Tests: tests/Feature/{AllocationAdminTest,AllocationLifecycleTest,AllocationPurchaseFlowTest,BillingAuthorizationTest,BillingPageTest,MachineAllocationEnforcementTest,PaystackBillingTest,WebhookCsrfExemptionTest}.php.
- EXISTING: Routes: /billing (BillingPortal) and /billing/success inside the auth:sanctum/verified/ensure_team/admin.2fa group (routes/web.php:70-81, 214-228).

## 4. Entitlement and audit design
- EXISTING auditability: purchases are traceable payment -> ledger row via machine_allocations.payment_id; admin changes require `--reason` and optional `--by` user (AdjustMachineAllocation.php:56-72); the portal shows the last 10 ledger rows (BillingPortal.php:89-111). Ledger rows are immutable by convention (no update/delete code), not by DB trigger or permission (UNVERIFIED at DB level). Payment `metadata` stores the Paystack metadata.
- EXISTING concurrency: per-team atomic cache lock `machine-provision-{team}` (10 s TTL, 5 s block) wrapped around a DB transaction that re-reads the balance inside the lock (MachineProvisioningService.php:39-63, 113-132). The lock depends on cache store atomic locks. Comment says "cache_locks table in production" (line 20). Whether production uses the database cache store is UNVERIFIED (CACHE_STORE is in .env.example only as a key).
- EXISTING balance protection: balance is derived (SUM) so no counter can drift; negative balance is not possible through the UI (purchases are positive, only admin CLI can post negative deltas, and there is no check preventing a negative net balance). Over-allocation is tolerated by design.
- EXISTING (gap): no `idempotency_key` on provisioning requests, no database-level uniqueness for `(payment_id, class)` on the ledger.
- EXISTING: Tenant isolation via `HasTeamFilters` global scope; billing queries use `withoutGlobalScopes()` and explicit team_id (MachineEntitlementService.php:132-151). Test: test_cross_tenant_ledgers_are_isolated.
- EXISTING: Logging redaction of sensitive keys (app/Logging/RedactSensitiveData.php).

## 5. Integration with InfoDot and Dot.Finance
- EXISTING: Mines holds NO SSO or OIDC code. No handoff, Socialite or InfoDot client was found in app/, routes/, config/ or composer.json (grep for handoff/oidc/socialite/infodot returned nothing outside config/ecosystem.php). Auth is Jetstream/Fortify/Sanctum with local accounts and local 2FA. `config/ecosystem.php` is only a platform registry and a `handoff_ttl` setting (lines 17-58; comment says InfoDot issues tokens). The registry still lists Dot.Billing, Dot.Farms and Dot.Plug as active (lines 22, 30, 38), which conflicts with the 2026-11-13 discontinuation decision. Cleanup is pending in this file.
- EXISTING: Public sign-up is closed (ALLOW_REGISTRATION default off in .env.example; `user:create` command in git log 1be844d). Accounts come from invitation or administrator.
- EXISTING: No InfoDot platform-entitlement or licensing check. No call to InfoDot for entitlement. Mines entitlements are fully local (own tables).
- EXISTING: No Dot.Finance integration. No code posts invoices, payments or journals to Finance, and no VAT handling.
- EXISTING: The only Dot service wired is Dot.Memory (app/Services/Memory/MemoryClient.php; env DOT_MEMORY_URL/DOT_MEMORY_TOKEN, config/services.php:38-44, plus app/Listeners/MemoryLoopSubscriber.php and MemoryOutboxRow model), unrelated to billing. Guardian logging listener also exists (app/Listeners/CountGuardianLogErrors.php).
- UNVERIFIED: whether production Mines is linked to InfoDot accounts or SSO outside this repo (nothing found in this repo).

## 6. Reusable vs Mines-specific; gaps and risks

### Reusable pattern
1. Append-only signed-delta ledger per (tenant, resource class) with balance = SUM(delta), a `source` enum and `payment_id`, `subscription_id`, `reason`, `created_by` columns (machine_allocations).
2. One grant path only: signature-verified provider webhook; UI/callback pages never grant.
3. Server-computed price from a plan table; client sends quantities only.
4. Single enforcement service (summary + provision) shared by UI, API and integrations, with a per-tenant lock and an in-transaction re-check.
5. Consumption recorded on the consumer row (`allocation_state`), separate from operational status. Lapse never mutates data and never rewrites the ledger; renewal restores capacity.
6. Trial = empty ledger rule; admin grant/revoke command with mandatory reason.
7. Payment idempotency via unique provider reference; dynamic provider plan per amount (`paystack_dynamic_plans`).
8. Capacity notifications (low/full/pending).

### Mines-specific
- Unit = machine slot (a stock held while the machine exists), two classes ADT/heavy, OEM discovery and `pending_activation`, class mapping config. Capacity model is concurrent-occupancy, not drawn-down credit. A shared wallet that spends per use needs a different consumption model (debit entries per event), which Mines does not have.
- Subscription-coupled (lapse engages only with a Paystack subscription).

### Gaps and risks (not fixed)
1. No refund/chargeback handling; ledger `refund|cancellation|expiry|restore` sources have no writer.
2. No VAT/tax: tax=0; invoices have no PDF; no tax-invoice numbering; no Finance posting.
3. Ledger double-grant guard is not DB-enforced; webhook handlers swallow exceptions and return 200, so failures are not retried and no reconciliation job was found.
4. Renewal metadata double-grant risk is UNVERIFIED (2.6, section 3).
5. Multiple concurrent subscriptions (one per purchase) while entitlement reads only the latest; no quantity-change flow.
6. Replay check is time-only and could reject legitimate retries; no stored event-id dedupe for non-charge events.
7. Admin CLI can produce a negative net balance; no guard.
8. No in-app admin UI for adjustments (command only), no per-line invoice items.
9. Local accounts only: no central identity or entitlement, so a shared wallet must be introduced from outside.
10. ecosystem.php registry still lists discontinued platforms.
11. Machine deletion hard-deletes (no SoftDeletes on app/Models/Machine.php) and releases the allocation, so history goes with it (UNVERIFIED for related-row cascade).

## 7. Cost drivers (names only; no secrets read)
- Payments: Paystack API (transactions, customers, plans, subscriptions, manage link). Secret/public keys, callback and webhook URLs via env.
- OEM telemetry APIs: Volvo CE (CareTrack), CAT VisionLink, Komatsu KOMTRAX, Bell (Fleet API plus sso.bellequipment.com token endpoint), Hitachi ConSite, JCB LiveLink (config/integrations.php). Scheduled `integrations:sync-due` (routes/console.php:49), SyncIntegrationMachinesJob, SyncMachineMetricsJob, MachineLocationUpdateJob. Per-OEM licensing or API fees are UNVERIFIED (not in code).
- Routing: OSRM base URL for route planning (app/Services/RoutePlanningService.php:70-80; `osrmBaseUrl` is a property, whether public or self-hosted is UNVERIFIED).
- Realtime: Laravel Reverb websocket server (env REVERB_*); Redis/Memcached/database cache and queue drivers (.env.example); a scheduled `queue:work` every minute (routes/console.php:37).
- Storage: S3-compatible disk configured (AWS_* env; config/filesystems.php:50), local disk default, report PDFs via barryvdh/laravel-dompdf, operator documents and mine plan uploads (MinePlanUpload model), ArchiveOldMetricsJob for telemetry retention. Telemetry volume (sensor_readings, machine_metrics) is the main database growth driver; volume and retention figures are UNVERIFIED.
- Database: SQLite for dev, MySQL/Postgres in production per migrations comments (exact production DB UNVERIFIED).
- Search: laravel/scout (driver UNVERIFIED).
- Mail: SMTP (MAIL_* env).
- Compute: AI services (app/Services/AI/*, FleetOptimizerAgent, AIOptimizationService). No external LLM HTTP call found in app/ (grep for Http:: shows only Integration, Paystack, Memory, OSRM and webhook delivery), so these appear to be local/rule-based; UNVERIFIED in detail.
- Dot.Memory service token (platform-level, optional).
- Outbound webhook delivery job (DeliverWebhookJob, queue `webhooks`).
