# Dot.Finance: monetisation audit (read-only, R-60)

Evidence base. The local working copy `/Users/sakhilebhayi/Dot/Dot.Finance` is STALE (branch `main` at cd3a1ca, 83 commits behind origin; it is still the old personal-finance tracker). Everything below was read from `origin/main` e63e9fb (2026-11-18, "R-54 central InfoDot sign-in") exported with `git archive` into the scratchpad (`.../scratchpad/src/Dot.Finance`). Paths are repo-relative. No `.env` read; only `.env.example`/config. Tags: EXISTING = verified in code; PROPOSED; NEEDS DEVELOPMENT.

## 1. What it does (core value)
- EXISTING: double-entry accounting on top of a cash ledger, per personal or company ledger (InfoDot teams): immutable hash-chained journals, periods, chart of accounts (`docs/R44_REPORT.md` s2, `database/migrations`: `journal_entries`, `journal_lines`, `gl_accounts`, `accounting_periods`).
- EXISTING: South African sales and purchases: quotes, pro formas, tax invoices, credit/debit notes, signed customer links, reminders, statements; bills, expense claims, payment runs (`routes/sales.php`, `routes/purchases.php`, tables `sales_documents`, `bills`, `payment_runs`).
- EXISTING: bank statement import (CSV/OFX/QIF) and reconciliation, no live bank feed (`routes/bank.php`, `docs/R44_BANK.md`).
- EXISTING: SA tax preparation (VAT201 fields, EMP201, provisional, income-tax estimate); never submits to SARS (`routes/tax.php`, `docs/R44_REPORT.md` s2 "Tax").
- EXISTING: 13 reports, planning/forecast, a rules-based assistant with an optional model layer (OFF by default) (`routes/reporting.php`, `routes/planning.php`, `config/intelligence.php`).

## 2. Most valuable features (measurable business value)
- EXISTING: invoicing + receivables ageing + reminders (cash collected sooner): `app/Http/Controllers/Sales/*`, `app/Services/Sales/Reminders.php`, `routes/console.php` (daily `SendReminders`, `RunRecurring`).
- EXISTING: VAT201 preparation with drill-down and VAT control reconciliation (`app/Http/Controllers/Tax/VatController.php`, `docs/R44_TAX.md`).
- EXISTING: bank reconciliation matching engine (`app/Services/Bank/*`, `app/Http/Controllers/Bank/ReconciliationController.php`).
- EXISTING: purchases: bills, supplier bank-detail change guard, payment runs, receipt capture with reviewable extraction (`app/Http/Controllers/Purchases/*`, `config/purchases.php`).
- EXISTING: cash-flow forecast, affordability, anomaly detectors, weekly digest (`app/Services/ForecastService.php`, `app/Services/Intelligence/Detectors`, `config/intelligence.php`).
- EXISTING: controls: 6 roles / 27 capabilities, approvals, spend limits, audit log (`app/Http/Controllers/Controls/*`, table `finance_audit_log`).
- Interpretation (PROPOSED): value concentrates in (a) documents issued to customers, (b) tax/period close, (c) reconciliation and (d) the AI layer.

