# R-9 gap analysis: InfoDot community rewards (inspection done 2026-10-20)

Method: read InfoDot's Q&A code and migrations; cloned Dot.Brain, Dot.Memory and Dot.Dopemine and searched them for any
points, ledger, reward or redemption code and documents; ran a GitHub code search over every repository of the account
for "Dot Points", "dot_points", "points_ledger" and "redeemable". No design or code has been written yet.

## 1. The brief assumes systems that do not exist

| The brief says | What the inspection found |
|---|---|
| "the existing Dot ecosystem scoring and rewards system" | **None.** No points, ledger, balance, score or reward table or document in InfoDot, Dot.Brain, Dot.Memory or Dot.Dopemine. The only hits for "Dot Points" are the brief itself; "redeemable" also matches an unrelated Dot.Emall promo-code feature. |
| "points earned through other Dot platforms" | No platform awards points. |
| "the user's existing Dot ecosystem profile" | InfoDot has a Jetstream profile (`/user/profile/{id}`, `profile.show`, `profile.edit`) with no contribution or points content. There is no cross-platform profile. |
| "redeemable Dot Points balance" | Nothing defines what points are redeemable for, who redeems, or any redemption flow. |
| "Dot.Brain, Dot.Memory and Dot.Dopemine ... existing architecture" | Brain = governed knowledge (Dot Knowledge Protocol, agents, ADRs). Memory = contextual retrieval/indexing. Dopemine = an **ethical engagement mechanic catalogue** (fixed `MechanicCategory` enum; deliberately no leaderboards, loss-framed streaks or variable-ratio rewards). Useful neighbours, but none holds a ledger. |

So R-9 is not "extend"; the ledger, rules engine, event contract and profile display would all be new. The brief's own
constraint ("do not create a separate rewards platform") means choosing which existing service hosts them.

## 2. What InfoDot has today (the part that can be reused)

- **Questions** (`questions`): user, team, visibility (public/company), question, description, integer `status` (default 0, unused beyond that), Scout search. Created by `QuestionsController@add_question` (validation: title 3-255, body min 3). No duplicate detection, no moderation, no acceptance.
- **Answers**: there is **no answers table**. Replies are `comments` (polymorphic on questions and solutions, threaded by `parent_id`, soft-deleted). An `Answer` model exists (15 lines) with no migration. Nothing marks a comment as the accepted solution.
- **Likes** (`likes`): polymorphic, per user, soft-deleted; no uniqueness constraint seen, no anti-abuse.
- **Solutions + steps**: tutorial-like content with steps (`solutions`, `solutions_step`), visibility/team columns added 2026-10-17. No verification, versioning or review.
- **Moderation, reports, appeals, reputation**: none. **Tests**: no feature tests for questions, comments or likes were found.
- Identity/teams/tenant scoping (`HasKnowledgeVisibility`, Jetstream teams) exist and can carry tenant isolation.

## 3. Proposed shape (for the owner to confirm; nothing built)

Recommendation: **Dot.Dopemine hosts the ledger and rule engine**; Brain and Memory consume events; InfoDot publishes events and
displays results.

- Why Dopemine: it is already the ecosystem's single, ethics-audited home for "how do we encourage participation", has an
  enum-based guard against manipulative mechanics, deployments/outcomes tracking and wellbeing observations. A points
  **ledger** there keeps the money-like record away from Memory (which the brief forbids from being authoritative) and
  away from Brain (which governs knowledge, not transactions).
- Ledger: append-only entries (idempotency key = contribution id + rule id + rule version), balances derived, reversals as
  new entries, never an unauthorised negative balance, full audit trail. Rules are rows (value, eligibility, daily cap,
  approval requirement, platform scope), not code.
- Events: InfoDot -> Dopemine over the existing signed server-to-server pattern (HMAC over timestamp + body, as for Notify
  and Analytics), with the ten `infodot.*` events from the brief; Dopemine decides awards; Brain receives approved
  contributions as candidate Knowledge Pack material through its review workflow; Memory receives expertise context.
- InfoDot first needs the missing foundations: an `answers` model or accepted-comment marker, acceptance by the question
  author or a moderator, a moderation and reporting workflow, independent verification for solutions, versioning of solutions,
  and anti-abuse (unique likes, rate limits, duplicate and self-answer detection).
- Profile: InfoDot's profile page reads balance and history from Dopemine's API and shows redeemable points, achievements and
  any quality indicator separately, as the brief requires.

## 4. Decisions only the owner can make

1. **Home of the ledger** (recommended: Dopemine) and whether the first increment may start from "no points exist anywhere".
2. **What "redeemable" means.** There is no redemption catalogue. A points balance people can redeem is a reward currency with
   consumer-protection, tax and liability consequences; the safe first increment is balance + history with redemption
   deferred until the rewards themselves are defined.
3. Whether the first increment is limited to InfoDot Q&A foundations (answers, acceptance, moderation) plus the ledger and
   three event types, with Brain/Memory/Dopemine-side recommendations in later increments.

## 5. Proposed phases once decided

P1 InfoDot Q&A foundations with tests (answers, acceptance, reports, uniqueness, rate limits) -> P2 ledger + rule engine + signed
event intake in the chosen service, with idempotency and reversal tests -> P3 `infodot.*` events and the profile display ->
P4 verification and moderation workflow with appeals -> P5 Brain/Memory/Dopemine integrations -> P6 anti-abuse signals and
acceptance tests from section 11 of the brief.
