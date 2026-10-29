# Requirement R-13: InfoDot entrepreneur tiers, knowledge reputation, business seminars and account licensing

Source: owner brief of 2026-10-29, stored as received. The chat interface's own markup around the tier list (image queries and layout tags) is removed; every word of the requirement is kept. **Status: inspected and planned, not built; see `R13_INSPECTION.md`.**

## INFODOT — ENTREPRENEUR TIERS, KNOWLEDGE REPUTATION, BUSINESS SEMINARS & ACCOUNT LICENSING
Enterprise-Ready Feature Expansion for the Dot Ecosystem

### 1. Mission
Extend the existing InfoDot platform with a comprehensive entrepreneur development, knowledge reputation, business seminar, and account licensing system.

These must be fully functional features integrated into InfoDot's existing architecture, user experience, database, authentication, billing, and Dot ecosystem integrations.

Do not build disconnected modules or superficial UI demonstrations. Inspect the existing codebase, reuse working functionality, identify gaps, and implement the features end to end.

The objective is to make InfoDot a platform where people can learn about business, demonstrate their knowledge, build professional credibility, share expertise, host educational events, grow their businesses, and expand their teams at a predictable cost.

The platform must support individual entrepreneurs, first-time business owners, experienced entrepreneurs, established companies, mentors, and business professionals without unnecessarily restricting access to knowledge or participation.

The implementation must cover four core capabilities:

1. InfoDot Tiers: A transparent reputation and business-development progression system.
2. InfoDot Seminars: A feature that enables any eligible InfoDot user to host free or paid business seminars.
3. InfoDot Account Licensing: A pricing and licensing model in which a business can start with up to three registered users for free and pay for additional user licenses.
4. Dot Ecosystem Intelligence: Integration with Dot.Brains, Dot.Memory, and Dot.Dopemine to improve knowledge quality, user development, and engagement through measurable outcomes.

### 2. Feature One: InfoDot Tiers

#### 2.1 Create a Four-Tier Entrepreneur Development System
Replace generic user labels with a distinctive InfoDot progression system that recognises users' business knowledge, practical contribution, learning progress, and demonstrated experience.

Use the following proposed names:

- **Tier 1: InfoDot Spark** (Beginner / Business Explorer). For users who are starting to learn about business, exploring business ideas, asking questions, and building foundational knowledge. Recognition: curiosity, participation, learning, and early progress. (Emblem idea: a bronze seedling.)
- **Tier 2: InfoDot Venture** (First-Time Entrepreneur). For users actively establishing their first business, learning operational fundamentals, and turning ideas into practical action. Recognition: consistent learning, useful contributions, and progress toward business goals. (Emblem idea: a silver compass.)
- **Tier 3: InfoDot Growth** (Experienced / Growing Entrepreneur). For users who have demonstrated meaningful business experience, contributed practical solutions, and helped others overcome real business challenges. Recognition: applied knowledge, consistent contributions, and evidence of practical experience. (Emblem idea: a gold rising sun.)
- **Tier 4: InfoDot Luminary** (Pro / Recognised Business Expert). For users who consistently provide valuable solutions, demonstrate substantial expertise, and earn the trust of the community through verified contributions and experience. Recognition: trusted expertise, demonstrated impact, and sustained contribution to business development. (Emblem idea: a platinum laurel crown.)

These names should be stored in configurable tier definitions rather than hardcoded into the application. Their thresholds, descriptions, visual styles, and eligibility criteria must be adjustable by authorised administrators.

Critical distinction: An InfoDot tier represents demonstrated knowledge, contribution, or verified experience. It must not automatically be presented as proof that a business is legally registered, financially successful, or professionally certified.

#### 2.2 Build a Comprehensive Reputation Engine
Users must be able to earn recognition through meaningful participation in InfoDot.

Evaluate activities such as:

