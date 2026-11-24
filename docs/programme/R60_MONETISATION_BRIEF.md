# R-60 brief (owner, 2026-11-24): Dot Ecosystem, Unified Platform Monetisation and Revenue Strategy

Stored verbatim as given by the owner (the only change: the spaced dash in the title is a colon and a comma, to keep the house rule of no dashes in written text). The place of this brief in the programme is in `R59_AUDIT_AND_PLAN.md`, section 11.

---

## 1. Strategic Objective

We need to introduce a sustainable monetisation strategy across all 22 live Dot platforms, excluding Dot.Mines from the redesign because Dot.Mines already has its own established plans, pricing structure and machine allocation billing model.

The objective is to identify opportunities to generate revenue from the valuable features and services provided by every other live Dot platform.

I want each platform to have its own monetisation model, just like Dot.Mines, where users pay a relatively small amount to access specific platform resources, capabilities or services.

However, I do not want to simply charge users for opening applications or accessing basic functionality.

I want us to identify the features that deliver measurable business value and create affordable ways for users to pay for what they actually use.

**The principle: Make entry affordable, make valuable features accessible, and monetise usage, capacity, resources and advanced capabilities.**

## 2. Use Dot.Mines as the Monetisation Reference Model

First, thoroughly inspect Dot.Mines and understand its existing monetisation architecture.

Specifically, investigate how its machine allocation system works.

For example:

When a user purchases machine allocation space, that allocation gives them the ability to access and utilise their allocated machine resources and associated machine data throughout the Dot.Mines ecosystem.

This is important because the user isn't simply purchasing access to a page or a feature. They are purchasing a resource that unlocks functionality and provides value across multiple areas of the platform.

I want the other Dot platforms to adopt a similar philosophy.

Identify equivalent resources, allocations, consumption units or premium capabilities within each platform.

Examples of possible monetisation mechanisms include:

- Resource allocation.
- Storage allocation.
- Usage credits.
- Processing capacity.
- AI consumption credits.
- Transaction credits.
- Communication credits.
- Active user or seat allocation.
- Project allocation.
- Document processing capacity.
- Automation execution credits.
- Premium reporting access.
- Advanced analytics.
- Integration capacity.
- Business listing packages.
- Service usage credits.

These are examples, not mandatory pricing mechanisms. Determine the most appropriate model for each platform based on its actual functionality.

## 3. Conduct a Complete Audit of All 22 Live Platforms

Before proposing prices, inspect the actual implementation of every live Dot platform.

Do not rely solely on platform names or assumptions about what they do.

Inspect:

- Existing features.
- Backend functionality.
- Database structure.
- Existing subscriptions.
- Existing billing integrations.
- User workflows.
- Business workflows.
- Resource consumption.
- Third-party API costs.
- Infrastructure costs.
- Existing usage limits.
- Existing platform entitlements.
- Cross-platform dependencies.
- Features that create measurable value.

For every platform, identify:

1. What the platform does.
2. Its most valuable features.
3. Which features should remain free.
4. Which features could be monetised.
5. What users would actually be purchasing.
6. What resource or capability the purchase unlocks.
7. Whether the purchased resource can create value across other Dot platforms.
8. How usage should be measured.
9. What happens when the allocation is exhausted.
10. How the platform can generate recurring revenue.

Create a complete monetisation assessment for all 22 platforms.

Do not modify Dot.Mines pricing, plans or existing billing behaviour. Its existing implementation should be treated as the reference model.

## 4. Develop a Unique Monetisation Model for Each Platform

Do not force every platform into the same subscription structure.

Each Dot platform solves a different business problem, and its pricing should reflect the value it provides.

Consider the following monetisation opportunities where they genuinely fit the platform.

### Resource-based monetisation

Users purchase an allocation of something tangible, such as storage, capacity, projects, processing resources or other platform-specific resources.

### Consumption-based monetisation

Users purchase credits and consume them when performing particular actions.

Examples include AI requests, document processing, automation execution and communications.

### Transaction-based monetisation

Users pay a small fee when completing certain revenue-generating or commercially valuable transactions.

### Feature-based monetisation

Basic functionality remains available, while advanced capabilities, specialised tools, premium reporting and advanced automation require payment.

