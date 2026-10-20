# R-9 design: Dot Points ledger (hosted in Dot.Dopemine) and InfoDot contribution rewards

Decided by the owner on 2026-10-20 after the gap analysis (nothing existed to extend):
1. The ledger and rule engine live in **Dot.Dopemine**. InfoDot publishes signed events; Brain and Memory consume; InfoDot's profile reads.
2. Points are redeemable for **billing credit, premium capacity and marketplace items**, plus **recognition** (badges, never redeemable).
3. Points have **no cash value and are non-transferable**; a published price list per reward.
4. **Each award expires 12 months after it is granted.** Mitigations built in because this sits uneasily with Dopemine's
   rule against punitive mechanics: spending always consumes the oldest lot first, the person is warned 30 days before
   anything lapses (once, plain wording, switchable off in preferences), and expiry never hides history (it is a ledger entry).

## Ledger (Dopemine)

Append-only `ledger_entries` (no update, no delete; enforced in the model and tested): `uuid`, `user_uuid`, `type`
(`award`, `redeem`, `expire`, `reverse`), signed `points`, `rule_key`, `rule_version`, `contribution_ref`
(`infodot:answer:123`), `idempotency_key` (unique), `expires_at` (awards only), `lot_id` (the award a spend/expiry/reversal
draws on), `reason`, `meta`. Balance and every lot's remaining points are derived by summing; nothing is stored that can drift.
- **Idempotent:** one award per `(contribution_ref, rule_key)` and per event id; a replayed or edited event is a no-op.
- **FIFO spending:** a redemption writes one `redeem` entry per lot it draws on, oldest `expires_at` first.
- **Never negative:** a redemption needs enough unexpired points. A reversal removes only the unspent part of the lot and
  records the unrecovered remainder for a moderator; the balance cannot go below zero and history is never edited.
- **Expiry:** a scheduled job writes `expire` entries for lots past `expires_at` with points left, after the 30-day warning.

`reward_rules` (immutable versions; editing writes a new version): `key`, `version`, `platform`, `event_type`, `points`,
`min_points`/`max_points`, `requires_approval`, `daily_cap_points` per user, `per_target_cap`, `active`, `effective_from`.
Every entry records the exact `rule_version`. Defaults are the owner's table (question 2, answer 5, accepted +10,
verified +10, tutorial 10-20, troubleshooting 10-25, helpful 1-3, correction 3-10, adopted 10-25, multi-user bonus 5-20),
**seeded as data, editable by administrators without a deploy**.

`contributions` (the lifecycle record per platform object): `submitted -> validated -> published -> accepted | verified ->
rewarded`, plus `flagged` and `reversed`. An award needs the right state and the right actor (acceptance by the question
author or a moderator who is not the answerer; verification by someone who is neither author nor acceptor).

`rewards` (catalogue: kind `billing_credit | capacity | marketplace`, price in points, monthly cap, active) and
`redemptions` (user, reward, points, `pending | fulfilled | failed | cancelled`, fulfilment reference). Fulfilment sits behind
an adapter per kind; until Dot.Billing / the entitlement / marketplace hooks exist the adapter is **manual** (an operator
marks it fulfilled) and the screen says so. A failed or cancelled redemption writes a compensating `reverse` entry.

## Events (InfoDot -> Dopemine, signed server to server)

Same pattern as Notify and Analytics: `POST /api/ingest/contributions`, HMAC-SHA256 over `{timestamp}.{raw body}`, headers
`X-InfoDot-Timestamp` and `X-InfoDot-Signature`, per-platform secret (`DOPEMINE_INGEST_SECRET_INFODOT`), 5-minute replay window,
event id for idempotency. Types: `infodot.question.published`, `infodot.answer.published`, `infodot.answer.accepted`,
`infodot.solution.verified`, `infodot.tutorial.approved`, `infodot.contribution.helpful`, `infodot.knowledge.corrected`,
`infodot.knowledge.adopted`, `infodot.contribution.flagged`, `infodot.contribution.reward_reversed`. Minimum metadata:
`event_id`, `type`, `occurred_at`, `user_uuid`, `contribution_ref`, `actor_uuid` (who accepted or verified), `tenant_uuid`,
visibility. No content, no email. The browser never sends events: only InfoDot's server, after it has validated the action.

## InfoDot foundations needed first (none of this exists)

`users.uuid` (stable identity across apps; backfilled), a real `answers` model (today replies are comments), acceptance by
author or moderator, reports and a moderator queue, solution verification and versioning, unique per-user likes with rate
limits, duplicate and self-answer detection, tests for all of it. Votes inform but never establish correctness.

## Increments

A. Dopemine ledger core + rules + redemption + expiry, tests (this increment starts here).
B. InfoDot: user uuid, answers, acceptance, reports, anti-abuse, tests.
C. Signed event client in InfoDot (queued, retried, outage-tolerant) + intake in Dopemine; profile display (balance, lots about to
   expire, history, achievements, expertise areas as three separate things).
D. Moderation queue, appeals, reversals UI, verification workflow.
E. Brain candidate-knowledge recommendations; Memory expertise context; Dopemine notification preferences and suggestions.
F. Acceptance run against section 11 of the brief; privacy review of the public portfolio.
