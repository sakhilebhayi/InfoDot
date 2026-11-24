# Dot.Auction monetisation audit (read-only)

Evidence base: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Auction (extracted read-only to scratchpad `src/Dot.Auction`). NOTE: the local working tree is on stale branch `feat/reverb-realtime-core` (12 migrations, 74 files behind main); origin/main has 26 migrations and holds the R-42 rebuild. Paths below are relative to the repo root on origin/main. Only `.env.example` was read.

Legend: EXISTING (verified in code), PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Live-bidding marketplace: sellers list lots with start/reserve/buy-now prices and a fixed window; buyers bid in real time. EXISTING (`app/Services/Auctions/BidPlacer.php`, `ListingManager.php`, `BuyNowPurchaser.php`).
- Proxy (eBay-style) auto-bid, anti-snipe extension, reserve confidentiality with a seller decision when reserve is not met (`AutoBidEngine`, `config/auction.php` snipe keys, `ReserveNotMetProposal`, `ReserveDecisionPanel`). EXISTING.
- Settlement of ended lots into `auction_settlements`; buyer pays online by Paystack (test mode); operator view of payments. EXISTING (`SettlementLifecycle`, `SettlementPayer`, `PaystackClient`, `Operator/SettlementPaymentController`).
- Watchlists, ending-soon, outbid/won/sold notifications. EXISTING.
- Seller payout is MANUAL and untracked. EXISTING (`app/Notifications/AuctionSoldNotification.php`, `docs/R42_DECISIONS.md` U-8 and open question 4).

## 2. Most valuable features (measurable business value)
- Reserve price + buy-now + anti-snipe + auto-bid: raise sell-through and final price; measurable via `auctions.status`, `bids`, `auction_settlements.amount_cents`. EXISTING.
- Online settlement (Paystack) with overdue marking (`app/Console/Commands/MarkOverduePayments.php`): turns a win into cash; GMV measurable. EXISTING.
- Append-only bid ledger (`2026_11_09_000001_make_bids_append_only.php`): audit-grade, supports disputes and any usage billing. EXISTING.
- Listing photos (max 6 per auction, private disk with variants): `AuctionImageManager`, `AuctionImageVariants`. EXISTING.

## 3. Existing limits, quotas, plans, entitlement, billing, fee code
- Plans/subscriptions/credits/wallet in this app: none (no table, no class; grepped app/config/database). EXISTING absence.
- Transaction/commission/platform-fee logic: NONE. `auction_settlements` has only `amount_cents` + `currency` (`2026_10_18_000004`, `2026_10_19_000001`); the Paystack call has no subaccount/split/fee field. The buyer pays the full amount to the platform's Paystack account; seller payout is manual. EXISTING absence. This is the most important gap.
- Entitlement (access gate): `app/Http/Middleware/EnsureCompanyEntitled.php` appended to the web stack (`bootstrap/app.php`), reading InfoDot `GET /api/ecosystem/companies/{uuid}/platforms?platform=auction` via `app/Services/InfoDot/CompanyEntitlement.php` (10 min cache, fails open on outage, `AUCTION_ENTITLEMENT_CHECK`). Statuses understood: suspended, payment_required, subscription_expired/expired/trial_ended/cancelled, needs_company. EXISTING. It is a boolean access gate, not a quantity.
- Hard limits (abuse limits, not allowances): photos per auction `auction.images.max_per_auction` default 6 (`AuctionImageManager.php:48`); duration min 60 min / max 30 days (`ListingTerms.php`); bid rate limit 20 per 60 s per user (`config/auction.php`); limiters browse 60/min, writes 60/min, uploads 20/min, livewire 300/min, paystack-webhook 120/min (`AppServiceProvider.php`). EXISTING.
- No cap on active listings, listings per month, bids per auction, or storage per seller. EXISTING absence.
- Operator role: `users.is_platform_operator` (`2026_10_19_000003`), env `AUCTION_OPERATOR_EMAILS`. EXISTING.

