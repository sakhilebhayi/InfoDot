# R-61 availability contract (version 2) and how a platform must enforce it

Written 2026-11-24. This is the rule every Dot platform follows so that InfoDot can switch a platform off (maintenance) and have it hold.

## The call

`GET /api/ecosystem/companies/{company-uuid}/platforms?platform={key}` with the platform's own bearer secret (unchanged from R-50). The answer now carries `contract: 2` and, for every platform, three new fields next to the existing ones:

| Field | Meaning |
|---|---|
| `available` | true when the platform may be used at all right now (operational or degraded). False for maintenance, unavailable, development and not launched. It is about the platform, for everyone, whatever they hold |
| `state` | the platform's operational status as an administrator set it |
| `message` | plain wording to show people when `available` is false, otherwise null |

`entitled` (what the company holds) and `status` (the business state) are unchanged. `available: false` never changes a purchase, an entitlement or any data. An administrative switch and a commercial entitlement are separate answers.

## What a platform does with it

1. Ask for its own entry (`key` equal to its own key). If `available` is false, refuse every signed-in request that would use or change data with HTTP 503 and the `message`. Reading and exporting the company's own records may stay open where the law requires it (Finance already exempts report and attachment downloads). Do not delete, sign out or cancel anything.
2. Keep the last answer it received, with the time. Cache a fresh answer for at most 60 seconds, so a switch takes effect within about a minute.
3. If InfoDot cannot be reached:
   - the last answer is under 6 hours old: act on it. A platform last told "unavailable" stays unavailable; a platform last told "available" stays available;
   - there is no answer, or it is older than that: fail open (keep working), but never turn on anything that is paid for without a recorded entitlement. One InfoDot outage must not take every platform down.
4. An old platform that only reads `entitled` keeps working exactly as before; it simply does not honour the switch yet.

## Status

- InfoDot side: built (this change).
- Platforms: not yet. A pilot is built in Dot.Tasks (separate pull request). Every other platform still reads only `entitled` and fails open, so switching them off in InfoDot only stops people opening them from InfoDot.
