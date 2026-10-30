# R-17 — Enterprise readiness, security hardening and market launch

Owner brief, received 2026-10-30 and stored verbatim below. Scope: a full re-audit and remediation
of the platform toward market launch — security, privacy/POPIA, UI/UX, content, performance, SEO
and AI discoverability, commercial readiness, and an evidence-based readiness score under the
weighted model in §19. The run record and report live in `docs/programme/R17_LAUNCH_READINESS.md`
and `docs/programme/enterprise/`.

---

# INFODOT – ENTERPRISE READINESS, SECURITY HARDENING & MARKET LAUNCH MASTER PROMPT

## 1. Mission and objective

We have significantly expanded InfoDot's functionality. It is now time to transform the platform into a fully enterprise-ready, commercially viable product that is ready to be introduced to the market.

This is not a routine code review, UI refresh, or security scan.

Conduct a comprehensive, end-to-end evaluation of the entire InfoDot platform, covering every aspect of its architecture, functionality, content, user experience, security, infrastructure, integrations, performance, scalability, compliance, discoverability, and commercial readiness.

The objective is to establish whether InfoDot can confidently be:

- Introduced to the public market.
- Sold to individuals, startups, SMEs, and established businesses.
- Used by organisations with multiple employees and business units.
- Trusted with confidential business information.
- Scaled to support substantial user and business growth.
- Operated reliably in a production environment.
- Maintained and developed without compromising security or stability.
- Presented as a credible, professional enterprise product.

**Do not assume InfoDot is enterprise-ready simply because features have been implemented. Every feature must be inspected, exercised, verified, and tested.**

The final product must be functional, secure, polished, reliable, scalable, discoverable, and commercially presentable.

## 2. Non-negotiable development principles

Follow these principles throughout the audit and implementation:

1. Inspect the actual codebase before making changes.
2. Use Laravel Boost to understand, inspect, develop, debug, and verify Laravel functionality.
3. Inspect every relevant route, controller, Livewire component, service, model, database table, migration, middleware, job, event, API endpoint, integration, and frontend component.
4. Test actual application workflows, not just the existence of code.
5. Never assume that a feature works because its interface exists.
6. Do not introduce mock functionality, fake data, simulated integrations, or placeholder production behaviour.
7. Do not unnecessarily rewrite functioning systems.
8. Preserve existing functionality while implementing improvements.
9. Do not remove features simply because they are difficult to fix.
10. Do not expose secrets, credentials, confidential information, or internal system details.
11. Do not claim security, compliance, or performance improvements without evidence.
12. Do not mark an audit item as complete until its acceptance criteria have been verified.
13. Maintain a record of all changes, migrations, configuration changes, and security decisions.
14. Test after every significant implementation stage.
15. Never sacrifice security or data integrity to achieve a faster implementation.

Use the existing technology stack and architecture wherever appropriate. Follow established Laravel, Livewire, Tailwind, and Laravel Boost conventions.

---

# 3. Complete platform discovery and baseline audit

Before modifying anything, conduct a complete audit of the existing InfoDot application.

Understand the entire product, including:

- Public-facing website.
- Landing pages.
- Registration and authentication.
- User onboarding.
- User profiles.
- Business profiles.
- Company and tenant management.
- Questions.
- Solutions.
- Multimedia uploads.
- Comments and answers.
- Mentions and recommendations.
- Solution validation.
- Collaborative solution improvements.
- Solution version history.
- Search and duplicate detection.
- Notifications.
- Business knowledge discovery.
- AI-assisted functionality.
- Dot ecosystem integrations.
- Rewards and contribution scoring.
- Subscription management.
- Billing and payment workflows.
- Administration.
- Settings and preferences.
- APIs and external integrations.
- Background jobs and scheduled tasks.
- Email and notification delivery.
- File storage.
- Reporting and analytics.
- Account management and privacy controls.

Inspect the database schema and determine whether relationships, constraints, indexes, and data ownership rules are correctly implemented.

Identify:
- Incomplete features.
- Broken workflows.
- Dead routes.
- Unused components.
- Duplicate functionality.
- Missing validations.
- Security weaknesses.
- Performance bottlenecks.
- Poorly designed interfaces.
- Inconsistent terminology.
- Missing content.
- Incorrect business logic.
- Unhandled errors.
- Configuration weaknesses.
- Deployment risks.

Produce an evidence-based baseline report before starting major changes.

Do not invent problems or scores. Every finding must reference the actual implementation, observed behaviour, test results, or configuration.

---

# 4. Complete functionality audit

Every feature in InfoDot must be tested from the perspective of a real user.

For each feature, verify:

- The interface renders correctly.
- The user can complete the intended action.
- Data is correctly validated.
- Data is correctly stored.
- Data is correctly retrieved.
- Permissions are enforced.
- Errors are handled properly.
- Notifications are delivered where required.
- Related integrations work.
- The feature works on desktop and mobile.
- The feature works under realistic usage conditions.

