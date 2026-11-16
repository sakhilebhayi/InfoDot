# R-53 runbook: one free licence per business, paid additional licences

Owner decisions D1-D12 are recorded in the R-53 strategy (2026-11-16). This file is the operating procedure for the InfoDot side. Merging the R-53 pull requests does NOT deploy: deploys are a manual workflow.

## The rule in code

- The free allowance is read in one place: `SeatLedger::freeAllowance()`. Its base is `licensing.included_seats` (env `LICENCE_INCLUDED_SEATS`, default 1).
- A company-level override (`team_licences.included_seats`) wins until `team_licences.grandfathered_until`. After that date the override is ignored and the base applies. An override with no date is a manual setting and is kept.
- Prices are configuration only (`LICENCE_SEAT_PRICE_MINOR`, `LICENCE_SEAT_PRICE_ANNUAL_MINOR`, `LICENCE_CURRENCY`, all excluding VAT). Empty means no price is shown anywhere.

## Cutover order (no one is blocked in between)

1. Read the production numbers first (queries in the R-53 code audit). Note any `team_licences.included_seats` that is not null: `licence:grandfather` never overwrites those.
2. Before deploying, set `LICENCE_INCLUDED_SEATS=3` in the host `.env` so the deploy changes nothing.
3. Deploy. The migration adds two nullable columns to `team_licences` (additive).
4. Dry run: `php artisan licence:grandfather` (writes nothing; prints each company, its people, and what it would be set to).
5. Apply: `php artisan licence:grandfather --apply` (`--days=N` changes the 90). Each company with more people than the free allowance is frozen at its current headcount (owner + members + live invitations) until T0 + N days. Re-running is safe.
6. Announce to owners (email and banner), then set `LICENCE_INCLUDED_SEATS=1` (or remove the override). New companies now get one free licence; frozen companies are unchanged.

## Rollback (setting only, nothing deleted)

- Immediate: put `LICENCE_INCLUDED_SEATS=3` back. Every company without an override returns to the old rule.
- Undo the freeze: `php artisan licence:grandfather --rollback` (report) then `--rollback --apply`. Each frozen row is restored from the snapshot stored on it. Nobody is added or removed.

## What the freeze means for a company

It keeps everyone it has. It cannot add anyone above the frozen headcount without a paid licence. When `grandfathered_until` passes, the frozen people are no longer free; what happens to them then is decided by the licence lifecycle rules (phase 2 of R-53), never by deleting anything.

## Phase 2: paid licences (batches, assignments, lifecycle)

### Tables (additive)

- `licence_batches`: one purchase = one batch = one Paystack subscription with its own renewal date (D4). `uuid`, `team_id`, `quantity`, `interval` (month|year), `unit_price_minor`, `total_minor` (ex VAT, fixed by InfoDot from configuration), `currency`, `status` (pending|active|grace|ended|refunded), `period_start`, `period_end`, `grace_until`, `cancelled_at`, `quantity_next` (a reduction scheduled for the next renewal), `payment_ref`, `price_snapshot`.
- `licence_assignments`: `team_id`, `user_id`, `batch_id` (nullable), `state` (active|reserved|lapsed|released), `assigned_at`, `released_at`. A member with no row is licensed (so existing companies need no backfill). `reserved` is part of the vocabulary; a pending invitation still holds its place through `SeatLedger` rather than a row.
- History is `team_licence_events` (`batch_created`, `batch_activated`, `batch_renewed`, `batch_payment_failed`, `batch_cancelled`, `batch_cancel_requested`, `batch_reduction_scheduled`, `batch_refunded`, `batch_ended`, `batch_payment_overdue`, `licence_assigned`, `licence_released`, `licence_lapsed`, `licence_reactivated`, `licence_grace_started`, `licence_grace_cleared`, `ownership_licence_swapped`).

### Capacity

`capacity = free allowance + legacy purchased_seats + sum(quantity of batches that count now)`. A batch counts when it is `active` and inside its paid period (plus `LICENCE_PERIOD_TOLERANCE_MINUTES`, default 60, for a renewal that is minutes late), or in `grace`/`refunded` until `grace_until`. This is computed from the dates on every read, so a missed scheduler run cannot leave an expired batch counting. A `pending` batch never counts.

### The confirmation Dot.Finance sends (same endpoint, same HMAC, same 300 s window)

`POST /api/finance/platform-subscription-events`, headers `X-InfoDot-Timestamp` and `X-InfoDot-Signature: sha256=HMAC_SHA256("{timestamp}.{raw body}")` with `PLATFORM_SUBSCRIPTION_CALLBACK_SECRET`. A body with no `kind` is a platform subscription event exactly as before. A body with `"kind": "licence_batch"`:

