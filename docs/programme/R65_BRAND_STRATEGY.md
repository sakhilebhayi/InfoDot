# R-65: InfoDot brand positioning, audience and acquisition strategy

Status: research and strategy, 2026-11-26. Nothing in the product has been changed. Section 11 lists the website changes proposed for approval.

Labels used throughout: **[V]** verified in the live site, the code or the channel tools today; **[A]** assumption to test; **[R]** recommendation.

## 1. What InfoDot is and what value it genuinely provides

**[V] What exists today**

- A public knowledge layer: questions and answers, step-by-step solutions that a moderator who did not write them can verify, topics, references, a verified library, search (live homepage, /faqs, /features).
- A business directory with filters for category, place, type and size, plus business profiles (/businesses). Today it lists a handful of businesses, one marked as a demonstration.
- One account that opens the Dot platforms a business has access to, grouped by goal (/business-tools). Access depends on the business and how it is arranged; the site already says "not every tool comes with every account".
- Free registration with email confirmation (/register). First-party acquisition tagging (referral code, UTM tags, search/social referrer, 30-day cookie) is built (config/acquisition.php, CaptureAcquisition middleware).
- Sign-in honesty is a product value: "We describe what is there now, not what is planned" (/about).

**[V] Platform status, in three buckets** (source: config/ecosystem.php `active` flags, the 2026-10-19 inventory where all hosts answered, and the owner-item notes in the platform memory files; feature depth per platform was not re-tested in this pass)

1. Live and usable as a sign-in handoff: Finance, HR, Projects, Tasks, Files, Docs, Sheet, Forms, Notify, Analytics, Engage, Press, Design, Pulse, Tutor, Agents.
2. Live with a limit the copy must respect: Charts and Mines (own sign-in, no InfoDot handoff); Emall (shop empty until categories are seeded); Ehail (cannot take bookings until routing key, payment and operator are set); Auction (rebuild PR not merged or deployed per the last record); Agents (governance features live, but treat AI claims carefully).
3. Not to be marketed: Central, Memory, Dopemine (internal), and the discontinued Plug, Billing and Farms.

**The plain explanation for a business owner:** InfoDot is where you find practical answers from other businesses, and where one account opens the tools to run your business (money, people, work, documents) so what you learn and what you do sit together. It is not only a directory and not a bundle of unrelated apps.

**What genuinely differs from alternatives [V + A]:** a directory lists you; a SaaS tool does one job; InfoDot combines verified shared knowledge, a business profile, and one sign-in across the tools. The knowledge layer with visible provenance (accepted by the asker, verified by a moderator who did not write it) is the least copyable part.

**Honest weak points the strategy must work around [V]:** few businesses listed, so "find suppliers and customers" is not yet a strong promise; a small public solution library; no testimonials, case studies or statistics exist and none may be invented.

## 2. Ranked target audiences

Ranking logic: relevance to what is live today, ability to be reached cheaply in South Africa, likelihood to stay active, and ability to bring others.

| Rank | Class | Audience | Why |
|---|---|---|---|
| 1 | **Primary** | Owner-run small businesses of 2 to 25 people (trades, professional services, retail, transport, tutoring) that run on WhatsApp, spreadsheets and one person's memory | Matches the live proposition exactly: written-down know-how plus Finance, HR, Tasks, Files, Docs, Forms. Feels the pain weekly. Reachable on Facebook and TikTok. Owner decides alone, so conversion is fast. |
| 2 | Secondary | People starting their first business or formalising a side business | Knowledge layer plus free profile fit; low willingness to pay yet, but high activity and strong word of mouth. |
| 3 | Secondary | Independent professionals and consultants (photographers, bookkeepers, IT support, trainers) | Booking, business profile and directory visibility are directly useful; they refer clients. |
| 4 | Strategic | Accountants, bookkeepers and business advisers serving several small clients | One adviser can bring 10 to 50 businesses. Needs a partner pathway (the acquisition config already has a `partner` channel). |
| 5 | Strategic | Incubators, enterprise-development programmes, SEDA-type support bodies, business forums and chambers | Institutional channel already modelled (`institutional`). Slow but large. |
| 6 | Future | Mid-size operations-heavy firms (mining contractors, transport fleets) | Gated on the owner's employment-contract check (decision on record); do not target now. |
| 7 | Future | Other African markets | After a repeatable South African engine. |