### Questions and Solutions

Test:
- Creating, editing, publishing, and deleting questions.
- Creating, editing, publishing, and deleting solutions.
- PDF, image, and video attachments.
- Media previews and downloads.
- Comments and answers.
- User and business mentions.
- Solution recommendations.
- "I tried this and it works" confirmations.
- Duplicate question and solution detection.
- Suggested improvements.
- Approval and rejection workflows.
- Solution version history.
- Original and improvement contributor attribution.
- Search and discovery.
- Content reporting and moderation.

### Community and knowledge functionality

Test:
- User contributions.
- Business contributions.
- Expertise and recommendations.
- User reputation.
- Contribution rewards.
- Dot.Brains knowledge ingestion.
- Dot.Memory contextual intelligence.
- Dot.Dopemine reward and engagement loops.
- Knowledge relationships and provenance.

### Business and account functionality

Test:
- User registration.
- Email verification.
- Login and logout.
- Password recovery.
- Account deletion.
- Business creation.
- Business membership.
- Invitations.
- User roles.
- Permissions.
- Tenant switching.
- Subscription selection.
- Billing.
- Payment processing.
- Subscription changes.
- Cancellation.
- Renewal.
- Account restrictions.

Identify workflows that appear functional but fail when used in realistic scenarios.

Do not accept a successful frontend interaction as proof that the backend implementation works.

---

# 5. UI/UX and product experience audit

Conduct a complete visual and functional review of every page in InfoDot.

The objective is to make InfoDot feel like a premium, mature, professional business platform.

### 5.1 Design consistency

Inspect:
- Typography.
- Colours.
- Spacing.
- Layouts.
- Navigation.
- Buttons.
- Forms.
- Cards.
- Modals.
- Dropdowns.
- Tables.
- Data visualisations.
- Icons.
- Notifications.
- Empty states.
- Loading states.
- Error states.
- Confirmation states.

Eliminate inconsistent design patterns and unnecessary interface complexity.

### 5.2 User experience

Evaluate the complete user journey:

- First visit.
- Understanding what InfoDot does.
- Registration.
- Onboarding.
- Joining or creating a business.
- Discovering knowledge.
- Asking questions.
- Publishing solutions.
- Uploading supporting media.
- Finding experts.
- Participating in discussions.
- Tracking contributions.
- Managing subscriptions.
- Returning to the platform.

Every workflow should be intuitive without requiring technical knowledge.

Identify unnecessary steps, confusing labels, broken navigation, unclear actions, and unnecessary friction.

### 5.3 Responsive design

Test the complete application on:

- Desktop.
- Laptop.
- Tablet.
- Mobile devices.

Verify layouts at different viewport sizes.

Pay particular attention to:
- Navigation.
- Search.
- Rich content.
- Multimedia attachments.
- Tables.
- Dashboards.
- Forms.
- Modal windows.
- Business profiles.
- Question and solution detail pages.

### 5.4 Accessibility

Evaluate accessibility against WCAG 2.2 AA where applicable.

Implement:
- Semantic HTML.
- Keyboard navigation.
- Visible focus states.
- Accessible labels.
- Appropriate colour contrast.
- Screen-reader support.
- Accessible form validation.
- Alternative text for meaningful images.
- Accessible dialogs.
- Proper heading hierarchy.

### 5.5 Product polish

Eliminate:
- Placeholder content.
- Broken images.
- Missing icons.
- Inconsistent loading indicators.
- Unfinished pages.
- Dead buttons.
- Misleading labels.
- Unnecessary animations.
- Poorly formatted text.
- Broken empty states.
- Debug messages.
- Unprofessional error messages.

The finished interface must feel deliberate, coherent, and production-ready.

---

# 6. Laravel Boost – Complete application inspection and verification

Laravel Boost must be used as a core development and verification tool throughout this project.

Do not treat Laravel Boost as a substitute for security testing or end-to-end testing. Use it to inspect the application and guide implementation within the actual Laravel architecture.

Use its available capabilities to:

- Inspect application structure.
- Understand existing Laravel conventions.
- Inspect routes.
- Understand models and relationships.
- Inspect database schemas.
- Review migrations.
- Identify relevant services.
- Inspect Livewire components.
- Identify validation and authorisation logic.
- Investigate errors.
- Debug failing workflows.
- Verify framework-specific implementations.
- Follow current Laravel and package guidance.

Review:
- Route definitions.
- Controllers.
- Form Requests.
- Policies.
- Gates.
- Middleware.
- Livewire components.
- Eloquent models.
- Database migrations.
- Queues.
- Jobs.
- Events.
- Listeners.
- Notifications.
- Scheduled commands.
- API resources.
- Exception handling.
- Service providers.
- Configuration.
- Dependency management.

Run appropriate:
- Laravel tests.
- Feature tests.
- Unit tests.
- Database tests.
- Static analysis.
- Code formatting.
- Frontend build checks.
- Integration tests.

