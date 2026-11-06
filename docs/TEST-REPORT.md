# Test report — Expert Stats 0.4.1 QA patch

Run: `php tests/run.php`  
Environment: PHP 8.3 CLI, SQLite in-memory `$wpdb` stub  
Network: none. No Codeable HTTP, no credentials, no WordPress site.

## Results

**290 passed, 0 failed.**

| Suite | What it proves |
| --- | --- |
| PHP 8 profile / avatar / invalid API guards | Nested avatar arrays, string avatars, and a non-array `wpcable_account_details` option do not TypeError. Instrumented `record()` still stores a real `succeeded_at` only on success. |
| Server-side pagination / filters / grouping | Page size 25, disjoint page IDs, published/lost/preferred/has_notes filters, client grouping. |
| No-data financial / date handling | Missing transactions stay unavailable, not `$0.00` / 1970. |
| Estimate math regression | PERT hours and fee factors unchanged. |
| Opportunity provenance | Cues cite source task IDs; chat/SEO/auto-send stay unsupported. |
| 0.4.1 QA | Y2K38 / future timestamps do not format or yield negative ages; sentinel activity does not ground EBR scores; health snapshot is cached vs no-recorded-attempt vs in-progress without inventing `succeeded_at`; filter KPI SQL matches table filters; `maybe_refresh` does not enqueue; sync lock rejects a second acquire; avatar CSS max 32×32; workroom URL is `https://app.codeable.io/tasks/{id}/workroom`; comments/description marked unavailable. |

## PHP lint

`php -l` on every plugin PHP file: no syntax errors.

## Not run (by design)

- Live Codeable API
- WordPress admin against a real site
- Destructive migrations / table truncates
- Auth-token decrypt / credential extraction
- App XHR / undocumented endpoint probing for comments

## Screenshots

Synthetic HTML fixtures in `tests/fixtures/ui/` (banner: **SYNTHETIC FIXTURE**). Captures:

- `docs/screenshots/fixture-tasks-workspace.png`
- `docs/screenshots/fixture-clients-workspace.png`
- `docs/screenshots/fixture-stats-empty.png`
- `docs/screenshots/fixture-opportunities.png`

These are not live workrooms and do not contain real client names.
