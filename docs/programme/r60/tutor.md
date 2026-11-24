# Dot.Tutor: monetisation fact audit (R-60, read-only)

Source basis: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Tutor (tip 58255c7, R-51), extracted read-only with `git archive`. NOTE: the local checkout (`main`, 186c1db, Aug 2026) is 59 commits behind `origin/main` and predates R-47 (it has 12 migrations and a handful of services; origin/main has 45 migrations and 15 domains under `app/Domain`). The checked-out files are NOT the deployed code. Paths are relative to the repo root on `origin/main`. Tags: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

Two meanings of "entitlement" exist here and must not be confused: (a) InfoDot company entitlement, "does this company hold Dot.Tutor" (`app/Services/InfoDot/EntitlementClient.php`, `app/Http/Middleware/EnsureCompanyEntitled.php`); (b) learner access to something they bought (`app/Domain/Money/Services/Entitlements.php`, table of the `Entitlement` model: a learner owns a lesson, course, resource or bundle).

## 1. What it does
- EXISTING: a South African tutoring marketplace and learning platform: tutors list offerings (1:1 lessons, group seats, packages, courses, materials, bundles, rooms/workshops), learners find them by explained matching and book/pay (`app/Domain/Marketplace`, `docs/R47_MARKETPLACE.md`, `docs/R47_MATCHING.md`; weights in `config/marketplace.php`).
- EXISTING: academic structure as data: CAPS/IEB Grades 1-12, higher education, subject rooms per level and audience (`app/Domain/Academic`, `app/Domain/Community`, `docs/R47_ACADEMIC.md`).
- EXISTING: learning: courses, library, question banks, assessments, flashcards (SM-2), certificates (`app/Domain/Learning`).
- EXISTING: live classroom: native WebRTC mesh (max 6), chat, whiteboard, notes, not recorded (`app/Domain/Classroom`, `config/classroom.php`).
- EXISTING: money and safety: Paystack orders, double-entry ledger, versioned commission, earnings, payouts, refunds, coupons, learner credit; guardian approval for minors, tutor verification (`app/Domain/Money`, `app/Domain/Safety`, `docs/R47_MONEY.md`). Optional AI learning assistant (off by default).

## 2. Most valuable features (measurable business value)
- Booked and completed lessons (`tutor_sessions` status; `app/Services/Scheduling/BookingService.php`), the core transaction, with completion lifecycle and ratings (`RatingService.php`).
- Paid orders: gross merchandise (order lines freeze price, commission bps, commission_minor, tutor net; `database/migrations/2026_11_10_500000_create_money_tables.php`). Gross-margin source is commission.
- Explained matching and storefronts (`/u/{handle}`) that bring a learner to the right tutor.
- Tutor business tooling: earnings, statements, payouts, course studio, coupons, bundles, insights.
- Course/library sales and enrolments (`learning_*` tables), certificates.
- Safety/verification (guardian approval, tutor verification levels) that make minors' tutoring possible; expensive to build, cheap to run.
- AI tutor assistant (compute-costly, optional).

## 3. Existing limits, quotas, plans, entitlement, billing
- InfoDot company entitlement: EXISTING boolean only. `EnsureCompanyEntitled` (alias `entitled`) is on the signed-in route groups (`routes/web.php:61`, `routes/tutor/{academic,ai,community,money,classroom,ops,comms,security,integration}.php`). It reads InfoDot's `/companies/{uuid}/platforms?platform=tutor` answer (cached 10 min / 60 s on failure) via `EntitlementClient.php`; only an explicit "no" returns a 403 refusal page (never redirects); everything unknown carries on. Nothing already made is touched: bookings, lessons and payments stay. Exempt: switching company, own account, terms, policy, data export/close. Webhooks, jobs, sign-in and public pages are not checked. Personal workspaces use the sign-in answer stored in session `infodot.personal_entitled`. No quantities read.
- BILLING CODE EXISTS here, unlike the other three platforms: a full Paystack integration and ledger (`app/Domain/Money/**`, `config/money.php`): 15% default platform commission (`MONEY_COMMISSION_PERCENT`, versioned per-category rules in `money_commission_rules`, `percent_bps`, `effective_from`), optional pass-through of gateway fee, fixed fee, refund windows 7 days, 24 h lesson hold, payout minimum R100 (10000 cents), monthly payout day, Paystack Transfers off, coupons, learner credit (ledger account `learner_credit:{user}`, credit grants by finance), receipts with optional VAT, manual CSV payout batches. Live Paystack keys are refused unless `PAYSTACK_ALLOW_LIVE=true`; nothing has been run against live Paystack (`docs/R47_REPORT.md`). Recurring subscriptions deliberately NOT built (`docs/R47_MONEY.md` D-511). Owner's strategic note: no paid placement, no paying to message (`docs/R47_RESEARCH.md`).
- Other limits EXISTING: offering duration 15-240 min; group capacity max 30; package max 40 lessons; recurring max 26 weeks; unpaid order holds 60 min; courses upload max 20 MB (`LEARNING_MAX_UPLOAD_KB`), video 200 MB (`LEARNING_MAX_VIDEO_KB`), classroom file 10 MB; whiteboard 20 pages; classroom max 6 in room; storefront/evidence uploads 5-8 MB; throttles on matching, reviews, uploads.
- AI limits EXISTING (`config/ai.php`, `app/Domain/Ai/Guardrails/UsageLimits.php`): off unless `AI_ENABLED` and key; per minute 6; per learner per day by age band child 15 requests / US$0.25, teen 30 / $0.50, adult 50 / $0.75; tutor 60 / $1.50; platform monthly budget US$100 (`AI_MONTHLY_BUDGET_USD`), refusal code `monthly_budget`; price table in code for cost accounting; model default `claude-opus-5-5`. Platform-wide, not per company.
- Free items already supported: `price_minor = 0` becomes entitlement source `free` (`Entitlements::grantForLine`), so free lessons, courses and library items work without a payment.