**Highest-priority segment [R]:** owner-run service and trade businesses with 2 to 25 people. It should produce the first wave because it can be reached with Facebook and TikTok content about problems it already has, the first value (find an answer, open Tasks or Files) arrives in the first session, and each business brings staff (invites) and a profile (directory growth).

## 3. Personas (scenario-based; fictional composites for planning, not customers)

1. **Thandi, owner of a four-person cleaning and maintenance company, Pretoria.** Quotes live in WhatsApp, invoices in a spreadsheet, the supervisor holds the schedule in her head. Goal: stop losing jobs when the supervisor is off. Tools now: WhatsApp, Excel, paper. Hook: "If your best person resigned on Friday, what would you lose on Monday?" Platforms: Tasks, Files, Finance, Forms. Registers when she sees she can write down the job steps and give staff one place to find them.
2. **Sipho, 24, wants to start a mobile coffee cart.** Has an idea, no plan, no registered company. Goal: know the first five steps. Hook: practical start-up answers and a free profile. Platforms: knowledge layer, Sheet, Finance later. Content: carousels on first steps and mistakes.
3. **Naledi, independent photographer, Durban.** Fears a single point of failure (illness before a wedding) and wants clients to find her. Hook: written hand-over steps, business profile, bookings. Platforms: Docs, Forms, directory.
4. **Johan, bookkeeper with 15 small clients, Johannesburg.** Chases clients for documents. Goal: one place clients upload and ask. Hook: Files, Forms and shared answers; invites clients. Strategic referrer.
5. **Lindiwe, programme manager at a small-business incubator.** Needs tools her cohort can actually use. Hook: free account for each founder, one dashboard of platforms. Institutional channel.

## 4. Audit of existing brand messaging (site) [V]

Strengths: the Discover / Manage / Grow structure (R-55) is clear; the hero says "Your business, all in one place." with a plain sub-line; claims are restrained and honest; /business-tools starts from the owner's need ("I want to organise my employees").

Weaknesses:
- "All in one place" is the vague slogan the brief warns about; the hero does not say who it is for or what happens after joining.
- No visual of the ecosystem or the product in the first screen; the product is never shown working.
- No identity layer: nothing says what a business becomes by joining.
- The three-step "getting started" does not say what is free; "Join InfoDot for Free" appears but the free scope is unstated.
- The homepage carries a prominent search bar before value is established (kept by owner order in R-55; leave it).
- The directory is thin, so any "find customers and suppliers" line over-promises.

## 5. Audit of social content (what is observable) [V for numbers, A for interpretation]

Channels connected: Facebook, Instagram, LinkedIn page, TikTok business, X, all named "InfoDot". Queue read on 2026-11-26: 206 posts in the 10 to 27 Nov window (186 published, 20 queued), no errors. The longer queue runs into 2027.

Platform analytics for the last 30 days (reported by each platform through the scheduling tool; small numbers):
- Facebook page: impressions 3 to 29 a day since 16 Nov, 2 to 3 engagements on three days, 2 new followers in total.
- Instagram: about 2 to 5 reach a day; follower gain 28 over 20 to 24 Nov; one day of data for views (52), likes (2), comments 0, shares 0, saves 0.
- LinkedIn page: 7 page views and 7 clicks in the whole month, 0 shares, 0 comments.
- TikTok: 1,166 views and 14 likes, 0 comments, 0 shares reported for the latest day.
- X: the platform returned no analytics.

