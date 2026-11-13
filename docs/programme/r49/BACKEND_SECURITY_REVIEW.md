# R-49 backend, workflow and security review (second pass)

Date: 2026-11-13. Branch `r49/backend-security-pass`, cut from `main` at `dd18954`.

This pass covers the nine areas that the earlier reviews of the same day did not read in depth. What those reviews
already fixed (access control on business, inbox, collection, notice and attachment routes; injection, XSS and SSRF
sweeps; webhook signatures; CSRF and CORS; sessions; account suspension; sign-in limits) was not redone. Earlier
registers: `docs/programme/enterprise/FINDINGS.md`, `docs/programme/R18_SECURITY_REMEDIATION.md`.

## How to read this

- **Severity.** P0 = data of one person or company reaches another, or money is lost, with no precondition. P1 = money or
  data wrong in an ordinary sequence of actions. P2 = a failure a person will meet (error page, stuck state, stale
  disclosure) or a lasting integrity gap. P3 = narrow, cosmetic to the user, or defence in depth.
- **Evidence.** "Test" means a PHPUnit test that failed before the fix and passes after it. "Read" means the finding
  was established by reading the code and was not run.
- Scenarios say what goes wrong for whom. They are not steps.

No P0 was found. Fourteen fixes were made (B-01 to B-14), each in its own commit with a guarding test in
`tests/Feature/Stabilisation/`. No migration was added. Suite: 1,612 passed, 4 skipped (baseline 1,566 passed, 4
skipped). Pint passes; PHPStan reports no errors and the baseline is unchanged at 136.

## Findings at a glance

| Id | Sev | Area | Finding | State | Evidence |
|---|---|---|---|---|---|
| B-01 | P1 | 1 Seminars | Cancelling a seminar did not refund the rest of a part-refunded ticket | Fixed | Test |
| B-02 | P1 | 1 Seminars | A ticket bought again kept the earlier refund; a new checkout could replace a ticket whose money was still held | Fixed | Test |
| B-03 | P2 | 1 Seminars | A replaced checkout stayed payable; a payment on it matched no ticket and was not returned | Fixed | Test |
| B-04 | P3 | 1 Seminars | Link checker accepted several non-public address ranges | Fixed | Test |
| B-05 | P2 | 9 Errors | Reporting or approving a former member's question answered 500 | Fixed | Test |
| B-06 | P2 | 9 Errors | A list sent where text is expected answered 500 on public addresses | Fixed | Test |
| B-07 | P2 | 5 Accounts | A business could be left with no owner | Fixed | Test |
| B-08 | P2 | 5 Accounts | Files of a deleted account or business stayed on disk | Fixed | Test |
| B-09 | P2 | 6 Mail | Team invitation: mail failure left a half-made invitation and a 500 | Fixed | Test |
| B-10 | P2 | 6 Mail | Verification email code: mail failure left a saved code and a 500 | Fixed | Test |
| B-11 | P2 | 6 Mail | Password reset link and "resend verification": mail failure answered 500 | Fixed | Test |
| B-12 | P2 | 4 Visibility | Hidden content still shown on three read paths | Fixed | Test |
| B-13 | P3 | 7 Scheduler | One person's error stalled the nightly reputation pass for everyone after them | Fixed | Test |
| B-14 | P3 | 2 Points | An acceptance could be reversed twice; a late request could reset a question | Fixed | Test |
| L-01 | P2 | 5 Accounts | Deleting an owner's account deletes their businesses for every member, without warning | Left: owner decision | Test (probe) |
| L-02 | P2 | 4 Visibility | Any business can be named in a mention by its id, including private and restricted ones | Left: owner decision | Read |
| L-03 | P2 | 1 Seminars | One row per person per seminar: a second purchase overwrites the first sale's record | Fixed later (`seminar_ticket_sales`, one row per paid checkout) | `SeminarTicketSalesTest` |
| L-04 | P3 | 1 Seminars | Refund callback: stated amount is not compared with the computed amount | Left: contract | Read |
| L-05 | P3 | 1 Seminars | Raising capacity lets a newcomer pass the waiting list | Left: owner decision | Read |
| L-06 | P3 | 1 Seminars | Host can edit attendance after the end, which changes whose rating counts | Left: owner decision | Read |
| L-07 | P3 | 1 Seminars | Host can move or cancel a seminar that has already ended | Left: owner decision | Read |
| L-08 | P3 | 1 Seminars | Link checker reads the whole response body | Left: small, untested | Read |
| L-09 | P3 | 5 Accounts | Team invitations never expire; the link adds the invited address whoever opens it | Left: owner decision | Read |
| L-10 | P3 | 5 Accounts | Two-factor is switched on before the person has proved the authenticator works | Left: needs the view | Read |
| L-11 | P3 | 8 Database | `teams.user_id`, `questions.user_id`, `comments`, `likes`, `attachments.user_id` have no foreign key | Left: schema change | Read |
| L-12 | P3 | 8 Database | `associates` has no uniqueness on (person, associate) | Left: soft deletes | Read |
| L-13 | P2 (conditional) | 7 Scheduler | With `CACHE_DRIVER=database` the scheduler's locks have no table | Left: not the configured driver | Read |
| L-14 | P3 | 1 Seminars | A repeated notice inside a transaction would abort it on PostgreSQL | Left: production is MySQL | Read |
| L-15 | P3 | 4 Visibility | Comment bodies of company-only items are written to the search index (no read path uses it) | Left | Read |
| L-16 | P3 | 3 Livewire | `QuestionList`, `SolutionList` and `Question` components have no guards and are mounted nowhere | Left: dead code | Read |
| L-17 | P3 | 9 Errors | A moderator typing a reference on the library desk gets different messages for "company-only", "hidden" and "not there" | Left | Read |

