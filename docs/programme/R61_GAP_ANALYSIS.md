# R-61 gap analysis: the control centre brief against what InfoDot already has

Date: 2026-11-24. Status: read-only analysis of the InfoDot repository. Nothing is built, nothing is proposed as decided. The brief is `R61_CONTROL_CENTRE_BRIEF.md`.

## 1. How this brief relates to the others

- **R-60 (monetisation strategy):** the R-61 sections 1 to 4, 10 and phase 1 are the same work as R-60. The platform audit (`R60_M0_AUDIT.md`) is the Phase 1 audit of this brief. The strategy document (R-60 M2) still needs the owner's cost figures; R-61 says the pricing decisions are left to me, but without cost inputs I cannot justify a price, so that step still waits.
- **R-50 (platform admin centre, built and live):** most of the "Dot Ecosystem Control Centre" already exists at `/admin/platforms`. Section 3 lists what.
- **R-59 (growth):** unrelated except that the R98 seat stays a licence batch and the wallet idea in R-60 M3 is what R-61 phase 3 calls the entitlement engine.

So R-60 and R-61 should be run as one programme: R-60 supplies the strategy and wallet design, R-61 adds the control and enforcement layer on top of R-50.

## 2. The most important finding: switching a platform OFF is not enforced inside the platforms

Verified in this repository:

- InfoDot already has a per-platform operational status, set by a platform administrator with a reason and an audit entry: `operational`, `degraded`, `maintenance`, `unavailable`, `development`, `not_launched` (`app/Models/PlatformCatalogue.php`, `app/Services/Platforms/CatalogueAdmin.php`).
- InfoDot's own access decision honours it: a platform that is not usable gives `temporarily_unavailable` or `not_launched` when someone tries to open it through InfoDot (`PlatformAccess::decideFor`, `businessState`).
- But the call a platform makes to ask "is this company allowed?" (`GET /api/ecosystem/companies/{uuid}/platforms`, `CompanyPlatformsController`) returns `entitled` from `companyHolds()`, and `companyHolds()` ignores the operational status. It returns `status: temporarily_unavailable` in a separate field, but the R-60 audit reports that platforms read only the boolean `entitled`, cache it for about ten minutes, and fail open on any error.

So today, putting a platform in maintenance in InfoDot stops people opening it from InfoDot, but does not stop a signed-in user, a bookmarked page or the platform's own API from working. Requirement 6A ("its API endpoints and backend operations must enforce the disabled state") is not met, and requirement 13.9 cannot be met without changing how every platform asks.

Also reported by the R-60 auditors (not re-verified by me): Dot.Mines has no InfoDot link at all, Dot.Charts has no InfoDot entitlement, Notify's ingest and Files' API skip the entitlement check, and Engage's outside signers bypass it by design. These would not honour an OFF switch either.

## 3. What already exists in InfoDot (verified in this repository)

| Brief requirement | What exists |
|---|---|
| Platform-level control | Per-platform operational status with six states, edited by platform admins with a mandatory reason, audited (`CatalogueAdmin::updatePlatform`, `platform_catalogue_events`). Platform admin gate `administer-platforms`, host-only role grants, now with audited moderator commands too |
| Control Centre pages | `/admin/platforms`: Catalogue, Bundles, Companies (grant, trial, suspend, restore, cancel, access), Sign-in branding, Infrastructure, Deliveries (entitlement outbox), Confirmed payments, Growth Centre, Audit trail |
| Pricing control | Plans per platform (model: free, included, subscription, per user, usage, enterprise; amount, currency, interval, trial days, tax treatment), approval and withdrawal workflow, price change withdraws approval, bundles of platforms, an audit trail |
| Free activation | `offer()` returns `free`, `unpriced_free` or `paid`; with no approved price a platform is free to activate. Core platforms are included |
| Entitlements | `platform_entitlements` with states (pending, active, trial, payment required, expired, suspended, cancelled) and sources; per-person access mode; a retried outbox that tells platforms about changes; payment confirmations only from Finance |
| Business-facing states | `businessState()` already distinguishes not launched, temporarily unavailable, payment required, trial ended, suspended and more, and the access page words each |
| Audit | Catalogue audit events with before, after, reason and actor |

## 4. Gaps against the brief