## 3. Existing limits, quotas, plans, entitlement checks, billing code
Entitlement consult (EXISTING):
- `app/Http/Middleware/EnsureCompanyEntitled.php` runs after `ResolveLedgerContext` in every signed-in group. It calls `app/Services/InfoDot/EntitlementClient.php::forCompany()` which does `GET {infodot}/api/ecosystem/companies/{uuid}/platforms?platform=finance` with bearer secret and `X-Dot-Platform`. Answer is BOOLEAN `entitled` + `status` per platform, cached 10 min (failure cache 60 s). Any failure or unknown = let through (fail-open). Refusal = 403 page; Livewire updates abort(403). Personal ledger is never asked (session flag `infodot.personal_entitled`). Switch: `FINANCE_ENTITLEMENT_CHECK` (`config/ecosystem.php`).
- GAP: the contract carries no numeric allowance (no seat count, storage, credits, doc quota). It is access (on/off) only.
Hard limits that exist (not commercial):
- `config/shared.php`: attachments `max_bytes` 15 MB (`FINANCE_ATTACHMENT_MAX_BYTES`), mime allow-list; signed share-link days (default 30, max 180); share throttle.
- `config/ops.php`: CSV import max 5,000 rows via web (larger via CLI).
- `config/intelligence.php`: assistant 12 questions/person/min; AI model max_tokens 400, timeout 12 s; model layer `FINANCE_AI_ENABLED` default false.
- `config/licences.php`: `max_quantity` 1000 per checkout; `included_seats` 1.
- Role/capability matrix and spend limits are access controls, not commercial plans.
No plan, tier, subscription or credit code exists for Finance's own product (EXISTING: grep of `app/` and `config/` finds billing only under `Licences/` and `Paystack/`, which sell InfoDot seats).

## 4. Billing capabilities in detail (where a wallet would live)

### 4.1 Architecture (EXISTING)
Finance is the payment arm for InfoDot "user licences" (R-53): InfoDot holds the licence ledger and price authority (`r53/CONTRACT.md`, `config/licensing.php` on the InfoDot side); Finance runs Paystack, issues the invoice into a seller company's books, handles refunds and pushes signed confirmations back. Wired in `routes/intel_api.php` lines 26-33.

### 4.2 API contracts used by InfoDot (EXISTING, signed server-to-server)
Signature: `app/Services/Licences/LicenceSignature.php`, HMAC-SHA256 over `"{timestamp}.{raw body}"`, header `X-InfoDot-Signature: sha256=<hex>` + `X-InfoDot-Timestamp`, tolerance 300 s, secret `LICENCE_INFODOT_SECRET` (falls back to `FINANCE_ENTITLEMENT_SECRET`). Size cap 16,384 bytes.
- `POST /api/licences/checkout` (`Api/LicenceCheckoutController`, throttle 60/min). Body: `batch_uuid` (idempotency key), `team_uuid`, `quantity` 1..1000, `interval` month|year (+ aliases), `total_minor` (1..2,000,000,000), `currency` must equal `ZAR`, `customer{name,email,address?}`, `vat_treatment?`. Replies `{checkout_url,batch_uuid,status,replayed}`; 409 same batch_uuid with different terms; 502 Paystack failure; 503 no Paystack key. Amount/quantity only from the signed body.
- `POST /api/licences/cancel` (`Api/LicenceCancelController`): `{batch_uuid,team_uuid,mode:"at_period_end"}` disables the Paystack subscription; ends at period end; idempotent; 404 for another company's batch; 409 subscription_not_ready.
- `GET /api/entitlements/{team_uuid}` (`Api/LicenceEntitlementController`, signed GET): returns `licensed_quantity`, `seat_limit = included_seats + licensed`, per-batch `status/quantity/interval/period_end`. Kept for reconciliation.
- `POST /api/paystack/webhook` (`Api/PaystackWebhookController`, throttle 240/min): verifies `x-paystack-signature` HMAC-SHA512 of raw body (`PaystackSignature`), dedupes by `event_key` in `paystack_webhook_events`.
- Finance -> InfoDot push (`Services/Licences/LicenceOutbox.php`, job `app/Jobs/PushLicenceEvent.php`): `kind: licence_batch`, `type` in paid|renewed|payment_failed|refunded|cancelled, with period_start/end, amount_minor, currency, paystack_reference; transactional outbox table `licence_outbox`, exponential backoff 30 s..6 h, `rejected` parked on 400/422; target `LICENCE_PUSH_URL` (InfoDot R-50 endpoint `/api/finance/platform-subscription-events`).
- Reconcile: `licences:reconcile` reads InfoDot `GET /api/licences/reconcile/{team_uuid}` (`LICENCE_RECONCILE_INFODOT_URL`).

