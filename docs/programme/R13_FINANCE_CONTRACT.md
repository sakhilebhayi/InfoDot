# R-13 seat purchasing: the contract Dot.Finance must implement (2026-10-29, payments moved to Dot.Finance 2026-11-13)

Paystack is the payment provider and Dot.Finance is where it lives (owner decision). InfoDot never takes a payment, never talks to Paystack and never trusts a checkout redirect. This page is what InfoDot expects of Dot.Finance: InfoDot's side is built and tested against it; whether Dot.Finance answers this way yet has **not been verified** from this repository.

## What Dot.Finance must provide

Paystack hosted checkout, a signature-verified webhook (HMAC-SHA512 over the raw body, idempotent settlement against its own payment attempt), invoices raised in advance per period, a subscription engine with `past_due` and per-invoice grace dates, and `GET /api/entitlements/{company_uuid}` signed per platform (`X-InfoDot-Platform`, `X-InfoDot-Timestamp`, `X-InfoDot-Signature`). The answer carries `status` and `seat_limit` (null means unlimited). The free tier's seat limit is `FINANCE_FREE_SEAT_LIMIT`, default 3.

## What InfoDot does (built)

`FinanceEntitlements` reads that endpoint (cached 5 minutes, 3 second timeout), before every seat check and when the Seats panel is shown, and stores the result as the company's licence: `purchased_seats = max(0, seat_limit - 3)`, `payment_status`, plan slug as `payment_ref`, and an audit entry whenever capacity changes. Any failure (not configured, unreachable, 401, unreadable) leaves the stored licence exactly as it was. Past due keeps the seats; nobody is ever removed. Switched on by `LICENCE_FINANCE_CONNECTED=true`, `LICENCE_FINANCE_URL`, `FINANCE_ENTITLEMENT_SECRET` (the value of `FINANCE_ENTITLEMENT_SECRET_INFODOT` in Dot.Finance's `.env`).

## What Dot.Finance needs before extra seats can be sold (waits on owner decisions)

Today a plan has one price and one `seat_limit`, so a company can only buy a whole plan. Selling extra seats needs:

1. A per-seat price on the plan, monthly and annual, **nullable so a plan without one sells no extra seats** (nothing invented), in the plan's currency.
2. `extra_seats` on the subscription, and an invoice line "Extra seats x n" in `InvoiceGenerator` using the price snapshot.
3. The entitlement answer reporting `seat_limit = plan seat limit + extra_seats`, so InfoDot needs no change.
4. A seat-change action with proration, and the invoice paid through the existing Paystack checkout, a seat becoming usable only once that invoice is settled by the verified webhook.

**Owner decisions that block 1 to 4:** the per-seat price and currency; monthly and annual or one only; proration when seats are added or removed mid-period (none until next renewal, daily pro rata, or pay the full period at once); whether removing seats refunds, credits or takes effect at renewal; and the grace policy after a failed payment (the subscription engine marks past due after the invoice grace date). Paystack subscriptions are card-based per its support page; whether bank transfer (EFT) is acceptable for seats is also a decision.

Until those exist the Seats panel says paid seats are not available yet, and no price is shown.
