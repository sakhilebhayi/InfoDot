# R-14 — Knowledge seeding: how the official library works

The owner's brief is verbatim in `requirements/infodot-knowledge-seeding.md`. This file records the
architecture audit, the design that came out of it, what each batch contains, and how to publish.

## 1. Audit of the publishing architecture (2026-10-30)

What already existed, and is reused unchanged:

| Piece | Where | Used for R-14 |
| --- | --- | --- |
| `Solutions` + `Steps` | `app/Models/Solutions.php`, `Steps.php` | One entry = one public solution with steps (title ≤255, description ≤20 000, step bodies ≤5 000, ≤50 steps) — the same shape the create form writes. |
| Topics | `Topic`, `TopicService`, `TopicCatalogue` | Entries are filed under 1–3 topics. The starter catalogue is platform support topics (Getting started, Billing, one per platform); business subjects did not exist, so the seeder adds them (on the moderation trail). |
| Visibility | `HasKnowledgeVisibility` | Entries are `public`: every signed-in member sees them, no company sees another's content. |
| Search | Scout + TNTSearch on `Solutions` | Public entries index on save; questions surface them through search and “More like this” (`App\Support\Related`). |
| Verification + library | `verified_at`, `LibraryEntry` | Untouched. The official account is **not** a moderator: a human moderator may verify an entry or adopt it into the Verified library, the official account cannot approve its own work. |
| Moderation, comments, likes, reports | existing | Apply to seeded entries like any other solution. |

Gaps found, and the smallest implementation chosen (brief §10):

1. **No official account existed.** `knowledge:seed` creates one from `config/knowledge.php` →
   `official` (default `library@infodot.co.za`, name “InfoDot”): verified, random password nobody
   holds, its own personal workspace, no moderator flag. `User::isOfficialAccount()` identifies it;
   the solutions list and detail pages show an “Official” chip beside its name.
2. **No idempotency key.** `solutions.seed_key` (nullable, unique; migration `2026_10_30_100005`)
   carries the entry file's key, so re-running updates in place. Community solutions keep NULL.
3. **No multi-paragraph rendering.** Solution descriptions and step bodies rendered as collapsed
   single lines; the views now keep the author's paragraphs (`nl2br(e(...))` — still escaped).
4. **No “last reviewed” field.** Not added (brief: “where the platform supports it”). Each entry's
   Sources step must carry a “Last reviewed: date” line; the validator enforces it.
5. **No structured source field.** Not added. Sources live in the entry's final step
   (“Sources and further reading”), with publisher and date, enforced by the validator.

## 2. How an entry is written and published

Entries are **content as code**: one Markdown file per entry under `database/knowledge/<stage>/`,
reviewed in a pull request like any other change, then published on the host with one command.

File format (validated fail-closed; one bad file stops the whole batch):

```
---
key: matches-the-file-name            (lower-case, digits, hyphens; becomes solutions.seed_key)
title: Shown as the solution title
topics: Money and cash flow | Starting a business      (1-3, "|"-separated names)
tags: comma, separated, search tags
duration: 4
duration_type: hours|days|weeks|months|years|infinite
evidence: One line naming the evidence type (research-backed / documented practice / best practice / official requirement).
---
The discovery summary: what problem this solves and for whom. Appears above the steps;
the "Evidence:" line is appended to it automatically.

## First step heading
Step body. Plain text only (everything is escaped); paragraphs are kept.

## Sources and further reading
Title - Publisher - URL - date. One per line.
Last reviewed: YYYY-MM-DD.
```

The last section must be the sources (with at least one link and the review date). Content rules
come from the brief §4–§6: problem → why it happens → who it is for → approach → concrete steps →
tools → verifiable example (or none, never invented) → KPIs → mistakes → when to get a
professional. Nothing goes in a file that was not checked against the cited source.

Commands (host, from `/home/infodotc/public_html/main`):

```
php artisan knowledge:seed --dry    # validate and list, write nothing
php artisan knowledge:seed         # publish/update under the official account
```

`--force` publishes a new entry even when a community solution already has the same title (by
default such entries are skipped and reported, so a batch never shouts over a member). Tests:
`tests/Feature/Knowledge/KnowledgeSeedTest.php`, including a gate that every shipped entry file
validates.

## 3. Batches

### Batch 1 — Stage 1, essential survival knowledge (2026-10-30)

Eight entries, researched 2026-10-30 (web research with source verification; SA official sources
for regulatory content). Files in `database/knowledge/stage-1/`. Topics introduced: Starting a
business, Money and cash flow, Customers and sales, Pricing, Planning and strategy, Legal and
compliance.

Entry list and review notes are kept beside the files as they land; anything a batch could not
verify is marked inside the entry itself, never stated as fact.

## 4. Still requires review (owner)

- The official account's address: `library@infodot.co.za` by default (`KNOWLEDGE_OFFICIAL_EMAIL`
  overrides). The mailbox need not exist; the account cannot be signed into (random password), but
  password reset would email that address — point it somewhere the owner controls or leave it
  unrouted.
- Moderator verification of seeded entries is a human decision, entry by entry. Entries ship
  unverified on purpose.
- Regulatory figures (VAT threshold, filing fees, interest rates) change: each entry carries its
  “Last reviewed” date, and re-publishing an updated file is one command. A periodic review is an
  owner task; suggested cadence every 6 months.