## 1. Seminars and money paths

**Inspected.** `app/Http/Controllers/Seminar*.php`, `Api/SeminarTicketEventsController.php`,
`Api/SeminarRoomWebhookController.php`, all of `app/Services/Seminars/` (`SeminarService.php` in full), the seminar
models, the seminar routes in `routes/web.php` and `routes/api.php`, the seminar migrations (indexes and uniques), and
the commands `SweepSeminarPayments`, `PruneSeminarRecords`, `SendSeminarReminders`, `AwardSeminarHosts`,
`IssueSeminarCommissionInvoices`.

**Found sound.** Edit, cancel, attendance and refund are host-only; the receipt belongs to the buyer; invoices are scoped
to the host; the export is the caller's own data; moderation routes carry the moderator middleware and the service
checks again; room tokens re-check on every call; a draft answers 404 to others. Price, amount, user and seminar ids
come from the server; amounts are integer cents. The ticket callback fails closed with no secret, has a 300-second
window, a unique key per (event, ticket, key), checks amount and currency, treats paid-after-refunded as nothing, and
refunds a payment that arrives after cancellation. Capacity is counted under the seminar row lock and holds count.
There is no payout-request action in InfoDot: Dot.Finance settles. The meeting link is shown only to the host and to
registered people inside the join window. The link checker accepts https only, refuses IP literals, credentials and
other ports, checks every resolved address on every hop, and pins the connection to the address it checked.

**B-01 (P1, fixed).** `SeminarService::cancel()`, `retryRefunds()`, `assertPriceChangeAllowed()`. A ticket the host had
refunded in part still holds the rest of the buyer's money. Cancelling the seminar asked for refunds of fully paid
tickets only, so the rest stayed with the host while the notice said refunded in full; a refund of such a ticket that
did not reach Dot.Finance was never retried. Root cause: three queries listed `paid` and omitted
`partially_refunded`. Guard: `SeminarPartRefundedTicketTest`.

**B-02 (P1, fixed).** `SeminarService::startCheckout()`. One row holds a person's ticket for a seminar. Buying again
after a full refund reused the row with the earlier refund still on it, so the new ticket showed nothing left to
refund. A new checkout could also replace the reference of a ticket whose refund was still on its way, or of a paid
ticket given up after the deadline. Root cause: the reused row reset some money columns and not others, and nothing
refused a checkout over held money. Fix: a new ticket starts with nothing refunded or disputed; checkout is refused
while money from the earlier ticket is held or on its way back. Guard: `SeminarTicketBoughtAgainTest`.
See L-03 for the part that is not fixed.

**B-03 (P2, fixed).** `SeminarService::startCheckout()`, `applyTicketEvent()`. A buyer whose held place ran out of time
could start again before the sweep ran. The new reference replaced the old, Dot.Finance was not told to close the old
payment page, and a payment made there matched no ticket: money taken, no place, no refund. Fix: the replaced page is
cancelled, and a payment for a reference InfoDot issued but no longer holds is refunded, with an error line for a
person if Dot.Finance cannot be reached. Guard: `SeminarReplacedCheckoutTest`. Whether Dot.Finance expires an unpaid
ticket by itself was not verified; the fix does not depend on it.

