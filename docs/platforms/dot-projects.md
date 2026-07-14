# Dot.Projects — Project Management Platform

**Role:** The Delivery and Coordination Layer of the Dot Ecosystem  
**URL:** `projects.infodot.app`  
**Tagline:** Plan, build, and ship — with your whole organisation behind you.

---

## Vision

Dot.Projects is not another Jira clone. It is the **project delivery platform** where plans connect to real organisational resources — people, files, finance, and automation — rather than living in a disconnected ticket system.

A project in Dot.Projects knows its budget from Dot.Finance, its documents from Dot.docs, its file assets from Dot.Files, its team from InfoDot, and its automated workflows from Dot.Agents. When a milestone slips, Dot.Analytics surfaces the risk before the manager notices. When a sprint completes, Dot.Engage can automatically notify stakeholders.

Competitors like Jira, Asana, Monday.com, and Linear track work. Dot.Projects connects work to outcomes.

---

## Architecture

```
Dot Ecosystem

├── Dot.Projects
│   ├── Projects and Workspaces
│   ├── Task Boards (Kanban, List, Timeline)
│   ├── Milestone and Roadmap View
│   ├── Resource Management
│   ├── Budget Tracking
│   └── Reporting and Health Dashboard
│
└── Shared: PostgreSQL · Redis · Reverb (real-time) · Meilisearch
```

---

## Projects and Workspaces

The top-level structure organises work across teams and business units.

```
Organisation
  └── Workspace (e.g. Product · Engineering · Marketing · Client Projects)
        └── Project (e.g. InfoDot Upgrade · Q3 Campaign · Client ABC)
              └── Sections / Sprints / Phases
                    └── Tasks
```

### Project record
- Name, description, status, priority
- Start and end date with configurable phases
- Project owner and team members (from InfoDot teams)
- Budget allocation linked to Dot.Finance
- Health indicator: On Track / At Risk / Off Track (AI-assessed)
- Tags and custom fields

---

## Task Views

Every project's tasks are viewable in multiple formats.

### Kanban board
- Columns represent workflow stages: Backlog, In Progress, Review, Done (customisable)
- Cards show assignee, due date, priority, and tags
- Drag-and-drop between columns with automatic status updates
- Swimlanes by assignee, priority, or sprint
- WIP limits per column

### List view
- Flat or grouped task list with inline editing
- Sort by any field: due date, priority, assignee, status
- Bulk edit: reassign, reprioritise, move to sprint
- Filters: my tasks, overdue, unassigned, by tag

### Timeline (Gantt) view
- Visual timeline of tasks and milestones across weeks and months
- Dependency lines between tasks
- Drag to reschedule; dependency chain updates automatically
- Critical path highlighting
- Milestone markers with percentage complete

### Calendar view
- Tasks plotted by due date on a monthly/weekly calendar
- Colour-coded by project, assignee, or priority
- Drag-and-drop rescheduling

---

## Tasks

### Task record
- Title, description (rich text from Dot.docs)
- Status, priority, type (task, bug, feature, spike, chore)
- Assignee(s), due date, estimated hours
- Parent task / subtask hierarchy
- Dependencies: blocked by / blocking
- Files attached from Dot.Files
- Comments and activity log
- Time tracking: manual or timer-based
- Labels and custom fields

### Task types
```
Task     Bug     Feature     Epic     Story     Spike
Chore    Review  Milestone   Risk     Decision
```

---

## Sprints and Milestones

### Sprints
- Configurable sprint length (1, 2, or 3 weeks)
- Sprint planning: drag tasks from backlog into sprint
- Sprint goal and capacity planning (hours available vs committed)
- Sprint burndown chart (actual vs ideal)
- Sprint retrospective linked to a Dot.docs template

### Milestones
- Named checkpoints representing meaningful delivery moments
- Milestone dependencies: milestone B cannot be reached before milestone A
- Milestone health: percentage of dependent tasks completed
- Milestone slippage alerts fed to project owner and Dot.Analytics

---

## Resource Management

- Team member capacity: available hours per sprint or week
- Current allocation: hours committed across all projects
- Overallocation warnings: alert when a team member is over 100% allocated
- Skills tagging: assign tasks to people with matching skills
- Workload view: per-person task load across the week

---

## Budget Tracking

- Budget set per project with connection to Dot.Finance account
- Actual spend pulled live from Dot.Finance transactions tagged to the project
- Budget variance: planned vs actual with trend
- Forecast: projected final spend based on burn rate
- Budget alert when spend reaches configurable threshold (e.g. 80%)

---

## Reporting and Health Dashboard

### Project health report
- Velocity: tasks completed per sprint vs planned
- Scope creep: tasks added after sprint start
- Defect rate: bugs opened vs bugs closed
- Milestone completion rate
- Budget burn rate
- Team contribution distribution

### Portfolio view (cross-project)
- All active projects on one screen with health indicators
- Filter by team, business unit, risk level, or timeline
- Export to PDF for stakeholder reporting

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Tasks | Tasks created in Dot.Tasks sync as project tasks |
| Dot.docs | Project briefs, specs, and meeting notes linked to projects |
| Dot.Files | Project file assets attached to tasks and milestones |
| Dot.Finance | Budget tracked against real transactions |
| Dot.Agents | Automated task creation, status updates, and notifications |
| Dot.Analytics | Project velocity, milestone health, and resource data feed EIP |
| Dot.Engage | Project milestones trigger stakeholder notifications |
| InfoDot | Team membership, permissions, and notifications via InfoDot |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 3 projects, 5 members, Kanban + list views | Included with InfoDot |
| Team | Unlimited projects, timeline, sprints, time tracking | Per-seat monthly |
| Business | Resource management, budget tracking, portfolio view | Per-seat monthly |
| Enterprise | Custom workflows, advanced reporting, SSO, SLA | Annual contract |
