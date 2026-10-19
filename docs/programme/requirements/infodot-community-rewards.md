# Requirement: InfoDot community contributions and knowledge rewards

Source: owner brief of 2026-10-19, stored here verbatim so it is built from the owner's words. **Status: not started.**
It joins the programme backlog as R-9 (see [REMEDIATION_ROADMAP.md](../REMEDIATION_ROADMAP.md)). First step when it
starts: inspect what already exists (InfoDot questions/answers/moderation/profile pages, and the existing Dot Points
scoring and ledger in Dot.Brain, Dot.Memory and Dot.Dopemine) before designing anything; reuse, do not duplicate.

## ADDITIONAL REQUIREMENT: INFODOT COMMUNITY CONTRIBUTIONS & KNOWLEDGE REWARDS

### 1. Objective

Extend the existing Dot ecosystem scoring and rewards system to include **InfoDot**, rewarding users for sharing useful knowledge, publishing solutions, answering questions, helping other users troubleshoot problems, and contributing information that improves the wider ecosystem.

This functionality must be integrated into the existing architecture of Dot.Brain, Dot.Memory and Dot.Dopemine. Do not create a separate rewards platform.

All eligible points must appear on the user's existing Dot ecosystem profile, alongside points earned through other Dot platforms.

The objective is to build a community where users are motivated to share knowledge, solve real problems, learn from one another and improve the value of the entire Dot ecosystem.

### 2. InfoDot contribution categories

Implement configurable reward rules for the following contribution types.

**A. Asking and answering questions**
- Award points for asking a well-formed question that passes validation.
- Reward users for providing useful, relevant and original answers.
- Award additional points when an answer is marked as the accepted solution by the question author or an authorised moderator.
- Reward answers that receive positive feedback from other users, subject to anti-abuse thresholds.
- Recognise answers that help multiple users solve the same problem.
- Reward follow-up explanations that clarify a difficult topic or provide missing implementation details.
- Do not reward duplicate, spam, trivial or deliberately low-quality questions and answers.

**B. Publishing solutions and tutorials**
- Reward users for publishing verified solutions to real problems.
- Reward step-by-step tutorials, technical guides, implementation examples, troubleshooting procedures and best-practice documentation.
- Recognise reproducible solutions that include appropriate instructions, evidence, examples or test results.
- Award additional points when a solution is independently verified by other users or moderators.
- Reward solutions that are reused successfully by multiple community members.
- Recognise corrections and improvements to existing solutions.
- Allow solutions to be updated and versioned without repeatedly awarding points for the same contribution.

**C. Knowledge sharing and community support**
- Reward users who help other users troubleshoot issues.
- Recognise constructive participation in technical discussions and community knowledge-sharing activities.
- Reward helpful comments that materially improve an answer or solution.
- Reward contributions that make complex topics easier for other users to understand.
- Recognise contributions that reduce repeated support requests by creating reusable knowledge.
- Where appropriate, recognise community members who consistently provide accurate, respectful and constructive assistance.

**D. Contribution quality and long-term value**
- Give additional recognition to solutions that remain accurate and useful over time.
- Reward verified corrections to outdated or inaccurate information.
- Recognise contributions adopted into approved knowledge resources.
- Reward knowledge that benefits multiple Dot platforms, teams or organisations, subject to applicable permissions.
- Recognise contributors whose knowledge helps improve workflows, documentation, platform usability or business processes.

### 3. Configurable starting reward schedule

Implement these as proposed defaults, not hard-coded values. Administrators must be able to configure point values, eligibility, daily limits, approval requirements and applicable platform rules.

| Eligible activity | Suggested reward |
|---|---:|
| Approved, useful question | 2 DP |
| Substantive answer | 5 DP |
| Answer accepted as the solution | +10 DP |
| Solution independently verified | +10 DP |
| Published tutorial or technical guide approved | 10–20 DP |
| Reusable troubleshooting solution approved | 10–25 DP |
| Helpful contribution recognised by the community | 1–3 DP |
| Verified correction to existing knowledge | 3–10 DP |
| Contribution adopted into an approved knowledge pack | 10–25 DP |
| High-value solution benefiting multiple users | 5–20 DP bonus |
| Sustained, high-quality community contribution milestone | Configurable bonus |