### 4.3 Checkout (EXISTING)
`Services/Licences/LicenceCheckout.php`: creates `licence_batches` row, cache lock per batch, creates/reuses a Paystack plan per exact (amount, currency, interval), calls `PaystackGateway::initializeTransaction` with the plan code, stores hosted `checkout_url` (status awaiting_payment). Reference `lic_<uuid>[_n]`. Card only, via Paystack hosted page (`Services/Paystack/HttpPaystackGateway.php`; interface `PaystackGateway` has createPlan, initializeTransaction, createSubscription, disableSubscription, verifyTransaction, refund). Whole feature inert without `PAYSTACK_SECRET_KEY` (`config/paystack.php`; checkout 503).
- NOTE for a wallet: the gateway's `initializeTransaction(..., ?planCode)` allows a plan-less one-off charge (EXISTING capability), but `LicenceCheckoutController` requires `interval` and `LicenceCheckout` always attaches a plan: a one-off top-up is NEEDS DEVELOPMENT (new endpoint/kind), not a rewrite.
- Open owner point recorded in `r53/CONTRACT.md`: one Paystack account has one webhook URL per mode, and Dot.Mines already receives `/webhooks/paystack`; Finance's webhook URL cannot be the same unless routed/forked.

### 4.4 Price book (EXISTING = none in Finance; by design)
Finance holds NO price list. Price authority is InfoDot (R99/user/month, R990/user/year, marginal bands per `r53/CONTRACT.md` D1-D3, owner approved 2026-11-16). Finance's only price object is `paystack_plans` (`amount_minor`,`currency`,`interval`,`plan_code`) created per exact amount by `Services/Paystack/PlanRegistry.php` and never updated. A credit/resource price book would be NEEDS DEVELOPMENT (best kept in InfoDot config as today, or a versioned `price_book` table in the wallet service).

### 4.5 Webhooks and payment application (EXISTING)
`Services/Licences/LicenceWebhookProcessor.php`: handles charge.success, invoice.update, subscription.create, invoice.payment_failed, subscription.disable/not_renew, refund.processed/failed. Value is granted only after re-verifying the transaction with Paystack (`verifyTransaction`: status success, currency ZAR, exact batch amount). One payment = one `licence_payments` row unique by Paystack reference (renewal reported twice is granted once). Unmatched money is recorded `unmatched`, batch flagged `needs_review`, never guessed. Batches lock `lockForUpdate`. Statuses: pending, awaiting_payment, active, past_due, cancelled, refunded.
Idempotency is strong on every layer (batch_uuid, event_key, reference, outbox event_id): this is the right pattern to reuse for wallet top-ups.

### 4.6 Invoices and VAT (EXISTING)
`Services/Licences/LicenceInvoicer.php` makes a SA tax invoice in the seller company's books through the ordinary Sales services (`DocumentDrafts`, `DocumentIssuer`, receipts). Seller = `LICENCE_SELLER_TEAM_UUID` ledger (+ optional `LICENCE_CASH_ACCOUNT_ID` clearing account); unset = payments still recorded and confirmed but no invoice (`skipped`, retry command `licences:invoices`). VAT follows the seller ledger's own profile: VAT-registered = amount treated VAT-INCLUSIVE with `ZA_VAT_STD`; not registered = no VAT line. Owner clarification 2026-11-16: selling business NOT VAT-registered, prices final, no VAT added. Buyer `vat_treatment` and address are only stored (for credit notes), not applied. Sales layer supports gap-free numbering, issued-document immutability (DB trigger), SA tax-invoice checklist gate for registered sellers (`docs/R44_SALES.md`).
- Trap: if the seller later registers for VAT the invoice treatment flips to inclusive automatically but `r53` says a "price ex VAT, VAT on top" change would be a checkout change (not built).

