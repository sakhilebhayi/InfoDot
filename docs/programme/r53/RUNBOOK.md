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
