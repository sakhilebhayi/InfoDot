# R-29 — Owner brief (2026-10-31), stored verbatim

# IMPLEMENTATION PROMPT: INFODOT BUSINESS IDENTITY, PROFILE CUSTOMISATION, VERIFICATION AND ANTI-IMPERSONATION SYSTEM

## 1. OBJECTIVE

Transform InfoDot's business profile system into a professional business identity and trust platform.

Every business on InfoDot should be able to establish a recognisable digital identity through its business profile, photographs, branding and verified business information.

Other businesses and users should be able to understand who they are interacting with, recognise what the business looks like, identify its ownership or authorised representatives, and establish whether the business has been verified by InfoDot.

Introduce a robust business verification and anti-impersonation system that protects legitimate businesses against fake accounts, business-name impersonation, copied branding, fraudulent registration details and other forms of business identity theft.

The system must achieve five objectives:

1. Allow businesses to build complete, visually recognisable profiles.
2. Allow businesses to upload profile pictures, logos and background cover images.
3. Introduce a credible InfoDot business verification process.
4. Protect verified businesses against impersonation and fraudulent duplicate accounts.
5. Give users transparent ways to identify, report and investigate suspicious business profiles.

This must be implemented as a fully functional part of InfoDot, not simply as a visual profile redesign.

---

# PART A — BUSINESS PROFILE AND VISUAL IDENTITY

## 2. Create Professional Business Profiles

Redesign the business profile page to function as a business's public digital identity within InfoDot.

The profile should communicate what the business does, what it looks like, who it serves and whether its identity has been verified.

It should feel like a professional business identity page rather than a personal social-media profile.

Every business should be able to customise its profile using authentic business information and visual assets.

## 3. Profile Pictures and Business Logos

Allow businesses to upload and manage their profile pictures.

Support:

- Business logos.
- Brand symbols.
- Business profile photographs.
- Other appropriate business identity images.

Requirements:

- Upload images from a device.
- Preview images before publishing.
- Crop and reposition images.
- Support common image formats.
- Validate file type and size.
- Automatically optimise image dimensions and file size.
- Maintain good image quality.
- Provide appropriate image fallbacks.
- Allow authorised users to replace or remove images.
- Maintain an appropriate history of profile-image changes.

The profile picture should be clearly visible across InfoDot, including business search results, business listings, discussion spaces, comments, answers, business recommendations and other relevant areas.

## 4. Business Background and Cover Images

Allow every business to upload a background cover image for its profile.

The cover image should allow a business to visually communicate its real-world identity.

Examples include:

- Company premises.
- Offices.
- Shops.
- Restaurants.
- Factories.
- Workshops.
- Mining operations.
- Agricultural operations.
- Teams and employees.
- Products and services.
- Other genuine representations of the business.

Requirements:

- Upload a cover image.
- Preview before publishing.
- Crop, reposition and resize.
- Support desktop and mobile layouts.
- Maintain image quality.
- Optimise images for performance.
- Provide appropriate fallback designs.
- Allow replacement and removal.
- Maintain profile-image history where appropriate.

The cover image should be prominent without overwhelming the business information.

## 5. Business Image Gallery

In addition to the profile picture and cover image, consider a business gallery where companies can showcase their operations.

Allow authorised businesses to add photographs of:

- Their premises.
- Products.
- Services.
- Facilities.
- Teams.
- Projects.
- Equipment.
- Completed work.

Each image should support optional descriptions and categories.

Businesses should be able to manage the visibility of gallery content.

Do not allow gallery images to be presented as independently verified evidence unless they have actually been reviewed through an appropriate verification process.

## 6. Business Profile Information

Create a comprehensive business profile containing relevant information such as:

- Registered business name.
- Trading name.
- Business logo.
- Cover image.
- Business description.
- Industry.
- Business category.
- Products and services.
- Physical business location, where appropriate.
- Operating regions.
- Website.
- Business contact details.
- Business registration number, where applicable.
- Date established, where supplied.
- Business size or operational category, where appropriate.
- Social and professional links.
- Business verification status.

Allow authorised business owners and administrators to edit the profile.

Protect sensitive information and ensure that private registration documents and verification data are not publicly exposed.

## 7. Business Profile Presentation

The finished profile should have a polished and professional layout.

