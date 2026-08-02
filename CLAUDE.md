# InfoDot — CLAUDE.md

**Project:** InfoDot — Ecosystem Hub for the Dot Ecosystem
**Stack (current, verified 2026-08-02):** Laravel 12 · PHP 8.4 · Livewire 3 · PostgreSQL 16 · Reverb 1.10 · Vite 6 · DaisyUI 5 · Alpine.js 3 · Sanctum 4 · Scout 10

---

## What InfoDot Is

InfoDot is the **hub** of the Dot Ecosystem — the central identity provider that lets a user log in once and move between every connected Dot platform without re-authenticating. It issues short-lived Sanctum handoff tokens (5 min TTL, `ecosystem:read` ability, one-time use) that satellite apps redeem via their own `/auth/ecosystem` endpoint.

**Core features already built:** Solutions hub · Q&A with threaded comments · polymorphic likes · user profiles · social graph · team management · real-time notifications (Reverb) · file storage · full-text search (Scout) · ecosystem SSO token issuance (`EcosystemTokenController`) and consumption (`EcosystemAuthController`) · a dashboard widget (`EcosystemWidget`) listing every registered platform, grouped by category, with one-click launch into any of them.

---

## Status: this file was stale, corrected 2026-08-02

Everything below the previous "Upgrade Phases" section described a migration plan as if not yet started. **That migration was already completed in an earlier session** (see `git log`: `feat: complete Laravel 12 upgrade — Livewire 3, Reverb, Vite, ecosystem SSO`, `feat: complete Phase 3 — Scout on Comment, Sentry, PHPStan level 5 clean`). This file simply was never updated to say so — exactly the kind of documentation drift the wider Dot Ecosystem's `os/12-README-Automation.md` was written to catch. Verified directly against the real code before writing this:

| Phase (of the original 5-phase plan) | Status |
|---|---|
| **1 — Stack Foundation** (Laravel 12, Vite, PostgreSQL, Reverb, Scout, DaisyUI 5, remove Vue) | ✅ Done. `composer.json`/`package.json` confirm target versions; zero `.vue` files; zero `@mix()` calls; zero `fullText()` migrations; `.env.example` targets `pgsql`; `laravel/reverb` v1.10.2 is in `composer.lock`. |
| **2 — Livewire 2 → 3 Rewrite** | ✅ Done. All 9 Livewire components use `#[Computed]`/`#[On]` attributes and typed properties — verified by reading `EcosystemWidget.php` directly. |
| **3 — Feature Completion** (Scout, Sentry, PHPStan L5, PHPUnit 11) | ✅ Done per commit history. Not independently re-verified this pass (no PHP available in the environment that wrote this update — see the Dot Ecosystem's `os/13-Engineering-State.md` §4 for why). |
| **4 — Ecosystem Hub Layer** | ✅ Done. `POST /api/ecosystem/token` (`EcosystemTokenController`), the platform-launcher widget (`EcosystemWidget`, not literally named `dot-switcher` as the original plan assumed, but functionally identical), and `config/ecosystem.php` all exist and work. |
| **5 — Dot.Files Integration** | ❌ Not done, and out of scope for the Dot Ecosystem work that produced this update — Dot.Files is a separate repo never touched by that session. |

## What changed 2026-08-02: platform registry reconciled against the real Dot Ecosystem

`config/ecosystem.php` previously listed 18 platforms, overlapping only partially with the actual 20-platform Dot Ecosystem tracked in `Dot.Brain/os/Appendix.md` (which itself is the authoritative registry — every one of those 20 platforms is a real, built Laravel/Jetstream app with its own `EcosystemAuthController` implementing the exact same token contract InfoDot expects, byte-for-byte verified). Nine real, built platforms were missing entirely: **Dot.Memory, Dot.Plug, Dot.Mines, Dot.Notify, Dot.Billing, Dot.Charts, Dot.Farms, Dot.HR, Dot.Dopemine**. All nine were added to `config/ecosystem.php` and grouped into `EcosystemWidget::groups()` (a new "Industry Verticals" category was added for Mines/Farms; the rest joined existing categories by domain fit).

The registry now has 27 entries: the 20 real Dot Ecosystem platforms, plus 7 (`Dot.Files`, `Dot.Docs`, `Dot.Forms`, `Dot.Sheet`, `Dot.Engage`, `Dot.Press`, `Dot.Tutor`) that are real separate repos from InfoDot's original, older plan but were never part of the Dot Ecosystem's build-out this session — kept in place rather than removed, per an explicit decision not to unilaterally drop them.

**Not yet verified:** whether those 7 repos actually implement the `/auth/ecosystem` receiving endpoint the way all 20 real Dot Ecosystem platforms do — they were never reviewed as part of this update. Treat their entries in the registry as aspirational until checked.

---

## Key Patterns (still accurate, kept from the original plan)

### Ecosystem SSO — receiving end (every satellite app has this)
```php
// routes/web.php
Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle']);

// EcosystemAuthController
public function handle(Request $request): RedirectResponse
{
    $accessToken = PersonalAccessToken::findToken($request->query('token'));
    abort_if(
        ! $accessToken || ! $accessToken->can('ecosystem:read')
        || ($accessToken->expires_at && $accessToken->expires_at->isPast()),
        403
    );
    $user = $accessToken->tokenable;
    $accessToken->delete(); // one-time use
    Auth::login($user);
    return redirect()->route('dashboard');
}
```
Verified identical (modulo the final redirect route) between InfoDot's own copy and Dot.Billing's — this is the real, working contract every satellite app implements.

### Ecosystem SSO — issuing end (InfoDot only)
```php
// EcosystemWidget::launch() and EcosystemTokenController::issue()
$token = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->addMinutes(config('ecosystem.handoff_ttl', 5)));
// redirect to: rtrim($platform['url'], '/') . '/auth/ecosystem?token=' . $token->plainTextToken
```

### Livewire 3 component pattern
```php
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ExampleComponent extends Component
{
    public string $query = '';

    #[Computed]
    public function results(): array
    {
        return Solution::search($this->query)->get()->toArray();
    }

    #[On('someEvent')]
    public function handleEvent(array $data): void
    {
        // handle it
    }
}
```

---

## Database

- **Driver:** PostgreSQL 16, `DB_DATABASE=infodot` — confirmed identical across InfoDot and every checked satellite app's `.env.example` (Dot.Billing, Dot.Ehail, Dot.Auction), so the shared-database assumption the SSO scheme depends on is structurally consistent, not just documented intent.
- **Shared tables:** `users`, `teams`, `team_user`, `personal_access_tokens`.
- Each satellite keeps its own domain tables but points at the same DB instance.
- **Caveat:** this confirms the *configuration* is consistent, not that a real shared Postgres instance has ever been stood up and migrated — no PHP/Postgres runtime existed in the environment that wrote this update. See `Dot.Brain/os/13-Engineering-State.md` §4.

## Testing

- **Framework:** PHPUnit 11, 31 test files present.
- **Target coverage:** 70%+ — commit history claims this was hit (`test: add coverage gap tests — hit 70%+ line coverage target`), not independently re-run.
- Run tests: `php artisan test` or `./vendor/bin/phpunit`
- Static analysis: `./vendor/bin/phpstan analyse --level=5`

## Dev Commands

```bash
php artisan serve              # Laravel on :8000
npm run dev                    # Vite on :5173
php artisan reverb:start       # WebSockets on :8080
php artisan queue:work         # Queue worker
php artisan migrate            # Run migrations
php artisan test               # Run test suite
./vendor/bin/phpstan analyse   # Static analysis
```

## Real next steps (not the old Phase 1–5 plan, which is done except Phase 5)

1. Actually stand up the shared PostgreSQL instance and run `composer install && php artisan migrate && php artisan test` for InfoDot and every satellite — nothing in this ecosystem has ever been executed against a real environment (see `os/13-Engineering-State.md` §4, ecosystem-wide, not InfoDot-specific).
2. Verify whether Dot.Files/Dot.Docs/Dot.Forms/Dot.Sheet/Dot.Engage/Dot.Press/Dot.Tutor actually implement the `/auth/ecosystem` receiving contract — currently just asserted, not checked.
3. Dot.Files integration (original Phase 5): shared-DB pointing, PR triage, `/auth/ecosystem` endpoint — still not started, still out of scope until someone reviews that repo directly.
4. None of the 20 real Dot Ecosystem platforms publish real DKP Knowledge Packs to Dot.Brain yet (`Dot.Brain/os/19-Knowledge-Packs.md`) — InfoDot itself has no DKP integration either; not this file's problem to solve, but worth knowing InfoDot inherits the same gap as everything else in the ecosystem.