- Asking thoughtful, relevant business questions.
- Providing useful answers to other users.
- Publishing practical business solutions.
- Having solutions marked as accepted or helpful.
- Receiving positive feedback on answers and solutions.
- Demonstrating that a proposed solution helped resolve a real problem.
- Sharing relevant educational resources.
- Participating constructively in business discussions.
- Hosting educational seminars and contributing useful learning material.
- Completing approved learning activities.
- Demonstrating verified business experience where appropriate.

Differentiate between activity, contribution quality, and demonstrated expertise.

For example, asking a good question should help a user demonstrate curiosity and engagement. However, asking hundreds of low-quality questions must not be a shortcut to the highest tier.

Likewise, posting many answers must not outweigh the quality, relevance, accuracy, and usefulness of those answers.

#### 2.3 Design the Reputation Scoring Model
Create a configurable scoring engine with separate dimensions for:

- Learning: Questions asked, learning activities completed, and knowledge gained.
- Contribution: Useful answers, practical solutions, and shared resources.
- Impact: Accepted solutions, helpful feedback, and demonstrated outcomes.
- Consistency: Meaningful participation over time.
- Expertise: Verified experience and sustained high-quality contributions.
- Trust: Community feedback, moderation outcomes, and adherence to platform standards.

Use these dimensions to calculate an overall reputation score and determine tier eligibility.

Establish appropriate controls against:

- Spam and repetitive questions.
- Self-voting and reciprocal voting schemes.
- Duplicate or copied answers.
- Fake accounts and coordinated manipulation.
- Artificial engagement and automated point farming.
- Unsubstantiated claims of expertise.
- Retaliatory ratings and abusive feedback.

Do not make a high question count the primary signal of expertise. Reward the quality of contributions and their demonstrated usefulness.

#### 2.4 Separate Tiers From Individual Achievement Badges
In addition to the four primary tiers, support specialist badges that recognise specific achievements.

Examples include: Problem Solver; Helpful Mentor; Verified Business Owner; Community Contributor; Knowledge Sharer; Seminar Host; Consistent Contributor; Industry Specialist; Verified Expert.

A user may hold several specialist badges simultaneously while maintaining one primary tier.

Verified credentials must be clearly distinguished from community-awarded badges. A badge for helping others must never imply a formal qualification that the user does not possess.

#### 2.5 Tier Progression and Transparency
Every user must be able to understand:

- Their current tier.
- Their current reputation score.
- The activities that contributed to their score.
- The criteria for reaching the next tier.
- Their progress toward the next tier.
- Badges earned and the requirements for other badges.
- Any deductions, moderation adjustments, or revoked achievements, with appropriate explanations.

Build a profile experience that presents this information professionally and motivates genuine learning.

Tier changes must be recalculated through reliable, auditable processes. Avoid arbitrary manual changes, unexplained score fluctuations, or irreversible penalties for ordinary inactivity.

Provide an appeal or review process for disputed tier decisions.

### 3. Feature Two: InfoDot Business Seminars

#### 3.1 Mission
Introduce a dedicated InfoDot Seminars feature that allows all eligible InfoDot users to organise and host business seminars, whether free or paid.

Users must not need to reach InfoDot Luminary or another high reputation tier before they can host an event.

A beginner may organise a free seminar to share what they are learning, while an experienced entrepreneur may host a paid seminar on a subject in which they have demonstrable expertise.

Apply proportionate safeguards to paid events and specialist claims without creating unnecessary barriers for new entrepreneurs.

#### 3.2 Seminar Creation
Build a complete seminar creation workflow that allows hosts to specify:

- Seminar title and description.
- Topic and business category.
- Host profile and relevant credentials.
- Intended audience and learning objectives.
- Date, start time, duration, and time zone.
- Online, in-person, or hybrid format.
- Maximum attendance and registration deadline.
- Free or paid admission.
- Ticket price and supported currency.
- Available ticket types.
- Seminar cover image and supporting materials.
- Registration requirements.
- Cancellation, refund, and attendance policies.
- Whether questions, discussions, or follow-up materials are available.
- Whether the event will be recorded, with appropriate participant consent.