Identify and resolve framework-level warnings, deprecated functionality, failing tests, and insecure implementation patterns.

Do not assume the Laravel version or available Boost capabilities. Inspect the installed application and use compatible tooling.

---

# 7. Enterprise security hardening

Conduct a security-first review of the entire platform.

Use recognised security practices, including OWASP Top 10, OWASP ASVS, and relevant Laravel security guidance.

The objective is to protect user accounts, business information, uploaded files, APIs, sessions, integrations, and the underlying infrastructure.

## 7.1 API security and secret protection

Inspect every API endpoint and integration.

Requirements:

- Never expose private API keys in frontend code.
- Never expose database passwords or database connection credentials.
- Keep service-role keys and privileged credentials exclusively on trusted server infrastructure.
- Store secrets in secure environment configuration or an appropriate secret manager.
- Do not commit secrets to Git.
- Rotate credentials that may have been exposed.
- Use least-privilege credentials.
- Secure API authentication.
- Enforce authorisation on every protected endpoint.
- Validate API request parameters.
- Restrict API response fields.
- Prevent sensitive information from appearing in API responses.
- Implement appropriate API rate limits.
- Use secure error responses.
- Avoid exposing stack traces, internal paths, database details, or configuration information.

Do not confuse hiding an API endpoint with securing it. API routes may be discoverable, but every operation must be properly authenticated and authorised.

### Public database keys

Where the architecture uses a client-accessible database service, use only explicitly designed public or anonymous client keys where appropriate.

Never expose privileged database credentials, service-role keys, or secret keys in browser code.

If using Supabase or a similar service:
- Enable and enforce Row Level Security (RLS) on all relevant exposed tables.
- Write explicit policies for SELECT, INSERT, UPDATE, and DELETE.
- Restrict records by authenticated user, business, and tenant.
- Test policies for anonymous, authenticated, and privileged access.
- Ensure privileged server-side operations bypass RLS only through controlled, trusted services.
- Do not rely on a public key as a security boundary.

If InfoDot uses Laravel's own database connections, do not introduce an unnecessary public database client architecture. Enforce access through server-side authorisation and database ownership rules instead.

## 7.2 Git secret removal and repository security

Inspect the complete Git repository.

Requirements:

- Search for API keys.
- Search for passwords.
- Search for database credentials.
- Search for private tokens.
- Search for certificates and private keys.
- Search for accidentally committed environment files.
- Search for sensitive configuration files.
- Search Git history for exposed secrets.
- Add and verify appropriate `.gitignore` rules.
- Implement secret scanning.
- Add appropriate pre-commit and CI checks.

If secrets are discovered:
1. Identify the affected credentials.
2. Rotate or revoke exposed credentials.
3. Remove secrets from current tracked files.
4. Clean Git history where necessary and authorised.
5. Coordinate history rewrites with repository users.
6. Verify that secrets are no longer present.
7. Document the incident and remediation.

Simply deleting a secret from the latest commit is not sufficient if it remains in repository history.

Never print actual secret values in audit reports.

## 7.3 Authentication and authorisation

Enforce server-side authentication and authorisation throughout the application.

Requirements:

- Secure login and registration.
- Secure password recovery.
- Email verification where appropriate.
- Multi-factor authentication for privileged roles.
- Role-based access control.
- Business-level permissions.
- Tenant isolation.
- Server-side permission checks.
- Protected administrative operations.
- Secure account recovery.
- Protection against account enumeration.
- Secure handling of disabled or suspended accounts.

Do not rely on frontend visibility or hidden buttons as permission controls.

## 7.4 Database and record security

Lock down record-level access.

Requirements:

- Users must only access records they are authorised to access.
- Business users must only access their own company's private records.
- Tenant boundaries must be enforced server-side.
- Record ownership must be verified on every operation.
- Sensitive record fields must not be exposed unnecessarily.
- Users must not be able to modify protected ownership, tenant, role, or permission fields.

Prevent insecure direct object references (IDOR).

Use policies, scoped queries, validation, and database constraints as appropriate.

## 7.5 Field tampering and mass assignment

Prevent field tampering and unauthorised modification.

Inspect all create and update operations.

Requirements:
- Use explicit validation.
- Use safe Eloquent mass-assignment configuration.
- Use validated data rather than blindly accepting request payloads.
- Protect sensitive fields.
- Prevent users from changing their own privileged roles.
- Prevent tenant ID manipulation.
- Prevent ownership reassignment.
- Prevent subscription and billing manipulation.
- Prevent users from modifying approval status or trusted knowledge flags without permission.

Never trust values simply because they originate from the frontend.

## 7.6 Sensitive data encryption

Identify all sensitive information stored by InfoDot.

Apply encryption where appropriate to:
- API credentials.
- Integration tokens.
- Confidential business information.
- Sensitive personal information.
- Private configuration.
- Other data requiring enhanced protection.

