---
key: measuring-customer-acquisition-cost
title: Measure what a new customer costs you, and what they are worth
topics: Customers and sales | Money and cash flow
tags: customer acquisition cost, CAC, lifetime value, marketing ROI, ROAS, payback period, tracking
duration: 2
duration_type: weeks
evidence: Industry-standard metrics as defined by their originators and major teaching institutions (David Skok's forEntrepreneurs, Andreessen Horowitz, Harvard Business School Online, Corporate Finance Institute), with the benchmarks presented as the heuristics their own authors say they are.
---
Most small businesses spend on marketing without knowing the one number that decides whether the spending works: what a paying customer costs to win, compared with what that customer is worth. This entry is the arithmetic — customer acquisition cost (CAC), a simple lifetime value (LTV), and the payback period — plus a way to track it with nothing more than a spreadsheet and the discipline to ask every new customer one question. None of it needs analytics software.

## The problem
Three measurement failures hide whether marketing pays. First, counting only the advert: the real cost of acquiring customers includes the people's time, commissions, discounts and referral fees, and leaving those out flatters every channel (Andreessen Horowitz lists omitted costs as a classic metric mistake; Corporate Finance Institute's definition includes salaries, overhead and commissions). Second, judging paid channels on a blended average: when organic and paid customers are mixed, a worsening paid channel hides behind free word of mouth (a16z calls blended CAC a common problem and says paid CAC shows whether spend can scale). Third, valuing a customer by revenue instead of profit margin: a customer who brings R1,000 of revenue at a 20% margin is worth R200 towards costs, not R1,000 — computing LTV on revenue is the error a16z, Harvard Business School Online and CFI all warn against.

## Who this is for
Any business that spends money or meaningful time to win customers — adverts, flyers, commissions, a salesperson, your own selling hours — and especially a business with repeat customers, where the value of a customer unfolds over months and the arithmetic genuinely changes decisions.

## Step 1 — Compute your CAC twice
Pick a period (a month or a quarter). Count the NEW paying customers won — customers, not enquiries. Then compute two versions of CAC = money spent ÷ new customers (the formula as David Skok defined it: total sales and marketing cost over deals closed):

1. **Programme CAC**: adverts, printing, agency fees, promotional discounts, referral fees, tools.
2. **Fully loaded CAC**: all of that plus the people — the salesperson's pay for the period, or an honest rand value on your own selling hours, plus commissions.

