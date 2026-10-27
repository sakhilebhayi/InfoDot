#!/usr/bin/env bash
# Fixture test of tools/popia-inventory.py: a small fake Laravel app (and one nested like Dot.Charts), no network, no git needed.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/tools/popia-inventory.py"
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
FAIL=0; N=0
ok() { N=$((N + 1)); echo "ok   - $1"; }
bad() { N=$((N + 1)); echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; printf '%s\n' "$OUT" | head -n 12 | sed 's/^/       | /'; fi; }

mk() { # dir
  mkdir -p "$1/database/migrations" "$1/app/Models" "$1/app/Services" "$1/resources/views" "$1/routes" "$1/config"
  : > "$1/artisan"
  cat > "$1/database/migrations/2026_01_01_000000_create_people.php" <<'PHP'
<?php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('email');
            $table->string('phone')->nullable();
            $table->string('id_number')->nullable();
            $table->decimal('salary', 10, 2);
            $table->text('medical_notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('email_hash', 64)->nullable();
            $table->boolean('is_active');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('engine_health');
            $table->integer('health_score');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nickname')->nullable();
            $table->dropColumn('nickname');
        });
    }
};
PHP
  cat > "$1/app/Models/Employee.php" <<'PHP'
<?php
class Employee extends Model {
    protected function casts(): array { return ['email' => 'encrypted', 'salary' => 'encrypted', 'phone' => 'encrypted:string', 'id_number' => \App\Casts\SensitiveText::class]; }
}
PHP
  cat > "$1/composer.json" <<'JSON'
{"require":{"php":"^8.3","laravel/framework":"^13.0","stripe/stripe-php":"^15"},"require-dev":{"sentry/sentry-laravel":"^4"}}
JSON
  printf 'MAIL_MAILER=log\n# PAYSTACK_SECRET_KEY=\nANTHROPIC_API_KEY=\nAWS_ACCESS_KEY_ID=\nAWS_BUCKET=\n' > "$1/.env.example"
  printf '<link href="https://fonts.googleapis.com/css2?family=Inter">\n<a href="https://infodot.co.za/x">own</a>\n' > "$1/resources/views/layout.blade.php"
  printf '<h1>Privacy</h1>\n' > "$1/resources/views/privacy.blade.php"
  printf "<?php\nRoute::get('/export', fn() => 1);\n" > "$1/routes/web.php"
}
mk "$W/Dot.Fixture"
mkdir -p "$W/Dot.Nested"; mk "$W/Dot.Nested/backend"

OUT="$(python3 "$TOOL" "$W/Dot.Fixture" "$W/Dot.Nested" 2>&1)"; RC=$?
check "runs and exits 0" "[ $RC -eq 0 ]"
check "reads both repos (the nested one through its backend/ folder)" "printf '%s\n' \"\$OUT\" | grep -q 'Repositories read: 2' && printf '%s\n' \"\$OUT\" | grep -qE '^\| Dot.Nested \| 2 \|'"
check "special personal information column found (medical_notes), not cast encrypted" "printf '%s\n' \"\$OUT\" | grep -q 'Dot.Fixture: \`employees.medical_notes\` (special personal information; encrypted cast: no)'"
check "machine health metrics are not special personal information" "! printf '%s\n' \"\$OUT\" | grep -q 'machines.engine_health' && ! printf '%s\n' \"\$OUT\" | grep -q 'machines.health_score'"
check "encrypted casts recognised (email, salary, phone)" "printf '%s\n' \"\$OUT\" | grep -E '^\| employees \| email \|' | grep -q '| yes |' && printf '%s\n' \"\$OUT\" | grep -E '^\| employees \| salary \|' | grep -q '| yes |'"
check "a custom Sensitive* cast counts as encrypted (id_number)" "printf '%s\n' \"\$OUT\" | grep -qE '^\| employees \| id_number \| Identity \| yes \|'"
check "hash, flag and timestamp columns are not personal data" "! printf '%s\n' \"\$OUT\" | grep -qE '^\| employees \| (email_hash|is_active|created_at|deleted_at) '"
check "a dropped column is forgotten" "! printf '%s\n' \"\$OUT\" | grep -q 'nickname'"
check "stripe is a dependency; sentry in require-dev is not read" "printf '%s\n' \"\$OUT\" | grep -E '^\| Dot.Fixture \|' | grep -q 'Stripe (payments; dependency)' && ! printf '%s\n' \"\$OUT\" | grep -E '^\| Dot.Fixture \|' | grep -q 'Sentry'"
check "a commented-out key is ignored, a set key is config only" "! printf '%s\n' \"\$OUT\" | grep -q 'Paystack' && printf '%s\n' \"\$OUT\" | grep -q 'Anthropic (AI API; config only)'"
check "blank AWS keys do not make AWS a processor" "! printf '%s\n' \"\$OUT\" | grep -q 'Amazon SES'"
check "external font host listed, own host not" "printf '%s\n' \"\$OUT\" | grep -q '^| fonts.googleapis.com |' && ! printf '%s\n' \"\$OUT\" | grep -q '^| infodot.co.za |'"
check "signals: soft deletes, privacy page, export route found" "printf '%s\n' \"\$OUT\" | grep -qE '^\| Dot.Fixture \| 1 \| 0 \| 0 \| 1 \| 0 \| 1 \| 0 \|'"
check "no value from .env.example reaches the output" "! printf '%s\n' \"\$OUT\" | grep -q 'MAIL_MAILER=log'"
python3 "$TOOL" "$W/missing" >/dev/null 2>&1; check "a missing folder is rejected (exit 2)" "[ \$? -eq 2 ]"
echo; echo "$N checks, $( [ "$FAIL" = 0 ] && echo 'all passed' || echo 'SOME FAILED' )"
exit "$FAIL"
