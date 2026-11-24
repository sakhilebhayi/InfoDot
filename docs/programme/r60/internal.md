# Internal services: Dot.Memory and Dot.Dopemine (short note, read-only)

Evidence base: `origin/main` read-only extracts (local trees are 50 and 61 commits behind). Neither platform has any plan, subscription, fee, wallet or customer-facing billing code (grep for plan/subscription/paystack/commission/credit returned only Points rewards in Dopemine and nothing in Memory). EXISTING absence.

## Dot.Memory (`app/Services/Memory`, `Knowledge`, `Participation`, `routes/api.php`)
- Purpose: persistence substrate for other platforms' intelligence loops: loop records (events, decisions, actions, outcomes via `POST /api/intelligence/*`), ops incidents and recall (`/api/ops/*`), retrieval SLA tracking, participation store with consent, knowledge recommendations for InfoDot/Brain (signed, rate-limited `knowledge.rate_limit_per_minute` 120). EXISTING.
- Measurable resources: `loop_records`, `ops_incidents`, `ops_recall_events`, `retrieval_observations`, `participation_consents`, `knowledge_outcomes`, storage tiers/indexes tables. Callers already identified by team (Sanctum) or signed secret (`config/knowledge.php`, `config/participation.php`). EXISTING.
- Cost drivers: database/SQLite storage and backups (nightly backup command), no third-party service calls found.
- Monetisation view (PROPOSED): do not sell Memory directly. Treat it as a shared internal service whose cost is carried by the platforms that write to it (Central already reports decisions to it). If a quantity is ever charged, the neutral unit is "records retained" or "retention months", metered from `loop_records` counts per team. Retrieval/recall for safety (incidents, audit) stays free. NEEDS DEVELOPMENT for any per-team count or retention cap; none exists.

## Dot.Dopemine (`app/Services/Points`, `routes/api.php`)
- Purpose: the ecosystem engagement platform and, in practice, the Dot Points ledger: signed ingest of contributions from InfoDot (`POST /api/ingest/contributions`), rules with versions, 12-month point lots with expiry (`PointsLedger.php`, `ledger_entries`: uuid, user_uuid, type, points, rule_key, rule_version, idempotency_key unique, lot_id, expires_at), rewards and redemptions, expiry warnings, digests, a prohibited-metrics ethics gate. EXISTING.
- Rewards table has `kind` = billing_credit | capacity | marketplace, `price_points`, a `monthly_cap` across all people (`RewardMonthlyLimitReached`), params JSON; redemptions have idempotency key and pending/fulfilled/failed/cancelled status (`2026_10_21_100004_create_rewards_and_redemptions_tables.php`). So the code already anticipates points converting into credits or capacity. Fulfilment of billing_credit/capacity is NOT connected to any wallet (none exists). EXISTING design, NEEDS DEVELOPMENT for fulfilment.
- Cost drivers: storage, Dot.Notify emails for expiry warnings and digests (`app/Services/Notify`). No vendor payments.
- Monetisation view (PROPOSED): Dopemine is a cost centre and a growth tool. It is the best in-house template for a shared wallet ledger (idempotency key, lots, expiry, append-only entries, caps per period). Points should remain non-cash and non-purchasable; they could be redeemed for platform credits only inside an owner-set monthly cap, so they cannot become an uncontrolled liability. Free for all platforms.

## Gaps common to both
- Not exposed to customers; no entitlement middleware checks. Metering would be internal chargeback only (platform to platform), not invoices. NEEDS DEVELOPMENT only if the owner wants internal cost allocation.
- Status caveat: wikis say "unverified, hand-authored by an AI agent" for early passes; origin/main has since added Guardian health, backups and hardening commits, but current production usage volume was not verified here.
