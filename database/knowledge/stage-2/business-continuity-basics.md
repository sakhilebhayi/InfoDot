---
key: business-continuity-basics
title: A one-page continuity plan: survive the fire, the riot, the crash and the key person leaving
topics: Running the business | Legal and compliance
tags: business continuity, backups, insurance, Sasria, business interruption, key person, suppliers, disaster recovery
duration: 1
duration_type: weeks
evidence: Planning method from government continuity guidance (Ready.gov/FEMA, the UK Cabinet Office small-business toolkit, business.gov.au) and agency backup rules (CISA, NCSC); the South African insurance mechanics read from Sasria's own policy wording and FAQ; survival statistics handled honestly — the famous ones are unsourced, so the one real study is cited instead.
---
Most small businesses have no written answer to "what happens if the shop burns, the server dies, the street riots, or the one person who knows the system resigns?" The honest case for fixing this isn't the famous statistic — the widely quoted "40% of businesses never reopen after a disaster" has no traceable study behind it (US government-linked sources variously say 25%, 40% and 40–60%, none naming their evidence) — it's the real research: a Purdue University study of small businesses after Hurricane Katrina found that having property insurance before the storm was associated with survival, that longer-established and larger firms survived better, and that about a quarter of owners couldn't get affordable cover AFTER the event. Preparation is cheap, it's what correlates with survival, and most of it fits on one page.

