# Dot.Emall monetisation audit (read-only)

Evidence base: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Emall (read-only extract in scratchpad `src/Dot.Emall`). The local working tree is on `main` but behind origin/main (local 15 migrations, origin about 49), so local files under-report the platform. Paths are repo-relative on origin/main. Only `.env.example` read.

Legend: EXISTING (verified), PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Multi-seller marketplace: stores, products with variants, stock movements, categories/attributes, cart, guest and signed-in checkout, orders split into seller groups ("order_groups"). EXISTING (`app/Services/CheckoutService.php`, `Cart/`, `Catalogue/`, `Checkout/`).
- Paystack card payment with payment attempts, webhooks, refunds, unpaid-order expiry (`PaymentService`, `PaystackWebhookHandler`, `RefundService`, `orders:expire-unpaid` every 10 min). EXISTING.
- Seller desk: orders/parcels, shipment tracking links, promo codes, returns handling, Q&A and review responses, exports, analytics. EXISTING (`Services/Seller/*`, `Services/Shipping/*`, `Services/Returns`).
- Discovery: search with synonyms, recommendations, saved searches, product alerts, optional AI shopping assistant. EXISTING (`Services/Search`, `Discovery`, `Assistant`).
- Operator desk: listing moderation (flag rules, prohibited terms), payment and ledger oversight. EXISTING (`Services/Operator`, `config/emall.php`).

## 2. Most valuable features (measurable)
- Paid orders with an internal seller ledger (gross, commission, net) per seller group: the clearest measurable value, GMV and take. EXISTING (`app/Services/Ledger/LedgerService.php`, table `ledger_entries`).
- Listing moderation and trust (store trust, review integrity, operator flags): protects buyer confidence. EXISTING.
- Stock holds, variants, bulk product tools (`SellerProductController::bulk`, `duplicate`), seller analytics and CSV exports. EXISTING.
- Returns lifecycle with escalation (`emall:escalate-returns`). EXISTING.

## 3. Existing limits, quotas, plans, entitlement, billing, fee code
- PLATFORM COMMISSION EXISTS: `config/emall.php` `commission.percent` (env `EMALL_COMMISSION_PERCENT`), default 0 ("no business term is invented"); per-store override table `store_commission_overrides` (`2026_11_09_100400_create_order_history_returns_and_ledger.php`, model `app/Models/StoreCommissionOverride.php`). Commission = percent of item value after discount, NOT on delivery; percent is snapshotted on the ledger entry; reversals append signed rows. `LedgerService::recordSale`, `commissionPercent`, `syncGroup`. EXISTING.
- Fee disclosure before publishing: `app/Services/Ledger/SellerFees.php`, `app/Services/Seller/FeePreview.php`. EXISTING.
- Earnings release: held then released 7 days after delivery (`emall.commission.release_after_days`, `emall:release-ledger` hourly in `routes/console.php`). EXISTING. No automated payout; Paystack transfers/subaccounts and KYC are an owner item (`docs/R43_DECISIONS.md` D-2, D-18, D-107). EXISTING absence.
- `emall:reconcile` (`app/Services/Ledger/Reconciler.php`) checks orders, payments, refunds, ledger, but is not scheduled (D-107). EXISTING.
- Ledger idempotency: unique `idempotency_key` ('sale:{group}', 'rev:{group}:{n}'). EXISTING.
- Entitlement (access gate): `app/Http/Middleware/EnsureCompanyEntitled.php` + `app/Services/InfoDot/EntitlementClient.php`; reads InfoDot, 10 min cache, unknown never locks out; `ecosystem.infodot.entitlement_check`. EXISTING.
- Hard limits (abuse/quality, not allowances): photos per product 6 and 4096 KB (`app/Services/ProductImages.php`); max price 10,000,000 and stock 10,000,000 (`SellerProductController`); unpaid orders per buyer 3 (`EMALL_MAX_UNPAID_ORDERS`); return photos 4; rate limiters checkout, suggest 90/min, community 20/min, assistant, webhooks 600/min (`AppServiceProvider`, `DiscoveryServiceProvider`). EXISTING.
- No cap on listings per store, no seller plan, no credit or wallet. EXISTING absence. One store per owner (`2026_10_19_300000`).
- Seller approval toggle `emall.seller.requires_review` (default false). EXISTING.

