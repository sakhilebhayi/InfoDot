# R-62 audit and phased plan: business-first onboarding and company account experience

Date: 2026-11-24. Status: audit and plan only. Nothing is built. The brief is `R62_BRIEF.md`. Everything marked "verified" was read in this repository today; nothing was tested against production data.

## 1. The short version

Most of the building blocks already exist. A company is a Jetstream team, there is a company switcher, ownership transfer, invitations and roles, a company profile with logo, business verification, free activation, platform recommendations and an onboarding funnel. What is missing is the **order and emphasis** (the company is still something a person discovers after registering), and three things that do not exist at all: **website import, document import and any AI or extraction service**.

The two real decisions are how to do extraction (there is no AI provider in InfoDot today, and sending company documents to an outside service is a data-protection choice) and what a person's name looks like (one `name` column today, the brief asks for first and last).

## 2. What exists today (verified)

| Brief area | What the code does |
|---|---|
| Registration | Fields: one `name`, `email`, password and confirmation, terms. There is **no username** anywhere (nothing to remove). Heading "Create your account", lede "Free. We email you a link to confirm the address first, because other Dot platforms trust it." (`resources/views/auth/register.blade.php`, `app/Actions/Fortify/CreateNewUser.php`) |
| Login | Heading "Sign in". The lede is platform-specific when a platform started the sign-in, otherwise "One account. Connected business tools. A community that shares what works." Not business-first |
| Account structure | Registration creates the person plus a **personal workspace** (a team with `personal_team = true`). A company is a team with `personal_team = false`; a person can own several. The navigation labels the current team "Personal workspace" or "Company" |
| Company creation | Two paths: the "Create a company" menu item (`teams.create`, Jetstream) and the R-54 "Set up your business" page (`/onboarding/business`, shown when a platform for businesses needs a company). Both go through `CreateTeam`, which requires name and visibility (nothing is chosen for the person), with industry and description optional. I extended the second one in R-59 1B with optional stage, size and aims |
| Company profile | `TeamProfile`: description, website, contact email and phone, location, regions, registration number and country, established date, size, social links, billing country and VAT number (private), objectives, stage, size band. Logo is an attachment on the team (`Team::logo()`). Business verification exists (`VerificationService`) |
| Switcher | The navigation lists the person's companies when they belong to more than one (`current-team.update`), plus "Create a company", "Company settings" and API tokens for administrators |
| Isolation | Membership is in `team_user` with a role; access to one company does not grant another (Jetstream teams). Company-scoped records carry a `team_id` |
| Roles | Jetstream roles: owner, `admin` (Administrator), `manager`, `member`, `editor` (labelled Member). **No external collaborator role** |
| Ownership transfer | Exists (R-49): `TransferOwnershipForm`, `TeamPolicy::transferOwnership` |
| Invitations | Email only, one address at a time, through `InviteTeamMember` under a seat-licence guard. Invitations expire (`TeamInvitation::freshExpiry`), can be resent and cancelled. **No bulk entry, no shareable link** |
| Getting Started | `GettingStarted::steps()`: add a profile picture, ask a first question or write a first solution, set up a business, invite a colleague (only once a business exists), open a platform. Two of five steps are personal. The block can be dismissed; the steps are derived from real data, so progress is "saved" by nature |
| Home | `HomeController` draws Getting Started plus the usual home content; the company is not the headline |
| Activation | `POST /platforms/{platform}/activate`. `PlatformAccess::offer()` says `free`, `unpriced_free` (no approved price, so free) or `paid`; `PlatformCatalogue::isUsable()` says whether InfoDot has the platform ON. Core platforms are "included" for every company |
| Recommendations | Two existing, both rule-based and explained: `PlatformRecommendations` (R-50, from industry and what the company has done) and `PlatformRecommender` (R-59 1B, from the aims a company chose). **Neither picks "the first four free platforms that are ON"**; there is no configured priority other than the catalogue's `sort` column |
| Analytics | R-59 1B records account created, email confirmed, business created and described, category chosen, aims set, colleague invited, platforms recommended, first platform opened, and the Growth Centre shows the funnel by channel. **Not recorded:** login completion, which capture method was used, extraction success, abandonment points, returning users, retention |
| Uploads | `config/attachments.php`: images, PDF and video with size caps, content sniffed and the extension must agree, SVG excluded. **No malware scanning** anywhere |
| Outbound requests | Guzzle and Laravel's HTTP client. A careful pattern exists in `MeetingLinkCheck`: https only, never connects to private or reserved addresses, re-checks every redirect. It is specific to meeting links. **There is no general safe-fetch helper** |
| Extraction | **None.** `smalot/pdfparser` is installed (PDF text only). No Word, Excel or OCR parser. **InfoDot has no AI or language-model integration** (no provider code or keys). No website importer exists |

