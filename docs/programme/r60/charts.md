# Dot.Charts: monetisation fact base (R-60, read-only audit)

Audit basis. Dot.Charts lives in `/Users/sakhilebhayi/Dot/ChartSense` (there is no `Dot.Charts` folder; the GitHub repo was renamed to Dot.Charts, registry id `dot-charts`). The local checkout is `main`, 36 commits behind `origin/main` (e00ff6f, 2026-11-15, as of last fetch; I did not fetch). I read `origin/main` for the audit. Paths are repo-relative. Dot.Charts is NOT a plain Laravel app: it is a Laravel 12 API (`backend/`), a FastAPI Python engine (`analytics/`) and a static Vite frontend (`frontend/`), on SQLite. The audit task described "a Laravel app"; this is a correction. Status tags: EXISTING, PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Market-analysis workbench: backtests nine strategies on live-fetched price history for equity, crypto, commodity and forex (`backend/app/Http/Controllers/BacktestController.php`, `analytics/main.py`, `analytics/strategies/*`). EXISTING.
- Chart-image analysis: OCR a screenshot to find the ticker, then swing-structure analysis on real recent data (`ChartAnalysisController.php`, `analytics/analysis/ocr_symbol.py`). EXISTING.
- Visual strategy builder (saved rule sets), options volatility regime read, and a trading journal (reflections only, never positions or orders; a schema-invariant test enforces it). EXISTING.
- Knowledge Pack generate, approve, sign (Ed25519) and publish towards Dot.Brain, operator-only; Dot.Brain has no live endpoint yet, so nothing is sent (`backend/app/Services/Dot*`, `wiki.md` section 5). EXISTING but dormant.
- Not financial advice: every result carries a disclosure and the platform states it never holds user positions or executes trades (`wiki.md` section 7). This is a regulated-adjacent domain; see section 6.

## 2. Most valuable features
- Backtest engine with nine strategies including a walk-forward ML signal and cointegration pairs trading, persisted per user with confidence band and loss-honest disclosure (`DisclosureFormatter.php`). EXISTING.
- Custom rule strategy builder with server-side validation (`CustomStrategyController.php`, `analytics/strategies/custom_rules.py`). EXISTING.
- Chart OCR to analysis (the nearest thing to a "wow" feature for a small trader). EXISTING.
- Trading journal linked to runs. EXISTING.
Business value is learning and decision hygiene for individual traders, not revenue for a business. It is the least aligned of the four platforms with "small South African businesses first".

## 3. Existing limits, quotas, plans, entitlement checks, billing
- InfoDot entitlement: NONE (EXISTING absence). No `EnsureCompanyEntitled`, no `EntitlementClient`; grep for entitle/redeem/SSO in `backend/app`, `backend/routes` finds nothing. Auth is its own Sanctum register/login (`backend/app/Http/Controllers/AuthController.php`). `/Users/sakhilebhayi/Dot/r50/ARCHITECTURE.md` section 5 confirms "Platforms with their own sign-in (Charts, Mines) cannot be enforced by InfoDot... marked managed in the platform". The Analytics registry copy marks charts `active => false`.
- Rate limits (EXISTING, `backend/app/Providers/AppServiceProvider.php`): backtests 30/hour per user, 3/hour per anonymous IP; chart-analysis 10/hour per IP; options-vol 20/hour per IP; login 5/min by email+IP; register 5/hour by IP; search 40/min per user; global api 60/min per IP.
- Per-request caps (EXISTING): backtest date range at most 1825 days (`BacktestController::MAX_RANGE_DAYS`, and again in `analytics/data/cache.py`).
- Operator gate (EXISTING): `is_platform_operator` for knowledge-pack routes.
- Plans, subscriptions, credits, billing: none (EXISTING absence).
- Anonymous use (EXISTING): `POST /api/backtests`, `/api/chart/analyze` and `/api/options/vol-signal/{symbol}` accept unauthenticated callers (routes in `backend/routes/api.php`), limited only by IP rate limits. This is the only open door that spends compute for people with no account.

## 4. Resources consumed or stored, and where to count them
| Resource | Where it can be read today | Status |
|---|---|---|
| Backtest runs per user/period/strategy/asset class | `backtest_runs` (user_id nullable, strategy, asset_class, status queued/complete/failed, results JSON, created_at) | EXISTING; anonymous runs have null user_id |
| Backtest compute seconds / cost | not recorded (no duration column) | NEEDS DEVELOPMENT |
| Result storage (equity curves in `results` JSON) | size of `backtest_runs.results` | EXISTING (derivable) |
| Saved strategies | `custom_strategies` | EXISTING |
| Journal entries | `journal_entries` | EXISTING |
| Chart analyses / OCR calls | not persisted; only the rate limiter key counts | NEEDS DEVELOPMENT |
| Options-vol reads | not persisted | NEEDS DEVELOPMENT |
| Market-data fetches | SQLite bar cache `analytics/data/ohlcv_cache.db` (cache only, no per-user count) | EXISTING cache, no metering |
| Knowledge packs / gate decisions | `knowledge_packs`, `dkp_gate_decisions` (operator, not customer) | EXISTING, not billable |
Scheduler (EXISTING, `backend/routes/console.php`): monthly knowledge-pack generation, every-minute scheduler heartbeat, nightly SQLite backup. No customer-driven background jobs; backtests run synchronously inside the request (60 s HTTP timeout to the engine, `AnalyticsServiceClient.php`).

