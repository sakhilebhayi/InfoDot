# R-64 — InfoDot premium UI/UX: forms and inputs, progress, notifications, motion

Received 2026-11-25. QUEUED, NOT STARTED: it follows R-63 (booking), which is in progress. The owner's two prompts are stored verbatim below, followed by the single consolidated prompt written from them. The consolidated prompt is the one to run.

---

## Contents

1. The consolidated prompt (the one to run)
2. Owner prompt A, verbatim (premium UI/UX, motion, progress indicators and notification experience)
3. Owner prompt B, verbatim (global form and input design system redesign)

---

## 1. The consolidated prompt

**R-64: make InfoDot feel like a premium business ecosystem. Forms and inputs, progress, notifications, motion.**

### How to run this
Audit first, read-only. Then bring the owner an inventory, a phased plan and the decisions in section 9, and build only after approval. One phase per pull request, merged on green; nothing is deployed without the owner's instruction. Where a phase touches a shared package or another Dot platform, apply it to one platform at a time, InfoDot first.

### 1. The standard
InfoDot should feel like one carefully designed, award-quality product. Every field, button, card, table, menu, notification, progress indicator and transition follows one visual and interaction language. The best-finished control on the platform today is the search bar; it is the starting reference for the finish, proportions and feel of every input. Use the installed design skills (the Awwwards skill for direction and motion quality, with Hallmark, Impeccable, design-taste-frontend, emil-design-eng and ui-ux-pro-max for the build and finish pass), applied inside InfoDot's existing "Yard" design language, not instead of it. The aim is not to make InfoDot look animated. It is to make it feel exceptional to use.

### 2. What already exists (inspect, then extend; never duplicate)
- The Yard design system (`resources/css/infodot.css`, the `ix-*` classes), the shared dot-ui package (a checksummed manifest, so changes to it follow its own versioning and re-sync process across platforms), Roboto everywhere (R-52), tokens in `:root`, light and dark themes of equal quality, the nonce-based CSP (no inline handlers), Livewire 3 and Alpine, and tracked `public/build` (a CSS change means a real build is committed).
- Notifications: `InAppNotices` and `UserNotice` (kinds, dedupe key, per-kind opt-out), one `Mailable` per domain (for example `EnquiryActivity`), scheduled reminder commands, and the booking system's planned notifications (R-63 phase 5). Extend these; do not create a second system.
- Progress data that already exists and is real: the Getting Started steps, onboarding events, profile completeness, licence and seat counts, platform entitlements, tasks and projects in their own platforms.
- The icon font is a self-hosted subset (`tests/Feature/IconFontTest.php` enforces it; new icons mean rebuilding it).

### 3. Part A: forms and inputs (global redesign)
Audit every form element on every InfoDot page and every live Dot platform before changing anything, and produce an inventory: text, email, password, search, number, telephone, date and time, select, multi-select, searchable selectors, textarea, rich text, checkbox, radio, toggle, file upload, validation messages, labels and helper text, form buttons, multi-step forms, modal and inline forms, filters and settings controls. Record duplicated components, conflicting or legacy CSS and different implementations of the same control.
Then:
- One premium input language: refined surfaces, controlled radii, subtle borders, balanced contrast, consistent type, well-proportioned heights, clean icons, deliberate padding, consistent spacing between label, field, hint and error. No browser-default look, heavy borders, heavy shadows, gradients or generic Bootstrap forms. Different input types get fitting treatments inside the same system.
- Fix alignment at the root, with shared layout components and a grid, never one-off margins: equal heights within a form, label placement, left and right edges, side-by-side fields with matching height and spacing, icon and text alignment, button alignment, dropdown positioning, responsive columns.
- Design every state: default, hover, focus (especially refined), active, filled, disabled, read-only, valid, invalid, loading, required. Validation messages sit in reserved space so they cause no layout shift. Placeholders stay secondary to labels and values. Required and optional are marked elegantly.
- Rebuild the complex controls: custom select and searchable select (never clipped, with open and close transitions), date and time pickers (a modern calendar, clear selected, available and unavailable dates, responsive), file upload (drag and drop, previews, real progress and errors), checkbox, radio and toggle (consistent, accessible targets), textarea and rich text (padding, toolbar, focus), password fields (visibility control, feedback).
- Mobile is designed, not shrunk: touch-sized fields, stacked columns, no horizontal overflow, no clipped menus, correctly placed pickers, keyboard-aware behaviour.
- Reusable global components (text input, search input, select, textarea, checkbox, radio group, toggle, date picker, file uploader, validation message, form section) on shared design tokens (colour, type, radius, height, spacing, border, focus ring, transition, disabled). Improve existing components rather than replacing them, and introduce no dependency or second component system.
- Preserve everything that works: submission behaviour, Livewire bindings and events, validation, backend processing, search, uploads, authentication, permissions and workflows.

