# R-12 first increment on InfoDot: friction signals (2026-10-28)

**Status: built, tested, off by default.** InfoDot counts where its own pages fail people and shows a moderator-only page, "Where people struggle". Nothing is counted until the owner sets `UX_SIGNALS=true` on the host. This is the first working slice of the loop in [requirements/dot-brain-ux-interaction-intelligence.md](requirements/dot-brain-ux-interaction-intelligence.md); the contract it follows is Dot.Brain's `services/ux-interactions` (ADR-0025, proposed).

## What is stored (all of it)

One row per **day, route template, event type, status, error category and speed bucket**, with a count (`ux_daily_counts`). Nothing else.

| Stored | Example | Never stored |
|---|---|---|
| Route **template** | `/question/view/{qid}` | the address opened, ids, query strings, search words, form content |
| Event type from the contract | `form.validation_failed`, `search.empty`, `request.slow`, `nav.view`, `request.failed`, `form.submit`, `search.run` | clicks, mouse movement, scroll, keystrokes |
| Status, error category (`validation`, `not_found`, `permission`, `rate_limited`, `server`) | `failure` / `not_found` | error messages |
| Speed bucket (`lt_3s`, `gte_3s`) for slow views | | exact timings |
| A count | 42 | any person, session, cookie, IP address, user agent or tenant |

The consent basis in the contract is `aggregate_only`: a count cannot be traced to a person because no person is in it. An address no route answers is counted as `/{unmatched}` and the address itself is dropped (a test proves an email-shaped path does not reach the table). A test pins the table's column list so a column that could hold a person or text cannot be added unnoticed. The service refuses any type, status or error outside the contract's lists and any route that is not a plain template.

What is counted: web page views (success, 403, 404), server errors (5xx), rate limiting (429), forms that send people back (validation failures), successful form submissions, searches (found / nothing found, from the results page), and views slower than 1,500 ms.

## What it does with the counts

`ux:friction` (command) and `/moderation/friction` (moderators only) apply fixed rules to the last 7, 14, 30 or 90 days and compare each finding with the period before it:

| Rule | Fires when (defaults in `config/ux.php`) | First look suggested |
|---|---|---|
| Form sends people back | at least 20 submissions on a form and at least 20% fail validation | labels, hints, required fields |
| Searches find nothing | at least 20 searches and at least 30% find nothing | missing topics or answers, other ways of saying it |
| Slow page | at least 20 views and at least 20% slower than 1,500 ms | queries and what the page loads |
| Address does not exist | 10 or more 404s on a route in the window | find the link that points there |
| Server error | 3 or more 5xx on a route in the window | application log for that time |

A rule says nothing under 20 observations. Each finding shows the numbers, the rate before, whether it is better, worse or about the same, and how far to trust it (low under 60 observations, medium to 200, high above). **Advisory only:** it recommends a page to look at and changes nothing. This closes the loop the brief asks for as far as one product can alone: observe (counts), find (rules), a person acts, and the next period shows whether the rate moved.

## Turning it on, and off

On: set `UX_SIGNALS=true` in InfoDot's `.env` on the host (then `php artisan config:cache`). Off: remove it or set `false`; counting stops at once and `/moderation/friction` says so. Existing rows are removed after 180 days (`ux:prune`, scheduled daily) or all at once with `DELETE FROM ux_daily_counts`.

**Owner decisions still open before it goes on** (none is needed for the code to be safe while it is off): whether the privacy policy should mention these counts (it holds no personal information, so the position is that it does not need consent, but the policy is the owner's and an adviser's call); the thresholds above; and the three Dot.Brain decisions in ADR-0025 (who measures across platforms, where a cross-platform dashboard lives, events versus aggregates). This increment follows the author's recommendation on the last one (aggregates only) so it could be built without waiting.

## Not built

Client-side signals (repeated clicks, rage clicks, abandoned multi-step flows, help opened): they need a script in the browser and are where personal information leaks in, so they wait for the owner's decisions. Cross-platform collection (sending these counts to Dot.Analytics' signed daily ingest is the natural next step; it needs `ANALYTICS_INGEST_URL` and a per-platform secret from the owner). Recording the decision and its outcome in Dot.Memory's loop. Other platforms.
