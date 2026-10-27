# Requirement: Dot ecosystem User Interaction Intelligence and automated UX improvement engine

Source: owner brief of 2026-10-27 ("ADDITIONAL MODULE", sections 12 to 21), stored here verbatim so it is built from the
owner's words (the brief says "Dot.Brains"; the repository is `Dot.Brain`). It extends the Brand Feeling Engine brief
([dot-brain-brand-feeling-engine.md](dot-brain-brand-feeling-engine.md), R-11) and joins the programme backlog as R-12
(see [REMEDIATION_ROADMAP.md](../REMEDIATION_ROADMAP.md)). **Status: not started.** First step when it starts: inspect
what already exists in Dot.Brain, Dot.Memory, Dot.Dopemine and Dot.Analytics (event ingest, snapshots) before designing
anything; reuse, do not duplicate. The notes added by the programme (not part of the owner's brief) are in the roadmap
under R-12.

---

ADDITIONAL MODULE: DOT ECOSYSTEM USER INTERACTION INTELLIGENCE & AUTOMATED UX IMPROVEMENT ENGINE
12. Mission — Make the Entire Ecosystem Learn From Every User Experience
Extend the existing Dot.Brains Brand Feeling Engine by implementing a native User Interaction Intelligence capability across InfoDot and the entire Dot ecosystem.
This must be an addition to the existing Dot.Brains architecture, not a standalone analytics product or a replacement for existing tracking, analytics, memory, or engagement functionality.
The objective is to understand how people actually use our products, identify where they struggle, establish why those problems occur, and continuously improve their experience using evidence rather than assumptions.
We must be able to understand:

* What users click, select, open, submit, dismiss, and interact with.
* Which workflows users start, complete, abandon, or repeatedly attempt.
* Where users hesitate, encounter errors, repeat actions, or become stuck.
* Which interface elements are confusing, difficult to discover, or misleading.
* Which steps cause unnecessary effort, delays, or failed outcomes.
* Whether a problem affects one user, a particular user group, a tenant, a product, or the wider ecosystem.
* Whether an implemented improvement actually resolves the original problem.
* Whether the improvement introduces new problems elsewhere.

The ultimate objective is to make InfoDot and the Dot ecosystem progressively easier, more intuitive, more useful, and more valuable every time we learn something meaningful about the user experience.
Every significant interaction should have the potential to produce useful knowledge. Every verified UX flaw should create an opportunity for improvement. Every implemented fix should be measured against the experience that existed before it.
13. Establish the User Interaction Intelligence Architecture
Implement this capability within the existing ecosystem architecture, following the actual conventions and integrations available in the repositories.
A. Interaction Tracking Layer
Create a lightweight, reusable interaction-tracking capability that can be integrated into InfoDot and the individual Dot platforms.
Capture meaningful interaction events, including:

* Page and application navigation.
* Button and actionable element clicks.
* Form starts, validation failures, submissions, and completion.
* Search queries and unsuccessful search outcomes, subject to privacy rules.
* Feature discovery and usage.
* Workflow progression and abandonment.
* Repeated clicks or submissions that may indicate confusion.
* Navigation loops and repeated returns to the same step.
* Loading delays, timeouts, and failed requests.
* Application errors and failed actions.
* Modal, menu, and contextual help interactions.
* Successful completion of important user goals.
* Recovery after an error or failed attempt.

Do not indiscriminately record every possible event. Define a standard event taxonomy and prioritise events that help explain user outcomes.
Every event should contain only the minimum necessary information, using an appropriate schema such as:

* Event identifier.
* Timestamp.
* Tenant identifier, where applicable and authorised.
* Product and application identifier.
* Pseudonymous user or session identifier, where appropriate.
* Event type and stable interaction name.
* Page, route, or workflow identifier.
* Workflow step and sequence information.
* Success, failure, or abandonment status.
* Relevant performance measurements.
* Error category or diagnostic reference, where applicable.
* Instrumentation and schema version.
* Consent and applicable processing context.

Do not record passwords, authentication tokens, payment details, private message contents, document contents, or other sensitive information in interaction events.
Do not assume that a click explains user intent. Treat interaction events as observable behaviour that may require additional evidence to interpret.
B. Friction and Stuck-Point Detection Engine
Create a system that identifies probable UX problems by analysing interaction patterns and application outcomes.
Detect signals such as:

1. Repeated clicks on the same control without a successful result.
2. Repeated form validation failures.
3. Users repeatedly moving between two or more screens without completing their goal.
4. Users abandoning a workflow at a particular step.
5. Unexpectedly long completion times compared with an appropriate baseline.
6. Failed searches followed by repeated query changes.
7. High error rates associated with particular components or workflows.
8. Users repeatedly opening and dismissing help without completing the task.
9. Actions that appear successful but are followed by repeated attempts.
10. Significant differences in completion rates between comparable user groups or product versions.
11. Slow interfaces or failed network requests that prevent progress.
12. A sudden increase in friction after a release or interface change.

Establish configurable detection rules and statistically appropriate thresholds.
Distinguish between a confirmed technical defect, a probable usability issue, an unusual interaction pattern, and an unverified hypothesis.
Do not conclude that a user is stuck solely because they spend a long time on a screen. The user may be reading, thinking, multitasking, or intentionally pausing.
Where the system cannot confidently explain the cause, it must identify the evidence needed to investigate rather than inventing a reason.
C. UX Issue Registry
Create a persistent, versioned registry of detected and investigated UX issues.
Each issue should contain:

* Issue identifier and affected product.
* Relevant page, component, and workflow step.
* Description of the observed problem.
* Supporting event patterns and diagnostic evidence.
* Estimated number of affected sessions and users.
* Frequency, severity, and business impact.
* Confidence in the diagnosis.
* Known root cause, if established.
* Proposed remediation.
* Implementation status.
* Linked code change or release, where available.
* Before-and-after evaluation results.
* Regression risks and outstanding uncertainties.

Group repeated observations into common issues where appropriate instead of creating a new issue for every affected session.
Prioritise issues using severity, frequency, user impact, business importance, confidence, and remediation cost.
The registry must help Dot.Brains identify systemic weaknesses instead of merely generating a large list of individual events.
14. Define Responsibilities Across Dot.Brains, Dot.Memory, and Dot.Dopemine
The three systems must cooperate through explicit ownership and supported integration contracts.
A. Dot.Brains — Understand, Diagnose, and Learn
Dot.Brains is the central intelligence and decision-making authority.
It must receive aggregated interaction evidence, diagnosed friction patterns, issue reports, and evaluation results.
For every significant UX issue, Dot.Brains must:

1. Identify the affected user journey.
2. Analyse the interaction sequence and relevant technical diagnostics.
3. Compare the evidence against the intended product experience and brand principles.
4. Investigate likely root causes.
5. Determine whether the problem is isolated or systemic.
6. Generate and prioritise remediation proposals.
7. Define what a successful fix would look like.
8. Establish the baseline metrics and evaluation method.
9. Record verified lessons using the existing knowledge governance process.
10. Reuse validated solutions in other applicable products without blindly copying product-specific assumptions.

For example, if users repeatedly fail to complete a business registration form in InfoDot, Dot.Brains should investigate validation behaviour, field clarity, technical errors, and the order of the steps before recommending a solution.
It must distinguish observed facts from hypotheses and explain the evidence behind its recommendations.
Use the existing Dot.Brains knowledge architecture, including DKP where applicable. Do not create a duplicate knowledge store.
B. Dot.Memory — Preserve Context and Coordinate Remediation
Extend Dot.Memory's role so that it supports continuity between issue detection, diagnosis, implementation, and verification.
When Dot.Brains identifies a sufficiently supported issue and an approved remediation plan, Dot.Memory should use its existing capabilities to coordinate or trigger the next step through the authorised integration workflow.
This may include:

* Creating or updating a UX improvement task.
* Linking the issue to the relevant product, component, and workflow.
* Preserving the baseline metrics and acceptance criteria.
* Recording implementation status and release references.
* Tracking outstanding actions and dependencies.
* Initiating an authorised remediation workflow where supported.
* Ensuring that the original issue remains linked to the resulting fix.
* Scheduling or requesting post-release evaluation.
* Recording whether the fix resolved the problem or requires further work.

Important: Dot.Memory must not silently edit application code or deploy unreviewed changes merely because an issue was detected.
If the existing Dot.Memory architecture does not support task execution or remediation orchestration, implement an explicit integration with the appropriate existing service. Do not assign undocumented capabilities to Memory.
The goal is a traceable transition from a verified UX issue to an implemented and evaluated improvement.
C. Dot.Dopemine — Measure the Before-and-After Experience
Extend Dot.Dopemine to evaluate the behavioural impact of UX improvements.
For each issue, Dopemine must receive the baseline measurements, the intended outcome, the implemented change, and the post-release measurements.
It should compare:

* Task completion rate.
* Workflow abandonment rate.
* Error and validation failure rate.
* Repeated attempts per task.
* Time to successful completion.
* Recovery rate after errors.
* Feature discovery and successful adoption.
* User feedback and satisfaction signals, where available.
* Relevant engagement and return-use indicators.
* Any adverse changes in other important product outcomes.

Dopemine must calculate a transparent improvement score based on relevant metrics and their predefined importance.
It must distinguish genuine improvement from normal variation, changes in user mix, seasonality, or unrelated releases.
Where sufficient data is available, use controlled experiments or suitable comparison groups. Where sample sizes are insufficient, report the result as inconclusive rather than declaring success.
Do not treat more clicks, longer sessions, or greater time spent as automatically positive outcomes. A successful UX improvement may reduce the number of clicks and the time required to complete a task.
Dopemine's goal is to measure whether users achieve their intended outcomes more easily and reliably.
15. Implement the Closed-Loop UX Improvement Cycle
Build a traceable workflow connecting all three systems.
<escape>Observe → Detect → Diagnose → Propose → Approve → Implement → Test → Measure → Learn → Reuse</escape>
The workflow must operate as follows:
Step 1 — Observe
The interaction tracker captures privacy-appropriate events and relevant application diagnostics.
Step 2 — Detect
The friction engine identifies unusual patterns, errors, repeated attempts, and potential abandonment.
Step 3 — Diagnose
Dot.Brains investigates the evidence, determines the likely cause, and establishes the confidence level.
Step 4 — Propose
Dot.Brains generates a specific remediation proposal with acceptance criteria and measurable expected outcomes.
Step 5 — Coordinate
Dot.Memory creates or updates the appropriate work item and maintains continuity throughout the remediation lifecycle.
Step 6 — Implement
The authorised development workflow applies the change. Low-risk, well-tested improvements may be automated where explicit policies permit. Higher-risk changes require human approval.
Step 7 — Test
Run the relevant unit, integration, regression, accessibility, and end-to-end tests.
Step 8 — Measure
Dot.Dopemine evaluates the updated experience against the original baseline.
Step 9 — Learn
Dot.Brains records the evidence, implementation details, outcome, confidence, and any remaining limitations.
Step 10 — Reuse
Where a pattern is demonstrably reusable, Dot.Brains proposes its adoption in other relevant products. Each adoption must be checked against the target product's context.
Every stage must have a recorded status. Failed, inconclusive, rejected, or rolled-back improvements must remain visible in the issue history.
The system must never claim that a fix was implemented, deployed, or successful without supporting evidence.
16. Create a Before-and-After Experience Score
Each remediation must have an evaluation record showing the condition before and after the change.
Include:

* Original issue severity.
* Baseline observation period.
* Baseline sample size.
* Baseline task completion and failure rates.
* Baseline time-to-completion statistics.
* The specific change implemented.
* Release identifier and evaluation period.
* Post-change sample size.
* Post-change measurements.
* Absolute and relative changes, where mathematically appropriate.
* Statistical uncertainty or confidence intervals when justified.
* Relevant user feedback.
* Regression checks.
* Final outcome and confidence.
* Follow-up actions.

Use a transparent scoring methodology. Do not combine unrelated metrics into a single score without documenting the weighting and interpretation.
Where a metric's baseline is zero or otherwise unsuitable for relative percentage calculations, use an appropriate alternative and explain it.
The result must classify the change as one of the following:

* Verified improvement.
* Partial improvement.
* No meaningful change detected.
* Regression detected.
* Inconclusive due to insufficient evidence.
* Rolled back.

Dopemine should report both the measured outcome and a normalised improvement score where the available evidence supports it.
A high score must never conceal a critical regression, security issue, privacy violation, or accessibility defect.
17. Build a User Experience Intelligence Dashboard
Create a dedicated experience-insights area within the existing Dot.Brains interface, following the product's established design system.
The dashboard should allow authorised users to inspect:

* Overall ecosystem UX health.
* UX health by product and tenant.
* Most frequent and highest-impact friction points.
* Workflows with the highest abandonment rates.
* Screens and components associated with repeated errors.
* Recently detected issues.
* Issues awaiting investigation or approval.
* Fixes currently in progress.
* Fixes awaiting validation.
* Before-and-after improvement results.
* Regressions and rolled-back changes.
* Recurring issues across multiple products.
* Improvements that can be reused elsewhere.
* Tracking coverage and data-quality warnings.

Allow authorised users to drill down from an aggregated problem to its relevant workflow, diagnostic evidence, issue history, and remediation outcome.
Respect tenant boundaries and role-based permissions. Company administrators must not be able to inspect another company's user-level interaction data.
The dashboard must be useful for product teams and management without exposing unnecessary personal information.
18. Privacy, Security, and Responsible Monitoring
Implement privacy and security as core architectural requirements.

* Collect only information needed to improve the experience.
* Respect applicable consent, notice, retention, and data-protection requirements, including relevant POPIA obligations.
* Use pseudonymous identifiers where appropriate.
* Define retention periods and deletion workflows.
* Prevent sensitive form values and private content from entering event payloads.
* Apply tenant isolation and least-privilege access.
* Encrypt data in transit and at rest according to the existing platform standards.
* Restrict access to individual-level diagnostics.
* Audit access to sensitive interaction reports.
* Apply rate limits and event validation.
* Protect the event pipeline against malicious or malformed input.
* Provide suitable aggregation or anonymisation before sharing reusable lessons.
* Do not use keystroke surveillance, covert recording, or unnecessary behavioural profiling.

Do not assume that removing a user's name automatically makes data anonymous. Treat re-identification risk appropriately.
Where session replay or detailed interaction capture is considered, it must be a separate, explicitly reviewed capability with appropriate masking, access controls, consent, and retention safeguards. It must not be silently enabled as part of ordinary event tracking.
19. Performance and Reliability Requirements
The tracking system must not make the ecosystem slower or less reliable.
Implement:

* Lightweight client-side event collection.
* Asynchronous delivery where appropriate.
* Batching and sampling where justified.
* Server-side validation.
* Idempotent ingestion and duplicate handling.
* Retry and backpressure strategies.
* Bounded event queues and resource usage.
* Monitoring for lost, delayed, or malformed events.
* Versioned event schemas.
* Appropriate storage and aggregation strategies.
* Graceful degradation if tracking or analytics services become unavailable.

Interaction tracking must never block critical user actions or prevent a form submission from succeeding merely because an analytics service is unavailable.
Do not send every raw event to an AI model. Aggregate and filter events first, then use deeper analysis where the expected value justifies its cost.
20. Rollout, Validation, and Acceptance Criteria
Implement the capability incrementally, starting with InfoDot and then establishing a reusable integration pattern for the wider Dot ecosystem.
Before rollout, inspect each product's architecture and identify the correct instrumentation points. Do not assume that every platform shares the same frontend, event pipeline, authentication model, or deployment process.
Acceptance criteria:

1. Meaningful interactions can be captured using a documented, versioned event schema.
2. Tracking respects privacy rules and tenant isolation.
3. Friction patterns can be detected from real event and diagnostic evidence.
4. Dot.Brains can analyse issues and produce traceable remediation proposals.
5. Dot.Memory can coordinate the supported improvement workflow.
6. Dot.Dopemine can compare baseline and post-change results.
7. Each issue can be traced from detection through remediation and evaluation.
8. Improvements can be marked inconclusive or unsuccessful when evidence warrants it.
9. Approved fixes can be implemented through authorised development workflows.
10. Automated tests cover tracking, permissions, event validation, issue detection, integrations, and outcome calculations.
11. Tracking failures do not interrupt critical product functionality.
12. The dashboard presents useful, accurate, permission-appropriate information.
13. Existing platform functionality remains intact.
14. Documentation explains the architecture, event taxonomy, privacy controls, operating procedures, and extension process.

Do not fabricate interaction data to demonstrate success. Synthetic events may be used in isolated test environments when explicitly identified as test data and kept separate from production analytics.
21. Final Objective
Build a self-improving user experience system in which InfoDot and the Dot ecosystem learn from the real difficulties users encounter.
When a user struggles, the system should identify the friction and investigate why it happened.
When the cause is sufficiently understood, Dot.Brains should recommend a targeted improvement.
Dot.Memory should coordinate the authorised fix and preserve the context required to complete it.
Dot.Dopemine should measure whether the updated experience actually helps users succeed.
Dot.Brains should then preserve validated lessons so the ecosystem can benefit from what has been learned.
The long-term objective is to reduce unnecessary friction, increase successful task completion, improve trust, and make every product feel progressively more intuitive.
Build a system that does not merely track what users do. Build a system that learns why users struggle, helps resolve the underlying problems, and proves whether the experience became better.
Implement this as a native extension of the existing Dot.Brains Brand Feeling Engine, reusing established Dot.Memory, Dot.Dopemine, and ecosystem infrastructure wherever possible. Inspect the real codebase, implement the required functionality, test the integrations, and report verified results rather than declaring success based on a design or plan alone.
