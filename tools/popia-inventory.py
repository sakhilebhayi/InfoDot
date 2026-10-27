#!/usr/bin/env python3
"""Personal-data inventory of the Dot Laravel apps, read from their source (roadmap item: POPIA readiness).

    popia-inventory.py [--ref origin/main] [--md OUT.md] [--json OUT.json] REPO_DIR [REPO_DIR ...]

Read-only. For each repository it reads, from the git ref (or from the working tree when the folder is not a git
checkout): the migrations, the model casts, composer.json / package.json, config/services.php, .env.example, routes,
Blade views and the scheduler. It reports

  * every table column whose NAME looks like personal information, by category (identity, contact, financial,
    special personal information, children, online identifiers and location, credentials, free text), and whether
    the model casts it as `encrypted`;
  * the third-party processors visible in the dependencies and the configuration (payments, mail, error tracking,
    object storage, AI APIs, analytics);
  * external hosts that pages load fonts or scripts from (the visitor's IP address reaches them);
  * signals for retention and subject rights: soft deletes, prune jobs, export and erase routes, a privacy page.

It is a heuristic from names, not from data: it cannot see what is stored in a free-text column, whether a column is
used, or what is on the production database. It prints no row, no secret and no value from any `.env`. This is an
engineering inventory to support a legal assessment, not legal advice.
"""
import argparse
import json
import os
import re
import subprocess
import sys

# ---- categories (column name patterns, matched against the lower-cased, underscore-split name) ---------------------
CATEGORIES = [
    # (category, label, regex). Order matters: the first match wins, so the more sensitive ones come first.
    ('special', 'Special personal information (POPIA s26)',
     r'(^|_)(health_(condition|record|info\w*|notes?|declaration)|medical|medication|diagnosis|illness|disabilit\w*|sick(ness)?|pregnan\w*|religio\w*|race|racial|ethnic\w*|'
     r'union|political|sex_life|sexual\w*|biometric\w*|fingerprint|genetic\w*|criminal\w*|offence|allegation|conviction)($|_)'),
    ('children', 'Children (POPIA s34)', r'(^|_)(child|children|minor|guardian|parent_name|dependants?)($|_)'),
    ('financial', 'Financial',
     r'(^|_)(salary|wage|bank|iban|swift|card|cvv|payout|pay_rate|tax|vat|account_(no|number)|routing|invoice_to|billing_(name|address))($|_)'),
    ('online', 'Online identifiers and location',
     r'(^|_)(ip|ip_address|user_agent|ua|device\w*|latitude|longitude|lat|lng|lon|geo|geofence\w*|location|session_id|fingerprint_id)($|_)'),
    ('identity', 'Identity',
     r'((^|_)(first|last|middle|full|preferred|legal|maiden|nick|display|emergency_contact|contact|customer|client|employee|operator|driver)_name($|_))|'
     r'(^|_)(surname|username|handle|avatar|photo\w*|profile_photo\w*|dob|birth\w*|gender|age|id_number|national_id|passport|employee_(no|number)|nationality|job_title)($|_)|^name$'),
    ('contact', 'Contact',
     r'(^|_)(email|e_mail|phone|mobile|cell\w*|tel|telephone|whatsapp|address|street|suburb|city|postal\w*|zip\w*|province)($|_)'),
    ('credential', 'Credentials and secrets',
     r'(^|_)(password|passwd|token|secret|api_key|apikey|remember_token|two_factor\w*|otp|pin|recovery_codes)($|_)'),
    ('free_text', 'Free text (may hold anything)',
     r'(^|_)(body|message|comment|comments|notes?|bio|reason|description|content|feedback|answer|answers|response|payload|meta|metadata)($|_)'),
]
CAT_RE = [(c, label, re.compile(rx)) for c, label, rx in CATEGORIES]
# name-like columns that are NOT about a person (a table, a team, a product): reduce noise for 'identity'/'free_text'
NOT_PERSONAL_TABLE = re.compile(r'^(roles|permissions|migrations|cache|cache_locks|jobs|job_batches|failed_jobs|password_reset_tokens|sessions|personal_access_tokens|teams?|team_.*|settings)$')