### 4.7 Multi-currency (EXISTING in books, NOT in billing)
Books: `currencies`, `fx_rates` (rate_e8 integers, history never overwritten, ECB feed OFF by default, no entry screen: `docs/R44_DECISIONS.md` D-209/D-210, `config/shared.php`). Billing: `config/licences.php` `currency=ZAR` and checkout validates `in:ZAR`; verifyTransaction rejects other currencies. ZAR-only today, good for "ZAR first".

### 4.8 Refunds (EXISTING)
`Services/Licences/LicenceRefunds.php` + CLI `licences:refund` (`Console/Commands/Licences/RefundBatch.php`): refund via Paystack only for a FIRST purchase within `LICENCE_REFUND_WINDOW_DAYS` (7) unless `--force`; recorded only when `refund.processed` arrives; then a credit note in the seller books (`licence_credit_notes`) and a `refunded` push to InfoDot. Legal review of wording skipped by owner decision (accepted risk).

### 4.9 Subscriptions (EXISTING, narrow)
One Paystack subscription per batch; quantity change = new batch; cancel at period end (`LicenceCancel`); `licence_batches.period_start/period_end`, `first_paid_at`, `cancelled_at`. Grace/lapse rules live in InfoDot (14-day grace, D8).

### 4.10 Wallet readiness summary
- Present and reusable: idempotent signed contract, Paystack verification, payment dedupe, outbox push with retries and reconciler, invoice into SA books, refund -> credit note, ZAR-only enforcement, audit tables.
- Absent (NEEDS DEVELOPMENT): a balance ledger (credit/debit entries, never-negative, expiry, reservations), top-up (one-off) checkout kind, consume/deduct API, per-resource price book, deferred-revenue treatment (unused credits as a liability; the GL exists so it can be posted), usage statements, low-balance notification (Notify client exists: `Services/Integrations/NotifyClient.php`), per-platform allocation of one wallet.
- Risk to flag: accounting treatment of prepaid credits (revenue recognition on use vs on sale, VAT point of supply) needs an accountant decision; breakage/expiry and refund rules need legal review (D10 already pending).

## 5. Resources consumed or stored, and where they can be counted today
| Resource | Where counted (EXISTING) |
|---|---|
| Issued documents (invoices/quotes) | `sales_documents` (+`sales_document_events`), `document_sequences` |
| Bills / expense claims | `bills`, `expense_claims` |
| Transactions / journal lines | `transactions`, `journal_entries`, `journal_lines` (200k-row ledger tested, `docs/R44_QA_perf.md`) |
| Bank statement lines imported | `bank_statement_lines`, `bank_statements` |
| Attachment storage | `attachments.size` (bytes), per ledger owner columns |
| Customer/supplier records | `parties` |
| Emails sent to customers | no counter; sent via `Mail::to()` in `Sales/DocumentMailer.php`, `Sales/Reminders.php`; `sales_reminders` table records reminders, `sales_document_events` records sends (verify) |
| AI assistant questions | `assistant_messages`; no token/cost count |
| Receipt/model extraction calls | `Services/Purchases/Extraction/ModelExtractor.php`; no usage row |
| Report exports | `report_exports` |
| Seats/members | `team_user`, `ledger_member_roles` |
| Ledgers (companies) | `teams` |
| Audit actions | `finance_audit_log` |
| Background jobs | `jobs`, `failed_jobs` (queue `database`) |
| Paystack transactions | `licence_payments`, `licence_batches` |
All tenant-owned by ledger owner columns with a fail-closed tenant scope, so per-ledger aggregation is straightforward. NEEDS DEVELOPMENT: a dedicated append-only `usage_events` (ledger, resource, quantity, source_id, at) so counts do not depend on row deletion.

## 6. Third parties and cost drivers
- Paystack (payments; fees per transaction). Anthropic API (assistant wording, receipt extraction; per-token, both OFF by default: `config/intelligence.php`, `config/purchases.php`). Mail transport (SMTP/log; Postmark/Resend/SES placeholders in `config/services.php`). ECB reference rates (free, OFF). Dot.Notify webhook (internal). Dot.Analytics (internal). InfoDot (redeem/entitlement/membership). Reverb (broadcast).
- Cost drivers: AI tokens (receipt images are the largest), storage of attachments (15 MB each), outbound email, Paystack fees, database growth, PDF/XLSX generation CPU (exports streamed/queued).