Use appropriate encryption at rest and in transit.

Use modern authenticated encryption mechanisms and secure key management.

Do not implement encryption that makes normal application operations unreliable.

Review key rotation, backups, encrypted data recovery, and access to encryption keys.

Do not store passwords using reversible encryption.

## 7.7 Password security

Ensure passwords are securely hashed using Laravel's supported password hashing mechanisms, with Argon2id or an appropriately configured modern alternative.

Requirements:
- Secure password hashing.
- Appropriate hashing parameters.
- Password reset token protection.
- Password reuse controls where justified.
- Secure password validation.
- Protection against credential stuffing.
- No plaintext passwords in logs or database records.

## 7.8 Session and cookie security

Secure all authentication and session cookies.

Configure:
- Secure cookies.
- HttpOnly cookies.
- Appropriate SameSite settings.
- Session regeneration after authentication.
- Session invalidation on logout.
- Session expiration.
- Protection against session fixation.
- CSRF protection.
- Appropriate session storage and encryption.

Ensure cookie configuration is compatible with the actual deployment architecture, HTTPS termination, and any legitimate cross-origin requirements.

## 7.9 Login and API rate limiting

Implement appropriate rate limiting for:

- Login attempts.
- Registration.
- Password recovery.
- Email verification.
- API endpoints.
- Search endpoints.
- @mentions.
- Uploads.
- AI operations.
- Expensive database operations.
- Public forms.
- Other abuse-prone functionality.

Use sensible limits based on user identity, IP address, endpoint risk, and authenticated business context.

Avoid rate limits that unnecessarily block legitimate users.

## 7.10 Bot and automated abuse protection

Introduce suitable bot protection.

Consider:
- Login abuse detection.
- Automated registration protection.
- CAPTCHA or privacy-conscious alternatives where justified.
- Suspicious request detection.
- Request throttling.
- Spam prevention.
- Abuse reporting.
- Automated account activity monitoring.

Do not make the entire application unnecessarily difficult to use through excessive CAPTCHA challenges.

## 7.11 Database query protection

Use parameterised queries and safe ORM practices.

Requirements:
- Prevent SQL injection.
- Avoid unsafe raw SQL.
- Validate query parameters.
- Restrict sorting and filtering to approved fields.
- Avoid dynamically constructing SQL from untrusted input.
- Use appropriate database permissions.
- Review database error handling.

## 7.12 Input validation and output encoding

Validate all user-controlled input on the server.

Use:
- Laravel validation.
- Form Requests where appropriate.
- Strict type and format validation.
- Length restrictions.
- File validation.
- Business-rule validation.
- Context-aware output encoding.

Escape user-generated content appropriately.

Prevent:
- Stored XSS.
- Reflected XSS.
- DOM-based XSS.
- HTML injection.
- Script injection.
- Unsafe Markdown rendering.
- Unsafe links.
- Malicious embedded media.

Allow rich text only through a carefully sanitised and controlled rendering pipeline.

## 7.13 File upload security

Restrict user uploads.

Implement:
- File size limits.
- File type allowlists.
- MIME type verification.
- File signature verification.
- Safe filenames.
- Malware scanning where available.
- Secure storage.
- Private access controls.
- Download authorisation.
- Safe PDF previews.
- Safe image processing.
- Secure video processing.
- Upload rate limits.
- Storage quotas.
- File lifecycle policies.

Never trust file extensions alone.

Do not allow uploaded files to execute as server-side code.

Prevent unauthorised access to private business documents and media.

## 7.14 API response minimisation

Trim API responses.

Return only the information required by the requesting user and operation.

Requirements:
- Use controlled API Resources or equivalent response serializers.
- Remove internal IDs and metadata where unnecessary.
- Do not expose passwords, tokens, internal notes, or private fields.
- Avoid exposing other tenants' information.
- Avoid leaking stack traces.
- Prevent excessive data exposure.
- Apply pagination and response limits.
- Use explicit field selection where appropriate.

## 7.15 Security headers

Review and implement appropriate HTTP security headers, including:

- Content-Security-Policy.
- Strict-Transport-Security.
- X-Content-Type-Options.
- Referrer-Policy.
- Appropriate frame protection through CSP.
- Permissions-Policy.

Configure policies based on actual application dependencies.

Avoid breaking legitimate functionality through an incorrectly restrictive Content Security Policy.

## 7.16 HTTPS enforcement

Force HTTPS in production.

Requirements:
- Redirect HTTP to HTTPS.
- Use valid TLS certificates.
- Configure secure cookies.
- Configure trusted proxies correctly.
- Avoid mixed content.
- Secure application-to-service communication.
- Review TLS configuration.
- Enable HSTS after verifying the production HTTPS setup.

---

# 8. Privacy, POPIA and regulatory readiness

InfoDot will handle personal and business information. Privacy must be built into the application architecture.

