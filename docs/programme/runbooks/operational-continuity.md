# Runbook — operational continuity (R-24 §9)

InfoDot (and the Dot fleet) currently depends on one operator. This package lets another
authorised technical person maintain or recover it. **It names where every credential LIVES —
no secret values appear in this document or anywhere in the repository.**

## Critical systems and where their keys live

| System | What it controls | Access lives in |
|---|---|---|
| Domain (infodot.co.za) | everything — expires 2026-11-27 unless renewed | the hosting reseller account (registrar of record: Key-Systems GmbH via the reseller); credentials: owner's password manager |
| cPanel / shared host | the live apps, databases, mail, DNS zone, cron | reseller/cPanel login; SSH deploy keys live ONLY in each repo's GitHub Actions secrets (DEPLOY_SSH_KEY etc.) |
| GitHub (sakhilebhayi) | all source, CI/CD, Actions secrets | owner's GitHub account (2FA); a recovery admin needs org/repo access granted by the owner |
| Backup encryption | restoring ANY backup | passphrase file on the host at `~/.dot-backup/passphrase` AND a copy the owner holds offline; without it backups are unreadable — this is the single most important secret |
| Off-site backup pull | the Mac mirror of `~/dot-backups` | read-only SSH key `~/.ssh/dot_backup_pull` on the owner's Mac (tools/pull-dot-backups.sh) |
| Paystack | money | Paystack dashboard login (owner); secret keys only in Billing host `.env` |
| SARS eFiling, Information Regulator portal | tax + POPIA duties | owner credentials (password manager) |
| OIDC signing keys | ecosystem sign-in | `storage/oauth-*.key` on the InfoDot host (untracked; regenerate with `php artisan passport:keys` — regenerating invalidates issued tokens, which is acceptable recovery) |

## Recovery procedures (already written, tested where stated)

1. **App recovery**: every deployed repo's `deploy.yml` redeploys main from GitHub to the host
   (manual dispatch; rollback copy kept per deploy). A new host = run each repo's `bootstrap.yml`
   / adopt flow, restore `.env` from the owner's secure copy, restore DB from backups.
2. **Database recovery**: `runbooks/mysql-restore-drill.md` — the same procedure, pointed at the
   production DB name instead of the scratch one, IS the disaster recovery. Requires the backup
   passphrase.
3. **DNS/email**: zone lives on the host nameservers (ns1-4.mysecurecloudhost.com); the reseller
   panel rebuilds it; record values are documented in the R-18 register §B.
4. **Monitoring**: `ops:check` alerts daily (Sentry); server-logs/host-health workflows read the
   host from GitHub Actions without SSH keys on a laptop.

## Single points of failure and realistic mitigations

1. **The owner** — mitigation: this document + a named second person holding (a) GitHub access,
   (b) the reseller login's recovery route, (c) the backup passphrase in a sealed/escrowed form.
   Without all three, recovery stalls.
2. **The backup passphrase** — if it exists only on the host, a host loss destroys the backups'
   usability. ACTION (owner, 10 min): confirm an offline copy exists away from the host.
3. **One shared host** — all ~20 apps share one cPanel account. Mitigation is the tested restore
   path plus the off-site backup mirror; a second-host warm standby is not justified at current
   scale.
4. **Email deliverability** — mail rides the same host; registrar/DNS work (R-18) reduces
   spoofing, not outage. Acceptable at current scale.

## Escalation contacts

Hosting/provider: the reseller's support channel (owner holds the account). Registrar: via the
reseller. Paystack: dashboard support. SARS/Regulator: published lines (in the knowledge
library's entries). Everything else: the repos' programme docs are the operational memory.

## Recovery priority order

1. Domain alive → 2. Host serving InfoDot (identity + knowledge) → 3. Database current (restore
drill) → 4. Mail (reset/verification flows) → 5. Satellites in the order users need them.
