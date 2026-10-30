# Vendor and third-party register (draft)

Every outside party that receives data from InfoDot or runs part of it. Built from `docs/programme/POPIA_INVENTORY.md` (column and dependency names read from source on 2026-10-27), `config/services.php`, the privacy draft Part B (`docs/programme/legal/privacy-policy-additions.md`) and the workflows. **What a vendor does with data after it receives it is not visible from this repository**; those cells say "unknown" rather than guess. Contracts, locations and security reviews are **[OWNER]** items.

| Vendor | Role | What it receives from InfoDot | When | Where it sits / agreement | Evidence of review |
|---|---|---|---|---|---|
| Shared cPanel host (LiteSpeed, MySQL, PHP) | Runs everything; holds the database, files and backups | Everything | Always | **[OWNER]**: provider, country, operator agreement (POPIA s21) | The host's limits are documented in `docs/programme/EXECUTION_LOG.md` (1 GB RAM, 100 processes, 30 entry processes for the account); the provider's security posture is unknown |
| GitHub (repository, Actions) | Source, CI, deploy pipeline, secrets for deploy (`DEPLOY_SSH_KEY` and others) | Source code; deploy credentials as repository secrets; test and CI logs | Always | United States (GitHub); GitHub terms | Secret scanning and push protection are enabled; Dependabot security updates are off; `main` has no branch protection (FINDINGS P-01, P-02) |
| Paystack | Payment provider for paid seminars (through Dot.Billing) | Buyer email, amount, reference; payout account details for hosts (via Billing) | Paid seminars only | **[OWNER]**: legal entity, processing location, operator or responsible party | Test-mode run, including a refund of a split payment, not yet done (`R13_GO_LIVE.md`) |
| Dot.Billing | The ecosystem's payment system (this owner) | As Paystack, plus the host agreement record, company identifiers | Paid seminars, company seats | Same host | Contract between InfoDot and Billing is the signed HMAC API (`docs/api/openapi.yaml`, `R13_BILLING_CONTRACT.md`) |
| Dot.Dopemine | Dot Points ledger (this owner) | Event id, host uuid, seminar reference, three numbers (counts and an average); never names or ratings | Points features | Same host | Signed calls; Dopemine's own retention not reviewed |
| Dot.Memory, Dot.Brain | Opt-in knowledge sharing, library recommendations (this owner) | Topic counts and references for people who opted in; library candidates (public content) | Opt-in only | Same host | See `docs/programme/VERIFIED_LIBRARY.md` |
| Sentry | Error tracking (`sentry/sentry-laravel`) | Exception class, message, stack and request details; `send_default_pii` is false. Each event is tagged with the random `request_id` | Only once a DSN is set in the host `.env` | **[OWNER]**: plan, data region | Wired in the exception handler 2026-10-30 (tested with a collecting transport); no DSN set yet, so nothing is sent today |
| LiveKit server | Video for the InfoDot room (self-hosted) | Name (60 characters), `u<id>`, role, the person's address, media | Only if the room feature is on | **[OWNER]**: where the server runs (needs the owner's server; not yet run) | `R13_LIVEKIT_SERVER.md` |
| Google STUN (`stun.l.google.com`) | Lets browsers find each other in the small room | The browser's public address | Small rooms only | Google; the default, replaceable with `P2P_STUN_URLS` | `R13_SMALL_ROOMS.md` |
| Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`) | Typefaces loaded from Google | The visitor's address and browser details | Every page that uses them | Google | Self-hosting is a design choice listed in `POPIA_READINESS.md` |
| Other outside hosts the pages load | unpkg.com, cdnjs.cloudflare.com, cdn.jsdelivr.net, Gravatar (avatars), Unsplash (images), social links | Visitor address and browser details; Gravatar also receives an MD5 of the author's email (FINDINGS B-15) | Pages that include them | Various | Counted in the inventory, not reviewed individually |
| Mail (SMTP) | Verification, reset and invitation email | Recipient address and message | Account emails | The host's mail service, sender `no-reply@infodot.co.za` | Timeout set to 10 s (this programme); deliverability and retention unknown |
| Stripe (Cashier) | Billing library in `composer.json` | Nothing: no webhook route is registered without `STRIPE_WEBHOOK_SECRET`, and no Stripe tables exist | Never, today | n/a | FINDINGS B-10 |
| Pusher | Realtime library | Nothing known to be sent: the broadcast event is not dispatched (FINDINGS A-11); credentials were removed from the example env | Never, today | n/a | **[OWNER]** confirm the host `.env` does not use the old example's Pusher credentials |
| Owner's Mac | Off-host backup pull | Encrypted backup archives | Daily 04:15 | Owner's laptop (FileVault on) | `docs/programme/BACKUPS.md` (useless without the passphrase; passphrase copy outstanding) |

## Standing questions for each paid or personal-data vendor

1. Is there a written agreement that says what the vendor may do with the data (POPIA s21)? **[OWNER]**
2. Where is the data processed, and is that outside South Africa (s72)? **[OWNER/ADVISER]**
3. How long does the vendor keep it, and can a deletion request reach it? (For account deletion: Billing's and Paystack's copies are not erased by InfoDot's eraser; see `R13_PRIVACY_GAPS.md`.)
4. Who is told, and how fast, if the vendor has a breach? **[OWNER]**