**B-04 (P3, fixed).** `MeetingLinkCheck::anyPrivate()`. The address test used PHP's "private" and "reserved" ranges,
which leave out shared carrier space, benchmarking and protocol-assignment blocks, multicast, and the IPv6 prefixes
that carry an IPv4 address. Confirmed by running the filter. Guard: `MeetingLinkAddressRangesTest`.

**Left.**
- **L-03 (P2).** With one row per person per seminar, a second purchase after a full refund overwrites the first
  sale's reference and payment date. The commission invoice run reads those, so the first sale can drop out of a month
  while its refund line stays. The fix is a ticket table with one row per purchase: a schema and reporting change, too
  large for this pass.
- **L-04 (P3).** `applyRefund()` reads the callback's refund amount and never compares it with the amount it
  computes; a callback without a running total is treated as a full refund. The right rule depends on what Dot.Finance
  promises to send.
- **L-05 to L-07 (P3).** Waiting-list order after a capacity change; attendance edits after the end (they decide whose
  rating counts toward host points); moving or cancelling a seminar that has ended. Each is a rule the owner should set.
- **L-08 (P3).** The link checker downloads the whole response within its 4-second limit. A size cap is small but
  could not be tested against the HTTP fake, so it was not shipped untested.
- **L-14 (P3).** `InAppNotices::record()` catches a duplicate-key error. Inside a transaction on PostgreSQL that
  aborts the transaction. Production is MySQL, where it does not.

**Not finished.** Seminar Blade views were searched, not read in full. `P2pCapacity.php` and the smaller models
(`SeminarReport`, `SeminarRemovalAppeal`) were not read. `HostDebtLedger` takes Dot.Finance's figures as given.

## 2. Points and reputation

**Inspected.** `PointsController`, `ReputationController`, `ReputationRingsController`, `AchievementsController`,
`app/Services/Points/`, `app/Services/Reputation/`, `app/Services/Participation/`, the forwarding listeners, the
outbox migrations.

**Found sound.** The points pages always use the signed-in person's id. A redemption sends only a server-issued token;
the reward and its cost come from the session ticket, and the token is the idempotency key. No amount is read from the
request. Both outboxes have a unique event id and are written with insert-or-ignore; flushing claims rows for five
minutes, parks after 25 attempts, and stops after three unreachable calls. Self-awards are refused (own answer, own
solution, own report). Digest and expiry mails are logged in the same transaction as the send, so a retry does not
mail twice. The ring and reputation desks are moderator-only.

**B-14 (P3, fixed).** `AnswerService::revokeAcceptance()`. Two requests that both still saw an answer as accepted each
told the ledger to reverse the award, and a late one reset the question after another answer had been accepted. Root
cause: unlike `accept()`, no lock and no second look. Guard: `AcceptanceRevokedOnceTest`. Whether the ledger would
have deducted twice is outside this repository.

**Left.** Accepting, removing and accepting again sends a new award event each time with a new id. Whether that can be
used to earn more than once is decided by the ledger's own rules, which are not in this repository.

## 3. Livewire components

**Inspected.** Every class under `app/Livewire/` and the Jetstream `TeamMemberManager` it extends.

**Found sound.** `Search` (guests included), `VerifySolution`, `ReportContent`, `HiddenNotice` (ids locked),
`SolutionImprovements` and `SolutionConfirmations` (the id is re-authorised on every call), the three moderation
components (`boot()` re-checks the moderator on every request), `Answers` actions, `Associates`, `TeamMemberManager`
(invite, add, remove and role change go through the policy; cancelling an invitation is scoped to the team and gated),
the profile form, and the platform launch components.

**Found.** The `Answers` and `Comments` panels are part of B-12 below. **L-16:** three components with no guards are
not mounted by any page, so they cannot be reached; they should be deleted rather than left.

## 4. Knowledge visibility

**Inspected.** `HasKnowledgeVisibility`, `IsModerated`, `AnswerService::canSee*`; the question, solution, home, topic,
library, people, picker, sitemap and analytics controllers; `Reference::resolve`, `Related`, `SimilarContent`;
`InAppNotices` and `RecordInAppNotice`; `CandidateFeed` and its controller; `shouldBeSearchable` on questions and
solutions; `MentionService`; the landing page.

**Found sound.** No path was found that returns one company's private question, solution or answer to another. Lists and
pages use the global scopes and answer 404; search hits are re-read through the scope; notices write their words at
read time through the scope; the candidates feed is public-only; the sitemap holds no knowledge content; nothing is
emailed about knowledge events and there is no knowledge export.