It should include:

- Prominent cover image.
- Business profile picture or logo.
- Business name.
- Verified status indicator, where applicable.
- Industry and business category.
- Short business description.
- Products and services.
- Business location or operating region.
- Website and contact information.
- Business gallery.
- Relevant InfoDot activity and contributions.
- Appropriate business trust information.

The profile must be responsive and look excellent on desktop, tablet and mobile.

The verified indicator must be clear, recognisable and difficult to confuse with an ordinary decorative icon.

---

# PART B — INFODOT BUSINESS VERIFICATION SYSTEM

## 8. Introduce Official Business Verification

Build an official InfoDot business verification system.

Its purpose is to establish whether a business account is genuinely associated with the business it claims to represent.

Verification should help prevent:

- Business impersonation.
- Fake company profiles.
- Fraudulent business listings.
- Fake supplier accounts.
- Identity theft.
- Misrepresentation of registered businesses.
- Fraudulent use of company branding.
- Duplicate accounts attempting to impersonate legitimate businesses.

Verification must be based on evidence, not simply on whether an account has filled in its profile information.

## 9. Business Verification Application

Introduce a dedicated verification application process.

An authorised business owner or representative should be able to request verification from the business profile.

The application should capture the information required to establish the business's identity.

Potential verification information includes:

- Registered business name.
- Trading name.
- Business registration number, where applicable.
- Country of registration.
- Business registration documentation.
- Business website, where available.
- Official business email address, where available.
- Business telephone number.
- Business operating address, where appropriate.
- Identity and authority of the person submitting the application.
- Supporting documents demonstrating their authority to represent the business.

For South African businesses, consider appropriate verification against available CIPC business-registration information and documentation.

For businesses registered in other countries, design the system to support the relevant local business registries and equivalent evidence.

Do not assume that every business has a company registration number. Support legitimate sole proprietors, informal businesses and other business structures through alternative verification routes.

## 10. Verification Methods

Build a flexible verification framework capable of supporting different verification methods.

Potential methods include:

### A. Business Registration Verification

Validate the supplied business registration information against an authoritative registry or approved verification provider where available.

### B. Official Email Verification

Verify control of an email address associated with the business's official domain.

Do not treat possession of a generic email address as proof of business ownership.

### C. Telephone Verification

Verify control of an official business telephone number through a suitable verification process.

### D. Business Representative Verification

Verify that the person applying has the authority to represent the business.

### E. Document Verification

Allow secure submission and review of appropriate business-registration and supporting documents.

### F. Manual Verification

Provide an administrative review process for businesses that cannot be automatically verified.

Support multiple methods where necessary.

Do not award verified status solely because one weak verification signal succeeds.

## 11. Verification Statuses

Introduce clear verification statuses.

Suggested statuses:

| Status | Meaning |
|---|---|
| Unverified | The business has not completed verification |
| Pending | Verification application has been submitted |
| Under review | Additional review is required |
| Verified | InfoDot has successfully verified the business identity |
| Rejected | The application did not meet verification requirements |
| Additional information required | Further evidence is required |
| Suspended | Verification has temporarily been suspended |
| Revoked | Previously granted verification has been withdrawn |

Each status must have clear rules and corresponding user-interface behaviour.

A rejected or pending account must not display the official verified indicator.

A verified status must be granted only after the required checks have been successfully completed.

## 12. Official Verified Business Badge

Once InfoDot has successfully verified a business, display an official verification badge.

The badge should appear consistently across relevant InfoDot experiences:

- Business profile.
- Business search results.
- Business directory.
- Business discussions.
- Business answers.
- Comments.
- Recommendations.
- Business networking features.
- Other places where the business identity is displayed.

When a user interacts with the badge, show a verification details panel.

The panel should communicate:

- This business has been verified by InfoDot.
- Verification status.
- Verification date.
- Relevant verification category or level.
- Whether verification is current.

Do not expose confidential registration documents, personal identity documents or sensitive verification information.

Clearly explain what the verification means and what it does not guarantee.

For example, business identity verification does not automatically guarantee financial stability, service quality, regulatory compliance or trustworthiness in every transaction.

## 13. Verification Renewal and Monitoring

Verification must not be a permanent, unmonitored status.

Implement:

- Verification expiry or review dates where appropriate.
- Periodic verification checks.
- Business information change detection.
- Ownership-change review.
- Registration-status review where supported.
- Verification suspension.
- Verification revocation.
- Reverification after significant changes.

If a business changes its registered identity information, ownership or other important details, determine whether additional verification is necessary.

Maintain a history of verification decisions and changes.

---

# PART C — BUSINESS IMPERSONATION AND DUPLICATE ACCOUNT DETECTION

## 14. Protect Verified Business Identities

This is a critical requirement.

Once a business has been verified on InfoDot, establish a protected business identity record.

The purpose is to prevent another account from registering and presenting itself as the same business using copied information, branding or registration details.

For every verified business, maintain a protected identity record containing relevant identity attributes.

Potential attributes include:

- Verified registered business name.
- Trading name.
- Business registration number.
- Country of registration.
- Verified business website.
- Verified official contact details.
- Profile picture or logo.
- Cover image.
- Relevant identity-document references.
- Verification history.
- Business ownership and authorised representatives.

Store sensitive verification information securely.

Use appropriate normalisation and matching methods for identity comparison.

## 15. Detect Duplicate Business Names

When someone attempts to create a business account using an existing verified business's registered name or trading name, compare the submitted information against existing verified identities.

The system must recognise:

- Exact name matches.
- Differences in capitalisation.
- Extra spaces.
- Punctuation differences.
- Common abbreviations.
- Minor spelling variations.
- Similar names designed to imitate an existing business.
- Alternative trading names.

Do not automatically reject every similar business name.

Different businesses may legitimately share similar names, particularly across different countries, industries or registration jurisdictions.

Instead, assess the available evidence and determine whether the new registration may represent an attempt to impersonate an existing business.

## 16. Detect Reused Business Registration Numbers

The business registration number should be treated as a strong identity attribute.

When another account attempts to register using the same registration number as an existing verified business:

1. Identify the existing verified business.
2. Compare the submitted business details.
3. Check whether the new applicant is already an authorised representative.
4. Determine whether the registration could represent a legitimate branch, subsidiary or authorised account.
5. Flag unexplained duplicate registration details for review.
6. Prevent the applicant from receiving verification until the conflict is resolved.

Where a business registration number is unique within its issuing registry, treat an exact duplicate as a high-priority identity conflict.

However, do not automatically label the applicant a scammer before investigation.

## 17. Detect Copied Profile Pictures and Cover Images

Introduce image-based impersonation detection.

When users upload profile pictures or cover images, compare them against protected visual assets belonging to existing verified businesses.

Use appropriate image-similarity techniques, such as:

- Perceptual hashing.
- Image fingerprinting.
- Near-duplicate image detection.
- Similarity analysis.
- Additional visual comparison methods where appropriate.

The objective is to detect cases where someone copies a verified business's:

- Logo.
- Profile picture.
- Cover image.
- Other protected branding.

The system should recognise that identical or similar images can also be used legitimately by:

- Authorised representatives.
- Franchises.
- Branches.
- Subsidiaries.
- Approved distributors.
- Businesses using shared corporate branding.

Image similarity should therefore contribute to a risk assessment rather than independently prove fraudulent intent.

## 18. Combined Identity Matching and Risk Assessment

The most important part of this system is the ability to compare multiple identity attributes together.

When a new business registers, assess:

- Business-name similarity.
- Registration-number match.
- Profile-image similarity.
- Cover-image similarity.
- Website similarity.
- Contact-detail similarity.
- Country and registration jurisdiction.
- Existing business relationships.
- Existing authorised representatives.

Use a configurable risk-assessment model.

Suggested outcomes:

| Risk classification | Suggested response |
|---|---|
| Low | Allow registration |
| Medium | Request additional information |
| High | Flag for manual review |
| Critical | Temporarily restrict verification and potentially suspicious identity claims pending investigation |

A combination of an exact registration-number match, near-identical branding and a matching registered business name should create a high-priority identity conflict.

The system should not allow an account to obtain a verified badge while an unresolved identity conflict exists.

## 19. Important: Distinguish Duplicate Identity from Fraud

Do not automatically classify every duplicate as a scam.

The system must distinguish between:

- An existing business owner creating another account.
- An authorised employee registering an account.
- An official branch.
- A subsidiary.
- A franchise.
- An authorised distributor.
- A legitimate business with a similar name.
- An account attempting to impersonate a verified business.
- A potentially fraudulent account.

Provide a process for legitimate applicants to demonstrate their relationship with an existing verified business.

Allow the verified business owner or authorised administrator to confirm or reject a claimed relationship.

Where necessary, escalate the case to InfoDot administrators.

---

# PART D — FRAUD FLAGS, REPORTING AND INVESTIGATION

## 20. Introduce Business Impersonation Alerts

When a potentially fraudulent duplicate account is detected, create a security alert.

The alert should contain:

- Existing verified business.
- New account involved.
- Matching business information.
- Similarity indicators.
- Registration-number conflict, if applicable.
- Profile-image comparison.
- Cover-image comparison.
- Risk classification.
- Date and time detected.
- Current review status.
- Recommended next action.

Notify the appropriate InfoDot administrators.

Where appropriate, notify the affected verified business owner.

Do not expose sensitive applicant information unnecessarily.

## 21. Business Profile Reporting

Allow users and businesses to report suspicious business profiles.

Include report categories such as:

- Impersonating another business.
- Using stolen branding.
- Fake business information.
- Duplicate business identity.
- Suspicious registration information.
- Fraudulent contact details.
- Misleading business representation.
- Other identity-related concerns.

Users should be able to provide supporting information.

Reports must be recorded, prioritised and routed to the appropriate review process.

## 22. Administrative Investigation Centre

Build an InfoDot administrative verification and fraud-monitoring interface.

Administrators should be able to:

- Review verification applications.
- Inspect submitted evidence.
- Compare business identities.
- Review duplicate-registration conflicts.
- Compare uploaded logos and cover images.
- Review reported profiles.
- Investigate impersonation alerts.
- Request additional evidence.
- Approve or reject applications.
- Suspend verification.
- Revoke verification.
- Restrict suspicious identity claims.
- Resolve legitimate duplicate-account cases.
- Record investigation outcomes.

Every administrative action must be audited.

Support appropriate reviewer permissions and separation of duties for sensitive verification decisions.

## 23. Fair Investigation and Appeal Process

Provide a clear process for applicants who are incorrectly flagged.

Allow them to:

- Receive an appropriate explanation.
- Submit additional evidence.
- Explain their relationship with an existing business.
- Request a review.
- Appeal a rejection or restriction.

Do not publicly label a business as fraudulent merely because it has been flagged.

Use carefully defined internal statuses such as:

- Potential impersonation.
- Identity conflict.
- Under investigation.
- Confirmed impersonation.
- Cleared after review.

Only use a confirmed fraud or impersonation classification after an appropriate investigation.

---

# PART E — DATABASE, ARCHITECTURE AND SECURITY

## 24. Establish a Reliable Business Identity Architecture

Inspect the existing InfoDot database and business-profile architecture before implementation.

Extend existing tables and services where appropriate rather than creating duplicate identity systems.

Design the appropriate data structures for:

- Business profile media.
- Business identity records.
- Verification applications.
- Verification evidence.
- Verification decisions.
- Verification history.
- Verified identity attributes.
- Authorised business representatives.
- Duplicate identity matches.
- Impersonation alerts.
- Fraud reports.
- Administrative investigations.
- Appeals.
- Audit events.

Use suitable relationships, indexing and constraints.

Sensitive verification information must be separated from public profile information.

Do not expose sensitive information through ordinary business-profile APIs.

## 25. Protect Verification Evidence

Verification documents may contain sensitive business and personal information.

Implement:

- Private document storage.
- Encryption where appropriate.
- Strict access controls.
- Secure document uploads.
- File validation.
- Malware scanning where available.
- Restricted administrative access.
- Secure document previews.
- Audit logging.
- Defined retention periods.
- Secure deletion when legally and operationally appropriate.

Never make submitted registration documents or identity documents publicly accessible.

## 26. Prevent Verification Abuse

Protect the verification process against:

- Repeated fraudulent applications.
- Automated account creation.
- Registration-number harvesting.
- Unauthorised access to verification records.
- Attempts to manipulate verification status.
- Fake supporting documents.
- Account takeover.
- Unauthorised changes to verified business details.
- Image-based impersonation.