## 7. Candidate purchase units and free tier
- Free core (PROPOSED, keep always free so books are never held hostage): recording transactions, accounts, categories, budgets, the personal ledger, reading and exporting your own records (the entitlement middleware already exempts report/attachment downloads for legal retention duty: `EnsureCompanyEntitled::EXEMPT`), bank statement import within the 5,000-row cap, tax calendar, rules-based assistant (no model).
- Capacity units (PROPOSED): attachment storage GB; ledgers (companies); named seats beyond the InfoDot-licensed ones (seats already sold via licences; do not double charge).
- Resource/credit units (PROPOSED): AI credits for model-assisted receipt extraction and assistant wording; customer-facing emails/reminders sent beyond a monthly free block; large exports/bulk imports through the CLI-only path; issued documents per month beyond a generous free allowance.
- Feature-gated (PROPOSED, sparingly): recurring invoicing, payment runs, multi-currency with FX, approvals/spend limits. Tax prep should stay free for compliance trust.
- Transaction unit (PROPOSED, later): a per-collection fee if Finance ever provides online invoice payment (not built: `config/sales.php` says gateways other than manual are not implemented).
- When allocation runs out (PROPOSED): never block reading, exporting or reconciling. Block only the metered action (e.g. send the email, run the AI extraction, upload the file) with a plain message and a top-up link; fall back to the rules-based path (assistant) or manual entry (receipt). Statuses already work this way for the AI: AnthropicClient returns null on any failure and the assistant continues (`app/Services/Intelligence/AnthropicClient.php`).

## 8. Cross-platform dependencies and shared resources
- Depends on InfoDot: identity, teams, roles, entitlement (`ecosystem.php`, `EntitlementClient`), membership webhook (`Api/EcosystemMembershipController`), licence ledger (above).
- Integrations: Dot.Emall sales feed inbound (`Api/EmallSalesController`, table `emall_sale_lines`; contract only, Emall exports nothing yet), Dot.HR payroll (contract only), Dot.Analytics publisher (inert), Dot.Notify client (inert until configured). Dot.Files has no storage API for Finance (`docs/R44_REPORT.md` s7): attachments are Finance-local.
- Shareable across the ecosystem (PROPOSED): the wallet and price book (Finance as billing home), AI credits (Finance, Files, Notify, Tasks all call Anthropic), seats (already InfoDot-owned), comms credits (email/SMS used by Notify and Finance reminders), storage if Files becomes the central store.
- Platform-specific (PROPOSED): issued-document allowances, bank-statement lines, ledgers.

## 9. Gaps: meter, enforce, audit
- NEEDS DEVELOPMENT meter: `usage_events` append-only table + emitters at DocumentIssuer, DocumentMailer/Reminders, Attachments upload, ModelExtractor/AnthropicClient, exports.
- NEEDS DEVELOPMENT enforce: backend check (service-layer, not UI) before each metered action; extend the InfoDot entitlement answer with numeric allowances (it is boolean today) or add a wallet balance API with a reserve/commit/release pattern; fail-open vs fail-closed policy per resource (AI: fail to rules; storage: block upload only).
- NEEDS DEVELOPMENT audit: wallet ledger as append-only, double-entry style (credit/debit, balance never stored alone), tie-out check command like `finance:gl:verify`, reconciliation to Paystack settlements (the existing `licences:reconcile` pattern).
- NEEDS DEVELOPMENT: one-off top-up checkout kind, a `credit_topped_up` / `credit_consumed` push kind to InfoDot, price book, VAT/revenue policy decision, legal terms for expiry/refund.
- Housekeeping before building: sync the local working copy (83 commits behind) and resolve the open Paystack webhook-URL sharing with Dot.Mines.