## 3. Gaps against the brief and what they need

| # | Brief item | Gap | Change needed |
|---|---|---|---|
| 1 | Business-first register and login copy | Copy is generic | Copy and layout only |
| 2 | First and last name | One `name` column used everywhere, including other platforms | See decision D2 |
| 3 | Company first after registering | A new person lands on Home with personal steps; a company is created later or when a platform asks | Make company setup the landing step after the email is confirmed, reusing `CreateTeam` |
| 4 | Company as the primary identity | Partly there (switcher, profile) | Headline the active company on Home and in the shell; keep person and company records separate |
| 5 | Getting Started is company-focused | Two personal steps lead | Rewrite the steps: company profile, essential details, invite, activate first platforms, explore |
| 6 | Option A website import | Does not exist | Safe fetcher plus structured-data extraction (section 4) |
| 7 | Option B document import | Does not exist | Parsers, optional AI, scanning (section 4) |
| 8 | Option C minimal manual creation | Exists but asks visibility up front and has more fields | Reduce to name, industry and a short description; the rest later |
| 9 | One review-and-confirm flow | Does not exist | New import record with per-field source, confidence and confirmation |
| 10 | Extraction service reusable by other platforms | Does not exist | A service with a stable interface, in this repository first |
| 11 | First four free, ON platforms, dynamically | No such selector | New selector over the catalogue; see decision D4 |
| 12 | Bulk and link invitations, external collaborator | Email, one at a time; no link, no such role | New invitation forms and a link token; a new role needs your approval |
| 13 | Company-centred Home | Not yet | Home rework |
| 14 | Analytics list | About half recorded | Add events (section 6) |
| 15 | Migrating existing users | Existing companies already work through the same tables | Mostly nothing to migrate; see section 7 |

## 4. Extraction: how, and the decisions it needs

**Website import (Option A)** can be done mostly **without any AI**: fetch the home page and the likely About, Contact and Services pages and read what sites publish for machines (JSON-LD, Open Graph and meta tags, the page title, `mailto:` and `tel:` links, social links). That never invents anything, gives a confidence per field and costs nothing per use. A language model would only help with free-text pages and is optional. It needs a **new safe fetcher**: https only, resolve and refuse private, loopback, link-local and cloud metadata addresses, pin the resolved IP, re-check every redirect, cap size, time and redirects, respect `robots.txt`, rate limit per person and per host, and an allowlist of content types.

**Document import (Option B)**: PDF text is available today; Word, Excel and images need new libraries (and OCR for images). Pulling named fields out of free text reliably needs either rules (names, emails, phone numbers, websites are regular; a description is not) or a language model. Sending a company's documents to an outside AI provider is a data-protection decision under POPIA (a processor agreement, a notice, no retention at the provider), and it has a per-use cost that belongs in the R-60 cost inputs. **I will not wire a provider without your decision.**

**Malware scanning** needs a scanner (ClamAV or a scanning service). On shared cPanel hosting a scanner is often not available; this has to be checked on the host before the promise "malware protection" can be kept. Until then uploads are validated by type, size and content sniffing, parsed in an isolated step, and never opened as code.

**Retention and access** (all PROPOSED): store the uploaded file privately for the company only, delete it within a set number of days after the review is confirmed or abandoned (suggest 30), keep the extracted values and their source, and record which source each confirmed value came from.

## 5. Database changes (PROPOSED)