Use rate limits, audit logs, appropriate authentication and risk-based additional checks.

Verification decisions must be made by authorised processes and cannot be changed by ordinary users through client-side requests or manipulated API parameters.

## 27. Privacy and Legal Considerations

Design the verification process to comply with applicable privacy and data-protection requirements.

For South African operations, consider POPIA requirements, including:

- Lawful processing.
- Purpose limitation.
- Data minimisation.
- Appropriate security safeguards.
- Retention and deletion.
- Data-subject rights.
- Third-party verification-provider arrangements.

Do not collect unnecessary personal information.

Clearly explain why verification information is required, who can access it and how it is used.

Support appropriate international data-protection requirements as InfoDot expands.

---

# PART F — USER EXPERIENCE AND BUSINESS TRUST

## 28. Make Verification Easy to Understand

Users should immediately understand the difference between:

- An unverified business.
- A business that has applied for verification.
- A verified business.
- A business whose verification is under review or suspended.

Display this information consistently.

Do not use misleading badges or ambiguous symbols.

Provide clear explanations of verification criteria.

## 29. Business Profile Trust Section

Introduce a trust section on the business profile.

Where appropriate, display:

- InfoDot verification status.
- Verification date.
- Verification category.
- Business identity information confirmed.
- Website verification status.
- Business contact verification status.
- Relevant public business information.
- Business reporting options.

Only display information that has actually been verified.

Do not imply that a business has been verified in areas that have not been checked.

## 30. Verified Business Protection

Verified businesses should have additional identity-protection capabilities.

Consider:

- Alerts when a potential duplicate is registered.
- Notifications when their verified branding is copied.
- Notifications about registration-number conflicts.
- Ability to report impersonation.
- Ability to confirm authorised representatives.
- Ability to manage branches and subsidiaries.
- Ability to review linked business profiles.
- Ability to request urgent investigation.

The verified business should have a clear channel for communicating with InfoDot about identity misuse.

---

# PART G — IMPLEMENTATION REQUIREMENTS

## 31. Inspect the Existing InfoDot Application

Before implementation, use Laravel Boost to inspect the current architecture.

Review:

- Business-profile models.
- Business-profile pages.
- Existing image-upload functionality.
- Media storage.
- Business-registration fields.
- User and business relationships.
- Roles and permissions.
- Admin functionality.
- Authentication and InfoDot identity.
- Existing verification capabilities.
- Existing reporting and moderation.
- Database structure.
- Existing API endpoints.
- Relevant installed design and security skills.

Determine what already exists, what is incomplete and what needs to be introduced.

Do not create a parallel profile, identity or verification system if an existing foundation can be extended.

Preserve existing functionality.

## 32. Implement the Complete Workflow

Do not stop after producing a design or technical plan.

Implement the working experience, including:

1. Business profile image upload.
2. Cover image upload.
3. Image cropping and optimisation.
4. Business gallery.
5. Updated public business profile.
6. Verification application.
7. Evidence submission.
8. Verification review.
9. Official verified badge.
10. Protected verified identity records.
11. Duplicate identity detection.
12. Image similarity detection.
13. Registration-number conflict detection.
14. Impersonation alerts.
15. Business reporting.
16. Administrative investigation.
17. Appeal and review workflow.
18. Verification renewal and revocation.

All functionality must use actual application data.

Do not create mock verification statuses, fake badges or simulated fraud alerts and present them as operational.

---

# PART H — TESTING AND ACCEPTANCE CRITERIA

## 33. Business Profile Testing

- [ ] Businesses can upload profile pictures.
- [ ] Businesses can upload cover images.
- [ ] Images can be cropped and repositioned.
- [ ] Image uploads are validated and optimised.
- [ ] Profile images display consistently across InfoDot.
- [ ] Cover images display correctly on desktop and mobile.
- [ ] Business galleries work.
- [ ] Only authorised business representatives can modify business profiles.
- [ ] Sensitive information remains private.

## 34. Verification Testing

- [ ] Businesses can apply for verification.
- [ ] Supporting documents can be submitted securely.
- [ ] Verification applications can be reviewed.
- [ ] Administrators can approve and reject applications.
- [ ] Verified businesses receive the official badge.
- [ ] Unverified businesses cannot display the official badge.
- [ ] Verification status changes are audited.
- [ ] Suspended and revoked verification is reflected across InfoDot.
- [ ] Verification renewal works where required.

