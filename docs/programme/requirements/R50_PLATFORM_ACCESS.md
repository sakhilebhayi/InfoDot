# Requirement R-50: platform catalogue, access and commercial state

Source: owner brief of 2026-11-13, summarised here. The approved specification is `docs/programme/r50/ARCHITECTURE.md`; the audit
evidence and the landing brief sit beside it. This file records what was asked for and what the owner decided, so the reason
behind the code can be found without the conversation it came from.

## What was asked for

Before R-50 a platform being in the list and switched on was the only thing checked: any signed-in person could open every
platform, InfoDot's own Analytics page showed the whole fleet as if each business had all of it, and a platform had a name and
an icon but no description, category or price. The owner asked for:

1. A catalogue: what each platform is, who it suits and which category it belongs to, kept in one place.
2. InfoDot as the one place that says which company may use which platform, and which people in that company.
3. A Platforms page that is a catalogue for the company in front of it: its own platforms first, the rest by category, each
   with its state in plain words and one next action.
4. A commercial model with plans, bundles and trials, in which no price is ever invented: a price is shown only when an
   approved plan row carries it.
5. Recommendations that rest on real facts about the company, each with its reason, never on invented insight.
6. Analytics that shows a business its own platforms. Whether a platform's server answers is a matter for staff.
7. A landing experience per platform, built inside that platform's own design direction.

## The owner's decisions (2026-11-13)

1. **Four separate concepts, never interchangeable:** registration (the platform exists in the registry), operational status
   (it is working), business entitlement (this company may use it) and commercial status (how it is paid for).
2. **A free core for every company, with no activation step:** InfoDot, Dot.Files, Dot.docs, Dot.Tasks and Dot.Finance.
   Dot.Finance stays reachable in every state, because it is where a company pays.
3. **Existing companies start with the free core only,** like a new company, and switch the rest on themselves.
4. **A platform with no approved price can be switched on free by the company itself,** shown as "free while pricing is being
   finalised". Nothing is ever charged without a new, explicit consent step once a price exists. No price is invented: a
   platform without an approved price is flagged as "pricing not configured" for administrators.
5. **A platform administrator role,** granted only by a command on the host, manages the catalogue, prices, bundles and
   complimentary access. Moderators get no commercial powers. Every change is audited.
6. **Payments go through Dot.Finance.** Paid activation is a state machine that only a verified, signed confirmation from
   Dot.Finance can complete. Until Dot.Finance can send one, no paid plan can be started and nothing simulates a payment.
   Platforms that were discontinued never appear.
7. **No business milestone ever gates a platform.**

## Build order

| Step | What | Where |
|---|---|---|
| 1 | Data model, the access decision, enforcement at every door, refusal pages, the administrator role, history and outbox | merged (#225) |
| 2 | The Platforms page and the page of one platform, switching on and off, people access, recommendations, the Analytics panel, Dot Apps | this document's step |
| 3 | The administrator centre, plans, bundles, trials, complimentary access, the signed payment confirmation | separate work |
| 4 to 8 | Other platforms, landing experiences, payments in Dot.Finance, the end-to-end scenarios | later |

## Step 2 as built

- **Platforms** (`/platforms`): every platform a business may be shown, with its original logo, name, one-line purpose and the
  company's state from `PlatformAccess::businessState`. "Your company's platforms" first, then the catalogue's categories.
  Search by name or by need; filter by category and by state. One action per platform: Open, Switch on, Open Dot.Finance or
  See details.
- **One platform** (`/platforms/{key}`): what it is for, who it suits, what it does, what it costs (approved plans only, or
  "free while pricing is being finalised"), the company's state and the reason, who may open it, and the company's history
  for that platform (owner and administrators only).
- **Switching on and off:** each behind a page that says what will happen. Owner or administrator switches on; only the owner
  switches off. An unpriced platform is free for now, and the page says that no charge will ever be made without new, explicit
  consent.
- **People:** everyone in the company or selected people; the owner and administrators always. Every change is in the history.
- **Recommendations:** rules in `config/platform_recommendations.php`, tested against the company's own records only
  (industry chosen, people, platforms held, counts of what it has done in InfoDot). Each shows its reason and can be dismissed
  for the company. With nothing true to say, nothing is shown.
- **Analytics:** the panel that listed the fleet now lists the company's own platforms, of those in the catalogue.
- **Dot Apps:** only what the company holds and the person may open, grouped by catalogue category, with a link to Platforms.
- **Notices:** the owner and administrators are told in InfoDot when the company's entitlement to a platform changes.

## Rules that hold for all of it

- Access is decided in one class and enforced server-side at every door. A page never decides.
- Everything acts on the person's current company. No address names a company, and a form drawn for one company is refused
  for another.
- Nothing destroys data. Switching a platform off keeps the entitlement, its history and what the company made.
- No price, count, activity or recommendation is shown that a record does not carry.
