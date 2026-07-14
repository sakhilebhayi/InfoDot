# InfoDot Testing Strategy

## Purpose

This document defines how tests are written, organized, and maintained in the InfoDot codebase. The target is 70% coverage before MVP, with the highest priority on auth flows, ecosystem behavior, and content operations.

---

## Framework And Tooling

- **Test runner:** PHPUnit 11
- **Factory system:** Laravel model factories
- **HTTP testing:** Laravel's `TestCase` with `RefreshDatabase`
- **Livewire testing:** `Livewire\Livewire` testing facade (Livewire 3)
- **Static analysis:** PHPStan level 5+ (separate from testing)
- **Run all tests:** `php artisan test` or `./vendor/bin/phpunit`
- **Run a specific test:** `php artisan test --filter=SolutionTest`
- **Run a group:** `php artisan test --group=auth`

---

## Test Types And When To Use Each

### Feature tests

Located in `tests/Feature/`. These tests make HTTP requests or invoke Livewire components from the outside and assert on responses, database state, and dispatched events.

Use feature tests for:
- complete user flows end to end
- authentication and authorization paths
- Livewire component interactions
- API endpoints and their responses
- ecosystem token issuance and verification

Feature tests are the primary source of coverage confidence. Most work should be covered here.

### Unit tests

Located in `tests/Unit/`. These tests call a single class or method in isolation without booting the full framework.

Use unit tests for:
- pure computation inside domain helpers or value objects
- validation rules applied in isolation
- formatting or transformation logic
- coverage of edge cases in a single method that would be expensive to exercise through HTTP

Avoid writing unit tests for code that trivially delegates to Laravel or Eloquent methods — those are already tested by the framework.

---

## Priority Test Areas

These areas are required to have test coverage before the MVP release, in priority order:

1. **Authentication flows** — login, logout, registration, password reset, 2FA
2. **Ecosystem token flows** — issuance, handoff acceptance, expiry, revocation, double-use rejection
3. **Solution CRUD** — create, read, update, delete, authorization, validation
4. **Q&A flow** — ask question, add answer, accept answer, edit, delete, authorization
5. **Team invitations** — invite, accept, reject, team membership changes
6. **Search** — query returns correct results, empty state, Scout indexing
7. **File uploads** — upload, authorization, filename sanitization, MIME type validation
8. **Notifications** — notification created on expected events, user can dismiss

---

## Test Writing Conventions

### Structure each test with Arrange, Act, Assert

```php
public function test_user_can_create_a_solution(): void
{
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = $this->actingAs($user)->post('/solutions', [
        'solution_title'       => 'How to use Laravel Scout',
        'solution_description' => 'A full guide on Scout integration.',
        'tags'                 => ['laravel', 'search'],
    ]);

    // Assert
    $response->assertRedirect();
    $this->assertDatabaseHas('solutions', ['solution_title' => 'How to use Laravel Scout']);
}
```

### Name tests as complete sentences

Test method names must read as plain English sentences that describe the behavior being proven, not the implementation.

```php
// Good
public function test_guest_cannot_access_solution_editor(): void
public function test_team_member_can_view_shared_files(): void
public function test_expired_handoff_token_is_rejected(): void

// Bad
public function test_solution_403(): void
public function testHandoffToken(): void
```

### One assertion intent per test

Each test should verify one outcome. Multiple `assert*` calls are fine when they prove a single behavior from different angles, but do not mix unrelated assertions in one test method.

### Use factories for all test data

```php
// Good — describes what the state is, not how to create it
$user = User::factory()->withTeam()->create();
$solution = Solution::factory()->for($user)->create();

// Bad — manual inserts bypass factory states and validation
DB::table('users')->insert([...]);
```

Add factory states for common setups: `withTeam()`, `suspended()`, `withEcosystemToken()`.

### Use `RefreshDatabase`

All feature tests should use the `RefreshDatabase` trait to ensure test isolation:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class SolutionTest extends TestCase
{
    use RefreshDatabase;
}
```

---

## Testing Livewire 3 Components

Livewire 3 components are tested using the `Livewire` facade.

```php
use Livewire\Livewire;

public function test_search_returns_matching_solutions(): void
{
    Solution::factory()->create(['solution_title' => 'PostgreSQL migration guide']);
    Solution::factory()->create(['solution_title' => 'Vue to Alpine migration']);

    Livewire::test(Search::class)
        ->set('query', 'PostgreSQL')
        ->assertSee('PostgreSQL migration guide')
        ->assertDontSee('Vue to Alpine migration');
}

public function test_unauthenticated_user_cannot_submit_solution_form(): void
{
    Livewire::test(SolutionComposer::class)
        ->set('title', 'Test')
        ->call('submit')
        ->assertForbidden();
}
```

### Testing dispatched events and listeners

```php
Livewire::test(NotificationBell::class)
    ->dispatch('notification.received', ['id' => 1])
    ->assertSet('unreadCount', 1);
```

---

## Testing Ecosystem Token Flows

These are feature tests that cover the ecosystem handoff. They are critical and must not be skipped.

```php
public function test_valid_handoff_token_logs_user_in(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('handoff:files', ['handoff'], now()->addMinutes(5));

    $response = $this->get('/auth/ecosystem?token=' . $token->plainTextToken);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'handoff:files']);
}

public function test_expired_handoff_token_is_rejected(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('handoff:files', ['handoff'], now()->subMinute());

    $response = $this->get('/auth/ecosystem?token=' . $token->plainTextToken);

    $response->assertForbidden();
    $this->assertGuest();
}

public function test_handoff_token_cannot_be_used_twice(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('handoff:files', ['handoff'], now()->addMinutes(5));

    $this->get('/auth/ecosystem?token=' . $token->plainTextToken);
    $secondResponse = $this->get('/auth/ecosystem?token=' . $token->plainTextToken);

    $secondResponse->assertForbidden();
}
```

---

## Testing Authorization With Policies

Write explicit tests for every policy boundary. Do not assume authorization works because a controller uses `$this->authorize()`.

```php
public function test_non_owner_cannot_edit_solution(): void
{
    $owner   = User::factory()->create();
    $other   = User::factory()->create();
    $solution = Solution::factory()->for($owner)->create();

    $response = $this->actingAs($other)->put("/solutions/{$solution->id}", [
        'solution_title' => 'Changed',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('solutions', ['solution_title' => 'Changed']);
}
```

---

## Coverage Targets By Area

| Area | Target |
|---|---|
| Auth flows | 100% |
| Ecosystem token flows | 100% |
| Solution CRUD + policies | 90% |
| Q&A flow + policies | 90% |
| Team management | 85% |
| File uploads | 80% |
| Search | 70% |
| Notifications | 70% |
| Overall project | 70% |

---

## Running Static Analysis

PHPStan is separate from tests but runs in CI alongside them.

```bash
./vendor/bin/phpstan analyse --level=5
```

The `phpstan.neon` configuration should exclude vendor and generated files. PHPStan failures block the CI pipeline at the same priority as failing tests.

---

## CI Test Order

Recommended CI pipeline order:

1. `composer install --no-dev` validation
2. `php artisan config:cache` check
3. `./vendor/bin/phpstan analyse --level=5`
4. `php artisan test --parallel`
5. Coverage report generation

Tests must not communicate with external services in CI. Use fakes for Scout, Mail, Notifications, Queue, and Storage:

```php
protected function setUp(): void
{
    parent::setUp();
    Queue::fake();
    Mail::fake();
    Notification::fake();
    Storage::fake('s3');
}
```
