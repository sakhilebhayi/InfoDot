# Recovery drills: code rollback and database restore (procedure; nothing recorded yet)

Why this exists: the readiness model counts a rollback and a restore as evidence **only once they have been performed and timed**. `docs/programme/BACKUPS.md` describes the tooling and records two restore tests, both of **Dot.Memory (a SQLite database)**. InfoDot's own data is in **MySQL**; its dump has only ever been checked by reading the dump footer, never imported. Until Drill B is run, InfoDot has an unproven recovery path, and the readiness model treats that as a release blocker.

Both drills are non-destructive: they restore into a scratch location and never touch the live app or database. Run them from the owner's account on the host (`/home/infodotc`). Record the results in the tables at the end and commit this file.

## Drill A: code rollback (about 10 minutes)

What a deploy keeps: before it changes anything, `deploy.yml` writes `~/dot-infodot-<mode>-<UTC stamp>.tgz` (the app folder without `vendor`, `node_modules`, logs and built assets), mode 600, newest three kept. It does **not** contain the database. A rollback of code therefore does not undo migrations; the recent ones are additive (new tables and columns, indexes).

1. List the copies and note the newest: `ls -l ~/dot-infodot-*.tgz`
2. Prove it is readable and complete, in a scratch folder (not the live one):

```bash
mkdir ~/rollback-check && cd ~/rollback-check
time tar xzf "$(ls -1t ~/dot-infodot-*.tgz | head -1)"
ls artisan composer.json .env >/dev/null && echo "has artisan, composer.json and .env"
cd ~ && rm -rf ~/rollback-check
```

3. Write down the seconds `time` printed. The real rollback is: `php artisan down`, extract the tarball over `/home/infodotc/public_html/main`, `composer install --no-dev` if `composer.lock` changed, `php artisan config:cache`, `php artisan up`, then check `/up` and `/ready`. **Do not run those steps as a drill on the live folder**; the scratch extraction is the drill.

## Drill B: restore InfoDot's MySQL database into a scratch database (about 30 minutes)

Needs: the newest InfoDot archive, the passphrase (it lives on the host; a copy in a password manager is an open owner item), and a **scratch MySQL database** that is not the live one. On cPanel's MySQL Databases page create `infodotc_restorecheck` and a user with rights on it only (a normal account cannot `CREATE DATABASE` from the shell). Delete both afterwards.

1. **Confirm a fresh archive exists.** Run the `Backups` workflow with mode `inventory`, or on the host `~/bin/dot-backup inventory`. Note the age of InfoDot's newest archive: that age is your RPO right now.
2. **Decrypt and extract to a scratch folder.** Run the `Backups` workflow with mode `restore-test` and `keys` = the key `inventory` shows for InfoDot (this prints `restore_seconds`, `archive_bytes`, `extracted_kb`), or on the host `dot-backup --time restore <key> <archive> ~/restore-check/<key>` as described in BACKUPS.md section A. Write down the numbers.
3. **Import the dump into the scratch database**, with the password in a mode-600 file, not on the command line:

```bash
umask 077
printf '[client]\nuser=infodotc_restore\npassword=%s\n' "$(read -rsp 'scratch db password: ' p; echo "$p")" > ~/.restore.cnf
time mysql --defaults-extra-file=$HOME/.restore.cnf infodotc_restorecheck < ~/restore-check/<key>/database.sql
```

4. **Compare with the live database** (read-only counts; same user names for both connections as appropriate). For each of `users`, `questions`, `answers`, `seminars`, `seminar_registrations`, `seminar_ticket_events`, `teams`, `migrations` run `SELECT COUNT(*) FROM <table>;` on the scratch database and on the live one. The scratch counts should be equal to or a little below the live counts (rows written since the backup). Anything **higher**, or a missing table, is a failed drill.
5. **Check the schema version**: `SELECT migration FROM migrations ORDER BY id DESC LIMIT 3;` on both databases. The restored one must end at a migration that exists in the code (the deploy log or `php artisan migrate:status` lists them). Do not point the live app at the scratch database to test it: the app's configuration is cached, so an environment override would not take effect and a wrong edit of `.env` would.
6. **Clean up**: `rm -rf ~/restore-check ~/.restore.cnf`, then delete the scratch database and user in cPanel.

## What to record

| Date (UTC) | Drill | Archive age (RPO at the time) | Archive bytes | Extract seconds | Import seconds | Row counts match (live vs scratch) | Result |
|---|---|---|---|---|---|---|---|
| **[not yet run]** | B | | | | | | |

| Date (UTC) | Drill | Tarball name | Extract seconds | `artisan`/`.env` present | Result |
|---|---|---|---|---|---|
| **[not yet run]** | A | | | | |

## What the numbers give you

* **RPO** today = the archive age from step 1. The backup runs nightly at 02:40 (BACKUPS.md), so up to about 24 hours of seminar sign-ups, ratings and payments could be lost. Payment data is also held by Billing and Paystack, so a ticket paid since the last backup can be rebuilt from their records; a rating or a question cannot.
* **RTO** for a database loss = decrypt/extract + import (Drill B) + the swap: `php artisan down`, import into the real database, `php artisan up`, check `/ready`. The swap is not rehearsed here because it overwrites live data; time it in a real event and add the figure.
* Whether an **off-host copy** is usable: BACKUPS.md says the owner's Mac pulls the backup folder daily. Do step 2 once from the Mac's copy (needs the passphrase) so the claim that the Mac copy can restore is tested, not assumed.

Only after Drill B has a row, change the release blocker in `../FINDINGS.md` (C-10) and the Reliability line in `../SCORECARD.md`.
