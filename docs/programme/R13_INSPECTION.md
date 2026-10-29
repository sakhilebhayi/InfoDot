# R-13 inspection and plan (2026-10-29)

Brief: `requirements/infodot-tiers-seminars-licensing.md` (tiers, six-dimension reputation, seminars, company seat licensing). This file records what the source shows (verified from the repositories, not production), the decisions only the owner can make, and the phases. Nothing here was run against production data.

## 1. What exists today

| Area | Finding | Source |
|---|---|---|
| Companies | Jetstream teams. A company is a team that is not `personal_team`. Roles `admin` and `editor`. Add, update, remove and delete are owner-only. Membership changes are sent to platforms that copy membership. | InfoDot `JetstreamServiceProvider`, `TeamPolicy`, `SendMembershipChange` |
| Seats | **Did not exist.** There was no limit on invitations or members. | InfoDot `InviteTeamMember`, `AddTeamMember` |
| Payment | **Correction (2026-10-29): the first version of this row was wrong.** It was read from a Dot.Billing working tree that is behind `origin/main`. On `origin/main` Dot.Billing already has **Paystack**: hosted checkout (`PaystackClient::initializeTransaction`, `verifyTransaction`), a webhook that verifies `x-paystack-signature` (HMAC-SHA512 over the raw body, `hash_equals`) before parsing and settles `charge.success` against its own payment attempt by reference, holds unknown or mismatched events for an operator, and records `refund.processed` without applying it. It also has a subscription engine (plans with monthly and annual cents prices, trials, cancel at period end, queued plan changes, `past_due` with invoice grace dates, dunning left to a human) and a signed server-to-server `GET /api/entitlements/{company_uuid}` that other platforms use to ask what a company is on. **What it lacks for seats:** plans carry a `seat_limit` but there is no per-seat quantity or add-on item, and no webhook back to InfoDot. InfoDot's unused `laravel/cashier` should stay unused. | Dot.Billing `origin/main`: `PaystackWebhookController`, `Services/Payments/Paystack`, `Services/Billing/SubscriptionService`, `SubscriptionLifecycle`, `routes/api.php` |
| Achievements | `UserAchievement` with `rule_version` and `revoked_at`, built by `AchievementService` and `RefreshAchievements`. This is the base for specialist badges. | InfoDot (R-9) |
| Points ledger | Dot.Dopemine: append-only signed ledger, versioned reward rules, daily caps, self-award blocked, approver must differ from author, flagged content earns nothing, reversals. Event types are questions, answers, solutions, contributions and knowledge corrections. **There is no seminar, attendance, badge or tier event type, and no pair-decay or collusion rule anywhere.** | Dot.Dopemine `origin/main` docs |
| Memory | Dot.Memory participation API accepts only `accepted` and `verified` kinds with a topic and count, consent-gated, erasable. By design there is no "who knows topic X" and no ranking of people. No store for goals, learning journeys or seminar follow-up. | Dot.Memory `origin/main` `docs/participation-api.md` |
| Brain | **No tier, score, badge, seminar or licensing specification.** Policy is prohibitive: reputation may not set trust (`brain.community.md`), leaderboards and streaks are withheld or prohibited, and a feature needs a registered metric and an ethics-gate pass. Brain's proposal and ratification lifecycle (ADR-0024) covers brand artefacts only. No collusion or fraud detection exists. | Dot.Brain docs and ADRs |

Caveat: the Memory, Dopemine and Brain working trees were behind `origin/main` (25, 37 and 47 commits), so the contract details were read from `origin/main`.

## 2. What phase 1 built

Seat licensing, because it needs no payment provider to be correct and it is the part that can harm people if wrong.

- `team_licences` and `team_licence_events` tables (additive, guarded). A company with no row has the included seats, free.
- `config/licensing.php`: included seats (3), whether pending invitations hold a seat (yes), grace days, and a **price that is empty until the owner approves one**. Nothing is hardcoded and no currency amount exists in code.
- `SeatLedger`: the owner holds one seat and every member holds one; a pending invitation holds one. `guard()` runs the check and the change in one transaction with a row lock on the company, so two invitations at once cannot both take the last seat. Personal workspaces are not licensed. A company already over capacity keeps everyone; only new additions are blocked. The refusal says what is true and that nothing was charged and nobody removed. Refusals are audited even though the attempted change rolls back.
- Hooked into `InviteTeamMember` and `AddTeamMember` (the only two ways a person is added). Leaving or removal is audited and the person's account and content are untouched.
- `TeamPolicy::viewLicence` (owner and administrators) and `changeLicence` (owner). Reputation and tier play no part: nothing about a person's InfoDot standing can grant organisation permissions.
- A Seats panel on the team settings page for those who may see it, with an honest state: paid seats are "not available yet" until a price is set and billing is connected.