Assess compliance with:
- South Africa's Protection of Personal Information Act (POPIA).
- GDPR where applicable.
- Applicable electronic communications and consumer protection requirements.
- Relevant payment and financial requirements.
- Contractual obligations with business customers.

Do not claim legal compliance solely because a privacy policy exists.

Review:

- Privacy policy.
- Terms and conditions.
- Consent collection.
- Data processing purposes.
- Data retention.
- Account deletion.
- Data export.
- Data access requests.
- Data correction.
- Third-party processors.
- Cross-border data transfers.
- Breach response.
- Business data separation.
- User consent records.
- Marketing preferences.
- Cookie consent.

### Cookie banners and consent management

Implement a proper cookie consent experience.

Requirements:
- Display a clear cookie banner where required.
- Explain necessary and optional cookies.
- Provide accept, reject, and customise options where applicable.
- Do not treat closing the banner as consent.
- Prevent non-essential tracking technologies from activating before consent where required.
- Record consent decisions.
- Allow users to change their preferences.
- Provide a persistent cookie settings control.
- Respect regional privacy requirements.

Necessary authentication and security cookies must continue to work.

Ensure analytics, marketing, and third-party tracking scripts respect consent settings.

---

# 9. Infrastructure, deployment and operational readiness

Inspect the entire production infrastructure and deployment process.

Review:

- Hosting environment.
- PHP configuration.
- Laravel configuration.
- Database configuration.
- Queue workers.
- Scheduled tasks.
- Cache.
- Session storage.
- File storage.
- Email services.
- External integrations.
- Environment variables.
- Logging.
- Deployment scripts.
- CI/CD.
- Domain and DNS.
- TLS certificates.
- Monitoring.
- Backups.

Implement appropriate production configuration.

Requirements:

- Production debug mode disabled.
- Secure environment configuration.
- Reliable queue processing.
- Correct scheduled task execution.
- Production-appropriate caching.
- Database connection security.
- Reliable storage permissions.
- Health checks.
- Error monitoring.
- Centralised logging where appropriate.
- Automated backups.
- Tested restoration procedures.
- Disaster recovery planning.
- Deployment rollback procedures.
- Zero-downtime deployment where supported and justified.

Do not expose internal logs or diagnostics to ordinary users.

Test the complete deployment process in an appropriate staging environment before production release.

---

# 10. Performance, reliability and scalability

Evaluate the application under realistic production workloads.

Inspect:

- Database query performance.
- N+1 query problems.
- Slow endpoints.
- Search performance.
- Queue backlogs.
- Memory usage.
- CPU usage.
- Cache efficiency.
- Asset delivery.
- Image optimisation.
- Video processing.
- API response times.
- Frontend rendering.
- Concurrent user behaviour.

Implement:

- Database indexing.
- Query optimisation.
- Eager loading where appropriate.
- Efficient pagination.
- Caching.
- Background jobs.
- Lazy loading.
- Asset optimisation.
- Image compression.
- CDN delivery where appropriate.
- Queue monitoring.
- Appropriate connection management.

Conduct load testing and identify the actual capacity of the current infrastructure.

Do not claim that InfoDot supports one million users merely because the architecture is theoretically scalable.

Document tested concurrency, response times, infrastructure limits, bottlenecks, and the capacity expansion plan.

Set measurable performance targets and benchmark the application before and after optimisation.

---

# 11. API and integration readiness

Audit all internal and external API integrations.

Verify:

- Authentication.
- Authorisation.
- Request validation.
- Response validation.
- Error handling.
- Timeout handling.
- Retry policies.
- Rate limits.
- Logging.
- Secret management.
- Versioning.
- Documentation.
- Availability monitoring.
- Failure recovery.

Ensure integrations cannot expose confidential information or bypass tenant permissions.

Review all integrations with:
- AI providers.
- Dot ecosystem applications.
- Email services.
- Payment providers.
- File storage services.
- Authentication providers.
- Analytics services.
- Other external APIs.

Ensure failures in one integration do not unnecessarily break unrelated InfoDot functionality.

Provide clear integration status and diagnostic information to authorised administrators.

---

# 12. Content quality, accuracy and completeness audit

Conduct a complete audit of all InfoDot content.

This includes:

- Landing pages.
- Product descriptions.
- Onboarding instructions.
- Help pages.
- FAQs.
- Questions and Solutions.
- Official InfoDot knowledge.
- Business categories.
- User-facing messages.
- Email templates.
- Notifications.
- Error messages.
- Legal pages.
- SEO content.
- AI-generated summaries.
- Knowledge articles.

### Content standards

Every piece of official content must be:

- Accurate.
- Relevant.
- Clear.
- Professionally written.
- Consistent with InfoDot's purpose.
- Free from unnecessary repetition.
- Appropriately categorised.
- Properly formatted.
- Useful to the intended audience.