```json
{
  "kind": "licence_batch",
  "event_id": "unique-per-event",
  "type": "paid | renewed | payment_failed | refunded | cancelled",
  "batch_uuid": "...", "team_uuid": "...",
  "quantity": 3, "interval": "month",
  "period_start": "ISO-8601", "period_end": "ISO-8601",
  "amount_minor": 29700, "currency": "ZAR",
  "paystack_reference": "...",
  "occurred_at": "ISO-8601 (optional, used to ignore events that arrive out of order)"
}
```

`period_start`, `period_end`, `amount_minor`, `currency` are required on `paid` and `renewed`. Answers: `{"status":"applied|duplicate|ignored"}`, 400 malformed, 401 signature, 422 unknown/mismatched (a `paid` whose quantity, interval, total or currency differs from what InfoDot priced is refused and stores nothing). Finance never sends a price InfoDot did not set: the batch's `total_minor` is what `POST {FINANCE}/api/licences/checkout` is given.

Effects: `paid` activates a pending batch (never anything else); `renewed` extends the period (a smaller `quantity` is a reduction); `payment_failed` starts 14 days of grace from the period end; `cancelled` ends the batch at the period end (a pending one just closes); `refunded` gives 14 days of grace then lapses. Duplicates answer `duplicate`; an event the batch has already moved past is stored and answered `ignored`. `ops:check` reports `licence_batch_active_without_activation_record` (must be 0).

### Lapse rules (D5, D8)

`LicenceLifecycle::rebalance()` is the one rule. Room: lapsed people get their licence back, longest-standing member first; any running grace is cleared. Over capacity: nothing happens on the day; a 14-day grace starts (`team_licences.grace_until`); when it ends and the company is still over, the most recently added people lapse. A batch that already had its own grace (failed payment, refund) lapses its people straight away. The owner and administrators never lapse. Nothing is deleted: a lapsed person keeps their account, content and place in the company, and loses the company's platforms (`PlatformAccess` refuses with `not_licensed`; the SSO redeem answer shows the company as not entitled for them). `php artisan licence:sweep` (every five minutes) moves batches along by date.

### Owner actions (services, no screens yet)

`LicenceBatches::createPending / reduceAtPeriodEnd / cancelAtPeriodEnd`, `LicenceAssignments::release / assign / reassign`, and the free licence moving with `TransferTeamOwnership`. All owner-only (`changeLicence`), all audited.

### Not in these PRs

Team Licences and upgrade screens; the call to Finance's `/api/licences/checkout` and the checkout redirect; the banner and email on a failed payment; the daily reminder emails. Finance's side is built separately against the same contract.

## Phase 3: Team Licences screens and the upgrade journey

- Settings: Company settings, section "Team licences" (`resources/views/teams/_seats.blade.php`, data from `LicenceOverview`). Owner and administrators see it; only the owner can buy or change anything (`changeLicence`).
- Upgrade: when no licence is free the "Add a person" form is replaced by the upgrade panel (why, what it includes, cost, monthly or yearly, how to buy more). The buy page is `GET /teams/{uuid}/licences/buy`; the quantity and interval are the only things taken from the browser, the total is worked out from configuration.
- Paying: `POST /teams/{uuid}/licences/buy` makes (or reuses) the pending batch and calls `POST {LICENCE_FINANCE_URL}/api/licences/checkout`, signed with `FINANCE_ENTITLEMENT_SECRET` (X-InfoDot-Timestamp / X-InfoDot-Signature), then redirects to the `checkout_url` (https only). Any failure shows "nothing has been charged"; the batch stays pending and is closed by `licence:sweep` after `LICENCE_PENDING_BATCH_DAYS`. Needs `LICENCE_FINANCE_CONNECTED=true`, `LICENCE_FINANCE_URL`, `FINANCE_ENTITLEMENT_SECRET` and the price variables.
- Return: set Finance's `LICENCE_CHECKOUT_RETURN_URL` to `https://<infodot host>/licences/return`. Paystack appends `?reference=lic_<batch uuid without dashes>`; the page maps that to the batch, checks the signed-in person may see it, and only ever reads the ledger (waiting / active / closed). `/licences/return/failed` is the same page worded for a failed attempt. It refreshes every `LICENCE_RETURN_REFRESH_SECONDS` for `LICENCE_RETURN_WAIT_MINUTES`, then stops and says it is taking longer.
- Owner actions (take back, give, move, close unpaid purchase) each open a page that names who loses access and when before anything happens. Reduce and cancel call Finance first and are OFF (`LICENCE_FINANCE_CANCEL_ENABLED`, default false) because Finance has no cancel endpoint: switching them on before it exists would show a subscription as stopping while Paystack still charges. While off the page says so and nothing changes.
- The older R-13 Finance pull no longer adds `purchased_seats` once a company has any licence batch (it would count every paid licence twice, because Finance's `seat_limit` includes them).