PROCESSORS = [
    # (display name, kind, regex over dependency names, regex over uncommented .env.example / config lines, regex over app code)
    ('Stripe', 'payments', r'stripe/stripe-php|laravel/cashier', r'STRIPE_', r'stripe'),
    ('Paystack', 'payments', r'paystack', r'PAYSTACK_', r'paystack'),
    ('PayPal', 'payments', r'paypal', r'PAYPAL_', r'paypal'),
    ('Mailgun', 'mail', r'mailgun', r'MAILGUN_|MAIL_MAILER=mailgun', r'mailgun'),
    ('Postmark', 'mail', r'postmark', r'POSTMARK_|MAIL_MAILER=postmark', r'postmark'),
    ('Resend', 'mail', r'resend/resend', r'RESEND_|MAIL_MAILER=resend', r'resend'),
    ('Amazon SES / S3 (AWS)', 'mail or storage', r'aws/aws-sdk-php|league/flysystem-aws-s3', r'AWS_ACCESS_KEY_ID=\S|AWS_BUCKET=\S|MAIL_MAILER=ses', r"disk\(.s3.\)|'s3'|Storage::disk\('s3"),
    ('SendGrid', 'mail', r'sendgrid', r'SENDGRID_', r'sendgrid'),
    ('Twilio', 'sms', r'twilio', r'TWILIO_', r'twilio'),
    ('Vonage', 'sms', r'vonage|nexmo', r'VONAGE_|NEXMO_', r'vonage|nexmo'),
    ('Sentry', 'error tracking', r'sentry/sentry', r'SENTRY_', r'sentry'),
    ('Bugsnag', 'error tracking', r'bugsnag', r'BUGSNAG_', r'bugsnag'),
    ('Pusher', 'realtime', r'pusher', r'PUSHER_APP_KEY=\S|BROADCAST_(DRIVER|CONNECTION)=pusher', r'pusher'),
    ('Algolia / Meilisearch / Typesense', 'search', r'algolia|meilisearch|typesense', r'ALGOLIA_|MEILISEARCH_|TYPESENSE_', r'algolia|meilisearch|typesense'),
    ('OpenAI', 'AI API', r'openai', r'OPENAI_', r'openai'),
    ('Anthropic', 'AI API', r'anthropic', r'ANTHROPIC_', r'anthropic|api\.anthropic\.com'),
    ('Google APIs / sign-in', 'identity or analytics', r'google/apiclient|laravel/socialite', r'GOOGLE_CLIENT', r'socialite|google'),
    ('Dot.Analytics ingest', 'first-party analytics', r'$^', r'ANALYTICS_INGEST_URL', r'ANALYTICS_INGEST|analytics\.ingest'),
]
EXTERNAL_HOST_RE = re.compile(r'https?://([a-z0-9.-]+\.[a-z]{2,})', re.I)
OWN_HOST = re.compile(r'(^|\.)(infodot\.co\.za|localhost|example\.(com|org|invalid)|w3\.org|schema\.org|laravel\.com|github\.com|tailwindcss\.com|'
                      r'livewire\.laravel\.com|jetstream\.laravel\.com|alpinejs\.dev|vuejs\.org|inertiajs\.com|vitejs\.dev)$', re.I)

SIGNALS = [
    ('soft_deletes', 'soft deletes in migrations', 'database/migrations', r'softDeletes\('),
    ('prune', 'prune jobs (model:prune / Prunable / MassPrunable / a prune command)', 'app', r'Prunable|MassPrunable|model:prune|prune|purge|retention'),
    ('scheduler', 'scheduled commands', 'routes', r'Schedule::|->schedule\('),
    ('export_route', 'a data export route or action', 'routes', r'export|download-my-data|data-export'),
    ('erase_route', 'an account deletion or erase route', 'routes', r'account.*(delete|destroy)|profile.*destroy|erase|forget-me|delete-account|\bdestroy\b'),
    ('privacy_page', 'a privacy or terms page', 'resources/views', r'privacy|terms|popia'),
    ('cookie_banner', 'a cookie / consent notice', 'resources/views', r'cookie|consent'),
]