Identify:
- Outdated information.
- Contradictory information.
- Duplicate content.
- Broken links.
- Empty pages.
- Placeholder text.
- Poorly written descriptions.
- Misleading claims.
- Unsupported statistics.
- Missing instructions.
- Incorrect terminology.

### Business knowledge credibility

Official InfoDot content must distinguish between:

- Research-backed recommendations.
- Documented case studies.
- Industry best practices.
- Community-reported experience.
- AI-generated suggestions.
- Expert-reviewed material.

Cite credible sources for factual business advice.

Never invent:
- Testimonials.
- Contributors.
- Case studies.
- Statistics.
- Business results.
- Research citations.
- Expert credentials.

Provide appropriate author and review information where relevant.

---

# 13. SEO, Search Engine Visibility and AI Discoverability

Optimise InfoDot for traditional search engines and relevant AI-powered discovery systems.

The objective is to make public, useful InfoDot content discoverable without exposing private business information.

## 13.1 Technical SEO

Inspect and optimise:

- robots.txt.
- sitemap.xml.
- Canonical URLs.
- Page titles.
- Meta descriptions.
- Heading hierarchy.
- URL structures.
- Internal links.
- Image alternative text.
- Structured data.
- Indexing status.
- Redirects.
- Broken links.
- Duplicate pages.
- Mobile usability.
- Page performance.

### robots.txt and AI crawlers

Review the existing robots.txt file.

Allow legitimate search engine crawlers and appropriate AI search/discovery crawlers to access public content where commercially and legally appropriate.

Evaluate relevant crawlers individually and make an explicit policy decision about each.

Do not automatically allow every AI training crawler.

Do not rely on robots.txt to protect private content. Authentication and server-side access controls must enforce privacy.

Ensure private account pages, dashboards, tenant data, administrative pages, and confidential content cannot be indexed.

### 13.2 Sitemap.xml

Create or optimise sitemap.xml.

Include:
- Public landing pages.
- Public product pages.
- Public categories.
- Public knowledge articles.
- Publicly accessible questions and solutions where appropriate.

Exclude:
- Private dashboards.
- Authentication pages where inappropriate.
- Administrative pages.
- Confidential business content.
- Private user information.
- Duplicate or non-canonical pages.

Ensure the sitemap is updated when relevant public content is published or removed.

### 13.3 Structured data

Implement appropriate schema.org structured data where supported.

Consider:
- Organization.
- WebSite.
- WebPage.
- BreadcrumbList.
- Article.
- BlogPosting.
- Person.
- SoftwareApplication.
- FAQPage only where its content and eligibility genuinely qualify.

Use valid JSON-LD.

Do not add misleading ratings, fake reviews, invented author information, or structured data that does not match visible page content.

Validate structured data using recognised testing tools.

### 13.4 Semantic HTML5

Use appropriate semantic HTML5 elements.

Ensure:
- Correct heading hierarchy.
- Meaningful page landmarks.
- Proper navigation elements.
- Accessible forms.
- Descriptive link text.
- Semantic content sections.
- Meaningful image descriptions.

### 13.5 Page titles and meta descriptions

Every indexable public page must have:

- A unique, descriptive page title.
- A relevant meta description.
- An appropriate canonical URL.
- Correct social sharing metadata where relevant.

Avoid duplicate titles and descriptions across public pages.

### 13.6 Indexing and crawlability

Identify and fix:
- Accidental noindex directives.
- Incorrect canonical tags.
- Broken redirects.
- Orphaned public pages.
- Crawl errors.
- Duplicate indexing.
- Incorrect robots rules.
- Missing sitemap entries.
- Unnecessary URL parameters.
- Poor internal linking.

Do not index pages that should remain private.

### 13.7 Content discoverability

Improve public content to answer genuine business questions.

Where appropriate:
- Use descriptive headings.
- Answer common business questions directly.
- Include practical explanations.
- Provide credible references.
- Identify the author or reviewing organisation.
- Include publication and review dates.
- Explain the evidence behind recommendations.
- Link related knowledge resources.

Optimise for useful answers rather than keyword stuffing.

### 13.8 E-E-A-T and content credibility

Strengthen Experience, Expertise, Authoritativeness, and Trustworthiness.

Where relevant:
- Provide author information.
- Identify the source of official content.
- Include credible citations.
- Distinguish editorially reviewed content from community submissions.
- Show review dates.
- Explain methodology for research-backed material.
- Avoid unsupported claims of expertise.

Do not manufacture credentials or imply that AI-generated content was written by a human expert.

---

# 14. Administration and enterprise management

Evaluate the administrative experience.

An enterprise-ready platform must give authorised administrators sufficient control without compromising tenant privacy.

Review:
- User management.
- Business management.
- Roles and permissions.
- Subscription management.
- Billing.
- Usage monitoring.
- Storage monitoring.
- API usage.
- Security events.
- Audit logs.
- Content moderation.
- Reports.
- System health.
- Integration status.
- Queue status.
- Notification failures.
- Account restrictions.
- Support workflows.

