# R-50 audit notes: platform inventory (24 repos, read at remote default branches, 2026-11-13)

## Fleet-wide
- Default branch `main` everywhere except Dot.Analytics (`feature/ecosystem-sso`).
- Stack: Laravel 13 + Jetstream 5.5 + Livewire 3.6 + PHPUnit. Exceptions: Files and mines on Laravel 12; Press is
  Inertia 2 + Vue 3; Charts is a Laravel 12 API with a vanilla-JS multi-page Vite front end and no Tailwind.
- Tailwind 4.3: Central, Engage, Forms, Press, Sheet, Tutor, docs, mines. Tailwind 3.4: the rest.
- Deploy on merge: Central, mines, Dot.docs. Manual everywhere else.
- After InfoDot sign-in every app goes to `route('dashboard')`, except Files (`/files`), Pulse (`/floor`), Tutor
  (role home). mines and Charts have their own login, no InfoDot sign-in.
- Tenant: `teams.infodot_team_uuid` in all SSO apps (Dopemine and Memory map a token to a local user only).
- NO app reads a plan or entitlement from InfoDot. NO app has an in-app switcher; only Finance and Pulse link back
  to InfoDot from the app layout.
- Each app carries its own hard-coded `config/ecosystem.php` (22 distinct versions). Every copy still lists the
  three discontinued keys; default URLs are `*.infodot.app` with keys sheet/press/docs while live hosts are
  sheets/pres/doc on infodot.co.za.
- Logos: every repo has a PNG logo (`public/images/logo.png` + `logo-light.png`, mostly 1181x886; some 2362x2362);
  no SVG masters anywhere. Tutor's `logo.png` is 128x128 (larger files exist). Finance, Pulse, Press, Engage and
  Ehail draw an inline SVG mark in the app chrome instead of the PNG. Files' layouts reference no logo.
- All 24 base URLs answer 200.

## Per platform (lands on; primary workflow; design identity; own billing)
- Agents: /dashboard "Command centre" status board; `marketplace`; The Signal Box; Stripe subscriptions + /billing/plans.
- Analytics: /dashboard five panels, generic; `workspace.ask`; DESIGN.md unnamed; dormant free/plus plan config.
- Auction: /dashboard "Home", two competing actions; `seller.auctions.create` / `auctions.index`; Rostrum; Paystack transactional.
- Central: /dashboard "Operational Overview" control room; `control-rooms.shifts.start`; control-room; none.
- Design: /dashboard "Home" Start something; `design.start`; The Light Table; none.
- Dopemine: /dashboard "Mechanic Catalog", generic, inline styles; admin catalog; none recorded; none. (internal)
- Ehail: /dashboard role buttons + stats, old inline-styled look; `rides.create`; Route Line; Paystack transactional.
- Emall: /dashboard -> seller.dashboard or account.home; `products.index`; Shelf Edge; Paystack transactional.
- Engage: /dashboard "Desk", clear primary; `engagements.create`; The Margin; none.
- Files: /files browser, no landing; upload in browser; own DESIGN.md; none.
- Finance: /dashboard "Command centre"; `transactions.create`; The Tie-Out; none.
- Forms: /dashboard "The desk", three buttons; `teams.forms.builder`; The Carbon Book; none.
- HR: /dashboard greeting + pending leave (only real personalisation); `leave-requests.create`; The Register; none.
- Memory: /dashboard knowledge overview; `knowledge.index`; control-room; none. (internal)
- Notify: /dashboard "Notification Hub", generic admin; managers on page; none recorded; none.
- Press: /dashboard deck library + New presentation dialog; new deck -> `press.editor`; The Spine; none.
- Projects: /dashboard filterable table; `projects.create`; The Programme; none.
- Pulse: /floor round + knock sheet; answer/knock; The Floor; none.
- Sheet: /dashboard "Your spreadsheets"; `spreadsheets.create`; The Dot Grid; none.
- Tasks: /dashboard "Overview" stats + boards, semi-generic; `task-lists.create`; The Job Card; none.
- Tutor: learn.home or /dashboard, semi-generic; `tutors.browse`; Reading Room; Paystack + local course Entitlement.
- docs: /dashboard "Your desk" counts + recent, KPI-tile pattern; "New document"; design notes; none.
- mines: /dashboard ops dashboard; fleet/map; control-room; OWN subscriptions, plans, Paystack, machine entitlements.
- Charts: own login then /backtest.html static tool; backtest; instrument skin; none.

## Findings
1. No platform has the target landing composition (logo, headline, exactly one dominant action). 14 are designed
   in their own system; Analytics, Dopemine, Ehail, Files, Memory, Notify, Projects, Tasks, Tutor, Charts are
   generic or lists.
2. Naming to settle centrally: Sheet (Dot.Sheet / host sheets / login title "Dot.Sheets"); docs (Dot.docs /
   APP_NAME Dot.Doc / host doc); Press (host pres); Forms logo alt "Dot Forms"; mines login title "Mines";
   Charts folder ChartSense.
3. Billing to reconcile with central entitlements: mines (real plans + Paystack), Agents (Stripe subscriptions),
   Analytics (dormant plans), Tutor (course entitlements), Auction/Emall/Ehail/Tutor (transactional only).
4. Thin or internal: Dopemine, Memory, Notify, Files (one screen), Charts (static tool, separate auth).
5. Suggested landing order: Engage, Sheet, Press, Design, Forms; then Tasks, Projects, Finance, HR, Auction; then
   Emall, Tutor, Ehail; then Agents, Analytics; then Pulse, Files; then Notify, Memory, Dopemine; last the three
   that deploy on merge (Central, docs, mines) and Charts.

Not verified: host env URL overrides; what signed-in pages actually render (descriptions are from code).
