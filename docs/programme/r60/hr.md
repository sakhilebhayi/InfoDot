# Dot.HR monetisation audit (read-only)

Source note: local checkout /Users/sakhilebhayi/Dot/Dot.HR is on `main` 94 commits behind origin/main (2026-11-14). Everything below is read from origin/main via `git archive` extracted to /private/tmp/claude-501/-Users-sakhilebhayi-Dot/e077f64e-f1ab-4cb2-b638-4c260e093867/scratchpad/r60/src/Dot.HR; paths are repo-relative. Local `wiki.md` and the stale local tree describe a small leave/employee MVP and are out of date. Labels: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: Workforce records for a business team: employees, positions, leave types and requests with approval and balances (`app/Models/Employee.php`, `LeaveRequest.php`, `app/Services/Hr/LeaveBalance.php`).
- EXISTING: Recruitment: vacancies, public job board and apply form, applications, interviews, offers, hire-from-offer, hiring costs (`routes/web.php` /jobs; `app/Services/Recruitment/*`).
- EXISTING: Employee relations and performance: cases (disciplinary), improvement plans, reviews, goals, 360 feedback, competencies, learning paths, training budgets, certifications.
- EXISTING: Time and workforce: shifts, shift swaps, attendance, timesheets, payroll INPUTS (not payroll calculation) with sign-off (`app/Services/Hr/PayrollInputs.php`, table `payroll_runs`).
- EXISTING: Leaving (resignation, exit interview, service certificate), employment documents with acknowledgement, personal data export (POPIA), audit log (`hr_audit_log`).

## 2. Most valuable features
- Leave management with balances and approvals (daily-use; time saved).
- Recruitment pipeline with public job board and skills match (`app/Services/Recruitment/SkillMatch.php`): measurable cost-per-hire (`hiring_costs`, `hiring_steps`).
- Payroll inputs export for an outside payroll provider (CSV, `/payroll/inputs.csv`) with approved hours and leave: direct saving. HR holds no pay, tax or ID numbers by design (`PayrollInputs.php` docblock).
- Employment documents + policy acknowledgement with encrypted storage (`app/Services/Hr/Documents.php`, `EncryptedFiles.php`): compliance evidence.
- Cases/improvement plans with timelines (legal defensibility under South African labour practice).
- Insights (headcount, turnover etc. computed only from own records) with CSV export (`app/Services/Intelligence/Insights.php`).
- Statutory retention automation (BCEA 3-year time records default) in `config/hr.php`, jobs `hr:retention`, `hr:purge-expired`.

## 3. Existing limits, quotas, plans, entitlements, billing
- EXISTING entitlement: `app/Http/Middleware/EnsureCompanyEntitled.php` + `app/Services/InfoDot/CompanyEntitlement.php` + `app/Support/Entitlement/Refusal.php`. Asks InfoDot for the company's platform list (`config/ecosystem.php` 'infodot' block: `companies_url`, `platform`=`hr`, `secret`), cached ten minutes; it reads only a boolean `entitled` (CompanyEntitlement.php ~line 111: `is_bool($row['entitled'])`) giving states HELD / NOT_HELD / UNKNOWN. UNKNOWN carries on (fail open). Switch `HR_ENTITLEMENT_CHECK` (`config/hr.php`). Access on/off only; no quantities.
- EXISTING rate limits (abuse only): `throttle:hr` 240/min per user (`AppServiceProvider.php:51`), route-level limits such as documents 20/min, job apply 20/min, CSV exports 20/min.
- EXISTING file caps: HR documents 10 MB (`HrDocument::MAX_BYTES`).
- EXISTING retention configuration (env): `HR_RETENTION_DAYS` 3y, candidates 365 d, time records 3y, notifications 180 d, case retention 5y (`config/hr.php`).
- No seats cap, employee cap, plan, subscription, credit or billing code in the repo. No AI calls anywhere in `app/` (no Anthropic/OpenAI references; SkillMatch and Insights are deterministic). NEEDS DEVELOPMENT for all commercial controls.