class Source:
    """Files of one repository, from a git ref when possible, else the working tree."""

    def __init__(self, path, ref):
        self.path, self.ref = path, ref
        self.git = os.path.isdir(os.path.join(path, '.git')) and subprocess.run(
            ['git', '-C', path, 'rev-parse', '--verify', '-q', ref + '^{commit}'], capture_output=True).returncode == 0
        self.names = self._list()
        self._cache = {}
        # a Laravel app may sit in a sub-folder (Dot.Charts has backend/): the folder that holds `artisan`
        self.root = ''
        if 'artisan' not in self.names:
            subs = sorted(n[:-len('artisan')] for n in self.names if n.endswith('/artisan') and n.count('/') == 1)
            if subs:
                self.root = subs[0]

    def _list(self):
        if self.git:
            out = subprocess.run(['git', '-C', self.path, 'ls-tree', '-r', '--name-only', self.ref], capture_output=True, text=True).stdout
            return out.splitlines()
        names = []
        for root, dirs, files in os.walk(self.path):
            dirs[:] = [d for d in dirs if d not in ('.git', 'node_modules', 'vendor', 'storage', 'public')]
            for f in files:
                names.append(os.path.relpath(os.path.join(root, f), self.path))
        return names

    def read_many(self, wanted):
        """{name: text} for the names not yet read, in one git call when this is a checkout."""
        todo = [n for n in wanted if n not in self._cache]
        if self.git and todo:
            data = subprocess.run(['git', '-C', self.path, 'cat-file', '--batch'], input=''.join(f'{self.ref}:{n}\n' for n in todo).encode(),
                                  capture_output=True).stdout
            pos = 0
            for n in todo:
                nl = data.index(b'\n', pos)
                head = data[pos:nl].decode().split()
                if len(head) == 3 and head[1] == 'blob':
                    size = int(head[2])
                    self._cache[n] = data[nl + 1:nl + 1 + size].decode('utf-8', 'replace')
                    pos = nl + 1 + size + 1
                else:
                    self._cache[n] = ''
                    pos = nl + 1
        else:
            for n in todo:
                try:
                    self._cache[n] = open(os.path.join(self.path, n), encoding='utf-8', errors='replace').read()
                except OSError:
                    self._cache[n] = ''
        return {n: self._cache[n] for n in wanted}

    def under(self, prefix, suffixes=None):
        prefix = self.root + prefix
        return [n for n in self.names if n.startswith(prefix) and (suffixes is None or n.endswith(suffixes))]

    def has(self, name):
        return self.root + name in self.names

    def read(self, name):
        return self.read_many([self.root + name])[self.root + name]


def classify(table, col):
    c = col.lower()
    if c in ('id', 'created_at', 'updated_at', 'deleted_at', 'team_id', 'user_id', 'email_verified_at', 'uuid', 'ulid', 'type', 'status', 'name_hash') or (c.endswith('_id') and c not in ('session_id', 'national_id', 'passport_id')):
        return None
    if c.endswith(('_hash', '_at', '_count', '_limit')) or c.startswith(('is_', 'has_', 'max_', 'min_', 'total_')):
        return None
    for cat, label, rx in CAT_RE:
        if rx.search(c):
            if cat in ('identity', 'free_text') and NOT_PERSONAL_TABLE.match(table):
                return None
            if cat == 'identity' and c == 'name' and table not in ('users', 'employees', 'customers', 'contacts', 'members', 'people', 'persons', 'clients', 'leads', 'candidates', 'students', 'farmers', 'suppliers', 'vendors'):
                return None
            return cat
    return None


def parse_migrations(src):
    """{table: {column: type}} from Schema::create / Schema::table blocks (column definitions only)."""
    tables = {}
    files = src.under('database/migrations/', '.php')
    for name, text in src.read_many(files).items():
        cur = None
        for line in text.split('\n'):
            m = re.search(r"Schema::(?:create|table)\(\s*['\"]([a-z0-9_]+)['\"]", line)
            if m:
                cur = m.group(1)
                tables.setdefault(cur, {})
                continue
            if cur is None:
                continue
            m = re.search(r"\$table->([a-zA-Z]+)\(\s*['\"]([a-zA-Z0-9_]+)['\"]", line)
            if m and m.group(1) not in ('index', 'unique', 'primary', 'foreign', 'dropColumn', 'dropIndex', 'dropUnique', 'dropForeign', 'renameColumn', 'foreignId', 'constrained'):
                tables[cur][m.group(2)] = m.group(1)
            if m is None:
                m2 = re.search(r"\$table->(rememberToken|timestamps|softDeletes)\(", line)
                if m2 and m2.group(1) == 'rememberToken':
                    tables[cur]['remember_token'] = 'string'
            m = re.search(r"\$table->morphs\(\s*['\"]([a-zA-Z0-9_]+)", line)
            # morphs add *_type/_id columns, none personal: ignored on purpose
            m = re.search(r"dropColumn\(\s*\[?\s*['\"]([a-zA-Z0-9_]+)['\"]", line)
            if m:
                tables[cur].pop(m.group(1), None)
    return tables


def encrypted_columns(src):
    """Columns a model casts as `encrypted` (any flavour), as {column name} (not table-exact: models are mapped by name only)."""
    cols = set()
    for name, text in src.read_many(src.under('app/Models/', '.php')).items():
        for m in re.finditer(r"['\"]([a-zA-Z0-9_]+)['\"]\s*=>\s*['\"]encrypted(?::[a-z]+)?['\"]", text):
            cols.add(m.group(1))
        for m in re.finditer(r"['\"]([a-zA-Z0-9_]+)['\"]\s*=>\s*\\?(?:[A-Za-z]+\\)*\w*(?:Encrypt|Sensitive|Secret|Protected)\w*::class", text):
            cols.add(m.group(1))
    return cols