**B-12 (P2, fixed).** `resources/views/livewire/answers.blade.php`, `Livewire/Answers.php`, `Livewire/Comments.php`,
`resources/views/welcome.blade.php`. After a moderator hides a question or solution only its author and moderators
should see it. Three paths asked only whether the item was public or the viewer's company's: the recommended-solution
card under an answer kept showing a hidden solution's title, opening lines and author; the answers and comments
panels of a page that was already open kept loading; the landing page kept listing a hidden library entry. Root
cause: `canSeeContent()` has no hidden rule (the moderation desk depends on that) and these readers used it as is.
Fix: `AnswerService::canRead()` adds the rule for readers. Guard: `HiddenContentStaysHiddenTest`.

**Left.**
- **L-02 (P2).** `MentionService::record()` accepts any business id. The autocomplete deliberately offers only
  businesses with public content or the writer's own, and a private or restricted business page answers 404, but a
  mention made by id prints that business's name on the writer's own item and notifies its owner. Not fixed because an
  existing acceptance test (`MentionsAndRecommendationsTest`) asserts that a stranger can mention a business that has
  no public content, so the rule is the owner's to state. Limits today: five per item, twenty per hour.
- **L-15, L-17 (P3).** As in the table.

**Not finished.** Attachment view rules were not re-read (reviewed earlier). The participation outbox payload and the
reputation export were skimmed.

## 5. Account lifecycle

**Inspected.** `CreateNewUser`, `DeleteUser`, `DeleteTeam`, `CreateTeam`, `InviteTeamMember`, `AddTeamMember`,
`RemoveTeamMember`, `TeamPolicy`, `SeminarDataEraser`, Jetstream's `DeleteUserForm`, `TeamInvitationController` and
`Team::purge()`, every user foreign key in the migrations, Fortify's feature list.

**What deletion does today** (measured with a probe test, not assumed): the person's teams are removed, including any
business they own; their solutions, steps and answers go by database cascade; their questions stay and show "Former
member"; comments and likes stay; seminar data follows `SeminarDataEraser`; Sanctum tokens are deleted and the other
platforms are told to end their sessions.

**B-07 (P2, fixed).** `CreateTeam::create()`, `DeleteUser::delete()`. Production holds one business whose owner's
account is gone. No code deletes a person without their teams, and the schema has no key from a team to its owner. The
path found: creating a business wrote the team, then the profile, then the history row with no transaction, and
deletion removed the teams it had read earlier. A request that outlived the account wrote the team and then failed on
the history row, leaving the team. Fix: creation is one transaction that takes the person's row and refuses when the
account is gone; deletion takes the same row and reads the teams afresh. Existing rows are not touched. Guard:
`OwnerlessTeamTest`. This is the only path found by reading; a row removed by hand in the database would look the same.

**B-08 (P2, fixed).** `AttachmentService::pruneOrphans()`, `attachments:prune`. Cascades run no model code, so files
uploaded to a deleted person's solutions, steps and answers, and everything under a deleted business (logo, cover,
gallery, verification evidence, enquiry files), stayed on disk with rows pointing at nothing. The daily prune now
removes an attachment whose owner row no longer exists; a soft-deleted owner is kept and a row touched in the last day
is left alone. Guard: `OrphanedAttachmentFilesTest`. On its first run in production this will remove files already
orphaned; the count is logged as `attachments.orphans_pruned`.

**Found sound.** Password reset tokens are single-use (framework broker); two-factor changes require the password;
cancelling an invitation is authorised and scoped; a personal workspace takes no members.

**Left.**
- **L-01 (P2).** Deleting the account of a business owner deletes the business for every member, with no warning and
  no transfer. Members lose their current team; company-only questions filed under the business remain, visible to
  their authors only. Whether to block deletion, require a transfer or warn is a product decision. There is no
  ownership transfer today.
- **L-09 (P3).** An invitation link has no expiry and adds the invited address to the team whoever opens it while
  signed in.
- **L-10 (P3).** Two-factor has no confirmation step, so it is active before the person has shown the authenticator
  works. Turning the step on needs the profile view checked with it.

## 6. Notifications and email

**Inspected.** Every `Mail::` and `notify` call in `app/` outside console commands (ten sites), Fortify's password reset
and verification controllers, the exception handler.

**Found sound.** Contact form, enquiry activity, email-change notice, verification after sign-up and after an email
change already catch a transport failure. In-app notices are database rows.