Ensure administrative permissions are granular and enforced server-side.

Provide meaningful audit trails for sensitive administrative actions.

Do not give every administrator unrestricted access to confidential customer information by default.

---

# 15. Subscription, Billing and Commercial Readiness

Inspect the complete commercial model.

Verify:
- Subscription plans.
- Pricing presentation.
- Trial functionality.
- Payment processing.
- Recurring billing.
- Renewals.
- Upgrades.
- Downgrades.
- Cancellations.
- Failed payments.
- Refund workflows.
- Invoices.
- Receipts.
- Usage limits.
- Storage limits.
- User limits.
- Business limits.
- Feature entitlements.

Ensure subscription enforcement happens on the server.

Users must not be able to bypass limits by manipulating frontend values or API requests.

Ensure the pricing and product presentation clearly communicate what customers receive.

Test the full customer journey from selecting a plan through payment and access provisioning.

Review customer support and billing dispute workflows.

---

# 16. Testing, CI/CD and release quality gates

Implement or improve a reliable automated quality pipeline.

The pipeline should include:

- Dependency checks.
- Secret scanning.
- Static analysis.
- Code formatting.
- Unit tests.
- Feature tests.
- Integration tests.
- Database migration checks.
- Frontend build checks.
- Security tests.
- API tests.
- Permission tests.
- Tenant isolation tests.
- Performance checks.
- End-to-end tests.

Where appropriate, introduce:
- Dependency vulnerability scanning.
- Software composition analysis.
- Static application security testing.
- Dynamic application security testing.
- Container or infrastructure scanning.

Establish release gates.

Do not deploy when:
- Critical tests fail.
- Secrets are detected.
- Critical security vulnerabilities remain.
- Migrations are unsafe.
- Required environment configuration is missing.
- Core workflows are broken.
- Tenant isolation tests fail.

Implement rollback procedures and preserve deployment traceability.

Every production release must be associated with a tested version.

---

# 17. Business continuity and disaster recovery

Assess InfoDot's ability to recover from infrastructure failures, accidental data loss, malicious activity, and service outages.

Implement:
- Automated database backups.
- Secure file backups.
- Backup encryption.
- Backup retention policies.
- Off-site or geographically separate backup storage where appropriate.
- Restore testing.
- Disaster recovery documentation.
- Recovery Time Objectives (RTO).
- Recovery Point Objectives (RPO).
- Incident response procedures.
- Security breach procedures.
- Service restoration procedures.

A backup is not considered reliable until a successful restoration has been tested.

Ensure business-critical data and media attachments are included in recovery planning.

---

# 18. Documentation and operational handover

Produce and maintain accurate technical documentation.

Include:
- System architecture.
- Database structure.
- Authentication and authorisation.
- Tenant isolation.
- API documentation.
- Integration documentation.
- Environment configuration.
- Deployment procedures.
- Backup and recovery procedures.
- Security controls.
- Monitoring and alerting.
- Troubleshooting.
- Administrator instructions.
- Customer support procedures.

Document all newly introduced dependencies and configuration requirements.

Never include actual secrets in documentation.

Ensure another qualified developer can understand, maintain, deploy, and troubleshoot InfoDot without relying on undocumented assumptions.

---

# 19. Enterprise readiness scoring

After completing the audit and remediation, evaluate InfoDot across the following dimensions.

| Assessment area | Weight |
|---|---:|
| Core functionality and workflow reliability | 15% |
| Security and privacy | 20% |
| UI/UX and accessibility | 10% |
| Content quality and credibility | 10% |
| Performance and scalability | 10% |
| Infrastructure and operational reliability | 10% |
| API and integration readiness | 5% |
| SEO and discoverability | 5% |
| Commercial and subscription readiness | 5% |
| Testing, documentation and maintainability | 10% |
| **Total** | **100%** |

Score each area using actual evidence.

A high score must not conceal a critical security weakness.

Use these readiness classifications:

- **Below 60%:** Not market-ready.
- **60–74%:** Limited readiness; substantial remediation required.
- **75–84%:** Approaching market readiness; important gaps remain.
- **85–94%:** Strong enterprise readiness; remaining improvements must be assessed.
- **95–100%:** Exceptional readiness against the defined assessment scope, subject to independent validation where required.

### Critical release blockers

Regardless of the overall score, InfoDot must not be declared ready for market if any of the following remain unresolved:

- Critical security vulnerabilities.
- Exposed credentials or unrotated compromised secrets.
- Cross-tenant data exposure.
- Broken authentication or authorisation.
- Unauthorised access to private records.
- Data corruption or loss risks.
- Broken payment or subscription enforcement.
- Critical production workflows failing.
- Missing or untested critical backups.
- Serious privacy violations.
- Unresolved critical vulnerabilities in production dependencies.