## 35. Impersonation Detection Testing

Create realistic test scenarios.

### Scenario 1: Exact Business Duplicate

A verified business already exists. A new account registers using the same registered business name and registration number.

Expected result: The system identifies the identity conflict, blocks automatic verification and creates a high-priority review alert.

### Scenario 2: Copied Branding

A new account uploads the exact same profile picture and cover image as a verified business.

Expected result: The system identifies the visual similarity and assesses it alongside other identity information.

### Scenario 3: Minor Name Variation

A new account registers using a slightly modified version of an existing verified business name.

Expected result: The system identifies the potential similarity and determines whether additional review is appropriate.

### Scenario 4: Legitimate Branch

An existing verified business registers an authorised branch.

Expected result: The system allows an appropriate branch relationship to be established without incorrectly classifying the branch as fraudulent.

### Scenario 5: Authorised Employee

An employee creates an account and requests association with an existing verified business.

Expected result: The business owner or authorised administrator can confirm the relationship.

### Scenario 6: Different Business, Similar Name

Two legitimate businesses have similar names but different registration details and ownership.

Expected result: The system does not automatically accuse either business of impersonation.

### Scenario 7: Registration Number Reuse

A new account uses a registration number belonging to another verified business.

Expected result: The registration conflict is detected and verification is withheld until the discrepancy is resolved.

### Scenario 8: False Positive Appeal

A legitimate business is flagged because it uses shared corporate branding.

Expected result: The business can submit evidence and request a review. The case can be cleared by an authorised reviewer.

### Scenario 9: Verification Revocation

A previously verified business is found to have submitted false information.

Expected result: An authorised administrator can suspend or revoke verification, with the decision audited and the badge updated across InfoDot.

### Scenario 10: Unauthorised Verification Change

A normal business user attempts to modify their verification status through an API request.

Expected result: The request is denied and the attempt is appropriately logged.

## 36. Security Testing

- [ ] Users cannot access another business's verification evidence.
- [ ] Users cannot change their verification status.
- [ ] Users cannot impersonate authorised business representatives.
- [ ] Verification decisions are protected server-side.
- [ ] Registration conflicts cannot be bypassed through API manipulation.
- [ ] Image-matching results cannot expose private image data.
- [ ] Admin-only operations require appropriate permissions.
- [ ] Fraud reports are access-controlled.
- [ ] All important verification decisions are audited.
- [ ] Tenant and business ownership checks work correctly.

---

# PART I — FINAL DELIVERY REPORT

## 37. Provide an Evidence-Based Implementation Report

Once implementation is complete, provide:

1. Summary of the new business-profile experience.
2. Profile-picture and cover-image functionality.
3. Business gallery implementation.
4. Verification architecture.
5. Verification methods supported.
6. Verification statuses and badge implementation.
7. Business identity protection mechanisms.
8. Duplicate business-name detection.
9. Registration-number conflict detection.
10. Image similarity detection.
11. Impersonation alerts.
12. Administrative investigation functionality.
13. Appeal and false-positive handling.
14. Security and privacy controls.
15. Automated and manual test results.
16. Outstanding limitations.
17. Recommended next steps.

Clearly distinguish between completed, partially implemented, unimplemented and blocked functionality.

Do not claim a business registry integration or automated identity check works unless it has actually been connected and tested.

---

# FINAL PRODUCT VISION

InfoDot should become a platform where businesses can establish a recognisable and trustworthy digital identity.

A business should be able to show the world what it looks like, what it does, where it operates and who it is.

Other businesses should be able to identify legitimate companies and make more informed decisions about who they interact with.

Verification should provide a meaningful level of confidence that a business account is genuinely associated with the business it represents.

Most importantly, verified businesses should be protected against impersonation.

If someone attempts to create another account using an existing verified business's name, registration number, profile picture and cover image, InfoDot must recognise the identity conflict, flag it for investigation and prevent the new account from falsely presenting itself as the verified business.

The goal is not simply to add a blue tick to business profiles.

**The goal is to build a credible business identity and trust infrastructure for the InfoDot ecosystem, where legitimate businesses can establish their identity and fraudulent impersonation becomes significantly harder.**