### 4. Part B: progress indicators (real data only)
Add contextual progress wherever it tells a person something true: profile completion, business onboarding and company setup, team invitation, platform activation (which recommended platforms are on, what is next), account verification, document upload and processing, form and multi-step workflow progress, project and task completion (from actual completed tasks and defined stages), and subscription or usage limits where relevant. Provide linear, circular, step, milestone, percentage and compact dashboard variants. Every figure is calculated from system data; never a decorative or fabricated bar. Changes animate subtly and are announced to assistive technology (`role="progressbar"` with values, or live regions).

### 5. Part C: notifications
Audit the existing notification and email infrastructure first, then extend it.
- In-app: bell with unread count, dropdown and a full notification page, read and unread states, categories (account and security, business workspace, team invitations, projects and tasks, bookings and appointments, documents and files, billing and subscriptions, platform activity, system announcements), timestamps, icons, direct links, mark read and mark all read, history, and dismiss or archive. Notifications are actionable: an invitation can be accepted or declined from the notification itself.
- Email: branded, responsive InfoDot templates, clear subjects, concise copy, one clear action button, a plain fallback when a link cannot be opened. Events include registration and verification, password and security events, invitations, team membership changes, bookings and their changes, project assignments, important task updates, document sharing, billing and payment confirmations, subscription changes and announcements.
- Preferences: per channel (in-app, email), per category, important versus optional, and frequency (instant or digest) where it makes sense. Security and account-protection notices cannot be switched off.
- Behaviour: event-driven, from real activity only; deduplication, grouping, rate limits and digests instead of an email for every minor event; strict membership, permission and tenant isolation, so nobody is told about anything they may not see. No fake or simulated notifications.

### 6. Part D: motion, interaction and feedback
- A motion design system defined once and reused everywhere: durations, easing curves, hover, focus, page transition, modal and drawer, scroll reveal, loading and expand and collapse standards, as tokens. CSS first; add no animation dependency where CSS or what the app already ships can do it. Respect `prefers-reduced-motion` with a considered alternative.
- Scroll and page motion, used selectively: section reveals, gentle fades and short upward movement, staggered groups, smooth navigation transitions, scroll-linked progress, animated statistics only where the figures are real, smooth expand and collapse, refined modal and drawer transitions, parallax only on selected marketing sections. No bouncing, long durations, large movement or anything that gets in the way of reading.
- Hover and micro-interactions only on things that are interactive (buttons, inputs, search, selects, navigation, tabs, cards that are links, platform tiles, table rows that act, checkboxes, toggles, menus, icons), with clear pressed, active and focus states. Hover never carries essential information or function; touch works the same.
- Feedback: toasts for short-lived news, persistent messages for what people need to revisit, confirmation dialogs, save and processing indicators, error messages that say what happened and how to fix it. Never show success before the backend confirms it.
- Loading: skeletons for content-heavy pages, consistent spinners and button loading states, smooth swaps between loading and loaded, informative empty states. Loading indicators reflect real activity and never imply completion.

