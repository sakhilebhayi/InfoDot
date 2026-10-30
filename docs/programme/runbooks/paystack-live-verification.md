# Runbook — live Paystack verification (R-17 blocker; R-22 §3.2)

**Where the money actually moves:** Paystack lives in **Dot.Billing**. InfoDot never touches a
card — paid seminars and seat licensing call Billing's signed API (`BillingMarketplace`,
`config/licensing.php`: "Dot.Billing is the only place seats are bought and paid for"). So the
live verification is a Billing-first exercise, observed from InfoDot.

## 0. Preconditions (settle these first — they decide whether the test can run at all)

1. **Is Dot.Billing deployed to production, and where?** The repo has no deploy workflow, so
   deployment is manual/out-of-repo. If it is not live, this blocker converts to "deploy
   Dot.Billing first" and the test waits.
2. Billing's Paystack keys on that host: confirm they are **live** keys (a live test with test
   keys proves nothing) and that the webhook URL registered in the Paystack dashboard points at
   the live Billing host.
3. InfoDot production has `licensing.billing.url` + secret set (the signed channel), and
   `seminars.paid.enabled` if the seminar path is being tested.

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