Allow drafts, previews, publishing, editing, cancellation, and appropriate post-event updates.

Use clear validation and sensible defaults so that a first-time host can create a seminar without being overwhelmed.

#### 3.3 Free and Paid Events
Free seminars must support registration, attendance tracking, reminders, event access, and feedback.

Paid seminars must support the complete commercial workflow, including:

- Configurable ticket pricing.
- Secure checkout through a supported payment provider.
- Payment confirmation.
- Registration and ticket issuance.
- Refund and cancellation handling.
- Host earnings and transaction reporting.
- Applicable fees, taxes, and payout rules.
- Fraud prevention and payment reconciliation.
- Appropriate records for financial reporting.

Inspect Dot.Billing and any existing payment integrations before building payment functionality.

Reuse established billing capabilities where possible. Do not create a second, conflicting payment system inside InfoDot.

Do not store raw payment-card details. Payment processing must follow the supported provider's security requirements.

If payment processing, payouts, or a suitable seminar delivery provider is not yet configured, implement the integration boundary and clearly document the remaining setup requirements.

#### 3.4 Seminar Delivery and Attendance
Support online seminars through an existing or properly integrated video-conferencing or webinar provider.

For in-person events, provide venue information and attendance-management tools.

Implement appropriate functionality for: registration confirmation; calendar invitations; event reminders; secure event access; attendance tracking; host and attendee management; questions and answers; post-event feedback; follow-up resources; attendance certificates where appropriate and clearly defined; host analytics.

Do not claim to provide live video hosting unless an actual delivery provider has been integrated and tested.

#### 3.5 Seminar Discovery
Create a dedicated seminar discovery area within InfoDot.

Users should be able to discover seminars by: business topic; industry; host; date and time; free or paid admission; price range; online or in-person format; language; relevance to their business interests.

Provide search, filtering, clear event details, registration, and ticket purchase.

Recommendations may use the user's interests and relevant activity, subject to privacy settings and permissions.

Do not make paid events automatically rank above free events. Sponsored placements, if introduced, must be clearly identified.

#### 3.6 Host Reputation and Quality
Show seminar hosts' InfoDot tiers, relevant badges, verified credentials, and past seminar feedback.

Collect attendee feedback on the quality, relevance, organisation, and usefulness of the seminar.

Prevent fake reviews, retaliatory ratings, and manipulation by requiring appropriate attendance or registration evidence for event-specific reviews.

Do not automatically equate ticket price or popularity with educational quality.

Paid and free seminars should both be able to contribute to a host's reputation when the event meets the relevant quality criteria.

### 4. Feature Three: InfoDot Account Types and User Licensing

#### 4.1 Replace the Existing Account-Type Model Where Necessary
Inspect the current InfoDot account types, registration flow, organisation model, user invitations, subscription structure, and billing implementation.

Redesign the model so that it clearly distinguishes between: 1. An individual user. 2. A business or organisation. 3. Members of that organisation. 4. The organisation's subscription and licensed user capacity.

Avoid confusing a user's personal profile with the organisation they belong to.

A user should be able to maintain an individual InfoDot profile and participate in the community while also belonging to a business workspace, subject to the applicable account and platform policies.

One individual must not need to create duplicate personal identities simply to join multiple businesses.

#### 4.2 Free Business Account: Up to Three Users
Implement the following commercial rule: A registered business or organisation can have up to three licensed users at no subscription charge for the core business-account offering.

The three-user allowance must be calculated per business or organisation, not per individual account, browser, device, or invitation.

The business should be able to: register its organisation; create its business workspace; invite up to two additional users after the initial account owner, assuming the owner occupies one licensed seat; manage the three included user seats; assign appropriate roles and permissions; view its current licensed capacity; upgrade when additional seats are required.

The exact features included in the free offering must be defined explicitly. Do not assume that every paid capability, premium integration, or resource-intensive service must be unlimited simply because the business has three free seats.

