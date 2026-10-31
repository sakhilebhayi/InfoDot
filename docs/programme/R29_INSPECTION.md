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
