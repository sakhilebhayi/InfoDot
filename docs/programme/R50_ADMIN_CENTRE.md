# R-50 step 3: the platform admin centre (operator guide and payment confirmation contract)

Written 2026-11-14. The specification is `docs/programme/r50/ARCHITECTURE.md` (sections 2, 4, 6, 9 and 11). This page is
for two readers: the person who operates the admin centre, and the Dot.Finance team, who implement the confirmation
call in the second half.

## 1. The platform administrator

A platform administrator manages the catalogue, plans and prices, bundles, and each company's access. It is a role of
its own. A moderator has none of these powers, and neither does the owner of a company.

The role is given and taken away only from a shell on the host:

```
php artisan platform-admin:grant  person@example.com
php artisan platform-admin:revoke person@example.com
```

- The account must exist, must have verified its email address and must not be suspended. Otherwise the command
  refuses and changes nothing.
- Each grant and revocation is written to the audit trail (`platform_catalogue_events`, subject `administrator`).
- There is no screen, form, API call or setting that grants the role. The service behind the commands refuses to run
  inside a web request.
- A suspended administrator loses access at once; the role itself stays on the account until it is revoked.

Everything below is at `/admin/platforms` and is behind the `administer-platforms` gate. Anyone else who is signed in
gets a 403; a signed-out visitor is sent to sign in. Administrators see a "Platform admin" tab in the top bar.

## 2. What each action does

Every change needs a written reason of 10 to 255 characters. The reason, the administrator and the time are kept and
cannot be edited afterwards.

### Catalogue

| Action | Effect |
|---|---|
| Set "Working" | `operational` and `degraded` let people in. `maintenance` and `unavailable` show businesses "temporarily unavailable". `development` and `not_launched` show "not launched". Nobody loses an entitlement. |
| Shown to businesses | Off hides the platform from the Platforms catalogue and refuses launches. A core platform cannot be hidden. |
| Description fields | The words businesses read about the platform. |
| Core | Read-only. The core set (InfoDot, Dot.Files, Dot.docs, Dot.Tasks, Dot.Finance) is the owner's decision. |

A platform that is missing from the list is added by `php artisan platforms:sync` on the host.

### Plans and prices

The application ships with no plans and no amounts. A platform with no plan on sale is "free while pricing is being
finalised": a company can switch it on at no charge.

1. **Add a plan** on the platform's page: name, kind, amount in minor units (cents: 19900 is 199.00), currency code,
   interval, optional trial days. The amount and currency may be left empty while the price is undecided. No value is
   suggested.
2. **Approve the price** on the plan's page. The page shows the exact amount; you tick a box and write who decided it.
   Without this step the plan cannot be sold.
3. **Switch the plan on.** A plan is on sale only when it is switched on and its price is approved.

Changing the amount, currency, interval or kind withdraws the approval, and the plan goes off sale until the new price
is approved. You can also withdraw an approval directly.

A price change never alters what a company already holds. Its entitlement, dates and standing stay as they are, and
renewals for the plan it holds keep being accepted. A new price applies only to what is agreed after it is approved.

### Bundles

A bundle is a named set of at least two platforms sold at one price. The same rule applies: create, approve the
price, switch on. Changing the price or the platforms inside withdraws the approval. Core platforms and platforms
with their own sign-in cannot be in a bundle.

### Companies

Find a company by name or owner code. Its page lists what it holds and the full history.

| Action | Effect |
|---|---|
| Grant complimentary access | The company holds the platform at no charge, with an optional last day. On that day it expires by itself. |
| Start a trial | A trial of 1 to 90 days, or of the length a chosen plan offers. When it ends: `payment_required` if a priced plan was chosen, otherwise `expired`. |
| Suspend | Nobody in the company can open the platform. The company cannot switch it back on. A payment does not lift a suspension. |
| Restore | The platform returns to the standing it had when it was suspended. |
| Cancel | Ends the company's use. Time already paid for is honoured to the end of the period; anything else ends now. Nothing is deleted. |
| Who may use it | Everyone in the company, or selected people (owners and administrators always). |

