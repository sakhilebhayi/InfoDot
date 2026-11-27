# R-61 brief (owner, 2026-11-24): Dot Ecosystem, centralised pricing, premium features and platform control system

Stored verbatim as given by the owner (the only change: spaced dashes in headings are colons, to keep the house rule of no dashes in written text). Its place in the programme and the gap analysis against what InfoDot already has are in `R61_GAP_ANALYSIS.md`.

---

## 1. Objective

We need to implement a unified pricing and feature monetisation system across all live Dot platforms.

Currently, Dot.Mines is the only platform with an established pricing structure. The other live platforms do not yet have their own pricing models.

I want you to develop an appropriate monetisation strategy for every other live Dot platform, based on the actual features, functionality and business value each platform provides.

The fundamental principle is:

**Activating a Dot platform must always be free. Using its basic functionality must remain accessible. However, certain valuable features and services within each platform should require payment.**

We must make monetisation affordable and attractive, particularly for small businesses. We do not want users to feel that they need to purchase an expensive subscription simply to use the ecosystem.

Dot.Mines must retain its existing plans, pricing, machine allocation structure and billing functionality. Do not modify its established commercial model unless explicitly instructed.

## 2. Audit Every Live Dot Platform

Before implementing anything, inspect all currently live Dot platforms, excluding Dot.Mines from the new pricing design.

Identify the actual functionality of each platform.

For every platform, establish:

- What the platform does.
- Its core functionality.
- Its most valuable features.
- Which features should remain free.
- Which features justify charging users.
- Which features have additional operating or infrastructure costs.
- Which features provide measurable business value.
- Whether pricing should be based on usage, capacity, transactions, credits or feature access.

Do not introduce charges simply to generate revenue.

A feature should only be monetised when there is a reasonable commercial justification.

Create a pricing strategy that is unique to each platform. Do not force all platforms into identical subscription structures.

## 3. Platform Activation Must Always Be Free

All live Dot platforms must be discoverable and accessible through the existing InfoDot ecosystem.

When a business wants to start using a platform, it should be able to activate it without paying an activation fee.

Examples:

- Activating Dot.HR must be free.
- Activating Dot.Finance must be free.
- Activating Dot.Projects must be free.
- Activating Dot.Analytics must be free.
- Activating Dot.Files must be free.

The same principle applies to all other eligible live platforms.

However, free activation does not mean unlimited access to every capability.

Each platform must have a clearly defined distinction between:

1. Free platform functionality.
2. Paid features.
3. Paid resource allocations.
4. Optional premium capabilities.

A user must be able to enter a platform, understand its value and use its free functionality before being asked to purchase anything.

## 4. Develop Individual Pricing Models for Every Platform

I am leaving the commercial pricing decisions to you.

Investigate each platform and decide which features deserve to be monetised.

For example, depending on actual functionality:

**Dot.Files**
- Free basic file management.
- Paid additional storage capacity.
- Paid advanced document processing.
- Paid advanced file-sharing or collaboration capabilities where justified.

**Dot.Analytics**
- Free basic dashboards and reports.
- Paid advanced analytics.
- Paid AI-generated insights.
- Paid advanced data processing and integrations.

**Dot.HR**
- Free basic employee records.
- Paid advanced recruitment workflows.
- Paid payroll-related capabilities where supported.
- Paid advanced HR analytics and automation.

**Dot.Projects**
- Free basic project management.
- Paid advanced project reporting.
- Paid automation.
- Paid additional project capacity or specialised collaboration features.

**Dot.Agents**
- Free access to basic agent management capabilities.
- Paid agent execution credits.
- Paid advanced AI workflows.
- Paid higher processing capacity.

These are illustrative examples, not predetermined requirements. Verify that the features actually exist or are technically feasible before assigning prices.

Apply the same level of analysis to every other live Dot platform.

For each monetised feature, define:

- Feature name.
- What it does.
- Why it deserves a price.
- Free alternative, if applicable.
- Pricing model.
- Proposed price in ZAR.
- Billing frequency.
- Usage or allocation limits.
- What happens when payment expires or usage is exhausted.

Prefer small, affordable purchases over expensive subscriptions wherever possible.

Consider once-off purchases, monthly allocations, usage credits, capacity-based pricing and optional premium packages.

Do not create artificial restrictions simply to force purchases.

## 5. Introduce a Centralised Pricing and Entitlement Engine

The entire Dot ecosystem must use a consistent centralised mechanism for determining which users and businesses can access paid functionality.

Develop a shared entitlement system that can:

- Identify premium features.
- Determine whether a business has purchased access.
- Validate the applicable subscription or allocation.
- Grant access after confirmed payment.
- Deny access when an entitlement is inactive or expired.
- Track usage where applicable.
- Support recurring and once-off purchases.
- Record transactions.
- Support refunds and entitlement reversals.
- Manage feature access across multiple platforms.

All paid feature access must be validated on the backend.

