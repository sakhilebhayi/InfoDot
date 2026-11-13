# R-49 Image audit: no broken picture, a fallback for every missing one

Date: 2026-11-13. Branch `r49/interaction-image-audit`, cut from `main` at `dd18954`. Companion to `INTERACTION_AUDIT.md`; the same sweep produced the numbers. Nothing was requested from the production site, so what the live web root actually holds is not known here; where that matters it is said.

## How pictures reach a page

| Kind | Where the file lives | How a page names it | Who may load it |
|---|---|---|---|
| Site logo, tab icons | `public/img/logo_white.png`, `public/img/icons/icon.png`, `public/favicon.ico`, `public/img/logo1.png` | `asset()` | Anyone |
| Platform logos, landing photographs, the arrow | `resources/img/**` (58 files) | `Vite::asset()`; the build picks them up through the `import.meta.glob` list in `resources/js/app.js` | Anyone |
| Profile pictures | public disk, `profile-photos/` | `/storage/profile-photos/{file}` (`ProfilePhotoController`) | Anyone with the random file name |
| Business logo, cover, gallery, offering and project pictures, brochures | private disk | `/attachments/{uuid}` (`AttachmentController`) | Whoever may open the business page; a replaced picture only the business's decision-makers and moderators |
| Question, solution, step, answer and comment attachments | private disk | `/attachments/{uuid}` and `/attachments/{uuid}/thumb` | Whoever may see the item they are on |
| Verification evidence, report and appeal files, inbox files | private disk | `/attachments/{uuid}` | The parties and reviewers only |

## What the sweep inspected

Every `<img src>`, `srcset` entry, `<source>`, `poster`, CSS `url()` in a style attribute or `<style>` block, icon link, stylesheet, script and frame on every page of every walk (1,715 pages, seven walks, see `INTERACTION_AUDIT.md`).

