# R-62 brief (owner, 2026-11-24): InfoDot business-first onboarding and company account experience redesign

Stored verbatim as given by the owner (the only change: spaced dashes are colons, to keep the house rule of no dashes in written text). The audit of what InfoDot already has, the gaps and the phased plan are in `R62_AUDIT_AND_PLAN.md`.

---

## 1. Strategic Objective

We need to fundamentally improve the InfoDot registration, login and onboarding experience.

Several users have experienced confusion during onboarding because the current process makes it unclear whether they are creating an individual account or a business account.

Currently, a user registers, logs in and then has to discover that they need to navigate to their profile icon to create a company. Many users do not understand this distinction, which creates unnecessary friction and causes potential customers to abandon the onboarding process.

We need to eliminate this confusion.

The new experience must be built around one central principle:

People create accounts, but businesses are the primary entities they come to InfoDot to establish, manage and grow.

The individual user account should become the access and identity mechanism through which users manage one or more businesses.

The company profile should become the primary identity and workspace presented throughout InfoDot.

Our objective is to make it exceptionally easy for someone to:

1. Register an InfoDot account.
2. Create their first company.
3. Populate the company's information quickly.
4. Establish its business profile.
5. Invite employees and collaborators.
6. Discover the most relevant Dot platforms.
7. Start using the ecosystem immediately.

Minimise unnecessary steps, repetitive questions and excessive data-entry requirements.

The ultimate goal is to increase registration completion, business activation, team invitations, platform adoption and long-term retention.

Before implementing anything, audit the existing authentication, user profiles, company management, onboarding, business verification, platform activation and tenant architecture.

Preserve existing functionality wherever possible.

## 2. Redesign the Registration and Login Experience

The registration and login pages must communicate clearly that users are establishing access to manage a business on InfoDot.

The current use of names and usernames should be reviewed and redesigned to eliminate confusion between personal identity and company identity.

### Registration experience

The registration page should clearly explain:

Create your InfoDot account to start and manage your business.

The registration process should collect only the information necessary to create an individual login identity.

For example:

* First name.
* Last name.
* Email address.
* Password.

Review whether a username is actually necessary at registration. If it is only used for personal profile identification and is not essential to business operations, consider removing it from the initial registration process.

Do not confuse users by presenting personal account fields as though they are creating their company's profile.

The company name and business details should be collected during the company creation and onboarding process.

### Login experience

The login page must maintain a consistent business-first message.

Users should understand that they are logging into InfoDot to access and manage their businesses.

Where appropriate, show messaging such as:

Your business starts here. Sign in to your InfoDot workspace.

The design must remain consistent with the existing InfoDot visual identity.

## 3. Make the Company Profile the Primary Workspace Identity

This is one of the most important architectural and UX changes.

Currently, individual user profiles and company profiles create confusion.

We need to introduce a more intuitive relationship between them.

### New account structure

The intended relationship should be:

Individual User Account → One or More Company Workspaces → Dot Ecosystem Platforms

An individual user registers one personal login account.

Under that account, they can create and manage multiple companies.

For example:

A user registers on InfoDot and creates:

* Company A: their primary business.
* Company B: another business they own.
* Company C: a separate business venture.

The same user should be able to switch between these companies without creating separate login accounts.

### Company profile as primary identity

Once a user has established their first company, the company profile should become the primary profile displayed throughout the InfoDot business environment.

Review the existing profile architecture and determine how the user profile and company profile can be combined into a coherent experience without duplicating records or compromising existing functionality.

The company should have its own:

* Business name.
* Logo.
* Cover image.
* Business description.
* Industry.
* Contact information.
* Website.
* Location.
* Business details.
* Verification status.
* Team members.
* Connected Dot platforms.

The individual user remains the authenticated person behind the company and retains their personal identity, security credentials and permissions.

Do not eliminate the underlying individual user account or merge company records into user records in a way that compromises data integrity.

### Multiple company management

Users must be able to create additional companies under the same InfoDot account.

Introduce an intuitive company switcher that allows users to:

* View all companies they belong to.
* Switch between companies.
* Create another company.
* Manage company information.
* Invite team members.
* Manage company permissions.
* View the platforms activated by each company.

Each company must have its own isolated business data, subscriptions, settings, team memberships and platform entitlements.

A user's access to one company must not automatically grant access to another company.

Implement appropriate company ownership transfer, administrator roles and recovery mechanisms.

## 4. Redesign the Getting Started Experience

Once users log in, the InfoDot homepage should immediately focus on establishing their company.

The current Getting Started experience should be redesigned to prioritise business setup rather than personal profile completion.

The homepage should guide users through a simple business activation journey.

### New Getting Started checklist

The recommended sequence should be:

Step 1: Create your company profile

Encourage the user to establish their company's digital identity.

Allow them to add their company name, logo, business description and essential details.

Step 2: Complete essential business information

Help them populate the information required to make their company discoverable and useful within InfoDot.

Do not force users to complete every optional field.

Step 3: Invite your team

Encourage users to invite employees, business partners and collaborators.

Provide a simple invitation mechanism using email addresses and shareable invitation links where appropriate.

Step 4: Activate your first Dot platforms

Recommend the most suitable starting platforms based on the company's requirements.

Step 5: Explore the business ecosystem

Introduce the other available Dot platforms gradually, based on the business's needs and activity.

### Important UX requirement

The Getting Started checklist must be company-focused.

Do not make personal profile completion the primary onboarding objective.

Personal account security, password management and optional individual profile information should remain accessible through account settings without distracting from the main business onboarding journey.

Progress should be saved automatically.

Users must be able to leave and resume onboarding without losing their work.

## 5. Introduce Three Ways to Populate Company Information

This is a major improvement intended to reduce registration and onboarding friction.

When a user starts creating their company profile, they should have three clear options.

### Option A: Import information from a company website

Allow users to enter their company's website URL.

InfoDot should then analyse the publicly accessible website and extract relevant company information.

Potential fields include:

* Company name.
* Business description.
* Logo.
* Industry.
* Contact email.
* Telephone number.
* Business address, where available.
* Website.
* Products and services.
* Social media links.
* Company background.

The system should use appropriate extraction methods, including publicly accessible page content and metadata.

Where supported, inspect relevant About, Contact, Services and Products pages.

Do not assume that every website contains all the information.

Allow users to review and edit extracted information before saving it.

Clearly indicate which fields were automatically populated and which still require confirmation.

Do not invent missing information or automatically treat extracted details as verified business information.

Website imports must respect website access restrictions, security controls, rate limits and applicable privacy requirements.

Prevent server-side request forgery, access to internal network resources and unsafe URL fetching.

### Option B: Upload a company document

Allow users to upload an existing company document.

Supported formats should include, where technically appropriate:

* PDF.
* DOC.
* DOCX.
* TXT.
* XLS.
* XLSX.
* Relevant image formats containing business information.

Examples of documents users may upload:

* Company profile documents.
* Business plans.
* Company registration documents.
* Business proposals.
* Company brochures.
* Business information sheets.

Use document extraction and AI-assisted information recognition to identify relevant company information.

Extract available information and automatically populate the appropriate company profile fields.

For example, if a company profile document contains a company name, description, services, contact details and address, those fields should be populated automatically.

Where information is missing, leave the fields available for manual completion.

Allow the user to review and correct all extracted information before submitting it.

Documents containing sensitive information must be processed securely.

Do not expose uploaded documents to other users or companies.

Implement appropriate file validation, malware protection, retention policies and access controls.

### Option C: Enter company information manually

Retain the existing manual company creation process.

Users who do not have a website or company document must still be able to create their business profile easily.

The manual process should be simplified to collect only essential information first.

Suggested minimum required information:

* Company name.
* Business category or industry.
* Short business description.

Other information should be optional or requested progressively.

Do not force users to complete a lengthy form before they can establish their company.

### Unified information capture experience

All three methods should ultimately feed into the same company profile creation workflow.

The intended experience is:

