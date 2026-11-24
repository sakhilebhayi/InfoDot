# R-60 step M0: audit of the Dot platforms for monetisation (read-only)

Date: 2026-11-24. Status: audit only. No code was changed in any repository, no price is proposed, nothing is configured.

## 1. How this audit was done, and what to trust

Six read-only auditors each read the code of a group of platforms and wrote one file per platform in `docs/programme/r60/` (24 files, same folder). Each statement in those files is marked EXISTING (verified in code, with a path), PROPOSED or NEEDS DEVELOPMENT. They read only `.env.example` files, never a real `.env`, and changed nothing.

What I checked myself: `finance.md` and `internal.md` (read in full), and the earlier R-53 price record in `~/Dot/r53` (section 5). The other files are the auditors' reports and have not been line-by-line checked by me; where this document leans on them it says "auditor reports".

**The local checkouts are stale.** Almost every platform folder under `~/Dot` is tens of commits behind `origin/main` (Finance 83, Agents 84, Docs/HR/Projects/Sheet/Press 40 to 95, Design/Engage/Pulse/Tutor 59 to 71, Analytics on an old branch, Auction and Ehail on old feature branches). The auditors therefore read `origin/main` through `git archive`, as of the last fetch (the tips are mostly 2026-11-15 to 2026-11-18). Anything newer on a remote was not seen. Dot.Mines was read from its local checkout. Dot.Charts has no `Dot.Charts` folder; it is `~/Dot/ChartSense`.

## 2. The list of platforms (decision M1)

The brief says 22 live platforms, excluding Dot.Mines. The registry (`config/ecosystem.php`) has 25 active entries:

| Group | Entries |
|---|---|
| Excluded by the brief | mines |
| Internal services (no customer-facing product) | memory, dopemine |
| InfoDot itself | infodot |
| The other 21 | agents, analytics, auction, central, charts, design, ehail, emall, engage, files, finance, forms, hr, notify, press, projects, pulse, sheet, tasks, tutor, docs |

21 plus InfoDot is 22, which is the most likely reading of "22". Please confirm. Also note the registry still lists the discontinued Billing, Farms and Plug as active in other repos' configs (Mines' `config/ecosystem.php` does), which should be cleaned separately.

## 3. The reference model: Dot.Mines (auditor report, `r60/mines.md`)

- **Unit.** One slot for one machine, in two classes. Stored in `machine_allocations`, an append-only ledger of signed `delta` rows (with source, payment, subscription, reason, creator). The balance is the sum, never a stored counter.
- **Consumption is a stock, not a meter.** A slot is held while a machine occupies it. There are no usage events and no draw-down. A wallet that spends credits per use needs a debit model Mines does not have.
- **One grant path.** Only a signed Paystack webhook grants allocation, de-duplicated on the Paystack reference. The ledger has no database-level unique constraint, so a concurrent double grant is theoretically possible.
- **One enforcement gate.** Two services decide everything (UI, API and sync all call them), with a per-team lock and a re-check inside a transaction. There is no middleware or Gate.
- **Lapse.** A lapsed subscription sets capacity to 0 and blocks new machines; data and history are never hidden.
- **Gaps in Mines billing.** No refund or chargeback handling, the ledger sources for refund, cancellation, expiry and restore have no writer, VAT is hard-coded to 0, no invoice PDF, no grace period beyond the paid period, no reconciliation job. Whether Paystack renewals repeat the allocation metadata (which would double grant) is UNVERIFIED.
- **No link to the ecosystem.** Mines has no SSO, no InfoDot entitlement call and no Dot.Finance integration; billing is local and calls Paystack directly. A shared wallet has to be built from outside Mines. Mines also receives Paystack's webhook at `/webhooks/paystack`, which matters in section 4.

Nothing in Mines was touched.

## 4. Existing billing infrastructure (verified for Finance and InfoDot)

**Dot.Finance** (read in full): it is already the payment arm for InfoDot seats and is solid: signed idempotent contract (HMAC-SHA256 over timestamp and body, 300 s), hosted Paystack checkout, cancel at period end, a webhook that re-verifies every payment with Paystack before granting anything, one payment recorded once, a transactional outbox with retries pushing signed confirmations to InfoDot, a reconciler, a tax invoice posted into the seller's books, refund then credit note. It is ZAR only.