Do not rely exclusively on frontend locks or hidden buttons.

If a feature is locked, its underlying API, backend actions and data access must also be protected.

Use the existing InfoDot identity, authentication and billing infrastructure wherever possible.

Avoid creating separate, disconnected payment systems for every Dot platform.

## 6. InfoDot Must Be the Central Control Authority

This is a particularly important requirement.

The main InfoDot account must become the central authority for controlling the availability of every Dot platform and every monetised feature within those platforms.

I want a dedicated administrative management interface within the main InfoDot account.

From this interface, authorised administrators must be able to control:

### A. Platform-level controls

Every live Dot platform must have an administrative ON/OFF control.

For example:

| Platform | Status | Administrative action |
|---|---|---|
| Dot.Mines | ON | Disable |
| Dot.HR | ON | Disable |
| Dot.Finance | ON | Disable |
| Dot.Analytics | ON | Disable |
| Dot.Files | ON | Disable |
| Dot.Projects | ON | Disable |

Apply this to every live platform.

When a platform is switched OFF by InfoDot:

- Users must no longer be able to access its operational functionality.
- New platform actions must be blocked.
- Its API endpoints and backend operations must enforce the disabled state.
- Existing data must remain preserved.
- Existing subscriptions and purchases must remain recorded.
- Users should receive a clear maintenance or temporary unavailability message.
- The platform should remain visible where appropriate, but clearly indicate that it is temporarily unavailable.

Do not delete data, deactivate user accounts or cancel subscriptions simply because a platform has been switched OFF.

### B. Feature-level controls

Inside every platform, provide administrative ON/OFF controls for individual features.

For example:

Dot.Analytics:
- Basic dashboards: ON.
- Advanced reporting: ON.
- AI insights: ON.
- Advanced integrations: ON.

Dot.HR:
- Employee management: ON.
- Recruitment automation: ON.
- Advanced HR reporting: ON.
- Premium workflows: ON.

Apply the same approach to every platform.

The main InfoDot account must be able to disable an individual feature without disabling the entire platform.

This will allow us to temporarily disable a feature while developing, improving, testing or maintaining it.

When a feature is switched OFF:

- Its functionality must be unavailable to users.
- Its backend operations must be blocked.
- Its interface must communicate that the feature is temporarily unavailable.
- Its existing user data must remain intact.
- Its purchase history and entitlement records must remain intact.

### C. Pricing-level controls

The main InfoDot account must also be able to manage pricing for each monetised feature.

Administrators should be able to:

- Change prices.
- Enable or disable purchases.
- Create new pricing packages.
- Modify existing packages.
- Configure usage limits.
- Set free usage allowances.
- Configure promotional pricing.
- Set introductory offers.
- Configure billing periods.
- Manage regional pricing.

Any price changes must have appropriate effective dates and audit records.

Existing customers should not have their active purchases unexpectedly revoked or repriced without an appropriate migration or renewal policy.

## 7. Create a Centralised Platform and Feature Management Dashboard

Build a dedicated administrative interface within InfoDot called:

**Dot Ecosystem Control Centre**

This should provide a comprehensive view of every live Dot platform.

The dashboard must show:

- Platform name.
- Platform operational status.
- Platform development or maintenance status.
- Number of active users.
- Number of paying users.
- Number of monetised features.
- Active paid features.
- Disabled features.
- Current revenue.
- Available pricing packages.
- Active purchases.
- Feature usage statistics.

Administrators should be able to open any platform and manage its individual features.

The interface should support:

1. Platform management.
2. Feature management.
3. Pricing management.
4. Entitlement management.
5. Purchase management.
6. Usage monitoring.
7. Revenue reporting.
8. Maintenance controls.

Use clear status indicators and confirmation dialogs for important changes.

For potentially disruptive operations, provide a confirmation step explaining the consequences before applying the change.

## 8. Establish Proper Maintenance and Development Controls

I want to use the InfoDot administrative controls whenever we need to work on a particular Dot platform.

For example:

If we are redesigning Dot.Analytics, I should be able to switch Dot.Analytics OFF from InfoDot.

We can then work on the platform without users accessing incomplete or unstable functionality.

Once development and testing are complete, we should be able to switch it back ON.

The same must apply to individual features.

For example, if we are developing an improved recruitment module in Dot.HR, we should be able to disable that specific feature while keeping the rest of Dot.HR operational.

Introduce distinct operational states where appropriate:

- Active.
- Temporarily disabled.
- Under maintenance.
- Development/testing.
- Retired.

Ensure that these states are properly enforced throughout the ecosystem.

Do not confuse an administrative availability switch with a commercial entitlement.

A feature can be:
- Available and free.
- Available and paid.
- Temporarily unavailable.
- Under development.
- Retired.

These are separate conditions and must be managed independently.

## 9. User-Facing Premium Feature Experience

When a user encounters a paid feature, it should be clearly identified as a premium capability.

If the user has not purchased access, display a professional locked-feature experience.

