# Dot.Tasks — Personal and Team Task Management Platform

**Role:** The Daily Work and Productivity Layer of the Dot Ecosystem  
**URL:** `tasks.infodot.app`  
**Tagline:** Everything you need to do, in one place.

---

## Vision

Dot.Tasks is not a to-do list. It is the **operational task layer** that bridges personal productivity with team workflows — capturing every action item generated anywhere in the Dot ecosystem and surfacing it to the right person at the right time.

When Dot.Agents completes an analysis and identifies a decision needed, it creates a task. When a form submission requires follow-up, it becomes a task. When a meeting note in Dot.docs contains an action item, AI extracts it into a task. Tasks flow into Dot.Tasks from every platform; every task flows back as context to every platform.

The difference between Dot.Tasks and tools like Todoist, TickTick, and Microsoft To Do is ecosystem depth. Dot.Tasks is not the end of the workflow — it is the operational surface where ecosystem work gets done.

---

## Architecture

```
Dot Ecosystem

├── Dot.Tasks
│   ├── My Tasks (personal inbox)
│   ├── Team Queues
│   ├── Smart Lists (AI-organised views)
│   ├── Focus Mode
│   └── Automations
│
└── Shared: PostgreSQL · Redis · Reverb (real-time) · Meilisearch
```

---

## Task Model

### Task record
- Title and rich text description
- Status: To Do, In Progress, Waiting, Done, Cancelled
- Priority: Urgent, High, Normal, Low
- Due date and optional start date
- Assignee (single or shared)
- Tags and categories
- Project link (syncs to Dot.Projects if associated)
- Parent task and subtask hierarchy
- Files attached from Dot.Files
- Source context: which platform created this task and why
- Recurrence: daily, weekly, monthly, or custom

### Task sources
Every task carries a source label so the user understands where it came from:

| Source | Example |
|---|---|
| Manual | User created directly in Dot.Tasks |
| Dot.docs | Action item extracted from meeting notes |
| Dot.Forms | Follow-up required from form submission |
| Dot.Agents | Agent created task as part of workflow |
| Dot.Projects | Task delegated from a project |
| Dot.Engage | Follow-up created from CRM deal activity |
| Dot.Finance | Invoice action required |

---

## My Tasks — Personal Inbox

The personal view shows tasks assigned to the logged-in user across all sources.

### Smart sections (AI-organised)
```
Today           → Due today or flagged by user
Upcoming        → Due in next 7 days
Someday         → No due date
Waiting         → Blocked on someone else
Done            → Completed this week
```

Users can also create custom sections with their own filter logic.

---

## Team Queues

Shared task lists for teams and business functions.

- Configurable queues per team (matching InfoDot teams)
- Unassigned queue: tasks waiting to be picked up
- Assignment rules: auto-assign based on workload, skill tag, or round-robin
- Queue health: average wait time, oldest unresolved task, assignment gaps
- Queue visibility: show or hide from specific team members

---

## Smart Lists

AI-generated task views that require no manual organisation.

| Smart list | Logic |
|---|---|
| Do today | AI selects highest-priority tasks that can be completed today |
| Overdue | All tasks past due date, sorted by urgency |
| Needs attention | Tasks with no activity in 3+ days |
| Delegated by me | Tasks assigned to others but created by you |
| High stakes | Tasks linked to deals, projects, or agents flagged as high priority |
| Quick wins | Tasks estimated under 15 minutes with high priority |

---

## Focus Mode

A distraction-free single-task view for deep work sessions.

- One task displayed at a time with full context
- Built-in Pomodoro timer (25 min work / 5 min break)
- Notes panel for working notes that attach to the task
- Complete and auto-advance to next task in sequence
- Focus session summary: tasks completed, time spent

---

## Task Automations

Rules that keep tasks organised without manual effort.

| Trigger | Action |
|---|---|
| Task overdue by 1 day | Bump priority to Urgent |
| Task completed | Notify task creator if different from assignee |
| No activity for 3 days | Send a reminder notification |
| Task added to queue | Auto-assign based on workload rule |
| Due date within 24 hours | Send push notification to assignee |
| Task from Dot.Forms | Auto-tag with form name and source |

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.docs | AI extracts action items from meeting notes as tasks |
| Dot.Projects | Project tasks sync bidirectionally with Dot.Tasks |
| Dot.Forms | Form submission follow-ups created as tasks |
| Dot.Agents | Agents create, update, and complete tasks as part of workflows |
| Dot.Engage | CRM follow-ups appear as tasks in personal inbox |
| Dot.Analytics | Task completion rates, overdue trends, and team throughput feed EIP |
| InfoDot | Notifications for assigned tasks; team membership drives queue access |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | Personal tasks, basic smart lists | Included with InfoDot |
| Team | Team queues, assignment rules, ecosystem task sources | Per-seat monthly |
| Business | AI smart lists, focus mode, automations, analytics | Per-seat monthly |
| Enterprise | Custom automations, SLA tracking, audit log, SSO | Annual contract |
