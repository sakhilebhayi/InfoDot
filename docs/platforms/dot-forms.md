# Dot.Forms — Intelligent Form and Survey Platform

**Role:** The Data Collection and Input Layer of the Dot Ecosystem  
**URL:** `forms.infodot.app`  
**Tagline:** Every answer goes somewhere useful.

---

## Vision

Dot.Forms is not a survey tool. It is the **structured data collection platform** that feeds every other Dot platform with real-world inputs — from customer enquiries and job applications to regulatory compliance forms, field inspections, and asset registration. Every form submission is a data event that can trigger a workflow, update a record, or route a task.

Competitors like Typeform, Google Forms, JotForm, and SurveyMonkey collect data. Dot.Forms collects data **and routes it** — into Dot.Engage, Dot.Projects, Dot.Finance, Dot.Agents, and anywhere else in the ecosystem.

---

## Architecture

```
Dot Ecosystem

├── Dot.Forms
│   ├── Form Builder (drag-and-drop + logic)
│   ├── Response Inbox and Database
│   ├── Routing and Action Rules
│   ├── Embedded and Shared Forms
│   ├── Offline-capable Field Forms
│   └── AI Form Intelligence
│
└── Shared: PostgreSQL · Redis · S3 (file uploads) · Meilisearch
```

---

## Form Builder

A visual drag-and-drop builder with conditional logic and branching.

### Field types

```
Basic               Advanced             Media
─────               ────────             ─────
Short text          Signature            File upload
Long text           Payment (Stripe)     Image capture
Number              Geolocation          Video submission
Date / time         NFC tag scan         Audio recording
Dropdown            Barcode / QR scan
Checkbox            Address lookup
Radio buttons       Rating (stars/NPS)
Scale (1–10)        Matrix / grid
Yes / No            Repeating section
Email               Hidden field
Phone               Calculated field
```

### Conditional logic
- Show or hide fields based on previous answers
- Branch to different pages based on selections
- Skip sections for irrelevant respondents
- Score responses and branch on score ranges

### Multi-page forms
- Configurable page breaks with progress indicator
- Save and resume — respondents complete in multiple sessions
- Per-page validation before advancing

---

## Form Types

| Type | Use case |
|---|---|
| Survey | Customer satisfaction, NPS, internal feedback |
| Application | Job applications, grant requests, vendor registration |
| Inspection | Site visits, equipment checks, safety audits |
| Registration | Event sign-up, asset registration, onboarding |
| Order form | Product or service ordering with payment |
| Intake form | Lead capture, service requests, support intake |
| Compliance | Regulatory declarations, risk assessments |
| Field report | On-site data collection with GPS and photo capture |

---

## Response Routing

Every form submission is an event. Routing rules determine where it goes.

### Built-in routes

| Destination | What happens |
|---|---|
| Dot.Engage | Creates or updates a contact or lead record |
| Dot.Tasks | Creates a task assigned to a user or team |
| Dot.Projects | Adds a submission to a project inbox |
| Dot.Finance | Triggers an invoice or payment collection |
| Dot.Agents | Starts an AI agent workflow with response as input |
| Email notification | Notifies a user or team of the submission |
| Webhook | Posts the response payload to an external URL |

### Conditional routing
Route differently based on field values:
- If department = "HR" → assign to HR team
- If urgency = "Critical" → create a high-priority task and notify on-call
- If score < 6 → flag as at-risk and create a follow-up task in Dot.Engage

---

## Response Database

All submissions are stored in a searchable, filterable response database.

- Full-text search across all responses
- Filter by form, date range, field value, or routing outcome
- Export to CSV, XLSX, or JSON
- Bulk actions: assign, tag, archive, delete
- Per-response status tracking: New, In Progress, Resolved, Archived
- Calculated summary stats for numeric and rating fields

---

## Sharing and Embedding

- Shareable public URL with optional password protection
- Embed via iframe in any web page or Dot.Press site
- Embed in Dot.docs documents
- QR code generated for every form (for print and signage)
- API endpoint for programmatic submission from external systems

---

## Offline Field Forms

For teams working without internet connectivity (field inspections, remote sites, mining operations, agricultural checks).

- Forms sync to the mobile browser cache before going offline
- Submissions queue locally and sync when connectivity returns
- GPS coordinates and timestamp captured at submission time
- Camera access for photo evidence
- Signature capture on touchscreen devices

---

## AI Form Intelligence

| Feature | Description |
|---|---|
| Smart field detection | Suggests appropriate field type as you type a question |
| Duplicate response detection | Flags submissions that appear to be duplicates |
| Sentiment analysis | Scores open-text responses for sentiment |
| Auto-tagging | Tags responses by topic for filtering and analytics |
| Summary generation | AI summary of all responses to an open-text question |
| Anomaly detection | Flags unexpected patterns in response data |

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Engage | Form submissions create and update CRM contacts |
| Dot.Tasks | Responses trigger task creation with routing rules |
| Dot.Finance | Payment-enabled forms create transactions in Dot.Finance |
| Dot.Agents | Submissions start agent workflows automatically |
| Dot.Analytics | Response rates, completion times, and trends feed analytics |
| Dot.Files | File upload responses stored in Dot.Files / S3 |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 3 forms, 100 responses/month, basic fields | Included with InfoDot |
| Starter | 20 forms, 1,000 responses/month, conditional logic | Per-seat monthly |
| Business | Unlimited forms and responses, routing, AI, payments | Per-seat monthly |
| Enterprise | Offline mode, custom branding, advanced routing, SLA | Annual contract |