### 7. Cross-cutting requirements
- Performance: no layout shift from animation, no scroll-handler maths, few observers and listeners, smooth on low-powered phones, animate `transform`, `opacity` and similar cheap properties.
- Accessibility: contrast, keyboard operation, visible focus, screen-reader announcements for progress and notifications, reduced motion, touch targets.
- Consistency: one language across InfoDot and every Dot platform through the shared package, rolled out platform by platform.
- Honesty: no decorative progress, no fake notifications, no visual-only functionality.

### 8. Phases (each a PR; audit first)
1. Global audit: forms inventory, existing progress data, notification and email infrastructure, current animations and transitions, reusable components and tokens, performance and accessibility risks.
2. Design tokens and the motion system (including reduced motion).
3. Core form components and input states.
4. Complex controls (select, date picker, uploader, rich text, toggles).
5. Progress components and their real data sources.
6. In-app notification centre.
7. Email templates and event delivery.
8. Notification preferences, grouping, deduplication, digests.
9. Scroll motion, page transitions, hover and micro-interactions.
10. Loading, success, error and empty states.
11. Platform-wide rollout, one platform at a time, then alignment correction of every form layout.
12. Quality assurance: real-browser checks at nine widths, accessibility, performance on a low-powered profile, and automated tests for event delivery, permissions, email, unread counts, progress calculations and reduced-motion behaviour.

### 9. Decisions to put to the owner before building
- The order of platforms for the rollout, and who owns changes to the shared dot-ui package.
- Whether motion stays CSS-only (recommended) or may use the animation libraries the landing page already loads.
- Default notification frequency (instant versus daily digest) per category.
- Which categories exist in code today and which are new; where booking notifications (R-63 phase 5) plug in so they are not built twice.
- Email design direction and the sending identity.
- How the light and dark themes should treat motion and the new input surfaces.

### 10. Definition of done
Every major form and input follows the new language; the search bar and every other input look like one product; alignment and spacing are corrected at the root; complex controls are rebuilt; all states are consistent; desktop and mobile are polished; existing functionality is intact; progress, notifications, motion and loading follow one coherent language; reusable components prevent drift; nothing legacy-looking remains. Every interaction feels intentional, every progress indicator is true, every notification has a purpose, every animation earns its place.

---

## 2. Owner prompt A, verbatim

ADDITIONAL SCOPE — INFODOT PREMIUM UI/UX, MOTION, PROGRESS INDICATORS & NOTIFICATION EXPERIENCE
Extend the existing global form and input redesign into a broader platform-wide UI/UX improvement initiative.
The objective is to make InfoDot feel like a premium, modern, interactive business ecosystem. Every page should feel thoughtfully designed, responsive, intuitive and enjoyable to use.
Do not limit these improvements to forms. Apply them across the entire InfoDot experience, including dashboards, navigation, business profiles, platform pages, cards, tables, modals, onboarding, settings, and other interactive elements.
Use the Awwwards design skill to guide the visual direction, interaction design and motion quality.
1. Platform-Wide Progress Indicators
Introduce meaningful progress indicators throughout InfoDot to help users understand their progress, what remains to be completed, and what they should do next.
Identify opportunities to introduce:

* Profile completion progress.
* Business onboarding progress.
* Company setup progress.
* Team invitation progress.
* Platform activation progress.
* Project completion progress.
* Task completion progress.
* Document upload and processing progress.
* Form completion progress.
* Multi-step workflow progress.
* Account verification progress.
* Subscription or usage-limit indicators, where relevant.

Progress indicators should be contextual. Only display them where they provide meaningful information.
Examples:
Business onboarding
Show a visual completion indicator that guides a business through creating its company profile, adding essential information, inviting team members, and activating its first platforms.
Projects and tasks
Display progress based on actual completed tasks, milestones or defined project stages.
File uploads
Show real upload progress, processing status and completion feedback.
Platform activation
Show users which recommended platforms they have activated and which useful next steps remain.
Design different types of progress indicators where appropriate:

