# R-66: business-to-business booking

Owner direction, 2026-11-25: booking should work like the inbox. On your own business page you manage; on every other business page you can ask for an appointment. Decisions taken the same day:

1. The settings button on your own page is renamed **Manage bookings** and stays beside **Inbox**.
2. **Every other business page offers an appointment.** Where the business has published a calendar, the button is **Book an appointment** (the R-63 flow, unchanged). Where it has not, the button is **Request an appointment**: a dated request the business accepts or declines, carried like a message.
3. **A booking or request made by a company member is recorded as from that company**, so both sides see it as business to business.

## What exists today (audited)

| Piece | State |
| --- | --- |
| Own page | Members see Inbox and Bookings (settings); visitors see Message business and, only when bookable, Book an appointment (`business/show.blade.php`, `BusinessProfileController::bookable`). Already the inbox pattern; only the label misled. |
| Bookable | Feature open + business switched on + calendar published + at least one active appointment type. No business on the live site meets this yet. |
| Booker's company | `bookings.customer_company` is free text the person types; no link to a company. Enquiries (`enquiries`) carry `user_id` only, so the inbox does not record the sender's company either. |
| Requests without a calendar | Nothing. A `Booking` needs an appointment type, a start and blocked time, so it cannot hold an open request. Enquiries have categories (`config/enquiries.php`) and a status flow that fits accept or decline. |

## Phases (one PR each)

**Phase 1 (this PR).** Rename the button to Manage bookings; a test that your own page offers Manage bookings and never Book an appointment.

**Phase 2: the booking company.**
- Migration: nullable `customer_team_id` on `bookings` and `enquiries` (foreign key to teams, null on delete), indexed.
- Confirm step and message form: when the person belongs to one or more companies, a "Booking for" choice (their companies, or "Just me"), checked server side against membership; the business's own members are still refused.
- The business sees the company as a link to its business page on the board, the appointment page, the CSV and the inbox; the booker sees "Booked for" on My bookings. `customer_company` is filled from the company name for old readers.
- Privacy exporter and eraser cover the new column. Tests for membership checks, display and erasure.

**Phase 3: Request an appointment.**
- Every business page that is not your own and accepts messages shows Request an appointment when it has no published calendar (Book an appointment when it has one).
- The request form: preferred date, preferred time or part of day, length, what it is about, and "Booking for". It creates an enquiry in a new `appointment` category with the requested date and time in new nullable columns, so it lands in the business inbox.
- In the inbox the business can Accept (with the confirmed date and time) or Decline (with a reason); the requester is told through the existing enquiry notices and mail. An accepted request is recorded on the enquiry; it does not reserve calendar time, because the business has no calendar.
- Limits reuse the enquiry throttles and blocks. Tests for visibility rules, the form, accept, decline and notices.

**Phase 4: QA and deploy** on the owner's instruction, with the usual backup first.

## Not in scope

A company-wide "our bookings" list for the booker's colleagues (bookings stay with the person who made them, labelled with the company), payments or deposits for appointments, and syncing with outside calendars.
