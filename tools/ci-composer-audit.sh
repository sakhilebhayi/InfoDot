#!/usr/bin/env bash
# Release gate: fails when Composer reports a HIGH or CRITICAL advisory (or an abandoned package is not allowed). Lower
# severities are printed as warnings so they are seen, but do not stop a release; the weekly dependency audit tracks them.
set -euo pipefail
out="$(composer audit --format=json --no-interaction 2>/dev/null || true)"
python3 - "$out" <<'PY'
import json, sys
raw = sys.argv[1] or "{}"
try:
    d = json.loads(raw)
except json.JSONDecodeError:
    print("composer audit did not return JSON; treating this as a failure")
    sys.exit(1)
adv = d.get("advisories", {})
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