1. User chooses website, document or manual entry.
2. InfoDot extracts available information where applicable.
3. Extracted information is presented in an editable form.
4. User reviews and confirms the details.
5. Company profile is created.
6. Additional information can be completed later.

The objective is to make company creation as fast as possible.

## 6. Introduce Intelligent Business Information Extraction

Where website or document import is used, implement an intelligent extraction service.

It should:

* Identify relevant business information.
* Map extracted information to the correct database fields.
* Detect missing information.
* Identify conflicting information.
* Avoid duplicate company records.
* Preserve the original source for audit purposes where appropriate.
* Allow user corrections.
* Track extraction confidence.
* Never invent company information.

If information from a website conflicts with an uploaded document, show the conflicting values and let the user decide which is correct.

Do not automatically overwrite confirmed company information during subsequent imports.

The extraction service should be reusable across the ecosystem for future document and business onboarding workflows.

## 7. Recommend the First Four Free and Available Dot Platforms

Once the company profile has been created, the Getting Started experience should recommend which platforms the business should activate first.

This recommendation must be dynamic.

Inspect the actual Dot ecosystem platform registry and identify the first four platforms that satisfy both conditions:

1. They are free to activate and have accessible free functionality.
2. They are currently switched ON by the main InfoDot administrative account.

Recommend those four platforms as the initial starting points.

Do not hardcode four platform names.

The recommendations must be derived from actual platform availability, activation eligibility and pricing configuration.

If a platform is switched OFF globally, it must not be recommended.

If a platform has no usable free functionality, it must not be presented as a free starting recommendation.

### Platform recommendation experience

Each recommended platform should display:

* Platform name.
* Logo or icon.
* Short description.
* What it helps the business accomplish.
* Why it is a good starting point.
* Whether its initial access is free.
* Any limitations or premium features that may apply.

Make platform activation straightforward.

Users should be able to activate recommended platforms directly from the Getting Started experience.

Do not automatically activate paid features or purchase subscriptions on behalf of users.

### Intelligent recommendations

Where possible, use the company's industry and stated business objectives to improve recommendations.

However, the first four recommendations must always respect the free and globally enabled platform requirements.

If the platform recommendation engine has not yet collected enough information to personalise suggestions, use the first four eligible platforms according to the configured recommendation priority.

## 8. Improve Team Invitations

Inviting employees and collaborators should be one of the primary onboarding actions.

After creating the company profile, encourage the owner or administrator to invite their team.

Support:

* Individual email invitations.
* Multiple email invitations.
* Shareable invitation links.
* Invitation status tracking.
* Resending invitations.
* Cancelling invitations.
* Assigning appropriate company roles.

Suggested roles should include the existing equivalent of:

* Owner.
* Company administrator.
* Manager.
* Employee.
* External collaborator.

Use the existing permission architecture where possible.

An invited user should be able to create or use their existing InfoDot login and then join the relevant company.

If the invited user already belongs to other companies, joining a new company must not affect their existing memberships.

Do not automatically grant invitations excessive permissions.

## 9. Improve the Company-Centred Homepage

After initial onboarding, the InfoDot homepage should operate as the user's company workspace.

The homepage should prioritise:

* Active company identity.
* Company logo and branding.
* Business profile completion.
* Team members.
* Company activity.
* Activated Dot platforms.
* Recommended next actions.
* Relevant business insights.
* Available business resources.

If a user manages multiple companies, the active company must be clearly visible.

Provide a simple company switcher.

Every company-specific action must operate within the currently selected company context.

Avoid showing personal profile completion tasks as the main homepage focus.

## 10. Preserve Account and Company Security

This redesign must not compromise the existing InfoDot authentication or centralised Dot identity architecture.

Important requirements:

* One individual login can manage multiple companies.
* Each company has its own independent workspace.
* Company membership is controlled by explicit permissions.
* Company data remains tenant-isolated.
* Owners and administrators have appropriate controls.
* Users cannot access companies they do not belong to.
* Invitation tokens are secure and expire appropriately.
* Company ownership can be transferred safely.
* Business profile information is only editable by authorised users.
* Website and document extraction cannot bypass security controls.
* Existing accounts and company records must remain intact.