- `company_profile_imports`: id, team id (nullable until created), user, source (`website`, `document`, `manual`), status, source reference (a normalised host or the private file path), extracted values (JSON with confidence per field), confirmed values, conflicts, created and expired timestamps. This is also where the original source is kept for audit.
- `team_profiles`: a per-field source marker for confirmed values (`field_sources`, JSON) so a later import never silently overwrites a confirmed value.
- `team_invitations`: a shareable-link token with its own expiry and role cap (a separate table, `team_invite_links`, is cleaner).
- No change to `users` or `teams` is needed except the name question (D2). Nothing merges person and company records.

## 6. Analytics to add

Login completion, capture method chosen and completed, extraction success per field, time from account to first company, abandonment step (the last step reached, from the events already kept), invitation accepted, returning business users and retention windows. Counts are of people, companies and memberships separately. These are extra rows in the onboarding events table and the Growth Centre; no figure is shown without a record behind it. A before-and-after comparison is only possible from 2026-11-24, when recording began.

## 7. Migration

Existing companies already use `teams`, `team_profiles` and `team_user`, so the new flow reads them as they are. The only careful part is people who registered but never made a company: they keep their personal workspace and see the new company-first setup on their next visit. Nobody is moved, merged or renamed. Duplicate company detection (same registration number or same website) is a check at creation, not a migration.

## 8. Security

Tenant isolation is unchanged (team id on every company record, membership checks). New items: the safe fetcher (above), private storage and access checks on uploaded documents, invitation links as random tokens stored hashed with expiry and a maximum role, rate limits on import and invitation endpoints, nothing an import finds is treated as verified, audit entries for imports and for link creation, and no extracted text is rendered unescaped.

## 9. Phased plan (PROPOSED)

| Phase | What | Needs |
|---|---|---|
| 0 | Your answers to D1 to D8 | You |
| 1 | Business-first register and login copy; company setup as the first step after the email is confirmed; manual creation reduced to name, industry and a short description; Getting Started rewritten company-first | Nothing new |
| 2 | Shared "capture, review, confirm" flow and the import record; per-field source; duplicate check | Migration |
| 3 | Website import (safe fetcher, structured-data extraction, no AI) | D5 |
| 4 | Invitations: several emails at once, a shareable link, invitation status | Migration; D6 for the role |
| 5 | The first-four selector over free, ON platforms with activation from Getting Started | D4 |
| 6 | Company-centred Home and the extra analytics | Nothing new |
| 7 | Document import: PDF and text first, then Word, Excel and images if approved; scanning once the host is checked | D3, D5, new libraries (approval) |

Each phase is tested (real requests, no mocks of the extraction itself), formatted, analysed and reviewed before it merges, and nothing is deployed without your go.

## 10. Decisions needed

| # | Decision | Recommendation |
|---|---|---|
| D1 | Landing: after the email is confirmed, go straight to company setup | Yes |
| D2 | First and last name | Collect both in the form and store them joined in the existing `name`, so nothing else in the ecosystem changes. A separate stored first and last name touches every platform that reads the person |
| D3 | AI provider for documents: none, or which one, and the data-protection terms | Start with no AI; rules for the regular fields; decide on a provider only if the results are not good enough. Needs the cost inputs for R-60 if used |
| D4 | What "free" and "first four" mean: free if the offer is `free` or `unpriced_free`, ON if InfoDot has it operational or degraded; do core (already included) platforms count; the order is the catalogue `sort` unless you set another | Count only non-core platforms the company does not hold yet, in catalogue order, then personalise by industry and aims among the eligible ones, never outside the four rule |
| D5 | Website import without AI first | Yes |
| D6 | A new "external collaborator" role: what it may see and do | A member with no access to the company's business page editing, team list or settings; to be confirmed |
| D7 | Retention for uploaded documents | 30 days after confirmation or abandonment |
| D8 | Scanner: is installing ClamAV (or a scanning service) acceptable, and does the host allow it | To be checked on the host |

## 11. Not verified

Production data and settings, the exact current look of each screen in a browser, whether the host can run a scanner, how many existing people registered without ever creating a company, and which platform entries are ON in production (the catalogue was not read from the live database).
