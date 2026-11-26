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

## Decisions made while building (2026-11-26)

1. **Dot.Sheet** moved to Manage, after Dot.Forms, at the owner's request.
2. **Grow** therefore holds no platform: every platform that was in Grow now has an explicit place elsewhere. It shows
   InfoDot's own live growth features instead: bookings, page insights, seminars and company knowledge.
3. **Dot.Central** stays off the homepage: it has no agreed visitor-facing description. **Dot.Agents** stays off as before
   (`BusinessTools::shown()` excludes it).
4. **Special Platforms** copy describes what someone can do with each platform (`opportunity` in
   `config/business_tools.php`), keeps each platform's existing limits (Ehail's routing service and tariff, Charts not being
   financial advice) and says signing up is "a start, not a promise of income, work or customers".