| Brief section | Gap | Size |
|---|---|---|
| 6A platform ON/OFF enforced on backend | See section 2. Needs a contract where the answer carries availability, and a shared client or middleware in every platform that blocks and shows a maintenance message. Needs a defined fail-safe policy (below) | Large, touches every repository |
| 6A and 8 states | Existing states cover active, degraded, maintenance, unavailable, development and not launched. The brief also wants "retired" and "temporarily disabled". `unavailable` may stand for the latter; `retired` is new | Small |
| 6B feature-level ON/OFF | Nothing exists. `platform_plans.features` and `limits` are JSON columns that no platform reads. Needs a feature registry (platform, key, name, state, free or paid), an admin screen, an API field per feature, and a check in each platform for each feature | Large |
| 6C pricing: effective dates | Not present. A price change withdraws approval; there is no "takes effect on" date and no versioning, so a renewing customer's price is not protected by design | Medium |
| 6C promotions, introductory offers, regional pricing, free allowances | Not present. ZAR only in billing | Medium to large |
| 5 entitlement engine with usage | The answer is boolean. No numeric allowance, no usage counter, no reservation | Large (this is R-60 M3, the wallet) |
| 5 refunds and reversals | Seat licences have refund and credit note (Finance). Nothing general | Medium |
| 7 dashboard figures | Active users, paying users, revenue and feature usage per platform are not shown. Revenue can only come from Finance-confirmed payments; usage only where platforms record it (R-60 audit: uneven) | Medium |
| 7 and 6 confirmation dialogs | Disruptive changes use a reason field; no consequence-explaining confirmation step | Small |
| 9 locked-feature experience | InfoDot has access pages; platforms have no shared locked-feature component | Medium, per platform |
| 11 safe behaviour if InfoDot is down | Platforms fail open today (good for availability, bad for OFF). No last-known-good store or defined policy | Design needed |
| 11 propagation across instances | Entitlement changes go out through an outbox; no equivalent for status or feature changes | Medium |

## 5. Design direction (PROPOSED, for review, not decided)

1. **One availability answer.** Extend the platform's question to return `available` (bool), `state` (the status), `message`, and a `features` map (key to `on`, `off`, `needs_purchase`), plus later numeric allowances. `entitled` is kept for compatibility.
2. **One enforcement client.** A small shared package or copied middleware used by every platform: asks InfoDot, keeps a last-known-good copy with a short expiry, blocks and shows the right message for each of the four reasons in section 9 of the brief (not purchased, feature off, platform in maintenance, no permission).
3. **Fail-safe policy.** If InfoDot cannot be reached: use the last-known-good answer; if the last answer said OFF, stay OFF (so maintenance holds); if there is no answer at all, fail open for existing customers but never grant a paid feature without a recorded entitlement. This keeps one InfoDot outage from taking all platforms down.
4. **Push as well as pull.** Status and feature changes go through the same outbox mechanism as entitlements so platforms drop their cache immediately.
5. **Feature registry in InfoDot** (platform, feature key, kind free, paid or resource, state, linked plan, audit events), edited in the Control Centre with a reason, the same way platform status is today.
6. **Effective-dated price versions** instead of editing a plan in place, so renewals and existing purchases keep their agreed price until the renewal policy says otherwise.
7. **Rollout order that does not need prices:** the availability contract and enforcement client first, tried on one or two platforms (Dot.Tasks and Dot.HR are good candidates: both already have an entitlement middleware), then the feature registry, then pricing versions and the wallet (R-60 M3), then revenue intelligence.

## 6. Where the brief and earlier decisions need the owner

1. **"Main InfoDot account" as the authority.** I read it as the platform administrator role, so several people can hold it, as built in R-50. If you mean one single account, that is a different design. (You also just asked for one account to hold everything; the Control Centre would follow whatever you decide here.)
2. **Dot.Mines.** The brief's example table lists Mines as switchable, but says not to change its commercial model. Mines has no InfoDot link today. I read this as: control it from InfoDot (ON/OFF and maintenance only) without touching its plans, pricing or billing. Please confirm.
3. **Free activation versus the R98 seat.** Activating a platform stays free (already true when no price is approved). The R98 per-user price is for additional people beyond the free seat, not for activating a platform. Please confirm that reading.
4. **The retired and temporarily disabled states.** Use the existing `unavailable` as "temporarily disabled" and add `retired`, or keep exactly the five states in the brief.
5. **Cost inputs and a margin (R-60 M2)** are still needed for any price, and the annual price at R98 is still open.
6. **Priority.** The enforcement client touches every platform repository. Which first: the availability contract and enforcement (needs no prices), or the strategy and pricing (needs your cost figures)?

## 7. Not verified

- Platform behaviour is from the R-60 auditors' reports on each repository's `origin/main`, not re-checked by me, except what is stated as verified in section 2 and 3.
- No platform was tested live; no switch was flipped.
- Production data (which platforms are currently marked what, how many entitlements exist) was not read.
