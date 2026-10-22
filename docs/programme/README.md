# Dot enterprise programme

Programme brief: enterprise transformation, security hardening, scalability and investor readiness for the
whole Dot ecosystem. This folder is the single place for its documents. Existing material is linked, not
copied: [platform-completion-register.md](../platform-completion-register.md) (per-platform audit of the 13
platforms completed 2026-10-18), [platform-go-live-checklist.md](../platform-go-live-checklist.md) and
[platform-merge-and-deploy-runbook.md](../platform-merge-and-deploy-runbook.md).

Rule for every file here: a statement is either **verified** (with the command or run that proves it and the
date), **estimated**, or **planned**. Nothing is scored or claimed without evidence.

| Deliverable | Status |
|---|---|
| [ECOSYSTEM_INVENTORY.md](ECOSYSTEM_INVENTORY.md) | written 2026-10-19 (verified probes, repo and CI state) |
| [SECURITY_ASSESSMENT.md](SECURITY_ASSESSMENT.md) | baseline written: dependency audit only; manual review not started |
| [PLATFORM_SCORECARDS.md](PLATFORM_SCORECARDS.md) | rubric and release-gate baseline; no 100-point scores yet (insufficient evidence) |
| [REMEDIATION_ROADMAP.md](REMEDIATION_ROADMAP.md) | written, prioritised from the baseline |
| [BACKUPS.md](BACKUPS.md) | written 2026-10-22 for R-3: what is backed up, encryption and key handling, retention, restore procedure, off-host hook, empty RPO/RTO table (no measured restore yet) |
| [EXECUTION_LOG.md](EXECUTION_LOG.md) | started |
| ECOSYSTEM_ARCHITECTURE, PLATFORM_AUDIT (beyond the 13), SCALABILITY_PLAN, TEST_STRATEGY, DESIGN_SYSTEM, INTEGRATION_CONTRACTS, DISASTER_RECOVERY, DEPLOYMENT_RUNBOOK, INVESTOR_TECHNICAL_DUE_DILIGENCE | **not started**; written as the roadmap reaches them. Existing partial sources: `docs/metrics-contract.md` and `config/notify_events.php` (contracts), each repo's `docs/deployment.md` (runbooks) |
