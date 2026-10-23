# Requirement: Dot.Brain Brand Feeling Engine (Blupin, InfoDot and the Dot ecosystem)

Source: owner brief of 2026-10-23, stored here verbatim so it is built from the owner's words (the brief says
"Dot.Brains"; the repository is `Dot.Brain`). **Status: foundation merged in Dot.Brain; scoring, analysis and the
Claude Skill are not built.** It joins the programme backlog as R-11 (see
[REMEDIATION_ROADMAP.md](../REMEDIATION_ROADMAP.md)). First step when it started: inspect what already exists in
Dot.Brain, Dot.Memory and Dot.Dopemine before designing anything; reuse, do not duplicate. That inspection found
that Dot.Brain is a specification repository plus zero-dependency Node CLI services run from the owner's machine or
GitHub Actions, not a running application (no HTTP server, no signer or ingester for Knowledge Packs). The decisions
that follow from it are recorded in Dot.Brain ADR-0021 and summarised under R-11 in the roadmap.

---

# DOT.BRAINS — BUILD THE BRAND FEELING ENGINE

## Native Brand Intelligence Capability for Blupin, InfoDot, and the Entire Dot Ecosystem

### Mission

Let's build The Brand Feeling Engine as a first-class, native intelligence capability inside Dot.Brains.

This is not a request to create an isolated Claude Skill, a separate application, or a collection of branding documents that sit outside the ecosystem.

Dot.Brains must own and manage this capability as part of its existing intelligence architecture. It must be able to understand brand identity, analyse product experiences, generate implementation-ready recommendations, evaluate changes, and continuously improve its knowledge using verified outcomes.

Where Claude Skills are supported by the development environment, create an appropriate Claude Skill as an interface to this capability. The skill must use Dot.Brains' authoritative knowledge and workflows rather than becoming an independent source of truth.

The objective is to give every application built or improved through the Dot ecosystem the ability to create a distinctive, sophisticated, trustworthy, and emotionally meaningful brand experience.

### 1. Architecture: Dot.Brains Owns the Intelligence

Before implementation, inspect the existing Dot.Brains repository, architecture, documentation, knowledge systems, APIs, background jobs, permissions, testing infrastructure, and integrations with Dot.Memory and Dot.Dopemine.

Preserve the existing architecture wherever possible. Do not create a parallel intelligence system, duplicate existing services, or assume that documented features are operational without verifying them.

Build the Brand Feeling Engine as a modular capability within Dot.Brains, following the project's established architectural conventions.

The capability must include the following logical components, adapted to the actual codebase:

**Brand Intelligence Core**
Owns the principles, models, rules, frameworks, and decision logic used to translate brand strategy into product experience.

**Brand Profile Registry**
Maintains versioned brand profiles for Blupin, InfoDot, the Dot ecosystem, and individual products. Profiles must define each brand's purpose, audience, positioning, emotional promise, behavioural principles, visual direction, language, and experience standards.

**Experience Analysis Engine**
Analyses applications, interfaces, workflows, content, and user journeys to identify gaps between the intended brand experience and the actual implementation.

**Experience Design Engine**
Transforms brand profiles and analysis results into specific recommendations for product architecture, UI/UX, interaction design, UX writing, design systems, and customer workflows.

**Implementation and Validation Engine**
Converts recommendations into actionable development tasks, supports code changes through authorised development tools, and verifies whether the intended improvements have actually been implemented.

**Brand Quality Evaluation Engine**
Scores products against measurable brand experience criteria, explains each score, identifies weaknesses, and tracks improvement across versions.

**Knowledge and Learning Integration**
Uses Dot.Brains' existing knowledge architecture to preserve reusable insights, lessons, validated patterns, experiments, and relationships between brands, products, decisions, and outcomes.

These are logical responsibilities, not instructions to create seven unnecessary microservices. Use the simplest maintainable architecture that fits the existing system.

### 2. Create the Core Brand Experience Framework

Develop a proprietary framework inside Dot.Brains that connects human psychology, product experience, business value, and measurable customer outcomes.

The framework must evaluate:

* Emotional positioning and intended customer feelings.
* Identity reinforcement and professional confidence.
* Brand differentiation and recognisability.
* Trust, transparency, privacy, and reliability.
* Perceived value and actual product usefulness.
* Visual craftsmanship and interaction quality.
* Ease of use and cognitive load.
* Onboarding and time to first meaningful result.
* Habit formation through continuing value.
* Customer retention and advocacy.
* Accessibility and inclusive design.
* Consistency across platforms and customer touchpoints.

For each principle, maintain its definition, intended outcome, applicable contexts, implementation guidance, validation method, limitations, and supporting evidence.

Do not treat psychological assumptions as established facts. Distinguish documented research, observed user behaviour, internal hypotheses, and validated findings.

The framework must produce practical engineering decisions, not merely philosophical descriptions of branding.

### 3. Establish Brand Intelligence Profiles

