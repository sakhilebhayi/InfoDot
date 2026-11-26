# R-67: homepage and authentication messaging — audit and plan

Brief: `R67_BRIEF.md` (owner, 2026-11-26, verbatim). Audit done read-only against `main` at 78a724b. No code changed yet.

## 1. What the homepage says today (after the hero)

| # | Section | Heading today | Verdict |
| --- | --- | --- | --- |
| 1 | Problem | "Most small businesses run on memory and chat." Three situations (person away, figure in five places, solved before) | Accurate but small. It describes record keeping, not the bigger feeling of running a business alone. The brief replaces it. |
| 2 | Pillars | "What you can do with InfoDot": Discover / Manage / Grow, each with a checklist | Feature lists. "Grow" promises the least and lists the vaguest items ("Identify operational challenges"). No connections pillar, though messaging, the directory and appointments are now real. |
| 3 | How it works | "Getting started with InfoDot is simple." 3 steps | Generic; says nothing a visitor does not assume. |
| 4 | Tools | "Your business. Your tools. One connected ecosystem." logos by goal | Good structure. The lede spends its words on caveats ("not every tool comes with every account"). |
| 5 | Knowledge | "You don't have to figure it all out yourself." six definitions | Overlaps the new problem heading and the pillars; reads as a glossary. |
| 6 | Chain | "How a question becomes knowledge" Asked → Answered → Accepted → Verified | Strong and true. Keep. It is the trust proof. |
| 7 | Library | "A library you can act on" (live, 20 entries) | Strong proof. Keep. |
| 8 | Who | "Built for every stage of your business journey." five stages | Good. Keep, tighten. |
| 9 | Trust | "A place you can rely on" six facts | Six facts is a wall; three of them repeat the chain. |
| 10 | Close | "Your business journey starts here." | Generic, and its lede goes back to "write down how they work", the old message. |

Repetition: "figure it out yourself" (5), "learn from other businesses" (2, 5, 9), "connected ecosystem" (2, 4), "start where you are" (8, 10). Nothing on the page answers "what exactly do I get when I join today?" in one place.

## 2. Authentication pages today

- One card on the concrete ground, no side panel, no motion (by design: "these pages are for getting through").
- Platform-started sign-ins (R-54) already get a brand panel beside the form. InfoDot's own sign-in does not: it is the plainest page on the site at the moment the visitor decides.
- Login lede: "Your business starts here. Sign in to your InfoDot w…". Register lede leads with email confirmation mechanics. Forgot, reset and verify are clear but cold; reset has no lede at all.
- Architecture to keep: one InfoDot login for every platform, `$platformBranding` flows, the `f` flow parameter, terms, referral note, strength meter, busy states.

## 3. What can be promised (mapped to what exists)

| Promise | Real today | Do not say |
| --- | --- | --- |
| Knowledge | Ask questions, answers, accepted solutions, moderator-verified solutions, a researched library of 20 entries with named sources, improvements with versions | "expert advice", "guaranteed answers", any count of users or answers |
| Tools | 20+ live Dot platforms (finance, HR, projects, tasks, documents, files, forms, sheets, analytics, design and more), one InfoDot sign-in across them | that every tool comes with every account |
| Connections | Business pages, a business directory, the inbox (message a business, as a company), bookings and now appointment requests to any business | leads, tenders, a marketplace of opportunities, "find clients" |
| Presence and team | Your business page (logo, cover, gallery, offerings), your company and its members | verified status unless the business is verified |
| Cost | Creating an account is free | "free tools", "free forever", prices |

## 4. The story, section by section (proposed)

