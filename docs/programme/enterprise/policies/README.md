# InfoDot operating policies and procedures (drafts for the owner)

Written 2026-10-30 from what the repository, the workflows and the host runbooks actually do. They are **working procedures, not certifications**: nothing here says InfoDot is ISO 27001 or SOC 2 compliant, and no independent party has reviewed them. Square-bracketed items marked **[OWNER]** are facts or decisions only the owner can supply; I did not invent them.

| File | What it is | Evidence it rests on |
|---|---|---|
| [INCIDENT_RESPONSE.md](INCIDENT_RESPONSE.md) | What to do when something is wrong, who decides, what must be told to whom and when | The tools that exist today (`ops:check`, `/ready`, `server-logs.yml`, the deploy rollback copy) |
| [RECOVERY_DRILL.md](RECOVERY_DRILL.md) | How to prove a code rollback and a database restore work, with a table to record the measured times | `docs/programme/BACKUPS.md`, `deploy.yml` |
| [VENDOR_REGISTER.md](VENDOR_REGISTER.md) | Every outside party that receives data or runs a service, what it receives, and what is still unknown | `POPIA_INVENTORY.md`, the code, the privacy draft Part B |
| [RISK_REGISTER.md](RISK_REGISTER.md) | The open risks, ranked, with owner and next step | `../FINDINGS.md` and the business risks around it |
| [CHANGE_AND_ACCESS.md](CHANGE_AND_ACCESS.md) | How a change reaches production and who can do what | `ci.yml`, `deploy.yml`, GitHub settings as read on 2026-10-30 |

The scoring rubric treats a procedure as evidence only once it has been **exercised** (a drill, a test). These files are the procedure; the drill records are the evidence, and none exists yet except where stated.
