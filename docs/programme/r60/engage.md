# Dot.Engage: monetisation fact audit (R-60, read-only)

Source basis: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Engage (tip a8a57bb, R-51 desk rebuild), extracted read-only with `git archive`. NOTE: the local checkout is on branch `chore/signed-in-vite`, 59 commits behind `origin/main`, and predates the R-39 rebuild (its `app/Services/DailyCoService.php` and old contracts/video code are gone on main). Paths are relative to the repo root on `origin/main`. Tags: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: a business engagement record (15 derived states) that holds parties, internal and outside participants, documents, discussion, negotiation, approvals, signing and meetings (`docs/programme/R39_READINESS.md`; tables `engagements`, `engagement_parties`, `engagement_participants`).
- EXISTING: outside parties need no account: emailed token, then 10-minute one-time code (`app/Services/Engage/ParticipantAccess.php`, `config/engage.php`).
- EXISTING: electronic signing with placed fields, sequential/parallel signers, drawn/typed/uploaded signature, Ed25519-sealed evidence JSON, optional RFC 3161 timestamp, public hash check at `/verify`, own PDF engine (`app/Services/Engage/SigningService.php`, `EnvelopeFinaliser.php`, `EvidenceSeal.php`, `app/Services/Pdf/*`).
- EXISTING: native WebRTC mesh meetings (max 6, waiting room, no recording) over Reverb signalling (`MeetingService.php`, `IceConfig.php`, tables `meetings`, `meeting_attendees`, `meeting_signals`).
- EXISTING: hash-chained, trigger-protected audit trail per engagement (`engagement_audit_events`, `AuditTrail.php`, `engage:verify-audit`).

## 2. Most valuable features (measurable business value)
- Signing to completion: replaces paper/courier; value = completed envelopes (`signing_envelopes.status = completed`, `all_signed_at`, `finalised_at`) and signers (`signing_signers.signed_at`).
- Evidence and verification: sealed evidence + certificate + public verify (`signing_envelopes.evidence_seal`, `final_sha256`, `certificate_path`). This is the defensible-record value, and the part with a long-term storage obligation (retention default 7 years, `ENGAGE_RETENTION_YEARS`).
- Negotiation to agreement: notes, proposed changes, amendments, approvals (`document_comments`, `amendments`, `engagement_approvals`, `engagements.agreed_at`).
- Meetings tied to a deal record (`meetings.engagement_id`, `started_at`, `ended_at`).
- Templates (`contract_templates` table, migration 2026_08_10_164113).

