# R-12 inspection report: what the ecosystem already provides for a User Interaction Intelligence engine

Written 2026-10-27 from the source of the repositories (default branches; Dot.Analytics' default branch is
`feature/ecosystem-sso`, not `main`) and from the live host. **Nothing here was built or run; every statement names where it was
read.** The owner's brief is [requirements/dot-brain-ux-interaction-intelligence.md](requirements/dot-brain-ux-interaction-intelligence.md).
The brief says to inspect first, reuse, and not give a system a capability it does not have. This is that inspection.

## 1. What exists and can be reused

| Piece | What it really does | Where read | Fit for R-12 |
|---|---|---|---|
| **Intelligence loop (ADR-0015)** | A three-platform loop: Brain thinks, Memory stores, an executor acts. Contract `schemas/intelligence-loop.schema.json`. | Dot.Brain `services/intelligence/README.md`, ADR-0015 | **This is already the "closed loop" of the brief.** R-12 should extend it, not build a second one. |
| **Dot.Memory loop records** | `LoopRecord` with stages `observation, decision, action, outcome`; verdicts `improved, unchanged, worsened, inconclusive`; autonomy levels `observe, recommend, approve, execute, autonomous`; approval and execution statuses (`pending, succeeded, failed, refused, rolled_back`). API: `POST /api/intelligence/events, decisions, actions, outcomes`, `GET /context` (Sanctum token). | Dot.Memory `app/Models/LoopRecord.php`, `routes/api.php` | Matches the brief's verdict vocabulary and the "approval before execute" rule almost word for word. |
| **Memory ops incidents** | `POST/PATCH/GET /api/ops/incidents`, `GET /ops/recall`: incident records with updates. | Dot.Memory `routes/api.php`, `OpsIncidentController` | The nearest existing thing to a "work item". It is an incident log, **not a task tracker**; it cannot assign or execute work. |
| **Memory participation API** | Signed API: `PUT /participation/{userUuid}/consent`, `POST /participation/events`, `GET .../context`, `DELETE /participation/{userUuid}` (erase), `PruneParticipation` command. | Dot.Memory `routes/api.php`, `PruneParticipation.php` | **A working precedent for opt-in consent, per-user erase and retention pruning**, built for InfoDot community participation. The pattern to copy for interaction data. |
| **Dot.Dopemine interventions** | `POST /api/intelligence/interventions`: carries out a decision Brain made, but **only with a certified engagement mechanic** (a fixed enum of ethical categories; certification needs a recorded acid-test verdict). `MechanicOutcome` stores `engagement_movement` and `outcome_movement` per deployment period. | Dot.Dopemine `app/Http/Controllers/Intelligence/InterventionController.php`, `app/Models/MechanicOutcome.php`, README | Dopemine **acts on engagement mechanics and ethics-gates them; it does not measure UX fixes today.** Its rule "never optimise for screen time" matches the brief's "more clicks is not automatically good". Measuring before/after UX would be a **new** capability for Dopemine. |
| **Dot.Analytics metrics pipeline** | Platforms POST a signed **daily snapshot** (`POST /api/ingest/snapshots`, HMAC with a per-platform secret and a 5-minute timestamp window); Analytics stores it as received, computes `value, previous value, delta, delta %` per metric per day, raises anomaly alerts **only with at least 7 prior daily points**, marks data older than two periods stale. Contract: `docs/metrics-contract.md`, `config/metrics.php`. It also has `docs/compliance/gdpr-popia.md` and `docs/security/tenant-isolation.md`. | Dot.Analytics (branch `feature/ecosystem-sso`) `docs/metrics-contract.md`, `app/Http/Controllers/Api/IngestSnapshotController.php`, `app/Services/Metrics/` | **Closest existing "measure before and after" machinery** (deltas, anomaly detection, stale handling, tenant isolation). It ingests **aggregates, not events**. |
| **Dot.Notify event ingest** | `POST /api/ingest/events`, signed, idempotency key, rate limit per platform and company, 64 KB cap. | Dot.Notify `IngestEventController.php` | A good **shape** for a signed, idempotent, rate-limited ingest. Its purpose is notification delivery, not analytics. |
| **Brand Feeling Engine** | Analyses code **statically** (no runtime events): findings, `verify` (now pairs "restated" findings), BXQS, evidence. | Dot.Brain-main `services/brand-engine` | The R-11 base the brief extends. It measures what the code says, not what users do. |
| **Dot.Brain knowledge (DKP)** | Signed Knowledge Packs and the infodot-knowledge recommender (rules only). | Dot.Brain `brain.dkp.md`, `services/infodot-knowledge` | The place for "verified lessons", as the brief asks. |

