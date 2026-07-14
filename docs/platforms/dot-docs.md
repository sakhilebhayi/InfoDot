# Dot.docs — Collaborative Document Platform

**Role:** The Document Creation and Knowledge Layer of the Dot Ecosystem  
**URL:** `docs.infodot.app`  
**Tagline:** Write together. Think together. Know together.

---

## Vision

Dot.docs is not a word processor with sharing. It is the **collaborative knowledge platform** where teams create, refine, and operationalise documents natively connected to every other Dot platform.

A proposal created in Dot.docs links directly to the opportunity in Dot.Engage. A project brief connects to tasks in Dot.Projects. An SOP auto-syncs to the knowledge base in Dot.Agents. Meeting notes become Dot.Tasks action items automatically. Documents are not passive artefacts — they are living connectors between decisions and outcomes.

Competitors like Google Docs, Notion, Confluence, and Coda treat documents as the end product. Dot.docs treats documents as inputs to action across the entire Dot ecosystem.

---

## Architecture

```
Dot Ecosystem

├── Dot.docs
│   ├── Document Editor (rich text + blocks)
│   ├── Workspaces and Spaces
│   ├── Templates Library
│   ├── AI Writing Assistant
│   ├── Comments and Review Flows
│   └── Ecosystem Action Blocks
│
└── Shared: PostgreSQL · Redis · Reverb (real-time sync) · S3 · Meilisearch
```

---

## Document Editor

The editor is block-based, combining the flexibility of Notion with the speed of Google Docs.

### Block types

```
Text blocks          Media blocks         Embed blocks
─────────────        ────────────         ────────────
Heading (H1–H3)      Image                Dot.Sheet table
Paragraph            Video                Dot.Finance summary
Bulleted list        Audio                Dot.Analytics chart
Numbered list        File attachment      Dot.Projects milestone
To-do list           Code block           Dot.Tasks action item
Quote blockquote     Table                External embed (URL)
Callout / Note       Divider              Formula block
Toggle / collapse    Database view
```

### Real-time collaboration
- Simultaneous multi-user editing via Laravel Reverb
- Presence indicators showing active editors and cursor positions
- Conflict-free concurrent editing using CRDT or operational transforms
- Inline comments and resolved threads
- Suggestion mode: propose edits without overwriting the original

### Version history
- Auto-saved every 30 seconds and on every significant change
- Named checkpoints with author and timestamp
- Full restore or selective paste from any version
- Diff view between any two versions

---

## Workspaces and Spaces

Documents are organised in a hierarchical structure mirroring how teams think:

```
Organisation
  └── Spaces (e.g. Engineering · Marketing · Finance · Legal)
        └── Sections (e.g. Proposals · SOPs · Meeting Notes)
              └── Documents
```

- Each space maps to a team in InfoDot
- Permissions inherit down the hierarchy with per-document overrides
- Public spaces allow read access without authentication
- Private spaces are visible only to explicit members

---

## AI Writing Assistant

An inline assistant available throughout the editor via `/ai` command or selection.

### AI capabilities

| Command | What it does |
|---|---|
| Improve writing | Rewrites selected text for clarity and tone |
| Summarise | Condenses a long section into key points |
| Expand | Turns bullet points into full paragraphs |
| Translate | Translates selected text to a target language |
| Action items | Extracts to-do items from meeting notes |
| First draft | Generates an initial draft from a heading or prompt |
| Tone adjustment | Rewrite as formal, friendly, concise, or assertive |
| Table from text | Converts structured prose to a formatted table |

---

## Templates Library

A curated set of ready-to-use document templates.

### Core templates

```
Business          Project            HR                 Legal
─────────         ────────           ──                 ─────
Business plan     Project brief      Job description    NDA template
Pitch deck        Sprint notes       Offer letter       Service agreement
Meeting notes     Retrospective      Performance review Privacy policy
OKR tracker       Tech spec          Onboarding guide   Terms of service
Quarterly review  Product roadmap    Leave policy       Partnership MOU
```

Templates are contributed by the community and published via Dot.Pulse.

---

## Review and Approval Flows

Documents requiring sign-off before action follow a structured flow:

1. Author marks document as "Ready for review"
2. Reviewers are notified via InfoDot notifications
3. Reviewers leave inline comments or suggest edits
4. Author resolves comments and requests approval
5. Approvers sign off — recorded with name, date, and IP
6. Document is locked as approved version
7. Approved document can trigger a Dot.Agents workflow

---

## Ecosystem Action Blocks

Dot.docs is the only document platform where actions flow directly to other platforms.

| Action block | Effect |
|---|---|
| `@task` | Creates a task in Dot.Tasks linked to this document |
| `@project-milestone` | Attaches to a milestone in Dot.Projects |
| `@file` | Embeds or links a file from Dot.Files |
| `@agent` | Triggers a Dot.Agents workflow with document content as input |
| `@analytics` | Embeds a live chart from Dot.Analytics |
| `@finance-summary` | Pulls a live financial summary from Dot.Finance |

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Files | Documents backed up and stored in S3 via Dot.Files |
| Dot.Projects | Project briefs and specs linked to project records |
| Dot.Tasks | Action items extracted from meeting notes become tasks |
| Dot.Agents | Approved documents trigger agent workflows |
| Dot.Analytics | Document activity (views, edits, comments) feeds analytics |
| InfoDot | Search across all documents from InfoDot's global search |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 3 spaces, 50 documents, basic AI | Included with InfoDot |
| Team | Unlimited spaces, version history, templates | Per-seat monthly |
| Business | AI assistant, approval flows, ecosystem actions | Per-seat monthly |
| Enterprise | SSO, custom templates, audit log, advanced permissions | Annual contract |
