# R-15 — Rich multimedia questions and solutions: audit, constraints, design

The owner's brief is verbatim in `requirements/infodot-rich-media.md`. This file records what the
audit found, the constraints that shaped the design, what is built, and what is deliberately not.

## 1. Audit (2026-10-30)

| Area | Finding |
| --- | --- |
| Question composer | Plain Blade form (`questions/seek.blade.php`) → `QuestionsController@add_question`. Text only (title ≤255, description ≤20 000), topics, publish toggle. |
| Solution composer | Plain Blade form (`solutions/create.blade.php`) → `SolutionsController@add_solution`. Text, steps (≤50), topics, duration. |
| Answers | Livewire `Answers` → `AnswerService::publish` (text body, rate-limited, max per person per question). |
| Comments | Livewire `Comments` (text ≤5 000) on questions and solutions. |
| File upload today | None in InfoDot. `App\Models\File`/`Folder` exist but no routes use them; `config/media-library.php` is a leftover — spatie/laravel-medialibrary is **not** in composer.json. The Dot.Files page is a placeholder. |
| Storage | `local` disk = `storage/app` (private). No S3. Shared cPanel disk. |
| PHP | `fileinfo` and `gd` available (host and dev). No imagick, no ffmpeg, no ClamAV on shared hosting. |
| Background work | No queue worker (`QUEUE_CONNECTION=sync`); `afterResponse` closures + scheduled commands (cron pass killed at 115 s). |
| AI in the ecosystem | Dot.Brain is a rules-only recommender (no LLM); Dot.Memory stores context. There is **no** existing OCR, transcription, or vision capability anywhere in the ecosystem. |
| Visibility | `HasKnowledgeVisibility` global scope (public / company / author); `AnswerService::canSeeContent` is the single judge pages use. Moderation: hide + reports + appeals. |

## 2. Constraints → decisions

1. **No queue, 115 s cron ceiling** → uploads are validated and stored synchronously (fast);
   image thumbnails are made at upload (GD, best-effort); PDF text extraction runs in an
   `afterResponse` closure and is best-effort. Nothing blocks a submission on processing.
2. **Shared host, no ffmpeg** → no video transcoding, no poster frames. Videos are accepted as
   ready-to-play formats only (MP4, WebM), size-capped, and served with HTTP Range support so
   seeking works in the native player. Large-scale video (long seminars etc.) stays on the
   R-13 note: it needs its own server; this feature is for short demonstrations.
3. **No ClamAV / malware scanner on shared hosting** → defence is: content-sniffed allowlist
   (fileinfo, never the filename), extension/mime pairing, no SVG, files stored outside the web
   root and streamed through a permission-checked controller with `nosniff` and a sandboxing CSP,
   and download disposition for anything not previewable. An external scanning service is listed
   as an owner decision (dependency + cost) in §6; the brief's rule is to identify such
   dependencies before integrating them.
4. **No LLM/OCR/transcription service exists** → brief §5 is implemented to the honest extent:
   PDF text extraction (pure-PHP `smalot/pdfparser`) feeding search and the existing related-item
   suggestions; everything else (image understanding, OCR, video transcripts, AI clarifying
   questions) is documented in §6 as requiring an external service — not faked.
5. **PHP upload limits on the host are server-side** (`upload_max_filesize`/`post_max_size`) —
   the app's caps are config (`config/attachments.php`) but the host must allow at least the
   largest cap; owner checks MultiPHP INI. Chunked/resumable uploads are future work if the host
   ceiling proves low.
6. **Reuse, don't duplicate** → one polymorphic `attachments` table + one service + one widget +
   one viewer partial serve questions, solutions, solution steps, answers and comments. The old
   unused `File` model is left untouched (it belongs to the Dot.Files placeholder).

## 3. Design

**Model** `Attachment` (`attachments`): uuid (route key), user_id, team_id, nullable polymorphic
`attachable` (Questions, Solutions, Steps, Answer, Comment), kind (image|pdf|video), path,
thumb_path, name, mime, size, caption, sort_order, status, extracted_text, meta (json),
expires_at. An upload starts **unbound** (owner-only, expires in 24 h); submitting the form binds
it to the created content in the same transaction; `attachments:prune` (daily) deletes expired
orphans. Deleting content deletes its attachments and their files.

**Flow**: the composer widget (Alpine + XHR) uploads each file to `POST /attachments` (progress,
cancel, remove, caption, reorder client-side), and the form submits only the uuids+captions. The
server re-checks ownership and binds. Livewire forms (answers, comments) carry the same payload
through a hidden field.

**Serving**: `GET /attachments/{uuid}` streams from the private disk after a permission check
(unbound → owner only; bound → exactly who may see the parent, via `AnswerService::canSeeContent`,
with hidden content restricted to author + moderators). Range requests give 206 for video seek.
`/thumb` serves the GD thumbnail. URLs carry no storage paths.

**Caps** (config/attachments.php, defaults): image 10 MB, PDF 20 MB, video 100 MB; 10 attachments
per item; per-person daily quota (count + bytes). All server-enforced.

**Presentation**: one `attachment-list` partial — image grid with captions and a full-screen
lightbox (`<dialog>`), PDFs as cards with inline `<iframe>` preview on demand + download, videos
in the native player (`preload="metadata"`, never autoplay). Feeds show kind indicators and
support `?has=images|documents|videos`. Step attachments render under their step.

## 4. What is built (by brief phase)

- Phase 1–2: composer widget on ask + solution forms (incl. per-step), attachments table,
  upload/bind/prune/serve, caps and quotas — built.
- Phase 3: viewers (lightbox, PDF preview, video player), detail pages, feed indicators — built.
- Phase 4: answers and comments accept attachments through the same widget — built. Nested
  comment replies stay text-only (the reply form lives in the per-comment component; adding the
  widget there is a small follow-up if replies turn out to need media).
- Phase 5: PDF text extraction into search and related-content suggestions — built. OCR, image
  understanding, video transcription — external-service dependencies, see §6.
- Phase 6: extracted text joins the parent's search index; existing Brain candidates API already
  carries solutions (unchanged); content-type filters on feeds — built.
- Phase 7: validation/permission matrix tests, Range tests, prune test, quota tests — built
  (`tests/Feature/Attachments/AttachmentsTest.php`), plus a browser pass on 2026-10-30 against a
  local dev run: upload with progress/caption through the ask form (bound and displayed with
  lightbox), the Livewire answer path (bound, widget cleared), the per-step uploader on the
  solution form (uploads survive step removal and reindex their field names), feed indicators and
  the `?has=` filters, and streaming of the stored files (200/206).

## 5. Publishing/ops notes

Migration adds the table; no data migration. `attachments:prune` is scheduled daily. Disk usage
is the owner's cPanel quota: `du -sh storage/app/attachments` is the number to watch; a storage
usage line is in `ops:check`.

## 6. Owner decisions / external dependencies (not built, per brief §12)

- **Malware scanning**: needs an external scanner (e.g. a scanning API) or a host with ClamAV.
  Until then the mitigations in §2.3 stand. Decide if/when uploads open to a wider public.
- **OCR for images**, **video transcription**, **AI clarifying questions / image understanding**:
  all need a paid external AI service plus a place to run it; the ecosystem has none today. The
  structured hooks exist (`extracted_text`, `meta`) so a service can be added without reshaping
  the schema.
- **Host upload ceiling**: check `upload_max_filesize`/`post_max_size` in MultiPHP INI ≥ the
  video cap before announcing video support.
- **Disk headroom**: confirm cPanel quota headroom for media.
