# Dot.Central monetisation audit (read-only)

Evidence base: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Central (read-only extract in scratchpad `src/Dot.Central`; the local working tree is 44 commits behind origin/main, so origin was used). Paths are repo-relative. Only `.env.example` read. Dot.Mines (machine allocation) is out of scope but is Central's data source, so the boundary is noted in section 7.

Legend: EXISTING (verified), PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Mining control room: consumes Dot.Mines fleet data (equipment, status, metrics, production, alerts) per control room and turns it into shifts, line-ups, stoppage classification, rule-engine exceptions, approvals and reports. EXISTING (`wiki.md` section 1, `app/Services/Mines/*`, `Shifts/*`, `Lineup/*`, `Rules/*`).
- Shift line-up: faces, trucks per loading unit, operator per machine, standby reasons, confirmation. EXISTING (`LineupService`, tables `shift_lineups`, `lineup_faces`, `lineup_allocations`).
- Accountability: append-only audit log, dispatch decisions, alerts workflow, exception ledger; reports decisions and outcomes to Dot.Memory and shows Memory recall. EXISTING (`app/Services/Audits.php`, `Memory/*`, tables `audit_log`, `memory_outbox`).
- AI agent hub (second domain): team-scoped Claude agents with knowledge documents and conversations. EXISTING (`app/Services/AgentChatService.php`, tables `agents`, `agent_knowledge`, `conversations`, `messages`, `agent_usage_logs`).
- Per-room administration (shift types, downtime reasons, settings). EXISTING (`RoomProvisioner`, `control_room_settings`).

## 2. Most valuable features (measurable)
- Control rooms with a live Mines connection (rooms are the site boundary; `control_rooms`, `mines_connections`). Value is measurable as machines monitored, shifts confirmed, alerts handled. EXISTING.
- Stoppage (downtime) classification and availability numbers (`downtime_classifications`, `downtime_reasons.counts_against_availability`, `equipment_status_events`). EXISTING.
- Rule engine with exceptions and approvals (`RuleEngine`, `operational_exceptions`, `exception_events`) every 5 minutes (`routes/console.php`). EXISTING.
- Reports (six, per wiki). EXISTING.
- Agent hub: usage is directly measured in tokens (`agent_usage_logs`). EXISTING.

## 3. Existing limits, quotas, plans, entitlement, billing, fee code
- Plans, subscriptions, credits, wallet, fees in Central: none. Only references to "billing" in grep are third-party/boilerplate (`config/jetstream.php`, `AppServiceProvider`). EXISTING absence.
- Entitlement (boolean access gate): `app/Http/Middleware/EnsureCompanyEntitled.php` + `app/Services/InfoDot/EntitlementClient.php` + `app/Support/EntitlementRefusal.php`; cached read from InfoDot. EXISTING.
- Quantity limits: none for rooms, users, machines mirrored, shifts. Agent chat caps one request at `max_tokens` 1024 and input 4000 chars (`AgentChatService.php:70`, `app/Livewire/Agents/AgentChat.php:20`); knowledge docs capped at 50,000 characters (wiki section 3). These are safety caps, not allowances. EXISTING.
- Sync cadence: Mines sync every `mines.sync_interval_minutes` (default 5), engine hours every 15 minutes, rules every 5 (`routes/console.php`). Per-connection `provider_cadence_seconds` default 900 (`mines_connections`). EXISTING.
- No per-team throttle on agent chat found in routes (grep for throttle in routes/web.php found none relevant to agents). EXISTING absence; this is an unbounded paid-API cost.
- No mock-vs-live distinction for usage: with no `ANTHROPIC_API_KEY` the service echoes (dev mode). EXISTING.

