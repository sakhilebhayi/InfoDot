#!/usr/bin/env bash
# Release gate: fails when Composer reports a HIGH or CRITICAL advisory. Lower severities are printed as warnings so they are seen, but do not
# stop a release; the weekly dependency audit tracks them. It also FAILS when the audit itself did not run (no output, not JSON, no
# "advisories" key): an audit that could not reach Packagist must never read as "no advisories".
set -uo pipefail
out="$(composer audit --format=json --no-interaction 2>/dev/null)"
python3 - "$out" <<'PY'
import json, sys
raw = sys.argv[1]
try:
    d = json.loads(raw)
except (json.JSONDecodeError, TypeError):
    print("composer audit produced no usable JSON: the audit did not run, so the release is blocked.")
    sys.exit(1)
if not isinstance(d, dict) or "advisories" not in d:
    print("composer audit output has no 'advisories' key: the audit did not run, so the release is blocked.")
    sys.exit(1)
adv = d["advisories"]
rows = [(p, a) for p, items in (adv.items() if isinstance(adv, dict) else []) for a in items]
blocking = [(p, a) for p, a in rows if str(a.get("severity", "")).lower() in ("high", "critical")]
for p, a in rows:
    sev = str(a.get("severity", "unknown")).lower()
    print(f"{'BLOCKING' if (p, a) in blocking else 'warning'}: {p} {a.get('advisoryId')} {sev} {a.get('title', '')[:90]}")
if blocking:
    print(f"{len(blocking)} high/critical advisory(ies): the release is blocked until they are fixed or formally excepted.")
    sys.exit(1)
print(f"composer audit: {len(rows)} advisory(ies), none high or critical.")
PY
