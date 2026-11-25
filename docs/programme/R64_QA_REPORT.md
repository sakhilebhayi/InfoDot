# R-64 quality assurance report (phase 12)

Run on 2026-11-25 against main `e65877b9a` (the deployed code), in the project's own Playwright harness (`tests/Browser`, real Chromium, Firefox and WebKit, a fresh SQLite database with the demonstration content, no request to the live Dot hosts). The app was built the way production builds it (no websocket key).

## What was run and what it showed

| Check | Result |
|---|---|
| New spec `m-r64-ui.spec.ts`, Chromium, signed in: eight R-64 pages (Home, directory, questions, notifications, notification settings, onboarding, profile, a legal page) at ten widths from 320 to 1440 | No page wider than its screen at any width; no console error or failed request. One finding: the "On this page" toggle on legal pages was 21 px tall at desktop widths. Fixed here (it now has a minimum height). |
| Same spec, accessibility (axe, WCAG 2.0/2.1/2.2 A and AA) on those pages in light and dark | No serious or critical violation and no colour-contrast finding. |
| Notification settings saved: the pop-up appears after the redirect, is announced politely, dismisses, and does not return on the next page | Passes. |
| Home scroll reveal: everything marked is revealed after scrolling; with reduced motion the hiding class is never added and nothing is transparent | Passes. |
| Reading bar on a legal page follows the scroll (about 0 at the top, above 0.9 at the end) | Passes in Chromium. |
| A form cannot be sent twice (a slowed sign-in post pressed twice is sent once) | Passes. |
| Existing journeys, Chromium desktop (36 tests): new user (register, verify, profile photo, signed-in areas), business (edit, logo and cover crops, directory, enquiry), permissions, failure paths (including turned-away photo and attachment uploads), keyboard, the accessibility scans of public and signed-in pages, the investor walkthrough, case pages | 29 pass. |
| Phone and tablet (iPhone 14 WebKit, Pixel 7 Chromium, iPad): the full journey, no sideways scroll, every primary button at least 44 by 44 | All pass. |
| Firefox and WebKit desktop (the cross-engine set) | WebKit passes. Firefox: 7 of 8 pass; see below. |

## Not caused by R-64, found on the way

- Seven existing browser tests failed for reasons that predate R-64, because earlier programmes changed the product and the tests were not updated: the sign-up form has first and last name (R-48), a newly confirmed person lands on company onboarding instead of Home and company creation moved into the wizard (R-62), the contact page heading changed, the harness's reserved-domain addresses were refused by the reserved-domain rule, and a harness build picked up a local websocket key. Those were fixed in the harness (environment, sign-up helper, three specs) so the journeys run again.
- Still failing and not R-64: `k-platform-access` (expects 403 where an unavailable platform now answers 503, R-61) and `l-platform-catalogue` (the catalogue's wording changed, R-62), both light and dark; and the keyboard test for the **Dot Apps** menu. That last one is a real, small accessibility finding in the shared switcher: for a person whose company has no platforms, Tab leaves the panel after its filter field, the panel stays open, and Escape then does nothing because focus is outside it. It reproduces with all four R-64 scripts switched off, and the switcher files were not touched by R-64. It belongs to dot-ui (shared package, its own versioning): close the panel when focus leaves it.
- Firefox only: after saving the profile photo and name, the header avatar and name did not refresh within 30 s (Chromium and WebKit pass). The header is plain Blade since R-52, so it only updates on the next page load; the engines differ on timing. Not traced to an R-64 file; not verified against a pre-R-64 build.

## What is NOT verified

- Smoothness of the motion on a real, low-powered phone. The preview and the harness run on a fast computer; layout shift was zero and no long task appeared, but frame rate on a weak device has not been measured.
- The reduced-motion path was checked in Chromium only.
- The scroll-linked reading bar needs a browser that supports scroll-driven animations; it is absent (by design) elsewhere.
- The booking pages (the `bookings` feature is closed in production), the digest email as delivered, queue behaviour with a real queue, and mail rendering in real mail clients (the dark-mode rules are ignored by some clients, for example Outlook desktop).
- The other Dot platforms: nothing of R-64 has been rolled out to them yet.