## 5. Third-party services called
- Market data: `yfinance` (equity, commodity, forex, options chain) and `ccxt` with Binance (crypto), both free and keyless by an explicit owner decision (`wiki.md` section 8, "stay on free/keyless"). EXISTING. No paid vendor, no SLA. These are unofficial/scraped sources; commercial resale of derived output may breach their terms (PROPOSED: needs a licensing check before charging, not verified in code).
- OCR: `rapidocr-onnxruntime` run locally in a separate venv (`analytics/requirements-ocr.txt`), plus local `tesseract` fallback. No per-call fee, but CPU cost. EXISTING.
- AI/LLM: none called by this platform (EXISTING absence). The "ML signal" is a local scikit-learn gradient-boosting model retrained on every request, no model store (`analytics/strategies/ml_signal.py`). Compute-heavy, not API-priced.
- Mail: none sent by the code I read.
- Cost drivers: CPU and RAM on the shared host (vectorbt, backtrader, ML retraining, OCR), outbound market-data requests (rate-limit exposure at the free providers), SQLite storage.

## 6. Candidate purchase units, free tier, exhaustion behaviour
- Backtest compute units (PROPOSED, resource). One unit = one standard backtest; heavy strategies (`ml_signal`, `pairs_trading`, `method_714`, long ranges, intraday) cost more units. Reason: the real cost is CPU time, and the strategies differ by an order of magnitude. Needs a duration measurement first (see gaps).
- Saved history capacity (PROPOSED, capacity): number of stored backtest runs, saved strategies, journal entries beyond a free allowance.
- Chart analyses (PROPOSED, resource): OCR plus analysis counts, small unit.
- Keep free (PROPOSED): journal, strategy builder (the rule builder itself), disclosures, and a useful monthly allowance of standard backtests. Today's anonymous 3/hour and registered 30/hour already shape a free tier.
- Do not sell (PROPOSED, flagged): anything that reads as a signal, tip or advice, and the Knowledge Pack channel. The wiki records the platform as the ecosystem's only regulated-market surface; charging for outputs raises FSCA/FAIS questions that need legal advice. This is a risk flag, not a legal conclusion.
- When an allocation runs out (PROPOSED): block only new heavy backtests and new chart analyses, with a clear message and reset date; keep all saved runs, strategies and the journal readable, exportable and deletable. No cross-platform impact because Charts has no entitlement link.

## 7. Cross-platform dependencies and shareable resources
- Dependencies: none live. Dot.Brain publishing is built but has no endpoint. It mirrors the ecosystem registry (`backend/config/ecosystem.php`) and exposes a switcher endpoint (`EcosystemSwitcherController`). Guardian health endpoint for Dot.Brain (`/guardian/health`).
- Identity gap: it does not use InfoDot sign-in, so a shared wallet or entitlement cannot attach to a user or company until it adopts SSO (NEEDS DEVELOPMENT; the Forms/Agents/Analytics `ProvisionUserFromInfoDot` pattern exists elsewhere).
- Shareable (PROPOSED): a general "compute credit" only if the wallet design allows a non-AI compute unit; otherwise platform-specific. Storage credits (Dot.Files) are not relevant here.

## 8. Gaps to meter, enforce and audit
1. NEEDS DEVELOPMENT: identity. Without InfoDot SSO there is no company, so no wallet, no entitlement, no seat concept. Also anonymous runs cannot be charged to anyone.
2. NEEDS DEVELOPMENT: metering columns on `backtest_runs` (duration_ms, bars_processed, units_charged) and a persisted log for chart analyses and options reads.
3. NEEDS DEVELOPMENT: synchronous execution means a spent allocation must be checked before the engine call in `BacktestController::store`; there is no queue to defer or throttle by balance.
4. NEEDS DEVELOPMENT: the engine (`analytics/main.py`) has no authentication by owner decision (network isolation only, `wiki.md` section 2). Any enforcement must therefore sit in Laravel, and the engine port must stay private.
5. NEEDS DEVELOPMENT: audit of allocation movements (only `dkp_gate_decisions` exists as an audit pattern, for compliance).
6. NEEDS DEVELOPMENT: market-data source terms review before any paid tier; consider replacing free sources first (owner decision, not a code gap).
7. NEEDS DEVELOPMENT: the registry flags `charts` inactive in some copies; decide whether it belongs in the first monetisation wave at all (PROPOSED: last, after Analytics, Forms, Agents).
