# Access log review (owner action S-2f.2)

**Why.** Between 2026-10-20 and 2026-10-23 application folders were briefly or persistently readable over HTTP (S-2c, S-2d), and source archives sat in a docroot. Whether anyone *fetched* anything is a question only the raw web-server access logs can answer.

**What the review does.** `tools/access-log-review-host.sh`, run by the workflow **Access Log Review** (Actions tab, no input needed; optional `since`), reads the raw logs under `~/access-logs` and `~/logs` on the host and counts, per site and per category, the requests that were **answered** (HTTP 200 or 206 with a body) for `.env`, `.git`, databases, Laravel logs, composer files, `vendor/autoload.php`, `artisan`, `phpunit.xml` and archives, and those that were **refused** (403). It writes nothing on the host. Because this repository and its job logs are public, it prints **counts, days and a user-agent family only**: never an IP address, a request line or a user-agent string. Percent-encoded dots (`%2e`) are decoded, so `/%2eenv` is counted as `.env`.

The table lists **answered** requests only, with the **byte size** of what was returned (a real `.env` is a kilobyte or two; a page of tens of kilobytes returned with a 200 is the application's own page, not the file) and the **dates the logs cover**. Refused (403) requests are shown as one total per site. Run it with `times = yes` to also print the day and minute of each answered request (still no address), so it can be matched to our own probe and deploy runs in Actions.

**Reading the table.**

| You see | It means |
|---|---|
| `answered 0` everywhere | Nothing was ever served for these paths in the logs read. Say so in the incident record and close the item. |
| `answered > 0`, user agent `curl`, on a deploy day, 1 to a few clients | Almost certainly our own deploy probe (`curl`) in the seconds the guard had not yet applied. Match the day and time to the deploy runs in Actions (`gh run list`). |
| `answered > 0`, `browser-like`, `bot-or-scanner`, `python` or `go`, any day | Someone other than our probe received the file. Treat the data it contains as copied: go to *What to do* below. |
| `refused > 0` only | People asked, the guard held. No action beyond knowing they look. |

**How far back.** cPanel keeps archived monthly logs in `~/logs` only while "archive logs" is on; the table's `first day` shows how far the logs really reach. If the incident days are not covered, say that plainly: absence of evidence is not evidence of absence.

**Seeing the individual lines (privately).** Run in the cPanel Terminal, never in a public log:

```bash
# which clients (IP), when, which file, what status and size, for the sensitive paths that were ANSWERED
{ zcat -f ~/logs/*.gz 2>/dev/null; cat ~/access-logs/* 2>/dev/null; } \
  | grep -aiE '"(GET|HEAD) [^" ]*(%2e|\.env|\.sqlite|\.sql|storage/logs|\.git/|composer\.(json|lock)|vendor/autoload|/artisan|phpunit\.xml|\.(zip|tar|tgz|7z|rar|bak))[^" ]* HTTP[^"]*" (200|206) [1-9]' \
  | awk '{print $1, $4, $7, $9, $10}' | sort | uniq -c | sort -rn | head -50
```

To check whether an address is yours or GitHub's, compare the time with the deploy runs (`gh run list --workflow deploy.yml`); the deploy probe runs from GitHub's hosted runners.

**What to do when something was served.** Decide per site, with the paths in the table:
1. `.env` or a database or an archive containing them: rotate the database password, `APP_KEY` (read `docs/programme/` notes first: encrypted columns need `php artisan secrets:reencrypt`, as done for Mines on 2026-10-25; sessions and encrypted cookies are invalidated), mail and API credentials, and sign everyone out.
2. `laravel.log` or `composer.json/lock`: rotate anything that was logged (tokens, reset links), and review for personal data.
3. If personal information may have been accessed, the POPIA section 22 notification assessment applies (see `POPIA_READINESS.md` once written).

**Open after the first run.** Record the outcome (date of the run, the first day the logs cover, the counts) in `SECURITY_ASSESSMENT.md` under S-2f, and close or escalate items 2 to 4 there.

## First result (run 40540815624, 2026-10-27, `times = yes`)

113 log files, covering 2026-09-30 to 2026-10-27. The 403 totals per site show that every platform host was asked for these paths many times (24 to 613 per site) and the guard held. **26 table rows are answered requests; 21 of them are explained, 5 are not.**

**Explained: our own probes.** Every answered request on a platform host was a `curl` from one client, and each falls in the same minutes as a command this programme ran (Claude Code session transcripts hold the command times; the logs are in UTC, the first request of each burst lands within a minute of its command):

| Log (UTC) | Sites | Our command |
|---|---|---|
| 2026-10-17 13:47 | Files archive, 2.4 MB | exposure probe of `/Dot.Files.tar.gz` at 13:47 |
| 2026-10-20 15:53 to 15:57 | Dopemine, Engage, Memory, Sheet: `artisan`, `composer`, `database`, `laravel_log`, `phpunit` | the exposure checks and `probe-exposure.sh` of 15:53 to 15:57 on the day App Folder Guard was applied |
| 2026-10-23 18:44 to 18:49 | Agents, Engage, Forms, Sheet archives | archive checks at 18:44 and 18:49 (the 8,000,001-byte rows are a size cap of the check) |
| 2026-10-23 19:36 | Charts: `artisan`, `composer`, `database`, `laravel_log` | Charts exposure check at 19:36 |

This is a match by time and by user agent, not a proof: the log carries an address and our commands ran from the owner's machine. To prove it, the owner compares the address in the private command below with the public address of the Mac that ran Claude Code. Note what the matched rows also mean: **the SQLite databases and Laravel logs of Dopemine, Engage, Memory, Sheet and Charts were genuinely downloadable and were downloaded in full by our own check.** If nothing else asked, nothing left the host, but the copies the check wrote are on the owner's Mac (they were written to `/dev/null` or `/tmp`; delete any under `/tmp` that still exist).

**Not explained: 5 rows, 3 sites. Owner decision needed.**

| Site | Day (UTC) | What | Size | Client |
|---|---|---|---|---|
| doc.infodot.co.za | 2026-10-01 19:00 | `archive`, `artisan`, `composer`, `database`, `laravel_log`, one request each in the same minute | **16 bytes each** | one browser-like client |
| infodot.co.za | 2026-10-05 10:24 | `.env`-type file | 868 bytes | one browser-like client |
| infodot.co.za | 2026-10-08 11:05 | `.git`-type path | 281 bytes | one browser-like client |

Reading: the doc rows are five different paths that all returned the same 16 bytes. A real `artisan` file is 425 bytes (the platform rows above), so the body cannot be the file; it is most likely a catch-all reply. This is an inference from the sizes, not a verified fact. The two InfoDot rows cannot be classified from sizes: 868 bytes is in the range of a small `.env` or an `.env.example`, and 281 bytes is in the range of a `.git/config` or `.git/HEAD`. Our own session transcripts hold no request to infodot.co.za on those two days.

**Private command for these three (cPanel Terminal, never a public log).** It prints address, time, path, status and size for the answered requests only:

```bash
for s in infodot.co.za doc.infodot.co.za; do echo "== $s"; { zcat -f ~/logs/$s*.gz 2>/dev/null; cat ~/access-logs/$s* 2>/dev/null; } \
  | grep -aE '"(GET|HEAD) [^" ]*(%2e|\.env|\.git|\.sqlite|storage/logs|composer\.(json|lock)|/artisan|\.(zip|tar|tgz|7z|rar|bak))[^" ]* HTTP[^"]*" (200|206) [1-9]' \
  | awk '{print $1, $4, $7, $9, $10}'; done
```

Then decide:

- **Path is `/.env.example` or a `.git` path that is a static page of the app**: nothing secret was served; record and close.
- **Path is `/.env` (868 bytes) on infodot.co.za and the client is not ours**: treat the InfoDot application secrets as copied. Rotate, in this order: database password, mail credentials, `APP_KEY` (check which columns are encrypted first; InfoDot's secrets are re-encrypted with `php artisan secrets:reencrypt` as done for Mines on 2026-10-25), the platform handoff signing secrets (S-2b), and sign everyone out. The window is unknown (the file was answered once on 2026-10-05; whether the file was served on other days is not in the logs read, which start on 2026-09-30).
- **Path is a `.git` file (281 bytes) and is a real `.git/config`**: the remote URL may contain a token; revoke it, and find out why `.git` sits under the docroot.
- **doc rows are a catch-all reply**: record and close.

The logs reach back only to 2026-09-30. Anything before that is unknown.