* Linear progress bars.
* Circular progress indicators.
* Step-by-step progress trackers.
* Milestone indicators.
* Completion percentages.
* Compact progress indicators for dashboard cards.

Use subtle animations when progress changes, but never fabricate progress or display percentages that are not calculated from actual system data.
2. Centralised Notification Experience
Improve the notification experience across InfoDot by introducing a consistent, intelligent notification system.
Users should receive relevant notifications both inside InfoDot and through email.
Audit the existing notification infrastructure before implementation and extend it rather than creating duplicate systems.
In-app notifications
Improve the notification centre with:

* Notification icon with an unread count.
* Notification dropdown or dedicated notification page.
* Clear distinction between read and unread notifications.
* Notification categories.
* Notification timestamps.
* Relevant icons and visual indicators.
* Direct links to the relevant page or action.
* Mark as read and mark all as read.
* Notification history.
* Ability to dismiss or archive notifications where appropriate.

Notifications should be organised into meaningful categories, such as:

* Account and security.
* Business workspace.
* Team invitations.
* Projects and tasks.
* Bookings and appointments.
* Documents and files.
* Billing and subscriptions.
* Platform activity.
* System announcements.

Use contextual notifications that help users take action rather than simply informing them that something happened.
For example, instead of displaying only "You have a new invitation", provide an actionable notification that allows the user to review and accept or decline the invitation.
Email notifications
Integrate email notifications with InfoDot's existing email infrastructure.
Relevant events should trigger email notifications where appropriate, including:

* Registration and account verification.
* Password and security events.
* Business invitations.
* Team membership changes.
* Appointment bookings and changes.
* Project assignments.
* Important task updates.
* Document-sharing events.
* Billing and payment confirmations.
* Subscription changes.
* Important platform announcements.

Email notifications should use professionally designed, branded InfoDot email templates.
Requirements:

* Responsive email layouts.
* Clear subject lines.
* Consistent branding.
* Concise message content.
* Relevant action buttons.
* Links to the appropriate InfoDot pages.
* Appropriate fallback information if a user cannot access a linked page.

Avoid sending duplicate or unnecessary emails.
Notification preferences
Give users control over their notification preferences.
Allow users to configure:

* In-app notifications.
* Email notifications.
* Notification categories.
* Important versus optional notifications.
* Notification frequency, where appropriate.

Critical security and account-related notifications must not be silently disabled where they are required for account protection.
Respect user preferences, applicable privacy requirements and existing account security policies.
3. Intelligent Notification Behaviour
Notifications should be event-driven and relevant to the user's actual activities.
Examples:

* A business owner receives a notification when a team member joins.
* A user receives a notification when assigned a new task.
* A business receives a notification when someone books an appointment.
* A project manager receives a notification when a milestone is completed.
* A user receives a notification when a shared document is updated.
* A business receives a notification when its profile setup is incomplete, where reminders are enabled.

Avoid overwhelming users.
Implement sensible notification grouping, deduplication, rate limiting and frequency controls.
Where appropriate, introduce notification summaries instead of sending individual emails for every minor event.
Notifications must respect company membership, permissions and tenant isolation. Users must never receive information about activities they are not authorised to access.
4. Premium Scroll Animations and Page Transitions
Introduce more sophisticated scrolling and motion throughout InfoDot.
The objective is to make browsing the platform feel smooth, polished and engaging without compromising performance or usability.
Explore appropriate motion patterns such as:

* Subtle section reveal animations.
* Fade-in and fade-out transitions.
* Gentle upward or downward movement as content enters the viewport.
* Staggered appearance of cards and content groups.
* Smooth navigation transitions.
* Subtle parallax effects on selected marketing sections.
* Scroll-linked progress indicators.
* Animated statistics where the underlying figures are real.
* Smooth expansion and collapse of content.
* Refined modal and drawer transitions.
* Animated loading and empty states.

