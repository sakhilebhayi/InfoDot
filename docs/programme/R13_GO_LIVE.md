# R-13 go-live: what to set, in what order (2026-10-29)

Nothing below is a secret value; generate each secret yourself (`php -r 'echo bin2hex(random_bytes(32));'`) and never paste one into chat, a pull request or an issue. These lines go in the production `.env` of each app on the host; the deploy never overwrites `.env`.

## 1. Reputation (InfoDot only, no dependencies)

InfoDot `.env`:

    REPUTATION_ENABLED=true

then `php artisan config:cache` (the deploy does this on its next run). The nightly `reputation:recalculate` and the "Your progress" menu entry start working. Each person can switch their own score off from that page.

## 2. The Billing link (seat counts)

Needs Dot.Billing deployed first (its release is manual: Actions, "Deploy to Production", `confirm=deploy`).

1. Billing `.env`: `BILLING_ENTITLEMENT_SECRET_INFODOT=<secret A>`.
2. InfoDot `.env`:

       LICENCE_BILLING_CONNECTED=true
       LICENCE_BILLING_URL=https://billing.infodot.co.za
       BILLING_ENTITLEMENT_SECRET=<secret A, the same value>

3. Check: open a company's team settings, the Seats panel loads, and `storage/logs` has no "Billing entitlements" warning. Until Billing has an active subscription with extra seats for a company, nothing changes: every company still has its 3 free seats.

## 3. Paid seminars (Paystack, host sets the price, InfoDot keeps 10% of each ticket)

Needs step 2's secret A, the Paystack account live in Dot.Billing, and the Dot.Billing marketplace PR deployed.

1. Billing `.env`:

       BILLING_MARKETPLACE_ENABLED=true
       BILLING_MARKETPLACE_PLATFORM_PERCENT=10
       BILLING_MARKETPLACE_CALLBACK_URL=https://infodot.co.za/api/billing/ticket-events
       BILLING_MARKETPLACE_CALLBACK_SECRET=<secret B>
       BILLING_MARKETPLACE_RETURN_HOSTS=infodot.co.za

2. InfoDot `.env`:

       SEMINARS_PAID_ENABLED=true
       SEMINARS_BILLING_CALLBACK_SECRET=<secret B, the same value>

3. Tax and invoices (InfoDot is VAT-registered): InfoDot `.env`

       SEMINARS_ISSUER_NAME="<InfoDot's registered legal name>"
       SEMINARS_ISSUER_ADDRESS="<registered address>"
       SEMINARS_ISSUER_VAT_NUMBER=<10 digits>
       SEMINARS_ISSUE_TAX_INVOICES=false      # keep false until an adviser has approved the receipt wording

   Commission invoices are issued on the 1st of each month at 04:15 for the month before, and only while those three are set. `SEMINARS_COMMISSION_INCLUDES_VAT=true` (default) takes the VAT out of the 10% instead of adding it. Refund window: `SEMINARS_REFUND_HOURS_BEFORE=24`.

4. Try it with one small real ticket you buy yourself from a seminar you host from a second account, then refund it (cancel the seminar), and check the Paystack dashboard shows the split and the refund before telling anyone.

## What is not decided and is not built

Refunds when an attendee cancels (today: none; the page says so before they pay), VAT or tax invoices for the 10% fee and for hosts, who is the merchant of record, chargebacks, partial refunds, and host identity checks beyond Paystack's own.