Distinguish between:
- Implemented.
- Tested.
- Verified.
- Partially implemented.
- Not implemented.
- Blocked by external dependency.

Do not award full points for code that exists but has not been tested.

---

# 20. Implementation strategy

Do not attempt to modify the entire platform in one uncontrolled operation.

Work through structured phases.

### Phase 1 – Discovery and baseline
- Full repository audit.
- Architecture review.
- Feature inventory.
- Database inspection.
- UI/UX audit.
- Security baseline.
- Initial test execution.
- Evidence-based readiness scoring.

### Phase 2 – Critical security remediation
- Secret removal and credential rotation.
- Authentication and authorisation.
- Tenant isolation.
- Database access controls.
- Input validation.
- Encryption.
- Session and cookie security.
- API hardening.
- Upload security.
- Security headers.

### Phase 3 – Functionality and product quality
- Repair broken workflows.
- Complete unfinished functionality.
- Verify Questions and Solutions.
- Verify multimedia handling.
- Verify search and duplicate detection.
- Verify solution validation and improvements.
- Verify Dot ecosystem integrations.
- Verify notifications.

### Phase 4 – UI/UX and content refinement
- Complete visual audit.
- Responsive improvements.
- Accessibility.
- Content quality.
- Navigation.
- Onboarding.
- Product consistency.
- Error and empty states.

### Phase 5 – Performance and scalability
- Database optimisation.
- Search optimisation.
- Queue optimisation.
- Caching.
- Asset optimisation.
- Load testing.
- Infrastructure improvements.

### Phase 6 – SEO and discoverability
- robots.txt.
- Sitemap.
- Structured data.
- Canonical URLs.
- Metadata.
- Semantic HTML.
- Public content optimisation.
- AI crawler policy.
- Indexing validation.

### Phase 7 – Commercial and operational readiness
- Subscription and billing verification.
- Administration.
- Monitoring.
- Backups.
- Disaster recovery.
- Documentation.
- Support workflows.

### Phase 8 – Final validation and launch assessment
- Complete regression testing.
- Security retesting.
- Performance benchmarks.
- End-to-end testing.
- Deployment rehearsal.
- Final scoring.
- Release blocker assessment.
- Market-readiness decision.

---

# 21. Final deliverables

At the conclusion of the project, produce a comprehensive InfoDot Enterprise Readiness Report.

It must include:

### A. Executive summary
- Overall readiness score.
- Market-readiness classification.
- Major improvements.
- Remaining risks.
- Final launch recommendation.

### B. Full audit findings
- Every area inspected.
- Findings and severity.
- Evidence supporting each finding.
- Remediation undertaken.
- Remaining weaknesses.

### C. Security report
- Authentication.
- Authorisation.
- Tenant isolation.
- Database security.
- API security.
- Secret management.
- Encryption.
- Session security.
- File security.
- Vulnerability findings.
- Security retest results.

Never include actual credentials or secrets in the report.

### D. Product quality report
- Feature-by-feature status.
- UI/UX findings.
- Accessibility.
- Content quality.
- Mobile responsiveness.
- Functional test results.

### E. Performance report
- Baseline measurements.
- Improvements.
- Database performance.
- Search performance.
- API performance.
- Load-test results.
- Infrastructure capacity.
- Identified scaling limits.

### F. SEO and discoverability report
- robots.txt status.
- AI crawler policy.
- Sitemap status.
- Structured data validation.
- Metadata coverage.
- Canonical tags.
- Indexing findings.
- Public content improvements.

### G. Commercial readiness report
- Subscription and billing tests.
- Plan enforcement.
- Customer onboarding.
- Administrative readiness.
- Operational support readiness.

### H. Deployment and operational report
- CI/CD status.
- Environment configuration.
- Monitoring.
- Backups.
- Disaster recovery.
- Rollback procedures.
- Outstanding deployment dependencies.

### I. Outstanding actions
List every remaining issue with:
- Severity.
- Business impact.
- Security impact where applicable.
- Recommended solution.
- Priority.
- Responsible area.
- Estimated implementation effort.

---

# 22. Final mission statement

Transform InfoDot from a feature-rich application into a genuinely enterprise-ready business knowledge and intelligence platform.

Every page must look professional.

Every feature must work.

Every user interaction must be secure.

Every business record must be protected.

Every API must be appropriately secured.

Every piece of official content must be credible.

Every important workflow must be tested.

Every deployment must be recoverable.

Every public page must be discoverable where appropriate.

Every customer must be able to trust the platform with their business information.

**Do not optimise for an impressive audit report. Optimise for a product that can withstand real customers, real business data, real security threats, real operational demands, and real commercial expectations.**

Start by inspecting the actual InfoDot repository and application through Laravel Boost. Establish the baseline, identify the most serious risks, and then systematically implement, test, and verify the required improvements.

Do not declare InfoDot enterprise-ready until all critical release blockers have been resolved and the final assessment is supported by measurable evidence.