#### 4.3 Paid Additional User Licenses
When a business needs more than three users, it must purchase additional user licenses at a clearly disclosed price.

The pricing model should be configurable by authorised administrators rather than hardcoded into application logic.

Use a model that supports: a configurable price per additional user license; supported currencies; monthly and annual billing where applicable; subscription status and renewal dates; seat allocation and usage; additional seat purchases; seat reductions and removals; proration or other explicitly defined billing policies; failed payment handling; subscription upgrades and downgrades; invoice and payment history; cancellation and renewal management.

For example, if a business has eight active licensed users, three seats are included and five additional seats are billable.

The application must calculate the charge using the configured price and billing period. Do not invent or hardcode a currency amount without an approved pricing decision.

#### 4.4 Seat Allocation and Enforcement
Implement an accurate seat-allocation system.

Each organisation must be able to see: included seats; purchased seats; active licensed users; pending invitations and how they affect capacity; available seats; monthly or annual subscription costs; outstanding payments and billing status.

Define when a seat is consumed. A sensible default is that an accepted organisation membership consumes one seat, while a pending invitation reserves a seat only according to an explicitly defined policy.

The system must prevent race conditions that allow an organisation to exceed its licensed capacity during concurrent invitations or membership changes.

When no seats remain, explain the situation and provide an upgrade or seat-management workflow.

Do not unexpectedly delete users, block access to their personal profiles, or destroy business data because an organisation has reached its seat limit.

Define clear grace periods and administrative recovery procedures for billing failures.

#### 4.5 Organisation Roles and Permissions
Implement role-based access appropriate to a business environment. At minimum, consider: organisation owner; organisation administrator; manager; member.

Each role must have explicit permissions covering invitations, seat allocation, subscription management, organisation settings, and member administration.

Only authorised roles may purchase seats or change subscription settings.

An ordinary member must not be able to access billing information or administer the organisation merely because they hold an advanced InfoDot entrepreneur tier.

Entrepreneur reputation and organisation permissions must remain separate systems.

A user's InfoDot Luminary status must never automatically grant administrative privileges over a company.

#### 4.6 Subscription and Billing Integrity
Integrate with Dot.Billing where available and follow the established payment and subscription architecture.

Implement: server-side entitlement checks; verified payment webhooks; idempotent billing operations; invoice and transaction records; seat reconciliation; subscription lifecycle handling; auditable changes to licensed capacity; appropriate tenant isolation; protection against unauthorised seat increases; recovery from failed payments and webhook delivery.

The interface must never treat a successful checkout redirect as sufficient proof that payment has settled. Confirm the relevant payment state through the trusted billing integration.

Provide a clear billing summary before any charge is committed.

### 5. Connect All Three Features to Dot.Brains, Dot.Memory, and Dot.Dopemine
These features should contribute to the broader InfoDot intelligence ecosystem without duplicating existing systems.

**Dot.Brains.** Use it to: maintain the configurable tier definitions and scoring principles; analyse the quality and usefulness of questions, answers, and solutions; identify recurring business challenges that users are asking about; recommend relevant learning opportunities; identify seminar topics with demonstrated user demand; evaluate seminar feedback and contribution quality; detect reputation manipulation and suspicious patterns; analyse the usability of tier progression, seminar creation, and business registration; preserve validated lessons through existing knowledge governance. Knowledge updates must follow established review and versioning rules. Do not automatically treat popular or highly rated content as factually correct.

**Dot.Memory.** Use it to: maintain authorised continuity for a user's learning journey; preserve relevant business goals and progress where supported; track seminar planning and follow-up workflows; coordinate approved improvement tasks; maintain the context of user experience issues and their remediation; support the existing UX improvement lifecycle. Respect user consent, data retention policies, and tenant isolation. Do not create duplicate personal or organisational memory stores.