Write down exactly what you counted, because the sources themselves differ in scope (HBS Online's worked example counts only marketing cost; Skok, a16z and CFI include people) and a CAC is only comparable with itself if the ingredients stay constant.

## Step 2 — Separate blended from per-channel CAC
Blended CAC (all spend ÷ all new customers) tells you the trend of the whole machine. Channel CAC (that channel's spend ÷ customers that channel produced) tells you where the next rand should go. Judge each paid channel on its own CAC, never on the blend — a16z's "16 Startup Metrics" is blunt that blended numbers hide whether paid acquisition actually works.

## Step 3 — Compute a simple lifetime value on margin, not revenue
A workable small-business LTV is HBS Online's m × T: the yearly contribution margin a customer gives you (their yearly purchases × your gross margin on them, minus any direct cost of serving them) times the years a typical customer stays. If you know your churn instead — the fraction of customers you lose per year — average lifetime is roughly 1 ÷ churn (Skok's formulation). Where you have history, the most honest version is a16z's conservative route: add up the actual margin your customers delivered in their first 12 or 24 months and use that, rather than projecting decades of loyalty. One caution from the academic side: using a single average retention rate for everyone understates long-run value in subscription-like businesses (Fader and Hardie, Marketing Science, 2010) — the simple method is a decision tool, not a valuation.

## Step 4 — Hold the two numbers against the heuristics, as heuristics
The widely quoted rule of thumb is LTV of at least 3× CAC, with acquisition cost recovered within about 12 months. Both trace to David Skok's SaaS metrics guides (2010 and 2013), and he is explicit that they are guidelines from the SaaS world, not laws — "the above guidelines are not hard and fast rules", and healthy young businesses often miss them at first. HBS Online repeats "generally considered at least three"; CFI notes that above 3.0 is "considered good, but that's not necessarily the case". Use them as a conversation with your own numbers: LTV:CAC near 1 means every customer barely repays their acquisition; a very high ratio with slow growth can mean you are underspending on acquisition (Skok's 2013 point).

## Step 5 — Compute payback, because cash comes before profit
Payback in months = CAC ÷ monthly gross margin per customer (CFI's formula). A worked example: you spend R15,000 in a month (R12,000 adverts + R3,000 of valued selling time) and win 10 customers, so CAC is R1,500. Each customer yields about R900 of gross margin a year, which is R75 a month — so payback is 20 months, and three-year LTV is R2,700, an LTV:CAC of 1.8. That combination fails both heuristics: either the customers must spend more, stay longer, or cost less to win. Payback is also a cash number — Skok's warning is that a 12-month payback means funding a full year of acquisition spend before it returns.

## Step 6 — Track the inputs with a spreadsheet and one question
You do not need analytics infrastructure:
- **Ask every new customer "how did you hear about us?"** at enquiry or first sale and log the answer — guidance as plain as the SBA's and the UK's NI Business Info for evaluating ad spend. Treat the answer as one signal, not the whole truth: memory is fallible and the question is ambiguous between "first heard" and "what convinced you" (Rand Fishkin's SparkToro critique) — so pair it with the harder signals below.
- **Give each offline channel its own code**: a different voucher code or phone extension per flyer, radio spot or publication (NI Business Info's method).
- **Tag every link you share** with Google's UTM parameters (utm_source, utm_medium, utm_campaign), keeping names lowercase and consistent — "SpringSale" and "spring_sale" count as different campaigns (Google Analytics URL-builder documentation).
- **Group customers by the month you won them** and note, at 3, 6 and 12 months, how many still buy. That one cohort table is your real-world LTV forming in front of you.

## Step 7 — Read ROAS for what it is
If you run paid digital adverts you will meet ROAS: campaign revenue ÷ ad cost (Google's definition uses conversion value over spend). Two limits matter (CFI): a 1:1 ROAS only breaks even on revenue — at a 50% margin you need 2:1 just to break even on that sale — and ROAS sees a single purchase, not the customer's lifetime. Decide your own break-even ROAS from your margin before judging any campaign, and ignore generic benchmark ratios; the widely circulated ones have no cited study behind them.

## Common mistakes and when to get help
Counting leads instead of paying customers; changing what goes into CAC month to month and comparing the results anyway; computing LTV on revenue; judging a paid channel on blended CAC; treating 3:1 as a pass mark rather than a prompt (it came from SaaS, and its author calls it a guideline); ignoring payback while cash runs out; and inconsistent link tagging that splits one campaign into five. One legal note for South African businesses: the contact details you collect in your "how did you hear about us" log are personal information — POPIA section 69 restricts electronic direct marketing to people who consented or are existing customers being offered similar products, every message must identify you and offer an opt-out, and the Information Regulator (inforegulator.org.za) enforces it. If the arithmetic says customers cost more than they are worth and you cannot see why, a session with an accountant on your margins usually finds it faster than more marketing spend.

## Sources and further reading
SaaS Metrics – A Guide to Measuring and Improving What Matters — David Skok, forEntrepreneurs — https://web.archive.org/web/20100220000610/http://www.forentrepreneurs.com:80/saas-metrics/ — 2009–2010 (Internet Archive capture 20 February 2010).
SaaS Metrics 2.0 — David Skok, forEntrepreneurs — https://www.forentrepreneurs.com/saas-metrics-2/ — 16 January 2013.
16 Startup Metrics — Andreessen Horowitz — https://a16z.com/16-startup-metrics/ — 21 August 2015.
How to Calculate LTV/CAC Ratio — Harvard Business School Online — https://online.hbs.edu/blog/post/ltv-cac — 6 March 2025.
Customer Acquisition Cost (CAC) — Corporate Finance Institute — https://corporatefinanceinstitute.com/resources/accounting/customer-acquisition-cost-cac/ — 25 August 2019.
CAC Payback Period — Corporate Finance Institute — https://corporatefinanceinstitute.com/resources/valuation/cac-payback-period/ — 6 May 2024.
Return on Ad Spend (ROAS) — Corporate Finance Institute — https://corporatefinanceinstitute.com/resources/valuation/return-on-ad-spend-guide-finance/ — 7 January 2025.
Evaluate advertising spend — NI Business Info — https://www.nibusinessinfo.co.uk/content/evaluate-advertising-spend — undated.
How to Get the Most From Your Marketing Budget — U.S. Small Business Administration blog — https://www.sba.gov/blog/2019/2019-07/how-get-most-your-marketing-budget/ — 9 July 2019.
Collect campaign data with custom URLs — Google Analytics Help — https://support.google.com/analytics/answer/10917952 — undated.
The 3 Big Problems with Asking "How Did You Hear About Us?" — Rand Fishkin, SparkToro — https://sparktoro.com/blog/the-3-big-problems-with-asking-how-did-you-hear-about-us-5-minute-whiteboard/ — 20 September 2023.
Customer-Base Valuation in a Contractual Setting: The Perils of Ignoring Heterogeneity — Peter Fader and Bruce Hardie, Marketing Science 29(1) — https://ideas.repec.org/a/inm/ormksc/v29y2010i1p85-93.html — 2010.
Protection of Personal Information Act 4 of 2013 (section 69, direct marketing) — Government Gazette 37067 — https://www.gov.za/sites/default/files/gcis_document/201409/3706726-11act4of2013protectionofpersonalinforcorrect.pdf — 26 November 2013.
Last reviewed: 2026-10-31.
