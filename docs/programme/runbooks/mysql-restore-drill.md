# Runbook — MySQL restore drill (R-17 blocker; R-22 §3.1)

Backups are unverified until a restore succeeds. The nightly tool (`~/bin/dot-backup`, source
`tools/backup-host.sh`, full docs `docs/programme/BACKUPS.md`) already encrypts one archive per
app per night and has `restore` / `restore-test` built in. The drill = run them, then prove the
restored data answers real application queries. Never touches the live database.

Run ON THE HOST (SSH as the cPanel user), outside business hours. ~30–45 min.

## 1. Pick and check the newest archive (~2 min)

    dot-backup inventory --strict
    dot-backup verify infodot --time

`verify` decrypts the newest InfoDot archive to scratch, checks it, deletes the scratch — and
`--time` prints the timings. Note the archive's date: **that gap is your actual RPO** (target:
≤24h — nightly cron).

## 2. Scripted restore test (~5 min)

    dot-backup restore-test infodot --time

Restore + integrity check + timings into a scratch directory, then cleans up. Record the
printed seconds.

## 3. Real database restore into a scratch DB (~15 min) — the part only you can do

    dot-backup restore infodot <archive-name> ~/drill-scratch
    # cPanel → MySQL Databases → create database <user>_drill (and grant your DB user)
    mysql <user>_drill < ~/drill-scratch/**/database.sql

Then prove the data is whole (replace `<user>_drill` accordingly):

    mysql <user>_drill -e "SELECT COUNT(*) users FROM users; SELECT COUNT(*) solutions FROM solutions; SELECT COUNT(*) questions FROM questions;"
    mysql <user>_drill -e "SELECT COUNT(*) orphan_steps FROM steps s LEFT JOIN solutions so ON so.id=s.solution_id WHERE so.id IS NULL;"
    mysql <user>_drill -e "SELECT MAX(created_at) newest_user FROM users; SELECT MAX(created_at) newest_solution FROM solutions;"

Expect: counts in the same range as production, zero orphan steps, `newest_*` within the RPO
window. Optionally point a copy of `.env` (DB_DATABASE=<user>_drill) at it and run
`php artisan migrate:status` — every migration should show Ran.

## 4. Clean up and record (~5 min)

    mysql -e "DROP DATABASE <user>_drill;"
    rm -rf ~/drill-scratch

Record in the R-18/R-21 register: archive name + age (RPO), total restore seconds (RTO), the
query outputs, and any gaps. Paste the terminal output into the session and the completion
report gets updated with the evidence.

**Acceptance:** the drill counts only if step 3's queries ran against restored data. A green
`verify` alone is not a restore drill.