It does **not** have: a wallet, a balance ledger, a one-off top-up checkout (the gateway can do a plan-less charge; the controller and checkout always attach a plan), a consume or deduct API, a price book (price authority is InfoDot's configuration), deferred revenue treatment for unused credits, usage statements, or low-balance notices. All of these are NEEDS DEVELOPMENT.

**InfoDot**: the seat-licence engine is fully built (seats, batches, grace, banner) but dormant: no price, Finance not connected. `platform_plans.limits` is stored and never read. The entitlement API returns booleans only. Seminar commission (10 percent) is switched off.

**Open conflict to resolve before any wallet:** one Paystack account has one webhook URL per mode. Mines already receives `/webhooks/paystack`; Finance's webhook cannot use the same URL without routing or forking. (Recorded in `r53/CONTRACT.md` and confirmed by the auditor.)

## 5. Findings the owner needs to see now (price, VAT, bands)

These are not new bugs; they are records that do not agree with each other, found while checking Finance.

1. **Price history.** R-53 (approved 2026-11-16, `~/Dot/r53/STRATEGY.md` and `CONTRACT.md`): R99 per user per month excluding VAT, R990 per user per year (ten months for twelve), marginal volume bands. R-59 brief (2026-11-24): R100. You then set **R98** (2026-11-24). Nothing is configured at any of these prices.
2. **Annual price.** R990 was ten months of R99. At R98 the same rule gives R980, but no annual price has been stated for R98.
3. **Volume bands.** R-53 approved marginal bands; R-59 decision D1 (approved) chose flat. PR 255 made the default flat (bands are kept as a mechanism, off by default).
4. **VAT.** R-53 decision D11 (owner, 2026-11-16) says the selling business is NOT VAT registered, prices are final, no VAT is added, invoices carry no VAT line. PR 255 added an optional VAT-on-top quote (`LICENCE_VAT_RATE`), unset by default, so nothing changes unless it is set. Please confirm which rule you want; if the business stays unregistered, the VAT option should be removed.

None of this is live. These need your decision at M1.

## 6. What every platform has in common (auditor reports)

- **Entitlement is a yes or no that fails open.** Each platform has a middleware (`EnsureCompanyEntitled`) that asks InfoDot only whether the company is entitled and lets everyone through on any error. No platform receives a number (seats, storage, credits). A wallet needs either a numeric entitlement answer or a balance API with reserve, commit and release.
- **No platform has plans or credits of its own.** Billing code exists only in Finance (InfoDot seats), Mines (machines), Agents (Stripe, USD), Tutor (Paystack, own ledger, 15 percent commission, never run live), Ehail (commission, ledger, payouts), Emall (commission ledger, percent defaults to 0), Auction (none).
- **Metering is uneven.** Where AI is used, some platforms record tokens (Docs `ai_model_usage`, Analytics, Tasks, Agents, Central, Press, Design) and some do not (Sheet partly, Forms not at all). Several records have no company key (Tasks, Press, Tutor), so per-company charging is impossible without a change. Some rows are pruned (Design after 90 days).
- **Caps are inconsistent.** Tasks' AI cap is global across all companies. Sheet's monthly cap is enforced at only one of four AI entry points. Agents has limits that exist but are not enforced (`max_users`, `max_departments`, `max_workflows`). Forms' AI form builder has no per-team count.
- **Storage is unmetered in the central store.** Files has no quota; version history is not counted in file size; five platforms store through its API with no entitlement check. Other platforms (HR, Tasks, Press, Finance) keep their own files on local disk, so the same bytes can be stored twice.
- **Communications bypass.** Only email, in-app, webhook and Slack really deliver in Notify; SMS and push have no provider. Finance and Files send their own email, so a shared communications credit would be bypassed. Notify's `/api/ingest` skips the entitlement check and `AiNotifyService` reads the wrong config key.
- **Dot.Charts (ChartSense) is outside the ecosystem sign-in:** its own login, no InfoDot entitlement, anonymous backtests with per-IP limits, SQLite. Market-data and regulatory licence risk is flagged by the auditor.
- **Dopemine is the best template for a wallet ledger** (idempotency key, lots with expiry, append-only entries, monthly caps), and its reward kinds `billing_credit` and `capacity` already anticipate points becoming credits but have no fulfilment path.

## 7. Platform table (auditor reports unless marked)

"Candidate unit" is where a purchasable resource could be measured; it is an observation, not a proposal. No price is suggested: there are no cost inputs yet (M1).

| Platform | What stands out | Candidate unit | Main gap |
|---|---|---|---|
| Finance (verified) | Billing arm for seats; documents, VAT prep, reconciliation, AI receipt extraction (off by default) | Issued documents beyond a free block, AI credits, storage, emails | No usage events, wallet or price book |
| Files | Central company storage, unmetered | Storage GB (shared) | No quota; versions uncounted; no entitlement on its API |
| Notify | Email, in-app, webhook, Slack deliver | Messages sent (shared comms credit) | SMS/push absent; ingest unchecked; no log pruning |
| Tasks | AI task breakdown logged per call | AI credits, attachment storage | Global AI cap, no company key, double storage |
| Analytics | Free public data core; 25 watches, 100 saved, 200 alerts; USD 5 per team monthly AI cap | AI credits, library capacity | Six-hourly engine run for every team is uncontrolled cost |
| Charts | Own login, FastAPI engine, anonymous use | Backtest compute | Outside ecosystem sign-in; no metering; data licence risk |
| Agents | Plan limits and token quota exist; Stripe in USD | Agent runs, tokens | USD and Stripe; three limits unenforced |
| Forms | Entitlement, team cap 10, 10 MB upload, OpenAI form builder | AI builder credits, submissions | No quotas or plans; AI uncounted |
| Projects | Large feature set, AI plan generation (10 per user per hour) | AI credits, project and storage capacity | No quotas |
| HR | No AI; headcount drives value; encrypted local documents | Employees (seats) | Never paywall POPIA access; local-disk documents |
| Sheet | Per-business monthly AI cap (500,000 tokens default) | AI credits, storage | Cap enforced at one of four entry points |
| Docs | Best metering (`ai_model_usage`, daily spend guard) | AI credits, storage | No monthly team cap or reservation |
| Press | AI units exist (outline 3, suggest 1, one per slide), per-user daily quota | Slides and AI credits, asset storage | No team key on AI or exports; video up to 200 MB unmetered |
| Design | AI caps (60 per user, 300 per workspace per day), 200 MB assets per user | AI credits, asset storage | Exports run in the browser; AI table pruned at 90 days |
| Engage | Signing envelopes, email-heavy, no AI | A completed signing envelope | Outside signers bypass entitlement; TURN and timestamp authority unconfigured |
| Pulse | Mostly individuals; business pages and offers capped in config | Business page capacity | Billable party undefined; knock budget deliberately not buyable |
| Tutor | Own Paystack ledger and 15 percent commission, never run live; age-banded AI with USD 100 monthly cap | Learner purchases, AI | Duplicates Finance's ledger; "entitlement" means two things |
| Auction | No fee, commission or plan; Paystack test mode only; manual payout | Transaction fee at settlement | Real money needs owner decisions |
| Emall | Real commission ledger (default 0 percent), append-only | Transaction fee, AI assistant credits | No payouts; reconcile not scheduled |
| Ehail | Commission default 20 percent, versioned plans, double-entry ledger, payouts, business accounts | Existing commission; SMS and routing cost | Passenger wallet only holds cards |
| Central | Agent token usage logged per turn, no cap | AI credits | Rooms and machines not counted |
| InfoDot (verified) | Full seat engine, dormant | Seats | No price; limits unread; Finance off |
| Memory, Dopemine (verified) | Internal; no billing code | Not sold directly; Dopemine as ledger template | Fulfilment path missing |

## 8. Preliminary resource sharing map (PROPOSED, not decided)

- Candidates to share across the ecosystem: seats (InfoDot owns them), storage (if Files becomes the single store), AI credits (Finance, Tasks, Docs, Sheet, Design, Press, Projects, Analytics, Agents, Central and Forms all call model APIs), communications credit (Notify plus Finance and Files email).
- Platform-specific: signing envelopes (Engage), issued documents and bank lines (Finance), listings and transactions (Auction, Emall, Ehail), employees (HR), backtests (Charts).
- A purchase in one platform never unlocks unrelated premium features elsewhere; every entitlement must be explicit and auditable.

## 9. Cost risks seen so far

AI tokens are the largest and least controlled cost: Analytics' six-hourly run for every team, Tasks' global cap with a premium default model, Sheet's bypassable cap, Forms' uncounted builder. Next: outbound email and SMS (Engage, Finance, Notify), storage (Files, Press video, Design assets), mapping and SMS (Ehail), and market-data licences (Charts). Paystack fees per card payment. The auditors found no hosting invoices in code; those figures only you have.

## 10. What is not yet known

- Actual hosting, AI, SMS, email, storage and payment costs, and a target gross margin (M1). Without these no price can be justified.
- Whether Finance's production Paystack is live, and what the Mines webhook conflict means in production.
- Production usage volumes for every platform.
- The owner's reading of the price, VAT and band conflicts in section 5.
- The files for `origin/main` after the last fetch (tips mostly 2026-11-15 to 2026-11-18).

## 11. Decisions needed before M2

| # | Decision | Note |
|---|---|---|
| M1 | Confirm the 22 platforms (section 2) | I read it as the other 21 plus InfoDot |
| M2 | Cost inputs and a target gross margin | You supply the figures; I will not estimate costs |
| M3 | Wallet owner: company, with personal wallets for people without a company | Recommendation unchanged |
| M4 | Seats stay a licence batch the wallet recognises | Recommendation unchanged |
| M5 | Billing home Finance, with the gaps in section 4 closed first, and the Paystack webhook conflict resolved | Confirm |
| M6 | Price, annual price, bands and VAT (section 5) | New, found in this audit |
| M7 | Tutor, Agents and Ehail already have their own billing: fold into the shared model, or leave | New |

## 12. Next steps

M1 and M2 are yours. When the cost inputs are in, M2 is the strategy document (every platform, free and paid features, purchase unit, ZAR price with its justification, rules and cost notes) and M3 is the wallet and entitlement architecture proposal. Neither is started, and nothing is implemented before you approve them.
