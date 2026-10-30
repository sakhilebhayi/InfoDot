# R-16 — Questions & Solutions evolution: audit, design, record

The owner's brief is verbatim in `requirements/infodot-qs-evolution.md`. Built 2026-10-30 on top of
R-15's attachments and R-14's official library.

## 1. What the audit found and what was reused

| Need | Existing piece reused |
| --- | --- |
| Visibility/tenancy | `HasKnowledgeVisibility` global scope + `AnswerService::canSeeContent` judge every new read |
| Notifications | `UserNotice` + `InAppNotices` (new kinds registered; businesses notify owner + admins only) |
| Rewards | `ContributionApproved` → existing `infodot.contribution.approved` ledger rule through the durable `RewardOutbox` (dedupes by ref) |
| Brain | `CandidateFeed` (candidates API) — now carries `tried_count`, real `reuse_count`, and `edited_at` when a version changed |
| Search | Scout/TNT (public content; includes R-15 `attachment_text`) + MySQL FULLTEXT on the results page |
| Files | R-15 attachments ride on improvement proposals and move to what they support on approval |

No parallel systems: confirmations, mentions, recommendations, improvements and versions are five
small tables hanging off the existing models.

## 2. The features, and the honest edges

**“I tried this and it works”** (`solution_confirmations`, unique per person per solution): a
toggle, never the author's own, never mixed with likes; optional experience feedback; aggregated
insights only from five confirmations up; the page says plainly that this is community-reported,
not independent verification. The author is told once per person (ref-deduped).

**Mentions** (`mentions`, one row per identity per item): @name opens a debounced picker. People
are searchable by name; a business only once it has public content (or it is your own), so one
tenant's private client list never appears in another's autocomplete. The inline `@Name` stays
readable text; the stored rows are the resolved identities, shown as chips. Limits: 5 per item,
20/hour per person. A mentioned business notifies its owner and admins (max 5), never all members.

**Recommended solutions** (`solution_recommendations`): an answer can carry up to three references
to existing solutions, re-read under the recommender's own visibility; cards link the original
(never copy it) and the original author is told.

**Duplicate detection** (`SimilarContent`): TNT proposes ranked public candidates, a LIKE pass
adds own-company content, overlap of significant terms labels results *very similar* / *related*.
Semantics ceiling stated plainly: there is no embedding/LLM service in the ecosystem, so "semantic"
here means TNT ranking + term overlap — good enough to match the brief's cash-flow example (tested),
not a claim of NLP. Everything is re-read through the visibility scope; guidance only, publishing
is never blocked.

**Improvements + versions** (`solution_improvements`, `solution_versions`, `solutions.version`):
propose a better description/step or a missing step, with rationale and attachments; only the
original author decides (approve under row lock / reject with note / ask to clarify → proposer
revises). Approval writes the next version (v1 snapshot of the original is kept), credits the
improver on the version and in the page rail ("Improved by"), moves supporting files onto the
content, resets the moderator-verified mark (the verified text changed), reindexes, and rewards
the improver through the existing approved-contribution rule — never when the author approved
their own proposal. A proposal based on an older version is flagged stale and cannot be applied
until re-checked. Ownership never transfers.

**Search** (`config/search.php`): every server-backed box debounces from one config (header 400 ms
default, duplicate detection 700 ms), aborts stale requests, dedupes identical terms, caches
repeats 60 s per person, and is throttled per route. The results page (already paginated,
FULLTEXT on MySQL) now falls back to close-matches-by-meaning when exact words find under 3 hits,
and logs searches slower than `search.log_slow_ms` into the daily log. Index-page filter boxes are
local-only (no server round trip) and deliberately undebounced, per the brief.

**No invented numbers:** production latency is unmeasured (no staging). What the suite pins:
duplicate detection runs ≤12 queries regardless of library size, identical searches re-read
nothing, and the request behaviour above. Benchmarks on real data are an owner/staging task.

## 3. Abuse controls

One confirmation per person (DB unique), authors cannot confirm or reward themselves, mention
caps + hourly rate limits + per-item dedupe, recommendation cap (3) re-read under the
recommender's visibility, improvement rate limit (10/h) and author-only decisions under lock,
stale-version conflict refusal, all lookup endpoints throttled + cached, tenant isolation by the
same global scope as the pages, moderation/report flows unchanged and still apply (hidden content
keeps its attachments and improvements restricted).

## 4. Tests

`tests/Feature/Knowledge/`: SolutionConfirmationsTest (toggle/self/privacy/insights),
MentionsAndRecommendationsTest (search privacy, one-row-one-notice, business reps, visibility of
recommendations, limits), SimilarContentTest (reworded match, unrelated silence, tenant isolation
both ways, scoring thresholds), SolutionImprovementsTest (pending changes nothing, approve
versions/credits/rewards, self-approval earns nothing, author-only, stale conflict, reject/
clarify/revise, new-step), SearchOptimisationTest (query budget, cache, related fallback, config
debounce). Plus the existing candidates contract test extended for the new fields.

## 5. Owner notes

- Rewards: the ledger prices `infodot.contribution.approved`; improvement approvals arrive with
  kind `solution_improvement` and ref `infodot:improvement:N`. No new rule needed; adjust pricing
  in the ledger if improvements should earn differently.
- Brain: the candidates API gained `tried_count`, a real `reuse_count` and `edited_at` (additive
  fields). Brain's recommender can start using them whenever its rules want.
- An approved improvement clears the moderator-verified mark on purpose (the verified text
  changed); moderators re-verify from the solution page as before.
- Consolidating duplicate questions (brief §4.5) is deliberately not built: linking related
  discussions is served by the detection panel and related fallback; a moderator merge tool is a
  future piece if duplicates actually accumulate.
- Nested comment replies: mentions/attachments apply to top-level comments and answers; replies
  stay text-only (same note as R-15).
