# R-13 privacy and safety gaps: what the legal drafts found, what is now fixed (2026-10-29)

The legal drafts in `docs/programme/legal/` described behaviour the code did not yet have. This note lists each gap, the fix on branch `fix/r13-privacy-gaps`, and what is still open. Nothing here is deployed until the owner says so.

## Fixed in code

| Gap | Fix | Where |
|---|---|---|
| Account deletion left seminar, score and ticket data behind | `SeminarDataEraser`: first settles what is live (the person's upcoming seminars are cancelled and every ticket holder refunded in full; their own upcoming registrations are cancelled by the usual 24-hour rule), then deletes: seminars with no ticket ever sold (with registrations, ratings, reports, bans, room data), free registrations, written notes on ratings (the star rating stays in the host's average), link reports, seminar reports they made, room bans, payout account link, chat and peer rows, score snapshots and appeals, ring flags that name them, badges | `app/Services/Privacy/SeminarDataEraser.php`, hooked in `app/Actions/Jetstream/DeleteUser.php` |
| Money records had to survive deletion | Kept, with nothing readable on them: paid tickets, refund lines, ticket events, commission invoices. A host who sold tickets keeps the seminar title and its record but the description and meeting link are blanked; seller details and the host agreement are kept only where money moved or a debt is open. Shown as "A former member" | same |
| Screens broke for a deleted host or attendee | `Seminar::host()` and `SeminarRegistration::user()` default to a "former member"; notices to a missing person are skipped instead of failing | models, `InAppNotices::record` |
| Score opt-out did not match its text | Opting out also deletes ring flags that name the person, excludes them from future ring detection, and the export includes ring flags | `ReputationEngine`, `RingDetector` |
| Small rooms showed IPs without telling people | Click-through before joining: IP visible to the people passing the video on, possible relay, the STUN server named, nothing recorded, chat kept | `p2p-room.blade.php`, `seminar-p2p.js` |
| The notifications switch silenced service notices | Seminar cancelled, changed, spot open, refunded, disputed, link broken, money owed and removed are always recorded; the page says so | `UserNotice::SERVICE_KINDS`, `InAppNotices` |
| No way to report or remove a seminar | Signed-in people report (one report each, host cannot report their own); moderators get `/moderation/seminars`, can dismiss a report or remove a seminar. Removal cancels it, tells registrants, refunds everyone who paid in full, tells the host why, closes the reports and is written to the moderation record | `SeminarReport`, `SeminarModerationController`, `SeminarService` |
| A removed person could rejoin a room | Room bans for LiveKit and small rooms, including removal before the person ever entered | `seminar_room_bans`, `P2pRoom`, `SeminarService::roomRefusal` |

## Still open (owner or adviser)

- Retention periods are not written down. The eraser keeps money records without a stated period; tax advice decides how long.
- Paystack and Dot.Billing hold their own copies (payout account, transactions). Deleting the InfoDot account does not delete those; the privacy policy must say so, and a Billing-side erase is a separate item.
- No per-host opt-out of Dot Points; badges are public by default with the opt-out.
- Moderators removing a seminar is a decision with money consequences (full refunds). The wording of the host agreement on this is not approved.
- The reporter's identity is shown to moderators, never to the host. The policy text must say that.
- The legal drafts must be updated to match this note before they are shown to an adviser.