**Read with care:** counts are small, windows differ per platform, and the tool shows few days of detail. They show that content is being seen at low volume and almost nobody is acting on it. They do not show registrations: no post link carries a campaign tag (see below), so social-to-registration is currently not measurable.

Content observed:
- Every sampled post ends with a bare `https://infodot.co.za` plus the same hashtags (`#InfoDot #SmallBusinessSA`). **No UTM tags** on any link, so the existing acquisition tracking cannot attribute a registration to a channel or post. This is the single most important measurement fix.
- One scenario (the wrist break and the wedding) was posted on Facebook, Instagram, LinkedIn, TikTok and X on the same day with the same text; identical cross-posting.
- Strong hooks of the "what would break on Monday" type, a good fit for persona 1.
- A weaker strand: product-principle posts ("show only what works", no like counters) and AI-governance posts about Dot.Agents. They are honest but speak to the product team's pride, not an owner's problem.
- Posts are largely questions and scenarios; few show a platform doing the job. No screens of the product appear in the sample.
- Photographer, taxi driver and password examples target different people on one channel without a clear primary audience.

## 6. Direct answers to the ten questions

1. Are we speaking to the people most likely to use InfoDot? Partly. The scenario posts speak to small owners; the AI-governance and product-principle posts speak to a different, smaller audience.
2. Would an SME owner understand why to join? Not yet. Posts end in a bare URL with no statement of what they get.
3. Would an aspiring entrepreneur understand? No pillar is aimed at them in the sample.
4. Are we communicating practical business value? In the scenario posts, yes in problem; no in the solution (the solution step is "visit the site").
5. Are we showing how the ecosystem solves real problems? Rarely; one platform at a time is named, the connection between them is not shown.
6. Potential users or general awareness? Awareness at very low volume.
7. Curiosity enough to visit? Hooks are good; the path after the hook is not.
8. A reason to register? Not stated.
9. Is positioning strong enough to differentiate? Not in social; the verified-knowledge angle is barely used.
10. Stop / improve / start: **Stop** identical five-channel cross-posts and product-pride posts. **Improve** every link (UTM), every closing line (say what the person gets). **Start** product demonstrations, an aspiring-entrepreneur pillar, an adviser/partner pathway, and weekly reading of registration data.

## 7. Content to retain, improve, reduce, discontinue

- Retain: the "what would break on Monday" scenarios; the myth-versus-fact carousels; the honest-limits posts on LinkedIn only.
- Improve: add a one-line outcome and a tagged link to each post; rotate one scenario per channel instead of the same text everywhere.
- Reduce: Dot.Agents governance posts to one a week on LinkedIn only; password and generic security tips.
- Discontinue: identical simultaneous cross-posts; any post naming a discontinued or limited platform as if it works (Plug, Billing, Farms; Ehail bookings; Emall shopping) until the owner items are done.
- Standing constraint: queued posts are not deleted or edited without the owner's approval; queue changes are proposed in section 12 and section 17.

## 8. The three-layer messaging framework

**Layer 1, Functional (what InfoDot does).** Four jobs, each tied to something live:
- **Start:** practical answers and step-by-step solutions from other businesses; a free business profile.
- **Run:** one account for the tools that run the business: Finance, HR, Projects, Tasks, Files, Docs, Forms, Sheet, Notify.
- **Connect:** the business directory and profile; shared answers; (careful) discovery of suppliers and customers is limited by the directory's size, so describe it as "list your business so people can find it", not "find your next customer".
- **Grow:** Analytics and Sheet for knowing the numbers, Press and Design for looking professional, Engage and Pulse for staying in touch with customers, Tutor for learning. Describe as tools, not as guaranteed growth.

**Layer 2, Emotional (how it feels), each tied to a capability.**
- Relief: the answer is already written down, found by search or reference.
- Control: tasks, files and approvals in one place, with who did what recorded.
- Clarity: Analytics, Sheet and Finance show where the business stands.
- Confidence: answers show how they earned trust (accepted, verified).
- Never promise success; copy states "helps you" and "shows you".