## 3. Existing limits, quotas, plans, entitlement, billing
- Entitlement: EXISTING boolean only. `app/Http/Middleware/EnsureCompanyHoldsPlatform.php` (alias `entitled`, registered `bootstrap/app.php`; applied `routes/web.php:57`, `routes/engage.php:45`) calls `app/Services/InfoDot/CompanyEntitlement.php`, GET `{infodot}/api/ecosystem/companies/{uuid}/platforms?platform=engage` with the service secret; states HELD / NOT_HELD / UNKNOWN; cached 600 s / 60 s; only a clear NOT_HELD refuses (fail-open). Two scopes: `entitled` (person's current company) and `entitled:workspace` on engagement/meeting routes (the owning business only; outside parties, signers and meeting guests are NEVER asked, their link keeps working, `routes/engage.php:55,72,92,108,109`). Toggle `ENGAGE_ENTITLEMENT_CHECK` (`config/ecosystem.php`). Company key = `teams.infodot_team_uuid` (migration 2026_10_18_000002). No allocation/quantity read.
- Limits EXISTING in `config/engage.php`: upload 20 MB per file (`max_upload_kb`, enforced `DocumentController.php:64,92` and `DocumentService.php:207`); meeting max 6 participants (`rtc.max_participants`), 8 h max (`rtc.max_hours`), auto-end after 10 empty minutes; signing default 14 days (clamped 1-90 in `SigningService.php:88`), max 5 reminders; invitation link 14 days; completed signed-copy access 90 days; throttles on invites (30/min), messages (60/min), meetings (20/min). Retention: a business holding a signed agreement cannot be deleted before 7 years (`app/Actions/Jetstream/DeleteTeam.php:20`).
- Not limited today: documents per engagement, engagements per company, envelopes, signers per envelope, total storage, invitations per company, meeting minutes.
- Plans/subscriptions/billing code: none (no Paystack, plan, invoice or credit code in app/, config/, routes/, database/).
- Operator-only: dependency patch proposals / advisory scan (`ScanDependencyAdvisories`, `ApplyDependencyPatchJob`), not a customer feature.

## 4. Resources consumed or stored and where they can be metered
| Resource | Meter source today | Status |
|---|---|---|
| Engagements | `engagements.team_id`, `state`, `created_at` | EXISTING (soft deletes) |
| Document storage | `contract_versions.size`, `contracts.file_size`, `sha256`, `page_count` (private disk); signed output `signing_envelopes.final_path`, `certificate_path`; signature images `signing_signers.signature_path`, `initials_path`. Final/certificate sizes are NOT stored as columns | EXISTING for uploads; NEEDS DEVELOPMENT for final PDFs/certs/signature images |
| Signing envelopes sent / completed | `signing_envelopes` (`sent_at`, `status`, `all_signed_at`) | EXISTING |
| Signers (signatures collected) | `signing_signers.signed_at`, `method`, `identity_method` | EXISTING |
| Timestamp-authority calls | `signing_envelopes.timestamp_authority`, `timestamp_token` (set only when `ENGAGE_TSA_URL` is configured) | EXISTING |
| Outside participants invited | `engagement_participants` (+ verified_at) | EXISTING |
| Emails sent (invites, OTP codes, signature requests, reminders, completion, meeting invites, messages) | No counter. Sent via Laravel notifications: `EngagementNotice`, `VerificationCodeNotification`, `NewMessageNotification` called from `ParticipantAccess.php:63,107`, `SigningService.php:242,253`, `EnvelopeFinaliser.php:177`, `MeetingService.php:80,85`, `EngagementService.php:130,224,233`. Approximated today only by `engagement_audit_events` | NEEDS DEVELOPMENT |
| Meeting minutes | `meetings.started_at`/`ended_at`; per-person `meeting_attendees.joined_at`/`left_at` | EXISTING (participant-minutes derivable) |
| Signalling load | `meeting_signals` rows (+ Reverb websocket connections) | EXISTING, volume only |
| Audit events | `engagement_audit_events` (append-only, `event`, `engagement_id`) | EXISTING |
| Messages | `engagement_messages` | EXISTING |
| Seats | Jetstream `team_user` | EXISTING |
| Platform analytics | None: no Analytics client or MetricsCollector in this repo (unlike Design/Pulse/Tutor) | NEEDS DEVELOPMENT |

## 5. Third-party services and likely cost drivers
- Email transport (`MAIL_*`, SMTP): the biggest per-transaction cost driver, because each signing request, reminder (up to 5), OTP and completion message is an email and outside parties are multiplied.
- STUN (default Cloudflare/Google public STUN, `config/engage.php`) is free; TURN relay (`ENGAGE_TURN_URLS`) is NOT configured and untested; when added it is a bandwidth-metered cost that scales with meeting minutes x participants (mesh up to 6 = heavy uplink on users, TURN relayed traffic on the owner).
- RFC 3161 timestamp authority (`ENGAGE_TSA_URL`, off until chosen; may be per-stamp priced).
- Laravel Reverb websocket server (self-hosted; CPU/connection bound).
- Disk storage for documents, signed copies, certificates, signatures (private disk; 7-year retention makes storage grow monotonically).
- Spatie media library is installed (`config/media-library.php`, `media` table) but document files use contract paths.
- No AI services in this repo (grep for anthropic/openai: none). Old Daily.co integration removed (`meetings.provider` column still allows `daily`, unused).
- No SMS, no accredited signing provider (the report states none integrated; signature level is `basic`, `signing_envelopes.signature_level`).

## 6. Candidate purchase units (PROPOSED; no prices)
- Transaction/credit: "completed signing envelope" (or "signature collected"). Reason: clear business value, already one row, includes the cost of PDF finalisation, evidence, certificate, reminder emails, and long-term storage; outside signers are never charged and never need an account.
- Credit: outbound communication (email now, SMS later) pooled with other platforms in a comms credit.
- Capacity: retained storage (GB) for completed records, since 7-year retention is a promise; and active engagements.
- Capacity: meeting participant-minutes only once TURN exists and costs money; before that meetings cost almost nothing (P2P), so keep them free within limits.
- Feature: advanced signature level, third-party timestamp (TSA) on each envelope, templates beyond N, certified/accredited signing provider if ever integrated.
- Keep FREE (PROPOSED): creating engagements, discussion, negotiation, approvals, inviting and verifying outside parties, joining as an outside party, signing as an outside party, public `/verify`, native meetings within the current 6-person/8 h limits, audit trail, read access and download of everything already completed, and a small monthly number of signing envelopes to make the platform genuinely usable for a micro business.
- On exhaustion (PROPOSED): refuse only the act that spends the credit (sending a NEW envelope; adding NEW storage), with a plain message and a top-up route. Never block: signing by already-invited signers on an envelope already sent (credit is reserved/consumed at send), downloading signed copies, `/verify`, meetings, or the 7-year retained records. An unpaid company's completed records must remain readable and verifiable; stored-bytes overage should block new uploads, not delete.

## 7. Cross-platform dependencies and shareable resources
- Depends on InfoDot (login, entitlement, membership via `app/Actions/Ecosystem/SyncTeamsFromInfoDot.php`). No calls today to Dot.Files, Dot.Notify, Dot.Analytics, Dot.Finance (readiness report: "Dot.Files: not used for storage"). `ecosystem.php` lists them only as registry URLs.
- Shareable ecosystem resources (PROPOSED): storage (Dot.Files pool, if documents move there; currently app-local, so Engage storage is platform-specific until then); comms credits (email/SMS via Dot.Notify would be the natural single meter); seats (InfoDot company licence).
- Platform-specific: signing envelope credits, evidence/timestamp credits, meeting TURN minutes.

## 8. Gaps to build (NEEDS DEVELOPMENT)
- Allocation read from InfoDot (balances/limits per company) in `CompanyEntitlement.php`; today it returns only held / not held.
- Company-keyed usage ledger written at the point of spend: envelope sent/completed, email dispatched, TSA call, bytes stored, with idempotency key and `team_id`/`infodot_team_uuid`. The audit table is hash-chained per engagement and must not be repurposed (its triggers refuse UPDATE/DELETE and it holds evidence), so billing needs a separate table.
- Enforce on the backend at `SigningService::send` (reserve one envelope credit before sending, release on cancel/expire if policy says so) and `DocumentService` upload (storage). Outside-party routes bypass entitlement by design, so the check must live in the service layer keyed to the owning team, not in middleware.
- Storage accounting: add size columns for final PDF, certificate and signature images, or a nightly measure of the private disk per `team_id`.
- Email metering: wrap notification sends (single `CommsMeter` decorator) so each recipient send is counted; OTP resends are abuse-prone (throttled 20/min but not capped per company).
- Platform analytics publisher (none exists), and a per-company usage report/audit for disputes.
- Decide fail policy: entitlement is fail-open; spending credits must be fail-closed or deferred-reconciled to avoid free usage during an InfoDot outage, while completing an in-flight signing must stay available.
- TURN/TSA are owner-pending and unmeasured, so their cost per unit cannot be estimated from code.
