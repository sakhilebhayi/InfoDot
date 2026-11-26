#!/usr/bin/env bash
# Tests tools/ci-composer-audit.sh with a stub `composer`: it must pass on a clean audit and on a low advisory, and FAIL on a critical,
# high, medium or unrated advisory, on no output, on garbage and on JSON without an "advisories" key. An excepted id passes only with a reason.
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
fail=0
run() { # name expected-exit stub-output
  printf '#!/usr/bin/env bash\nprintf %%s "$STUB"\n' > "$tmp/composer"; chmod +x "$tmp/composer"
  STUB="$3" COMPOSER_AUDIT_EXCEPTIONS="${4:-$tmp/none.txt}" PATH="$tmp:$PATH" bash "$here/ci-composer-audit.sh" >/dev/null 2>&1; rc=$?
  if [ "$rc" = "$2" ]; then echo "ok   $1"; else echo "FAIL $1 (exit $rc, wanted $2)"; fail=1; fi
}
run "clean audit passes"                 0 '{"advisories":[]}'
run "clean audit with empty object"      0 '{"advisories":{}}'
run "low advisory passes with a note"    0 '{"advisories":{"a/b":[{"advisoryId":"X","severity":"low","title":"t"}]}}'
run "medium advisory blocks"             1 '{"advisories":{"a/b":[{"advisoryId":"X","severity":"medium","title":"t"}]}}'
run "unrated advisory blocks (null)"     1 '{"advisories":{"a/b":[{"advisoryId":"X","severity":null,"title":"t"}]}}'
run "unrated advisory blocks (missing)"  1 '{"advisories":{"a/b":[{"advisoryId":"X","title":"t"}]}}'
printf 'X  no fixed version yet; remove when 1.2 ships\n' > "$tmp/ok.txt"
run "excepted advisory with a reason passes" 0 '{"advisories":{"a/b":[{"advisoryId":"X","severity":null,"title":"t"}]}}' "$tmp/ok.txt"
printf 'X\n' > "$tmp/bad.txt"
run "exception without a reason blocks"  1 '{"advisories":{"a/b":[{"advisoryId":"X","severity":null,"title":"t"}]}}' "$tmp/bad.txt"
run "other ids are still blocked"        1 '{"advisories":{"a/b":[{"advisoryId":"Y","severity":"high","title":"t"}]}}' "$tmp/ok.txt"
run "high advisory blocks"               1 '{"advisories":{"a/b":[{"advisoryId":"X","severity":"high","title":"t"}]}}'
run "critical advisory blocks"           1 '{"advisories":{"a/b":[{"advisoryId":"X","severity":"critical","title":"t"}]}}'
run "no output blocks (audit did not run)" 1 ''
run "garbage blocks"                     1 'Could not resolve host: repo.packagist.org'
run "json without advisories blocks"     1 '{"error":"x"}'
exit $fail