def processors_and_hosts(src):
    blobs = {}
    deps, envtxt = '', ''
    if src.has('composer.json'):
        try:
            deps += ' '.join(json.loads(src.read('composer.json')).get('require', {})) + ' '
        except ValueError:
            pass
    if src.has('package.json'):
        try:
            pj = json.loads(src.read('package.json'))
            deps += ' '.join(list(pj.get('dependencies', {})) + list(pj.get('devDependencies', {})))
        except ValueError:
            pass
    blobs = {}
    if src.has('.env.example'):
        blobs['.env.example'] = envtxt = '\n'.join(l for l in src.read('.env.example').split('\n') if l.strip() and not l.lstrip().startswith('#'))
    code = '\n'.join(src.read_many((src.under('app/', '.php') + src.under('routes/', '.php'))[:1500]).values())
    found = []
    for disp, kind, dep_rx, env_rx, code_rx in PROCESSORS:
        if re.search(dep_rx, deps, re.I):
            status = 'dependency'
        elif re.search(code_rx, code, re.I) and re.search(env_rx, envtxt, re.I):
            status = 'used in code'
        elif re.search(env_rx, envtxt, re.I):
            status = 'config only'
        else:
            continue
        found.append({'name': disp, 'kind': kind, 'status': status})
    mailer = re.search(r'^MAIL_MAILER=(\S+)', blobs.get('.env.example', ''), re.M)
    views = src.under('resources/views/', '.php') + src.under('resources/css/') + src.under('resources/js/', ('.vue', '.js', '.ts'))
    hosts = {}
    for n, t in src.read_many(views).items():
        for h in set(EXTERNAL_HOST_RE.findall(t)):
            h = h.lower()
            if not OWN_HOST.search(h):
                hosts[h] = hosts.get(h, 0) + 1
    return found, (mailer.group(1) if mailer else None), dict(sorted(hosts.items(), key=lambda kv: -kv[1]))


def signals(src):
    out = {}
    for key, label, prefix, rx in SIGNALS:
        files = [n for n in src.under(prefix + '/') if n.endswith(('.php', '.vue', '.js', '.blade.php'))]
        texts = src.read_many(files[:400])
        hit = sorted(n for n, t in texts.items() if re.search(rx, t, re.I)) if key != 'privacy_page' else sorted(n for n in files if re.search(rx, n, re.I))
        out[key] = {'label': label, 'files': len(hit)}
    return out


def inventory(path, ref):
    src = Source(path, ref)
    tables = parse_migrations(src)
    enc = encrypted_columns(src)
    personal = {}
    for t, cols in sorted(tables.items()):
        for c, typ in sorted(cols.items()):
            cat = classify(t, c)
            if cat:
                personal.setdefault(t, []).append({'column': c, 'category': cat, 'encrypted_cast': c in enc})
    procs, mailer, hosts = processors_and_hosts(src)
    return {
        'repo': os.path.basename(os.path.abspath(path)),
        'source': ref if src.git else 'working tree',
        'tables': len(tables),
        'columns': sum(len(c) for c in tables.values()),
        'personal': personal,
        'encrypted_cast_columns': sorted(enc),
        'processors': procs,
        'mailer_default': mailer,
        'external_hosts': hosts,
        'signals': signals(src),
    }


def counts(inv):
    n = {c: 0 for c, _, _ in CATEGORIES}
    enc = {c: 0 for c, _, _ in CATEGORIES}
    for cols in inv['personal'].values():
        for col in cols:
            n[col['category']] += 1
            enc[col['category']] += col['encrypted_cast']
    return n, enc