Not done in phase 1: paid seats, any checkout, webhooks, grace-period enforcement (the column exists, nothing sets it), invitation-cancel audit (Jetstream emits no event for it), owner/admin/manager/member vocabulary.

## 3. Owner decisions

Answered 2026-10-29:

1. **Payment provider: Paystack**, through Dot.Billing (not a second system in InfoDot). Billing already has the Paystack checkout, signature-verified webhook and subscription engine. The work is: a per-seat quantity on the subscription, the entitlement payload carrying `seats`, and InfoDot reading it (see phase 2).
2. **Seat price: decided by looking at competitors first.** Findings are in section 5. No figure is chosen here; it is a configuration value the owner approves.
3. **Roles move to owner, admin, manager, member.** Plan in section 6.
4. **Tier thresholds: owner asked for an explanation first.** Section 7 sets out the choice.
5. **Seminar video: build our own.** Assessment and the recommended shape in section 8.

Still open: billing period options and proration, grace policy after a failed payment (the Billing engine already has per-invoice grace dates), and the Information Officer/privacy wording for seminar attendance data.

## 4. Phases

1. **Seat licensing foundation** (this change).
2. **Dot.Billing payment layer** (owner decision 1): provider, signed webhooks, idempotency, subscription state; InfoDot writes `purchased_seats`, `billing_status` and `grace_until` only from a verified event, never from a checkout redirect.
3. **Reputation engine** on the R-9 ledger and achievements: six dimensions as versioned rules with caps and decay, an audit-able recalculation, appeals; specialist badges separate from tiers. Needs the Brain ADR first.
4. **Tiers** as configuration over the reputation score, with transparent progression.
5. **Seminars**: free first, then paid through Dot.Billing; events through Dopemine and Memory only once their contracts add the event types (they have none today).
6. Anti-manipulation signals (pair decay and the like) once the engine exists; none exists to reuse.

## 5. Competitor seat pricing (web search 2026-10-29, aggregator pages, US dollars, billed annually unless noted)

Treat as indicative: sources disagree and several are aggregator sites rather than vendor pages. No South African rand prices were found, so no rand figure is proposed.

| Product | Per user per month | Note |
|---|---|---|
| Zoho Projects | about $4 to $15 across tiers | cheapest of the group |
| Trello | $5 standard, $10 premium | |
| Slack | about $6.67 to $7.25 (Pro), $12.50 to $15 (Business+) | messaging, not a knowledge tool |
| Notion | $10 to $18 | knowledge workspace, nearest in kind |
| Asana | about $10.99 to $24.99 | |
| Microsoft 365 Business Basic | about $6 | suite |
| Google Workspace Starter | about $7 | suite |

Paystack costs on top for South African merchants, per its pricing page: local cards 2.9% plus R1, local EFT 2% (VAT exclusive); subscriptions through Paystack are card-based, to be confirmed against current docs before building.

Read: the field charges $4 to $15 per person per month. InfoDot is a knowledge and Q&A product with a free tier of three people, so the low end ($4 to $7 equivalent, converted to rand on the day) is the defensible anchor. The owner sets the number; the code reads `LICENCE_SEAT_PRICE_MINOR` and `LICENCE_CURRENCY`.

## 6. Role move (owner, admin, manager, member)

Today InfoDot has `admin` and `editor` and sends the role key to every platform on sign-in handoff (`EcosystemRedeemController`) and in the membership webhook (`SendMembershipChange`). Consumers map it themselves: Dot.docs turns owner or admin into admin, editor into editor and anything else into viewer; Dot.Sheet maps `editor` to `editor`. **An unknown new key would silently demote people to viewer in Dot.docs**, so the order matters:

1. Consumers first: each platform learns `manager` and `member` and keeps `editor` working. One small PR per platform, no behaviour change.
2. InfoDot adds `manager` and `member` and a `legacy_role` field next to `role` in the handoff and webhook, so a platform not yet updated still sees `editor` for a manager and for a member.
3. A data migration moves existing `editor` rows to `member` (nothing is removed; no one loses access), the Seats and People pages show the new names, and `editor` is kept as a read-only alias until every consumer is confirmed.
4. Meaning: owner (billing, delete, transfer), admin (people, roles, seats), manager (invites people, runs team spaces, no billing), member (uses InfoDot). Reputation or tier never grants any of them.

