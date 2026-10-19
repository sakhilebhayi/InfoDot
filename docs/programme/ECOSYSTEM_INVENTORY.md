# Ecosystem inventory (baseline 2026-10-19)

Method: `gh repo list`, `gh run list`, `gh pr list` per repository; `curl` of `/`, `/login`, `/up` per host from
one machine; `composer audit --locked` and `npm audit --package-lock-only --omit=dev` on each default-branch
lockfile. Not inspected: the production hosts' filesystem, databases, cron tables and logs (no direct access from
this programme; only the repositories' own `server-logs` / `server-inspect` workflows can read them).

## Repositories (account `sakhilebhayi`)

27 Dot repositories. Not Dot platforms: `InfoDot` (portal + sign-in, itself a platform), `BluPin*`, `blupincapital`,
`DT`, `bhayiede`, `sakhilebhayi`.

| Repo | Default branch | Host | `/` `/login` `/up` | Pipelines present | Open PRs |
|---|---|---|---|---|---|
| InfoDot | main | infodot.co.za | 200 200 200 | deploy | 0 |
| Dot.Agents | main | agents | 200 200 200 | deploy, watchdog, security, test | 1 |
| Dot.Analytics | feature/ecosystem-sso | analytics | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Auction | main | auction | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Billing | main | billing | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Brain | main | none (library/registry, Guardian cron) | n/a | tests, guardian-cron, collector, token-check, waf-probe | 1 |
| Dot.Central | main | central | 200 200 200 | ci, deploy, watchdog | 0 |
| Dot.Charts | main | charts | 200, `/login` 404, 200 | deploy, watchdog, maintenance | 2 |
| Dot.Design | main | design | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Dopemine | main | dopemine | 200 200 200 | deploy, watchdog | 1 |
| Dot.Ehail | main | ehail | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Emall | main | emall | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Engage | main | engage | 200 200 200 | ci, deploy, drift, watchdog | 2 |
| Dot.Farms | main | farms | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Files | main | files | 200 200 200 | deploy, adopt, dependency-review | 18 |
| Dot.Finance | main | finance | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Forms | main | forms | 200 200 200 | ci, deploy, drift, watchdog | 2 |
| Dot.HR | main | hr | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Memory | main | memory | 200 200 200 | deploy, watchdog | 1 |
| Dot.Mines | main | mines | 200 200 200 | ci-security, deploy, watchdog | 6 |
| Dot.Notify | main | notify | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Plug | main | plug | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Press | main | pres | 200 200 200 (one probe timed out, re-probe 200) | ci, deploy, drift, watchdog | 2 |
| Dot.Projects | main | projects | 200 200 200 | ci, deploy, drift, watchdog | 2 |
| Dot.Pulse | main | pulse | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Sheet | main | sheets | 200 200 200 | ci, deploy, drift, watchdog | 2 |
| Dot.Tasks | main | tasks | 200 200 200 | ci, deploy, drift | 0 |
| Dot.Tutor | main | tutor | 200 200 200 | ci, deploy, drift | 0 |
| Dot.docs | main | doc | 200 200 200 | ci, deploy, watchdog | 0 |

Occasional single-probe timeouts (000) occurred on Ehail, Sheet and Press and cleared on re-probe (see
SECURITY/RELIABILITY finding R-1).

## Brief names with no repository

`Dot.Email` does not exist and was dropped by the owner on 2026-10-18 (mix-up with Dot.Ehail; email delivery is
Dot.Notify's job). The brief also names Dot.Docs (= repo `Dot.docs`).

## Shared host

All platforms run on one cPanel account (LiteSpeed, PHP, SQLite or MySQL per app; the 13 platforms deployed on
2026-10-18 use SQLite). Deploys are manual GitHub Actions over SSH; one crontab is shared by every app. Realtime
(Reverb) cannot run there. **The databases of the older apps (Mines, Files, Memory, ...) were not inspected in
this baseline: engine, size and backup state are unverified.**

## Dependency map (verified in code and config)

- InfoDot signs users into every platform: signed redeem flow, one `ECOSYSTEM_SECRET_<KEY>` per platform.
- Notify ingest (`POST /api/ingest/events`, HMAC): Auction, Billing, Ehail, Farms, HR, Tasks.
- Analytics ingest (`POST /api/ingest/snapshots`, HMAC): Auction, Billing, Design, Ehail, Emall, Farms, HR, Notify,
  Pulse, Tasks, Tutor. **Not yet switched on:** the apps still lack `ANALYTICS_INGEST_URL` (owner step pending).
- Billing entitlements (`GET /api/entitlements/{company}`, HMAC): secrets generated, callers not wired.
- Paystack (test mode): Billing, Emall, Auction, Ehail; keys and webhooks not yet configured on the host.
- Brain / Memory / Dopemine / Guardian monitoring: see `Dot.Brain` workflows and `docs/` of Dot.Memory; not
  re-audited in this baseline.
