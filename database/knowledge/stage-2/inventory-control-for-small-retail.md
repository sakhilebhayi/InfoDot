---
key: inventory-control-for-small-retail
title: Inventory control for a small shop: reorder points, counts, and where stock really disappears
topics: Running the business | Money and cash flow
tags: inventory, stock control, reorder point, safety stock, stocktake, cycle counting, shrinkage, FIFO
duration: 1
duration_type: months
evidence: Reorder-point and safety-stock methods from university operations teaching (UT Austin, NC State) and government small-business guidance; count methodology from published cycle-counting research; loss figures from the NRF's last published shrink survey and a 2024 South African study of small retailers, each with its stated limits; stock write-down tax treatment from SARS Interpretation Note 140.
---
Stock is usually a small retailer's biggest pile of cash, sitting on shelves — and it leaks three ways: you run out of what sells (lost sales), you over-order what doesn't (dead money), and some of it simply disappears (shrinkage, which is far from being all theft). This entry is the practical control system: a reorder point per item, counts that actually keep records honest, rotation for anything perishable, and a loss breakdown that tells you WHERE stock goes — plus the South African tax rule that makes documenting write-downs worth money.

## The problem
Three documented realities. Records drift: in the one large published retail study, 65% of nearly 370,000 inventory records at a single retailer were wrong (DeHoratius & Raman, Management Science 2008) — so a system that never counts is a system that lies. Reordering runs on supplier promises: lead times are assumed, not measured, and the US Chamber's guidance is pointed — a supplier who says two weeks but takes four needs a two-week cushion priced in. And shrinkage gets misdiagnosed as "theft": the last National Retail Security Survey to publish a rate (FY2022) measured US shrink at 1.6% of sales, split roughly external theft 36%, employee theft 29%, process/control failures and errors 27%, unknown 6% — about a third of loss is your own process, which you can fix without a single camera. (The NRF has since stopped publishing a shrink rate, so treat any newer "industry rate" with suspicion.)

## Who this is for
Shops, spazas, wholesalers, workshops with parts, restaurants with stock rooms — any business where goods sit between buying and selling. The methods scale down to a paper book and up to point-of-sale software.

## Step 1 — One clean baseline count
Count everything once, carefully, outside trading hours, and correct every record. Expect to find more errors than you believed possible (see the 65% finding above). This baseline is what every later count is judged against.

## Step 2 — Rank your items A, B, C
Compute each item's annual usage value (units sold × unit cost), sort, and split: the small set of items carrying most of the value is class A, the long tail is C (ABC analysis — the exact cut-offs are convention, not law). Then override by judgment: an item that stops the business when absent, or has a long lead time, is treated as A regardless of value (the cycle-counting literature's own warning that money-only ranking misses critical items).