### Capacity-based monetisation

Users pay according to the amount of resources they need, such as active employees, projects, storage, integrations or processing capacity.

### Service-based monetisation

Where a platform provides a service with an actual delivery cost, introduce affordable usage fees.

Select the appropriate approach for each platform. A platform may combine two or more models if doing so makes commercial sense.

## 5. Monetisation Across the Entire Dot Ecosystem

An important requirement is that purchases should not necessarily provide value only within the platform where they were made.

I want you to investigate opportunities for cross-platform monetisation.

For example:

If a user purchases additional storage capacity through Dot.Files, that storage allocation should be recognised wherever eligible Dot platforms need access to the user's shared storage resources.

If a user purchases AI credits, investigate whether those credits can be consumed across eligible AI-powered Dot platforms through a centralised credit system.

If a business purchases additional employee seats, those seats should be recognised by eligible platforms across the ecosystem.

If a user purchases communication credits, investigate whether eligible communication services can consume those credits without requiring separate purchases for every platform.

The objective is to avoid fragmented purchases and create a connected ecosystem where users can purchase resources once and benefit from them wherever applicable.

However, distinguish between shared resources and platform-specific resources.

For example, storage, AI credits and user seats may be shared, while specialised resources may remain exclusive to individual platforms.

Never allow a purchase in one platform to automatically unlock unrelated premium features elsewhere.

Every entitlement must be explicitly defined, enforced and auditable.

## 6. Pricing Strategy

The pricing philosophy should be based on affordability and volume.

I want users to feel that Dot platforms are affordable enough to use regularly without thinking twice about every small purchase.

Develop realistic pricing proposals for each platform.

Consider small South African businesses as the initial pricing audience.

Use South African Rand (ZAR) as the initial reference currency.

Where appropriate, investigate pricing in small increments rather than expensive monthly subscriptions.

For example, evaluate:
- Small once-off resource purchases.
- Affordable monthly allocations.
- Pay-as-you-use credits.
- Larger discounted resource bundles.
- Optional premium plans.
- Volume-based pricing.

Do not automatically use these example prices as the final prices.

Calculate suggested pricing based on:

- Infrastructure costs.
- Third-party service costs.
- AI consumption costs.
- Payment processing fees.
- Storage costs.
- Expected usage.
- Support costs.
- Desired gross margins.
- Competitive positioning.
- Perceived customer value.

Every proposed price must have a commercial justification.

Avoid charging for features whose marginal cost is negligible unless they deliver clear additional value.

## 7. Create a Unified Dot Wallet and Entitlement Infrastructure

Investigate whether the existing billing architecture can support a centralised Dot Wallet and resource allocation system.

The proposed system should allow businesses and users to:

- View their available resources.
- Purchase platform-specific allocations.
- Purchase shared ecosystem credits.
- View their transaction history.
- Monitor consumption.
- View remaining balances.
- Receive low-balance notifications.
- Configure automatic top-ups where supported.
- View invoices and payment records.
- Manage recurring allocations.
- Upgrade or downgrade allocations.

Each purchase must generate an auditable transaction.

Each resource consumption event must be recorded.

Implement reliable transaction processing, idempotency, balance protection, refund handling and concurrency safeguards.

Never rely on frontend checks to enforce paid access.

The backend must verify entitlements before allowing paid actions.

Where a user exhausts an allocation, clearly explain what has been exhausted and offer the appropriate purchase option.

Do not unexpectedly interrupt unrelated functionality.

## 8. Preserve a Useful Free Experience

One of the most important objectives is to encourage widespread adoption of the Dot ecosystem.

Do not make every useful feature paid.

Every platform should have an accessible free entry point that allows users to understand its value.

Identify a sensible balance between:

- Free core functionality.
- Affordable paid allocations.
- Advanced premium features.
- High-volume usage packages.
- Enterprise or specialised offerings.

A new business should be able to join InfoDot and start using the Dot ecosystem without immediately being forced to purchase multiple subscriptions.

The objective is to convert users through genuine product value, not artificial restrictions.

## 9. Build the Dot Monetisation Management Centre

Introduce a centralised administrative interface for managing monetisation across the Dot ecosystem.

