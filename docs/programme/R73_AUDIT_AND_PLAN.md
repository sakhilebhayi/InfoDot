# R-73: homepage storytelling, audit and phased plan

Brief: `R73_BRIEF.md` (owner, 2026-11-26, verbatim). Read-only audit of `main` at `7a108ab`; nothing changed yet.
Design work goes through the installed design skills (impeccable for the refinement inside the Yard system, gsap for the
scroll moments, design-taste for the anti-slop check), inside InfoDot's own identity ("The Yard"), not a new look.

## 1. What the homepage is today (welcome.blade.php, 11 blocks)

| # | Block | Heading today | Purpose | Keep / change |
|---|---|---|---|---|
| 1 | Hero (`partials/home-hero*`) | A better, more connected way to build a business. | Promise, search, Join + "See how it works", three picture scenes, library ledger and a real solution panel | **Keep** the heading, search and scenes; new supporting line (the dream); secondary CTA becomes "Explore the ecosystem" |
| 2 | `hp-reality` | The truth is, most businesses don't really know what they're doing. InfoDot is here to change that. | Six recognisable problems, then "not ambition, access" | **Rewrite** to the brief's Section 3 message; the six problems stay (they already match the brief's list), reworded so no business is called incapable |
| 3 | `hp-pillars` | Four things every business needs. One place to find them. | Knowledge, Tools, Connections, Progress, each with what works today | **Rework** into Section 4 (the support): what the connected ecosystem does, by capability, not a pillar card grid |
| 4 | `hp-week` | What a week with InfoDot can look like. | Five ordinary days, each using a live feature | **Move** into Section 5 as the "imagine" proof, or drop if Section 5 says it better (decided in phase 2 with the layout) |
| 5 | `hp-tools` | Your business. Your tools. One connected ecosystem. | Logo wall in eight goal groups; link to `/business-tools` | **Replace** with Section 5's interactive Start/Manage/Connect/Grow showcase, fed by the verified `BusinessTools` data |
| 6 | `hp-chain` | How a question becomes knowledge | Asked, answered, accepted, verified | **Fold** into Section 6 (access to knowledge) |
| 7 | Library (`lp-library`) | A library you can act on | Real researched entries, count from the database | **Keep**, inside Section 6 |
| 8 | `hp-who` | Wherever you are, there's a next step. | Five stages, idea to established | **Fold** into Section 2 (the ambition) as "wherever you are starting from" |
| 9 | `partials/aim` | The one-million-businesses aim (R-70) | Where InfoDot is going | **Keep**, as Section 6's closing line (the aim is about access) |
| 10 | `hp-join` | What opens when you join. | Five things waiting after sign-up; free | **Fold** into Section 7 (the action): the reasons to join, said once |
| 11 | `hp-trust` | A place you can rely on | Company-first visibility, verification, credit, guidance-not-advice | **Keep** as a compact band before Section 7 (trust before the ask) |
| 12 | `hp-cta` | Build your business with InfoDot in your corner. | Final CTA | **Replace** with Section 7's message and CTAs |

Problems in today's page the brief asks to remove:
- **Repetition:** "Join InfoDot for free" appears four times, plus a "Start for free". "Free" appears ten times across the
  page and the hero. The tools are introduced three times (pillar 2, the week, the tools section).
- **Tone:** the reality heading calls most businesses clueless. The brief says not to portray businesses as incapable.
- **No ambition moment:** the page goes straight from the promise to the problem. Nothing speaks to the person behind
  the business.