Apply scroll animations selectively.
Not every element needs to move. The goal is to create a refined experience, not a distracting interface.
Use motion to establish visual hierarchy, guide attention and make transitions feel natural.
Avoid excessive parallax, large movement distances, unnecessary bouncing, long animations and effects that interfere with reading or navigation.
5. Interactive Hover Effects and Micro-interactions
Introduce refined hover and interaction effects across all appropriate interactive elements.
This includes:

* Buttons.
* Input fields.
* Search bars.
* Dropdowns.
* Navigation links.
* Sidebar items.
* Cards.
* Business profile elements.
* Platform tiles.
* Tables and table rows.
* Icons.
* Tabs.
* Checkboxes.
* Toggles.
* Clickable labels.
* Action menus.
* Interactive dashboard components.

Examples of appropriate effects:
Buttons

* Subtle colour or surface transitions.
* Slight elevation where appropriate.
* Refined icon movement.
* Clear pressed and active states.

Input fields

* Smooth border and focus transitions.
* Subtle focus-ring treatment.
* Clear visual indication when selected.
* Consistent feedback when validation fails.

Cards

* Gentle elevation or border changes.
* Subtle movement where it improves discoverability.
* Clear indication that an element is clickable.

Navigation

* Smooth active-state transitions.
* Clear hover feedback.
* Refined indicator movement.

Icons

* Small, purposeful animations for selected actions.
* Consistent interaction feedback.

Do not apply hover effects to static content that is not interactive. Every hover effect should communicate that an element is interactive or provide useful feedback.
6. Interaction Feedback and System States
Ensure that every meaningful user action receives appropriate feedback.
Introduce or improve:

* Success notifications.
* Error notifications.
* Warning messages.
* Informational messages.
* Loading indicators.
* Skeleton loading states.
* Empty states.
* Confirmation dialogs.
* Save indicators.
* Processing indicators.
* Submission feedback.

Examples:

* When a user saves a form, provide clear confirmation.
* When a file uploads, show its upload and processing progress.
* When an action fails, explain what happened and how to resolve it.
* When a user activates a platform, provide confirmation and guide them to the next step.
* When a background operation is still processing, communicate its current state.

Use elegant toast notifications for short-lived feedback and persistent notifications for important information that users may need to revisit.
Never show a success message before the backend confirms that the action succeeded.
7. Loading Experience and Perceived Performance
Improve the experience users see while pages and components are loading.
Implement:

* Skeleton loaders for content-heavy pages.
* Consistent loading indicators.
* Button loading states during submissions.
* Progressive rendering where appropriate.
* Smooth transitions between loading and loaded states.
* Meaningful empty states when there is no data.

Avoid displaying blank screens, abrupt content shifts, or generic loading spinners when a more informative loading experience is possible.
Loading indicators must reflect actual application activity and must not imply that an operation has completed when it has not.
8. Motion Design System
Establish a consistent motion language across InfoDot.
Define reusable motion standards for:

* Animation duration.
* Easing curves.
* Hover transitions.
* Focus transitions.
* Page transitions.
* Modal animations.
* Scroll reveals.
* Loading animations.
* Expand and collapse interactions.

Use subtle, premium motion rather than exaggerated effects.
Ensure animations are consistent across all Dot platforms and do not introduce visual inconsistencies between different sections.
Respect the user's operating-system reduced-motion preference. Provide accessible alternatives for users who prefer reduced motion.
Avoid unnecessary JavaScript animation dependencies if CSS or existing application capabilities can achieve the same result efficiently.
9. Performance, Accessibility and Responsiveness
All UI/UX enhancements must maintain production-level performance.
Requirements:

* Animations must not cause noticeable layout shifts.
* Avoid excessive DOM observers and unnecessary event listeners.
* Prefer efficient CSS transitions where suitable.
* Avoid expensive scroll calculations.
* Ensure smooth performance on lower-powered mobile devices.
* Maintain accessible contrast.
* Preserve keyboard navigation.
* Provide visible focus states.
* Ensure screen readers can understand notification and progress updates.
* Respect reduced-motion settings.
* Ensure all interactions work on touch devices.