Create independent, version-controlled profiles for the following.

**Blupin**
The experience should reinforce precision, confidence, discipline, performance, and informed decision-making.
The system must inspect Blupin's actual products and audience before deciding how these principles should be expressed in each interface.

**InfoDot**
The experience should reinforce understanding, practical guidance, progress, opportunity, and belonging.
Small business owners must feel respected and equipped with sophisticated tools, regardless of their company size.
The platform should help users understand their businesses, access useful collective knowledge, make better decisions, and recognise their progress.

**The Dot Ecosystem**
The ecosystem should communicate coherence, intelligence, dependability, and operational capability.
Individual platforms—including Dot.Mines, Dot.Analytics, Dot.Docs, Dot.Agents, Dot.Finance, Dot.HR, Dot.Tasks, Dot.Brain, Dot.Memory, and Dot.Dopemine—must preserve their own functional identities while following shared experience principles.

Do not impose one generic interface on every product.

Store each brand profile as structured, versioned data using existing Dot.Brains conventions. Avoid hardcoding brand rules across unrelated controllers, views, or frontend components.

### 4. Integrate With Dot.Memory and Dot.Dopemine

Inspect the actual integration contracts and operational status of both systems before implementation.

**Dot.Brains is the intelligence authority.**
It defines the brand frameworks, analyses evidence, maintains the applicable knowledge, evaluates proposed improvements, and determines which lessons qualify for reuse.

**Dot.Memory supports continuity.**
Where supported by the existing architecture, use Dot.Memory to retain relevant brand decisions, product context, user preferences, experiment history, and verified lessons across sessions and projects.
Respect tenant boundaries, permissions, data retention rules, and the distinction between individual customer information and reusable, anonymised insights.

**Dot.Dopemine supports meaningful engagement.**
Where supported by its actual capabilities, use Dot.Dopemine to help products encourage useful return visits, completion of meaningful workflows, adoption of valuable features, and sustained engagement.
Never optimise engagement at the expense of user wellbeing, informed choice, privacy, or brand trust.

Do not create duplicate memory stores or competing learning loops. Follow the existing integration contracts and define clear ownership of data and decisions.

If any required integration is missing or incomplete, document the gap and implement the necessary integration through the established architecture rather than pretending it already works.

### 5. Connect Brand Intelligence to Real Development Work

The Brand Feeling Engine must be usable when a developer asks Dot.Brains or an authorised Claude integration to build a new application, improve an existing interface, review a repository, or redesign a customer journey.

The workflow must be:

1. Discover the application, repository, audience, business objectives, and existing brand profile.
2. Analyse the current implementation and identify experience gaps.
3. Establish the intended emotional outcomes and measurable product objectives.
4. Generate product-specific design and implementation specifications.
5. Produce actionable tasks, code changes, or implementation instructions.
6. Run appropriate tests and inspect the resulting implementation.
7. Evaluate the quality of the completed work against the brand standards.
8. Record evidence, unresolved issues, and validated lessons.
9. Propose eligible knowledge updates through Dot.Brains' existing governance mechanisms.

The capability must support both new products and existing applications.

It must understand that a mining fleet dashboard, a trading application, a business mentoring platform, and a document editor require different information hierarchies, interaction patterns, emotional expectations, and trust mechanisms.

Never allow the brand framework to override domain-specific safety, regulatory requirements, accessibility, security, or functional correctness.

### 6. Implement the Brand Experience Quality Score

Create a transparent 100-point evaluation framework with explicit scoring criteria for:

* Emotional clarity and positioning.
* Brand distinctiveness.
* Usability and cognitive load.
* Visual and interaction craftsmanship.
* Functional completeness.
* Trust and transparency.
* Perceived and demonstrated value.
* Accessibility and inclusion.
* Cross-platform consistency.
* Reliability and recovery from errors.
* Retention through continuing value.
* Measurable customer outcomes.

Define the weighting and scoring evidence for every dimension.

The score must distinguish between automated test results, expert assessment, observed user behaviour, and actual customer feedback.

Do not manufacture research findings or claim that users feel a particular way without evidence.

Target a score of at least 90/100 for release readiness, but never inflate a score to meet the target. Report blockers, confidence levels, missing evidence, and outstanding work honestly.

The scoring system must be versioned so that changes to its methodology do not silently invalidate historical comparisons.

### 7. Make the Skill Available Through Claude

Create a valid Claude Skill that acts as a practical development interface to the native Dot.Brains capability.

The skill must:

* Recognise brand experience and product-design tasks.
* Retrieve the relevant brand profile and applicable knowledge.
* Analyse the current repository before recommending changes.
* Follow the Brand Feeling Engine's methodology.
* Generate implementation-ready specifications and code where appropriate.
* Validate the actual work.
* Report findings and proposed knowledge updates to Dot.Brains through supported interfaces.

Use the current supported Claude Skill structure and conventions. Inspect the environment to determine the appropriate location, invocation method, and integration mechanism.

