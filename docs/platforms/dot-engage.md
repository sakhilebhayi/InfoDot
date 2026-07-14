# Dot.Engage — CRM and Customer Engagement Platform

**Role:** The Customer Relationship and Revenue Layer of the Dot Ecosystem  
**URL:** `engage.infodot.app`  
**Tagline:** Know your customers. Own the relationship.

---

## Vision

Dot.Engage is not just a CRM. It is the **customer intelligence and revenue operations platform** that unifies prospect data, sales pipelines, marketing campaigns, and customer success workflows in one place — with every other Dot platform feeding it context.

Traditional CRMs like Salesforce, HubSpot, and Zoho CRM are isolated databases. Dot.Engage knows that a customer filed a support request this morning (from Dot.Forms), that their contract expires next month (from Dot.Files), that their usage of Dot.Agents dropped last week (from Dot.Analytics), and that they posted a complaint on Dot.Pulse yesterday. Every signal, one view.

---

## Architecture

```
Dot Ecosystem

├── Dot.Engage
│   ├── Contact and Account Database
│   ├── Sales Pipeline
│   ├── Marketing Campaigns
│   ├── Customer Success Playbooks
│   ├── Communication Hub
│   └── Reporting and Forecasting
│
└── Shared: PostgreSQL · Redis · Reverb · Meilisearch · Stripe (Cashier)
```

---

## Contact and Account Database

The foundation of Dot.Engage is a unified view of every person and organisation.

### Contact record
- Name, email, phone, social profiles, location
- Company / account link
- Tags, segments, and custom fields
- Activity timeline: emails, calls, form submissions, deals, support tickets
- Lead score (AI-calculated)
- Ecosystem activity: mentions on Dot.Pulse, files shared, agent interactions

### Account record
- Company name, domain, industry, size, location
- Associated contacts with roles
- All deals, contracts, and revenue history
- Health score: engagement level, risk signals, growth potential
- Custom fields per industry or segment

### Segmentation
- Dynamic segments that update automatically as contact data changes
- Segment by score, tags, deal stage, last activity, ecosystem behaviour
- Segment export for use in campaigns, Dot.Agents workflows, or Dot.Forms

---

## Sales Pipeline

A visual Kanban-style deal pipeline with automation.

### Pipeline stages (customisable)

```
Lead → Qualified → Proposal → Negotiation → Closed Won / Closed Lost
```

### Deal record
- Title, value, probability, expected close date
- Linked contact and account
- Activities: notes, emails, calls, meetings, tasks
- Files: proposals, contracts, quotes from Dot.Files
- AI win probability score updated on activity
- Commission tracking and split assignments

### Pipeline automation
- Auto-advance deals when conditions are met
- Create follow-up tasks when a deal goes idle
- Alert sales owner when a deal's close date passes
- Trigger a Dot.Agents workflow when a deal closes

---

## Marketing Campaigns

Email, SMS, and multi-channel campaigns with segmentation and analytics.

### Campaign types
- Email newsletters and sequences
- Drip campaigns triggered by behaviour (form submission, deal stage change)
- SMS broadcasts (via configurable gateway)
- In-app notifications pushed to Dot platform users
- Social post scheduling via Dot.Press

### Campaign builder
- Drag-and-drop email designer with branded templates
- Personalisation tokens: first name, company, deal value, any contact field
- A/B testing: subject lines, send times, content variants
- Automated sequences: send email 1, wait 3 days, if opened send email 2

### Campaign analytics
- Open rate, click rate, unsubscribe rate, bounce rate
- Conversion tracking: which campaign led to a deal or form submission
- Revenue attribution per campaign
- Audience engagement over time fed to Dot.Analytics

---

## Customer Success Playbooks

Structured playbooks ensuring consistent post-sale customer experience.

| Playbook | Trigger |
|---|---|
| Onboarding | Deal closed won |
| QBR preparation | 30 days before quarter end |
| Renewal | 60 days before contract expiry |
| At-risk intervention | Health score drops below threshold |
| Upsell | Usage patterns indicate expansion readiness |
| Churn recovery | Contact unsubscribes or goes silent |

Each playbook defines a sequence of tasks, emails, and check-ins automatically assigned to the customer success owner.

---

## Communication Hub

All customer communication in one timeline, regardless of channel.

- Email: send and receive, tracked opens and clicks
- Call logging: manual log or integration with VOIP systems
- WhatsApp / SMS: outbound messages with delivery tracking
- Meeting scheduler: calendar link and meeting notes
- InfoDot notifications: ecosystem events involving this contact

---

## AI Lead Intelligence

| Feature | Description |
|---|---|
| Lead scoring | AI-calculated score 0–100 based on engagement, profile fit, and behaviour |
| Win probability | Live probability score per deal, updated on each activity |
| Churn prediction | Contact-level risk score based on engagement signals |
| Next best action | AI recommendation for what to do next on a deal |
| Ideal customer profile matching | Compare new leads against historical won deals |

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Forms | Form submissions create and enrich contact records |
| Dot.Agents | Drip sequences and follow-ups executed by agents |
| Dot.Analytics | Pipeline, revenue, and campaign data feeds EIP |
| Dot.Finance | Closed deals trigger invoices in Dot.Finance |
| Dot.Files | Proposals, contracts, and signed documents stored here |
| Dot.Pulse | Community sentiment and mentions linked to contact records |
| InfoDot | Team members assigned as owners; permissions via InfoDot teams |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 250 contacts, basic pipeline, email | Included with InfoDot |
| Starter | 5,000 contacts, campaigns, playbooks | Per-seat monthly |
| Business | Unlimited contacts, AI scoring, sequences, analytics | Per-seat monthly |
| Enterprise | Custom fields, SSO, revenue attribution, API, SLA | Annual contract |
