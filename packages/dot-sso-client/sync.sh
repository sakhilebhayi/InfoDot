#!/usr/bin/env bash
# dot-sso-client distribution: copy this package into a Laravel platform, the same pattern as dot-ui.
#
#   sync.sh <repo-path>
#
# The source of truth is InfoDot's packages/dot-sso-client. This script copies it to <repo>/packages/dot-sso-client, writes
#   packages/dot-sso-client/VERSION          line 1: InfoDot's git commit, line 2: the package version
#   packages/dot-sso-client/MANIFEST.sha256  `sha256sum` format, one "<hash>  <path>" per shipped file
# and installs the drift test <repo>/tests/Feature/DotSsoClientDriftTest.php, which recomputes those hashes (editing the
# copy, or leaving a stray file in it, fails the platform's test run).
#
# It also makes three additive edits to the host, each only if missing:
#   composer.json     autoload Dot\SsoClient\ => packages/dot-sso-client/src/, autoload-dev Dot\SsoClient\Tests\ => .../tests/
#   bootstrap/providers.php   Dot\SsoClient\DotSsoServiceProvider::class
#   phpunit.xml       a DotSsoClient test suite for packages/dot-sso-client/tests
# After the first run: `composer dump-autoload`. Re-running is safe: files removed from the package are removed from the
# host (only files the previous manifest listed). It never touches a git remote, an .env file, or a database.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="${1:-}"
[ -n "$REPO" ] || { echo "usage: sync.sh <repo-path>" >&2; exit 2; }
[ -d "$REPO" ] || { echo "not a directory: $REPO" >&2; exit 2; }
REPO="$(cd "$REPO" && pwd)"
[ -f "$REPO/composer.json" ] || { echo "no composer.json in $REPO: not a Laravel repository" >&2; exit 2; }
command -v python3 >/dev/null || { echo "python3 is required" >&2; exit 2; }

TOP="$(git -C "$HERE" rev-parse --show-toplevel 2>/dev/null || true)"
if [ -n "$TOP" ]; then
  COMMIT="$(git -C "$HERE" rev-parse HEAD)"
  DIRTY="$(git -C "$HERE" status --porcelain -- . 2>/dev/null | wc -l | tr -d ' ')"
  [ "$DIRTY" = "0" ] || echo "warning: the package has $DIRTY uncommitted change(s); VERSION records the commit, not these edits" >&2
else
  COMMIT="unknown"
fi

python3 -I - "$HERE" "$REPO" "$COMMIT" <<'PY'
import hashlib, json, os, re, shutil, sys

here, repo, commit = sys.argv[1:4]
dest = os.path.join(repo, "packages", "dot-sso-client")
SHIP_DIRS = ["config", "database", "resources", "routes", "src", "tests"]
SHIP_FILES = ["composer.json", "README.md"]

version = open(os.path.join(here, "VERSION")).read().strip()
if not re.fullmatch(r"\d+\.\d+\.\d+", version):
    sys.exit("bad VERSION in the package source")

