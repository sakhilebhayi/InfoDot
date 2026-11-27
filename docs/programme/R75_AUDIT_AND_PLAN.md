# R-75 — Audit and plan: add-on messages for "Business is hard enough"

Owner instruction (2026-11-27): do this as add-on messages, without completely changing the message InfoDot has today. Brief: `R75_BRIEF.md`.

## 1. Audit of the live public site (infodot.co.za, 2026-11-27, main a290376)

Most of the brief's emotional line is already on the homepage, in the R-67/R-73 story. Below, what is there, and what is missing.

| Brief element | Already live | Gap |
|---|---|---|
| Hero heading | "A better, more connected way to build your business." (owner wording, R-65) | Keep as is. The brief quotes it without "your"; the live owner-approved wording wins. |
| Direction A / E (less figuring out, more building) | "You started this business to build something. Not to spend every day figuring everything out." / "less searching and more building" | None |
| Direction B (what you need, in one place) | "one connected place: one account, one sign-in" | None |
| Direction C (not alone) | "you won't be building it alone" (closing section) | None |
| Direction D (more manageable) | Implied by the pain list | No line says "manageable" plainly |
| **Central idea "Business is hard enough. Getting the support you need shouldn't be."** | Nowhere | **Missing: the brief's main line** |
| **"Check InfoDot first" habit** | Nowhere as a habit; the "ordinary week" (Mon–Fri) shows it in use | **Missing** |
| Situations 1–4 (start, question, manage, grow) | "Wherever you're starting from, there's a next step." paths | Covered |
| **Situation 5 "I don't know what I need yet"** | Not among the paths | **Missing** |
| "Whatever stage, InfoDot is a good place to start" | "whatever stage your business is at" appears once, about the connected place | Not stated as an invitation |
| Journey tabs feel-line (Start approachable, Manage organised, Connect easier, Grow achievable, Special accessible) | Each tab has a functional intro line | **Missing the one emotional sentence per tab** |
| "We are not promising business will be easy..." honesty | "InfoDot can't promise how things turn out" | Covered; the central line can echo it |
| Register page | h2 "Your first step to a better-equipped business." + "One account... It takes a minute." | Could add one reassuring "what happens next" line |
| About | "Most businesses are figuring it out as they go..." | Central line fits "Why it exists" |
| Features | Built on "stop figuring it out alone / stop juggling / stop missing" | Already on message; one "start with whichever you need" line |
| FAQs | 12 questions | **No answer for "I don't know what I need yet"** |
| Directory, library, footer | Functional copy | Footer has no brand line |

Experience notes (brief §7):
- The homepage is long: about 400 text lines and every platform card in the tabs. Progressive discovery already exists (tabs, "Explore Business Tools").
- Navigation labels are plain ("Questions people ask", "Browse public answers", "Business tools", "Businesses").
- No new sections are needed beyond the two below.

## 2. Messaging architecture (add-ons only)

- **Brand promise (new, used sparingly):** "Business is hard enough. Getting the support you need shouldn't be."
- **Honesty line (pairs with it):** "We can't make running a business easy. We can make it easier to find the help that moves it forward."
- **Habit line:** "When something comes up, check InfoDot first."
- **Invitation:** "Whatever stage your business is at, InfoDot is a good place to start."

All of this sits on top of the existing hero, story, tabs and closing; none of those is rewritten.

## 3. Proposed add-ons, by place (draft copy for owner approval)

**Homepage**
1. **Central line band**: one short statement band between the pain list ("...It doesn't have to be this way.") and "Behind every business is a dream." Heading: the brand promise. One line under it: the honesty line.
2. **Sixth path** in "Wherever you're starting from, there's a next step.":
   - "You're not sure what you need yet"
   - "Ask in plain words, or see what other businesses needed first."
   - It links to the public questions.
3. **One feel-line per journey tab**, under the existing intro:
   - Start: "You don't need everything figured out before you begin."
   - Manage: "Fewer places to look, so the day-to-day feels more organised."
   - Connect: "The people you need are easier to find when they're in one place."
   - Grow: "Progress one step at a time, with your numbers and your customers in view."
   - Special Platforms: "Ways to put your skills to work, without needing a business plan first."
4. **"Check InfoDot first" strip**, placed directly after the ordinary week (which already shows the habit). Heading: the habit line. Four real links:
   - "A business question → Search the answers"
   - "A tool for the work → Explore Business Tools"
   - "A supplier or partner → Browse businesses"
   - "Your next step → Follow a researched solution"
5. **Closing section**: add the invitation line under "Start with one question, one tool or one connection...". The "Join InfoDot for free" count stays at two.

**Other public pages**
6. **Register:** under the existing lede, add "Nothing to set up first. Once you're in, ask a question, read a solution or open a tool." The page does not repeat "free" beyond what it says today; free is the current policy.
7. **About, "Why it exists":** open with the brand promise and one sentence of context.
8. **Features intro:** add "Start with whichever one you need first."
9. **FAQs:** add a new question, "I don't know what I need yet. Where do I start?", answered with three concrete first steps.
10. **Footer:** add a small brand line, "Business is hard enough. Getting the support you need shouldn't be."

**Not changed:**
- the hero heading and subline;
- the five tab names and their platform cards;
- the story sections, nav labels, routes, sign-in and registration logic;
- the Yard design system;
- internal pages and the other Dot platforms.

## 4. Phases (one PR each; merge and deploy only on the owner's word)

| Phase | Scope | Proof |
|---|---|---|
| P1 | Homepage add-ons 1–5 (central band, sixth path, tab feel-lines, check-first strip, invitation) | Homepage tests pinning the new lines and links. Join count still 2, no "guarantee", no em dashes. Live screenshots, desktop + mobile, light + dark. |
| P2 | Register, About, Features, FAQs, footer (6–10) | Page tests. FAQ anchor works. Footer on every public page. |
| P3 | QA + the brief's three-visitor review (idea / overwhelmed owner / established business), with fixes | Link check of every new CTA, axe/keyboard pass, mobile 390px. |
| P4 | Measurement baseline | Record today's homepage-to-registration figures from the existing onboarding funnel (sign-up sources hero/start/close), so the effect can be compared after deploy. No new tracking. |

Design work goes through the installed design skills (impeccable) inside the Yard identity. The band and strip reuse existing `hp-*` patterns, not new visual styles.