**Layer 3, Identity (what you become).** "A business that writes down what it knows, uses connected tools, and learns from others." The credible, non-exclusive version: **members build with their business written down and connected.** No claim that outsiders are worse.

## 9. Master brand message

**Directions evaluated** (1 to 5; clarity / practical value / emotion / difference / memory / credibility / audience fit / registration pull):

| Direction | Line | Scores | Verdict |
|---|---|---|---|
| A | "Your business. Better equipped." | 3 / 3 / 4 / 2 / 4 / 4 / 3 / 3 | Memorable, vague on what. |
| B | "Start. Run. Connect. Grow." | 4 / 3 / 2 / 3 / 4 / 4 / 4 / 3 | Clear skeleton; reads as a list. |
| C | "Write down how your business works. Then run it from one place." | 5 / 5 / 4 / 5 / 3 / 5 / 5 / 5 | Strongest: concrete, true today, differentiated, aimed at persona 1. |
| D | "Everything your business needs" | 2 / 2 / 2 / 1 / 2 / 2 / 3 / 2 | Rejected; the brief names this pattern. |

**Recommended direction: C as the headline idea, B as the structure, A as the sign-off.**

1. **Master positioning statement:** InfoDot helps small and growing businesses write down what they know and run on connected tools, so the business stops depending on one person's memory and the owner can start, run, connect and grow with more clarity.
2. **Primary headline:** "Your business, written down and connected." Alternatives to test: "Know how your business runs. Run it from one place." / "Stop running your business from WhatsApp and memory."
3. **Supporting statement:** "InfoDot gives you practical answers from other businesses and one account for the tools that run yours: money, people, work and documents. Free to join."
4. **Value proposition:** Find answers that have been checked, put your business's own know-how in one place, and open the tools you need with one sign-in.
5. **Short description (profile bios, up to 150 characters):** "Practical business answers and connected tools, with one free account. Start, run, connect and grow."
6. **Long description:** "InfoDot is a free place for businesses to find practical, checked answers and to open the tools that run their business, such as finance, people, projects, documents and forms, with one account. You write down how your business works, your team finds it, and the business stops depending on one person's memory."
7. **Social bio:** "Write down how your business works. Run it from one place. Free to join. infodot.co.za"
8. **Audience value propositions:**
   - Small owner: "When someone is off, the business keeps working because the steps are written down."
   - First-time founder: "Know your first five steps, with answers from people who have done it."
   - Independent professional: "A business profile and the paperwork tools in one account."
   - Adviser: "Give your clients one place to upload, ask and follow up; see them all in one list." (only once a partner pathway exists)
   - Institution: "Give every founder a free account and one dashboard of tools."
9. **Platform messaging frame** (one line each: problem, what it does, outcome):
   - Tasks: work lives in chat threads; Tasks gives each job an owner and a due date.
   - Files and Docs: documents are in five places; they live in one shared, searchable place.
   - Finance: books in a spreadsheet; invoicing and records in one place.
   - HR: leave and staff records on paper; one record per person.
   - Forms: requests by message; one form that fills the right place.
   - Sheet and Analytics: numbers in your head; shared sheets and reports.
   - Projects: jobs without a plan; plans with steps and owners.
   Only use a platform line after its current capability is re-checked in the product (section 10).
10. **Calls to action:** primary "Join InfoDot free"; secondary "See how it works" (the homepage anchor) and "Find the tools for your business" (/business-tools). Social: "Open one platform free" with a platform-specific tagged link.
11. **Language rules:** Say "business", "tools", "write down", "one account". Avoid "all-in-one", "everything", "AI-powered", "revolutionary", "seamless", "ecosystem" in first-line copy (use in the second line once the reader knows what it is). Never say "free" without naming what is free. No guarantees. No invented numbers. No names of discontinued platforms. Describe AI only where built, with a person in charge, as the FAQ already says. Use plain South African English; no dashes in visible text.

## 10. Ecosystem mapping: live platform to problem to outcome