## 4. Resources consumed or stored and where they can be metered
| Resource | Meter source today | Status |
|---|---|---|
| Lessons booked / completed | `tutor_sessions` (status lifecycle) and marketplace tables; `MetricsCollector` counts `sessions_7d`, `completed_7d`, `active_tutors` per company of the TUTOR's current team (`app/Services/Analytics/MetricsCollector.php`) | EXISTING |
| Commission earned | order lines `commission_minor`, `commission_bps`, ledger account `platform_commission`; `docs/R47_MONEY.md` s4 | EXISTING |
| GMV, payments, refunds | `money_*` tables (`Order`, `OrderLine`, `Payment`, `Refund`, `Earning`, `Payout`), ledger journals, `ops/reconciliation` and `ops/trial-balance` endpoints | EXISTING |
| AI calls, tokens, cost | `ai_interactions` (`input_tokens`, `output_tokens`, `cost_micro_usd`, `reached_model`, `actor_role`, `age_band`, `task`, `user_id`); retention 180 days (`AI_LOG_RETENTION_DAYS`) | EXISTING; per user, no company/team column |
| Course/library storage | `learning_*` course tables (`media_size`, `size`), library `size`, qualification evidence `size_bytes` (`safety_tables` migration line ~169, `tutor_qualifications` ~44); private disk `learning.disk_root` | EXISTING per row; no per-company roll-up |
| Notifications by channel | `comms_deliveries` (`channel` in_app/push/email/sms/notify, `status`, `provider`, `sent_at`, `user_id`, `category`, `type`) | EXISTING (best metering of any of the four apps) |
| SMS | `SmsSender` interface bound to `NullSmsSender`; no provider built (D-1006), every SMS copy is recorded as unavailable | NEEDS DEVELOPMENT |
| Classroom minutes | `classroom_attendance` (`AttendanceSpan`), `classroom_attendees`; `classroom_signals`, `classroom_board_ops` rows | EXISTING (minutes derivable; relay bandwidth not) |
| Seats / group places | `SeatPurchasable`, group capacity | EXISTING |
| Learner credit balance | ledger `learner_credit:{user}` (`CreditGrant` model) | EXISTING, closest thing to a wallet; per learner, ZAR |
| Seats of company (team members) | Jetstream `team_user` | EXISTING |

