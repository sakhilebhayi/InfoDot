# Runbook — live Paystack verification (R-17 blocker; R-22 §3.2)

**Where the money actually moves:** Paystack lives in **Dot.Billing**. InfoDot never touches a
card — paid seminars and seat licensing call Billing's signed API (`BillingMarketplace`,
`config/licensing.php`: "Dot.Billing is the only place seats are bought and paid for"). So the
live verification is a Billing-first exercise, observed from InfoDot.

## 0. Preconditions (updated 2026-10-31 from the Billing code audit)

1. **Dot.Billing IS deployed and pipelined**: live at https://billing.infodot.co.za, with a manual
   deploy workflow in its repo (12 successful production deploys since 2026-10-18). The earlier
   "no pipeline" note came from a stale checkout.
2. **The code has NEVER touched real Paystack** (its own README says so; every test fakes HTTP).
   Two production-side switches gate a live run, both on the Billing host `.env`:
   - a live secret key, AND `PAYSTACK_ALLOW_LIVE=true` — `PaystackConfig` refuses an `sk_live_`
     key without it (a deliberate safety catch);
   - the webhook URL registered in the Paystack dashboard must be
     `https://billing.infodot.co.za/webhooks/paystack`.
3. The webhook endpoint verifies `X-Paystack-Signature` (HMAC-SHA512 over the raw body,
   constant-time compare) BEFORE parsing; bad signature → 401. Business anomalies (unknown
   reference, amount mismatch, replay, overpayment) return 200 and are HELD as PaymentEvents for
   review at `/operator/payment-events` — check that queue during the test.
4. The payer's return callback requires a signed-in session and verifies the transaction
   server-side with Paystack; the browser redirect proves nothing by itself (by design).
5. InfoDot-side: `licensing.billing.url` + secret set (the signed channel), and
   `seminars.paid.enabled` only if testing the seminar path. The Billing marketplace API is OFF by
   default (`BILLING_MARKETPLACE_ENABLED=false`) — seminar-ticket tests need it enabled and
   configured.
6. Deploys put Billing in maintenance mode briefly; a webhook arriving then gets 503 and Paystack
   retries — don't run the payment window during a deploy.

## 1. The controlled transaction (you, ~20 min, coordinated window)

Smallest practical amount (Paystack ZAR minimum). Two flows worth one pass each:

- **Seat licensing:** buy 1 extra seat for a test company through the normal UI.
- **Paid seminar (if enabled):** a R10-ticket seminar on a test host account; buy 1 ticket.

For each: complete checkout (success), then repeat and **cancel** at the Paystack page, and
once with a declining card if you have one (failure path).

## 2. What we verify server-side afterwards (the session does this with you)

- Paystack dashboard shows the transaction with the expected reference, amount, currency, and
  the webhook delivered (2xx).
- Billing recorded payment + subscription/entitlement exactly once; webhook signature
  (`X-Paystack-Signature`, HMAC-SHA512) was verified; a **re-delivered** webhook (Paystack
  dashboard → resend) does NOT create a duplicate payment or entitlement.
- InfoDot reflects the entitlement (seat count / registration goes paid) via the signed read —
  not via the browser redirect.
- Cancelled + failed attempts left NO entitlement and no paid record.
- Receipts/billing records reconcile: Paystack settlement amount = recorded amount − fees.

## 3. Close out

Refund the test transaction(s) from the Paystack dashboard if desired (record the refund
reference). Paste the transaction references + webhook delivery screenshots/IDs into the
session; the completion report gets the evidence and this blocker closes.

**Never:** fabricate a payment record, mark this verified from a sandbox run, or test with
another person's card.