Hover effects must never be the only way to expose essential information or functionality.
10. Global Consistency Across InfoDot
These improvements must be implemented across the entire InfoDot ecosystem.
Do not improve only the homepage or selected dashboard pages.
Audit all existing sections and identify opportunities to improve the user experience consistently.
Reuse the global design system and shared components established during the form redesign.
Ensure the notification centre, progress indicators, motion effects, interaction states and loading experiences follow one coherent visual language.
11. Implementation and Quality Assurance
Before implementation:

1. Audit existing notification services and email infrastructure.
2. Audit existing progress-tracking capabilities and available data.
3. Inspect existing animations, transitions and interactive components.
4. Identify reusable components and existing design tokens.
5. Identify performance or accessibility issues that could be affected.

Implement the work in logical phases:

* Phase 1: Global UI/UX audit.
* Phase 2: Progress indicators.
* Phase 3: In-app notifications.
* Phase 4: Email notification integration.
* Phase 5: Notification preferences and event management.
* Phase 6: Scroll animations and transitions.
* Phase 7: Hover effects and micro-interactions.
* Phase 8: Loading, success, error and empty states.
* Phase 9: Performance, accessibility and responsive testing.

Test actual event delivery, notification permissions, email delivery, unread counts, progress calculations, animation behaviour and mobile interactions.
Do not create decorative progress bars, fake notifications, simulated events or visual-only functionality.
12. Final Experience Standard
InfoDot should feel like a premium digital business ecosystem that users genuinely enjoy interacting with.
Every interaction should feel intentional.
Every action should provide clear feedback.
Every progress indicator should communicate meaningful progress.
Every notification should have a purpose.
Every animation should improve the experience rather than distract from it.
Every page should feel alive, polished and professionally designed.
The final result should combine the usability and clarity of a great productivity platform with the visual sophistication and interaction quality of an award-winning digital product.
The objective is not to make InfoDot look animated. The objective is to make InfoDot feel exceptional to use.

---

## 3. Owner prompt B, verbatim

INFODOT — GLOBAL FORM & INPUT DESIGN SYSTEM REDESIGN
1. Objective
Conduct a complete visual and usability redesign of all forms, input fields, dropdowns, selectors, text areas, buttons associated with forms, and other data-entry components across InfoDot.
Currently, most fields across the platform look outdated, inconsistent, poorly aligned, and reminiscent of old Windows desktop applications. They do not reflect the premium design standard that InfoDot is intended to represent.
The existing search bar is currently the best-looking input component on the platform. Use it as the initial visual reference for the quality, finish, proportions, and overall aesthetic expected from other input components.
The objective is to establish a consistent, modern, premium input experience across every InfoDot page.
Every field should look like it belongs to the same carefully designed product.
Use the Awwwards design skill to guide the visual direction and produce a polished, contemporary interface. Do not simply apply generic styling or make superficial cosmetic changes.
2. Conduct a Global Form Audit First
Before making changes, inspect the entire InfoDot application and identify every page and component containing form elements.
Audit all instances of:

* Text input fields.
* Email and password fields.
* Search fields.
* Number and telephone inputs.
* Date and time pickers.
* Dropdowns and select menus.
* Multi-select fields.
* Text areas.
* Checkboxes and radio buttons.
* Toggle switches.
* File upload controls.
* Rich-text editors.
* Searchable selectors.
* Form validation messages.
* Input labels and helper text.
* Form action buttons.
* Multi-step forms.
* Modal and popup forms.
* Inline editing fields.
* Filter and settings controls.