## 4. Resources consumed or stored, and where a count can be read today
| Resource | Where countable today |
|---|---|
| Listings created / published | `auctions` (seller_id, status, created_at); published = status != draft |
| Active listings at once | `auctions` where status = active |
| Bids placed | `bids` (append-only; bidder_id, auction_id, is_auto_bid, created_at) |
| Auto-bid use | `bids.is_auto_bid` |
| Buy-now purchases | `BuyNowPurchaser`, resulting settlement rows |
| Settled value (GMV) | `auction_settlements.amount_cents` (+ status, paid_at) |
| Payment attempts | `settlement_payments` (provider, reference, status) |
| Photos / storage | `auction_images` (`2026_10_18_000006`); per-file byte size not confirmed as a column |
| Notifications / emails | `notifications` table; outbound email via `app/Jobs/SendNotifyEvent` to Dot.Notify |
| Per-company 7-day metrics | `app/Services/Analytics/MetricsCollector.php` (active, ended_7d, gmv_7d_cents, bids_7d, currency) |
Company key for a per-company meter: `teams.infodot_team_uuid` (`2026_10_18_000002`). EXISTING. An immutable per-event usage ledger: NEEDS DEVELOPMENT.

## 5. Third parties and cost drivers
- Paystack: fees per settled payment (the real marginal cost). EXISTING.
- Dot.Notify then email: per-message cost, queued. EXISTING. SMS: none found.
- Laravel Reverb realtime is self-hosted; production runs `BROADCAST_CONNECTION=log` with polling every 4 s (`config/auction.php`), so realtime is a server-load cost. EXISTING.
- Image storage and variant processing (disk, CPU). EXISTING. No maps and no AI calls in code.

## 6. Candidate purchase units
- TRANSACTION (PROPOSED): seller success fee on a paid settlement, or a listing credit consumed at publish. One clean hook exists: `SettlementLifecycle` when status becomes paid. Charged only on success, which suits small SA sellers and a free tier.
- RESOURCE/CREDIT (PROPOSED): listing credits beyond a monthly free allowance; extra photo slots; featured/promoted placement.
- CAPACITY (PROPOSED): concurrent active listings; bulk import capacity.
- FEATURE (PROPOSED): paid reserve price, scheduled start, promotion, seller analytics. Buyer features (browse, watchlist, bidding, auto-bid, anti-snipe) stay free.
- Free tier (PROPOSED): browsing, watchlist, bidding, notifications, a small monthly number of listings with basic photos, buyer payment, bid history.
- When an allocation runs out (PROPOSED): block only NEW publish (drafts still save); never freeze a running auction, a bid, a settlement or a payment; show a top-up prompt in the seller form. Check goes in `ListingManager` publish under the existing row lock.

## 7. Cross-platform dependencies
- InfoDot: identity, company uuid, entitlement read, SSO redeem (`app/Http/Controllers/Auth/EcosystemAuthController.php`), team sync (`app/Actions/Ecosystem/SyncTeamsFromInfoDot.php`). EXISTING.
- Dot.Notify (email events) and Dot.Analytics (daily metrics). EXISTING.
- Settlement handoff to Billing is a `wiki.md` roadmap item, not implemented; Billing is discontinued so the target would be Dot.Finance. NEEDS DEVELOPMENT.
- Shareable (PROPOSED): wallet/credit balance, email/notification credits, storage credits, Paystack rail and payout. Platform-specific: listing and active-lot credits, promotion slots.

## 8. Gaps: meter, enforce, audit
- Meter: usage ledger written in the same transaction as publish, settlement paid, buy-now, photo add. NEEDS DEVELOPMENT.
- Fee capture: fee_cents / seller_net_cents on `auction_settlements`; Paystack split or recorded manual payout (`paid_out_at`, flagged open in `docs/R42_DECISIONS.md`). NEEDS DEVELOPMENT.
- Enforce: gate in `ListingManager` publish (server side), not the UI; do not rely on the 10-minute cached entitlement. NEEDS DEVELOPMENT.
- Wallet: balance read/debit API shared with InfoDot or Finance, idempotency key per event, immutable ledger. NEEDS DEVELOPMENT.
- Audit: reuse the append-only trigger pattern from bids; monthly reconciliation against settlements and Paystack references. NEEDS DEVELOPMENT.
- Live money: Paystack is test-mode only (`PAYSTACK_ALLOW_LIVE=false` refuses sk_live_ keys, `.env.example`). Real fees need owner decisions on payout, disputes and legal.
