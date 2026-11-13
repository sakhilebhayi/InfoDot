# R-49: Demonstration content and the accounts desk

InfoDot can carry a small set of clearly labelled demonstration content, so the product can be shown working on
a site that has little content yet. It is created by the application's own code, marked wherever it shows, kept
away from search engines, mail and money, and removed with one command.

Nothing here is a claim about a real business. There are no customer counts, testimonials, ratings, revenue or
activity numbers in the content, and none may be added (PRODUCT.md, "Evidence on Hand").

## 1. What `demo:install` creates

The content is data in `database/demo/content.php`, reviewed like any other change. One run creates:

| Kind | Count | Notes |
| --- | --- | --- |
| People | 3 | Naledi Demo (owner), Sipho Demo (colleague), Lerato Demo (outside the first company; owns the second). Addresses `…@demo.infodot.test`, verified, each with a drawn profile picture and a personal workspace. |
| Moderator | 0, or 1 with `--with-moderator` | Thabo Demo. Not created by default: see section 3. |
| Companies | 2 | Ledger Works (Demonstration): bookkeeping, Johannesburg. Fieldline IT (Demonstration): IT support, Durban. Visibility "Anyone". |
| Business profile, per company | 1 | Description, type, industry, two directory categories, 4 and 3 services/products with "Demonstration price" notes, 1 portfolio project, 1 location, 1 announcement, website on `example.com`, email on `*.infodot.test`, phone `+27 00 000 01xx`. |
| Logo and cover, per company | 2 | Drawn with GD (`DemoArtwork`): the owner code stencilled in gold on graphite; the cover carries the word DEMONSTRATION. Stored through `TeamMediaService`, so they are ordinary attachments. |
| Questions | 6 | 5 public, 1 visible to Ledger Works only. |
| Answers | 7 | 4 accepted by the person who asked (through `AnswerService`). |
| Solutions | 4 (18 steps) | 3 public, 1 visible to Ledger Works only. |
| Comments | 2 | One on a question, one on a solution. |
| "Tried it" confirmation | 1 | Lerato on the month-end solution. |
| Enquiry | 1 | Lerato to Ledger Works, with Naledi's reply (through `EnquiryService`). |
| Notices | 15 | Whatever the application's rules produce from those actions; each of the three people has at least one. |

Also created because the application creates them: 3 personal workspaces, 5 root folders with their objects, 1
membership, 2 listing events, and the milestones (`user_achievements`) the seeded actions earn. Nothing is typed
in: no Dot Points, no reputation score, no verification. The content is filed under topics that already exist on
the site (for example "Money and cash flow" after `knowledge:seed`); the installer never creates a topic or a
directory category.

## 2. How demonstration content is marked

There is one mark: the table `demo_records` (model class, key). Every record created while the installer runs is
written to it by `App\Services\Demo\DemoRegistry`, whichever part of the application created it. No domain table
has a new column.

What a demonstration account writes later, while signed in (an answer, a comment, an enquiry, an upload), is
written to the register too, so it is labelled and leaves with the rest. This covers content only (questions,
answers, solutions and steps, comments, confirmations, enquiries and their messages, attachments, likes,
mentions, notices), never moderation or audit records.

### The label

`<x-demo-mark :of="$record" />` draws a small "Demonstration" chip (the existing `ix-chip ix-chip--open`; no new
CSS). It shows on:

- the business page and every directory row;
- questions and solutions, in their lists, on their pages and in search results;
- each answer and comment;
- a person's profile;
- an enquiry, in both inboxes and on the conversation;
- the moderators' accounts desk.

The company and people names also say so ("… (Demonstration)", "… Demo"), for the places a chip cannot reach
(page titles, another platform after single sign-on).

### Search engines, mail and money

- Demonstration businesses are listed in `/businesses` for people, are left out of `/sitemap.xml`, and their
  pages carry `<meta name="robots" content="noindex">` with no canonical link.
- Mail to a reserved top-level domain (`.test`, `.example`, `.invalid`, `.localhost`) is never sent
  (`SkipMailToReservedDomains`). The demonstration addresses are all on `.test`.
- A demonstration account earns no Dot Points (its events never enter the points outbox), cannot redeem points,
  set up a payout, accept the seller terms or buy a ticket. A demonstration company is never registered with
  Dot.Finance.

## 3. Install, rotate, check, remove

Run on the host from the application folder. In production each command needs `--force`, like `migrate`.

```
php artisan demo:install --force      # create the content; prints the three one-time passwords
php artisan demo:install --force --with-moderator   # the same, plus the demonstration moderator (or adds it later)
php artisan demo:install --force --rotate   # new passwords; the old ones stop working
php artisan demo:status               # what is installed, whether the moderator exists, whether real accounts added to it
php artisan demo:remove --force       # take all of it away
```

- Running `demo:install` twice changes nothing. It needs no queue worker. It does not rebuild the search index:
  each record is indexed as it is saved, the way a record written through the site is.
- The passwords are generated at run time, printed once and stored only as hashes. They are in no file and no
  log. Lost them: `--rotate`.
- The install is all or nothing: one database transaction, and the files of a failed run are deleted.
- If an account already uses one of the demonstration addresses, the install stops and changes nothing.

### The demonstration moderator is opt-in

