# R-13 inspection and plan (2026-10-29)

Brief: `requirements/infodot-tiers-seminars-licensing.md` (tiers, six-dimension reputation, seminars, company seat licensing). This file records what the source shows (verified from the repositories, not production), the decisions only the owner can make, and the phases. Nothing here was run against production data.

## 1. What exists today

| Area | Finding | Source |
|---|---|---|
| Companies | Jetstream teams. A company is a team that is not `personal_team`. Roles `admin` and `editor`. Add, update, remove and delete are owner-only. Membership changes are sent to platforms that copy membership. | InfoDot `JetstreamServiceProvider`, `TeamPolicy`, `SendMembershipChange` |
| Seats | **Did not exist.** There was no limit on invitations or members. | InfoDot `InviteTeamMember`, `AddTeamMember` |
| Payment | **Dot.Billing has no payment provider, API, webhooks or seat enforcement.** Its plans carry an unenforced `seat_limit`. The only Stripe pattern in the ecosystem is in Dot.Agents (signed webhook, event-id idempotency, checkout session action). InfoDot ships `laravel/cashier` and a `Billable` User that are unused and un-migrated. | Dot.Billing, Dot.Agents, InfoDot `composer.json` |
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

## 3. Decisions only the owner can make

1. **Payment provider and where it lives.** Dot.Billing has none. Options: build it in Dot.Billing (Paystack is already a dependency there and in Auction, Ehail, Mines) and have InfoDot call it, or reuse Dot.Agents' Stripe pattern. The brief forbids a second payment system inside InfoDot, so this is a Dot.Billing build first.
2. Seat price, currency, billing period (month or year), proration, and the grace policy after a failed payment. The code takes them from configuration and shows none until set.
3. Role vocabulary. The brief wants owner, admin, manager, member. InfoDot has admin and editor and other platforms read them through the membership webhook, so changing roles is an ecosystem change.
4. Tier thresholds and the weights of the six reputation dimensions; whether tiers may be shown publicly given Brain's ban on leaderboards and reputation-as-trust. Brain's ethics gate and likely an ADR come first.
5. A seminar video provider. Until one is chosen, seminars can be listed, registered for, and attended through a link the host supplies; no live video is claimed.

## 4. Phases

1. **Seat licensing foundation** (this change).
2. **Dot.Billing payment layer** (owner decision 1): provider, signed webhooks, idempotency, subscription state; InfoDot writes `purchased_seats`, `billing_status` and `grace_until` only from a verified event, never from a checkout redirect.
3. **Reputation engine** on the R-9 ledger and achievements: six dimensions as versioned rules with caps and decay, an audit-able recalculation, appeals; specialist badges separate from tiers. Needs the Brain ADR first.
4. **Tiers** as configuration over the reputation score, with transparent progression.
5. **Seminars**: free first, then paid through Dot.Billing; events through Dopemine and Memory only once their contracts add the event types (they have none today).
6. Anti-manipulation signals (pair decay and the like) once the engine exists; none exists to reuse.
