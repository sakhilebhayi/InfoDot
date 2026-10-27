# Access log review (owner action S-2f.2)

**Why.** Between 2026-10-20 and 2026-10-23 application folders were briefly or persistently readable over HTTP (S-2c, S-2d), and source archives sat in a docroot. Whether anyone *fetched* anything is a question only the raw web-server access logs can answer.

**What the review does.** `tools/access-log-review-host.sh`, run by the workflow **Access Log Review** (Actions tab, no input needed; optional `since`), reads the raw logs under `~/access-logs` and `~/logs` on the host and counts, per site and per category, the requests that were **answered** (HTTP 200 or 206 with a body) for `.env`, `.git`, databases, Laravel logs, composer files, `vendor/autoload.php`, `artisan`, `phpunit.xml` and archives, and those that were **refused** (403). It writes nothing on the host. Because this repository and its job logs are public, it prints **counts, days and a user-agent family only**: never an IP address, a request line or a user-agent string. Percent-encoded dots (`%2e`) are decoded, so `/%2eenv` is counted as `.env`.

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
