# Test report — Expert Stats 0.4.2 QA patch

Run: `php tests/run.php`  
Environment: PHP 8 CLI, SQLite in-memory `$wpdb` stub, Node jsdom + jQuery 3  
Network: none. No Codeable HTTP, no credentials, no WordPress site.

## Results

See the runner output for the live pass count. Suites:

| Suite | What it proves |
| --- | --- |
| PHP 8 profile / avatar / invalid API guards | Nested avatar arrays, string avatars, and a non-array `wpcable_account_details` option do not TypeError. Instrumented `record()` still stores a real `succeeded_at` only on success. |
| Server-side pagination / filters / grouping | Page size 25, disjoint page IDs, published/lost/preferred/has_notes filters, client grouping. |
| No-data financial / date handling | Missing transactions stay unavailable, not `$0.00` / 1970. |
| Estimate math regression | PERT hours and fee factors unchanged. Live-verified 1/1/1 → 1:00 / $100 / $90 and sequential 2/4/10 → 4:40 / $466.67 / $420 and cautious 6:40 / $666.67 (fee_type=client, rate 100, contractor 10%). |
| Opportunity provenance | Cues cite source task IDs; chat/SEO/auto-send stay unsupported. |
| 0.4.1 QA | Y2K38 / future timestamps do not format or yield negative ages; sentinel activity does not ground EBR scores; health snapshot is cached vs no-recorded-attempt vs in-progress without inventing `succeeded_at`; filter KPI SQL matches table filters; `maybe_refresh` does not enqueue; sync lock rejects a second acquire; avatar CSS max 32×32; workroom URL is `https://app.codeable.io/tasks/{id}/workroom`; comments/description marked unavailable. |
| 0.4.2 QA (PHP) | `applyCounts(data, $scope)` does not reference unbound `$root`; legacy `wpcable.js` skips `.wpcable-workspace`; `date_default_timezone_set` is gone; 17:13 UTC in `Asia/Karachi` formats as 10:13pm PKT, not 5:13pm labeled local; estimator template formulas unchanged. |
| 0.4.2 QA (JS) | Production `wpcable-workspace.js` against `{success:true,data:{items:[25],total,page,per_page,filter_counts,sync}}` replaces Loading with 25 rows and pagination; search / page 2 / per_page 50; `success:false` and ajax `.fail` do not leave Loading; a separate `{success:false,data:{state:'FINISHED'}}` sync poll does not clear rows. Estimate fixture sequential input+change+blur matches the live-verified numbers. |

## PHP lint

`php -l` on every plugin PHP file: no syntax errors.

## Root cause (Tasks Loading)

Live `wpcable_query_tasks` was HTTP 200, valid `application/json`, envelope `{success:true,data:{items:[25],…}}`. PHP-rendered KPI cards updated; tbody stayed a single Loading row; pagination stayed empty; no `pageerror`. `renderRows` called module-scope `applyCounts(data)` which read `$root` only declared inside `tasksWorkspace()`. Under `'use strict'` that is a `ReferenceError`. jQuery 3 Deferred `exceptionHook` logs a warning, not a pageerror, so `.done` aborted before `$tbody.empty()`. The second POST `{success:false,data:{state:'FINISHED'}}` is the sync poll, not a tasks failure.

## Not run (by design)

- Live Codeable API
- WordPress admin against a real site
- Destructive migrations / table truncates
- Auth-token decrypt / credential extraction
- App XHR / undocumented endpoint probing for comments

## Screenshots

Synthetic HTML fixtures in `tests/fixtures/ui/` and production-JS integration fixtures in `tests/fixtures/integration/` (banners: **SYNTHETIC FIXTURE** / **INTEGRATION FIXTURE**). Captures:

- `docs/screenshots/fixture-tasks-workspace.png`
- `docs/screenshots/fixture-clients-workspace.png`
- `docs/screenshots/fixture-stats-empty.png`
- `docs/screenshots/fixture-opportunities.png`
- `docs/screenshots/fixture-tasks-renderer-js.png` (production `wpcable-workspace.js`, mocked WP ajax — not live Codeable)
- `docs/screenshots/fixture-estimate-js.png` (production Estimate formulas, fee_type=client)

These are not live workrooms and do not contain real client names.
