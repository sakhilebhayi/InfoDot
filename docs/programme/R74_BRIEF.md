# R-74: restructure the business growth section and platform categories

Owner brief, 2026-11-26, summarised; the owner's exact platform lists and order are kept word for word. The full text is
in the session that built it.

## Objective

Restructure the homepage section "Imagine what your business could become with the right things behind it" into five
journeys, in this order:

- **Start:** I have a business idea, or I want to start a business. Platform: InfoDot only (not Dot.Tutor).
- **Manage:** run your business. Dot.Finance, Dot.HR, Dot.Projects, Dot.Files, Dot.Tasks, Dot.Docs, Dot.Press, Dot.Forms,
  in that order. (Owner follow-up, same day: also Dot.Sheet.)
- **Connect:** build relationships and visibility. Dot.Notify, Dot.Pulse, Dot.Engage, Dot.Analytics, in that order.
- **Grow:** every remaining live business-growth platform not assigned elsewhere.
- **Special Platforms:** for people without a business idea who want ways to earn, create, offer services or develop
  skills. Dot.Mines, Dot.Tutor, Dot.Design, Dot.Auction, Dot.Emall, Dot.Ehail, Dot.Charts, in that order. Suggested
  heading "Not every opportunity starts with a business idea." Never imply guaranteed income, work or customers.

Keep the existing design and interactions; audit the live registry first; no invented or unfinished platforms; no
duplicates; tabs usable on phone and tablet; keyboard, focus, links, logos and animations verified.

## Owner's final allocation (2026-11-26, replaces the lists above)

- **Start:** InfoDot.
- **Manage:** Dot.Finance, Dot.HR, Dot.Projects, Dot.Files, Dot.Tasks, Dot.docs, Dot.Press, Dot.Sheet.
- **Connect:** Dot.Pulse, Dot.Engage, Dot.Notify.
- **Grow:** Dot.Analytics, Dot.Forms, beside InfoDot's own growth features (bookings, page insights, seminars, company
  knowledge).
- **Special Platforms:** Dot.Mines, Dot.Tutor, Dot.Design, Dot.Auction, Dot.Emall, Dot.Ehail, Dot.Charts.

Every one of the 21 live public platforms on the homepage appears exactly once.

## Decisions made while building (2026-11-26)

1. **Dot.Central** stays off the homepage: it has no agreed visitor-facing description. **Dot.Agents** stays off as before
   (`BusinessTools::shown()` excludes it). Dot.Memory and Dot.Dopemine are internal.
2. **Special Platforms** copy describes what someone can do with each platform (`opportunity` in
   `config/business_tools.php`), keeps each platform's existing limits (Ehail's routing service and tariff, Charts not being
   financial advice) and says signing up is "a start, not a promise of income, work or customers".