Do not limit the audit to the main dashboard or registration pages. Include every live Dot platform and every accessible section of InfoDot.
Identify duplicated components, inconsistent styling, legacy CSS, conflicting styles, and components that use different implementations for the same type of input.
Produce an inventory before implementation so that no major area is overlooked.
3. Establish a Premium Global Input Design Language
Create a unified visual language for all form elements.
Use the existing InfoDot search bar as the starting reference, while improving it where necessary.
The new design should communicate:

* Premium quality.
* Simplicity.
* Precision.
* Modernity.
* Consistency.
* Professionalism.
* Ease of use.

Visual direction:

* Clean, refined input surfaces.
* Carefully controlled border radiuses.
* Subtle and sophisticated borders.
* Balanced background and surface contrast.
* Consistent typography.
* Properly proportioned input heights.
* Beautifully designed icons.
* Thoughtful internal padding.
* Clear visual hierarchy.
* Subtle transitions and interaction feedback.
* Consistent spacing between labels, fields, descriptions, and errors.

Avoid outdated browser-default styling, heavy borders, excessive shadows, unnecessary gradients, oversized controls, and generic Bootstrap-style forms.
Do not make every field look identical regardless of purpose. Different input types should have appropriate visual treatments while remaining part of the same design system.
4. Fix Alignment and Layout Problems Globally
This is one of the most important parts of the redesign.
Many existing fields are not properly aligned, have inconsistent dimensions, or appear uneven within their respective forms.
Correct all alignment issues across the platform.
Enforce consistent rules for:

* Input heights within the same form.
* Label positioning.
* Horizontal and vertical alignment.
* Left and right edges of related fields.
* Consistent field widths.
* Internal padding.
* Spacing between form rows.
* Spacing between form sections.
* Alignment of icons and input text.
* Dropdown positioning.
* Button alignment.
* Responsive column layouts.

Fields that belong together should share a clear grid and alignment structure.
For example, two fields displayed side by side should have matching heights, aligned labels, consistent widths where appropriate, and equal spacing.
Do not use arbitrary margins or one-off positioning fixes to hide layout problems. Correct the underlying layout structure and reusable components.
Forms must look intentional, balanced, and professionally composed.
5. Redesign Every Input State
Do not focus only on how fields look when they are empty.
Design all relevant interaction states:

* Default.
* Hover.
* Focus.
* Active.
* Filled.
* Disabled.
* Read-only.
* Valid.
* Invalid.
* Loading.
* Required.

Focus states should be especially refined, with subtle visual feedback that makes it obvious which field the user is interacting with.
Validation messages should be positioned consistently and should not cause unpredictable layout shifts.
Required fields should be indicated elegantly without cluttering the interface.
Placeholder text must remain visually secondary to entered content and labels.
6. Labels, Instructions and Form Hierarchy
Improve the way forms communicate information.
Ensure:

* Labels are clear and consistently positioned.
* Labels are not confused with placeholder text.
* Supporting descriptions are readable and unobtrusive.
* Required and optional fields are clearly distinguished.
* Error messages are concise and useful.
* Form sections have appropriate headings.
* Long forms are broken into logical sections.
* Related fields are visually grouped.

Avoid unnecessary labels, descriptions, and decorative elements that make forms feel crowded.
Where appropriate, use progressive disclosure so users only see additional fields when they are relevant.
7. Redesign Complex Form Components
Give particular attention to components that commonly look outdated or inconsistent:
Dropdowns and selectors

* Custom-designed menus.
* Smooth opening and closing transitions.
* Clear selected states.
* Search functionality where useful.
* Properly positioned menus that do not get clipped.
* Consistent styling with the rest of the form.

Date and time pickers

* Modern calendar interface.
* Clear navigation.
* Properly distinguished selected, available, and unavailable dates.
* Responsive layouts.

File uploads

* Beautiful drag-and-drop surfaces.
* Clear upload instructions.
* File previews where applicable.
* Upload progress and error states.

Checkboxes, radios and toggles

* Custom, consistent styling.
* Clear selected and unselected states.
* Accessible interaction targets.

Text areas and rich-text editors