def sha(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        h.update(f.read())
    return h.hexdigest()

files = []
for d in SHIP_DIRS:
    for root, _, names in os.walk(os.path.join(here, d)):
        for n in sorted(names):
            if n.startswith("."):
                continue
            files.append(os.path.relpath(os.path.join(root, n), here))
files += [f for f in SHIP_FILES if os.path.exists(os.path.join(here, f))]
files = sorted(set(files))

# Remove what the previous manifest listed and this one no longer ships.
old = os.path.join(dest, "MANIFEST.sha256")
if os.path.exists(old):
    for line in open(old).read().splitlines():
        if not line.strip():
            continue
        p = line.split(None, 1)[1]
        if p not in files and os.path.exists(os.path.join(dest, p)):
            os.remove(os.path.join(dest, p))

for rel in files:
    target = os.path.join(dest, rel)
    os.makedirs(os.path.dirname(target), exist_ok=True)
    shutil.copyfile(os.path.join(here, rel), target)

with open(os.path.join(dest, "MANIFEST.sha256"), "w") as f:
    for rel in files:
        f.write("%s  %s\n" % (sha(os.path.join(dest, rel)), rel))
with open(os.path.join(dest, "VERSION"), "w") as f:
    f.write("%s\n%s\n" % (commit, version))

# Prune empty directories left behind.
for root, dirs, _ in os.walk(dest, topdown=False):
    for d in dirs:
        p = os.path.join(root, d)
        if not os.listdir(p):
            os.rmdir(p)

# Drift test.
os.makedirs(os.path.join(repo, "tests", "Feature"), exist_ok=True)
shutil.copyfile(os.path.join(here, "sync", "DotSsoClientDriftTest.php"), os.path.join(repo, "tests", "Feature", "DotSsoClientDriftTest.php"))

changed = []

# composer.json autoload mappings (an autoload mapping, not a dependency, exactly like dot-ui). Edited as text so the
# host's formatting is left alone.
cj = os.path.join(repo, "composer.json")
text = open(cj).read()
def ensure(text, section, ns, path):
    key = json.dumps(ns)[1:-1]
    entry = '"%s": "%s"' % (key, path)
    m = re.search(r'"%s"\s*:\s*\{\s*"psr-4"\s*:\s*\{' % section, text)
    if not m:
        sys.exit("composer.json has no %s psr-4 block; add the mapping by hand: %s" % (section, entry))
    close = text.index("}", m.end())
    block = text[m.end():close]
    if entry in block:
        return text, False
    if '"%s"' % key in block:
        sys.exit("composer.json maps %s to something else; fix by hand" % ns)
    last = block.rstrip()
    indent = re.search(r"\n([ \t]*)\"", block)
    pad = indent.group(1) if indent else "            "
    new_block = last + ",\n" + pad + entry + "\n" + pad[:-4] if pad.endswith("    ") else last + ", " + entry
    return text[:m.end()] + new_block + text[close:], True
text, c1 = ensure(text, "autoload", "Dot\\SsoClient\\", "packages/dot-sso-client/src/")
text, c2 = ensure(text, "autoload-dev", "Dot\\SsoClient\\Tests\\", "packages/dot-sso-client/tests/")
if c1 or c2:
    json.loads(text)  # still valid
    open(cj, "w").write(text)
    changed.append("composer.json autoload mappings")

# Provider registration (Laravel 11+ bootstrap/providers.php).
bp = os.path.join(repo, "bootstrap", "providers.php")
if os.path.exists(bp):
    src = open(bp).read()
    if "DotSsoServiceProvider" not in src:
        src = re.sub(r"\n\];", "\n    Dot\\\\SsoClient\\\\DotSsoServiceProvider::class,\n];", src, count=1)
        open(bp, "w").write(src)
        changed.append("bootstrap/providers.php")
else:
    print("note: no bootstrap/providers.php; register Dot\\SsoClient\\DotSsoServiceProvider yourself")

# phpunit suite.
px = os.path.join(repo, "phpunit.xml")
if os.path.exists(px):
    src = open(px).read()
    if "packages/dot-sso-client/tests" not in src:
        m = re.search(r"\n([ \t]*)</testsuites>", src)
        pad = m.group(1) if m else "    "
        inner = pad + "    "
        suite = '%s<testsuite name="DotSsoClient">\n%s    <directory suffix="Test.php">packages/dot-sso-client/tests</directory>\n%s</testsuite>\n' % (inner, inner, inner)
        src = src.replace("\n" + pad + "</testsuites>", "\n" + suite.rstrip("\n") + "\n" + pad + "</testsuites>", 1)
        open(px, "w").write(src)
        changed.append("phpunit.xml")

print("dot-sso-client %s (%s) copied to %s: %d files" % (version, commit[:10], dest, len(files)))
for c in changed:
    print("edited:", c)
if any(c.startswith("composer.json") for c in changed):
    print("next: composer dump-autoload")
PY
