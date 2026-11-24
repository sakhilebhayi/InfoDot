# R-59 Phase 0 verdict: can Finance take R98 per seat, and can the ledger carry referral rewards?

Date: 2026-11-24. Read-only audits of `Dot.Finance` (origin/main e63e9fb, 2026-11-18) and `Dot.Dopemine` (origin/main 99c72b3, 2026-10-30). The local checkouts were behind, so both were read from origin/main with read-only git commands. Neither audit ran code, tests or migrations, and neither could see production configuration, so "has it run against real Paystack" and "is the production secret set" remain **unverified**.

Owner decisions D1 to D9 in `R59_AUDIT_AND_PLAN.md` were approved on 2026-11-24 with the recommendations given there.

## 1. Dot.Finance: verdict

**In principle yes, today, by card, once configured.** The note in `docs/programme/r50/AUDIT_ANALYTICS_PAYMENTS_NOTES.md` that Finance has no provider client, webhook or subscription code is **stale** (it described commit d8e43ab, before November 14 to 18). Finance now has its own "R-53 licence batch" system: a different design from the R13 contract (batches, one Paystack plan per amount, instead of plans and subscriptions), but it covers the path InfoDot needs.

| Need | State | Notes |
|---|---|---|
| Hosted checkout for N seats | EXISTS | `POST /api/licences/checkout`, signed (HMAC-SHA256 over `{ts}.{body}`, 300 s), idempotent per batch, max 1,000 seats, ZAR only. Finance does **not** set the price: it trusts `total_minor` from InfoDot's signed body, so N x 10000 gives R98 a seat |
| Paystack initialise, verify, refund, subscription disable | EXISTS | Webhook is signature-checked, de-duplicated, and re-verifies status, currency and exact amount before granting. Code header says some payload shapes were written "as recalled" and are unverified against real Paystack |
| Renewal | PARTIAL | Paystack-native (saved card); no dunning or grace logic of its own; past-due seats stay valid until period end |
| Signed events to InfoDot | EXISTS | Outbox with retry; `paid`, `renewed`, `payment_failed`, `refunded`, `cancelled`; target is whatever `LICENCE_PUSH_URL` is set to |
| Cancel | EXISTS | `POST /api/licences/cancel` (added 2026-11-18); InfoDot's cancel switch is still off |
| Add or reduce seats | MISSING | Quantity is fixed per batch; adding seats means a new batch; no proration |
| Refunds | PARTIAL | Operator-initiated command only, 7-day first-purchase window, credit note built; no customer-facing refund |
| Seat invoice with VAT | PARTIAL | Real SA tax invoice through the Sales module, VAT per the seller ledger's profile; **does nothing unless the seller ledger and cash account are configured**; no customer VAT number field |
| Multi-currency, sponsor-pays, usage limits, price book | MISSING | ZAR only |
| Entitlement read for InfoDot | PARTIAL | Finance returns `seat_limit = LICENCE_INCLUDED_SEATS (default 1) + licensed seats`; InfoDot's reader assumes 3 free seats and subtracts 3, so the two sides would disagree by two seats unless aligned |
| `POST /api/companies` | MISSING | Not needed: checkout carries team and customer details |
| Tests | EXISTS | Seven licence test files plus a fake gateway; not run in this audit |

**Switches (all default off):** `PAYSTACK_SECRET_KEY`, `LICENCE_INFODOT_SECRET`, `LICENCE_PUSH_URL` and `LICENCE_PUSH_SECRET` (must point at InfoDot's `/api/finance/platform-subscription-events` and match `PLATFORM_SUBSCRIPTION_CALLBACK_SECRET`), `LICENCE_CHECKOUT_RETURN_URL`, and `LICENCE_SELLER_TEAM_UUID` plus a cash account for invoices.

**What must be true before R98 is charged to a real customer:**
1. Align the free allowance on both sides to **1** (D1 keeps one free seat): set `LICENCE_INCLUDED_SEATS=1` in InfoDot and Finance and fix InfoDot's reader to read the allowance from one place.
2. Configure the Finance and InfoDot secrets and URLs above, and the seller ledger, in production.
3. Run one real test purchase with a Paystack test key, then a live one, checking the webhook, the pushed event, the entitlement and the invoice end to end (this also removes the "payload shapes unverified" risk).
4. Decide proration, grace and refund policy (R13 owner questions are still open); until then keep cancel and reduce off and say so in the terms.
5. Add the missing pieces in Finance as separate PRs: customer VAT number on checkout, reduce-seats, and (later) multi-currency and sponsor-pays.

## 2. Dot.Dopemine ledger: verdict

**No, not without code changes in Dot.Dopemine.** It is a real ledger (rules as data, signed ingest, idempotency, reversals), but:

| Need | State |
|---|---|
| Accept `infodot.referral.*` events | Passes signature and prefix checks, then answers **202 "unsupported"** and pays nothing. InfoDot's outbox would treat that as delivered and lose the reward silently |
| Create new rules or event types | Admin UI can only edit points, caps and flags of existing rules; a new rule needs a migration, and a new event type needs the processor's allowlist and a handler (a deploy) |
| Milestone tiers by count | MISSING (one value per event); the admin form's maximum of 10,000 also blocks the 20,000 tier |
| Holding period | MISSING |
| Reversal | EXISTS: recovers only unspent points, never goes negative; spent points are lost with a note |
| Business-level reward | MISSING: every award is per user; the tenant is context only (matches decision D3: credit the owner's account) |
| Referral fraud rules | MISSING (caps and pair decay exist but are not aimed at referrals) |
| Production readiness | UNVERIFIED: the shared secret (`DOPEMINE_INGEST_SECRET_INFODOT`) is not in `.env.example`; no go-live document |

**Smallest change:** in Dopemine, add the two event types with handlers, seed milestone rules (5, 20, 100, 500), raise the admin maximum; in InfoDot, **hold each reward until the holding period has passed and send one event per tier** (so Dopemine needs no hold logic) and treat a 202 "unsupported" as a failure, not a success; confirm the production secret. Reversal can reuse `infodot.contribution.reward_reversed` with `facts.rule_key`.

## 3. What this changes in the plan

- **Phase 1A (acquisition capture), 1B (onboarding events and recommender) and 1D (Growth Centre) have no dependency on either system and can start now.**
- **1C (referrals)** splits: the InfoDot side (codes, links, attribution, state machine, fraud rules, holding period, admin tier table, reversal trigger) can start now; **issuing points is blocked on the Dopemine change** (a separate PR in that repository) and the production secret.
- **1E (R98 seats)** is configuration and alignment work in InfoDot plus the Finance switches above. It stays dormant, with the price unset in production, until the end-to-end test purchase passes.
- **Corrections to record:** the R50 note about Finance is stale; the Analytics page's hard-coded "23 of 23" figure must not be used; InfoDot's free-seat default (3) must become 1 in both repositories.
- **Unverified, to confirm with the owner or on the host:** production values of the licensing flags and secrets, whether `LICENCE_INCLUDED_SEATS=1` was applied, and the production database engine.
