# Change management and access control (draft, as it works today)

## How a change reaches production

1. **Branch and pull request.** Every change goes through a pull request from a branch. (Nothing enforces this: `main` has no branch protection, so a direct push is technically possible. FINDINGS P-01.)
2. **Automatic checks** (`.github/workflows/ci.yml`, run on every pull request and again as the release gate): high or critical advisories (Composer), `npm audit` at high, PHPStan level 5 against the baseline with a ceiling that can only fall, Pint, the OpenAPI file parses, the full test suite (1,241 tests), migrations on MySQL, the production frontend build, coverage measured (94.0% of lines), GitGuardian secret scanning, and the host-tool tests (backup, cron runner, docroot guard and others).
3. **Review.** The owner reads the summary and approves the merge in the conversation. A second reviewer does not exist. Perch Scan runs on each pull request and has failed on every pull request in this programme with unexplained labels; it is not treated as a gate, and where its pointers could be checked they have been; that found several real defects in the audit's own changes (for example a canonical-URL fix that would have broken login on `www.infodot.co.za`, and an advisory gate that failed open).
4. **Merge** (squash) by the owner's instruction only.
5. **Deploy** is manual: the `Deploy to Production` workflow, with an explicit `confirm=deploy`, which re-runs the CI gate, builds the frontend, takes a rollback copy of the app folder on the host, puts the site in maintenance mode, updates the code, runs migrations, rebuilds caches and smoke-checks `/`, `/login`, the built CSS and `/up`. A failure after maintenance mode starts **leaves the site down on purpose** and prints the rollback copy's name. **Only the owner authorises a deploy**, each time.
6. **After the deploy** the owner or the responder checks `/ready`, the response headers and the page that changed (done for every release in this programme; the evidence is in the pull request threads).

## What a change must carry

* A test that fails without the change (for a defect) or that pins the behaviour (for a feature). The programme's fixes each have one; ten of twelve in the 2026-10-30 batch were checked to fail on the old code.
* An update to `docs/programme/enterprise/FINDINGS.md` when it closes or opens a finding.
* A note when it needs a host action (new environment variable, new cron line, migration with locking risk).

## Emergency changes

The same path, faster: a pull request, the checks, the owner's approval. If the site is down and the cause is a release, restore the rollback copy first (`RECOVERY_DRILL.md`, Drill A), then fix forward in a pull request.

## Who can do what

| Capability | Who has it today | Notes |
|---|---|---|
| Merge to `main` | The repository owner, and any session acting with the owner's `gh` login | No branch protection and no second approver |
| Run the deploy workflow | Same | Uses repository secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` |
| Host shell, `.env`, database | The owner (SSH key); the deploy key can run the pipeline's fixed commands | The database password and `APP_KEY` were rotated 2026-10-27 |
| Read the backup passphrase | Whoever has the host account | A password-manager copy is an outstanding owner item |
| Moderator powers in the product | Accounts with `users.is_moderator`, set by an administrator | Seminar moderation now refuses a moderator deciding on their own seminar |
| Company roles | Owner, admin, manager, member (Jetstream teams) | A member cannot invite or cancel invitations (fixed 2026-10-30) |

## Access review

None has been done. A first review is: list the repository collaborators and deploy keys, list `is_moderator` users, list who can SSH to the host, and confirm each is still needed. Record the date here: **[not yet run]**.

## Changes this document recommends (not made)

1. Turn on branch protection for `main`: require the CI checks, block force pushes and deletion. Because the owner is the only committer, do not require a human review until there is a second person. This changes how merges work, so it is the owner's decision.
2. Turn on Dependabot security updates.
3. Name a deputy who can reach the host and the backup passphrase.
