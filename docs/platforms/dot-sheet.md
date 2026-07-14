# Dot.Sheet — Intelligent Spreadsheet Platform

**Role:** The Structured Data and Calculation Layer of the Dot Ecosystem  
**URL:** `sheet.infodot.app`  
**Tagline:** Data that works with your whole organisation, not just your team.

---

## Vision

Dot.Sheet is not Google Sheets with a Dot logo. It is the **live data workspace** where numerical analysis, operational planning, and business modelling connect directly to every other Dot platform. A budget sheet in Dot.Sheet links to live transactions in Dot.Finance. A production planning sheet pulls fleet data from Dot.Fleet. A sales pipeline sheet updates when Dot.Engage records change.

Spreadsheets traditionally exist in isolation — data is manually exported, pasted, and becomes stale. Dot.Sheet ends the export cycle by making every sheet a live window into the Dot ecosystem.

---

## Architecture

```
Dot Ecosystem

├── Dot.Sheet
│   ├── Grid Engine (calculation + rendering)
│   ├── Formula Engine (Excel-compatible + ecosystem formulas)
│   ├── Live Data Connectors
│   ├── Charts and Visualisations
│   ├── Collaboration Layer
│   └── Automation Rules
│
└── Shared: PostgreSQL · Redis · Reverb (real-time sync) · S3
```

---

## Grid Engine

The core spreadsheet experience is fast, keyboard-navigable, and familiar.

### Cell capabilities
- All standard cell types: text, number, currency, date, time, percentage, boolean
- Cell formatting: bold, italic, colour, borders, alignment, number format
- Merge cells, freeze rows and columns
- Data validation: dropdown lists, number ranges, date constraints, custom formulas
- Conditional formatting with colour scales, icon sets, and custom rules
- Cell comments and threaded discussions
- Named ranges and defined names

### Sheet structure
- Multiple sheets per workbook with tab navigation
- Sheet-level permissions: hide, protect, and lock individual sheets
- Cross-sheet formula references
- Workbook sharing with per-sheet access control

---

## Formula Engine

Compatible with the Excel/Google Sheets formula syntax with additional Dot ecosystem functions.

### Standard function categories
```
Math & Stats       Text               Date & Time        Logic
────────────       ────               ───────────        ─────
SUM, AVERAGE       CONCATENATE        TODAY, NOW         IF, AND, OR
COUNT, COUNTA      LEFT, RIGHT, MID   DATEDIF            IFERROR
MIN, MAX           TRIM, UPPER        WORKDAY            SWITCH
VLOOKUP, XLOOKUP   SUBSTITUTE         NETWORKDAYS        IFS
INDEX, MATCH       TEXT               EDATE              NOT
SUMIF, COUNTIF     SPLIT              YEAR, MONTH, DAY
SUMPRODUCT         REGEXMATCH
```

### Dot ecosystem functions

| Function | Returns |
|---|---|
| `DOT.FINANCE(account, period)` | Live balance or transaction total from Dot.Finance |
| `DOT.TASKS(team, status)` | Count of tasks matching filters from Dot.Tasks |
| `DOT.ANALYTICS(metric, date_range)` | Named metric value from Dot.Analytics |
| `DOT.AGENTS(agent_id, stat)` | Agent execution stats from Dot.Agents |
| `DOT.FORMS(form_id, field, filter)` | Aggregated form response value from Dot.Forms |

These functions refresh on a configurable schedule (every 5 min, hourly, daily) or on demand.

---

## Live Data Connectors

Sheets can subscribe to live data streams from across the ecosystem.

| Source | Data available |
|---|---|
| Dot.Finance | Account balances, transaction totals, budget vs actual |
| Dot.Agents | Agent run counts, success rates, processing time |
| Dot.Analytics | KPIs, dashboard metrics, trend data |
| Dot.Forms | Response aggregates, completion rates |
| Dot.Tasks | Task counts by status, assignee, team |
| External API | JSON/CSV endpoint with configurable polling |
| PostgreSQL direct | Named query results refreshed on schedule |

Connector data appears in a dedicated range. Formulas reference that range as normal cells.

---

## Charts and Visualisations

Built-in chart builder producing publication-ready visuals.

### Chart types
```
Bar (vertical/horizontal)    Line                  Area
Stacked bar                  Scatter               Bubble
Pie and donut               Combo (bar + line)     Gauge
Waterfall                   Gantt (timeline)       Heatmap
Treemap                     Funnel                 Candlestick
```

Charts update automatically when the underlying data changes and can be embedded in Dot.docs documents or published to Dot.Press.

---

## Collaboration

- Real-time multi-user editing via Laravel Reverb — every keystroke reflected for all editors
- Presence avatars on cells showing which user is editing where
- Row-level locking: mark a row as in-use to prevent conflicts during data entry
- Commenting on cells with mention notifications
- Suggestion mode: propose cell changes for review
- Full change history at cell level

---

## Automation Rules

Spreadsheet-level triggers that react to data changes.

| Trigger | Example action |
|---|---|
| Cell value reaches threshold | Notify team if budget overrun exceeds 10% |
| Row added | Create a task in Dot.Tasks for the new record |
| Value changes in range | Start a Dot.Agents workflow with the updated row |
| Formula result crosses threshold | Send alert notification via InfoDot |
| Scheduled refresh complete | Email a snapshot of the sheet to a distribution list |

---

## Import and Export

- Import: XLSX, CSV, TSV, Google Sheets (via Sheets API), JSON
- Export: XLSX, CSV, PDF (rendered), JSON
- Sync mode: two-way sync with a Google Sheet for migration or hybrid use
- API: read and write cell ranges via REST API (used by Dot.Agents)

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Finance | Live account and budget data in sheets via DOT.FINANCE() |
| Dot.docs | Charts embedded as live blocks in documents |
| Dot.Analytics | Sheet data pushed to Analytics as a custom data source |
| Dot.Agents | Agents read and write sheet ranges as part of workflows |
| Dot.Files | Workbooks stored and versioned in Dot.Files / S3 |
| InfoDot | Shared search, team-based access via InfoDot team model |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 5 workbooks, 10,000 cells, basic charts | Included with InfoDot |
| Team | Unlimited workbooks, collaboration, live connectors | Per-seat monthly |
| Business | Ecosystem functions, automation rules, API access | Per-seat monthly |
| Enterprise | Direct DB connectors, custom functions, audit log | Annual contract |