## 4. Resources consumed or stored and measurable counts
| Resource | Where it can be read today |
|---|---|
| Employees (headcount, active/terminated) | `employees` (team_id, status, employment_type; soft deleted) |
| Seats (users with logins) | `team_user`, `team_invitations`; `employees.user_id` link; `profile` self-service linking via `app/Listeners/LinkEmployeesOnLogin.php` |
| Leave requests | `leave_requests` |
| Candidates/applications | `vacancies`, `applications`, `application_events`, `interviews`, `offers`; public applications come from unauthenticated /jobs/{vacancy}/apply |
| Stored documents (bytes) | `hr_documents.size` (per file, per team); files encrypted on local private disk (`Storage` disk `local`, `Documents.php`, `EncryptedFiles.php`); contractor invoices via `EncryptedFiles` (`contractor_invoices`) |
| Payroll input runs | `payroll_runs` (`people`, `period_start`, `period_end`, signed_off_at) |
| Timesheets/attendance/shifts | `timesheets`, `attendance_records`, `shifts`, `shift_assignments` |
| Cases, plans, reviews, feedback rounds | `hr_cases`, `improvement_plans`, `reviews`, `feedback_rounds` |
| Audit events | `hr_audit_log` (append-only; team_id, actor) - good source for "actions" |
| Notifications to people | in-app `notifications` only (all four Notification classes use channel `database`); email/SMS leave via Dot.Notify events (queued job `app/Jobs/SendNotifyEvent.php`; counted nowhere locally) |
| Exports | CSV routes throttled, not logged (insights, training, payroll) |
| Analytics pushes | `app/Jobs/SendAnalyticsSnapshot.php` daily `hr:publish-metrics` |
| Background jobs | scheduled: purge, retention, publish-metrics, certification and engagement reminders, heartbeat (`routes/console.php`) |

## 5. Third-party services and cost drivers
- InfoDot (identity and entitlement), Dot.Notify (email/SMS delivery, cost sits there), Dot.Analytics (snapshots). Mail config present (`config/mail.php`).
- No external AI, SMS, e-signature, background-check or payroll API is called.
- Cost drivers: database size, encrypted document storage on the app host's private disk (NOT Dot.Files; `Storage` disk `local`), outbound notices via Notify, backups/restore drill (`VerifyRestore`).

## 6. Candidate purchase units (PROPOSED, no prices)
- Capacity: active employee records (headcount bands) is the natural measure for HR and is already a table count; stored document GB; number of open vacancies; payroll-input runs per month; recruitment candidates retained.
- Credit/transaction: notification credits (email/SMS to candidates or staff via Notify wallet); job board applications processed; AI features if later added (none exist: AI CV screening would need a deliberate, lawful design - NEEDS DEVELOPMENT, and POPIA automated-decision care).
- Feature bundles: recruitment, performance (reviews/360), employee relations (cases/plans), payroll inputs exports could be capacity-gated modules; low marginal cost, so price by headcount not by feature.
- Keep FREE: employee directory for a small headcount, leave requests and approvals, working-day calendar, personal data export and access for the data subject (POPIA right; must never be paywalled), audit log, existing document read access, statutory retention jobs, exit/leaving records, a small job board with a few open vacancies.
- Never gate: an employee's access to their own data, legal-hold of records, time records needed for BCEA compliance.
- On exhaustion (PROPOSED): hitting the headcount cap blocks only creating new employee records beyond the cap (or flags "over allocation" with a grace period); existing employees, leave, documents and exports keep working read/write; notification credit exhaustion falls back to in-app notices (already the default channel); storage exhaustion blocks new uploads only; never lock out people who need their own records.

## 7. Cross-platform dependencies and shareable resources
- EXISTING: InfoDot SSO and entitlement, Notify, Analytics (`app/Services/*`). `employees.user_id` links to InfoDot-provisioned users.
- Overlap with Dot.Tasks/Projects (timesheets) and Dot.Finance (payroll inputs handed to finance/outside provider); no code coupling found.
- Shareable (PROPOSED): comms credits (Notify), seats (an InfoDot-wide licence), storage (if documents move to Dot.Files; today they do not), AI credits (not used today).
- Platform-specific: headcount capacity, vacancy capacity, payroll-input runs.

## 8. Gaps (NEEDS DEVELOPMENT)
- Entitlement answer carries no quantities: extend InfoDot's `/companies/{uuid}/platforms` response and `CompanyEntitlement` beyond the boolean.
- Local usage ledger with idempotency (nothing exists): daily headcount snapshot (employees.status active) for capacity billing; documents bytes per team; notification counts; vacancies.
- Backend enforcement points: `EmployeeController@store`, `hr/Lifecycle` hire/`HireFromOffer`, `InviteTeamMember` action (seats), `Documents::put` / `EncryptedFiles::put` (storage), `VacancyController@store`, `JobBoardController@apply` (spam vs paid volume).
- Storage metering is local only: documents are on the app host's disk; moving to Dot.Files (like Projects does) would allow a shared storage allocation, but is a migration with encryption and SHA-256 integrity implications.
- Audit: reuse `hr_audit_log` for admin actions; add allocation/consumption/adjustment entries; protect sensitive data in usage records (no PII in the ledger: counts only).
- POPIA and labour-law review before any capacity lock that could impede statutory record access.