1. **Promise (hero, unchanged).** "A better, more connected way to build a business." See decision D3.
2. **Reality (new problem section).** Heading per D1 (owner's wording). Below it, six short recognitions in the owner's voice, then one turn:
   - You make big decisions without knowing who to ask.
   - The business runs on spreadsheets, WhatsApp threads and a phone full of files.
   - You fix the same problem twice because nobody wrote down the answer.
   - You know there is a better tool for this. You just don't know which one.
   - The supplier, partner or customer you need exists. You can't find them.
   - You want to grow, but nobody tells you what the next step is.
   - Turn: "None of this is a lack of ambition. It's a lack of access: to knowledge, to tools and to the people who could help."
3. **Gap → solution (four pillars replace three; decision D2).** Same photo plates, one line of tension and one of answer each, three proof points, one action:
   - **Knowledge.** Stop figuring it out alone. Ask, find checked answers, follow researched steps.
   - **Tools.** Stop running the business across disconnected apps. One InfoDot account opens the Dot tools your business uses.
   - **Connections.** Stop missing what you can't see. Find businesses, message them as your company, ask for an appointment.
   - **Progress.** Turn what you learn into what you do. Answers become steps, steps become the way your team works.
4. **Possibilities ("A week with InfoDot", new, replaces "How it works").** Five concrete moments, each a real feature, e.g. Monday you search how to register for VAT and follow a verified solution; Tuesday you send an invoice from Dot.Finance; Wednesday you find a printer in the directory and ask for an appointment; Thursday a new hire gets their own login; Friday what you learned this week is written down for the next person. Scroll-linked progress line (reuses the existing `data-journey` motion).
5. **Tools by goal (kept),** lede rewritten to lead with what you can do; the access caveat moves to one short line.
6. **Proof (chain + library, kept),** the "You don't have to figure it all out" glossary is removed (its job moves to the Knowledge pillar).
7. **Transformation (who it is for, kept and tightened),** each stage gets a before → with InfoDot line.
8. **Invitation (new "What opens when you join"),** five tangible things in one place: your account and profile; your business page and team; the knowledge base; the inbox, bookings and appointment requests; one sign-in to the Dot tools your business can use. "Creating an account is free." Then the close.
9. **Trust (trimmed to four facts)** and the close with a new heading and lede tied to the story (no "write down how they work").

Copy rules: no statistics, testimonials or user counts; no em dashes in visible copy; sentence case; no "elevate/seamless/unlock"; no promise of success or growth, only access to what supports it.

## 5. Authentication pages (proposed)

- InfoDot's own sign-in and registration get a panel beside the form on desktop, built on the R-54 brand-panel structure so there is still one layout: a short headline, three lines of what the account opens, and the hero's promise. On phones it collapses to one line above the form, so the form stays first.
- **Register:** "Create your free InfoDot account" with the panel "What opens when you join". The confirmation-email explanation moves to a hint under the email field.
- **Login:** "Welcome back." with a lede in the same voice ("Your knowledge, your team and your tools are where you left them.").
- **Forgot / reset / verify / two-factor:** reassuring, plain, one sentence each; reset gains a lede; verify becomes "One last step".
- Platform-branded flows (R-54) unchanged.

## 6. Phases (one PR each)

- **P1 Homepage narrative:** sections 2–4 and 8–9 above (copy + layout for the problem section, four pillars, the week, the join panel, trimmed trust, new close), meta description aligned, tests updated. Design skills: `impeccable` (refine within the Yard), `emil-design-eng` for the pillar and week components, `gsap-scrolltrigger` within the existing reveal rules (start at top 98%, short, reduced-motion safe).
- **P2 Authentication pages:** InfoDot panel, copy for login/register/forgot/reset/verify/two-factor/confirm; R-54 branded flows untouched; tests.
- **P3 QA and deploy:** the whole journey home → register → verify → onboarding → sign in, rendered at desktop, tablet and phone widths, axe and console checks, Impeccable check, then deploy on the owner's word with the backup first.

## 7. Owner decisions

- **D1 Problem heading.** The brief's line is "The truth is, most businesses don't really know what they're doing." It is bold, but "don't know what they're doing" judges the reader. Recommended: **"The truth is, most of us are making it up as we go. InfoDot is here to change that."** It keeps the honesty and the turn, and "us" stands beside the owner rather than over them.
- **D2 Pillars.** Replace Discover / Manage / Grow (R-55) with Knowledge / Tools / Connections / Progress as the brief describes. Recommended: yes.
- **D3 Hero wording.** The brief quotes "build business"; the live, owner-approved heading (2026-11-25) is "build **a** business". Recommended: keep the live wording.

### Decided by the owner, 2026-11-26

- **D1:** the owner's own wording: **"The truth is, most businesses don't really know what they're doing. InfoDot is here to change that."** The supporting copy carries the warmth: it stands with the reader (ambition is not the problem, access is) so the heading provokes without humiliating.
- **D2:** four pillars: Knowledge, Tools, Connections, Progress.
- **D3:** keep the live hero: "A better, more connected way to build a business."