## 2. What does not exist

1. **Any interaction-event pipeline.** No platform sends clicks, form failures, abandonment or timing. Analytics receives daily
   aggregates; Notify receives notification triggers; Dopemine receives contribution events for points. There is no shared
   front-end tracking package, and the front ends differ (Blade with Alpine, Livewire, Vue in Mines and Charts), so instrumentation
   points have to be chosen per product, as the brief says.
2. **A running service for Dot.Brain.** Dot.Brain is a specification repository plus Node CLI services run from a machine or GitHub
   Actions (ADR-0021; `brain.api.md` is a spec with no server). The brief's "dashboard within the Dot.Brain interface" and "Brain
   receives aggregated evidence" therefore have no runtime to live in. Running Laravel apps that could host a dashboard: Dot.Analytics
   (Livewire dashboards, metrics, AI insights), Dot.Central (control room), InfoDot.
3. **A UX issue registry**, a friction detector, or a before/after evaluation record. Analytics' anomaly detector and Memory's loop
   verdicts are the nearest parts.
4. **Task orchestration in Memory.** Memory stores and recalls; it does not create tasks or trigger remediation. The brief forbids
   assigning it undocumented capabilities, so the "coordinate" step needs an explicit integration with a service that can hold work
   (candidates: GitHub issues through the existing Guardian flow; a Memory ops incident as the record, linked to a GitHub issue).
5. **Consent and notice text for behavioural tracking**, a retention schedule, and a decision on what may be sent to AI models
   (all open owner decisions in `POPIA_READINESS.md` section 4).

## 3. Where the brief and the existing architecture disagree (owner decisions)

- **Who measures?** The brief says Dopemine compares before and after. In the code, Dopemine is the ethical engagement engine
  (it deploys certified mechanics and records movements); Analytics already computes deltas, previous values and anomalies. Options:
  (a) Analytics computes the metrics and the delta, Dopemine records the verdict against a deployed mechanic only when the change is
  an engagement mechanic; (b) extend Dopemine with a new "UX change evaluation" record, accepting that it is a new capability with its
  own ethics rule; (c) a new evaluation record in Memory's loop (verdict already exists) fed by Analytics. The brief names Dopemine; this
  report does not choose.
- **Where does the dashboard live?** Brain has no UI. Analytics or Central are the practical hosts; the brief says "within the existing
  Dot.Brains interface". Needs an explicit decision (ADR).
- **Events versus aggregates.** The existing contract is aggregate-first and privacy-friendly. Aggregating on the client or in the app
  server before sending (counts per route, workflow, step and outcome) fits "aggregate before AI", the 7-point anomaly rule and
  POPIA data minimisation, but cannot reconstruct a single user's path. Event-level storage (needed for sequences like loops and
  repeated attempts) is personal-data processing and needs the consent, retention and access decisions first.

## 4. Suggested first increment (no production collection)

1. Decide the three questions in section 3 (an ADR).
2. A versioned event taxonomy and JSON schema (minimum fields as in the brief), with a validator and a list of forbidden fields
   (form values, free text, tokens), tested.
3. A test-environment pipeline using clearly labelled synthetic events, a client-side beacon that cannot block a submit, and a
   server-side signed ingest modelled on Notify's (idempotency key, rate limit, size cap), tested for failure isolation.
4. A privacy design: consent via the Memory participation pattern, pseudonymous identifier, retention, erase, access audit.
5. Only then: InfoDot as the first product, behind a flag, collecting the minimum, with the owner's decisions recorded.

## 5. Risks to design out (they should become tests)

Tracking that slows a page or blocks a form; events carrying form values or document text; re-identification through rare
combinations of route, tenant and time; a score that rises because users click more; a "verified improvement" on too few sessions;
a fix recorded as deployed without a release reference.
