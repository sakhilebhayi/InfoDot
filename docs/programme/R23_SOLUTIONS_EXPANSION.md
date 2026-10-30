# R-23 — Business Solutions research expansion, Stage 2 (2026-10-31)

The owner's brief is verbatim in `requirements/infodot-solutions-research-expansion.md`. This
report covers the first execution batch: the gap audit, the research method, what Stage 2 adds,
and what remains.

## Audit and gap map

Stage 1 (R-14, live since 2026-10-30) holds 8 survival entries: first customers, getting paid,
retention, one-page plan, pricing, registration, cash-flow forecast, separating money. Against the
brief's lifecycle map, the biggest unserved areas were: funding; tax and employer compliance;
operations (SOPs, inventory); the measurement arithmetic (CAC, break-even); technology choices and
security; POPIA; continuity/insurance; and selling to government. Stage 2 targets exactly those.

## Research method (brief §§1, 3, 5)

Four parallel research passes gathered VERIFIED SOURCE PACKS before any writing: every
load-bearing claim carries its source URL, publisher and date; primary sources preferred (SARS,
National Treasury instruments, the Information Regulator's own guidance, Department of Employment
and Labour gazettes, Sasria's policy wording, EPA/NASA/CISA/NCSC, peer-reviewed studies by DOI);
conflicts reported rather than resolved silently; and each pack lists the tempting-but-unverified
claims that were deliberately EXCLUDED (famous unsourced statistics, retracted claims, secondary
figures). Notable catches that would otherwise have shipped as fact: the sefa/SEDA→SEDFA merger
(Oct 2024); the VAT threshold move to R2.3m with SARS's own promulgation caveat; the 2022
preference-points change (specific goals, not B-BBEE levels); the Public Procurement Act struck
down 17 Sep 2026; the 2025 Code of Good Practice on Dismissal replacing Schedule 8; the "40% never
reopen" and "60% close after a cyberattack" statistics having no traceable basis (the latter
disowned by its supposed source); and the NRF's retraction of the organised-retail-crime claim.

## Stage 2 — 12 entries (all validated by the live pipeline; `knowledge:seed --dry` green; local
publish + idempotent republish verified)

| Key | Area | Confidence |
|---|---|---|
| funding-for-south-african-small-businesses | A/D — SEDFA, NYDA, NEF, IDC, dtic; decline reasons; scams | High (institutions' own pages; amounts date-sensitive) |
| sars-compliance-for-small-businesses | D/G — registrations, VAT, turnover tax, SBC, PAYE cycle, penalties | High (all SARS; 2026 thresholds carry SARS's own promulgation caveat) |
| selling-to-government | C/H — CSD, RFQ/tender lanes, PPR 2022 points, SBDs, 30-day rule, scams | High (Treasury instruments + live tender documents) |
| hiring-your-first-employee | F/G — s29 particulars, NMW, UIF/COIDA/PAYE, 2025 dismissal Code, CCMA | High (statute + gazettes; wage resets annually) |
| popia-for-small-businesses | G — IO registration, 8-conditions programme, s69 marketing, PAIA, real fines | High (Regulator's own texts) |
| small-business-cybersecurity-baseline | E/G — NCSC/CISA baseline + POPIA s19/s22 overlay + SABRIC fraud patterns | High |
| business-continuity-basics | G — one-page plan, 3-2-1 offline, Sasria mechanics, BI/grid exclusions | High for mechanics; survival stats handled honestly (Katrina study cited, folklore flagged) |
| measuring-customer-acquisition-cost | C/D — CAC/LTV/payback + spreadsheet tracking + POPIA s69 note | High for formulas; benchmarks presented as their authors' heuristics |
| break-even-and-margins | D — contribution margin, break-even, markup-vs-margin, rent what-if | High (SBA + OpenStax + HBS) |
| choosing-business-software | E — requirements-first selection, SA pricing (dated), lock-in, POPIA operators | High for method; prices time-stamped 2026-10-31 |
| writing-sops-that-get-used | B — EPA/NASA method + checklist evidence INCLUDING the null results | High, with counter-evidence cited |
| inventory-control-for-small-retail | B/I — reorder points, cycle counts, shrink (honest figures), FIFO, SARS IN 140 | High for methods; loss stats with stated limits |

House format throughout (front-matter, steps, sources section with per-item dates and a Last
reviewed line, enforced by the seeder's validator). Three new topics enter the catalogue through
the seeder's normal moderation-trailed path: Technology and tools, Running the business, People
and teams.

## Duplicate and overlap control (brief §5, §8)

Titles checked against Stage 1 and the seeder's own publish-time guard (which refuses a new entry
whose title matches a community solution). Overlaps handled by cross-reference, not duplication:
CAC entry points to the retention entry; cybersecurity and POPIA entries are companions (breach
drill lives in security, the compliance programme in POPIA); funding entry defers business-plan
mechanics to the Stage-1 plan entry.

## Publishing steps (same as Stage 1)

1. Merge this PR, deploy InfoDot.
2. On the host: `php artisan knowledge:seed --dir=database/knowledge/stage-2` (idempotent by
   seed_key; `--dry` first if desired).
3. Verify: entries visible under the official account, search returns them, topic pages exist.

## Remaining (later batches)

Industry-specific solutions (brief §2-I: agriculture, construction/CIDB, hospitality, transport…),
deeper growth topics (franchising, exporting, investment-readiness), HR beyond the first hire, and
the 6-monthly regulatory re-verification sweep for every date-sensitive entry (VAT thresholds,
minimum wage, earnings threshold, funding programmes, software prices — each entry's sources
section carries its dates).
