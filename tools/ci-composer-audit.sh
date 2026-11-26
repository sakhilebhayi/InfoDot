#!/usr/bin/env bash
# Release gate: fails when Composer reports an advisory rated CRITICAL, HIGH or MEDIUM, or one with NO rating at all. R-72 (S-01): 47
# unrated advisories, Symfony mailer STARTTLS flaws among them, used to pass because only high/critical blocked. Low advisories are printed
# as warnings. An advisory can be let through only by naming its id in tools/composer-audit-exceptions.txt with a reason, so every
# exception is written down and reviewed. It also FAILS when the audit itself did not run (no output, not JSON, no "advisories" key): an
# audit that could not reach Packagist must never read as "no advisories".
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
out="$(composer audit --format=json --no-interaction 2>/dev/null)"
python3 - "$out" "${COMPOSER_AUDIT_EXCEPTIONS:-$here/composer-audit-exceptions.txt}" <<'PY'
import json, os, sys
raw, exceptions_path = sys.argv[1], sys.argv[2]
excepted = set()
if os.path.exists(exceptions_path):
    for line in open(exceptions_path, encoding="utf-8"):
        line = line.strip()
        if line and not line.startswith("#"):
            parts = line.split(None, 1)
            if len(parts) < 2:
                print(f"exception '{parts[0]}' has no reason written next to it: the release is blocked.")
                sys.exit(1)
            excepted.add(parts[0])
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
def severity(a):
    s = str(a.get("severity") or "").strip().lower()
    return s if s in ("critical", "high", "medium", "low") else "unrated"
blocking = [(p, a) for p, a in rows if severity(a) != "low" and str(a.get("advisoryId")) not in excepted]
for p, a in rows:
    state = "excepted" if str(a.get("advisoryId")) in excepted else ("BLOCKING" if (p, a) in blocking else "warning")
    print(f"{state}: {p} {a.get('advisoryId')} {severity(a)} {str(a.get('title', ''))[:90]}")
if blocking:
    print(f"{len(blocking)} advisory(ies) rated medium or above, or not rated: the release is blocked until they are fixed or excepted with a reason.")
    sys.exit(1)
print(f"composer audit: {len(rows)} advisory(ies), none blocking.")
PY