**B-09 (P2, fixed).** `InviteTeamMember::invite()`. The invitation was saved, then mailed. A refused send answered 500
with the invitation kept: it held a seat, nobody had received it, and inviting the address again was refused. Now the
invitation is removed, the licence trail records it, and the form says so. Guard: `InvitationMailFailureTest`.

**B-10 (P2, fixed).** `VerificationService::sendEmailCode()`. The same shape: code saved, send refused, 500, and a
one-minute refusal to try again. Now the code is cleared and the form says so. Guard:
`VerificationCodeMailFailureTest`.

**B-11 (P2, fixed).** `User::sendPasswordResetNotification()`, `Auth\ResendVerificationEmailController`. Asking for a
reset link or for the verification email again answered 500 when mail was down. Both return to their form with a
message; an undelivered reset token is not kept. Guard: `AccountMailFailureTest`.

## 7. Scheduler and commands

**Inspected.** `app/Console/Kernel.php`, `routes/console.php`, the scheduled commands and the services they call.

**Found sound.** Every entry has `withoutOverlapping(30)` and `onOneServer()`; a 30-minute lock expiry cannot leave a
command skipped for good. Commission invoices are unique per host and period; the host-points award is a conditional
claim with a fixed event id; reminders are one per person, seminar and kind; the payment sweep swallows per-record
failures; outbox flushes are bounded and retried.

**B-13 (P3, fixed).** `RecalculateReputation`. The pass saved its position at the end of a run, so an error for one
person ended the run early and every later run stopped at the same person. A failure is now logged with the person's
id and skipped. Guard: `ReputationPassResilienceTest`. No input was found that makes the calculation fail today.

**Left.** **L-13:** the locks use the default cache store. Both example environments set `file`, which works. No
migration creates the `cache` tables, so switching to the database store would stop every scheduled command. Not
verified: which store the host uses. `VerificationService::sweepExpiry` pages by offset while changing the column it
filters on; it would skip rows only beyond 1,000 a run.

**Not finished.** Commands that are not scheduled (`SsoPlatform`, `SeedKnowledge`, `Backfill*`, `Account*`,
`UxFriction`) were not read.

## 8. Database

**Inspected.** All migrations for indexes, unique keys and foreign keys, against the queries of the list pages.

**Found.** The two index migrations of 2026-10-30 cover the list and schedule queries; no query was found that clearly
lacks an index, so no migration was added. **L-11:** several legacy columns have no foreign key. `teams.user_id` is the
one behind B-07; it is a different integer width from `users.id` on MySQL, so a key needs a column change, and
production has a row that would fail it. **L-12:** two quick clicks on "connect" can create two associate rows; the
table uses soft deletes, so a plain unique key would block reconnecting.

## 9. Error handling

**Inspected.** Every `catch (\Throwable|\Exception)` in `app/`, request-input reads in the API and sign-in controllers,
`abort()` calls reachable from input.

**Found sound.** Every generic catch logs or reports, and none tells the person a failed action worked (the mail paths
in area 6 were the exceptions, now fixed).

**B-05 (P2, fixed).** `AnswerService::report()`, `ModerationService::ledgerFacts()`,
`ForwardKnowledgeEventToPoints`. A question outlives its author's account. Reporting it saved the report, used the
reporter's hourly allowance and then answered 500; approving it failed every time. Guard: `FormerMemberContentTest`.

**B-06 (P2, fixed).** Twenty reads of a request parameter cast it to a string or passed it to a typed argument. Sent as
a list, the value raised an error, so the sign-out, authorise and token addresses, the resource registry and the
question, solution and search lists answered 500 to anyone and wrote to the error log that the alert reads. Fix:
`App\Support\Input::text()`. Guard: `ArrayInputTest`.

**Not finished.** 403-versus-404 differences were surveyed, not listed route by route. One note: the business inbox and
the profile-edit routes answer 403 for a restricted business that is otherwise "not there"; the address uses a uuid.

## For the owner

1. L-01: what should happen to a business when its owner deletes their account.
2. L-02: may anyone mention any business, or only ones they can see.
3. L-03: approve a ticket table (one row per purchase) before paid seminars carry real volume.
4. L-05 to L-07: waiting-list order, attendance edits after the end, changes to an ended seminar.
5. L-09, L-10: invitation expiry; two-factor confirmation.
6. B-08: the first `attachments:prune` after deploy removes files already orphaned. Read the logged count.
7. The existing ownerless business in production is not changed by this work.
