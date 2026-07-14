# Dot.Central — Ecosystem Administration and Control Platform

**Role:** The Administration and Governance Layer of the Dot Ecosystem  
**URL:** `central.infodot.app`  
**Tagline:** One place to govern everything.

---

## Vision

Dot.Central is not an admin panel. It is the **ecosystem governance and operations platform** — the control centre from which IT administrators, platform managers, and executive stakeholders oversee every Dot platform, every user, every integration, and every system health signal from a single surface.

As the Dot ecosystem grows to 16+ platforms, each with its own configuration, permissions, and operational state, the management overhead compounds exponentially. Dot.Central eliminates that overhead by giving administrators a unified place to provision access, audit activity, configure integrations, enforce policies, and respond to platform incidents — without switching between 16 separate admin interfaces.

---

## Architecture

```
Dot Ecosystem

├── Dot.Central
│   ├── Platform Registry and Status
│   ├── User and Team Administration
│   ├── Permission and Role Management
│   ├── Audit Log and Compliance
│   ├── Integration Hub
│   ├── Billing and Subscription Management
│   └── System Health Dashboard
│
└── Shared: PostgreSQL · Redis · Reverb · Meilisearch · Sentry
```

---

## Platform Registry

A real-time view of every Dot platform in the ecosystem.

### Platform status board

| Column | Description |
|---|---|
| Platform | Name and URL |
| Status | Online / Degraded / Offline |
| Active users | Users online right now |
| Last deploy | Date and time of last deployment |
| Error rate | Errors per minute from Sentry |
| Response time | Average P95 response time |
| Queue depth | Pending jobs in the queue |

### Platform health detail
Each platform card expands to show:
- Uptime over last 30 days
- Recent incidents with root cause summaries
- Resource consumption: CPU, memory, storage
- Active Reverb connections
- Scout index status and last sync time

---

## User and Team Administration

### User management
- View all users across the ecosystem with last active timestamp
- Search by name, email, team, or platform activity
- Impersonate a user for support debugging (with audit log entry)
- Force logout: invalidate all tokens for a user immediately
- Suspend an account: prevent login without deleting data
- Export user list with filters for compliance reporting

### Team management
- View all teams with member counts and platform memberships
- Create, rename, and archive teams
- Move users between teams
- Merge teams with full data migration preview
- Orphan team detection: teams with no active members

### Role and permission matrix

A centralised view of what every role can do on every platform.

```
                   InfoDot  Dot.Files  Dot.Finance  Dot.Agents  ...
User                 ✓          ✓           —            —
Team Member          ✓          ✓           ✓            ✓
Team Admin           ✓          ✓           ✓            ✓ (manage)
Finance User         ✓          ✓           ✓ (all)      —
Platform Admin       ✓ (all)    ✓ (all)     ✓ (all)      ✓ (all)
Ecosystem Admin      ✓ (super)  ✓ (super)   ✓ (super)    ✓ (super)
```

Permissions can be modified at the organisation, team, or individual level.

---

## Audit Log

An immutable, searchable record of every significant action across all platforms.

### What is logged
- Authentication: logins, logouts, failed attempts, token issuance
- Permission changes: role assignments, team changes
- Data access: which user accessed which resource and when
- Administrative actions: impersonation, suspension, configuration changes
- Financial events: payment processing, plan changes, invoice generation
- Security events: suspicious activity flags, rate limit hits, policy violations

### Audit log features
- Full-text search across all events
- Filter by platform, user, event type, date range, IP address
- Export to CSV or JSON for external SIEM integration
- Immutable: no event can be edited or deleted (append-only)
- Retention: configurable — default 1 year, enterprise up to 7 years

---

## Integration Hub

A registry of all active integrations between Dot platforms and external services.

### Internal integrations
Shows the live status of every Dot-to-Dot connection:
- InfoDot → Dot.Files: SSO handoff, file metadata sync
- Dot.Engage → Dot.Finance: deal-to-invoice trigger
- Dot.Agents → All platforms: read/write permissions status

### External integrations

| Integration | Status | Actions |
|---|---|---|
| Stripe | Connected | Rotate keys, view webhook log |
| AWS S3 | Connected | Test connectivity, view usage |
| Meilisearch | Connected | Re-index, view index health |
| Sentry | Connected | View error volume, configure alerts |
| SMTP / SES | Connected | Test send, view bounce rate |
| Custom webhook | Active | View delivery log, retry failed calls |

---

## Billing and Subscription Management

### Organisation billing
- Current plan and next billing date
- Per-platform seat counts and overage alerts
- Invoice history with PDF download
- Payment method management
- Upgrade/downgrade flow with immediate effect and proration
- Usage-based billing breakdown by platform and seat

### Cost centre allocation
For enterprise organisations managing multiple departments:
- Allocate platform costs to cost centres
- Export departmental billing breakdown to Dot.Finance
- Approval workflow for plan upgrades above a threshold

---

## Policy Management

Organisation-wide policies enforced automatically.

| Policy | Description |
|---|---|
| Password policy | Minimum length, complexity, rotation frequency |
| Session policy | Max session duration, idle timeout |
| MFA policy | Require 2FA for all users, admins, or specific teams |
| IP allowlist | Restrict access to known IP ranges |
| Data retention | Per-platform retention duration before auto-purge |
| File upload policy | Allowed MIME types and max file size |
| API rate limits | Custom limits per team or user |

---

## System Health Dashboard

An executive and engineering view of ecosystem-wide health.

### Key indicators
- Ecosystem uptime score (average across all platforms)
- Total active sessions right now
- Total jobs processed in the last 24 hours
- Error rate trend over 7 days
- Database query performance: P50, P95, P99
- Queue worker health: running, stalled, failed jobs

### Incident management
- Create an incident: title, severity, affected platforms, status
- Incident timeline: updates posted as the incident progresses
- Incident resolution: root cause, actions taken, prevention plan
- Incident history: full log for post-mortems and SLA reporting

---

## Ecosystem Integration

Dot.Central is inherently cross-platform. It reads from all platforms and writes configuration back.

| Platform | Role in Dot.Central |
|---|---|
| InfoDot | User and team data source; authentication gateway |
| All Dot platforms | Health metrics, error rates, and audit events reported here |
| Dot.Finance | Billing and subscription management feeds financial records |
| Dot.Analytics | Ecosystem-level usage and health data feeds EIP |
| Sentry | Error monitoring integrated into platform health view |

---

## Revenue Model

Dot.Central is included at no additional charge for customers on Business and Enterprise plans. It represents the governance value of the ecosystem rather than a standalone product.

| Plan | Access |
|---|---|
| Free | Not available |
| Team | Read-only view of platform status and user management |
| Business | Full administration: users, teams, integrations, audit log |
| Enterprise | Policy management, billing allocation, advanced audit, SLA dashboard |