- **The ecosystem is a logo wall:** each group shows logos only. The problem each platform solves, what you do with it
  and its limits (the catalogue's `note`, for example Dot.Ehail needing a routing service) are on `/business-tools`, not
  on the homepage.

## 2. Verified facts the page may use (nothing else)

- **The ecosystem:** `BusinessTools::page()` shows a platform only when the registry says it is live and public, the
  catalogue describes it, and its logo and screen are bundled. Each has an outcome, a problem, a "why", "growth", a set-up
  `note` where one applies, capabilities (word for word from the catalogue) and its connections. The showcase uses this,
  so no platform count is hard-coded and nothing unavailable is advertised.
- **Before signing up, a visitor can:** search solutions, read the library list, browse the business directory and
  business pages, see a business's appointment calendar, and read `/business-tools`, `/features`, `/about` and `/faqs`.
  Reading a solution, asking or answering, and opening a platform need a free account.
- **Joining is free:** creating an account is free. How many people a company gets free is a licensing setting on the
  host (`LICENCE_INCLUDED_SEATS`; one free licence once the R-53 cutover is made). So "Free to join" is accurate, and
  "free for your whole team" is not said.
- **Registration:** the one InfoDot registration (`route('register')`, R-54 central auth). No new sign-up path.
- **Measurement that already exists:** the onboarding funnel records account created, email verified, company joined,
  first platform opened and first platform switched on (`OnboardingEvents`, shown in the Growth Centre). Missing: which
  homepage call to action a person came from (see phase 3).

## 3. The story mapped onto the page

| Step | Section | Heading (approved message, lightly edited only where noted) | Built from |
|---|---|---|---|
| 1 The promise | Hero | A better, more connected way to build your business. *Supporting (owner's tagline, 2026-11-26):* Give the business you've been dreaming about a place to grow, get it right the first time. | Hero today |
| 2 The dream | Ambition | A new heading of its own (see decision 2), so the hero's line is not repeated. It speaks to the person, celebrates starting, and moves through "wherever you are starting from". | `hp-who` |
| 3 The reality | Challenges | You started this business to build something. Not to spend every day figuring everything out. | `hp-reality`, reworded |
| 4 The support | Ecosystem, explained | Behind every business is a dream. Behind every growing business is the right support. | `hp-pillars`, reworked |
| 5 The possibility | Ecosystem, interactive | Imagine what your business could become with the right things behind it. | `hp-tools` replaced; `BusinessTools` data |
| 6 The opportunity | Access to knowledge | You deserve access to the knowledge, resources and opportunities that help businesses move forward. | `hp-chain`, library, aim |
| (trust) | Compact band | A place you can rely on | `hp-trust` |
| 7 The action | Final CTA | Your business has a future. Let's help you build towards it. *Join InfoDot for free* and *Explore the ecosystem* | `hp-join` and `hp-cta` |

Transitions: each section ends with a short line that hands over to the next (for example the challenges end on "it
doesn't have to be this hard", which the support section answers). This is the "one continuous story" requirement.

## 4. Owner decisions (2026-11-26)

1. **Hero wording:** "A better, more connected way to build **your** business." (the owner's answer; neither the brief's
   "build business" nor the live "build a business").
2. **Section 2 heading:** its own heading; the dream line stays in the hero only. Draft for phase 1: "Every business starts
   with someone who decided to try." (open to the owner's wording at review).
3. **Reality heading:** replaced by "You started this business to build something. Not to spend every day figuring
   everything out."
4. **The week:** kept, shortened, inside Section 5 as the concrete example.
5. **Hero tagline (after phase 1 review):** "Give the business you've been dreaming about a place to grow, get it right the first time." The context line under it was removed at the owner's request.

## 5. Phases (one PR each; merge and deploy only when the owner says)

- **Phase 1: the promise, the dream and the reality** (sections 1 to 3).
  - The hero's supporting line and the secondary CTA ("Explore the ecosystem" jumps to Section 5).
  - The new ambition section, built from `hp-who`, with a photograph from the existing set.
  - The reality rewrite.
  - Copy through the humanizer and copy-editing check. Tests updated (`PublicPagesTest` pins today's headings).
- **Phase 2: the support and the possibility** (sections 4 and 5).
  - Section 4: the connected ecosystem by capability.
  - Section 5: an interactive Start/Manage/Connect/Grow showcase. Tabs with full keyboard and screen-reader support,
    complete without JavaScript (all four stages shown stacked).
  - Each platform shows its problem, what you can do, the value, its set-up note where it has one, and a link to its
    place on `/business-tools`.
  - A test proves every platform shown is one `BusinessTools::shown()` allows, and that no count is hard-coded.
- **Phase 3: the opportunity, trust and the action** (sections 6 and 7).
  - Section 6 brings together knowledge access, the "how a question becomes knowledge" chain, the library and the aim.
  - Compact trust band; Section 7's final CTA.
  - Repetition removed: "Join InfoDot for free" twice on the page (hero and close) and once mid-page at most.
  - CTA attribution: each homepage join link carries `?from=<section>`, stored with the "account created" event. No new
    cookie, and the R-71 consent model is unchanged. The Growth Centre funnel shows sign-ups by homepage CTA.
- **Phase 4: the whole journey checked** (no new content).
  - Desktop, tablet and phone screenshots, read as a first-time visitor, signed out and signed in.
  - Every link and CTA destination tested.
  - The browser accessibility scan and the three-width sweep extended to the signed-out homepage.
  - Reduced motion and no-JavaScript checks; console clean; image weight checked.
  - An impeccable critique pass, and a design-taste anti-slop check.
  - The auth pages' side panels (R-67 phase 2) reviewed for lines that echo removed homepage copy.

Out of scope unless asked: the About and Features pages (aligned in R-67), the `/business-tools` page itself, and paid
media or new photography. All images come from the existing licensed set; any new photograph needs a separate download
approval.
