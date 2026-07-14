# InfoDot API Standards

## Purpose

InfoDot exposes a REST API consumed by the ecosystem's satellite Dot platforms and potentially third-party integrations. Consistent API design reduces integration friction and makes the hub easier to maintain as the number of connected platforms grows.

---

## Scope

These standards apply to:

- all routes under `routes/api.php`
- the ecosystem token endpoint (`POST /api/ecosystem/token`)
- any new internal API endpoints added during Phase 3 and beyond

Web routes returning Blade views follow different conventions and are not covered here.

---

## Authentication

All API endpoints require authentication via Laravel Sanctum.

**Request header:**

```
Authorization: Bearer {token}
```

Do not accept tokens via query string on authenticated API endpoints. Query string tokens are only acceptable for the one-time ecosystem handoff redirect, and that endpoint must immediately delete the token.

Endpoints that must remain unauthenticated must be documented explicitly with a justification comment in `routes/api.php`.

---

## URL Design

### Use nouns, not verbs

```
# Good
GET  /api/solutions
POST /api/solutions
GET  /api/solutions/{id}
PUT  /api/solutions/{id}
DELETE /api/solutions/{id}

# Bad
GET  /api/getSolutions
POST /api/createSolution
```

### Use kebab-case for multi-word resources

```
GET /api/team-members
GET /api/personal-access-tokens
```

### Nest related resources one level maximum

```
# Acceptable
GET /api/solutions/{id}/comments

# Too deep — flatten this
GET /api/solutions/{id}/comments/{commentId}/replies/{replyId}
```

### Use consistent resource naming across platforms

The following resource names are canonical across the InfoDot ecosystem. Satellite apps using these resources must match these names when referencing InfoDot data.

| Resource | Path segment |
|---|---|
| Users | `users` |
| Solutions | `solutions` |
| Questions | `questions` |
| Comments | `comments` |
| Teams | `teams` |
| Team members | `team-members` |
| Files | `files` |
| Notifications | `notifications` |
| Ecosystem tokens | `ecosystem/token` |

---

## Versioning

Prefix all API routes with a version segment:

```
/api/v1/solutions
/api/v1/users
/api/v1/ecosystem/token
```

- The current version is `v1`.
- Breaking changes require a new version.
- Non-breaking additions (new optional fields, new endpoints) do not require a new version.
- A version is supported for a minimum of 12 months after a successor version is released.
- Deprecation notices are communicated via a `Deprecation` response header on affected endpoints.

---

## Request Validation

All input must be validated at the `FormRequest` layer before reaching a controller or action.

- Return `422 Unprocessable Entity` for validation failures.
- Never return `400 Bad Request` for field-level validation errors.
- Validate all fields explicitly — do not use `$request->all()` without a validated subset.
- Return all field errors in a single response, not one at a time.

---

## Response Envelope

All API responses use a consistent JSON envelope.

### Success — single resource

```json
{
    "data": {
        "id": 42,
        "solution_title": "How to migrate to PostgreSQL",
        "created_at": "2026-07-14T10:00:00Z"
    }
}
```

### Success — collection

```json
{
    "data": [
        { "id": 1, "solution_title": "..." },
        { "id": 2, "solution_title": "..." }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 72
    },
    "links": {
        "first": "/api/v1/solutions?page=1",
        "last":  "/api/v1/solutions?page=5",
        "prev":  null,
        "next":  "/api/v1/solutions?page=2"
    }
}
```

### Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "solution_title": ["The solution title field is required."],
        "tags": ["The tags must be an array."]
    }
}
```

### Accepted (async operation queued)

```json
{
    "message": "Your request has been queued."
}
```

Return `202 Accepted` with this body for operations that are processed asynchronously.

---

## HTTP Status Codes

Use the correct status code for every response. Do not return `200 OK` for failures.

| Situation | Code |
|---|---|
| Successful read | 200 |
| Successful creation | 201 |
| Queued / async | 202 |
| No content (e.g. delete) | 204 |
| Validation failure | 422 |
| Unauthenticated | 401 |
| Authorized but forbidden | 403 |
| Resource not found | 404 |
| Method not allowed | 405 |
| Rate limit exceeded | 429 |
| Server error | 500 |

---

## Pagination

All collection endpoints must be paginated. Never return an unbounded list.

- Default page size: 15 items.
- Maximum page size: 100 items.
- Use `?page=2` for page-based pagination.
- Use `?per_page=30` for client-controlled page size (cap at 100).
- Include `meta` and `links` objects in all paginated responses (see envelope above).

Use Laravel's `paginate()` method. Use `CursorPaginate` for high-volume feeds where offset pagination would become expensive.

---

## API Resources

Use Laravel API Resources for all response serialization. Never return Eloquent models directly.

```php
// app/Http/Resources/SolutionResource.php
public function toArray(Request $request): array
{
    return [
        'id'                   => $this->id,
        'solution_title'       => $this->solution_title,
        'solution_description' => $this->solution_description,
        'tags'                 => $this->tags,
        'author'               => new UserResource($this->whenLoaded('user')),
        'created_at'           => $this->created_at->toIso8601String(),
        'updated_at'           => $this->updated_at->toIso8601String(),
    ];
}
```

Rules:
- Always use `$this->whenLoaded()` for relationships to avoid N+1 in the resource layer.
- Use ISO 8601 format for all timestamps.
- Do not expose sensitive fields: passwords, token hashes, internal flags.

---

## Rate Limiting

| Endpoint group | Limit |
|---|---|
| General API (`/api/*`) | 60 requests per minute per user |
| Ecosystem token issuance | 10 requests per minute per IP |
| Search | 30 requests per minute per user |
| File upload | 20 requests per minute per user |

Return `429 Too Many Requests` with a `Retry-After` header when the limit is exceeded.

---

## Ecosystem Token Endpoint

```
POST /api/v1/ecosystem/token
```

**Request:**

```json
{
    "platform": "files",
    "type": "handoff"
}
```

**Response (201):**

```json
{
    "data": {
        "token": "plaintext-token-here",
        "expires_at": "2026-07-14T10:05:00Z",
        "platform": "files"
    }
}
```

**Rules:**
- Requires an authenticated session (not an API token — this is a web session action).
- Only `handoff` type tokens may be issued this way.
- The plaintext token is shown once. It is never retrievable again.
- The endpoint is rate-limited.
- The response must not be cached.

---

## Error Handling

Implement a consistent `Handler` that maps exceptions to API responses.

```php
// app/Exceptions/Handler.php
$this->renderable(function (ModelNotFoundException $e, Request $request) {
    if ($request->expectsJson()) {
        return response()->json(['message' => 'Resource not found.'], 404);
    }
});

$this->renderable(function (AuthorizationException $e, Request $request) {
    if ($request->expectsJson()) {
        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }
});
```

Never expose stack traces, file paths, or internal exception messages to API consumers.

---

## Definition Of Done For New API Endpoints

- [ ] Route is versioned under `/api/v1/`
- [ ] Authentication is enforced or explicitly documented as public
- [ ] Input is validated via a `FormRequest`
- [ ] Response uses an API Resource or a documented envelope
- [ ] Status codes match the table above
- [ ] Rate limiting is applied
- [ ] A feature test covers the happy path and at least one error case
- [ ] The endpoint is documented in this guide or linked from it
