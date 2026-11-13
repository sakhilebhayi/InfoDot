# Runbook — live Paystack verification (R-17 blocker; R-22 §3.2)

**Where the money actually moves:** Paystack lives in **Dot.Finance**. InfoDot never touches a
card — paid seminars and seat licensing call Dot.Finance's signed API (`FinanceMarketplace`,
`config/licensing.php`: "Dot.Finance is the only place seats are bought and paid for"). So the
live verification is a Dot.Finance-first exercise, observed from InfoDot.

## 0. Preconditions (rewritten 2026-11-13: payments moved to Dot.Finance)

None of these has been checked against Dot.Finance from this repository. Confirm each one there
before the window; `docs/programme/R13_FINANCE_CONTRACT.md` is what InfoDot expects of it.

1. **Dot.Finance takes payments in production**: the Paystack checkout, the Paystack webhook and
   the signed API InfoDot calls are deployed, and you know its Paystack webhook URL.
2. **A live key is a deliberate act.** No real Paystack call has been made on InfoDot's behalf (every
   InfoDot test fakes HTTP). Confirm how Dot.Finance guards a live secret key (a switch that refuses an
   `sk_live_` key until it is turned on is the safety catch to look for), and register its webhook
   URL in the Paystack dashboard.
3. The webhook must verify `X-Paystack-Signature` (HMAC-SHA512 over the raw body, constant-time
   compare) BEFORE parsing; bad signature → 401. Business anomalies (unknown reference, amount
   mismatch, replay, overpayment) must be held for review, not applied: find where Dot.Finance
   lists them and check that queue during the test.
4. The payer's return must be verified server-side with Paystack; the browser redirect proves
   nothing by itself (by design).
5. InfoDot-side: `licensing.finance.url` + secret set (the signed channel), and
   `seminars.paid.enabled` only if testing the seminar path. Seminar-ticket tests also need the
   marketplace API switched on in Dot.Finance (`FINANCE_MARKETPLACE_ENABLED`).
6. A deploy puts an app in maintenance mode briefly; a webhook arriving then gets 503 and Paystack
   retries — don't run the payment window during a deploy of Dot.Finance.

## 1. The controlled transaction (you, ~20 min, coordinated window)

Smallest practical amount (Paystack ZAR minimum). Two flows worth one pass each:

- **Seat licensing:** buy 1 extra seat for a test company through the normal UI.
- **Paid seminar (if enabled):** a R10-ticket seminar on a test host account; buy 1 ticket.

For each: complete checkout (success), then repeat and **cancel** at the Paystack page, and
once with a declining card if you have one (failure path).

## 2. What we verify server-side afterwards (the session does this with you)

- Paystack dashboard shows the transaction with the expected reference, amount, currency, and
  the webhook delivered (2xx).
- Dot.Finance recorded payment + subscription/entitlement exactly once; webhook signature
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
