# Verified library (R-9 section 5)

Facts only. Built on branch `feat/r9-verified-library`; nothing here is live until the PR is merged and the deploy has
run its migrations.

A "Knowledge Pack" in InfoDot is a moderator-curated **Verified library** of answers and solutions. Adoption is a
moderator decision only. Dot.Brain runs a rules-only recommender (no LLM): it pulls candidates from InfoDot, writes its
recommendations to Dot.Memory, and reads the outcomes back. Brain recommends, moderators decide.

## What exists

| Piece | Where |
|---|---|
| Candidates endpoint for Brain | `GET /api/knowledge/candidates`, `App\Http\Controllers\Api\KnowledgeCandidatesController`, `App\Services\Library\CandidateFeed` |
| Library tables | `library_entries` (reference, adopter, time, recommendation id, `withdrawn_at`; no copy of any text) and `library_outcomes` (a decision about a recommendation, doubling as the outbox to Memory) |
| Rules | `App\Services\Library\LibraryService` (adopt, decline, withdraw), audited in `moderation_actions` as `library_adopted`, `library_declined`, `library_withdrawn` |
| Moderators' tab | `/moderation/library` ("Recommended"): Brain's recommendations from Memory next to the real content, the entries, and a form to add by reference (`POST /moderation/library`) |
| Public page | `/library` for signed-in people, filter `?topic=<slug>`; reads titles live from the source |
| Memory client and outbox | `MemoryRecommendationsClient`, `LibraryOutcomeOutbox`, scheduled `library:flush-outcomes` every minute |
| Points | adoption raises `KnowledgeAdopted`, forwarded as `infodot.knowledge.adopted`; withdrawal raises `LibraryEntryWithdrawn`, forwarded as `infodot.contribution.reward_reversed` with `facts.rule_key = infodot.knowledge.adopted` |

## Signing (both directions)

`X-InfoDot-Timestamp` = unix seconds. `X-InfoDot-Signature` = `sha256=` + hex HMAC-SHA256 of `"{timestamp}." + raw body`
(a GET signs the empty body, so the string is `"{timestamp}."`). The timestamp must be within 300 seconds of the
receiver's clock. Errors are `{"code": "...", "message": "..."}`.

| Direction | Secret (env) | Config |
|---|---|---|
| Dot.Brain to InfoDot (candidates) | `KNOWLEDGE_SECRET_BRAIN` | `knowledge.brain_secret`; unset = every request refused with 401 `invalid_signature` |
| InfoDot to Dot.Memory (recommendations, outcomes) | `KNOWLEDGE_SECRET_INFODOT` | `knowledge.infodot_secret`, base URL `MEMORY_URL`; unset = the tab says Memory is not connected and outcomes wait in the outbox |

Test vector (computed with `openssl dgst -sha256 -hmac`): secret `brain-secret`, timestamp `1700000000`, empty body gives
`sha256=27bcf34443f3118001d2ae002bcb50ccf3bc286b5faea90e95fb95c550084394`.

## Candidates contract

`GET /api/knowledge/candidates?since=<ISO 8601 with offset>&limit=100`, rate limited (60 per minute per address,
`KNOWLEDGE_CANDIDATES_PER_MINUTE`). `limit` is 1 to 200 (default 100); `since` is optional (absent or empty = from the
beginning) and exclusive. A bad `limit` or `since` is 422 `invalid_request`.

```json
{
  "items": [{
    "contribution_ref": "infodot:answer:12",
    "kind": "answer",
    "topics": ["getting-started"],
    "url": "https://.../question/view/7#answer-12",
    "author_uuid": "...",
    "created_at": "2026-10-27T09:00:00Z",
    "edited_at": null,
    "accepted_by_independent": true,
    "accepted_at": "2026-10-27T09:30:00Z",
    "verified_by_independent": false,
    "verified_at": null,
    "helpful_count": 0,
    "reuse_count": 0,
    "hidden": false,
    "reversed": false
  }],
  "next_since": "2026-10-27T09:30:00Z"
}
```

- Public (not company) items only; company content never appears, not even as a withdrawal row.
- An answer is a candidate when someone other than its author accepted it; a solution when a moderator other than its
  author verified it. `kind` is `answer` or `solution`; `contribution_ref` is `infodot:answer:ID` or `infodot:solution:ID`
  (questions are never candidates). `topics` are slugs (an answer takes its question's topics).
- Updates: every item changed after `since` is returned (its own or its question's `updated_at`, which moves on accept,
  un-accept, hide, restore, delete and topic correction). An item that is hidden, deleted, or under a hidden question comes
  back with `hidden: true`; one that is no longer corroborated comes back with `reversed: true` (and its
  `*_by_independent` flag false), so Brain can withdraw its recommendation.
- InfoDot keeps no history of earlier acceptances. A row changed after it was created and not corroborated now is
  reported `reversed: true` even if it never was; that is a withdrawal of something Brain may never have seen.
- Order: oldest change first, then answers before solutions, then id. A page is the first `limit` rows; rows sharing the
  last row's second are included even beyond `limit`, so a cursor never skips one. `next_since` is the last row's change
  time; `null` when the page is empty. Call again with `since=next_since` until `items` is empty.
- `edited_at` is always `null` (content cannot be edited yet); `reuse_count` is always `0`; `helpful_count` is the number of
  likes on a solution from people other than its author (answers have none, so 0).
- No titles, bodies, emails or names. The response carries `Cache-Control: no-store`.

## Recommendations from Memory (what InfoDot consumes)

- `GET {MEMORY_URL}/api/knowledge-recommendations?status=open&verdict=recommend_for_library&limit=50&cursor=` returns
  `{items: [{decision_event_id, contribution_ref, verdict, confidence, rationale: [{rule, effect, detail}], rule_version,
  decided_at, status, outcome_at}], next_cursor}`. Items that do not match (wrong verdict, a question, a bad reference, no id)
  are left out and counted on the tab.
- `POST {MEMORY_URL}/api/knowledge-recommendations/{decision_event_id}/outcome` with `{"outcome": "adopted" | "declined",
  "actor_ref": "<moderator users.uuid>"}`. Sent from the outbox, retried (30 s, 2 min, 10 min, 30 min, 1 h, then every 6 h,
  25 attempts at most); 404 `decision_not_found` or 422 parks the row (the decision in InfoDot stands); a 401 is logged as an
  error and retried hourly.

## Rules of the desk

- Moderators only (route middleware, the component, and the service each check). A moderator cannot adopt, decline or
  withdraw content they wrote.
- Adopting re-checks, under a lock, that the item is public, published, not hidden (nor its question), and corroborated by
  someone other than its author. Repeating it writes no second entry, audit row or event.
- Declining asks for no reason; it is recorded (`library_declined`) and reported to Memory. Withdrawing requires a reason
  (5 to 1000 characters) and sends the reversal once. A withdrawn entry can be adopted again as a new decision.
- The ledger decides what an adoption is worth (10 to 25 points under the rule `infodot.knowledge.adopted`); InfoDot sends
  no points figure.

## Not done

Reuse counting, content editing, automatic withdrawal when the source is hidden (the entry just stops being listed), and
and any use of the Brain "confidence" beyond showing what Memory sends. A repeat outcome for one recommendation is never
sent by InfoDot (one row per recommendation), so Memory's answer to a repeat is not exercised.