These values are examples for initial configuration. The implementation must support future changes without requiring code modifications.

### 4. Quality must outweigh quantity

Build the scoring engine to reward meaningful contributions rather than raw activity counts.

The system must:
- Award points only after the relevant eligibility conditions have been met.
- Apply quality checks and moderation requirements appropriate to each contribution type.
- Distinguish between submitting content, receiving community feedback, having a solution accepted, and having a solution independently verified.
- Apply configurable caps to prevent users from earning unlimited points through repetitive low-value activity.
- Detect duplicate answers, copied content, spam, artificial engagement and coordinated voting.
- Prevent users from earning points by repeatedly answering their own questions through alternate accounts.
- Avoid awarding the same reward multiple times for edits, repeated approvals or duplicate events.
- Support legitimate appeals and moderator corrections.
- Maintain an auditable record of awards, reversals and moderation decisions.

Do not penalise legitimate beginners for asking questions. Asking good questions should encourage learning, but answering questions and publishing verified solutions should generally carry greater rewards.

Community votes may inform scoring, but votes alone must not automatically establish that a solution is correct.

### 5. Dot.Brain integration

Dot.Brain must turn approved InfoDot contributions into structured, reusable knowledge.

It should:
- Identify potentially valuable answers, solutions, tutorials and troubleshooting procedures.
- Recommend high-quality contributions for review and inclusion in relevant Knowledge Packs.
- Record the origin of knowledge, contributor attribution, verification status, confidence, version and relevant relationships.
- Identify recurring unanswered questions and knowledge gaps.
- Recommend contributors who may be qualified to answer specific questions, without exposing private user or organisational information.
- Detect conflicting, outdated or potentially misleading solutions and flag them for review.
- Recognise when an InfoDot contribution could help another Dot platform solve a recurring problem.
- Propose knowledge updates through the established approval and versioning workflow.

**Governance:** Dot.Brain may analyse contributions and recommend awards or knowledge updates, but it must not bypass approved scoring rules, moderation policies or authoritative transaction records. AI-generated quality assessments must be explainable, reviewable and subject to configured safeguards.

### 6. Dot.Memory integration

Dot.Memory should maintain authorised contextual records of community participation and contribution history.

It should support:
- Tracking a user's areas of expertise based on demonstrated contributions.
- Remembering previously submitted solutions and their verification status.
- Identifying topics where a user consistently provides valuable assistance.
- Helping users resume unfinished answers, follow up on questions and improve their existing knowledge contributions.
- Supporting personalised learning and contribution recommendations.
- Providing relevant historical context to Dot.Brain and Dot.Dopemine when permitted.

Dot.Memory must not become the authoritative points ledger. It must respect user privacy, organisational boundaries, retention rules and consent settings. It must not expose private questions, restricted solutions or confidential business knowledge to unauthorised users.

### 7. Dot.Dopemine integration

Dot.Dopemine should encourage meaningful participation without turning the community into a spam-driven engagement loop.

Implement features such as:
- Personalised suggestions for unanswered questions matching a user's demonstrated expertise.
- Notifications when someone responds to a user's question or requests clarification.
- Notifications when a user's solution is accepted, verified or reused.
- Contribution milestones and knowledge-sharing achievements.
- Optional community challenges focused on solving real problems.
- Weekly or monthly summaries of useful contributions and learning progress.
- Recognition for maintaining accurate, helpful and respectful contributions over time.
- Recommendations for improving an answer when it needs clarification or supporting evidence.

Allow users to configure notifications and participation preferences. Avoid manipulative streaks, excessive notifications or rewards that encourage low-quality content.

### 8. InfoDot profile and reputation display

Display community contribution statistics directly on the user's existing Dot ecosystem profile.