## The problem
Four single points of failure recur: data (backups connected to the same machine the ransomware encrypts, or never test-restored), people (one person who alone can quote, invoice or run the machine), suppliers (one source for the critical input), and insurance assumptions (discovering after the event that riots, load-shedding losses or theft-during-looting weren't covered). On the data myth specifically: the claim that "60% of small businesses close within six months of a cyberattack" was publicly disowned by the organisation it was attributed to — treat every dramatic unsourced statistic in this field with suspicion, and prepare anyway.

## Who this is for
Every business — the plan scales from a spaza to a factory. South African specifics (Sasria, grid-failure exclusions) get their own steps.

## Step 1 — List what actually earns the money
The 3–5 products or services that bring in revenue, and for each: the people, premises, equipment, information and suppliers it depends on (the UK Cabinet Office's small-business continuity toolkit and business.gov.au both start exactly here).

## Step 2 — Run the one-hour impact analysis
For each critical function ask: what breaks after one day, one week, one month of disruption — operationally and in rand? Which function must come back first? (Ready.gov's business impact analysis.) Set two targets per critical system: how fast it must be restored, and how much data loss you can tolerate — these two numbers decide your backup frequency and your workarounds.

## Step 3 — Fix the data single-point-of-failure
The 3-2-1 rule (CISA): three copies, two different media, one off-site — and the UK NCSC's addition that matters most: at least one copy OFFLINE (disconnected when not in use), so no single incident can reach all your backups. Then restore-test it: a backup you've never restored is a hope, not a plan. Back up everything the business needs to operate — invoicing, documents, contacts, the website — not just "the important folder".

## Step 4 — Fix the people single-point-of-failure
For each critical task, the UK toolkit's method: document the process (a one-page SOP) and cross-train a second person. Add a skills inventory (who can do what, including skills not used in current roles) and a one-line succession note for the owner/manager role. This costs nothing and removes the most common small-business fragility.

## Step 5 — Fix the supplier single-point-of-failure
Name an alternative supplier for every critical input BEFORE you need one (Ready.gov's brochure: "identify additional suppliers just in case"); where the stakes justify it, split orders across two sources, ask key suppliers about their own continuity arrangements, and keep buffer stock of the one item that stops production.

## Step 6 — Match insurance to the plan, South African edition
Four verified mechanics that surprise people:
- **Riot, strike and public-disorder damage is NOT in conventional policies** — in South Africa it's covered by Sasria, the state special-risk insurer. You cannot buy Sasria cover directly: it attaches to an underlying policy through your insurer or broker, and must be in force at the time of the event. After the July 2021 unrest Sasria paid out over R32 billion across ~17,500 claims — cover that only existed for businesses that had attached it beforehand.
- **Sasria does not cover theft**, and looting only when it occurs during a covered peril; its business-interruption cover requires physical damage to property insured under a Sasria material-damage policy and excludes consequential loss.
- **Load-shedding/grid-failure income losses are widely excluded**: at least one major insurer (Santam) introduced an explicit grid-failure exclusion in commercial lines from 2023. Never assume power-cut losses are insured — read the wording.
- **The usual small-business set**: property/contents, business interruption (check what triggers it — typically physical damage), public liability (third-party injury/property damage), professional indemnity where you sell advice or professional services, plus Sasria. Disputes with your own insurer go to the Ombudsman for Short-term Insurance (osti.co.za) — after you've complained to the insurer first.

## Step 7 — Write the single page and keep a copy off-site
Headings (condensed from Ready.gov's template): who's in charge and the stand-in; the critical functions in restore order; the workaround for each (manual invoicing, the backup supplier, the alternate workspace — even "the owner's garage"); the restore procedure and where backups live; and THE CONTACT LIST — staff, key customers, suppliers, insurer AND broker with policy numbers, bank, landlord — printed, because the contacts file may be inside the dead laptop. Store a copy away from the premises.

## Step 8 — Test it once, review it on change
The cheapest test is a talk-through (the UK toolkit's discussion exercise): one hour, walk the team through "the shop burned down last night" and write down every gap the conversation finds. Re-run the one-pager whenever staff, premises, suppliers or systems change — and do one real restore-test of backups on a calendar schedule.

## What to expect and how to measure it
Five measurable states: last successful restore-test date (within the quarter); number of critical tasks with a trained second person (target: all); critical inputs with a named alternative supplier (all); insurance reviewed against Step 6's four mechanics within the last year, Sasria attached; the one-pager printed, off-site, and under 12 months old. The Katrina evidence says survival correlates with being insured and established before the event — all of which is decided now, not during the emergency.

## Common mistakes and when to get help
Backups connected 24/7 or never restored; plans that live only in the owner's head; "insurance covers it" assumed rather than read (riots, theft-in-looting, grid failure); buying Sasria after the unrest starts (cover can't generally be increased once an event is under way); single suppliers for the make-or-break input; and a beautiful plan nobody has talked through. Use a broker to review the insurance stack against your actual risks (that's what they're paid for), and your accountant for how much cash buffer the impact analysis implies. Quoting survival statistics in your own planning documents? Use the Purdue/Katrina study and skip the folklore numbers.

## Sources and further reading
Small business recovery following a natural disaster (Hurricane Katrina study) — Purdue Agricultural Economics (Wiatt & Marshall), PAER-2019-04 — https://ag.purdue.edu/commercialag/home/paer-article/small-business-recovery-following-a-natural-disaster — 5 June 2019.
When disaster strikes: preparation, response and recovery — Insurance Information Institute — https://www.iii.org/article/when-disaster-strikes-preparation-response-and-recovery-for-your-business — undated (the unsourced 40%/25% figures appear here and in FEMA P-1075, April 2019 — https://www.ready.gov/sites/default/files/2020-03/ready_emergency-financial-first-aid-toolkit.pdf — cited as examples of the unverifiable claim).
How a fake cyber statistic raced through Washington (the "60% close in six months" debunk) — Nextgov — https://www.nextgov.com/cybersecurity/2017/05/how-fake-cyber-statistic-raced-through-washington/137542 — 3 May 2017.
Back Up Business Data (3-2-1) — US CISA — https://www.cisa.gov/audiences/small-and-medium-businesses/secure-your-business/back-up-business-data — undated; Offline backups in an online world — UK NCSC — https://www.ncsc.gov.uk/blog-post/offline-backups-in-an-online-world — 13 August 2019 (modified 28 July 2026).
Business impact analysis; IT disaster recovery; business continuity plan template — Ready.gov (FEMA) — https://www.ready.gov/business/planning/impact-analysis, https://www.ready.gov/business/emergency-plans/recovery-plan and https://www.ready.gov/sites/default/files/2020-03/business-continuity-plan.pdf — undated.
Business Continuity Management Toolkit (small and medium organisations) — UK Cabinet Office — https://assets.publishing.service.gov.uk/media/5a7b283de5274a34770e9d01/Business_Continuity_Managment_Toolkit.pdf — undated.
When things don't go to plan — business.gov.au — https://business.gov.au/New-to-business-essentials/When-things-dont-go-to-plan — undated.
Sasria: who we are, claims FAQ, and business-interruption wording — Sasria SOC Ltd — https://www.sasria.co.za, FAQ https://www.sasria.co.za/wp-content/uploads/2021/07/Frequently-Asked-Questions-FAQ-Sasria-Claims-Underwriting-1.pdf (27 July 2021), BI wording https://sasria.co.za/wp-content/uploads/2026/02/Business-Interruption.pdf (February 2026).
Sasria pays out over R32bn after the 2021 riots — Moonstone — https://www.moonstone.co.za/97-of-claims-settled-sasria-pays-out-over-r32bn-in-wake-of-2021-riots/ — 10 July 2023.
Santam introduces grid-failure exclusions — Moonstone — https://www.moonstone.co.za/santam-introduces-exclusions-for-losses-following-loadshedding-and-other-electricity-grid-interruptions/ — 1 December 2022.
Business interruption complaints and the Ombudsman for Short-term Insurance — FAnews — https://www.fanews.co.za/article/compliance-regulatory/2/short-term-ombudsman/1025/business-interruption-complaints-and-the-role-of-the-ombudsman-for-short-term-insurance/29248 — 3 June 2020.
Last reviewed: 2026-10-31.
