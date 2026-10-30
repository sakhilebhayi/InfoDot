# R-16 — Questions & Solutions evolution

Owner brief, received 2026-10-30 and stored verbatim below. Scope: community validation ("I tried
this and it works"), mentions and recommendations, duplicate detection, collaborative solution
improvements with versioning and dual attribution, global search optimisation, and ecosystem
integration. Audit, design decisions and the implementation record live in
`docs/programme/R16_QS_EVOLUTION.md`.

---

INFODOT – QUESTIONS & SOLUTIONS EVOLUTION
Community Validation, Intelligent Recommendations, Duplicate Prevention, Collaborative Knowledge Improvement & Enterprise Search Optimisation
1. Project objective
Let's evolve InfoDot's existing Questions and Solutions functionality into an intelligent, collaborative, and community-validated business knowledge platform.
The objective is to move beyond a conventional question-and-answer system and build an environment where business owners can:

* Share real business challenges.
* Find people and businesses with relevant experience.
* Discover existing solutions before creating duplicate content.
* Confirm whether solutions have actually worked in practice.
* Recommend relevant people, businesses, and existing knowledge.
* Improve existing solutions collaboratively.
* Track how business knowledge evolves over time.
* Contribute meaningful experience that strengthens InfoDot's collective knowledge.

Introduce the following interconnected capabilities:

1. "I tried this and it works" – Users can confirm that they have personally implemented a solution and achieved positive results.
2. Intelligent mentions and recommendations – Users can mention individuals and businesses they believe can provide relevant answers.
3. Existing solution recommendations – Users can recommend relevant published solutions when responding to questions.
4. Intelligent duplicate detection – Identify similar questions and solutions before users publish new content.
5. Collaborative solution improvements – Allow users to propose improvements to approved solutions, subject to review and approval by the original contributor.
6. Version history and contributor recognition – Preserve original authorship while recognising users who contribute approved improvements.
7. Global search optimisation – Introduce debouncing, efficient retrieval, semantic discovery, and scalable search across InfoDot.

These features must work together as part of one coherent knowledge-sharing experience.
Do not implement them as isolated social features or create unnecessary parallel systems.
2. Solutions Page – "I tried this and it works"
Introduce a prominent "I tried this and it works" interaction on every published solution.
The purpose is to distinguish solutions that people simply like from solutions that people have actually implemented and found effective.
2.1 User interaction
Each solution should display:

* An "I tried this and it works" button.
* A visible count of users who have confirmed success.
* An indication that the count represents implementation confirmations.
* A list of users who have confirmed the solution, subject to privacy settings.

When a user selects the button:

1. Record their confirmation against that specific solution.
2. Update the visible count immediately.
3. Provide clear feedback that their confirmation has been registered.
4. Prevent the same user from confirming the same solution multiple times.
5. Allow users to withdraw their confirmation if they change their mind.

Use an appropriate toggle interaction so users can select and deselect the confirmation.
2.2 Distinguish validation from popularity
Do not treat this as an ordinary social media Like button.
A confirmation means:
"I have tried this solution in a real situation, and it worked for me."
It does not mean that the solution will work for every business.
Keep the following metrics separate:

* Views.
* Likes or general reactions, if already supported.
* Bookmarks or saves.
* "I tried this and it works" confirmations.
* Comments and discussions.
* Approved improvements.

Never combine these into one popularity score.
2.3 Optional implementation feedback
After confirming a solution, optionally invite the user to provide additional context:

* What type of business did you apply this to?
* Which part of the solution did you implement?
* What results did you achieve?
* How long did it take to see results?
* Would you recommend this approach to another business owner?

Do not force users to complete a feedback form before confirming a solution.
Allow users to provide feedback later or skip it entirely.
2.4 Validation transparency
Display the number of successful implementation confirmations clearly.
Where sufficient feedback exists, provide useful context such as:

* Number of businesses that tried the solution.
* Industries represented.
* Business sizes represented.
* Commonly reported outcomes.
* Variations in implementation experiences.

Only display aggregated insights when there is sufficient data to avoid misleading users or exposing private information.
Do not imply that a solution has been independently verified simply because users clicked the button.
Clearly distinguish between community-reported success and independently verified evidence.
3. Questions Page – Intelligent Mentions and Recommendations
Upgrade the Questions experience to allow users to involve relevant people, businesses, and knowledge contributors.
A business owner should be able to ask a question and directly recommend someone they believe has the experience or expertise to answer it.
3.1 @mention functionality
Introduce an intelligent mention system using the familiar `@` interaction.
When a user types `@` in a question, answer, or supported comment field, display an autocomplete search interface.
Allow users to search for and mention:

* Individual InfoDot users.
* Registered businesses.
* Relevant business contributors.
* Industry experts and experienced professionals.
* Organisations with relevant expertise.

Suggestions should display useful identification details, such as:

* User or business name.
* Profile image or logo.
* Relevant industry.
* Areas of expertise.
* Business category.
* Relevant experience, where available.

Avoid displaying unnecessary private information.
Mentions must resolve to actual platform identities rather than remain plain text.
3.2 Mentioning people
Example:
"@John Smith, you've previously worked with this type of inventory management system. Could you share how you approached this?"
The mentioned person should:

* Receive an appropriate notification.
* Be able to open the question directly.
* Be able to respond if they have permission.
* Be able to ignore or dismiss the mention.
* Have appropriate notification preferences.

3.3 Mentioning businesses
Users should also be able to mention businesses they believe have relevant experience.
Example:
"@ABC Construction, your experience with project cost management could help us understand how to solve this problem."
The mentioned business should receive a notification through its authorised representatives or configured business notification channels.
Do not automatically notify every employee belonging to a business.
Respect business membership, notification permissions, and tenant boundaries.
3.4 Recommending existing answers and solutions
Allow users to recommend an existing InfoDot solution when responding to a question.
The user should be able to search and select an existing solution without manually copying its URL.
Display the recommended solution as a linked content reference containing:

* Solution title.
* Short description.
* Author.
* Relevant category.
* Existing community validation count, where available.

A recommendation should link the original solution rather than create a duplicate.
3.5 Intelligent recommendation suggestions
Where appropriate, use InfoDot's existing knowledge and recommendation capabilities to suggest:

* People who have successfully contributed to similar questions.
* Businesses with relevant experience.
* Existing approved solutions.
* Relevant discussions and knowledge resources.

Suggestions must be based on actual available information, not invented expertise or arbitrary recommendations.
Do not rank users solely by activity or popularity.
Consider relevant experience, accepted answers, demonstrated contributions, industry relevance, and community feedback.
4. Questions and Solutions – Intelligent Duplicate Detection
Introduce duplicate detection into both Questions and Solutions publishing workflows.
The objective is to prevent the same business problem or solution from being repeatedly published while helping users discover existing knowledge.
This must be more sophisticated than comparing titles or checking for identical text.
4.1 When to check for duplicates
Run duplicate detection when users:

* Enter a question title.
* Add or update a question description.
* Begin creating a solution.
* Edit the content before publishing.
* Submit the final post.

Use efficient checks at appropriate points. Avoid repeatedly running expensive AI operations on every keystroke.
4.2 Similarity detection
Compare new submissions against existing content using a combination of:

* Text similarity.
* Semantic similarity.
* Keywords and business terminology.
* Problem descriptions.
* Industry and business categories.
* Related tags.
* Relevant attachment content where extraction is available.

The system must understand that differently worded questions can describe the same problem.
Example:

* "How can I stop running out of money before month-end?"
* "My business makes sales but I cannot pay my suppliers."
* "How do I improve cash flow in my small business?"

These may represent closely related business problems.
However, do not automatically classify every related question as a duplicate.
Distinguish between:

1. Exact duplicates.
2. Highly similar questions.
3. Related questions that address different aspects of the same problem.
4. Completely new questions.

Apply the same logic to solutions.
4.3 Duplicate detection interface
Before publishing, show a clear section:
We found some existing content that may help you.
Display relevant existing questions or solutions with:

* Title.
* Short description.
* Author.
* Category.
* Date published.
* Relevant engagement or validation information.

Allow users to open and review suggested content without losing their draft.
Give them the option to:

* Use an existing answer or solution.
* Follow or contribute to an existing discussion.
* Improve an existing solution.
* Continue publishing their new question or solution if it genuinely adds something different.

4.4 Do not unnecessarily block publishing
Duplicate detection should guide users, not frustrate them.
Do not automatically reject submissions simply because they are similar to existing content.
Allow users to explain why their situation is different or why a separate post is necessary.
Where a submission is extremely similar to an existing post, make the warning more prominent and encourage reuse.
Where content is only related, display recommendations without suggesting that the user is creating a duplicate.
4.5 Knowledge consolidation
Where multiple questions address the same problem, consider allowing authorised moderators or administrators to link related discussions or consolidate duplicates.
Preserve:

* Original authorship.
* Original contributions.
* Discussion history.
* Attachments.
* Relevant notifications.
* Links to the original content.

Never silently delete or overwrite user contributions during consolidation.
5. Solutions Page – Collaborative Solution Improvements
Introduce a capability that allows community members to propose improvements to existing approved solutions.
The objective is to ensure that InfoDot's knowledge continuously evolves as users discover better, simpler, cheaper, safer, or more effective ways to solve problems.
An approved solution should not become permanently static.
However, improvements must not be published automatically. The original solution author must retain the ability to review and approve proposed changes.
5.1 "Suggest an improvement" action
Add a clearly visible "Suggest an improvement" action to approved solutions.
When selected, open a structured improvement submission interface.
Allow users to propose:

* Simplifying an existing implementation step.
* Adding missing information.
* Correcting inaccurate information.
* Adding a better method.
* Introducing a more cost-effective approach.
* Providing additional examples.
* Adding supporting documents, images, or videos.
* Improving the solution for a particular industry.
* Updating outdated information.
* Highlighting risks or limitations.

Users should be able to explain why the proposed change makes the solution better.
5.2 Improvement submission structure
Each improvement proposal should contain:

1. The existing section or step being improved.
2. The proposed replacement or addition.
3. An explanation of why the change is beneficial.
4. Supporting evidence or references, where relevant.
5. Optional PDF, image, video, or other supported attachments.
6. Optional implementation experience or examples.

Where practical, display the original content alongside the proposed changes so the author can easily understand what is being suggested.
5.3 Author notification and approval
When an improvement is submitted:

1. Create a pending improvement proposal.
2. Notify the original solution author.
3. Provide a direct link to review the proposal.
4. Display the original content and proposed changes side by side.
5. Allow the author to approve, reject, or request clarification.
6. Record the decision and relevant timestamps.

The improvement must remain unpublished until it has been approved.
The original approved solution must remain unchanged while an improvement is pending.
5.4 Improvement review workflow
Provide the original author with three actions.
Approve

* Accept the proposed improvement.
* Apply the approved changes to the solution.
* Record the new solution version.
* Attribute the contribution to the person who proposed the improvement.
* Notify both contributors.

Reject

* Keep the existing approved solution unchanged.
* Record the rejection decision.
* Notify the proposer respectfully.
* Allow an optional explanation.

Request clarification

* Ask the proposer to revise or explain the suggested improvement.
* Keep the proposal pending.
* Allow further discussion between the two contributors.

5.5 Version history
Implement version control for approved solutions.
Every approved improvement must create a new version rather than silently overwrite the previous version.
Maintain:

* Original solution.
* Version number.
* Previous content.
* Updated content.
* Original author.
* Improvement contributor.
* Approval date.
* Change summary.
* Supporting references.
* Approval history.

Allow users to view the solution's improvement history where appropriate.
Provide a way for authorised users to inspect previous versions.
Avoid automatically replacing the current version with an older version without an explicit authorised action.
5.6 Dual contributor recognition
Both contributors must be recognised on the solution.
Display clear attribution such as:
Originally contributed by: Original Author
Improved by: Improvement Contributor
If multiple improvements have been approved, recognise all relevant contributors through a contribution history rather than displaying an increasingly long list of names in the main header.
The original author should retain original authorship.
The improvement contributor should receive visible recognition for their approved contribution.
Do not transfer ownership of the solution simply because another user improved it.
5.7 Multiple pending improvements
Support multiple improvement proposals without allowing conflicting updates to corrupt the approved solution.
If multiple users propose changes to the same section:

* Keep each proposal separate.
* Allow the author to compare proposals.
* Detect when a proposal is based on an outdated solution version.
* Require re-evaluation if another improvement changes the same content.
* Prevent conflicting proposals from being applied simultaneously without review.

6. Global Search Optimisation – Debouncing, Efficiency and Scalability
Objective
Audit and optimise every search bar across InfoDot, with particular attention to Questions and Solutions.
Search must be responsive, intelligent, and capable of handling a growing number of users and a large knowledge library without unnecessarily exhausting server resources.
The goal is to protect server resources while delivering an immediate and useful search experience.
Do not implement search optimisation only on the Solutions page. Apply consistent search behaviour across all applicable search interfaces throughout InfoDot.
6.1 Debouncing on all search bars
Implement client-side debouncing on every search input that triggers server requests.
Requirements:

* Introduce a configurable debounce delay, initially around 300–500 milliseconds.
* Do not send a request for every individual keystroke.
* Wait until the user pauses typing before triggering a search.
* Cancel or disregard outdated requests when a newer search is initiated.
* Prevent stale results from overwriting newer search results.
* Avoid duplicate requests for identical search terms.
* Do not execute searches for empty input unless the interface specifically requires it.
* Support keyboard navigation and accessible search suggestions.
* Provide clear loading, empty, and error states.
* Ensure consistent behaviour on desktop and mobile.

Apply debouncing to:

* Questions search.
* Solutions search.
* @mention autocomplete.
* Business and user discovery.
* Existing solution recommendations.
* Duplicate detection searches.
* Relevant category and tag search interfaces.
* Any other search input that triggers server-side retrieval.

Do not apply unnecessary debounce delays to local-only filtering that does not trigger server requests.
6.2 Optimise Solutions search
The Solutions search experience must be capable of finding relevant content quickly, even as the number of published solutions increases.
Search should consider:

* Solution titles.
* Descriptions.
* Detailed solution content.
* Categories and subcategories.
* Tags and keywords.
* Industries.
* Business types.
* Implementation steps.
* Relevant extracted text from approved PDF attachments.
* Relevant transcripts from processed videos, where available.

Support semantic search where the existing architecture allows it, so users can find solutions even when they describe a problem differently from the wording used in the original solution.
Example:
A user searches:
"My business has no money left before payday."
The system should be able to discover relevant solutions about:

* Cash flow management.
* Working capital.
* Accounts receivable.
* Financial forecasting.
* Supplier payment planning.

Prioritise relevance over simply returning the newest or most popular content.
6.3 Optimise Questions search
Apply the same search intelligence to existing questions.
Allow users to discover questions based on:

* Problem descriptions.
* Similar wording.
* Business challenges.
* Industry.
* Categories and tags.
* Relevant attachment content where processing is available.

Integrate search results with duplicate detection so users can discover existing discussions before publishing new questions.
6.4 Server and database optimisation
Inspect the current search implementation and identify inefficient database queries, unnecessary API calls, and expensive AI operations.
Implement appropriate optimisations, including:

* Indexed database columns.
* Full-text search where suitable.
* Efficient query construction.
* Pagination or cursor-based result loading.
* Result limits.
* Appropriate caching.
* Query deduplication.
* Request cancellation or stale-response protection.
* Efficient attachment metadata searches.
* Rate limiting and abuse prevention.

Do not retrieve the entire question or solution library for every search.
Do not run expensive AI or embedding operations on every keystroke.
Where semantic search is required, use a staged approach:

1. Apply the client-side debounce.
2. Perform efficient initial retrieval.
3. Rank relevant candidates using suitable search mechanisms.
4. Apply semantic ranking where beneficial.
5. Return a limited set of relevant results.

Use background processing to generate and maintain searchable representations of content when appropriate.
6.5 Search experience
The search interface should:

* Show relevant suggestions as users type.
* Display useful titles and short descriptions.
* Identify whether a result is a question or solution.
* Display relevant categories.
* Indicate relevant media where available.
* Allow users to open results without losing their current workflow.
* Support pagination or progressive loading.
* Preserve search terms when users navigate back.

Use skeleton loaders or subtle loading indicators where appropriate.
Do not make the interface feel slow simply because the system is protecting server resources.
6.6 Configurability and observability
Make debounce delays, result limits, and other search parameters configurable where appropriate.
Monitor:

* Search request volume.
* Search response times.
* Database query performance.
* Cache hit rates.
* Search failures.
* Semantic search latency.
* AI processing costs.
* Search behaviour under concurrent users.

Use this information to identify bottlenecks and optimise actual usage patterns.
6.7 Search testing and acceptance criteria
Verify that:

* All applicable server-backed search bars use debouncing.
* Rapid typing does not trigger a request for every keystroke.
* Previous requests cannot overwrite newer results.
* Identical searches do not generate unnecessary duplicate work.
* Empty searches are handled correctly.
* Solutions can be found using relevant alternative wording.
* Questions can be discovered through semantic similarity where supported.
* Search results are properly paginated.
* Search remains responsive under concurrent usage.
* Database and AI resources are not unnecessarily consumed.

Test with realistic volumes of questions, solutions, and attachments.
Performance objective: Search should feel immediate to users while remaining efficient, predictable, and scalable on the server.
Do not claim performance improvements without benchmarking before and after implementation.
7. Notifications and Collaboration
Integrate all new interactions into InfoDot's existing notification system.
Notifications should be generated for:

* Being mentioned in a question.
* Being mentioned in a discussion or answer.
* Having a business mentioned.
* Receiving a recommended solution.
* Receiving an improvement proposal.
* Having an improvement approved.
* Having an improvement rejected.
* Being asked to clarify a proposal.
* Receiving relevant responses to a question.

Notifications must:

* Link directly to the relevant content.
* Identify who initiated the action.
* Explain why the user is receiving the notification.
* Respect notification preferences.
* Avoid unnecessary duplicate notifications.
* Support appropriate in-app and configured external notification channels.

For businesses, notify authorised representatives rather than all members by default.
8. Dot.Brains, Dot.Memory and Dot.Dopemine Integration
Inspect the existing ecosystem integrations and use them where appropriate.
8.1 Dot.Brains
Use Dot.Brains to:

* Identify semantically related questions and solutions.
* Connect related business knowledge.
* Support duplicate detection.
* Discover relevant existing solutions.
* Incorporate approved improvements into the knowledge graph.
* Preserve the provenance of original and improved knowledge.

Only approved changes should enter the trusted knowledge base.
Pending and rejected proposals must not silently become trusted knowledge.
8.2 Dot.Memory
Where appropriate, use Dot.Memory to:

* Maintain contributor history.
* Recognise users' areas of demonstrated expertise.
* Understand business context where users have authorised its use.
* Track solution improvement history.
* Improve contextual recommendations.

Do not expose private business information through public recommendations.
8.3 Dot.Dopemine
Where the existing reward architecture supports it, recognise meaningful contributions such as:

* Successfully validated solutions.
* Approved solution improvements.
* Helpful recommendations.
* High-quality answers.
* Useful community contributions.

Reward quality and meaningful participation, not simply the number of clicks or mentions.
Introduce appropriate anti-abuse protections to prevent users from manipulating community scores.
All reward rules must be transparent and consistent with the existing InfoDot ecosystem scoring system.
9. Database, Architecture and Data Integrity
Before implementing, inspect the existing database and application architecture.
Identify existing models and relationships for:

* Questions.
* Solutions.
* Users.
* Businesses.
* Answers.
* Comments.
* Reactions.
* Notifications.
* Categories.
* Attachments.
* User contributions.
* Knowledge records.
* Search infrastructure.

Extend existing structures where possible.
Avoid creating duplicate tables or parallel systems where suitable functionality already exists.
Ensure the data model can support:

* Unique solution validation records.
* User-to-solution confirmation relationships.
* Mentions and mention notifications.
* Business mentions.
* Recommended solution references.
* Duplicate detection results.
* Improvement proposals.
* Improvement approval decisions.
* Solution version history.
* Contributor attribution.
* Auditable activity records.
* Search indexing and searchable content representations.

Use database transactions and appropriate constraints to protect data integrity.
Prevent duplicate confirmations, duplicate notifications, conflicting version updates, and unauthorised modifications.
Ensure all operations respect InfoDot's existing company and tenant isolation.
10. Security, Moderation and Abuse Prevention
These capabilities introduce new opportunities for spam, manipulation, harassment, and misleading business information.
Implement appropriate controls.
Validation abuse

* Prevent multiple confirmations from the same user.
* Do not allow fake confirmation counts.
* Detect suspicious repeated activity.
* Preserve a reliable audit trail.

Mention abuse

* Rate-limit excessive mentions.
* Prevent users from mass-mentioning unrelated people or businesses.
* Respect user notification and privacy settings.
* Provide blocking and reporting mechanisms where supported.

Duplicate abuse

* Avoid using duplicate detection as an unrestricted source of private content.
* Respect visibility permissions when returning suggestions.
* Prevent one tenant's private content from being surfaced to another tenant.

Improvement abuse

* Prevent unauthorised users from directly modifying approved solutions.
* Protect the original version during review.
* Preserve approval history.
* Allow users to report harmful or misleading proposals.

Search abuse

* Apply appropriate rate limits.
* Prevent automated search abuse.
* Limit expensive semantic-search operations.
* Protect search endpoints from enumeration and excessive requests.
* Cache suitable public search results without leaking private information.

Content moderation

* Provide appropriate moderation and reporting mechanisms.
* Flag potentially harmful, fraudulent, or dangerous advice.
* Apply additional review to high-risk legal, medical, financial, and safety-related recommendations.

11. User Experience and Design
Maintain InfoDot's established design identity.
The experience should be:

* Clean.
* Professional.
* Minimal.
* Intuitive.
* Responsive.
* Consistent across desktop and mobile.

Do not make the pages look like generic social media platforms.
The focus must remain on business knowledge, credibility, and useful collaboration.
Design the following interfaces carefully:

1. Solution validation button and confirmation count.
2. Confirmation contributor list.
3. Mention autocomplete interface.
4. Business and individual recommendation cards.
5. Duplicate detection suggestions.
6. Existing content comparison.
7. Suggest an improvement interface.
8. Improvement review and approval interface.
9. Solution version history.
10. Contributor attribution display.
11. Search suggestions and results.
12. Search loading, empty, and error states.
13. Notification experiences.

Every interaction must have proper loading, success, empty, and error states.
Avoid unnecessary animations, visual clutter, and excessive interface controls.
12. Testing and Acceptance Criteria
Do not consider the work complete merely because the interfaces exist.
Test the complete workflows.
12.1 Solution validation

* A user can confirm that a solution worked.
* The confirmation count updates correctly.
* The same user cannot create multiple confirmations.
* A user can withdraw their confirmation.
* Confirmation records remain accurate after refreshing the page.

12.2 Mentions and recommendations

* Users can search and mention other users.
* Users can search and mention registered businesses.
* Mentions create the correct notifications.
* Users can recommend existing solutions.
* Recommended solutions link to the correct original content.
* Private users and businesses are not exposed improperly.

12.3 Duplicate detection

* Exact duplicates are identified.
* Semantically similar content is suggested.
* Related but distinct questions are not incorrectly blocked.
* Users can open suggested content without losing drafts.
* Users can continue publishing when appropriate.
* Duplicate checks respect tenant visibility.

12.4 Solution improvements

* Users can propose changes to approved solutions.
* The original author receives a notification.
* Pending proposals do not change approved content.
* The original author can approve, reject, or request clarification.
* Approved improvements create new versions.
* Original and improvement contributors receive correct attribution.
* Conflicting improvements cannot overwrite each other.
* Previous versions remain accessible.

12.5 Search performance

* All applicable search bars use debouncing.
* Requests are not sent for every keystroke.
* Stale requests cannot overwrite current results.
* Identical searches do not create unnecessary work.
* Search results are relevant and correctly paginated.
* Search supports alternative wording where semantic search is enabled.
* Search works correctly on desktop and mobile.
* Server response times are measured.
* Database load is measured.
* Search remains reliable under concurrent usage.

12.6 Ecosystem integration

* Only approved knowledge changes are promoted to trusted knowledge.
* Contributor records are accurate.
* Rewards cannot be manipulated through repeated or automated interactions.

Include automated unit tests, feature tests, integration tests, and end-to-end tests.
13. Implementation Phases
Implement in the following order.
Phase 1 – Community validation

* "I tried this and it works" functionality.
* Unique confirmation records.
* Confirmation counts and contributor visibility.

Phase 2 – Mentions and recommendations

* Individual mentions.
* Business mentions.
* Mention autocomplete.
* Existing solution recommendations.
* Notification integration.

Phase 3 – Duplicate detection

* Similarity analysis.
* Suggested existing questions and solutions.
* Publishing warnings.
* Duplicate-safe submission workflows.

Phase 4 – Collaborative improvements

* Improvement proposals.
* Author notifications.
* Review and approval workflow.
* Rejection and clarification.
* Dual contributor attribution.

Phase 5 – Solution versioning

* Version history.
* Change comparisons.
* Conflict detection.
* Historical version access.

Phase 6 – Global search optimisation

* Audit every search bar.
* Implement consistent debouncing.
* Improve database queries and indexing.
* Implement search result caching.
* Optimise search pagination.
* Introduce semantic discovery where supported.
* Implement search monitoring and performance benchmarks.

Phase 7 – Ecosystem intelligence

* Dot.Brains integration.
* Dot.Memory contributor intelligence.
* Dot.Dopemine contribution rewards.
* Improved knowledge recommendations.

Phase 8 – Production readiness

* Security review.
* Performance optimisation.
* Abuse prevention.
* Automated testing.
* Monitoring and deployment validation.

14. Development and Implementation Rules
Before writing code:

1. Inspect the complete existing Questions and Solutions implementation.
2. Understand the current application architecture, database, and relationships.
3. Identify reusable components, services, and workflows.
4. Inspect authentication, permissions, and tenant isolation.
5. Review the existing notification architecture.
6. Review file storage and attachment handling.
7. Inspect existing search functionality and identify bottlenecks.
8. Review Dot.Brains, Dot.Memory, and Dot.Dopemine integrations.
9. Identify dependencies, missing functionality, and technical constraints.
10. Produce a concise implementation plan based on actual findings.

Then implement the features end-to-end.
Follow these rules:

* Do not create mock functionality.
* Do not introduce fake users, fake confirmations, or fake engagement.
* Do not use placeholder search results.
* Do not simulate AI processing as if it were real.
* Do not create duplicate systems where existing infrastructure can be extended.
* Do not break existing Questions and Solutions functionality.
* Do not compromise tenant isolation.
* Do not overwrite approved solutions without an authorised approval.
* Do not introduce expensive external services without evaluating alternatives and costs.
* Do not claim a feature is complete without testing its actual workflow.
* Do not leave critical features as frontend-only implementations.

If external services are required, document their purpose, configuration, costs, security implications, and fallback behaviour.
If infrastructure is missing, implement the smallest appropriate solution consistent with the existing architecture.
15. Final Expected Outcome
InfoDot should become a platform where business knowledge is continuously tested, improved, connected, and validated by the people who actually use it.
A business owner should be able to:

* Find a solution to a business problem.
* See how many people have actually tried it and found it useful.
* Understand which businesses or industries have benefited from it.
* Recommend someone with relevant experience to answer a question.
* Discover existing knowledge before creating duplicate content.
* Suggest a better way to implement an existing solution.
* Receive recognition when their improvement is approved.
* Follow the evolution of a solution over time.
* Find relevant knowledge quickly, even when they describe their problem differently.
* Participate in a knowledge ecosystem that rewards meaningful contributions.

The original contributor remains recognised, while the community helps make their knowledge better.
The guiding principle
InfoDot should not simply collect questions and answers. It should build a living body of business knowledge that becomes more useful, reliable, searchable, and practical every time someone contributes meaningful experience.
Begin with a complete audit of the existing implementation. Implement the entire evolution using the existing architecture wherever possible, verify each workflow with automated and manual testing, and provide a final implementation report covering:

* Completed features.
* Database and migration changes.
* New and modified services.
* Search architecture and performance benchmarks.
* Notification integrations.
* Dot ecosystem integrations.
* Security and permission controls.
* Automated test results.
* Deployment requirements.
* Remaining dependencies and limitations.

Completion standard: Every feature must work end-to-end in the actual InfoDot application. A visually complete interface without functioning backend logic, permissions, persistence, and testing is not an acceptable implementation.