Include:
- Current redeemable Dot Points balance.
- Total points earned through InfoDot.
- Questions asked and answers submitted.
- Accepted solutions.
- Verified solutions.
- Tutorials and guides published.
- Helpful contributions recognised by the community.
- Solutions reused or adopted into approved knowledge resources.
- Community contribution achievements and milestones.
- Optional expertise areas supported by actual contribution history.
- A public contribution portfolio containing only content the user is authorised to share.

Provide a detailed contribution history showing the activity, points awarded, date, status and reason for each award.

Users must be able to distinguish between **redeemable points**, **contribution achievements** and any separate reputation or quality indicator. These must not be combined into an unexplained score.

Public reputation must not expose private transaction histories, internal moderation notes, confidential organisational information or private questions. Users and administrators must have appropriate visibility and privacy controls.

### 9. Verification, anti-abuse and point reversals

Implement an auditable contribution lifecycle, for example:

`Submitted → Validated → Published → Accepted or Verified → Rewarded`

Not every contribution must follow every stage. Rules should reflect the type of content and the risk of abuse.

The system must:
- Use stable contribution IDs and idempotent event processing.
- Apply duplicate-detection and rate-limiting safeguards.
- Record the exact rule version responsible for every award.
- Prevent self-approval where independent verification is required.
- Support moderator review, user appeals and reasoned decisions.
- Reverse or adjust points when an award was fraudulent, duplicated or invalidated.
- Preserve the audit trail after reversals.
- Avoid silently deleting legitimate contribution history.
- Protect against vote manipulation, referral rings, automated spam and collusion.
- Ensure that point reversals cannot create an unauthorised negative balance or corrupt the ledger.

Any AI-assisted detection must be treated as a signal for configured controls or review, not as unquestionable proof of misconduct.

### 10. Ecosystem-wide knowledge contribution events

Extend the existing event architecture so InfoDot can publish validated events such as:

- `infodot.question.published`
- `infodot.answer.published`
- `infodot.answer.accepted`
- `infodot.solution.verified`
- `infodot.tutorial.approved`
- `infodot.contribution.helpful`
- `infodot.knowledge.corrected`
- `infodot.knowledge.adopted`
- `infodot.contribution.flagged`
- `infodot.contribution.reward_reversed`

Events must contain the minimum necessary metadata and must be validated against the authorised source. Do not trust client-side events or allow users to award themselves points.

Connect these events to the shared scoring and ledger workflow so InfoDot rewards are treated consistently with rewards earned elsewhere in the Dot ecosystem.

### 11. Testing and acceptance criteria

Before marking the feature complete, verify that:

1. Eligible InfoDot questions, answers and solutions generate the correct awards.
2. Accepted and independently verified solutions receive the configured bonuses exactly once.
3. Repeated submissions, edits and duplicate events do not create duplicate rewards.
4. Spam, collusion, self-approval and manipulated voting are handled by the configured safeguards.
5. Valid contribution corrections and point reversals retain an audit trail.
6. Points appear accurately on the existing user profile and in the detailed transaction history.
7. Public profiles show only authorised contributions and information.
8. Dot.Brain can recommend knowledge for review without bypassing its governance controls.
9. Dot.Memory receives only authorised contextual information and never replaces the points ledger.
10. Dot.Dopemine provides relevant, configurable contribution recommendations and notifications.
11. InfoDot rewards remain consistent with points earned through other Dot platforms.
12. Tenant isolation, permissions, accessibility, performance and relevant automated tests pass.

Inspect the existing InfoDot implementation before changing the architecture. Reuse existing community, question, answer, moderation, identity and profile functionality where appropriate. Do not create duplicate modules or replace working systems unnecessarily.

### Final principle

**Reward knowledge that helps people, solutions that solve real problems, and contributions that make the Dot ecosystem smarter.**

InfoDot must become a meaningful knowledge-sharing channel within the ecosystem, while Dot.Brain governs knowledge improvement, Dot.Memory supplies authorised context, Dot.Dopemine encourages useful participation, and the existing user profile makes earned points and achievements visible.

No fake contributions. No duplicate awards. No unverified claims of impact. Every point must be explainable, auditable and governed by configurable rules.
