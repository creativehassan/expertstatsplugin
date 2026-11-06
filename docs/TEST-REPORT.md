# Test report — Expert Stats 0.4.5

Run: `php tests/run.php`  
Environment: PHP 8 CLI, SQLite in-memory `$wpdb` stub, Node jsdom + jQuery 3, Chrome headless for overflow screenshots  
Network: no Codeable HTTP, no credentials, no WordPress site. Apiary docs were read from the attached 2015-03-02 blueprint extract only.

## Stages

| Stage | Version | What shipped |
| --- | --- | --- |
| 1 | 0.4.4 | Stats: one `#wpcable-stats-overview` + one `#wpcable-import-health`; overflow CSS; ZIP root `expertstatsplugin-master/`. Artifact kept: `expertstatsplugin-0.4.4-hybrid.zip`. |
| 2 | 0.4.5 | Documented read-only `GET /tasks/{id}` + `GET /tasks/{id}/comments` with per-project Load action, local cache, offline tests. |

## Results

See the runner output for the live pass count. Suites:

| Suite | What it proves |
| --- | --- |
| PHP 8 profile / avatar / invalid API guards | Nested avatar arrays, string avatars, and a non-array `wpcable_account_details` option do not TypeError. |
| Server-side pagination / filters / grouping | Page size 25, disjoint page IDs, published/lost/preferred/has_notes filters, client grouping. |
| No-data financial / date handling | Missing transactions stay unavailable, not `$0.00` / 1970. |
| Estimate math regression | PERT hours and fee factors unchanged. |
| Opportunity provenance | Cues cite source task IDs; chat/SEO/auto-send stay unsupported unless those keys are on the saved payload. |
| 0.4.1 QA | Y2K38 / sentinel timestamps; health provenance; filter KPI SQL; no auto enqueue; avatar CSS; workroom URL. |
| 0.4.2 QA (PHP+JS) | Tasks `applyCounts` scope; timezone PKT label; estimator formulas; production JS paints 25 rows from a WP ajax envelope. |
| 0.4.3 QA | List-payload description/comments mapping with missing vs empty; list importer paths unchanged; disconnected browsing; fluid CSS. |
| Packaging | `scripts/package-zip.sh` archive root is always `expertstatsplugin-master/wp-codeable.php`. |
| 0.4.4 QA | One Stats overview and one import-health; no `.first-stats-row`; financial charts still in source; page-level `scrollWidth <= clientWidth` at 1920 / 1280 / 390. |
| 0.4.5 QA | Documented plural `/tasks` GETs only; host stays `api.codeable.io`; redirects disabled; `limit`/`offset` not `page=`; missing `private` → unknown; 403 / HTTP error do not overwrite cache; full page without Link is partial; other-host and `/v1` Link not followed; disconnected load makes no HTTP; other connected account hides cache; disconnected owner can view saved cache; individual `comments/{id}` allowlisted but not auto-called; no POST/PUT/PATCH in the loader; page views/cron do not bulk-call the loader. |
| Production JS | Drawer shows **Load from Codeable (read-only)** and posts `wpcable_load_task_history` (local WP ajax, not a Codeable URL). |

## Description / comments — what 0.4.5 actually calls

| Path | When | Completeness / visibility |
| --- | --- | --- |
| `GET https://api.codeable.io/tasks/{id}` | Opt-in Load, known imported `task_id`, connected account | Description from payload when present. 403/5xx do not wipe cache. |
| `GET https://api.codeable.io/tasks/{id}/comments?limit=20&offset=N` | Same Load, after the task GET | `private` missing → unknown. Complete only if X-Total-Count met, short last page + no next, or 204 on page 0. Full page + no Link = partial. |
| `GET /tasks/{id}/comments/{comment_id}` | Allowlisted helper only | Not used by the Load loop. |
| `GET users/me/tasks/{filter}` | Existing list importer | Unchanged. Maps description/comments only if those keys are already on the list object. |
| Singular `/task/{id}` | Never | Undocumented third-party path. |

Apiary **lastUpdated 2015-03-02**. HOST in the blueprint is `api.codeable.com`; this plugin keeps the working `api.codeable.io` host and does not send the Bearer token to another domain.

## PHP lint

`php -l` on changed plugin PHP files: no syntax errors.

## Not run (by design)

- Live Codeable API from this cloud agent
- Credential / token extraction or minting
- WordPress admin against a real site (first live check is coordinator: one imported project → Load from Codeable)
- Destructive migrations / table truncates
- Attachments / reviews endpoints
- Bulk backfill

## Screenshots

Production templates rendered offline (`tests/render-production-screens.php`) plus production JS (Tasks/Clients ajax mocks). Banner: **SYNTHETIC FIXTURE**. Not live workrooms, not private client data.

- `docs/screenshots/production-stats-1920.png` (+ `-1280`, `-390`)
- `docs/screenshots/production-tasks-1920.png` (+ `-1280`, `-390`)
- `docs/screenshots/production-clients-1920.png` (+ `-1280`)
- `docs/screenshots/production-opportunities-1920.png`
- `docs/screenshots/production-estimate-1920.png` (+ `-390`)
- `docs/screenshots/production-settings-1920.png`

Owner-authorised live WP screenshots used for visual audit were **not** copied into this repo or the PR.