## 7. Tier thresholds: what the decision is

Tiers are the four names (Spark, Venture, Growth, Luminary) a person moves through, driven by a score made of six dimensions. A threshold is the score needed to enter the next tier. The decision is not the numbers; it is three things the numbers depend on:

1. **Who sees a tier.** Private (only you see your progress) or public (shown beside your name on answers). Brain's policy bans leaderboards and says reputation may never be used as proof that content is trustworthy, because it rewards volume and invites gaming. Recommendation: tiers are private progress; only specialist badges (topic knowledge earned from verified solutions) are public.
2. **What counts, and what can be gamed.** Learning, Contribution, Impact, Consistency, Expertise and Trust are only as honest as the events behind them. Today only questions, answers, accepted answers and verified solutions exist as signed events, with caps and self-award blocked. Consistency invites streak-chasing (Brain prohibits streaks as a target), and nothing detects two accounts boosting each other. Recommendation: weight Impact, Expertise and Trust (verified by someone else) heavily, keep Consistency small with no penalty for a gap, and add pair-decay before any tier has a visible reward.
3. **The numbers.** Starting values should be proposals the owner can change without code (they are configuration), tuned after a few weeks of real data. There is no real data yet, so any number picked now is a guess and is labelled so.

Brain's ethics gate needs a human-outcome target, a guard metric and an opt-out for this; that goes through a Brain ADR before a tier has a visible effect.

## 8. Own seminar video: honest assessment

Video cannot run on the current shared cPanel host: live media needs long-running processes, UDP ports and usually a TURN relay, none of which shared hosting allows. Building WebRTC media routing from scratch is months of specialised work and a security surface; it is not recommended. Two realistic shapes:

- **A. Our own room on our own server (recommended):** InfoDot owns everything a person sees (registration, join page, chat, Q and A, attendance, recording consent) and a self-hosted open-source media server (for example LiveKit or Janus) runs on a separate small server the owner provisions. We still control the data and the experience, and no third party provider is involved. Needs: a VPS with open UDP ports, a domain, and a hosting-location decision for POPIA (section 72).
- **B. One-way broadcast only:** the host streams to our server and the audience watches an HLS stream with text chat and Q and A. Cheaper and scales better, with 10 to 30 seconds of delay and no audience video.

Until one of these exists, seminars are listed, registered for, reminded and attended through a link the host supplies, and the page says so. No live video is claimed.

## 9. Status after the 2026-10-29 build session

| Piece | State |
|---|---|
| Seat licensing (3 free seats incl. owner, race-safe, audit, Seats panel) | merged (#139) |
| Roles owner/admin/manager/member (wire-compatible) | merged (#141) |
| InfoDot reads purchased seats from Dot.Billing's signed entitlements | merged (#142), off until configured |
| Free seminars (host, register, waitlist, join window, attendance, reminders, ratings) | PR #143 |
| Reputation engine, private progress page, appeals (off by default) | PR #144 (stacked on #143) |
| Dot.Billing extra-seat pricing, pro-rata invoice, settlement-gated entitlement | PR Dot.Billing#30 (set a price in the database to enable; no price, nothing is sold) |
| Brain ADR-0026 (reputation and tiers, proposed) | PR Dot.Brain#41 |
| Per-person reputation opt-out (deletes what is stored), own-data download, reciprocal-acceptance discount | built after the session above (PR on `feat/r13-reputation-optout`) |
| Specialist badges (public, opt-out `users.badges_visible`, per topic, beside answers and on the profile; `badges:backfill`) | PR `feat/r13-badges-rings-host-points`; levels are proposals in `config/achievements.php` `badges` |
| Advisory ring detection (`reputation:detect-rings`, `/moderation/rings`, score discount, never a ban) | same PR; thresholds are proposals in `config/reputation.php` `rings`. Cycles of 3 or 4 and tight mutual clusters of 3 to 8, last 180 days, moderators excluded |
| Dot Points for hosting (`seminars:award-hosts`, event `infodot.seminar.hosted`, off until `SEMINARS_POINTS_ENABLED=true`) | same PR; needs Dot.Dopemine PR #48 deployed first. Counts only attendees and ratings from outside every company the host owns or belongs to; one event per seminar, 24 h after it ended; reversed if the seminar is cancelled |
| Seminar video, Billing screens for buying seats | not built; see `R13_SEMINAR_VIDEO_PLAN.md` and ADR-0026 D1 to D4 |