The skill must not assume that Claude can automatically access Dot.Brains, every repository, or every connected application. Use authenticated, authorised interfaces that actually exist.

If a direct integration is not currently available, build the skill with a clearly defined integration boundary and document the missing connection. Do not simulate successful API calls or claim that external actions occurred when they did not.

Keep the skill lightweight and modular. Store authoritative, reusable knowledge in Dot.Brains and retrieve supporting material as needed instead of duplicating the entire framework inside every skill installation.

### 8. Knowledge Governance and Continuous Improvement

Use Dot.Brains' existing knowledge management and versioning conventions, including DKP where applicable.

Represent important knowledge relationships between:

* Brands.
* Products.
* Audiences.
* Emotional promises.
* Design principles.
* Components and patterns.
* Implementation decisions.
* Experiments.
* Metrics.
* Observed outcomes.
* Recommendations.
* Confidence levels.
* Dependencies and limitations.

The engine may generate recommendations and propose knowledge changes, but it must not silently rewrite authoritative brand principles based on unsupported assumptions.

Follow the existing governance process for reviewing, versioning, approving, and publishing knowledge changes.

Preserve the ability to trace an implemented design decision back to its rationale, applicable brand profile, supporting evidence, and validation results.

Ensure tenant-specific information remains isolated and that knowledge sharing follows explicit permissions and privacy requirements.

### 9. Production-Ready Engineering Requirements

Treat this as an enterprise capability, not a prototype or documentation-only exercise.

Implement the appropriate requirements for the actual Dot.Brains architecture:

* Authentication and authorisation.
* Tenant isolation.
* Input validation and secure data handling.
* Auditable decisions and knowledge changes.
* Versioned brand profiles and scoring rules.
* Background processing for expensive analysis.
* Retry and failure-handling strategies.
* Observability, logging, and actionable diagnostics.
* API contracts and integration tests.
* Unit and feature tests.
* Regression tests for existing Dot.Brains functionality.
* Performance and caching where justified.
* Documentation and operational procedures.
* Secure configuration and secret management.
* Rollback and recovery procedures.
* Accessibility and localisation where applicable.

Follow existing architectural standards rather than introducing unnecessary infrastructure.

Do not use fake integrations, placeholder success responses, fabricated brand data, or hardcoded claims that a product is enterprise-ready.

Where external services, credentials, or customer data are required, implement the supported integration boundary and explicitly identify what remains dependent on authorised access.

### 10. Required Implementation Deliverables

Implement and verify the following within the existing Dot.Brains project:

1. The native Brand Feeling Engine capability.
2. Versioned brand profiles for Blupin, InfoDot, and the Dot ecosystem.
3. The core brand psychology and product experience framework.
4. The experience analysis and implementation workflow.
5. The Brand Experience Quality Score and its transparent scoring criteria.
6. Integration with existing Dot.Memory and Dot.Dopemine capabilities where supported.
7. Knowledge governance and evidence-tracking mechanisms.
8. A Claude Skill that accesses the native capability through a verified integration path.
9. Automated tests, integration tests, and regression tests.
10. Technical documentation, setup instructions, and operational guidance.
11. Demonstrations covering a business intelligence dashboard, a mining fleet application, a business mentoring platform, and a customer-facing small business application.
12. An implementation report identifying completed work, test results, outstanding issues, and verified limitations.

Create the actual files and modify the actual codebase when authorised repository access is available. Do not stop after generating a plan.

### 11. Execution Instructions

Begin by inspecting the existing Dot.Brains implementation and its documentation.

Determine what already exists, what can be reused, what needs to be extended, and what genuinely needs to be built.

Produce a concise architecture assessment and implementation plan, then proceed with the implementation using the project's established development and testing practices.

Avoid unnecessary rewrites. Preserve existing working functionality. Keep changes modular and traceable.

Do not declare the work complete until the implementation has been inspected and the available validation checks have been run.

At completion, report:

* What was built.
* Where it lives within Dot.Brains.
* How the Claude Skill connects to it.
* How brand profiles and knowledge are managed.
* How Dot.Memory and Dot.Dopemine are integrated.
* Which tests passed and failed.
* The actual quality score and supporting evidence.
* Any remaining blockers or external dependencies.
* How to invoke, maintain, and extend the capability.

### Final Objective

Make Dot.Brains the intelligence foundation that helps every product in the ecosystem create experiences people genuinely value.

Blupin should earn trust through precision and performance. InfoDot should help business owners feel supported, capable, and equipped to grow. The Dot ecosystem should make sophisticated business technology feel coherent, dependable, and accessible.

Every product should have its own identity, but all should benefit from the same underlying commitment to thoughtful design, genuine usefulness, reliability, and lasting customer trust.

Build the intelligence inside Dot.Brains. Connect it to the real development workflow. Make its recommendations measurable, its knowledge governable, its integrations verifiable, and its impact visible in the products it helps create.
