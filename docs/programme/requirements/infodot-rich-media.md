# R-15 — Rich multimedia questions and solutions

Owner brief, received 2026-10-30 and stored verbatim below. Scope: evolve Questions and Solutions
(and answers/comments) from plain text into mixed-media submissions — text, PDFs, images, video,
attachments — with secure storage, rich presentation, and AI-assisted understanding where the
platform genuinely supports it. Audit, constraints and the phased implementation record live in
`docs/programme/R15_RICH_MEDIA.md`.

---

InfoDot – Evolve Questions & Solutions into Rich, Multimedia Knowledge-Sharing Experiences
1. Objective
Let's evolve the existing Questions and Solutions functionality in InfoDot to support rich, multimedia-based knowledge sharing.
Currently, users can only submit questions and solutions using plain text. This limits how effectively they can explain business challenges, demonstrate problems, communicate ideas, and share practical knowledge.
We need to transform both experiences so users can communicate using a combination of:

* Text.
* PDF documents.
* Images and photographs.
* Videos.
* Supporting attachments and reference materials.

The objective is to allow users to provide as much relevant context as possible when asking for help or sharing knowledge.
A business owner should be able to explain a problem in writing, attach a financial report, upload photographs of the issue, and include a video demonstrating what is happening.
Likewise, someone sharing a solution should be able to explain their approach, attach implementation documents, upload screenshots, and include videos demonstrating exactly how the solution works.
This should make InfoDot more practical, visual, collaborative, and useful to real business owners.
Do not build this as a separate media-sharing feature. Integrate it naturally into the existing Questions and Solutions experiences.
2. Questions – Rich question creation
Redesign the question creation experience to support mixed-media submissions.
2.1 Question composer
Users should be able to create a question using a rich composer that supports:

* A clear question title.
* Detailed text description.
* PDF uploads.
* Image uploads.
* Video uploads.
* Multiple attachments in one question.
* Text accompanying individual attachments.
* Relevant business categories and tags.
* Optional industry and business-stage context.

Users must be able to combine all supported content types within one submission.
For example:
A construction business owner wants advice about project cost overruns.
They should be able to:

1. Write a description of the problem.
2. Upload a PDF containing their project budget.
3. Attach photographs showing the current project situation.
4. Upload a video explaining where the project is experiencing delays.
5. Explain what they have already tried.
6. Ask the community for specific recommendations.

The result should be one well-structured question containing all the relevant context.
2.2 Attachment experience
Create an intuitive attachment interface that allows users to:

* Upload files using a file picker.
* Drag and drop supported files.
* Preview images before submission.
* Preview PDF documents.
* Preview videos where supported.
* See upload progress.
* Cancel an upload.
* Remove or replace an attachment.
* Add a description or caption to each attachment.
* Reorder attachments where appropriate.

Clearly display attachment types, filenames, file sizes, and upload status.
Do not require users to upload attachments one at a time through separate submission forms.
2.3 Question quality
Encourage users to provide useful context without making question submission unnecessarily complicated.
Where appropriate, offer optional prompts such as:

* What are you trying to achieve?
* What problem are you experiencing?
* When did the problem start?
* What have you tried so far?
* What outcome would you consider successful?

These should assist users in explaining their challenges, not create unnecessary barriers to submitting questions.
3. Solutions – Rich solution creation
Evolve the solution publishing experience using the same multimedia capabilities.
Users must be able to explain solutions through text, documents, images, videos, and supporting attachments.
The objective is to make solutions practical enough that another business owner can understand and potentially implement them.
3.1 Solution composer
Support:

* Solution title.
* Problem being addressed.
* Detailed explanation.
* Step-by-step implementation instructions.
* PDF documents and supporting resources.
* Images and annotated screenshots.
* Videos and demonstrations.
* Templates, checklists, and examples.
* Captions and explanations accompanying attachments.
* Relevant categories, industries, and tags.

3.2 Demonstrating solutions
Allow contributors to show how a solution works rather than relying entirely on written explanations.
Examples:
Financial management solution

* Explain the financial problem.
* Attach an Excel or PDF budgeting template if supported by the existing file policy.
* Upload screenshots demonstrating how to use it.
* Include a video explaining the implementation process.

Marketing solution

* Explain the marketing strategy.
* Upload examples of marketing campaigns.
* Attach supporting documents.
* Include a video demonstrating the process.

Operational solution

* Describe the operational challenge.
* Upload photographs of the process.
* Attach SOP documents.
* Include a demonstration video showing the improved workflow.

3.3 Step-by-step media integration
Allow contributors to associate media with individual implementation steps.
For example:

1. Explain the first implementation step.
2. Attach a screenshot demonstrating it.
3. Explain the second step.
4. Include a video showing how to complete it.
5. Attach a downloadable template or reference document.

This will allow solutions to function more like practical implementation guides rather than ordinary discussion posts.
4. Rich content presentation
The question and solution detail pages must be redesigned to present multimedia content beautifully and consistently.
Images