## 4. Resources consumed or stored and where countable today
| Resource | Where |
|---|---|
| Listings (products) per store | `products` (store_id, status approved/pending/rejected), `product_variants` |
| Orders / seller groups | `orders`, `order_groups`, `order_items` |
| Seller revenue and commission | `ledger_entries` (gross_cents, commission_cents, net_cents, status, seller_id, store_id) |
| Payments, refunds | `payments`, `payment_events`, `refunds` |
| Photos and bytes | `product_images` (+thumb_path); bytes via filesystem |
| Promo codes | `promo_codes` (per store) |
| Stock movements | `stock_movements` |
| Search/behaviour events | search and behaviour tables (`2026_11_09_300000/300100`), `SearchLog` |
| Assistant calls (AI) | not stored as usage; rate limiter `assistant` only. NEEDS DEVELOPMENT to count |
| Emails/notifications | `notifications` table; Dot.Notify events (`Services/Notify/NotifyClient.php`) |
| Marketplace metrics | `Services/Metrics/CommerceMetrics.php`, `Services/Analytics/MetricsCollector.php` (daily to Dot.Analytics) |
Company key: `teams.infodot_team_uuid` (`2026_10_18_100000`). EXISTING.

## 5. Third parties and cost drivers
- Paystack (payment fees, per paid order and per refund). EXISTING.
- Dot.Notify to email (per message). EXISTING. No SMS code.
- Anthropic API for the shopping assistant, only when `ANTHROPIC_API_KEY` is set (`app/Services/Assistant/AssistantModel.php`, per-call token cost, the one clearly variable vendor cost). EXISTING.
- Carriers: tracking links only (Courier Guy, Aramex, DSV, Pargo, PUDO, Fastway, Post Office; `config/emall.php`); no carrier rate or label API found. EXISTING.
- Storage and image processing (disk/CPU); search is DB-based (Postgres/portable), not a vendor. EXISTING.

## 6. Candidate purchase units
- TRANSACTION (PROPOSED, best fit, mostly built): commission on item value, already in the ledger. Keep it (0 free tier of money, charged only on sale) and add a per-order minimum or cap only if the owner wants.
- CAPACITY (PROPOSED): active listings above a free allowance; photo slots above 6 per product; promo codes; staff seats on a store (D-100 notes team desk access is undecided).
- CREDIT (PROPOSED): promoted placement (homepage/collection slots, `Discovery/Homepage`, `Collections`); AI assistant answers are a shopper-side cost the platform absorbs or caps per day; bulk import/export runs; notification (email) bundle.
- FEATURE (PROPOSED): advanced seller analytics/exports, multi-store.
- Free tier (PROPOSED): browse, search, buy, create a store, a generous starter number of listings, six photos, promo codes, order management, returns, basic analytics, ledger view.
- When an allocation runs out (PROPOSED): existing live listings and orders keep working; block only publishing a NEW listing or buying promotion; never block fulfilment, refunds, returns or payout visibility.

## 7. Cross-platform dependencies
- InfoDot: identity, SSO (`Http/Controllers/Auth/EcosystemAuthController.php`), `IdentityClient`, entitlement, team link (`Seller\StoreController::linkTeam`, `Actions/Ecosystem/SyncTeamsFromInfoDot.php`). EXISTING.
- Dot.Notify, Dot.Analytics, and the DKP incident pack publisher (`platform.dkp.json`, `emall:publish-dkp-incident-pack`) to Dot.Brain/Guardian. EXISTING.
- Payouts to sellers would route through Dot.Finance later (Billing discontinued). NEEDS DEVELOPMENT.
- Shareable (PROPOSED): wallet/credit balance, email credits, AI-assistant credits, storage, Paystack rail and payouts. Platform-specific: listing slots, promotion slots, commission rate.

## 8. Gaps
- Meter: only commission is metered. A usage/credit ledger for listings, promotion, assistant calls, emails. NEEDS DEVELOPMENT.
- Enforce: a listing-count check in `SellerProductController::store` and approval/resubmit paths (server-side), and a promotion purchase flow. NEEDS DEVELOPMENT.
- Payout: seller transfers (Paystack transfers/subaccounts), KYC, and a payout table; ledger statuses stop at "released". NEEDS DEVELOPMENT.
- Audit: schedule `emall:reconcile` (not scheduled today, D-107); extend it to the new credit ledger. NEEDS DEVELOPMENT.
- Commission is the only place a wallet-like balance already exists, so it is the template for the shared wallet design (append-only entries, idempotency key, signed reversals).
- Live money: Paystack test-mode default (`PAYSTACK_ALLOW_LIVE`), checkout requires a usable gateway in production (`EMALL_CHECKOUT_REQUIRES_GATEWAY`). Live shop was reported empty until `emall:seed-categories` (owner memory note), so volume is currently unproven.