Each action writes one event with the administrator and the reason, and queues one message to the platform. An
administrator cannot mark anything paid: only the confirmation in section 4 can.

### Deliveries

The outbox that tells each platform about an entitlement change. "Waiting" is retried automatically with growing
delays. "Parked" means the platform refused the event or delivery was given up; "Retry now" puts it back in the queue,
and the retry is audited. Platforms also read the entitlement when a person signs in, so a late delivery does not by
itself open or close anything.

### Confirmed payments

Lists and totals only what Dot.Finance has confirmed with a signed message. It is empty until the first confirmation.
Complimentary access and trials are never counted.

### Audit trail

Every catalogue, plan, bundle, administrator and retry entry. A company's own history is on its page.

## 3. What happens on its own

`php artisan platforms:lifecycle` runs every minute from the scheduler (no queue worker is needed):

- a trial past its end becomes `payment_required` (priced plan chosen) or `expired`;
- a dated complimentary grant past its last day becomes `expired`;
- a failed payment past its grace date becomes `expired`;
- a cancelled subscription past its paid period is closed.

Each change is made once, with its event and its message to the platform, however often the command runs.

`php artisan ops:check` also reports three counts that should always be zero:

- `paid_entitlement_without_confirmation`: an entitlement marked paid with no confirmation from Dot.Finance;
- `plan_on_sale_without_approval_record`: a plan or bundle marked approved with no approval in the audit trail;
- `entitlements_overdue_1h`: a trial or dated grant still open an hour after its end (the scheduler is not running).

## 4. The payment confirmation contract (for Dot.Finance)

InfoDot never takes a payment and never trusts a browser returning from a checkout. A platform becomes paid only when
Dot.Finance calls this endpoint after the payment provider has confirmed the money to Dot.Finance.

```
POST {infodot}/api/finance/platform-subscription-events
Content-Type: application/json
X-InfoDot-Timestamp: <unix seconds>
X-InfoDot-Signature: sha256=<hex HMAC-SHA256 of "{timestamp}.{raw body}" with the shared secret>
```

- The scheme is the same as `POST /api/finance/ticket-events`, with **its own secret**:
  `PLATFORM_SUBSCRIPTION_CALLBACK_SECRET` in InfoDot's environment. Use a value of at least 32 random bytes, different
  from every other shared secret.
- The timestamp must be within 300 seconds of InfoDot's clock. Sign the exact bytes that are sent.
- While the secret is not set on InfoDot, every request is refused. There is no test mode on InfoDot's side: build
  against a non-production InfoDot with its own secret.
- Rate limit: 300 requests a minute per address.

### Body

| Field | Type | Required | Meaning |
|---|---|---|---|
| `event_id` | string, up to 100, letters digits `. _ : -` | always | Unique per event. A repeat is answered `duplicate` and not applied again. |
| `event` | `paid`, `renewed`, `payment_failed`, `refunded`, `cancelled` | always | |
| `company_uuid` | string | always | The company's InfoDot uuid (`infodot_team_uuid`). |
| `platform` | string | always | The platform key, for example `hr`. |
| `plan` | string | `paid`, `renewed` (unless `bundle`) | The plan slug shown in the admin centre. |
| `bundle` | string | optional | The bundle slug. Send one event per platform in the bundle, each with its own `event_id`. |
| `amount_minor` | integer | `paid`, `renewed` (1 or more), `refunded` (0 or more) | What was received or returned, in minor units. |
| `currency` | three capital letters | with `amount_minor` | |
| `reference` | string, up to 120 | always | Dot.Finance's subscription reference. |
| `period_end` | ISO 8601 date-time | `paid`, `renewed` (in the future); optional on `cancelled` | The end of the period the payment covers. |
| `grace_until` | ISO 8601 date-time | optional on `payment_failed` | When access should stop. Capped at 30 days. Without it InfoDot uses `PLATFORM_PAYMENT_GRACE_DAYS` (7). |
| `occurred_at` | ISO 8601 date-time | always | When it happened at Dot.Finance. An event older than one already applied for the same company and platform is stored and answered `ignored`. |