* Display images clearly without unnecessary cropping.
* Support image enlargement and full-screen viewing.
* Allow users to browse multiple images.
* Preserve image quality where practical.
* Display captions and descriptions.

PDF documents

* Provide an embedded PDF preview where supported.
* Allow users to open documents in a larger viewer.
* Provide a secure download option when permitted.
* Display document names and relevant metadata.
* Avoid forcing users to download a PDF simply to understand its contents.

Videos

* Provide an integrated video player.
* Support playback controls, seeking, and full-screen viewing.
* Display video thumbnails and captions.
* Support subtitles or transcripts where available.
* Avoid automatically playing videos with sound.
* Ensure the experience works on mobile and desktop.

Mixed-media content
The presentation must preserve the order and relationships between text and media.
Do not simply display all uploaded files in a generic attachment list at the bottom of the page.
The content should feel like a structured explanation in which media supports the relevant part of the question or solution.
5. AI-assisted understanding and knowledge extraction
Use InfoDot's existing AI and ecosystem capabilities to make multimedia submissions more valuable.
Inspect the existing Dot.Brains, Dot.Memory, and AI integrations before implementing anything new.
Where technically supported, introduce the following capabilities:
5.1 Understanding uploaded content
When a user submits a question or solution, AI should be able to analyse its supporting material.
For PDFs:

* Extract readable text.
* Identify important information.
* Understand the document's relevance to the question or solution.

For images:

* Identify visible information and relevant context.
* Extract text using OCR where appropriate.
* Understand diagrams, screenshots, and photographs where supported.

For videos:

* Generate or obtain transcripts.
* Identify relevant sections and timestamps.
* Summarise the demonstration.
* Extract useful information from supported visual content.

5.2 Contextual question assistance
Use the submitted text and accessible attachment content to understand what the user is asking.
AI should be able to:

* Identify the main business problem.
* Recognise missing context.
* Suggest useful clarifying questions.
* Recommend relevant existing InfoDot questions and solutions.
* Suggest relevant categories and tags.
* Help users improve the clarity of their submissions.

AI must not automatically publish rewritten content or change a user's intended meaning without their approval.
5.3 Solution knowledge extraction
For solutions, identify:

* The business problem being addressed.
* The proposed approach.
* The implementation steps.
* Required resources.
* Relevant tools.
* Expected outcomes.
* Applicable industries.
* Related business challenges.

Where appropriate, make this structured knowledge available to Dot.Brains through the existing knowledge ingestion and approval processes.
Do not allow AI to invent details that are not supported by the submitted content.
Clearly distinguish between information extracted from attachments and AI-generated interpretations.
6. File management and media infrastructure
Inspect the existing InfoDot file storage and attachment architecture before implementing the feature.
Ensure that media uploads integrate with existing storage and permission mechanisms.
Requirements:

* Secure file storage.
* Proper attachment-to-question and attachment-to-solution relationships.
* Support multiple attachments per submission.
* Efficient file retrieval.
* Secure access controls.
* Upload validation.
* File metadata management.
* Attachment removal and replacement.
* Safe file deletion and lifecycle management.
* Reliable backup and recovery.
* Storage usage tracking.
* Scalable handling of increased media uploads.

Where suitable, use asynchronous processing for large files, video processing, OCR, transcription, and AI analysis.
Do not make a user wait for all background processing to finish before their question or solution can be submitted, provided the file has been securely uploaded and validated.
Show processing states such as:

* Uploading.
* Uploaded.
* Processing.
* Ready.
* Processing failed.

Allow users to retry failed processing where appropriate.
7. Security, privacy and content moderation
Media uploads introduce additional security risks. Treat this as a core requirement, not an optional enhancement.
Implement:

* File-type validation based on actual file content, not just filename extensions.
* File size limits.
* Secure upload handling.
* Malware and malicious-file scanning.
* Safe document previewing.
* Protection against executable and disguised files.
* Secure access to private attachments.
* Permission-aware downloads.
* Protection against unauthorised access to tenant or company files.
* Safe video and image processing.
* Appropriate retention and deletion policies.

Uploaded documents and media may contain confidential business information.
Respect the user's existing visibility and privacy settings. Private business information must never become publicly accessible merely because it was attached to a question or solution.
Do not expose private attachment URLs or storage paths.
Treat extracted document text, images, video transcripts, and other uploaded content as untrusted input. Never allow instructions embedded inside uploaded files to override system instructions, application permissions, or AI security controls.
Implement appropriate reporting and moderation mechanisms for inappropriate or harmful uploaded content.
8. User experience and design requirements
The experience must feel like a natural evolution of InfoDot, not a collection of disconnected upload controls.
Design principles:

* Clean, modern, minimal, and professional.
* Consistent with InfoDot's existing visual identity.
* Simple for non-technical business owners.
* Responsive on desktop, tablet, and mobile.
* Clear visual distinction between questions and solutions.
* Intuitive media attachment controls.
* Smooth upload and processing feedback.
* Accessible keyboard and screen-reader interactions.
* Excellent loading, empty, error, and success states.