Administrators should be able to:

- Configure pricing for each platform.
- Create resource packages.
- Configure credit allocations.
- Set usage limits.
- Manage free allowances.
- Configure premium entitlements.
- Set regional pricing.
- Configure volume discounts.
- Monitor revenue by platform.
- Monitor revenue by feature.
- Analyse consumption patterns.
- Monitor refunds.
- Identify suspicious usage.
- Compare platform profitability.

Include reporting for:

- Total ecosystem revenue.
- Monthly recurring revenue.
- One-time purchase revenue.
- Revenue per platform.
- Revenue per business.
- Revenue per active user.
- Average purchase value.
- Repeat purchase rate.
- Resource consumption.
- Gross margin estimates.
- Payment failures.
- Subscription churn.

All reporting must use actual transaction and consumption records.

## 10. User Experience and Pricing Presentation

Each platform must communicate its monetised features clearly and naturally.

Users should understand:

- What they are purchasing.
- Why they need it.
- What it unlocks.
- How much it costs.
- How long it lasts.
- Whether it is reusable.
- Whether it is shared across the ecosystem.
- How much remains.
- What happens when it runs out.

Introduce contextual purchase prompts at appropriate moments.

For example, if a user attempts to generate an advanced report that requires additional processing credits, explain the requirement and present the available packages.

Do not bombard users with advertisements or repetitive upgrade messages.

The purchase experience must be seamless, consistent and professionally designed across all Dot platforms.

Follow the existing InfoDot design system and the premium, minimal, enterprise-ready design principles used throughout the ecosystem.

## 11. Required Deliverables Before Implementation

Before changing the codebase, produce a detailed monetisation strategy document covering all 22 live platforms.

For each platform, include:

| Required assessment | Description |
|---|---|
| Platform | Actual live Dot platform |
| Core value | What it provides |
| Free features | What remains accessible |
| Paid features | What users pay for |
| Monetisation model | Allocation, credits, subscription, transaction or hybrid |
| Purchase unit | Exactly what the customer buys |
| Proposed pricing | Suggested ZAR pricing |
| Consumption rules | How usage is calculated |
| Cross-platform value | Where purchased resources can be reused |
| Cost considerations | Infrastructure and operating costs |
| Revenue opportunity | How the platform generates revenue |

Also provide:

1. A comparison of monetisation opportunities across all 22 platforms.
2. Recommended pricing packages.
3. A centralised wallet and entitlement architecture proposal.
4. An assessment of existing billing infrastructure.
5. A cross-platform resource sharing map.
6. Potential infrastructure and third-party cost risks.
7. A phased implementation roadmap.
8. A testing and acceptance criteria document.

Clearly distinguish between existing functionality, proposed functionality and functionality that requires development.

## 12. Implementation Instructions

Do not begin by blindly adding payment buttons or subscription restrictions.

First, understand the actual functionality of each platform and establish what users would be willing to pay for.

Then identify the best monetisation model for each platform.

Prioritise models that are:

- Easy to understand.
- Affordable.
- Commercially sustainable.
- Simple to purchase.
- Easy to measure.
- Reusable across eligible platforms.
- Scalable internationally.

Use Dot.Mines as the reference for resource-based monetisation, but do not copy its machine allocation model where it does not make sense.

Preserve existing plans, subscriptions, payment integrations and functionality unless a change has been explicitly approved.

Implement in controlled phases with automated tests and production validation.

Do not use mock billing, simulated transactions, fake balances or placeholder purchase functionality.

A feature is only complete when the purchase, payment confirmation, allocation, entitlement enforcement, consumption tracking and transaction history all work end-to-end.

## THE ULTIMATE OBJECTIVE

Transform the Dot ecosystem into an affordable, interconnected business technology ecosystem where every platform has a clear and sustainable revenue model.

Users should not feel that they are paying for access to software they barely use.

They should feel that they are purchasing useful business resources, additional capacity and capabilities that help them accomplish more.

**One ecosystem. Multiple valuable platforms. Affordable purchases. Connected resources. Sustainable revenue.**

Make the Dot ecosystem commercially sustainable while preserving the original ambition of making powerful business technology accessible to businesses of every size.