## Step 3 — Set a reorder point per A item
The formula, from standard operations teaching (UT Austin; NC State's safety-stock tutorial): **reorder point = average demand during the lead time + safety stock**. Use MEASURED lead time — track what each supplier actually takes. For safety stock, the simplest credible small-business method (US Chamber): average of your three biggest sales days minus the average day, times the lead time's weeks; the statistical method sizes it as Z standard deviations of lead-time demand (≈1.65 standard deviations for a 95% service level). Worked example: you sell 10 units a day on average; the supplier really takes 10 days; your busy-day cushion works out at 30 units — reorder point = 10×10 + 30 = 130. When stock hits 130, you order. Order size is a balance, not a formula to worship: bigger orders cut ordering/delivery costs but tie up cash and space (the classic EOQ model exists — Harris, 1913 — but its assumptions of constant demand and no bulk discounts rarely hold in a shop; use the thinking, not the square root).

## Step 4 — Count small and often, not once a year
Replace the dreaded annual-only stocktake with cycle counting (count a slice on a schedule): A items most often, B less, C least — a common starting pattern is quarterly/twice-yearly/annually, adjusted to your reality (the published cycle-counting review calls frequencies a judgment call; count-accuracy tolerances of 0.5%/1%/5% for A/B/C are the APICS convention it reports). Two rules make counting worth it: investigate the CAUSE of each variance instead of silently adjusting the record, and count in quiet periods, not while stock moves. Keep one full year-end count for the books and insurance (and SA tax — see Step 7).

## Step 5 — Rotate perishables: first in, first out
Date-mark stock as it arrives and sell oldest first (FIFO) — or earliest-expiry-first where batches differ — checking dates during counts. This is food-safety practice from the FDA's retail manual (date marking makes FIFO workable) and it directly attacks the loss category the South African evidence ranks worst for small shops: a 2024 survey of 130 small and micro retailers in Tshwane found spoilage had the largest effect on profitability loss among loss types, ahead of internal theft and shoplifting (Tabane, Phume & Retief, SAJEMS 2024 — a single-city, perception-based study, but the only SA small-retail evidence of its kind).

## Step 6 — Classify every loss before you buy cameras
When a count finds stock missing, book it to a category: external theft, internal theft, process/admin error (receiving mistakes, uncaptured sales, breakage), spoilage, unknown. After three months the split tells you where to spend: process errors want better receiving discipline, spoilage wants rotation and smaller orders, theft wants the visible controls the SA study's respondents used (lighting, line-of-sight layout, CCTV where affordable, prosecution notices). Guard against folklore: the widely repeated claim that organised retail crime drives half of shrink was formally retracted by the NRF itself in December 2023.

## Step 7 — Document write-downs: SARS lets damaged stock reduce tax
South African tax treats closing stock at cost LESS a justifiable diminution in value — damage, deterioration, change of fashion, drop in market value (Income Tax Act s22(1)(a), as interpreted in SARS Interpretation Note 140 of 16 October 2025). Two practical consequences: keep evidence for every write-down (photos, a dated write-off register, the count sheet), and note that SARS does not accept last-in-first-out as a stock valuation basis. Your counts are the paperwork that makes the deduction defensible.

## Step 8 — Paper, spreadsheet or software
A single-location, low-item shop with disciplined recording can run on paper or a spreadsheet (a "periodic" system, in business.gov.au's terms). Move to a perpetual system — point-of-sale or inventory software that updates on every sale — when counts keep drifting from the records, or when multiple sales channels, suppliers or locations make manual updates error-prone. There is no magic item-count threshold; the trigger is record drift you can see in your own cycle counts.

## What to expect and how to measure it
Measure four numbers monthly: record accuracy from cycle counts (share of counted items matching the record — manufacturing surveys show disciplined cycle counters sustaining 90%+); stockouts of A items (target: none); days of stock on hand for the top sellers (falling is cash released); and shrinkage as % of sales by category. Improvements show within one to two count cycles; the cash effect of tighter reorder points shows in the bank balance before it shows anywhere else.

## Common mistakes and when to get help
Trusting the record because "the system says so"; reorder points built on promised lead times; money-only ABC that leaves a critical cheap part uncounted; adjusting variances without investigating causes; counting mid-rush; treating all shrink as customer theft (about a third is process, and in the SA small-shop evidence spoilage leads); undocumented write-offs that waste a legitimate tax deduction; and buying inventory software to fix what is actually a receiving-discipline problem. An accountant should see your year-end count and write-off register; a stocktake service makes sense only once item counts genuinely exceed what a closed Sunday can cover.

## Sources and further reading
Stochastic inventory models (reorder point, safety stock) — Paul A. Jensen, University of Texas at Austin — https://utw11041.utweb.utexas.edu/ORMM/omie/computation/unit/invent_add/stochastic.html — ©2004.
Safety stock analysis: inventory management models — NC State Supply Chain Resource Cooperative — https://scm.ncsu.edu/scm-articles/article/safety-stock-analysis-inventory-management-models-a-tutorial — 28 January 2011.
How to estimate inventory (safety stock, lead times) — US Chamber of Commerce (Emily Heaslip) — https://www.uschamber.com/co/start/strategy/how-to-estimate-inventory — 24 August 2021.
Manage your inventory — business.gov.au — https://business.gov.au/products-and-services/inventory-management/manage-your-inventory — undated.
Inventory Cycle Counting – A Review — Rossetti, Collins & Kurgund, University of Arkansas (IERC 2001) — https://bpb-us-e1.wpmucdn.com/wordpressua.uark.edu/dist/7/795/files/2016/08/ierc_2001_a_kurgand.pdf — 2001.
Inventory record inaccuracy: an empirical analysis — DeHoratius & Raman, Management Science 54(4):627-641 — doi:10.1287/mnsc.1070.0789 — 2008.
National Retail Security Survey 2023 (FY2022 shrink rate and causes) — National Retail Federation — https://nrf.com/research/national-retail-security-survey-2023 — 26 September 2023 (cause split per the Loss Prevention Research Council release, https://lpresearch.org/2023-nrss-release-9-26-23/).
NRF retraction of the organised-retail-crime claim — Retail Dive — https://www.retaildive.com/news/nrf-updates-crime-report-faulty-numbers/701396/ — 4 December 2023.
Managing Food Safety: A Regulator's Manual (FIFO, date marking) — US FDA — https://www.fda.gov/media/72067/download — April 2006.
The effect of stock losses on profitability and sales of small and micro retail businesses in Tshwane — Tabane, Phume & Retief, South African Journal of Economic and Management Sciences 27(1) a5410 — https://sajems.org/index.php/sajems/article/view/5410/3052 — 18 June 2024.
Interpretation Note 140: diminution in the value of closing stock — SARS — https://www.sars.gov.za/wp-content/uploads/Legal/Notes/Legal-IntR-IN-140-Diminution-in-the-value-of-closing-stock.pdf — 16 October 2025.
How Many Parts to Make at Once — Ford W. Harris, Factory 10(2):135-136 — 1913 (reprinted Operations Research 38(6):947-950, 1990, doi:10.1287/opre.38.6.947).
Last reviewed: 2026-10-31.