Avoid cluttering the composer with too many buttons.
Use a clear primary writing area and an intuitive attachment toolbar.
Make it obvious that users can combine text, documents, images, and videos in a single submission.
Do not sacrifice readability for visual effects.
9. Discovery, engagement and community experience
Multimedia content should improve how users discover and interact with knowledge.
Update the Questions and Solutions feeds to:

* Display appropriate attachment previews.
* Indicate whether a post contains documents, images, or videos.
* Support filtering by content type where useful.
* Preserve existing search, category, and tag functionality.
* Allow users to open questions and solutions with their media intact.
* Support existing commenting and response workflows.

Users responding to questions should also be able to include relevant media where appropriate, so answers can contain supporting documents, images, and video demonstrations.
Ensure that attachment permissions are respected throughout comments, replies, sharing, and other interaction flows.
10. Performance and scalability
Design the implementation for a growing business community and potentially millions of users.
Consider:

* Resumable or chunked uploads for large files.
* Background processing queues.
* Image optimisation and thumbnails.
* Video transcoding where appropriate.
* Efficient PDF preview generation.
* CDN delivery for authorised media.
* Caching.
* Database indexing.
* Storage lifecycle management.
* Rate limiting.
* Upload quotas and abuse prevention.
* Monitoring and processing failure alerts.

Do not load every attachment in full when a feed page is opened.
Use thumbnails, lazy loading, pagination, and on-demand media retrieval.
Avoid introducing infrastructure that is unnecessarily expensive or complex. Choose an architecture that fits the existing application and can scale as actual usage grows.
11. Testing and acceptance criteria
Do not consider this feature complete merely because an upload button appears.
Test the complete user journey.
Questions

* A user can submit a text-only question.
* A user can submit a question with a PDF.
* A user can submit a question with images.
* A user can submit a question with videos.
* A user can combine text, PDFs, images, and videos.
* A user can preview, remove, and replace attachments.
* Uploaded media remains correctly associated with the question.
* Other authorised users can view the submitted content.

Solutions

* A user can publish a text-only solution.
* A user can publish a solution with supporting documents.
* A user can include multiple images and videos.
* A user can associate media with individual implementation steps.
* Other authorised users can view and access the supporting materials.

Security

* Unauthorised users cannot access private attachments.
* Invalid or malicious files are rejected or quarantined.
* Users cannot access another tenant's private media.
* File deletion and permissions work correctly.

AI and processing

* Supported PDFs can be processed.
* Supported images can be analysed.
* Video transcription and analysis work where enabled.
* Processing failures are visible and recoverable.
* AI does not invent content from inaccessible or unreadable attachments.

Performance

* Uploads work reliably on mobile and desktop.
* Large files are handled according to configured limits.
* Feed pages remain responsive.
* Background processing does not block normal application use.

Include automated tests, integration tests, and end-to-end tests for the critical workflows.
12. Implementation approach
Before writing code:

1. Inspect the existing Questions and Solutions pages.
2. Understand their current database structure and publishing workflows.
3. Inspect existing media upload and file storage capabilities.
4. Identify reusable components and services.
5. Review current authentication, permissions, and tenant isolation.
6. Inspect existing AI, Dot.Brains, and Dot.Memory integrations.
7. Identify missing functionality and technical constraints.

Then implement the evolution in logical stages:
Phase 1: Rich multimedia question and solution composers.
Phase 2: Secure multi-file upload, storage, and attachment management.
Phase 3: Rich content viewers for PDFs, images, and videos.
Phase 4: Media-enabled comments and responses.
Phase 5: AI-assisted document extraction, image understanding, and video transcription.
Phase 6: Knowledge extraction, search, discovery, and Dot.Brains integration.
Phase 7: Security hardening, performance optimisation, automated testing, and production readiness.
Work with the existing application architecture and coding standards. Avoid unnecessary rewrites or duplicate systems.
Do not use mock uploads, fake processing results, placeholder media, or simulated AI analysis in production functionality.
If a capability requires an external service, identify the dependency, configuration, cost implications, and fallback behaviour before integrating it.
13. Final expected outcome
Transform InfoDot Questions and Solutions into a rich multimedia knowledge-sharing platform where users can:

* Ask questions with detailed written explanations and supporting evidence.
* Upload business documents to provide context.
* Share photographs and screenshots to demonstrate challenges.
* Upload videos to explain problems.
* Publish detailed solutions supported by documents and demonstrations.
* Teach others through step-by-step multimedia guides.
* Share practical templates and downloadable resources.
* Receive more contextually relevant answers.
* Build a reusable knowledge base from real business experiences.

The ultimate objective is to make InfoDot a place where business owners don't just describe problems and read answers — they can show what is happening, understand what needs to change, and demonstrate exactly how to implement a solution.
Begin with a complete audit of the existing implementation, then deliver the feature end-to-end. Provide a clear implementation report covering completed functionality, database changes, storage architecture, security controls, AI integrations, tests, and any remaining dependencies.
