# Dot.Agents — AI Automation Platform

**Role:** The Automation and Intelligence Execution Layer of the Dot Ecosystem  
**URL:** `agents.infodot.app`  
**Tagline:** Automate everything. Delegate to AI.

---

## Vision

Dot.Agents is not a workflow builder that happens to have AI steps. It is the **AI operating system** of the Dot ecosystem — where every repetitive, rule-based, or decision-heavy task across every Dot platform is handled by intelligent agents rather than manual human effort.

Where n8n, Zapier, and Make.com connect apps via triggers and actions, Dot.Agents goes further: agents understand context, read and write across every Dot platform, decide what to do next, learn from outcomes, and report their value back into Dot.Analytics.

The result is an organisation that operates with fewer manual interventions. Agents run 24/7, adapt to changing conditions, and improve over time.

---

## Architecture

```
Dot Ecosystem

├── Every Dot platform  ← agents read/write across all of them
│
├── Dot.Agents
│   ├── Agent Builder (visual + code)
│   ├── Agent Library (marketplace)
│   ├── Agent Runner (execution engine)
│   ├── Decision Logs and Audit Trail
│   ├── Knowledge Base connector
│   └── Dot.Analytics integration
│
└── Shared: PostgreSQL · Redis · Reverb · Laravel Queues · LLM APIs
```

---

## Core Concepts

### What is an Agent?

An agent is a named, configurable entity that:
1. Has a defined trigger (event, schedule, webhook, or manual)
2. Has access to a set of tools (read a file, send an email, query a database, call an API)
3. Has an instruction set describing its goal
4. Uses an LLM to reason through steps toward that goal
5. Records every decision, action, and outcome for audit and learning

### Agent Types

| Type | Description |
|---|---|
| Task agent | Executes a single defined workflow start to finish |
| Monitor agent | Watches for conditions and triggers other agents or alerts |
| Conversation agent | Responds to user or customer messages in natural language |
| Research agent | Gathers, synthesises, and reports information on request |
| Decision agent | Evaluates options and recommends or executes a decision |
| Integration agent | Syncs data between Dot platforms or external systems |

---

## Agent Builder

The visual canvas where agents are created and configured.

### Building blocks

```
Triggers                 Actions                  Conditions
──────────               ─────────                ──────────
Schedule                 Read/write Dot.Files      If/Else branch
Webhook                  Create task (Dot.Tasks)   Wait for approval
Form submission          Send notification         Retry on failure
New file uploaded        Send email                Loop over list
Platform event           Call external API         Human-in-the-loop
Manual trigger           Update CRM record         Parallel execution
```

### Code mode
Agents can be defined in PHP or JavaScript for full programmatic control when the visual builder is insufficient.

### LLM configuration
- Model selection per agent: GPT-4o, Claude, Gemini, or self-hosted LLM
- System prompt editor with variable interpolation
- Context window management for large document inputs
- Temperature and confidence threshold controls

---

## Agent Library (Marketplace)

A curated set of ready-to-use agents published by BluPin, partners, and the community.

### Example library agents

| Agent | Function |
|---|---|
| Invoice Processor | Reads invoice PDFs from Dot.Files, extracts line items, posts to Dot.Finance |
| Contract Monitor | Watches Dot.Files for contracts expiring within 30 days, creates tasks in Dot.Tasks |
| Support Triage | Classifies incoming support messages, routes to correct team, drafts reply |
| Lead Scorer | Reads new CRM leads from Dot.Engage, scores them, updates the record |
| Report Generator | Pulls data from Dot.Analytics, writes a formatted summary to Dot.docs |
| Meeting Summariser | Processes meeting transcript, extracts actions, creates tasks in Dot.Projects |
| Social Scheduler | Publishes posts to Dot.Press on schedule based on content calendar |

Agents are published with a name, description, required permissions, variables, and a one-click install option.

---

## Execution Engine

### Queue-based processing
Every agent execution is a queued job. High-priority agents use a dedicated queue. Long-running agents run in isolated workers.

### Execution states
```
Pending → Running → Waiting (human approval) → Completed
                 ↘ Failed → Retrying → Failed (final)
```

### Decision Log
Every step of every execution is logged:
- which tool was called
- what the LLM received and returned
- what decision was made
- what action was taken
- how long it took
- whether it succeeded

The decision log is immutable and exportable for audit.

---

## Human-in-the-Loop

Some agents require human approval before an action is irreversible.

- Approval requests appear in the user's InfoDot notification centre
- Agents pause at the approval step and resume when approved or rejected
- Timeout policies: auto-approve, auto-reject, or escalate after N minutes
- Full context is shown to the approver: what the agent wants to do and why

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Files | Read and write files, trigger on upload events |
| Dot.Finance | Post transactions, read invoices, flag anomalies |
| Dot.Projects | Create tasks, update statuses, log progress |
| Dot.Engage | Read leads, update contacts, send communications |
| Dot.Analytics | Every agent execution feeds usage and value metrics |
| InfoDot | Decision logs and agent activity in user notification feed |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 3 active agents, 100 runs/month | Included with InfoDot |
| Starter | 10 agents, 1,000 runs/month | Per-seat monthly |
| Business | Unlimited agents, 20,000 runs/month, priority queue | Per-seat monthly |
| Enterprise | Dedicated runners, custom LLM, audit export, SLA | Annual contract |
