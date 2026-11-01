# R-29 — Business identity, verification and anti-impersonation: inspection and phased plan

Brief: `requirements/infodot-business-identity-verification.md` (verbatim). Inspected 2026-10-31
before any code, per Part G §31.

## What exists today (build on this, not beside it)

| Need | Existing foundation | State |
|---|---|---|
| The "business" entity | Jetstream **Team** (`teams`: id, user_id, name, personal_team, uuid, 4-char `code`) + roles admin/manager/member/editor + R-13 `team_licences`/seats | Minimal — no business fields at all |
| Business activity | `questions`/`solutions` carry nullable `team_id` + visibility | Businesses already act on the platform |
| Media pipeline | R-15 `attachments`: polymorphic, private `local` disk, bind-on-submit, permission-checked streaming (`AttachmentController`/`AttachmentService`), thumbnails, `meta` json | Reusable for logo/cover/gallery; needs a public-read path for branding and a Team attachable |
| User avatars | Jetstream `profilePhotos()` enabled | Users only; nothing for teams |
| Reporting | `content_reports` (polymorphic `reportable`, reasons, open/actioned/dismissed) | Extend with Team as reportable + impersonation reasons |
| Investigation | `/moderation` queue (moderator flag), `moderation_actions`, `moderation_appeals`, audit trail | The investigation-centre backbone; verification desk extends it |
| Image processing | **No intervention/imagick in composer** — R-15 thumbs use GD | Perceptual hashing must be plain-PHP GD (aHash/dHash); honest and dependency-free |
| Registry integration | None. No CIPC API is connected | Document-based review only; never claim automated registry checks |
| Notifications/mail | `user_notices` + SMTP via no-reply@infodot.co.za (working) | Reuse for conflict alerts |

## Honest method set (what verification can actually mean here)

Supported now: document submission + manual reviewer decision; control of an email address at the
business's own website domain (code sent + confirmed, generic mailbox domains refused); applicant
authority by declaration + document + (for duplicates) confirmation by the existing verified owner.
NOT claimed: CIPC/registry API checks (nothing is connected), telephone verification (no SMS
provider), financial standing. The badge panel must say exactly this.

## Phases (one PR each, owner approval per merge/deploy)

1. **Business profile + media** — `team_profiles` 1:1 table (public business fields; registration
   number stored but never rendered raw publicly), Team media via attachments (`role` = logo |
   cover | gallery, history kept as superseded rows), upload/preview/crop (server-side GD crop from
   client coordinates), public business page `/business/{code}` in the Yard design, logo surfaced
   where team content shows, authorised-editor gates, tests.
2. **Verification** — `business_verifications` (status machine per §11) + evidence as private
   attachments + `verification_events` (append-only history), domain-email check, reviewer desk in
   /moderation (decider ≠ applicant's team member), badge + details panel everywhere team identity
   renders, suspend/revoke/expiry + identity-field change detection → re-review, tests incl.
   unauthorised status-change attempts.
3. **Protected identity + detection** — on approval snapshot `verified_identities` (normalised
   name, normalised registration number, domain, GD perceptual hashes of logo/cover); matching on
   team create/profile edit/media upload + name-variant detection (normalisation + distance),
   registration-number exact conflict, combined configurable risk score → `identity_conflicts`
   (low/medium/high/critical), badge blocked while unresolved, alerts to moderators + affected
   verified owner, relationship claims (branch/employee/franchise) confirmed by the verified owner,
   scenario tests 1–7.
4. **Reporting, investigation centre, appeals** — business report categories (Team reportable),
   verification & identity desk (side-by-side identity + image comparison, request-more-evidence,
   approve/reject/suspend/revoke/restrict, every action audited), appeal flow + internal-only
   statuses per §23 (nothing public says "fraud" before a decision), scenario tests 8–10, Part I
   report + register.

Decisions a phase must not pre-empt: none — the brief's "consider" items (gallery, branch
management) are in scope but trimmed to what the host supports.

## Phase 2 delivered — business verification

- **Applications** (`business_verifications`): the business's owner or an administrator applies
  from `/business/{uuid}/verification` with the registered and trading names, category
  (registered company, sole proprietor, other entity — no registration number required for the
  last two), registration number, country, website, phone, address, their role, a declaration
  of authority, and 1–6 evidence documents (PDF or image, through the R-15 attachment pipeline,
  bound privately with role `evidence`). Registration number, phone and address are encrypted
  at rest. One open application per business. ID documents are deliberately not requested.
- **Status machine** (§11): pending → under review → more information needed → verified /
  not approved; withdrawn; suspended, reinstated, revoked; lapsed (expiry); superseded (a
  renewal took over). Only `VerificationService` moves a status — it is never a form field —
  and every move takes a row lock and writes an append-only `verification_events` row (the
  model refuses updates and deletes).
- **Honest checks**: approval needs at least two confirmed checks, one of them always a review
  of registration or founding documents, and at least one document on file. "Official email"
  counts only after the code sent to an address at the business's own website domain was
  entered (free mailbox domains refused; 15-minute code, 5 attempts, hashed). Telephone and
  registry checks are not offered — nothing is connected — and the plate says so.
