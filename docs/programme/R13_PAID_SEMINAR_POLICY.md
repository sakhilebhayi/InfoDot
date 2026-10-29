# Paid seminars: refunds, disputes, seller and tax (plan and decisions, 2026-10-29)

**This is an engineering plan, not legal or tax advice.** Every rule below that touches consumer law or VAT must be confirmed by an adviser before real money flows. Search results consulted are listed at the end; two of them could not be verified and are marked.

## Owner decisions (2026-10-29)

| Question | Decision |
|---|---|
| Attendee cancels | **Full refund if cancelled at least 24 hours before the start, nothing inside 24 hours.** The window is `seminars.paid.refund_hours_before`. A host cancelling always refunds everyone in full. |
| Merchant of record | **The host is the seller.** InfoDot is the marketplace: it collects payment through Paystack, keeps 10% as a commission, and invoices the host for that commission. |
| VAT | **InfoDot is VAT-registered.** Commission invoices to hosts carry VAT at 15% with InfoDot's VAT number. |

## What is built

1. **Attendee cancellation.** A paid ticket cancelled 24 hours or more before the start is refunded in full through Dot.Billing; inside 24 hours the registration is cancelled (the place is freed) and no money moves. The page states the rule before purchase and on the cancel button.
2. **Partial refunds.** A host can refund part of one ticket (a goodwill refund) up to what remains. The amount is sent to Billing with an idempotency key; the ticket shows partially refunded; the host's revenue and InfoDot's commission follow the net amount. **How Paystack apportions a refund between the host's subaccount and InfoDot on a split payment is not verified**; one small live test in Paystack test mode decides it before anyone relies on it.
3. **Chargebacks.** Billing records Paystack disputes and tells InfoDot; the ticket shows disputed, the host is told, a person answers in the Paystack dashboard (nothing is submitted automatically). A lost dispute removes the ticket (the attendee loses the place) and reverses the commission on it. **Who bears a lost dispute's cost is not settled here:** the account holder at Paystack bears it, and recovering it from a host's earnings needs a term in the host agreement and is not automated.
4. **Seller details and receipts.** A host can give a trading name and, optionally, a VAT number, and authorise InfoDot to issue receipts on their behalf. The attendee gets a receipt naming the seller. It is headed "Receipt" unless `SEMINARS_ISSUE_TAX_INVOICES` is on **and** the host gave a VAT number and authorised it, in which case it carries the abridged tax invoice fields (seller name, VAT number, number, date, description, amount, VAT included at 15%). Off by default pending the adviser.
5. **Commission invoices.** Monthly, per host, InfoDot issues a numbered tax invoice for the commission it kept that month (credit note when refunds outweigh sales), with VAT at 15% **extracted from the 10%** (the 10% is treated as VAT-inclusive so the host is never charged more than the 10% they were told; `SEMINARS_COMMISSION_INCLUDES_VAT`). Nothing is issued unless InfoDot's legal name, address and VAT number are configured.
6. **Moderator page** listing open disputes, refunds that were asked for but not confirmed, and callbacks Billing sent that did not match.

## Not decided or not built

- Whether ECTA section 44's seven-day cooling-off applies to a seminar on a fixed date. The owner chose the 24-hour policy; an adviser should confirm it is at least as generous as the law requires.
- Automatic recovery of lost chargebacks from a host.
- VAT treatment for hosts below the registration threshold, and for attendees outside South Africa.
- Host identity checks beyond Paystack's own.
- Payout timing (Paystack controls it).

## Sources consulted (web search, 2026-10-29)

- ECTA section 44: seven-day cooling-off, refund within 30 days, section 42(2) exclusions (the leisure or fixed-date exclusion was **not verified**): acts.co.za.
- SARS draft ruling on electronic services via intermediaries and a Tax Faculty FAQ on commission invoicing (the answer was not visible; the R1 million threshold was **not verified**).
- Paystack dispute documentation (events `charge.dispute.create`, `.remind`, `.resolve`); nothing found on how disputes behave for split or subaccount payments.