## 5. Third-party services and likely cost drivers
- Paystack (card/bank payments): transaction fee (a platform cost by default, absorbed unless `MONEY_PASS_GATEWAY_FEE_PERCENT` set; refunds do not return Paystack's original fee, D-508), optional Transfers for payouts (needs Registered Business).
- Anthropic Messages API (`config/ai.php`): the main variable compute cost; opus-class model by default (priced in code at 400/2000 US cents per million tokens in/out for `claude-opus-5-5`); capped at US$100 per month platform-wide by default.
- Email (`MAIL_*`), Web Push (VAPID, free), optional SMS (no driver), Dot.Notify mirror for six events (`config/notify.php`, off without URL and secret).
- STUN public servers; TURN relay optional and unconfigured (`CLASSROOM_TURN_*`), bandwidth-metered if added.
- Storage on the shared host's private disk (video up to 200 MB per lesson file; no virus scanner, per `config/learning.php`).
- InfoDot (identity, entitlement), Dot.Analytics (daily snapshot). External video hosts (YouTube, Vimeo) for course lessons carry their own hosting cost off-platform.

## 6. Candidate purchase units (PROPOSED; no prices)
- Transaction (already the model): marketplace commission on paid orders. It matches "charge for outcomes, not access" and is already versioned per category. Open question for the owner is only whether to keep a take rate and add capacity packs for tutors, not whether to start charging.
- Credit: AI assistant requests for tutors (they cost the most; a tutor plan limit of 60/day is already encoded), pooled with the ecosystem AI credit; learners keep a small free allowance (existing age-banded caps are a ready-made free tier shape).
- Capacity (tutor business): storage for courses/library, number of published courses/offerings, group-lesson capacity beyond a free count, team/organisation tutor seats (tutoring centres or schools buying licences).
- Credit (comms): SMS/email reminder credits for tutors who message their learners (once an SMS driver exists).
- Feature: tutor verification levels, storefront analytics, bulk invoicing/exports.
- Keep FREE (PROPOSED): learner onboarding, matching and search, storefront browse, free lessons/courses/library items, subject rooms and Q&A community, study groups, the classroom for booked lessons, all safeguarding (guardian approval, reporting, verification screening of tutors who work with minors), POPIA export/close, refunds and disputes, receipts. Safety must never be paywalled.
- Do NOT sell: ranking position or paid placement (explicitly rejected in `docs/R47_RESEARCH.md`), message access (the Superprof pattern the owner avoided), AI to minors beyond safeguarded caps.
- On exhaustion (PROPOSED): AI: refuse with the existing `Refusal` mechanism; deterministic features keep working. Storage: block new uploads but keep serving and selling existing items; courses with enrolled learners must stay accessible. Never revoke a learner's purchased access (`Entitlement`) because a tutor's own allocation ran out. Seat/credit gaps never cancel a confirmed lesson or hold earnings.

## 7. Cross-platform dependencies and shareable resources
- Depends on InfoDot (sign-in, company entitlement, team sync) and Dot.Analytics. Optional Dot.Notify mirror (`config/notify.php`: `tutor.lesson.confirmed`, `tutor.payment.received`, `tutor.payout.paid` etc., adult recipients only). No code integration with Dot.Files (storage is local), Dot.Finance (Tutor has its own parallel ledger), Dot.HR or Dot.Engage.
- Shareable (PROPOSED): AI credits (one ecosystem pool; `ai_interactions.cost_micro_usd` is already in the unit a wallet needs, but pricing in USD micro vs ZAR credit needs conversion); storage (move course/library media to Dot.Files); comms credits (via Dot.Notify); seats (InfoDot company licence for tutoring organisations).
- Platform-specific: commission take rate and payout rules (Tutor's own ledger); classroom TURN minutes; safeguarding capacity.
- Overlap to flag for the owner: Tutor's ledger (`app/Domain/Money/Ledger`) duplicates what Dot.Finance does and would be the second wallet to reconcile if a shared wallet is introduced; learner credit (`learner_credit:{user}`) is a ready model for a ZAR wallet but is private to this app.

## 8. Gaps to build (NEEDS DEVELOPMENT)
- InfoDot allocation read (limits/balances per company) beyond `entitled`; today the company concept is thin for Tutor because users and tutors are individuals, and analytics only attribute sessions to a tutor's current non-personal team (`MetricsCollector` docblock); "company" must be defined (tutoring organisation vs individual tutor).
- Company/team attribution on AI usage: add team key or resolve from user at write time; `ai_interactions` has only `user_id`, and it is pruned after 180 days (`purge_after`), so a durable usage ledger is needed for billing evidence.
- Unified usage ledger with idempotency for AI, storage, SMS/email, classroom relay; storage roll-up per owner (sum of `learning_*` sizes plus evidence).
- Enforce on the backend inside `AiGateway` (it already pre-checks `UsageLimits` per user and platform; add a per-company/wallet check) and in the course/library upload paths.
- SMS provider driver (interface exists, no implementation) and per-channel counters from `comms_deliveries`.
- Live Paystack verification (never done), VAT and operator settings (owner items), recurring subscriptions (decided out), payout cadence/legal review.
- Audit: money side already has journals and `assertLedgerHealthy`; allocation grants/consumption for non-cash resources (AI credits, storage) need the same append-only treatment and a reconciliation report.
- Decide wallet reconciliation with Dot.Finance and InfoDot to avoid two sources of truth for ZAR balances.