* Proper sizing and padding.
* Consistent typography.
* Refined toolbars.
* Clear focus and editing states.

Password fields

* Proper visibility controls.
* Clear validation feedback.
* Consistent sizing and alignment.

8. Mobile and Responsive Design
The redesign must be fully responsive.
Do not simply shrink desktop forms to fit mobile screens.
Design forms according to how a premium mobile application should work.
Requirements:

* Appropriate field heights and touch targets.
* Full-width fields where appropriate.
* Properly stacked columns.
* No horizontal overflow.
* No clipped dropdown menus.
* Properly positioned mobile date pickers.
* Readable labels and validation messages.
* Consistent spacing on smaller screens.
* Keyboard-aware form behaviour where applicable.

Test common mobile viewport sizes, tablets, laptops, and large desktop displays.
9. Create Reusable Global Components
Where possible, establish or improve a centralised set of reusable InfoDot form components.
Examples:

* Global text input.
* Global search input.
* Global select.
* Global text area.
* Global checkbox.
* Global radio group.
* Global toggle.
* Global date picker.
* Global file uploader.
* Global validation message.
* Global form section.

These components should use shared design tokens for:

* Colours.
* Typography.
* Border radius.
* Input heights.
* Spacing.
* Borders.
* Focus rings.
* Transitions.
* Disabled states.

Use the existing Laravel, Livewire, and Tailwind architecture appropriately.
Do not introduce unnecessary dependencies or duplicate component systems.
Where existing components are already reusable, improve them instead of creating replacements.
10. Preserve Functionality
This is a visual and usability redesign, not a reason to break existing functionality.
Preserve:

* Form submission behaviour.
* Livewire bindings and events.
* Validation rules.
* Backend processing.
* Database relationships.
* Search behaviour.
* File uploads.
* Authentication.
* Permissions.
* Existing business workflows.
* Existing responsive functionality.

Every redesigned component must remain fully functional.
Do not replace working functionality with static visual demonstrations.
11. Quality Assurance
After implementation, perform a global visual and functional review.
Check for:

* Misaligned fields.
* Inconsistent heights.
* Uneven spacing.
* Broken responsive layouts.
* Incorrect dropdown positioning.
* Clipped content.
* Inconsistent labels.
* Poor contrast.
* Broken validation messages.
* Unresponsive controls.
* Conflicting CSS.
* Duplicated styling.
* Inconsistent appearance between platforms.

Compare related forms across different pages to ensure they follow the same design language.
Run the relevant automated tests and add regression tests where necessary.
12. Implementation Strategy
Implement this systematically:
Phase 1 — Audit: Identify all form elements, their locations, existing implementations, and visual problems.
Phase 2 — Design system: Establish the global input design language and reusable components.
Phase 3 — Core components: Redesign the most frequently used fields and complex controls.
Phase 4 — Platform-wide rollout: Apply the new components and styling to every InfoDot page and live Dot platform.
Phase 5 — Alignment correction: Inspect and correct every form layout, grid, spacing, and responsive issue.
Phase 6 — Quality assurance: Test the visual appearance and actual functionality across devices and workflows.
Do not stop after redesigning a few prominent forms. The objective is platform-wide consistency.
13. Definition of Done
The redesign is complete only when:

1. Every major form and input across InfoDot follows the new design language.
2. The search bar and other inputs feel like parts of the same premium product.
3. Alignment and spacing problems have been corrected.
4. Dropdowns, selectors, date pickers, uploads, and other complex controls have been redesigned.
5. All input states are visually consistent.
6. Desktop and mobile experiences are polished.
7. Existing functionality remains intact.
8. Reusable components reduce future design inconsistencies.
9. No obvious legacy-looking form elements remain.

Final design principle: InfoDot is a premium business platform. Every interaction, including something as simple as typing into a text field, must reinforce that standard. No page should feel unfinished, outdated, or visually disconnected from the rest of the ecosystem.