A moderator can hide, verify and decide on real people's content, so an extra moderator account does not exist on
a real site unless someone asks for it. The default install creates none, and the walkthrough uses the owner's
own moderator account for the moderation part.

`demo:install --with-moderator` creates Thabo Demo with the rest, or adds only him to content that is already
installed (the other accounts and their passwords are untouched). `demo:status` says whether he exists.
`demo:remove` removes him with everything else. If he is created: on the accounts desk he can act on
demonstration accounts only (section 5); on the older desks nothing limits him, so use him on demonstration
content only and remove the content when the demonstration is over.

### What removal does

`demo:remove` deletes the records on the register, their files (attachments on the private disk, profile
pictures on the public disk), and the rows that cannot exist without them: memberships, category and topic
links, sessions, read markers and the like. Those are found from the database itself (`user_id`, `team_id`,
cascading foreign keys, polymorphic pairs), not by name or address. It runs in one transaction and takes the
records out of the search index first. It sends no webhook and no mail.

If a real account has added to demonstration content (answered a demonstration question, sent an enquiry to a
demonstration company, joined one), removal stops, changes nothing and says what it found. Then either leave the
content in place, or run `php artisan demo:remove --force --with-attached`, which removes those rows as well,
because an answer cannot outlive its question. The real account itself is never touched.

The test `test_remove_restores_every_table_to_its_count_and_deletes_the_files` proves the exactness: after
install and remove, every table holds the number of rows it held before, and both disks are empty again.

### Known limits

- A real person's notice about demonstration content ("someone answered your question", where the answer was a
  demonstration account's) is removed with the content. A notice that points at a demonstration question itself
  stays and reads "no longer available".
- Engines: the test suite runs on SQLite. On MySQL, the CI job "migrations on MySQL" runs
  `tools/mysql-demo-content-test.php`: the real commands in production mode, the two companies listed for a
  visitor, a second install that creates nothing, and every table's row count restored after `demo:remove`.

## 4. A 10-minute walkthrough

Steps 1, 2 and 7 were opened in a browser on a local copy during the build (2026-11-13); the pages in steps 3
to 6 are covered by the tests in `tests/Feature/Demo` and the search by a query against the local index, but were
not clicked through by hand. Rehearse the whole sequence once on the live site before the meeting. Sign in only
where it says so.

1. **Directory, signed out (1 min).** `/businesses`. Both demonstration listings, each with its chip. Search
   `bookkeeping`: Ledger Works, with what it matched on. Search `training`: both. Put `Durban` in Location:
   Fieldline IT only. Pick a category: one listing.
2. **A business page, signed out (2 min).** Open Ledger Works. Cover and logo carrying its owner code, the
   Demonstration chip, the announcement, services with price notes, the portfolio entry, the location, contact
   details, public questions and solutions from the company, and "Similar businesses" pointing at Fieldline IT.
3. **Knowledge, as the outside person (2 min).** Sign in as Lerato. `/questions`: waiting and solved questions.
   Open "How do I chase a late invoice…": the accepted answer. `/solutions`: open "Close the month in one
   morning", tick the steps, see the "tried it" confirmation. Search `backups` from the top bar.
4. **Enquiry, as the outside person (1 min).** `/inbox`: the conversation with Ledger Works and its reply. Write
   one more message.
5. **The same enquiry, as the business (2 min).** Sign in as Naledi. Ledger Works' inbox shows the new message.
   Reply. Then `/questions`: the company-only question shows for her ("Your company"); it did not for Lerato.
   Open the company-only solution "How we onboard a new bookkeeping client".
6. **Notices (30 s).** The bell: an answer received, an answer accepted, a comment, a confirmation.
7. **Moderation, as yourself (1.5 min).** Sign in with your own moderator account. Moderation, Accounts. Search
   `demo`. Open Lerato, read what suspension does, suspend with a reason, show the history line, restore. (Only
   if you would rather not show your own account: install with `--with-moderator` and sign in as Thabo.)

Afterwards: `php artisan demo:status`, then `php artisan demo:remove --force` when the content is no longer
wanted.

## 5. The accounts desk

Moderators could suspend an account only from a shell (`account:suspend`). `/moderation/accounts` is the screen
for it, in the Moderation tabs as "Accounts".

| Route | What it does |
| --- | --- |
| `GET /moderation/accounts` | The 15 newest sign-ups. With `?q=` (two characters or more): people whose name or email contains it, 20 a page. Each row: name, email, joined, email verified or not, companies, standing (active, or suspended since and why). It never lists everyone. |
| `GET /moderation/accounts/{user}` | The account, its standing, the suspension history, and the action that applies. |
| `POST /moderation/accounts/{user}/suspend` | Reason required, 10 to 255 characters, and the confirmation box ticked. The form says what will happen first. |
| `POST /moderation/accounts/{user}/restore` | Reason optional, at most 255 characters. |

Rules, checked on the server for every action (`AccountSuspension::whyNot`); the page shows the same sentence and
hides the form:

- A moderator cannot suspend or restore themselves.
- A moderator cannot suspend or restore another moderator. That stays the owner's decision, with
  `account:suspend` and `account:restore`.
- The demonstration moderator, when one was installed, can act on demonstration accounts only.
- Every action is written to `account_suspension_events` with the moderator who did it. A second click writes no
  second line.
- A person who does not exist is a 404. Anyone who is not a moderator gets the 403 the rest of `/moderation`
  gives.