The interface should explain:

- What the feature does.
- What business problem it solves.
- Why the user might need it.
- How much it costs.
- What the purchase includes.
- Whether it is once-off or recurring.
- Whether usage is limited.

Provide a straightforward purchase process.

After successful payment confirmation, the feature should become available automatically according to the purchased entitlement.

Do not expose payment prompts for features that are free.

Do not display purchase options for features that InfoDot administrators have disabled.

Distinguish between:

- Feature unavailable because it is not purchased.
- Feature unavailable because it is temporarily disabled.
- Feature unavailable because the platform is under maintenance.
- Feature unavailable because the user lacks the required permissions.

Each situation should have an appropriate message.

## 10. Pricing Philosophy

The objective is to make the Dot ecosystem affordable and commercially sustainable.

Our primary initial audience is small and medium-sized businesses in South Africa.

Use ZAR as the initial reference currency.

Consider the following principles:

- Keep basic functionality free.
- Make premium features affordable.
- Charge for genuine value.
- Prefer transparent pricing.
- Avoid unnecessary recurring subscriptions.
- Use resource-based pricing where appropriate.
- Use consumption-based pricing where appropriate.
- Avoid charging users twice for the same shared resource.
- Make cross-platform purchases work where shared entitlements are supported.

Every proposed price must be justified by customer value, operating costs and commercial sustainability.

Do not invent prices simply to fill a pricing table.

## 11. Security, Architecture and Reliability

Before implementation, audit the current InfoDot architecture and identify the best way to implement centralised platform and feature controls.

Requirements:

- Centralised administrative authority.
- Role-based access control.
- Backend-enforced platform availability.
- Backend-enforced feature availability.
- Backend-enforced paid entitlements.
- Tenant isolation.
- Auditable pricing changes.
- Auditable administrative actions.
- Reliable payment verification.
- Transaction integrity.
- Protection against unauthorised feature access.
- Appropriate caching and invalidation.
- Consistent enforcement across web interfaces, APIs and integrations.
- Automated tests.
- Error monitoring.
- Safe maintenance procedures.

Ensure that switching a platform or feature OFF takes effect reliably across all relevant application instances and services.

Avoid hardcoded pricing and scattered feature checks.

Use a maintainable central configuration and entitlement architecture.

Do not introduce a single point of failure that makes all Dot platforms unavailable if the central control service experiences a temporary issue. Define safe operational behaviour and appropriate caching for administrative configuration.

## 12. Implementation Phases

Implement this initiative in controlled phases.

**Phase 1: Audit and Monetisation Design**
- Audit all live platforms.
- Identify free and premium functionality.
- Propose individual pricing structures.
- Preserve Dot.Mines pricing.
- Produce a complete monetisation matrix.

**Phase 2: Central Control Infrastructure**
- Build platform ON/OFF controls.
- Build feature ON/OFF controls.
- Build maintenance states.
- Implement backend enforcement.
- Implement audit logging.

**Phase 3: Pricing and Entitlements**
- Implement centralised pricing configuration.
- Implement paid feature entitlements.
- Integrate existing payment infrastructure.
- Implement purchase and renewal workflows.
- Protect premium functionality.

**Phase 4: Platform Integration**
- Integrate each platform with the central system.
- Introduce feature locks.
- Introduce purchase experiences.
- Validate cross-platform entitlements.
- Test all paid and free functionality.

**Phase 5: Revenue Intelligence**
- Introduce revenue dashboards.
- Monitor purchases and feature consumption.
- Track conversion rates.
- Measure platform profitability.
- Identify opportunities to improve pricing.

## 13. Definition of Done

This initiative is only complete when:

1. Every live Dot platform has been assessed for monetisation opportunities.
2. Every platform has free core functionality.
3. Every platform has appropriately selected premium features where commercially justified.
4. Premium features are genuinely locked until the required purchase is confirmed.
5. Free features remain accessible.
6. Dot.Mines continues operating with its existing pricing model.
7. The main InfoDot account can switch any platform ON or OFF.
8. The main InfoDot account can switch individual features ON or OFF.
9. Platform and feature controls are enforced on the backend.
10. Pricing can be changed centrally without code modifications.
11. Existing purchases and user data are preserved.
12. All critical purchase and entitlement workflows have automated tests.
13. The system works consistently across all connected Dot platforms.

## FINAL INSTRUCTION

Approach this as a fundamental commercial and operational improvement to the Dot ecosystem.

Do not simply add pricing pages, locked icons or frontend switches.

Build a proper centralised system that gives InfoDot complete administrative control over platform availability, feature availability, pricing and paid access.

The main InfoDot account must remain the central authority.

Every Dot platform should remain independently useful, affordable to enter and commercially sustainable through its own carefully selected premium capabilities.

**Free to activate. Free to explore. Pay only for the additional capabilities and resources that deliver value.**

That is the monetisation philosophy we want across the entire Dot ecosystem.