Example:

```json
{
  "event_id": "fin-sub-000123-paid",
  "event": "paid",
  "company_uuid": "0b8f6c0e-1111-4222-8333-444455556666",
  "platform": "hr",
  "plan": "team",
  "amount_minor": 24900,
  "currency": "ZAR",
  "reference": "SUB-000123",
  "period_end": "2026-12-14T10:00:00+02:00",
  "occurred_at": "2026-11-14T10:00:03+02:00"
}
```

### What each event does

| Event | Result in InfoDot |
|---|---|
| `paid` | `active`, source `paid`, plan and reference stored, period set. The plan (or bundle) must be on sale. |
| `renewed` | Period extended, `active`. Accepted for the plan the company already holds even if its price has since changed. A different plan is recorded as a plan change. |
| `payment_failed` | `payment_required` with a grace date; the company keeps the platform until then, after which it expires. A second failure does not move the date. |
| `refunded` | `cancelled`, access ends at once. |
| `cancelled` | `cancelled`; access continues to `period_end` (or the period already stored). |

`payment_failed`, `refunded` and `cancelled` are refused unless the company holds a paid entitlement for that platform.
A suspended platform stays suspended whatever arrives; the payment facts are still recorded.

### Answers

| Status | Body | Meaning | What Dot.Finance should do |
|---|---|---|---|
| 200 | `{"status":"applied"}` | Stored and applied. | Done. |
| 200 | `{"status":"duplicate"}` | This `event_id` was already received. | Done. |
| 200 | `{"status":"ignored"}` | Stored; it changed nothing (older than an applied event, or a repeated failure). | Done. |
| 400 | `{"error":"bad_request"}` | The body is not a well-formed event. | Fix the sender. Do not retry unchanged. |
| 401 | `{"error":"invalid_request"}` | Signature, timestamp or secret. | Check the secret and the clock, then retry. |
| 422 | `{"error":"unprocessable"}` | The company, platform, plan or bundle is unknown or does not fit. InfoDot does not say which; the reason is in InfoDot's log beside the `event_id`. Nothing was stored. | Raise it with the InfoDot operator, then resend the same event. |
| 429 | | Too many requests. | Retry later. |
| 5xx | | InfoDot failed. | Retry with growing delays; the call is idempotent by `event_id`. |

### What Dot.Finance reads from InfoDot: `GET /api/finance/platform-plans`

The plans and bundles Dot.Finance may sell, so it never needs them typed in by hand. Signed in the scheme above, with the
same secret, but a GET signs the empty body: `X-InfoDot-Signature` is `sha256=` plus the HMAC-SHA256 of `{timestamp}.`
and `X-InfoDot-Timestamp` is the same unix time, accepted within five minutes. With the secret unset every request is
refused (401, the same answer for every fault).

Only what is on sale is returned: active, approved by a platform administrator, with an amount and a currency. Plans that
cost nothing are not listed (nothing is bought, and a payment for one would be refused). Nothing about any company or
person. Amounts are minor units.

```json
{
  "plans": [
    { "slug": "team", "platform": "hr", "name": "Team", "amount_minor": 24900, "currency": "ZAR", "interval": "month", "trial_days": 14 }
  ],
  "bundles": [
    { "slug": "office-pair", "name": "Office pair", "amount_minor": 39900, "currency": "ZAR", "interval": "month", "platforms": [ { "platform": "forms", "plan": null }, { "platform": "projects", "plan": null } ] }
  ]
}
```

The example is the shape only. The application ships no plan, so the list is empty until an administrator approves one.

## 5. What is not built in this step

- The owner's checkout. `Entitlements::subscribe()` records that an owner asked for a paid plan (`pending`), but no
  page calls it yet and nothing sends the company to Dot.Finance, because Dot.Finance cannot take the payment yet.
- In-app notices to a company's owner when an administrator changes its access.