See the platform frame in section 9, item 9. Cross-platform story to tell: a question answered on InfoDot becomes a task in Tasks, its documents live in Files and Docs, the money side in Finance, the people side in HR, and Sheet and Analytics show the result. **[A]** Cross-platform links beyond one sign-in (a task created from a form, for example) must be verified per platform before they are advertised.

## 11. Revised homepage structure (proposal, not built)

1. **Hero:** headline "Your business, written down and connected." Sub-line as above. Buttons "Join InfoDot free" and "See how it works". Right side: a real product panel (a search result of a verified answer beside a task list) built in the Yard design system; no stock imagery. Under the buttons one line stating what is free (only after confirming the exact free scope from licensing config).
2. **The problem:** three recognisable situations (the key person is off, the figures are in five places, the answer is in someone's head), each with the line that follows.
3. **The answer:** how InfoDot works in three steps with real screens.
4. **What your business can do:** the Start / Run / Connect / Grow set, each with two live capabilities.
5. **The tools:** grouped by goal, 6 to 8 live platforms, each one line; a link to /business-tools.
6. **How it feels:** four short statements (relief, control, clarity, confidence) each beside the capability that earns it.
7. **Who is here:** the identity statement; no invented counts; a live "businesses recently listed" strip taken from the directory.
8. **Join:** what you get in the first 10 minutes, then the button.

Design: use the existing Yard design system and the motion tokens from R-64; apply the installed design skills (hallmark for the page, impeccable for the finish pass, GSAP only for section reveals already in landing.js). Keep the search bar as ordered in R-55. Meta titles use the headline plus "free business tools and answers".

## 12. Social strategy and 30-day content calendar

**Channel roles [A, to be adjusted from data]**
- Facebook: owners and local business groups. Scenario posts, how-to checklists, short product demos.
- Instagram: carousels and short product walk-throughs; stories only where a poll or a question helps.
- LinkedIn: advisers, consultants, partners; one honest-limits or governance post a week, and the adviser pathway.
- TikTok: faceless screen recordings and relatable situations; every caption carries `https://infodot.co.za` (standing owner rule).
- X: short points and questions; links are stripped by the posting tool, so it points to the TikTok/website only by name.

**Pillars:** A Start, B Run, C Grow, D Discover the tools, E Business knowledge, F Connections. Mix: 40% knowledge and scenarios, 30% product demonstrations, 20% problem and solution, 10% direct "join" posts.

**Rules for every post:** one audience, one problem, one outcome line, one tagged link. Tag format: `https://infodot.co.za/?utm_source=<channel>&utm_medium=social&utm_campaign=<yyyymm-theme>&utm_content=<post-id>`.

**Calendar, 27 Nov to 26 Dec 2026.** Layers: F functional, E emotional, I identity. Each row is one lead post; the owner's existing three-a-day queue continues in parallel unless the owner asks to change it, and these lead posts take the 10:30 UTC slot where the queue is replaced. Visuals use the existing carousel pipeline in ~/Dot/brand/tiktok and screen recordings of the live product.

| Date | Channel | Audience | Layer | Pillar | Problem or aspiration | Hook | Visual | Capability shown | CTA | Outcome |
|---|---|---|---|---|---|---|---|---|---|---|
| 27 Nov | Facebook | small owners | F | B | key person leaves | "Your best person resigns Friday. What does the business lose Monday?" | 6-slide carousel | written steps in Docs/Files | Join free | traffic, comments |
| 28 Nov | TikTok | small owners | F | D | job details in WhatsApp | "Your team's jobs are buried in a group chat" | 25s screen recording | Tasks | Open Tasks free | first activation |
| 29 Nov | Instagram | founders | E | A | start without a plan | "Five steps before you register a business" | carousel | knowledge answers | Search the steps | profile visits |
| 30 Nov | LinkedIn | advisers | F | F | clients chase documents | "How a bookkeeper gets documents from clients without chasing" | text + one screen | Files, Forms | Talk to us | partner leads |
| 1 Dec | Facebook | small owners | F | B | where is the invoice | "Where was that invoice again?" | carousel | Finance | Join free | registrations |
| 2 Dec | TikTok | founders | E | A | first customers | "Three places to find your first ten customers" | talking-less text video | knowledge answers | Search answers | traffic |
| 3 Dec | Instagram | owners | E | B | control | "One list of what is due this week" | screen reel | Tasks | Open Tasks free | saves |
| 4 Dec | X | owners | F | E | quick tip | "Write the step down once. Nobody asks you again." | text | Docs | none | replies |
| 5 Dec | Facebook | owners | I | F | being found | "If a client searched for your trade, would they find you?" | profile screenshot | business profile | List your business | profile creation |
| 6 Dec | LinkedIn | consultants | I | F | credibility | "A profile that shows how you work" | carousel | profile, verified answers | Create profile | profiles |
| 7 Dec | TikTok | owners | F | D | staff leave | "Leave requests on WhatsApp?" | screen recording | HR | Open HR | activation |
| 8 Dec | Instagram | founders | F | A | forms | "Stop collecting details in messages" | carousel | Forms | Try a form | activation |
| 9 Dec | Facebook | owners | E | B | relief | "The relief of a written hand-over" | scenario | Docs | Join free | comments |
| 10 Dec | X | owners | F | E | question | "What is the one thing only you know?" | text | knowledge | none | replies |
| 11 Dec | LinkedIn | advisers | F | F | reporting | "One view of how a client is doing" | demo | Analytics, Sheet | Talk to us | partner leads |
| 12 Dec | TikTok | owners | F | D | files | "Five folders, one lost contract" | screen recording | Files | Open Files | activation |
| 13 Dec | Instagram | owners | E | C | clarity | "Do you know this month's number?" | carousel | Finance, Sheet | Join free | saves |
| 14 Dec | Facebook | owners | F | E | checklist | "Month-end checklist for a small business" | checklist image | knowledge answer | Read the answer | traffic |
| 15 Dec | LinkedIn | institutions | I | F | cohort tools | "Give every founder one account" | text | one account | Talk to us | institution leads |
| 16 Dec | TikTok | founders | F | A | questions | "Ask a business question, get an answer people vouch for" | screen recording | Q and A | Ask free | first question |
| 17 Dec | Instagram | owners | F | D | projects | "A plan with owners and dates" | carousel | Projects | Open Projects | activation |
| 18 Dec | Facebook | owners | E | B | control | "Who approved this?" | scenario | audit trail in tools | Join free | comments |
| 19 Dec | X | founders | F | A | tip | "Register first or write the plan first?" | text | knowledge | none | replies |
| 20 Dec | LinkedIn | advisers | F | E | honesty | "What InfoDot does and does not do" | text | honest limits | Read FAQs | trust |
| 21 Dec | TikTok | owners | I | F | being part of it | "Businesses that write things down" | text video | one account | Join free | registrations |
| 22 Dec | Instagram | owners | F | C | professional look | "Quotes that look professional" | carousel | Press, Design | Join free | saves |
| 23 Dec | Facebook | owners | F | B | year-end | "Year-end checklist: people, money, paperwork" | checklist | Docs, Finance | Join free | shares |
| 24 Dec | X | owners | E | C | quiet week | "Use the quiet week to write one process down" | text | Docs | none | replies |
| 26 Dec | LinkedIn | owners | I | C | next year | "Start the year with the business written down" | text | one account | Join free | registrations |

Gaps noted honestly: no testimonials or success stories exist, so none are scheduled; story formats are used only for polls.

## 13. Conversion system

Journey: **see a post, land on a tagged homepage, register, create the business, open one platform, invite one person.**

- **Stage 1 awareness:** problem scenarios, no sell.
- **Stage 2 interest:** one platform doing one job, in 25 seconds.
- **Stage 3 consideration:** FAQ-style answers ("who sees my question", "does AI write the answers") because the site already answers these well; honest-limits post.
- **Stage 4 conversion:** one tagged link, one plain sentence of what is free and what happens next.
- **Stage 5 activation and referral:** welcome email sequence (profile, first platform, invite a colleague); the referral system exists (referrals routes), so use it.
- **Measure (needs only what already exists):** acquisition cookie and UTM fields, the growth funnel in /admin/growth. Add: UTM on all outbound links, a weekly funnel review (visits, registration starts, completed registrations, business created, first platform opened, invites sent, paying), no cost-per-registration until any paid spend exists.

## 14. Competitive positioning (public knowledge, no figures)

| Alternative | Strong at | Where InfoDot differs |
|---|---|---|
| Business directories | being found | InfoDot adds shared checked knowledge and the tools, but its directory is small |
| Microsoft 365, Google Workspace | documents, mail, familiar | not built around business know-how or South African small owners; separate sign-ins to finance and HR tools |
| Asana, Trello, Monday | projects and tasks | one job each; InfoDot is one account across several jobs and a knowledge layer |
| Xero, Sage, QuickBooks | accounting depth | far deeper accounting than Dot.Finance; do not claim to replace them |
| WhatsApp groups plus spreadsheets | already used, free | the real competitor; the message is "keep WhatsApp, but stop keeping the business in it" |
| LinkedIn, local business forums | networking | InfoDot is practical how-to rather than status; do not claim a bigger network |

Gap to own without unsupported claims: **a small-business setup that writes down how the business runs and keeps the tools connected, with free entry.**

## 15. Measurement framework

Distinguish and report separately: engagement (platform numbers), website visits (analytics), registrations started and completed (acquisition record), businesses created, first platform opened, invited members, paying businesses. No targets are set here because no baseline exists; the first 30 days produce the baseline. Never report followers or likes as success. Cost per registration applies only once paid distribution exists.

## 16. Geographic expansion

Start in South Africa: Gauteng and KwaZulu-Natal first (the directory has entries there), then other provinces. Reach through Facebook business groups, TikTok, accountants and bookkeepers, and incubators. English first; isiZulu, isiXhosa and Afrikaans for carousels once a persona's channel data shows demand. Other African markets only after a repeatable acquisition rate and a payment route exist.

## 17. 90-day roadmap

- **Days 1 to 7:** approve this strategy; add UTM tags to every outbound link and fix the queue's closing lines; confirm the exact free scope; one baseline funnel read.
- **Days 8 to 30:** ship the homepage rewrite and hero product panel as one PR per step (hero and copy, problem and solution sections, platforms by goal, join section); rewrite /about, /features and registration lede for the same message; update meta; start the 30-day calendar; send the first adviser and institution outreach (owner-led).
- **Days 31 to 60:** read the funnel; keep what converts; build the welcome email sequence and invite prompts; create three real how-to guides from the verified library; record platform walk-throughs.
- **Days 61 to 90:** adviser/partner pathway with tagged links; one institution pilot; decide on paid distribution only if organic registration cost is known; extend to other provinces and one local language.

## 18. Verified, assumed, recommended

- **Verified today:** live site copy, directory fields, free registration, acquisition tagging code, the platform registry flags, channel list, post queue counts and sampled text, channel analytics (small).
- **Assumed:** audience rankings, personas, channel roles, competitor strengths, the hero headline preference.
- **Recommended:** everything in sections 8 to 17.

## 19. Open owner decisions

1. Approve the master message (direction C) and the hero headline wording, or choose an alternative.
2. Confirm exactly what is free (account, directory profile, which platforms) so the site can say it.
3. Approve the adviser and institution pathway as the strategic channel, and who leads outreach.
4. Approve changes to the queued posts (links, closing lines, the identical cross-posts); nothing queued has been touched.
5. Which platforms may be shown as working in demonstrations (Emall, Ehail and Auction depend on owner items).
6. Whether to build the hero product panel from real screens or from a staged demo workspace.
