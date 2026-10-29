> **DRAFT. WRITTEN BY AN AI SESSION. NOT LEGAL ADVICE. NOT REVIEWED. NOT LIVE.**
>
> Written by an AI coding session (Claude) on 2026-10-29 from the source code of this repository (`origin/main` at commit `5c916c5`) and the R-13 programme documents. No lawyer, Information Officer or owner has read it. It must not be copied into `resources/markdown/policy.md` (or any view), published, or shown to users until the owner's adviser has reviewed, corrected and approved it. Every `[OWNER/ADVISER]` marker is a decision or a legal question that this draft deliberately does not answer. Every `[PLACEHOLDER]` is a fact (a name, number, address or period) that the code does not contain and that this draft refuses to invent.
>
> **Reconciled 2026-10-29 against `origin/main` at `de65f55` (InfoDot #156).** The text and boxes now describe what the code does after the privacy and safety fixes (account deletion erases R-13 data, the score opt-out also removes ring flags, small rooms ask before connecting, service notices ignore the notifications switch, seminars can be reported and removed, and the host agreement screen and debt notices exist). Each place that changed says so. Still a draft, still unreviewed, still not live.
>
> Companion files: [terms-additions.md](terms-additions.md) and [legal-review-checklist.md](legal-review-checklist.md). Item numbers such as **C-12** point to rows in the checklist.

# Privacy policy: additions for the R-13 features (draft)

## How to read this file

- **Part A** is proposed policy text, in the voice and structure of the live policy (`resources/markdown/policy.md`): "we" is **BluePin Inc**, the responsible party the live policy names, and the Information Officer is reached at `privacy@infodot.co.za`, both exactly as the live policy has them. Whether BluePin Inc is the right legal entity is an owner question that already exists in the programme (see `POPIA_READINESS.md` section 1, last row) [OWNER/ADVISER]. The text under each heading is written so it can be lifted into the policy once approved.
- Under each activity a box headed **For the owner and adviser (not for publication)** carries what a policy page must not carry: the lawful-basis candidates, cross-border flags, retention gaps and the code that each statement was checked against.
- **Part B** is the table of operators and third parties.
- **Part C** lists the edits the existing policy sections need.
- **Part D** is the "how to use your rights" table.
- Publish a section only when its feature is switched on. The switch for each is named in its box (all are **off** in production today per `R13_GO_LIVE.md`).
- Every statement of fact was checked in the code. Where something could not be checked (for example what a third party does with data), the text says so or the checklist marks it **not verified**. Nothing in this file says data is encrypted, or deleted after N days, unless the code does it; where no retention exists, that is stated.

Proposed placement: a new block of sections after the live section 4 ("Ecosystem single sign-on"), with the live sections 5 to 11 renumbered. Numbering is the adviser's call. The headings below use **R1** to **R11** for now.

---

# Part A. Proposed policy text

## R1. Seminars: registering, attending, rating

*Switch: none; free seminars are on whenever the Seminars pages are deployed. Check before publishing that this is the intended state.*

Any signed-in member with a verified email address can host a seminar. Seminars are listed for signed-in members only.

**What we collect and keep**

- **If you host:** the seminar's title, summary, description, topic, start time, length, number of places, price (if any), how it is delivered (your own meeting link, a small room, or the InfoDot room) and, for your own meeting link, the link. We also create a short random share code for the seminar and, for small rooms, record the upload speed your browser measured when you tested your connection.
- **If you register:** a record that links your account to the seminar, with its status (registered, on the waiting list, cancelled or, for paid seminars, waiting for payment), when it changed, and whether and when you were noted as having attended.
- **If you rate:** your rating (1 to 5) and, if you choose, a note of up to 1,000 characters.
- **Seminar reminders and changes** reach you as in-app notifications (see R10). We do not send seminar emails.

**Why we use it:** to run the seminar (places, waiting list, who may enter and when), to tell you about reminders, changes and cancellations, to let hosts see who is coming, to show how well hosts are rated, and, if those features are switched on, to work out the knowledge score (R7) and Dot Points for hosting (R9).

**Who sees it**

- **The host** sees the names of the people who are registered or on the waiting list, whether each was noted as attending, and, for paid seminars, each person's ticket status. The host does not see your email address. The host sees the ratings and notes people leave, but not who left which one.
- **Other signed-in members** see a seminar's page, its host's name and, for the host, how many seminars they have hosted, how many people attended, and their average rating once at least 3 people have rated. They see how many places are taken, not who is registered.
- **Moderators** can see payment problems on paid seminars (R2), reports about seminars (below) and the score appeals and ring flags described in R7.

**Reporting a seminar.** Any signed-in member can report a seminar as misleading, harmful, a spam or scam, or something else, with an optional note of up to 1,000 characters. We keep the report (which seminar, who reported, the reason, the note, its status and the moderator's decision and note). **Only moderators see a report. The host is never told who made it.** A moderator dismisses the report or removes the seminar. If a seminar is removed it is cancelled, the people who registered are told, and the host is told the reason the moderator wrote. If it had not started, everyone who paid is refunded in full; if it had, the moderator chooses whether everyone who paid, only the people who reported it, or no one is refunded. The host can appeal once (we keep the appeal message and the answer), and a different moderator decides. You can also report a person in a seminar by the name shown in the room; we keep the name you typed, your report and the decision, and the person is not told who reported them; the moderator's name, the reason and the time are kept in our moderation record, which is not changed afterwards. If you delete your account, the reports you made are deleted.

**How we record attendance.** You are noted as attending when you open the host's meeting link from InfoDot, when you connect to a small room or the InfoDot room, or when the host marks you. The host can mark or unmark any registered person. Only people noted as attending can rate a seminar, and only once it has finished. A host's marking therefore affects a person's own attendance count.

**How long we keep it.** Closed reports about seminars and decided removal appeals are deleted 12 months after they were decided. Seminar, registration and rating records are otherwise kept while your account exists [PLACEHOLDER: retention period for these while an account exists]. Notifications are removed on a schedule (R10). **When you delete your account** (see "Deleting your account" below) most of this is erased then. A copy of what we hold is available from "Download my seminar data" on the Seminars page.

**Deleting your account.** Before your account is deleted we settle what is live: the seminars you host that have not happened are cancelled (everyone who paid is refunded in full), and your own upcoming registrations are cancelled under the usual refund rule (a full refund if you cancel at least 24 hours before the start, otherwise none). Then we delete: the seminars you hosted if you never sold a ticket (with their registrations, ratings, reports and room records), your free registrations, the written notes you left with ratings (your star rating stays in the host's average without your name), the broken-link reports and seminar reports you made, any room removal recorded against you, your payout account link, your chat messages and room records, your knowledge score data and any ring flag that named you, and your badges. **We keep, with your name and account gone**, the records money moved through: paid tickets, refund lines, payment events and commission invoices; and, if you sold tickets, the seminar's title and record (its description and meeting link are removed) and your seller details and host agreement, which we keep where money moved or a debt is still open. A person shown on those records appears as "A former member". These money records are deleted **five years after the last payment or invoice they relate to** (a nightly job; a ticket with an open dispute and a host with an open debt are kept until that is settled). [OWNER/ADVISER: five years is the owner's proposal; the adviser must confirm it against the record-keeping periods that apply to invoices and payments, and it can be changed with `SEMINARS_MONEY_RETENTION_YEARS`.]

**Your choices.** You can cancel a registration yourself at any time. You can switch off in-app notifications (R10), but see the warning there. To see or correct the information we hold about you, or to ask for it to be deleted, write to the Information Officer (Part D).

> **For the owner and adviser (not for publication) , R1**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: running the seminar you registered for is arguably performance of a contract (POPIA s11(1)(b)); host statistics and ratings may be a legitimate interest of the responsible party and of other members (s11(1)(f)); consent is not the natural basis for any of it. Choose, and record the choice.
> - **Recipients:** the host (names, attendance, ratings and notes without names), other members (counts only). No seminar data leaves InfoDot for free seminars, except to the host's own meeting service when you follow the host's link (R5).
> - **Cross-border (s72):** none for the registration data itself. The host's chosen meeting service or room server may be abroad (R3, R4, R5).
> - **Retention:** a nightly job (`php artisan seminars:prune`, 03:50; `SeminarRecordPruner`; periods in `config/seminars.php` `retention`, owner decisions of 2026-10-29) deletes closed reports and decided appeals after 12 months and money records of deleted accounts after 5 years (the 5 years is a proposal for the adviser to confirm). Seminars, registrations and ratings of people who still have an account have **no schedule** (C-02). **Account deletion now erases most seminar records and keeps money records unreadable (fixed in #156, was D-05 and E-02).** `app/Services/Privacy/SeminarDataEraser.php` is called from `DeleteUser`: `settle` (outside the delete transaction, because it talks to Billing) cancels the person's unfinished hosted seminars through `SeminarService::cancel` and cancels their upcoming registrations through `unregister`; `erase` (inside the transaction) deletes as the text says. What it keeps: registrations with a `ticket_ref` (paid or pending money), `seminar_refund_lines`, `seminar_ticket_events`, `seminar_commission_invoices`; for a host who sold, the seminar row with description and link blanked; seller profile, host agreement and debts where `seminar_refund_lines` or invoices exist or a debt is open. The kept rows still carry the numeric user id (there is no foreign key), which no longer points to anyone; the adviser may still regard that as pseudonymised, not anonymous data. Screens show "A former member" (`Seminar::host()` and `SeminarRegistration::user()` use `withDefault`). Tests: `SeminarReportsAndErasureTest`.
> - **Not erased by this:** Dot.Billing's and Paystack's own copies (payout account code, transactions, buyer email) are outside this repository; Dot.Dopemine's points record is outside it too. A Billing-side erase is a separate item.
> - **Integrity point for the adviser:** the host controls the attendance flag, and attendance feeds the attendee's own Learning points and, through ratings, the host's Trust points (R7) and Dot Points (R9). Information quality (s16) and a host marking friends as attendees are worth a sentence from the adviser.
> - **Reports (fixed in #156, was E-03):** `database/migrations/2026_10_29_100012_seminar_reports_bans_removal.php`; `SeminarReport`; `SeminarService::report`, `removeByModerator`, `dismissReport`; `SeminarModerationController`. The reporter's name is shown to moderators on `/moderation/seminars` and never to the host. A report holds free text of up to 1,000 characters, so it can hold anything (B-17). Closed reports and decided appeals are pruned after 12 months (`SeminarRecordPruner`); the moderation record has no schedule and is append-only by design. **Added 2026-10-29:** a report can name a person in the room (`about_name`); a host can appeal a removal once (`seminar_removal_appeals`, message up to 1,000 characters); both are free text (B-17). The download is `SeminarsController::exportMyData` and `SeminarDataExporter`.
> - **Checked in code:** `database/migrations/2026_10_29_100003_create_seminars_tables.php` (columns above); `app/Services/Seminars/SeminarService.php` (`register`, `joinUrl` sets `attended_at`, `markJoinedFromRoom`, `setAttended`, `rate`, `hostStats`); `resources/views/seminars/show.blade.php` (host sees `$p->user->name`, status, attended; feedback list shows rating and comment without a name; `min_ratings_shown` = 3 in `config/seminars.php`); `routes/web.php` (all seminar routes inside `auth:sanctum` + `verified`); no `Mail::` call in `app/Services/Seminars/`.

## R2. Paid seminars: payments, receipts, refunds, disputes and what hosts owe

*Switch: `SEMINARS_PAID_ENABLED` (default false) and Dot.Billing `BILLING_MARKETPLACE_ENABLED`. Do not publish until the Paystack test-mode run and the adviser's sign-off in `R13_GO_LIVE.md` step 3 are done.*

A host can charge for a seminar. The host is the seller. We collect the payment for the host through our payment partner and keep a commission.

**What happens to your payment details.** You pay on Paystack's own page. Your card or bank details go to Paystack, not to InfoDot, and we never see or store them.

**What we collect and keep, if you buy a ticket**

- To start the payment we send your **email address**, the amount, the currency, the seminar's title and a random ticket reference to Dot.Billing, which passes the email address and amount to Paystack.
- On InfoDot we keep the ticket reference, the amount, the currency, the payment status (pending, paid, part refunded, refunded, charged back), when it was paid, any refund (amount and time) and any dispute (status and time). While you are paying, the place is held for 30 minutes, and then released.
- You can open a **receipt** for a ticket you paid for. It shows your name and email address, the seller, the seminar, the amount, any refund, and a receipt number. It is headed "Receipt", unless tax invoices have been switched on and the host gave a VAT number and authorised us, in which case it is headed "Tax invoice".

**What we collect and keep, if you host a paid seminar**

- A **payout account**: the name on the account, the bank, and the account number. The account number goes from your browser through our server to Dot.Billing and on to Paystack. InfoDot does not store or log it; InfoDot keeps only Dot.Billing's identifier for the account, the bank code and the **last four digits**. Dot.Billing keeps Paystack's code for the account, the bank code, the last four digits and the name on the account.
- **Seller details**: the trading name to show on receipts, an optional VAT number, and whether you authorise InfoDot to issue receipts in your name.
- A numbered **commission invoice** for each month in which you sold or refunded tickets. It names you, lists the tickets and seminars it covers, and shows InfoDot's details, the commission and the VAT.
- The **record of the host agreement** you accept before selling tickets (which version and when), and any **debt** from a lost chargeback (see below). InfoDot keeps a copy of each debt's amounts and status as Dot.Billing reports them.

**Why:** to take payment for a ticket, give you a place, show you a receipt, refund you when you are entitled to it, pay the host, charge our commission and invoice it, deal with disputes with banks, and keep the financial records the law requires.

**Refunds.** If you cancel at least 24 hours before the seminar starts you are refunded in full (whatever has not already been refunded). Later than that, your place is released and no money moves. If the host cancels the seminar you are always refunded in full, and so are you if a moderator removes the seminar (see R1). If a payment arrives after the seminar was cancelled, ended or filled up, it is refunded. The host can also refund part or all of a ticket at their own choice. Refunds go back through Paystack and can take a few days to show on your statement.

**Disputes with your bank.** If you ask your bank to reverse a payment, Paystack tells Dot.Billing, which tells us. The ticket is marked disputed and the host is told. A person on our side answers the dispute in Paystack's dashboard; nothing is sent automatically. If the dispute is lost, the ticket is removed (you lose the place) and our commission on it is reversed.

**Hosts and lost disputes.** If a bank takes money back after a lost dispute, the host agreement (see the Terms) lets us recover what we lost from the host's later ticket sales. To do that, Dot.Billing records the host's agreement and the debt, and tells InfoDot. The host sees what they owe, what has been taken from later sales and what is left on their payout page, and is told in InfoDot when a debt opens, is paid down, is cleared or is written off. [OWNER/ADVISER: wording depends on the final host agreement, including the part on removal of a seminar by a moderator, which is still a draft.]

**Who receives it:** Dot.Billing (our payment system) and Paystack (the payment provider), as set out in Part B. The host sees that you hold a ticket and its status, not your email address.

**How long we keep it.** While your account exists, tickets, refunds, disputes, invoices and seller records are kept [PLACEHOLDER: retention period for people who still have an account]. After an account is deleted, the money records that remain are deleted five years after the last payment or invoice (see R1, "Deleting your account"). Dot.Billing's own retention is outside this repository and has not been checked.

**Your choices.** You can cancel a ticket (refund rules above). You can ask the Information Officer for the information we hold (Part D).

> **For the owner and adviser (not for publication) , R2**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: contract (ticket purchase; host agreement and payout, s11(1)(b)); legal obligation for invoices, VAT and tax records (s11(1)(c), the adviser to name the statutes and periods); legitimate interest for dispute handling and chargeback recovery (s11(1)(f)).
> - **Recipients:** Dot.Billing, Paystack, the host (status only), moderators (disputes page). The attendee's email address goes to both Billing and Paystack.
> - **Cross-border (s72):** Paystack's legal entity, where it processes and where its servers are, are **not stated anywhere in the repository**. Dot.Billing's hosting location is the ecosystem host's. [ADVISER: s72 ground, operator agreement under s21, Paystack's terms, whether Paystack is an operator or a responsible party for card data.]
> - **Merchant of record / seller:** the owner decided the host is the seller and InfoDot a marketplace that collects the payment (R13_PAID_SEMINAR_POLICY.md). Whether that holds when InfoDot takes the money into its own Paystack account and settles the host's share, and what that makes InfoDot for POPIA purposes, is an adviser question (B-04).
> - **Retention:** for deleted accounts, five years after the last payment or invoice (`SeminarRecordPruner::money`: tickets with no open dispute, their ticket events and refund lines; commission invoices; seller profile, agreement and settled debts once nothing else of the host's remains; the empty seminar shell). *None* for people who still have an account (C-02, C-03). The adviser must confirm five years.
> - **Built since the first draft (E-01 resolved):** `SeminarPayoutsController` (agreement text and acceptance), `BillingMarketplace::acceptAgreement`, `Api\SeminarTicketEventsController` and `HostDebtLedger` (`host_debt.*`), `SeminarHostDebt`, and the "What you owe InfoDot" section of `payouts.blade.php`. The agreement text is shown only when `SEMINARS_HOST_AGREEMENT_APPROVED` is true.
> - Paystack may also send the buyer its own payment emails or receipts; that is Paystack's behaviour and was not checked.
> - **Known unverified behaviours the text must not over-claim:** how Paystack splits a *refund* between host and InfoDot (not verified; `R13_PAID_SEMINAR_POLICY.md`, `seminar-payments.md`); that a lost dispute's money comes from InfoDot's account (stated in the host agreement draft, not verified against a real dispute); whether Paystack's own fee is refunded.
> - **Checked in code:** `database/migrations/2026_10_29_100006_paid_seminars.php`, `..._100007_paid_seminar_refunds_disputes_and_tax.php`; `app/Services/Seminars/BillingMarketplace.php` (`createTicket` sends `buyer_email`, amount, currency, description cut to 140 characters, return URL; `createPayoutAccount` sends the account number; no request body is logged; the Paystack link must be on a paystack.com or .co host); `app/Http/Controllers/SeminarPayoutsController.php` (account number validated and relayed, only Billing's id, bank code and last four kept; the form is redisplayed without the number); `app/Services/Seminars/SeminarService.php` (`startCheckout` 30-minute hold, `unregister` and `refundWindowOpen`, `cancel`, `markPaid` refunds a late payment, `hostRefund`, `applyDispute`, `applyDisputeResolution`); `app/Services/Seminars/CommissionInvoices.php`; `resources/views/seminars/receipt.blade.php`; `config/seminars.php` (`paid`). The attendee's name and email appear on the receipt (receipt view).

## R3. Small rooms (video between browsers)

*Switch: `SEMINARS_P2P_ENABLED` (default false). Do not publish until the real-network tests in `R13_SMALL_ROOMS.md` pass. The room page now carries the sentence about addresses and waits for a click (fixed in #156, was E-06), but the real-network tests still gate publication.*

A small room carries video straight from the host's browser to the viewers' browsers, with no media server. InfoDot's server decides who passes the video to whom, passes the small messages browsers use to find each other, and carries the room's chat.

**What this means for your information**

- **Addresses are visible to the people you are connected to.** Because the browsers connect directly, **a viewer can see the internet (IP) address of the person who feeds them, and the host can see the addresses of the people the host feeds.** Some viewers pass the video on to up to three others; if your computer is able to (a computer, not a phone, not on mobile data or data-saver mode, with at least 4 processor cores), it may be asked to, and it then uses your own bandwidth and processor while the room is open. The people it feeds can see your address.
- **A third-party "STUN" server sees your address.** Your browser asks a STUN server what its public address is, so that browsers can connect. By default InfoDot uses **Google's public STUN server** (`stun.l.google.com`). [PLACEHOLDER: replace with the server InfoDot actually uses if it runs its own.] No video passes through a STUN server. If InfoDot configures a relay ("TURN") server, video from people who cannot connect directly passes through it. [PLACEHOLDER: TURN operator, if any.]
- **What InfoDot's server keeps:** for each person in a room, a random room identifier, whether you are the host or a viewer, who feeds you and how far you are from the host, whether you are able to pass video on (a yes or no; we do not store your device details), when you were last seen and your status. The connection messages between browsers pass through our server and are deleted once they are delivered. These messages can contain the network addresses of the sender, as is normal for this technology. [ADVISER: undelivered messages are not currently cleared; see E-05.]
- **Chat** messages (up to 300 characters) are stored on InfoDot with your name for the seminar and are shown to everyone in the room, including people who join later.
- **The host's connection test:** before publishing a small room the host's browser uploads two small test files so we can tell the host how many people the room can hold. The files are not kept. The measured upload speed is stored on the seminar.
- **We do not record the room.** InfoDot has no recording feature. We cannot stop a host or viewer from recording their own screen; see the Terms.

**Why:** to deliver the room and to tell the host honestly how many people it can hold.

**How long we keep it.** Room records, connection messages, chat and room removals are deleted 30 days after the seminar ended (a nightly job). Delivered connection messages are deleted as soon as they are delivered.

**Your choices.** Before anything connects, the room page tells you that the people passing video to you or receiving it from you can see your internet address, that your computer may be asked to pass video on, which STUN server is used, that nothing is recorded and that chat is kept. **Nothing connects until you press "Continue".** If you do not want that, do not continue. You can leave at any time. The host can remove you from the room, and once removed you cannot get back in to that seminar.

> **For the owner and adviser (not for publication) , R3**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: contract (delivering the seminar you registered for, s11(1)(b)); legitimate interest in running the room. The address exposure to other attendees is the point to put to the adviser: whether registration plus notice on the room page is enough, or whether the small room needs the person's explicit agreement before they join.
> - **Recipients:** the people in your path through the room (addresses), the STUN operator (your address), a TURN operator if one is configured, InfoDot (the rows above).
> - **Cross-border (s72):** the default STUN server is Google's; its location is not stated in the repository [ADVISER]. Running your own STUN/TURN (`P2P_STUN_URLS`, `P2P_TURN_*`) removes the third party; `R13_SMALL_ROOMS.md` recommends deciding this before launch.
> - **Retention (was E-05, now built):** `SeminarRecordPruner::rooms` deletes peers, connection messages (delivered or not), chat and room removals 30 days after the seminar ended (`SEMINARS_ROOM_RETENTION_DAYS`); `p2p_signals` rows are still deleted at once when delivered.
> - **Checked in code:** `database/migrations/2026_10_29_100009_p2p_rooms.php`; `app/Services/Seminars/P2pRoom.php` (`iceServers`, `join` stores `can_relay`, `signal`/`poll` deletes delivered signals, `chat` stores `name` and `body` up to 300, `remove`); `config/seminars.php` `p2p.stun_urls` default `stun:stun.l.google.com:19302`; `resources/js/seminar-p2p.js` (relay capability test is a yes/no from user agent, connection type and core count; ICE servers come from the server; comment "Nothing is recorded"); `app/Http/Controllers/SeminarP2pController.php::speedtest` ("Nothing is kept"). That connection messages contain addresses is how WebRTC works and was not observed in this repository. The room page (`resources/views/seminars/p2p-room.blade.php`) now carries a `[data-consent]` block and `seminar-p2p.js` starts only after `[data-consent-ok]` is pressed (tests in `SeminarP2pTest`). Removal is recorded in `seminar_room_bans` and `p2p_peers` (status `removed`). **On account deletion** the person's peer rows, undelivered connection messages from those peers and chat messages are deleted (`SeminarDataEraser`), and so are all room rows of a seminar that is deleted.

## R4. The InfoDot room (video through a server we run)

*Switch: `SEMINARS_VIDEO_ENABLED` plus the `LIVEKIT_*` settings (default off). Do not publish until the acceptance test in `R13_LIVEKIT_SERVER.md` passes, including its step 7 (the policy names the media server host).*

The InfoDot room runs on a LiveKit video server that InfoDot operates. [PLACEHOLDER: the company or person who runs the server, the hosting provider, and the country or region.]

**What happens to your information**

- When you open the room, InfoDot checks you are the host or are registered for this seminar, and gives your browser a short-lived entry pass. The pass carries your **name** (first 60 characters), an identifier made from your account number, and whether you are the host or an attendee. It stops working 15 minutes after the seminar ends. Your browser connects to the video server with it, and the video server sees your **internet address** and connection details.
- Only the host sends audio, video and screen. Attendees watch and can chat.
- **Chat** goes through the video server's data channel to the people in the room with your name. **InfoDot does not store the chat.** [PLACEHOLDER: what the video server itself logs and for how long, which depends on how the owner configures it.]
- **Attendance:** the video server tells InfoDot (with a signed message) when a person connects, and we note that person as having attended.
- The host can remove you from the room; a removed person cannot get a new entry pass for that seminar.
- **We do not record.** InfoDot has no recording feature and the server settings in `R13_LIVEKIT_SERVER.md` do not enable one.

**Why:** to deliver the seminar, keep the room to registered people, and record attendance.

**How long we keep it.** InfoDot keeps no copy of the video or the chat. The attendance mark is a seminar record (R1).

> **For the owner and adviser (not for publication) , R4**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: as R3.
> - **Recipients:** the LiveKit server operator (name, identity, address, media). If the owner uses a hosting provider, that provider too.
> - **Cross-border (s72):** depends wholly on where the server is. `R13_LIVEKIT_SERVER.md` notes a South African region avoids a transfer for the media; `R13_SEMINAR_VIDEO_PLAN.md` records that as an owner decision. [OWNER/ADVISER]
> - **Operator agreement (s21):** needed between InfoDot and whoever runs the server if that is not InfoDot itself.
> - **Retention:** none in InfoDot; the server's own is unknown.
> - **Checked in code:** `app/Services/Seminars/LiveKit.php` (`joinToken`: `name` cut to 60, `sub` = `'u'.$user->id`, `metadata.role`, expiry = end of seminar + `token_minutes_after_end` 15, only the host may publish; `remove`; `webhookIsValid`); `app/Http/Controllers/Api/SeminarRoomWebhookController.php` (only `participant_joined` is used); `resources/js/seminar-room.js` (chat via `publishData`, 500-character limit, shows the participant's name, no storage call); no recording code in the repository. **The server has never been run against real traffic** (`R13_LIVEKIT_SERVER.md`, first paragraph).

## R5. Seminars that use the host's own meeting link

*Switch: none (this is the default way to deliver a seminar).*

If a host uses their own meeting service (for example Google Meet, Zoom, Microsoft Teams, Whereby or Jitsi), the host types the link in and we show it to the host and to people registered for the seminar from 15 minutes before it starts.

- **When you follow the link you leave InfoDot.** The meeting service then handles your information under its own privacy policy, not ours. InfoDot is not responsible for it, and we do not control whether the host or the service records the meeting.
- **We note you as attending** when you open the link through InfoDot.
- **We check the link.** When a host saves a link, or presses "Test this link", InfoDot's server looks the address up and requests the page (it identifies itself as `InfoDot-LinkCheck/1.0`), following up to three redirects, to see whether the link is well formed and answers. It never contacts a private network address. The web site at the link sees a request from InfoDot's server, and sees whatever the link itself contains, so a host should not put a secret in a link that they would not want that site to see. We remember each answer for about ten minutes and we do not keep the link check's result beyond a short status on the seminar ("ok" or "warning") and the time it was checked.
- **"The link did not open".** A registered person can tell us the host's link did not open once the seminar has opened. We record that you reported it (so one person counts once). The host is shown a **count**, and a notice, not your name.
- **The short InfoDot link** (`/j/` followed by 8 letters and digits) opens the seminar page and asks the visitor to sign in. It does not contain the meeting link.

> **For the owner and adviser (not for publication) , R5**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: contract / legitimate interest (as R1). The meeting service is a separate responsible party for what it does.
> - **Recipients:** the meeting service the host chose (when you follow the link); the host's link target (the link checker).
> - **Cross-border (s72):** the meeting service's own; InfoDot has no control. The adviser may want a sentence making clear that the host is choosing that service.
> - **Retention:** the link is stored with the seminar and not removed; the link report row (seminar and reporter ids) is deleted only when the host changes the link; none defined otherwise.
> - **Checked in code:** `app/Services/Seminars/MeetingLinkCheck.php` (https only, public addresses only, request pinned to the checked address, redirects re-checked, `Cache::remember(..., 600)`, user agent `InfoDot-LinkCheck/1.0`); `SeminarService::save` (stores `join_url_status`, `join_url_checked_at`; deletes `seminar_link_reports` when the link changes), `joinUrl`, `reportLink`; `database/migrations/2026_10_29_100010_seminar_links.php`; the report notice contains no reporter (`InAppNotices` wording for `SEMINAR_LINK_BROKEN`). The check cache stores the result keyed by a hash of the link, in the application's cache store, for 10 minutes.

## R6. Company seats and roles

*Switch: seat limits apply as soon as the licensing code is deployed (free seats only). Reading seat counts from Dot.Billing needs `LICENCE_BILLING_CONNECTED`. Selling extra seats is not available yet.*

A company on InfoDot (a team that is not a personal workspace) has a number of seats. The first 3 are free. The owner holds one seat, every member holds one, and an invitation that has not been answered holds one until it is accepted, declined or cancelled. When a company is full, we do not remove anyone: new invitations are refused with an explanation.

**What we keep**

- For each company, the number of free and extra seats, the billing status (free, active, past due, cancelled), the plan's reference and, if payment fails, a grace date.
- An **audit trail** of seat changes for each company: who made the change, what happened (invited, joined, left, invitation cancelled, blocked, capacity changed), the **email address** the change was about (for an invitation, the person invited, even if they never joined, and for a refused invitation the address that was refused), and the time.
- Each member's **role**: owner, admin, manager or member. Roles are about the company only. Your knowledge score, badges or level never give you a role.

**Who receives it**

- Company owners and administrators can see the Seats panel for their company and its recent history.
- When seat counts are read from Dot.Billing, InfoDot sends only the company's identifier; no personal information is sent. When extra seats can be bought, the purchase is made in Dot.Billing with Paystack, by the company.
- Your role, email address and company membership are shared with the connected Dot platforms, as set out in the live policy for sign-in and in the existing membership updates. [OWNER/ADVISER: the live policy section 4 describes the sign-on token but not this sharing of membership and role; see Part C.]

**How long we keep it.** [PLACEHOLDER.] Nothing currently removes seat records or the audit trail.

> **For the owner and adviser (not for publication) , R6**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: contract (providing a company plan); legitimate interest (audit trail, preventing over-allocation).
> - **Recipients:** Dot.Billing (company identifier only, when connected); connected platforms (name, email, role, company; existing flows).
> - **Cross-border (s72):** none added by this feature beyond those of Dot.Billing.
> - **Retention:** none defined for `team_licences`, `team_licence_events` (C-02). The audit trail records email addresses of people who were invited and **blocked** (never joined).
> - **Checked in code:** `database/migrations/2026_10_29_100001_create_team_licences_tables.php`; `app/Services/Licensing/SeatLedger.php` (`guard`, `record`: `subject` = email cut to 120 characters, `history` returns 8 rows by default); `app/Services/Licensing/BillingEntitlements.php` (the request carries only the company uuid; a failed read leaves the stored licence unchanged); `config/licensing.php` (3 included seats, pending invitations reserve a seat, no price set); `app/Support/TeamRoles.php` (roles; "Roles are organisation permissions only"); `app/Support/MembershipWebhook.php` and `EcosystemRedeemController.php` (email and role are sent to connected platforms).

## R7. Your knowledge score ("Your progress")

*Switch: `REPUTATION_ENABLED` (default false). When it is turned on, the score is worked out for **every** member unless that member has switched it off; it is on by default, not asked for first. See the box.*

InfoDot can work out a private "knowledge score" from what you do on InfoDot. It is a progress page for you. Nobody else can see it, there is no ranking or leaderboard, and a level never gives anyone a role or permission in a company.

**What we work out and keep**

- Counts of your public activity: public questions asked, topics explored, seminars you attended, answers you gave, solutions you wrote, answers that other people accepted, solutions a moderator verified, work added to the verified library, weeks in the last 12 when you were active, topics where you are an expert, and the number of your answers and solutions a moderator hid. It also uses your average rating as a seminar host once you have at least 3 ratings.
- From those, a score in six areas (Learning, Contribution, Impact, Consistency, Expertise, Trust), a total, and a level name (Spark, Venture, Growth or Luminary), together with the version of the rules and the counts behind each number.
- Each time your result changes we keep a new **snapshot**. The history is kept so a moderator can explain how a number was reached.
- If you **appeal**, the message you write (up to 1,000 characters) and the moderator's reply.

**How it is worked out.** By fixed rules, in code, from visible work. Only public, published work that was not hidden counts, never help you gave yourself. Repeated acceptances by the same person count less each time, and less again if you also accepted their answers. There is no human judgment in the arithmetic, but moderators decide what is hidden or verified, and decide ring flags (below), and those decisions feed in. We recalculate every night.

**Account rings.** Once a day InfoDot checks the last 180 days of accepted answers between members to find groups of 3 or 4 accounts that accept each other in a circle, and tight groups of 3 to 8 accounts that mostly accept each other. This protects members from people trying to push their own scores up. A match creates a *flag*. A flag is a question for a moderator, not a finding: it bans nobody and hides nothing, and the people in it are not told. Moderators see the **names** of the accounts in a flagged group, the number of acceptances and the share of acceptances that came from inside the group, never the content of the answers. While a flag is open or confirmed, acceptances between its members count for a quarter as much in the members' scores; a flag the moderator dismisses has no effect. Moderators are never part of a flag. The check does not look at moderators or at members who have switched their own score off (changed in #156, was A-09): their accepted answers are left out, so acceptances between them and others are not discounted.

**Who sees it.** Only you see your score. Moderators see your score breakdown only if you appeal. Moderators see flagged groups, with names. Nothing about the score is shown to other members.

**How long we keep it.** Snapshots and appeals are kept until you switch your score off, delete your account, or [PLACEHOLDER: retention for people who do not]. Ring flags that name you are deleted when you switch your score off or delete your account; flags are otherwise kept [PLACEHOLDER] until a moderator's decision and nothing else removes them.

**Your choices**

- **Switch your score off** at any time on the "Your progress" page. Doing so **deletes your stored snapshots and appeals and any ring flag that names you**, takes you out of the daily ring check, and nothing more is worked out about you until you switch it back on; switching back on starts afresh.
- **Download** everything stored about your score (a file with your snapshots, appeals, and the ring flags that name you with their kind, size of group, status, dates and any moderator note, but not the other members' names) from the same page.
- **Appeal** a result from the same page; a moderator checks the evidence, recalculates and replies on the page.
- To ask about ring flags or any other information, write to the Information Officer (Part D).

> **For the owner and adviser (not for publication) , R7**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: consent is unlikely to fit a score that is on for everyone once enabled; legitimate interest (s11(1)(f)) with the right to object (s11(3)) is the usual candidate, and the opt-out switch is the practical objection route. Alternatively make it opt-in, which would be a product change. Because ring detection touches opted-out people (below) the adviser should look at that part separately.
> - **Automated decisions (s71):** the score is rule-based and has no legal effect, grants no permission and is private, but ring discounts lower a score automatically until a moderator looks. [ADVISER: is s71 engaged, and is the appeal path enough?]
> - **Contradiction removed (fixed in #156, was D-03, E-07 and A-09 option 2):** the in-app pages say "Switching off deletes what is stored" and "Nothing is being worked out or stored about it". The code now agrees: `ReputationEngine::forget` deletes snapshots, appeals and every ring flag whose `member_ids` contain the person; `RingDetector` builds its moderator list as moderators plus opted-out people and excludes them from the graph; `export` lists `ring_flags`. **Consequence for the adviser:** a ring made of an opted-out person and others is invisible to the check, which weakens protection against gaming for the sake of honouring the opt-out. A flag that names several people is deleted when any one of them opts out or deletes their account.
> - **Account deletion (fixed in #156):** `SeminarDataEraser::erase` calls `ReputationEngine::forget` and deletes `user_topic_badges`; `user_notices` cascade through the foreign key.
> - **Recipients:** moderators (names in flags; the breakdown on an appeal). No third party.
> - **Cross-border (s72):** none.
> - **Retention:** none defined; opt-out is the only deletion route.
> - **Checked in code:** `app/Services/Reputation/ReputationEngine.php` (`compute`, `snapshot` returns null for an opted-out person, `forget` deletes appeals and snapshots, `export`, `evidence`); `app/Services/Reputation/RingDetector.php`; `app/Http/Controllers/ReputationController.php` (`preference`, `export`, `appeal`, message max 1000), `ReputationRingsController.php` (moderators; names via `User::whereIn`); `app/Console/Commands/RecalculateReputation.php` (skips `reputation_opt_out`), `app/Console/Kernel.php` (rings 03:10, recalculation 03:40); `config/reputation.php` (rules, tiers, `rings`); views `reputation/show.blade.php`, `off.blade.php`, `appeals.blade.php`, `rings.blade.php`. `ReputationController::resolve` recalculates the person's snapshot; the person sees the moderator's note on their page.

## R8. Specialist badges

*Switch: none specific; badges are computed when achievements refresh (`badges:backfill` fills existing members). Confirm with the owner whether they are live yet before publishing. Unlike the knowledge score, badges are **shown by default**.*

A specialist badge says that, in one topic, other people accepted your answers or a moderator verified your solutions. There are three levels (Contributor, Specialist, Authority), and no overall badge, score or ranking. A badge never gives you extra say, a permission or a different position for your answers.

**What we keep:** for each badge, your account, the topic, the level, the number of qualifying items and the version of the rules.

**Who sees it.** Badges are shown **by default** to other signed-in members: on your profile (all your badges) and beside your name on your answers in the topic of the question (the highest level you hold in that topic). A badge is never shown on a private or company-only question. Badges are not shown to people who are not signed in; InfoDot's profile and question pages are for signed-in members.

**Your choice.** On your profile there is a switch, "Show my specialist badges to other people". When you switch it off, nobody else sees any of your badges, on your profile or beside your answers. You always see your own. Switching it off **hides** your badges; it does not delete the stored badge rows, which are removed when the work behind them no longer qualifies, or when your account is deleted.

> **For the owner and adviser (not for publication) , R8**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: legitimate interest with an opt-out (s11(1)(f), s11(3)) is the owner's chosen design ("public, opt-out"); consent would require the opposite default. Adviser to confirm that default-visible is acceptable and what notice existing members need. `users.badges_visible` defaults to **true** for every existing member when the migration ran.
> - **Recipients:** other signed-in members. No third party.
> - **Cross-border (s72):** none.
> - **Retention:** the badge row is deleted when its work stops qualifying (`TopicBadges::sync`) and when the user is deleted (foreign key `cascadeOnDelete`). Hiding keeps the rows.
> - **Checked in code:** `database/migrations/2026_10_29_110001_create_user_topic_badges_table.php`; `app/Services/Knowledge/TopicBadges.php` (`forQuestion` joins `users.badges_visible = true` and refuses private/hidden questions; `forProfile` returns nothing for others when hidden; `sync` deletes when unearned); `app/Http/Controllers/AchievementsController.php::updateBadges`; `resources/views/profile/_badges.blade.php`; `config/achievements.php` `badges.levels` (2, 5, 12); `routes/web.php` (profile and question routes inside the sign-in group).

## R9. Dot Points for hosting a seminar

*Switch: `SEMINARS_POINTS_ENABLED` (default false); needs Dot.Dopemine to be deployed with the matching rule first.*

A host can earn Dot Points once for a seminar, after it has finished well attended and well rated by people outside the host's own companies. Attending earns nothing.

**How it is decided.** At least 24 hours after the seminar ended, InfoDot counts the attendees and ratings. People who are members or owners of any company that the host owns or belongs to are left out, and so are their ratings, so colleagues cannot make a seminar qualify. If at least 3 people from outside attended, at least 3 of them rated it, and their average is 4.0 or more, an event is queued. If a seminar is cancelled after points were sent, a reversal is sent.

**What leaves InfoDot.** Only: a unique event identifier, the kind of event ("seminar hosted"), the time, the **host's ecosystem account identifier**, a reference to the seminar (`seminar:` and its number), a flag that the contribution is public, and three numbers: the number of attendees counted, the number of ratings counted and the average rating. **No attendee's name, no individual rating and no note is sent.** The points service (Dot.Dopemine) applies the same thresholds again and decides what the event is worth.

**Who sees it.** The host sees a plain sentence on their seminar page about whether it qualified, with counts only. [OWNER/ADVISER: how Dot.Dopemine displays the event to the host is outside this repository.]

**How long we keep it.** The event is queued in a delivery table and sent with retries. Sent events are deleted 30 days after they were sent (a nightly job). [PLACEHOLDER: Dot.Dopemine's own retention.]

> **For the owner and adviser (not for publication) , R9**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: legitimate interest / contract (the points scheme the host takes part in), or consent if the owner prefers. The code has no switch for a host to opt out of hosting points, only the global `SEMINARS_POINTS_ENABLED`. Choose.
> - **Recipients:** Dot.Dopemine. The live policy does not mention Dot Points at all (Part C).
> - **Cross-border (s72):** none added, if Dopemine is hosted with the other platforms.
> - **Retention (was E-08, now built):** `SeminarRecordPruner` deletes `reward_outbox` rows 30 days after `sent_at` (`SEMINARS_POINTS_OUTBOX_RETENTION_DAYS`), the same as `participation_outbox`. This table is shared with other point events, so the prune applies to all sent rows in it.
> - **Checked in code:** `app/Services/Seminars/SeminarHostPoints.php` (`figures`, `colleagueIds`, `meetsThresholds`, `award`, `envelope`, `reverseIfAwarded`); `config/seminars.php` `points`; `app/Services/Points/RewardOutbox.php`; migration `2026_10_29_110003_add_host_points_to_seminars.php`. The envelope keys are exactly `event_id`, `type`, `occurred_at`, `user_uuid`, `contribution_ref`, `visibility`, `facts{attendee_count, rating_count, average_rating}`. The task description says "counts only and the host's id"; the average rating is also sent.

## R10. Notifications

InfoDot tells you, inside InfoDot, when something happens to your work or to a seminar you are part of: an answer to your question, an acceptance, a verified solution, a comment, and for seminars a reminder (a day and an hour before), a change of time, a cancellation, a place opening for you, a refund, a dispute on a ticket you sold, or a report that your meeting link did not open. A notification holds no words and no other person's name; the page writes the sentence when you open it, from what you are allowed to see.

**Keeping it short.** Read notifications are deleted after 90 days and all notifications after 365 days.

**Your choice.** On the Notifications page you can switch off "Tell me here when something happens to my work". While it is off, ordinary notifications (an answer, an acceptance, a comment, a seminar reminder) are not recorded for you. **Notices about a seminar's service are always shown, whatever the switch says:** a seminar cancelled, a seminar's time changed, a place opening for you, a refund, a dispute on a ticket you sold, a meeting link reported broken, a seminar removed by a moderator, and notices about money you owe. The page says so next to the switch. (Fixed in #156, was E-04.)

> **For the owner and adviser (not for publication) , R10**
>
> - **Lawful basis candidates** [OWNER/ADVISER]: contract / legitimate interest; the person already has a switch.
> - **Recipients:** none outside InfoDot.
> - **Retention (verified):** `notices:prune` runs daily at 03:20; read notices after 90 days, any after 365 (`InAppNotices::prune`, `PruneNotices`, `Kernel`).
> - **Checked in code:** `database/migrations/2026_10_28_100001_create_user_notices_table.php`; `app/Models/UserNotice.php` (kinds); `app/Services/Knowledge/InAppNotices.php::record` returns false when the recipient is missing, or has switched notices off and the kind is not in `UserNotice::SERVICE_KINDS`, and never notifies the actor; `resources/views/notices/index.blade.php`.

## R11. Where your information is processed, and by whom

See the table in Part B. Where a third party receives information about you, we give the reason and when it happens. We remain responsible for what we choose to send to them.

> **For the owner and adviser (not for publication) , R11 (POPIA s72 and s21)**
>
> The new cross-border and operator points are collected in Part B and in checklist rows B-06 to B-09. The live policy has no section on operators or transfers at all.

---

# Part B. Operators and third parties (proposed table, plus internal notes)

**Proposed for publication** (the adviser will tell the owner which must be named and in what form):

| Who | What they receive | Why | When |
|---|---|---|---|
| **Dot.Billing** (the Dot ecosystem's payment system, [PLACEHOLDER: operator and hosting location]) | For a ticket: your email address, the amount, the currency, the seminar title and the ticket reference. For a host: the name on the payout account, bank, account number (passed on to Paystack; Billing keeps the last four digits and Paystack's code for the account), and the record of the host agreement. For a company: the company identifier. | To take and settle payments, refund, record disputes and debts, and tell InfoDot the entitlements of a company | Paid seminars; company seats |
| **Paystack** ([PLACEHOLDER: legal entity and location]) | Your card or bank details (entered on its page, never seen by InfoDot), your email address, the amount and the payment reference; for a host, the payout account details | To process the payment and payout, refunds and bank disputes | Paid seminars |
| **Dot.Dopemine** (the Dot Points service) | For a seminar a host ran: the host's ecosystem account identifier, a reference to the seminar and three numbers (attendees counted, ratings counted, average rating). No attendee names or individual ratings. | To award Dot Points for hosting | Only if Dot Points for hosting is switched on |
| **STUN server** (default: **Google**, `stun.l.google.com`; [PLACEHOLDER: replace if InfoDot runs its own]) | Your browser's internet address, when you are in a small room | So browsers can find a direct path to each other. No video or chat passes through it. | Small rooms |
| **TURN relay** ([PLACEHOLDER: if configured]) | Video and audio of a small room for people who cannot connect directly, and their addresses | Fallback path for small rooms | Only if InfoDot configures one |
| **LiveKit video server host** ([PLACEHOLDER: operator, provider, region]) | Your name (first 60 characters), account-derived identifier, role, internet address, connection details, the host's audio, video and screen, and the room's chat in transit | To run the InfoDot room | InfoDot room |
| **The host's own meeting service** (chosen by the host: for example Google Meet, Zoom, Microsoft Teams, Whereby, Jitsi) | Whatever you give it when you join, under its own privacy policy | To run a seminar the host delivers on their own service | Link seminars |
| **The web address in a host's meeting link** | A request from InfoDot's server (not from you) to check the link opens | To tell the host if the link is broken | When a host saves or tests a link |
| **InfoDot's web host and database host** ([PLACEHOLDER]) | Everything InfoDot stores | To run InfoDot | Always (existing) |

**Internal notes (not for publication)**

| Row | Open point |
|---|---|
| Dot.Billing | Same group or a separate company from BluePin Inc? An operator agreement (s21) is required if separate. [OWNER/ADVISER] |
| Paystack | Entity, processing locations, operator or responsible party for card and payout data, s72 ground. Not stated in the repository. [ADVISER] |
| Dot.Dopemine | Same as Billing. |
| Google STUN | US-based processing is likely but not stated in the repository [ADVISER]. Removable by `P2P_STUN_URLS`. |
| LiveKit host | Region and provider are the owner's choice; South Africa avoids a transfer for the media (`R13_LIVEKIT_SERVER.md`). The server has never been run for real. |
| Host's meeting service | Chosen by the host, not InfoDot; see terms. |
| Sentry, Stripe, Pusher, Google Fonts | Already in the inventory (`POPIA_INVENTORY.md`, `POPIA_READINESS.md`); not new to R-13 and not repeated here. |

---

# Part C. Edits the existing policy sections need

These are changes to the live text that the new features require. They are listed, not drafted, because several depend on owner decisions.

| Live section | What the new features change |
|---|---|
| Header "Last updated: 6 August 2026" | Update the date when any of this goes live. |
| 2. What we collect | Add: seminar records (R1), ticket and payout records (R2), room records and chat (R3), seat and audit records (R6), score snapshots and appeals (R7), badges (R8), notifications (R10). The live text lists "Account, Team, Content, Associates, Files, Technical" only. |
| 3. Why we process | Add the purposes in R1 to R10. |
| 4. Ecosystem single sign-on | Add that your name, email, company and **role** are shared with connected platforms (existing behaviour in `EcosystemRedeemController` and `MembershipWebhook`, not described in the live policy). |
| 6. How long we keep your information | Currently only covers account and public content. Needs a retention schedule for every new data class: seminars, registrations, ratings, tickets, refunds, disputes, invoices, seller records, room records and chat, seat audit, score snapshots, appeals, ring flags, badge rows, outbox rows. Where the code defines a period: notifications (read 90 days, any 365); room data 30 days after the seminar; sent points rows 30 days; closed reports and decided appeals 12 months; money records of deleted accounts 5 years (to be confirmed). Everything else has none (checklist C-02). The account-deletion behaviour is now written in R1 ("Deleting your account") and has to be repeated or referred to here. |
| 7. How we protect your information | Do not add encryption claims. The new code stores the payout account number nowhere, signs server-to-server calls, uses short-lived room passes and does not log request bodies for payout calls. These are the only security statements the code supports; the adviser may want them stated carefully. |
| 8. Your rights | Add where each right is exercised (Part D). Add that the access right for seminar and payment data is by request to the Information Officer: only the knowledge score has a self-service download. |
| 9. Children | Seminars and payments need a sentence on minors: the seminar, ticket and payout code contains no age check (only a verified email is required). [ADVISER] |
| New section | Operators and cross-border transfers (Part B). |
| Live section 2, "Content you post ... visible to visitors browsing that content" | In the code, questions, solutions, profiles and seminars are all behind sign-in (`routes/web.php`), so "public" means every signed-in verified member. Check the live wording against the actual exposure (checklist D-07). |

**Not covered by the live policy at all, outside R-13 but noticed while reading** (listed so the adviser sees one picture): Dot Points balances, redemptions, expiry-warning and digest emails (`DeliverPointsExpiryWarnings`, `DeliverPointsDigests` send email); the opt-in sharing of topic counts to Dot.Memory (`config/participation.php`); the verified library; achievements and the "share achievements" switch; the UX friction counts (`config/ux.php`, off by default, aggregate only, 180 days).

---

# Part D. How a person uses their rights (proposed table)

| What the person wants | Where | What happens (verified) |
|---|---|---|
| See their knowledge score data | "Your progress", **Download my score data** | A JSON file with their email, snapshots, appeals and the ring flags that name them (kind, group size, status, dates, moderator note). |
| Stop the knowledge score | "Your progress", **Switch my score off** | Deletes their snapshots, appeals and ring flags that name them, stops recalculation and takes them out of the ring check. |
| Hide specialist badges | Own profile, "Show my specialist badges to other people" | Hides from everyone else; rows remain. |
| Stop in-app notifications | Notifications page, "Tell me here when something happens to my work" | Ordinary notifications and reminders stop. Cancellations, time changes, refunds, disputes, removals and money owed are still recorded. |
| Report a seminar | The seminar page, "Report this seminar" | A moderator reads it; the host is not told who reported. |
| Report a person in a seminar | The seminar page, "Report someone in this seminar" | A moderator reads it and can bar the person from the room; the person is not told who reported. |
| Appeal a seminar's removal (hosts) | The removed seminar's page | One appeal within 30 days; a different moderator decides; the answer appears on the page. |
| See everything held about your seminars | Seminars page, "Download my seminar data" | A JSON file: hosted seminars (with ratings received, without who gave them), registrations and tickets, ratings given, chat, reports and appeals, payout, seller, agreement, debt and invoice records. Never a bank account number. Dot.Billing's and Paystack's own copies are not in it. |
| Cancel a seminar registration | The seminar page | Registration cancelled; paid ticket refunded if 24 hours or more before the start. |
| Remove or correct anything else, or see seat audit records | Email the Information Officer at `privacy@infodot.co.za` | There is a self-service download for seminar data (above) and for the score; none for seat audit records. There is no cross-system subject-request process yet (`POPIA_READINESS.md`, condition 8). [OWNER/ADVISER: response time (30 days is the usual target); who answers the mailbox.] |
| Delete their account | Account settings (existing) | Cancels their unfinished hosted seminars (everyone who paid is refunded in full) and their upcoming registrations (usual refund rule), then deletes their seminars with no sales, free registrations, rating notes, reports, room records, chat, score data, ring flags, badges, notices and the user row. Money records stay with no name on them (R1, "Deleting your account"). Dot.Billing's and Paystack's own copies are not deleted by this. |
| Object to processing, withdraw consent, complain | As in the live policy section 8 | Contact the Information Officer, then the Information Regulator. |