**Dot.Dopemine.** Use it to: measure meaningful participation and return usage; evaluate whether users are progressing through relevant learning journeys; measure the usefulness of answers and solutions; track seminar discovery, registration, attendance, and follow-up engagement; evaluate whether changes to tier progression improve contribution quality; compare user experience before and after improvements; identify engagement patterns associated with successful business outcomes. Do not award reputation solely for time spent, number of clicks, seminar purchases, or subscription spending. Reward meaningful contribution and demonstrated value.

All three integrations must use actual supported services and documented contracts. If an integration is incomplete, identify and implement the required connection instead of assuming the capability already exists.

### 6. User Interface and Experience Requirements
The new functionality must feel like a natural extension of InfoDot. Create a coherent experience for: personal profiles and tier badges; reputation and contribution history; tier progression and achievement details; seminar creation and management; seminar discovery, registration, and tickets; host analytics and feedback; business registration and workspace management; team invitations and seat allocation; subscription upgrades and billing; organisation administration.

The experience must be sophisticated, professional, accessible, and easy for first-time entrepreneurs to understand.

Make important actions clear. Explain why a user has earned a badge, why a business must purchase another seat, and what a paid seminar includes.

Avoid confusing terminology, excessive gamification, deceptive upgrade prompts, or interfaces that shame users for having a lower tier.

Users must be able to understand the system without needing to read technical documentation.

### 7. Security, Governance, and Abuse Prevention
Implement the appropriate safeguards for the actual application architecture. Cover: authentication and authorisation; organisation-level tenant isolation; protection of business and billing data; reputation manipulation and spam prevention; seminar moderation and reporting; fraud prevention for paid events; refund and cancellation policy enforcement; secure invitation and membership workflows; privacy-compliant activity tracking; audit logs for administrative actions; protection against duplicate payments and webhook replay; appropriate access to attendee lists and seminar records; data retention and account deletion; appeals for reputation and moderation decisions.

Clearly distinguish verified facts, user-submitted claims, community feedback, and algorithmic assessments.

Do not expose private business information through public profiles, seminar listings, or reputation explanations.

### 8. Implementation and Acceptance Criteria
Before coding, inspect the actual InfoDot, Dot.Brains, Dot.Memory, Dot.Dopemine, and Dot.Billing repositories or integration contracts that are available. Map the existing account types, database relationships, payment services, event architecture, reputation mechanisms, and permissions. Then implement the changes incrementally.

The release must not be considered complete until:

1. The four InfoDot tiers and their configurable criteria work end to end.
2. Reputation scores update accurately and have an auditable history.
3. Users can earn multiple specialist badges without confusing them with their primary tier.
4. Users can create and manage free and paid seminars.
5. Seminar registration, attendance, payments, and refunds work through verified integrations.
6. Business accounts can operate with up to three included licensed users.
7. Additional seats are priced using the configured billing model.
8. Concurrent invitations cannot bypass seat limits.
9. Organisation roles and billing permissions are enforced server-side.
10. Subscription lifecycle and billing events remain consistent with Dot.Billing.
11. The applicable Dot.Brains, Dot.Memory, and Dot.Dopemine integrations work as implemented and documented.
12. Tenant isolation, privacy, and security tests pass.
13. Unit, feature, integration, and regression tests cover the new functionality.
14. The user interface works across supported screen sizes and meets applicable accessibility requirements.
15. Documentation covers setup, administration, pricing configuration, moderation, and operational support.

Do not use fabricated production users, simulated payment success, or hardcoded scores to claim that a feature is working. Use isolated test fixtures and payment-provider sandbox environments where appropriate, clearly distinguishing them from production data.

### Final Execution Instruction
Inspect the existing InfoDot architecture, identify what can be reused, and implement these capabilities as a cohesive extension of the current platform. Preserve working functionality, avoid unnecessary rewrites, and do not create parallel billing or knowledge systems.

At completion, report the implemented features, relevant files and migrations, integration status, test results, pricing configuration requirements, and any remaining blockers.

The end result must make InfoDot a place where entrepreneurs build credibility, share and monetise knowledge, learn from one another, and grow their businesses, with a fair entry point for small teams and a clear, reliable path to expansion.
