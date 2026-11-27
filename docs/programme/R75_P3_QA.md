# R-75 Phase 3 — QA and the three-visitor review (2026-11-27, live after P1 5e93150 and P2 9e96266)

## Automated checks (live infodot.co.za)

| Check | Result |
|---|---|
| 8 public pages × 3 sizes (1440 light, 820 dark, 390 light) | 24 runs. HTTP 200 on all; no horizontal overflow; no console errors |
| axe WCAG 2 A/AA, serious and critical | 0 violations on every page and size |
| Internal links found on the public pages | 57 checked, 0 broken |
| Keyboard | All six "Wherever you're starting from" paths and all four "check InfoDot first" doors are reached with Tab and show visible focus |
| Load | HTML arrives in about 1 s. Assets on the shared host can be slow under load: one run waited 72 s for the 659 KB Features photo, and 24–28 s for small scripts |

## Three-visitor review (independent reviewer, live site)

The reviewer read the site as three visitors: someone with only an idea, a small business owner who feels overwhelmed, and an established business. Kept as is, by the reviewer's own reading:
- the pain list ("The work lives in spreadsheets, WhatsApp threads, email and a phone full of files.");
- the honest "Good to know" lines;
- the Asked → Answered → Accepted → Verified ladder;
- the new "check InfoDot first" block.

## Fixed in this phase (PR r75/p3-qa)

1. **Weight.**
   - The home hero skyline and the Features photo are now WebP first, with the JPEG as the fallback: 616→224 KB and 659→283 KB.
   - Phones get the 800px Features photo.
2. **Empty first steps.**
   - The "You have a business idea…" path and the "Not sure what comes next" door both pointed to the `getting-started` topic, which has 0 results.
   - They now point to `starting-a-business`, which has 5 solutions.
3. **Established organisation path.** It pointed to About, which says nothing for organisations. It now goes to Business Tools.
4. **Broken anchor.** Features linked to `/#tools`, which does not exist. It now links to `/business-tools`.
5. **Empty public search.** The empty state now suggests "Try a word such as register, tax or pricing…".
6. **Discontinued platforms in the topic filter.**
   - Dot.Billing, Dot.Farms and Dot.Plug still showed. Their topics are now switched off by a migration (not deleted; reversible).
   - This follows the owner rule that discontinued platforms disappear everywhere.

## Needs the owner's decision (not changed)

1. **The hero never says what InfoDot is.**
   - The reviewer proposes one added line under the subheading: "InfoDot: free business answers, connected tools and a directory of businesses, in one account."
   - The hero is owner wording, so it is not touched without approval.
2. **The hero subline promises too much.** "…get it right the first time." conflicts with "InfoDot can't promise how things turn out" and is a comma splice. A suggested softening: "get more of it right the first time."
3. **Repetition.**
   - The promise now appears in the home band, on About, and in the footer of every public page.
   - "You do not need a company, a plan or any experience" appears three times.
   - The accepted/verified explanation appears on five pages.
   - The owner should decide whether the footer line stays.
4. **"Which tools you can open depends on… how access is arranged"** appears about six times and never says how to find out. The proposal is one added sentence pointing to the contact form.
5. **The register page says "You will set up your company next."** while the home page and FAQ say no company is needed. A newcomer may worry. It needs a decision on wording.
6. **Directory data:**
   - The "BluPin AI & Coding Institue" listing has a typo.
   - InfoDot itself is listed as a "Public entity".
   - Both are the businesses' own data.
7. **Business Tools page.** Four grouping schemes and some generic marketing lines ("Your business deserves more than disconnected tools.") were outside R-75's add-on scope and are left for a separate review.
8. **Shared-host asset speed.** WebP reduces the bytes, but slow static serving under load is a hosting issue. It links to the CPU work of 2026-11-27.