- **Reviewer desk** (`/moderation/verifications`, moderators): queue by state, the application
  beside the public profile with differences highlighted, documents (private streaming), checks,
  request information / approve / reject / reconfirm / suspend / reinstate / revoke, each with a
  message the business sees and an internal note it does not. A moderator who belongs to the
  business can read but every decision refuses them (§ decider ≠ member).
- **Badge** (`<x-business-badge>`, §12): the "approval plate" — a riveted VERIFIED plate beside
  the business name, shown only while verification is current. Its panel lists exactly the
  checks confirmed, the dates, the kind, and what verification does not guarantee. The rail's
  trust card (§29) says Verified / Under review / Not verified in words.
- **Monitoring** (§13): verification lasts 12 months (`VERIFICATION_VALID_MONTHS`); renewal
  opens 30 days before with a notice; `business:verifications` (daily 03:45) lapses expired
  ones. Changing the business name, trading name, registration number or country, or website
  takes the plate off ("under review") until a reviewer reconfirms.
- **Retention** (§25, POPIA): evidence of rejected, withdrawn, revoked, lapsed or superseded
  applications is deleted after `VERIFICATION_EVIDENCE_RETENTION_DAYS` (365), with the
  encrypted registration number, phone and address cleared; the decision history stays.
- **Registration number on the public page** is now masked for non-members (last three
  characters) — the Phase 1 page showed it whole, against §26 harvesting.
- **Notices**: businesses hear about every decision; reviewers about new applications, answers
  and identity changes (service notices, not silenced by the contributions switch).

Not built in Phase 2 (stated): automated CIPC/registry checks and SMS telephone checks (no
provider connected); malware scanning of evidence (no scanner on the host — reviewers are told
to open documents in the browser viewer); appeals against verification decisions (Phase 4);
duplicate/impersonation detection and protected identities (Phase 3); a business directory and
business-attributed answers do not exist yet, so the plate appears on the business page and the
company's own settings — the component is ready for those surfaces when they are built.

## Phase 3 delivered — protected identities and impersonation detection

- **Protected identities** (`verified_identities`, §14): on approval (and reconfirmation) a
  verified business's registered and trading names (normalised + a look-alike skeleton), its
  registration number **as a SHA-256 hash only**, country, website domain, confirmed email
  domain and 64-bit GD difference-hash fingerprints of its logo and cover become an active
  identity. Revocation and lapsing deactivate it. A verified business's new logo or cover is
  protected as soon as it is uploaded. The daily `business:verifications` sweep makes sure every
  currently verified business has one (covers businesses verified before Phase 3).
- **Matching** (§15–17): on business creation, rename, profile edits (identity fields and
  contact email), logo/cover uploads and verification applications, the account is compared with
  every active identity. Signals: registration number (60), exact name after normalising case,
  spacing, punctuation, accents, legal suffixes and common abbreviations (30), look-alike name —
  0/o, 1/l, rn/m… (30), minor spelling variation ≥ 0.85 similarity (25), same website (30),
  contact email at the verified domain (15), near-identical logo (25) and cover (15) within 8 of
  64 fingerprint bits, same country (5, only alongside another signal). Weights and thresholds
  live in `config/identity.php`.
- **Risk levels** (§18): low ≥ 25 recorded only; medium ≥ 40 withholds verification and tells
  reviewers; high ≥ 60 also tells the verified business; critical ≥ 80 likewise. Scenario 1
  (same name + number) scores 95 critical; scenario 7 (number reused) 65 high; scenario 2 (copied
  logo + cover) 40 medium; scenarios 3 and 6 (similar names) stay low or below.
- **Not an accusation** (§19): the verified business's own owner and administrators running
  another account are never flagged; confirmed relationships are never flagged; the account under
  review is not notified and its page says only "an extra check applies". Resolved pairs reopen
  only on a stronger match.
- **Relationships** (§19, §30): an account names a verified business by owner code and claims to
  be its branch, subsidiary, franchise, distributor or representative account; the verified
  business's owner or an administrator confirms or declines (members cannot). Confirming clears
  open conflicts between the two.
- **Reviewer desk** (`/moderation/identity`, §20): conflicts by level, the signals as a scored
  ledger, both businesses side by side with their logos and covers; dismiss (different
  businesses), record as related (with kind), or escalate for investigation (Phase 4). A reviewer
  in either business cannot decide. Registration numbers are never shown — only whether they match.
- **Alerts**: reviewers on medium and above; the verified business on high and above (its
  verification page lists identity alerts without exposing the other account's private details).

Not built in Phase 3 (stated): investigation outcomes, appeals and business reports (Phase 4);
phone-number matching (phones are not verified, so matching them would invent certainty);
scanning existing accounts in bulk (matching runs as accounts change; a backfill sweep is a
one-line follow-up if wanted); machine-learning image similarity (GD fingerprints only — no image
library on the host).