Review the existing database relationships before making structural changes.

If existing users have created companies through the current profile workflow, migrate them carefully into the new experience without losing ownership, memberships, data, subscriptions or permissions.

Do not create duplicate company records during migration.

## 11. Analytics and Onboarding Performance

We need to understand whether these changes are actually improving onboarding.

Introduce analytics that measure:

* Registration completion rate.
* Login completion rate.
* Percentage of users who create a company.
* Time taken to create the first company.
* Website import usage.
* Document import usage.
* Manual entry usage.
* Successful extraction rate.
* Company profile completion rate.
* Team invitation rate.
* Invitation acceptance rate.
* First platform activation rate.
* Time to first meaningful platform activity.
* Onboarding abandonment points.
* Returning business users.
* Business retention.

Compare onboarding performance before and after the redesign where historical data is available.

Do not use fake metrics.

Use real event tracking and clearly distinguish between users, companies and company memberships.

## 12. Design and User Experience Requirements

Use the existing InfoDot design system and maintain the premium, minimal and professional identity of the ecosystem.

The onboarding experience must be:

* Extremely intuitive.
* Mobile-first.
* Fast.
* Responsive.
* Accessible.
* Visually clear.
* Minimal in unnecessary steps.
* Consistent with the rest of InfoDot.

Use progressive disclosure rather than showing users lengthy forms all at once.

Make automatic information capture prominent.

Users should immediately understand that they can supply a website or document instead of manually typing everything.

Use clear progress indicators, helpful instructions, sensible defaults and automatic saving.

Avoid overwhelming users with unnecessary options, platform lists or lengthy explanations.

The experience should feel like setting up a modern business workspace, not completing an administrative registration application.

## 13. Implementation Approach

Do not immediately start changing the registration page without understanding the current account and company architecture.

Begin with a complete audit of:

* Registration and authentication.
* Individual user profiles.
* Company profiles.
* Company ownership.
* Company membership.
* Tenant relationships.
* Homepage onboarding.
* Company creation.
* Platform activation.
* Platform recommendations.
* Document upload.
* Website processing.
* Business verification.
* Existing migrations and database relationships.

Produce a technical implementation plan identifying:

1. Existing functionality that can be reused.
2. Existing functionality that needs improvement.
3. Database changes required.
4. UI changes required.
5. Backend services required.
6. Security considerations.
7. Migration requirements.
8. Testing requirements.

Then implement the redesign in controlled phases.

Do not break existing accounts, company records, authentication, subscriptions or connected Dot platform functionality.

Use real backend functionality throughout.

Do not introduce mock extraction services, placeholder import buttons or simulated onboarding completion.

Every import, company creation, invitation and platform activation must work end-to-end.

## 14. Definition of Success

The redesigned experience must achieve the following:

1. Users understand that their InfoDot login gives them access to manage businesses.
2. Users can create their first company without searching through profile settings.
3. Company profiles become the primary business identity.
4. Users can manage multiple companies under one login.
5. Business information can be captured through a website, document or manual entry.
6. The first company can be created with minimal mandatory information.
7. The homepage guides users through company setup rather than personal profile completion.
8. Users are encouraged to invite their teams.
9. The first four recommended platforms are dynamically selected from free, globally enabled platforms.
10. All company data and permissions remain properly isolated.
11. Existing users transition without losing data or functionality.
12. The new experience reduces onboarding time and improves business activation and retention.

## FINAL PRODUCT VISION

InfoDot should no longer make users figure out how to establish their business after registering.

The company must be at the centre of the experience from the moment someone enters the platform.

A person creates an account, establishes their company, invites their team and starts using the Dot ecosystem.

Give users the ability to manage multiple businesses through one login, populate business information automatically and discover the right tools without unnecessary friction.

The fewer barriers between registration and a functioning business workspace, the more businesses we can onboard and retain.

Build this experience around one objective:

Get businesses onto InfoDot as quickly and effortlessly as possible, then give them compelling reasons to stay.