## 4. Resources consumed or stored and where countable today
| Resource | Where |
|---|---|
| AI tokens in/out per user per agent per turn | `agent_usage_logs` (user_id, agent_id, tokens_input, tokens_output) and `messages.tokens_used`; join to `agents.team_id` for company |
| Mirrored machines | `equipment` (per control room) |
| Mirrored telemetry rows | `equipment_metrics`, `equipment_status_events`, `equipment_hour_readings`, `production_records` |
| Sync runs and failures | `integration_sync_runs`, `integration_webhook_deliveries`, `mines_connections` (last_success_at, consecutive_failures) |
| Shifts, line-ups, handovers | `shifts`, `shift_lineups`, `shift_handovers`, `shift_unit_summaries` |
| Alerts / exceptions | `alerts`, `alert_events`, `operational_exceptions`, `exception_events` |
| Control rooms, members | `control_rooms`, `control_room_user` |
| Audit events | `audit_log` (append-only) |
| Memory reports | `memory_outbox`, `memory_incidents` |
| Notifications | `notifications` |
Company key: `teams.infodot_team_uuid` (via `LinkTeamToInfoDot`, `SyncTeamsFromInfoDot`). EXISTING. Daily metrics publisher: not present in Central (unlike Auction/Emall/Ehail `PublishMetrics`); NEEDS DEVELOPMENT if Analytics coverage is wanted.

## 5. Third parties and cost drivers
- Anthropic Claude API (agent hub; default model `claude-sonnet-4-6`; full history replayed each turn, no truncation per wiki, so cost grows with conversation length). The single big variable cost. EXISTING.
- Dot.Mines REST API (per room token, calls per sync and one call per machine for engine hours): load/cost on Mines, not a vendor bill. EXISTING.
- Dot.Memory (internal), Dot.Notify/email, SQLite nightly backup (`BackupSqliteDatabase`). EXISTING. No payment provider, no SMS, no maps.

## 6. Candidate purchase units
- RESOURCE/CREDIT (PROPOSED): AI agent usage in tokens, packaged as "agent credits" (read straight from `agent_usage_logs`); this is the cleanest unit in the platform. Free monthly allowance of agent tokens per company.
- CAPACITY (PROPOSED): number of control rooms; number of machines mirrored per room (tied upstream to the Mines machine allocation, so one allocation could cover both); retention of telemetry history (months).
- FEATURE (PROPOSED): rule engine rules count, approvals, report packs and exports, Memory recall.
- Free tier (PROPOSED): one control room view of a small machine count, shift confirmation and handover, alert acknowledgement, audit trail (accountability records should never be paywalled), small agent allowance.
- When an allocation runs out (PROPOSED): read-only mirror and audit stay available; agent chat stops with a message and a top-up prompt; extra rooms/machines beyond allocation are not synced rather than deleted; never hide historic shift or audit records.

## 7. Cross-platform dependencies
- Dot.Mines (upstream data, per-room API token; machine allocation there is the reference model). Central should consume the Mines allocation rather than create a second machine meter. EXISTING dependency; PROPOSED rule.
- Dot.Memory (decision outcome outbox), InfoDot (identity, SSO, entitlement, company uuid), Dot.Guardian health (`app/Services/Guardian`). EXISTING.
- Shareable (PROPOSED): AI/agent token credits (same Anthropic cost shared with Emall assistant, Dot.Agents, Dot.Brain), notification credits, wallet. Platform-specific: control rooms, mirrored machine slots, telemetry retention.

## 8. Gaps
- Meter: agent usage is metered per turn but not per company/period and has no cap. Add a company-period aggregate and a pre-flight balance check in `AgentChatService::chat`. NEEDS DEVELOPMENT.
- Enforce: nothing counts rooms, machines or users against any allowance; gates would belong in room creation and `MinesSynchroniser`. NEEDS DEVELOPMENT.
- Audit: `audit_log` is append-only and reusable for credit events. NEEDS DEVELOPMENT for credit entries.
- Reporting: no `PublishMetrics` to Dot.Analytics. NEEDS DEVELOPMENT.
- Status caveat: wiki says no staging deployment exercised for the control room as of 2026-10-15 (I-32); live scale and cost are unmeasured.