| | Occurrences over all walks | Distinct files |
|---|---|---|
| Pictures named through the build (`<img>`, `srcset`, CSS `url()`) | | 51 |
| Pictures served by the app (attachments, thumbnails, profile pictures) | | 4 (3 address shapes) |
| Pictures from `public/` (`img/logo_white.png`) | | 1 |
| Tab icons from `public/` (`favicon.ico`, `img/icons/icon.png`) | | 2 |
| Pictures from another host (the sign-in card's background, `images.unsplash.com`) | | 1 |
| **All picture references** | **40,049** (`<img>` 36,546, CSS `url()` 1,774, icons 1,715, `srcset` 14) | **59** |
| Pictures whose address Alpine fills in (lightbox, upload preview), or a script (crop preview) | 640 | 2 |
| Stylesheets from another host (Google Fonts, Material Symbols, Font Awesome) | 3,430 | 5 |
| Scripts (Livewire from the app; ApexCharts from `cdn.jsdelivr.net` on Analytics) | 1,715 | 2 |

How each distinct file was verified:

- **Built (51):** the source file exists under `resources/img` and one of the globs in `resources/js/app.js` matches it, so the deploy's build includes it. The committed `public/build` is deliberately not what is checked: the deploy builds its own.
- **Served by the app (4):** requested as the viewer who was shown it; all answered 200 with an image content type.
- **From `public/` (3):** the file exists and is not empty, with one exception (below).
- **Other hosts (7 in all):** not fetched. Each host is checked against the page's own `Content-Security-Policy` header for its kind (`img-src`, `style-src`, `script-src`).

Result on the fixed branch: 59 picture references, 58 valid, 1 broken (`favicon.ico`, left for the owner). No missing files, no wrong paths. Before the fixes there was one more broken reference (defect 9).

`ImageIntegrityTest` also reads every Blade view and every PHP file under `app/` (not just rendered pages): 15 `asset()` references to 4 files, all present; 9 `Vite::asset()` call sites, all resolving to globbed source files; every outside host named in an `<img>`, `<script>`, stylesheet link or CSS `url()` allowed by the policy.

## Defects found and fixed

Line numbers are on `main` at `dd18954`. "Image" is `tests/Feature/Stabilisation/ImageIntegrityTest.php`; "Sweep" is `InteractionSweepTest.php`.

| # | Where | What was wrong | Root cause | Fix | Guard |
|---|---|---|---|---|---|
| 9 | `resources/views/analytics/dashboard.blade.php:219` | Each "Top contributors" row asked `www.gravatar.com` for a picture. The policy's `img-src` does not allow that host, so every row showed a broken image. It also put an MD5 of each person's email address in the page, which `User::avatar()` was written to stop. | A leftover from before the policy was enforced. | The shared avatar (`<x-ix.avatar>`): the person's own picture, or their initial. | Sweep (outside host not allowed); Image `test_no_view_shows_a_person_except_through_the_shared_avatar` |
| 10 | `navigation-menu.blade.php:85`, `profile/show.blade.php:15`, `profile/update-profile-information-form.blade.php:29`, `teams/team-member-manager.blade.php:125`, `teams/update-team-name-form.blade.php:17`, `teams/create-team-form.blade.php:16` | A profile picture whose file is no longer on the disk was still put on the page: a broken image in the top bar of every page for that person. | The views tested the database column, not the file. | `User::photoUrl()` returns the address only when the file is there (asked once per request); `<x-ix.avatar>` shows the initial otherwise. The address itself already answered 404. | Image `test_a_profile_picture_whose_file_is_gone_falls_back_to_the_initial_everywhere` |
| 11 | `home.blade.php:84`, `livewire/comment.blade.php:2`, and the six above | People were drawn three ways: picture or initial in some places, always the initial in others (the Home roster, comments), an outside avatar on Analytics. The initial was cut with `substr`, which splits a name starting with an accented letter into half a character. | Each view wrote its own. | One component, `<x-ix.avatar>`, and `User::initial()` (multibyte-safe). | Image `test_a_person_without_a_picture_is_an_initial_and_never_an_empty_image`, and the static check in row 9 |
| 12 | `BusinessProfileController.php:66-68,75-77,91-93,100-102`, `IdentityDeskController.php:41-44`, `businesses/_card.blade.php:8`, `inbox/index.blade.php:35`, `attachments/_list.blade.php:8` | A business logo, cover, gallery, offering or project picture, a brochure, or a picture on a question or solution whose file is gone was still put on the page: broken images on the public business page, the directory, the inbox and knowledge pages, and a brochure link that led to 404. | The pages trusted the database row. | `Attachment::onDisk()` (asked once per request) and `Attachment::shown()`. A lost logo falls back to the business's initial, as for a business that never uploaded one; a lost cover, gallery, offering or project picture and a lost brochure are left out; a lost attachment on a question, solution, step, answer or comment is left out and the page says "One attached file could not be loaded." The address answers 404, as before. | Image `test_business_pictures_whose_files_are_gone_fall_back_and_their_addresses_answer_404`, `test_a_question_or_solution_picture_whose_file_is_gone_is_said_not_shown`, `test_a_brochure_whose_file_is_gone_is_not_offered_for_download` |

Privacy was checked and needed no change (`test_pictures_are_served_only_to_people_who_may_see_what_they_belong_to`, `test_only_profile_pictures_are_served_from_the_storage_address`): a picture on a company-only question is 404 for a signed-in outsider and a sign-in redirect for a visitor; verification evidence opens only for the business's owner and administrators and for moderators; a replaced logo stops being public; a private business's logo is not served to visitors or outsiders; only `profile-photos/` files with picture extensions are served from `/storage`.

## Files under `public/` that views use, and the deploy

The deploy copies `public/build` to the web root and refreshes two files from the repository: `robots.txt` and `.well-known/security.txt` (`.github/workflows/deploy.yml`, "static files the docroot serves"). Nothing else under `public/` is published by a deploy.

| File | Used by | On the deploy's list | Note |
|---|---|---|---|
| `img/logo_white.png` | The site logo: `navigation-menu.blade.php:7`, `home.blade.php:39`, `components/public-layout.blade.php:42`, `layouts/guest-top.blade.php:6`, `errors/_ecosystem-layout.blade.php:72`, three Jetstream logo components, `vendor/mail/html/message.blade.php:5` (every email) | No | In the repository since the first commit (2026-03-18), so the live web root's own `img/` folder is expected to hold a copy. Not verified here. |
| `favicon.ico` | `layouts/app.blade.php:10`, `components/public-layout.blade.php:21`, `errors/_ecosystem-layout.blade.php:9` | No | **Zero bytes** since the first commit. See decision 1. |
| `img/icons/icon.png` | `layouts/guest.blade.php:7-8` (sign-in, register, reset pages) | No | A blue magnifier "idot" mark, not the current logo. |
| `img/logo1.png` | `welcome.blade.php:24` (the organisation logo in the landing page's structured data) | No | Same age as the logo. |

`ImageIntegrityTest::test_every_file_a_view_names_under_public_exists_and_no_new_one_has_been_added` holds this list closed: a view that names any other file under `public/` fails, with the instruction to use `resources/img` and `Vite::asset`.

## Owner decisions

1. **The tab icon.** `public/favicon.ico` is empty, so the app pages and the public pages have no icon in the browser tab, and the sign-in pages show an older blue mark. Choosing the icon is a brand decision. Once chosen, it should be built like the other images (`resources/img`, a glob in `resources/js/app.js`, `Vite::asset` in the three layouts) so it does not depend on what the web root happens to hold.
2. **The logo depends on a file the deploy does not publish.** It works today only if the web root kept its old `img/` copy. Moving `logo_white.png` to `resources/img` and naming it through Vite removes that dependency. It was not done here because many existing tests render pages against the committed `public/build/manifest.json`, the instruction for this change was not to commit `public/build`, and the test job does not run the build; the move needs either a refreshed committed manifest or a build step before the tests.
3. **Pictures and fonts from other hosts.** Allowed by the policy and working, but each needs the network on the day of a demonstration: the sign-in card's background (`images.unsplash.com`), the text fonts and the Material Symbols icon font (Google Fonts; without it the icons appear as their names, such as "arrow_back"), ApexCharts on Analytics (`cdn.jsdelivr.net`), and a Font Awesome stylesheet on the sign-in pages (`cdnjs.cloudflare.com`) that only two unmounted views use.
4. **100 unused files in `public/img`** (104 tracked, 4 used; 102 MB in the repository). No view names them. Removing them from the repository does not touch the web root's copies.

## Remaining exceptions

- **A thumbnail lost while its original survives** would still be a broken thumbnail: `onDisk()` checks the original. The two are written together and removed together.
- **Images inside emails** (the logo in every email, by absolute address) are not rendered by the sweep. The file check covers the path.
- **Pictures an Alpine expression fills in** (the lightbox's large image, the upload preview) get their address in the browser; the lightbox's addresses are the same attachment addresses the grid uses, which are requested.
- **Unmounted views** (`livewire/solution-list.blade.php:7` asks `source.unsplash.com` for a random picture) are never rendered. The policy allows the host, so the static check passes; the view should go when the legacy components are removed.
- **The live web root** was not inspected: the instruction was not to send requests to production.
