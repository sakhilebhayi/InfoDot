# R-50 audit notes: Analytics coverage and payments (read 2026-11-13)

## Headline
"Platform coverage: 23 live / 23 registered" is NOT in Dot.Analytics. It is InfoDot's own `/analytics` page:
`AnalyticsController.php:98` (`Platforms::visible()`), `analytics/dashboard.blade.php:6,230-251`, CSS `.ixa-cover`
in infodot.css 8103-8165. 25 registry entries minus dopemine and memory = 23, all hard-coded `active`. No ping, no
table, no entitlement, nothing per company; any signed-in verified user sees it. Tests touching /analytics:
TenantIsolationTest:145, InternalPlatformsTest, SignedInUiTest.

## Dot.Analytics (default branch feature/ecosystem-sso, b59546c; deploy manual)
- Its own equivalent is honest and per company: "Platforms connected N of 13 that can report" from DataSource rows
  (`WorkspaceController.php:26-34`, `dashboard.blade.php:10`).
- Roles: owner, admin, editor, viewer. Staff: `users.is_platform_operator` (artisan `analytics:operator`), gate
  `operate-platform`, page `/me/operate`, model `PlatformCheck` fed by daily `intelligence:collect`. Reuse for the
  infrastructure-health view.
- Tenant: `teams.infodot_team_uuid`. Arrival: POST /auth/ecosystem -> IdentityClient::redeem (bearer per-platform
  secret, 5 s) -> ProvisionUserFromInfoDot, SyncTeamsFromInfoDot; re-verifies every 12 h.
- Data: daily aggregate snapshots per company via signed `POST /api/ingest/snapshots`; 13 platform catalogues.
- STILL NAMES DISCONTINUED PLATFORMS: `config/ecosystem.php:22,30,38`, `IntelligenceEngineService::PLATFORMS`
  (:40-157), `config/metrics.php:105,115`, `.env.example:98-99`. Also `sheets` vs `sheet` variant in
  `config/intelligence_ecosystem.php:39`; its registry copy has drifted from InfoDot's (charts inactive; press,
  sheet, docs URLs differ).
- Landing: /dashboard (four counts + Livewire panels). DESIGN.md concept "Every figure shows its working".
- To show "Your business platforms": new InfoDot read (e.g. GET /api/ecosystem/companies/{uuid}/platforms with the
  existing per-platform bearer secret) or add `platforms` to the redeem payload.

## Payments
- The retired payments app was built and tested but, per its own docs, never ran against the host or took real
  money (test keys only; refuses live keys unless a flag is set). It has: plans + subscriptions (global, not per
  platform), lifecycle (trial end, renewal, past_due, cancel at period end, dunning), signed entitlement read
  `GET /api/entitlements/{uuid}`, company sync `POST /api/companies`, Paystack hosted checkout + verified
  idempotent webhook, invoices with numbering and a tax line, `/seats` page with proration, marketplace tickets
  with split/refund/disputes, signed callbacks to InfoDot. NOT built: subscription refunds, automatic card
  re-charge at renewal.
- Dot.Finance (main d8e43ab) is an accounting product (ledger, sales, purchases, bank, VAT201, reporting; 257 test
  files). Payment code = a port with a manual gateway only ("nothing simulates a payment"). No provider client,
  webhook, subscription, plan or entitlement code. Tenant by `infodot_team_uuid`. Has proper SA VAT (15%, code
  ZA_VAT_STD, tax-invoice checklist, numbering).
- Gap for Finance to serve InfoDot: entitlement read (S), company sync (S), plans/subscriptions/lifecycle (M),
  per-platform plans + central price catalogue (M, missing everywhere), invoices with VAT (M-L, choose Finance's
  SalesDocument or the simpler model), Paystack checkout/webhook (M), /seats page (M), marketplace (L), automatic
  renewal charging (M, missing everywhere), subscription refunds/credit notes (M), proof-of-payment callback (S).
- Riskiest: the platform's own receivables inside tenants' accounting product (tenancy boundary); two invoice
  models; refund apportionment on split payments unverified; proration/seat rules await owner decisions.
- Buildable with provider TEST keys: everything above end to end. Owner-only: live provider account and keys,
  bank verification, a small live refund test, VAT registration details, prices, proration/grace/refund policy,
  legal sign-off, EFT decision.
