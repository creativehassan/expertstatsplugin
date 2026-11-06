# Test report — Expert Stats 0.4.4

Run: `php tests/run.php`  
Environment: PHP 8 CLI, SQLite in-memory `$wpdb` stub, Node jsdom + jQuery 3, Chrome headless for overflow screenshots  
Network: documentation fetch only (Apiary). No Codeable HTTP, no credentials, no WordPress site.

## Results

See the runner output for the live pass count. Suites:

| Suite | What it proves |
| --- | --- |
| PHP 8 profile / avatar / invalid API guards | Nested avatar arrays, string avatars, and a non-array `wpcable_account_details` option do not TypeError. |
| Server-side pagination / filters / grouping | Page size 25, disjoint page IDs, published/lost/preferred/has_notes filters, client grouping. |
| No-data financial / date handling | Missing transactions stay unavailable, not `$0.00` / 1970. |
| Estimate math regression | PERT hours and fee factors unchanged. 1/1/1 → 1:00 / $100 / $90 and sequential 2/4/10 → 4:40 / $466.67 / $420 and cautious 6:40 / $666.67 (fee_type=client, rate 100, contractor 10%). |
| Opportunity provenance | Cues cite source task IDs; chat/SEO/auto-send stay unsupported unless those keys are on the saved payload. |
| 0.4.1 QA | Y2K38 / sentinel timestamps; health provenance; filter KPI SQL; no auto enqueue; avatar CSS; workroom URL. Missing description/comments stay missing (not empty). |
| 0.4.2 QA (PHP+JS) | Tasks `applyCounts` scope; timezone PKT label; estimator formulas; production JS paints 25 rows from a WP ajax envelope. |
| 0.4.3 QA | List-payload description/comments mapping with missing vs empty; no new HTTP routes in `api_calls.php`; disconnected browsing keeps saved rows; logout does not truncate; fluid CSS (no 1400px cap); Stats/Settings/Estimate/Opportunities production copy; additive schema 0.0.5. |
| 0.4.4 QA | Production Stats HTML has exactly one `#wpcable-stats-overview` and one `#wpcable-import-health`; no `.first-stats-row`; financial charts still in source; ZIP root `expertstatsplugin-master`; page-level `scrollWidth <= clientWidth` at 1920 / 1280 / 390. |
| Packaging | `scripts/package-zip.sh` archive root is always `expertstatsplugin-master/wp-codeable.php`. |

## Description / comments — what the sources actually show

This is **not** a claim that Codeable has no API for these fields.

| Evidence | Path / field | What it proves | Completeness |
| --- | --- | --- | --- |
| This plugin’s importer | `classes/api_data.php` `store_tasks()` from `GET users/me/tasks/{filter}` and `users/me/hidden_tasks/` (`classes/api_calls.php`) | Historically mapped title, flags, prices, state, kind, last_event timestamps only. Description/comments were **unmapped**, not proven absent. | N/A |
| This plugin git history | `store_tasks` never referenced `$task['description']` or comments arrays | Confirms a mapping gap in every importer revision in this repo. | N/A |
| No checked-in task JSON fixture | `tests/` has no sanitized `users/me/tasks` response sample | Whether the **list** payload includes `description` / `comments` is **unverified**. | Unverified |
| Public third-party Chrome extension (Philipp Stracker, `stracker-phil/codeable-extension`) | `GET https://api.codeable.io/task/{id}` reads `task.description`; `GET …/task/{id}/comments` uses `private` and `is_contractors_only` | Credible historical clue that those fields exist on **other** routes. That is **not** official Codeable documentation, so this plugin does **not** add those HTTP requests. | Pagination / full public+private coverage **unverified** |
| Codeable Help Center (product, not API) | Workroom description and comments as product features | The app has the sections. Not an API contract. | N/A |

Public documentation **does exist**: https://codeable.docs.apiary.io/ (blueprint `codeable.apib`). See `docs/APIARY-CODEABLE.md` for exact sections. 0.4.4 still:

1. Maps `description` / `body` / `details` / `task_description` and `comments` / `public_comments` / `private_comments` **only if those keys are already on the permitted list-payload object**.
2. Does **not** add documented `GET /tasks/{id}` or `GET /tasks/{id}/comments` yet — comments pagination in the blueprint is `limit`+`offset` in Parameters but `?page=` in `Link` examples; GET list sample omits `private` while the intro and POST body include it.
3. Distinguishes missing key vs empty value vs stored text. Missing is not rendered as an empty description or empty thread. One live saved record without those keys is not complete source proof.
4. Stores visibility flags (`private`, `is_contractors_only`) only when present. `is_contractors_only` is not in the Apiary blueprint.
5. UI sections exist for genuine stored records.

**Smallest extra evidence needed (no token/password):** a sanitized JSON sample of one `users/me/tasks/{filter}` item, **or** an official documented path if a dedicated comments/description read route should be added later.

## PHP lint

`php -l` on changed plugin PHP files: no syntax errors.

## Not run (by design)

- Live Codeable API / live endpoint guessing
- App XHR reverse engineering / token extraction
- WordPress admin against a real site
- Destructive migrations / table truncates
- Publishing owner-authorised private client screenshots

## Screenshots

Production templates rendered offline (`tests/render-production-screens.php`) plus production JS (Tasks/Clients ajax mocks). Banner: **SYNTHETIC FIXTURE**. Not live workrooms, not private client data.

- `docs/screenshots/production-stats-1920.png` (+ `-1280`, `-390`)
- `docs/screenshots/production-tasks-1920.png` (+ `-1280`, `-390`) — production `wpcable-workspace.js` filled rows
- `docs/screenshots/production-clients-1920.png` (+ `-1280`) — full-width list, drawer hidden until select
- `docs/screenshots/production-opportunities-1920.png` — ranked grouped table
- `docs/screenshots/production-estimate-1920.png` (+ `-390`)
- `docs/screenshots/production-settings-1920.png`

Owner-authorised live WP screenshots used for the visual audit were **not** copied into this repo or the PR.