def render_md(invs):
    cats = [c for c, _, _ in CATEGORIES]
    lab = {c: l for c, l, _ in CATEGORIES}
    out = ['# Personal data inventory (generated)', '',
           'Generated by `tools/popia-inventory.py` from the source of each repository (migrations, model casts, dependencies, config, views). '
           'A heuristic from **column names**: it cannot see stored values, free-text content, whether a column is used, or the production database. '
           'It is an engineering input to a legal assessment, not legal advice. See `POPIA_READINESS.md` for how it is used.', '',
           f'Repositories read: {len(invs)}. Source: ' + ', '.join(sorted({i["source"] for i in invs})) + '.', '',
           '## 1. Summary: personal-looking columns by category (columns; of which cast as `encrypted`)', '',
           '| Repository | Tables | ' + ' | '.join(lab[c].split(' (')[0] for c in cats) + ' |',
           '|---|---|' + '---|' * len(cats)]
    for i in invs:
        n, e = counts(i)
        out.append(f'| {i["repo"]} | {i["tables"]} | ' + ' | '.join((f'{n[c]} ({e[c]})' if n[c] else '-') for c in cats) + ' |')
    out += ['', 'The cast is only visible on the model; a column that is encrypted some other way, or an encrypted column the model does not cast, is not recognised.', '']
    out += ['## 2. Special personal information and children (POPIA s26, s34): column names that suggest it', '']
    any_special = False
    for i in invs:
        for t, cols in i['personal'].items():
            for col in cols:
                if col['category'] in ('special', 'children'):
                    any_special = True
                    out.append(f'- {i["repo"]}: `{t}.{col["column"]}` ({lab[col["category"]].split(" (")[0].lower()}; encrypted cast: {"yes" if col["encrypted_cast"] else "no"})')
    if not any_special:
        out.append('- none by column name')
    out += ['', '## 3. Financial and credential columns not cast as encrypted', '']
    rows = []
    for i in invs:
        for t, cols in i['personal'].items():
            for col in cols:
                if col['category'] in ('financial',) and not col['encrypted_cast']:
                    rows.append(f'- {i["repo"]}: `{t}.{col["column"]}` (financial)')
    out += rows or ['- none']
    out += ['', 'Credential columns (`password`, `remember_token`, API tokens) are listed per repository below; hashed or random values are expected there.', '',
            '## 4. Third-party processors visible in dependencies and configuration', '',
            '| Repository | Processors seen | Default mailer (`.env.example`) |', '|---|---|---|']
    for i in invs:
        out.append(f'| {i["repo"]} | ' + (', '.join(f'{p["name"]} ({p["kind"]}; {p["status"]})' for p in i['processors']) or '-') + f' | {i["mailer_default"] or "-"} |')
    out += ['', 'Whoever runs the production `.env` may use others (mail host, object storage). This table is only what the code and the example config show.', '',
            '## 5. External hosts that pages load from (the visitor\'s IP address and browser details reach them)', '',
            '| Host | Repositories (files that mention it) |', '|---|---|']
    hosts = {}
    for i in invs:
        for h, n in i['external_hosts'].items():
            hosts.setdefault(h, []).append(f'{i["repo"]} ({n})')
    for h, rs in sorted(hosts.items(), key=lambda kv: -len(kv[1])):
        out.append(f'| {h} | ' + ', '.join(rs) + ' |')
    if not hosts:
        out.append('| none | |')
    out += ['', 'A host listed here is not necessarily loaded on every page; it is a place to check. Hosts outside South Africa are a cross-border transfer question (POPIA s72).', '',
            '## 6. Retention and subject-rights signals (files that match; absence is a finding, presence is not proof it works)', '',
            '| Repository | ' + ' | '.join(v['label'] for v in invs[0]['signals'].values()) + ' |',
            '|---|' + '---|' * len(invs[0]['signals'])]
    for i in invs:
        out.append(f'| {i["repo"]} | ' + ' | '.join(str(v['files']) for v in i['signals'].values()) + ' |')
    out += ['', '## 7. Per repository: tables with personal-looking columns', '']
    for i in invs:
        out.append(f'### {i["repo"]}')
        out.append('')
        if not i['personal']:
            out += ['No personal-looking column names in the migrations.', '']
            continue
        out += ['| Table | Column | Category | Encrypted cast |', '|---|---|---|---|']
        for t, cols in i['personal'].items():
            for col in cols:
                out.append(f'| {t} | {col["column"]} | {lab[col["category"]].split(" (")[0]} | {"yes" if col["encrypted_cast"] else "no"} |')
        out.append('')
    return '\n'.join(out) + '\n'


def main(argv):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('repos', nargs='+')
    ap.add_argument('--ref', default='origin/main')
    ap.add_argument('--md')
    ap.add_argument('--json')
    a = ap.parse_args(argv)
    invs = []
    for r in a.repos:
        if not os.path.isdir(r):
            print(f'not a folder: {r}', file=sys.stderr)
            return 2
        invs.append(inventory(r, a.ref))
    invs.sort(key=lambda i: i['repo'])
    if a.json:
        json.dump(invs, open(a.json, 'w'), indent=1, sort_keys=True)
    md = render_md(invs)
    if a.md:
        open(a.md, 'w').write(md)
    else:
        sys.stdout.write(md)
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
