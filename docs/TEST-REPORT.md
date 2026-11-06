# Test report — Expert Stats 0.4.0 hybrid workspace

Run: `php tests/run.php`  
Environment: PHP 8.3 CLI, SQLite in-memory `$wpdb` stub  
Network: none. No Codeable HTTP, no credentials, no WordPress site.

## Results

**178 passed, 0 failed.**

| Suite | What it proves |
| --- | --- |
| PHP 8 profile / avatar / invalid API guards | Nested avatar arrays, string avatars, and a non-array `wpcable_account_details` option do not TypeError. Missing fields stay empty. Import health never invents `succeeded_at`. |
| Server-side pagination / filters / grouping | Page size 25, page 2 disjoint IDs, published/lost/preferred/has_notes filters, `client_id` cluster, `group_by_client`, KPI counts computed from DB (not hardcoded 2137/911/…). |
| No-data financial / date handling | Zero transaction rows → unavailable, not `$0.00`. `wpcable_date()` blanks null/0/`0000-00-00`/epoch. 1970 ranges rejected. Legacy client revenue table explains “not $0”. |
| Estimate math regression | PERT hours `(O+4M+P)/6` and `(O+2M+3P)/6`; `full` / `client` / `none` fee factors match `templates/admin-estimate.php`. |
| Opportunity provenance | Cues cite source `task_id`s, disclaimer “not a confirmed client need”, referral is low confidence, chat/SEO/auto-send stay on the unsupported list. Names are fixture-only. |

## PHP lint

`php -l` on every plugin PHP file: no syntax errors.

## Not run (by design)

- Live Codeable API
- WordPress admin against a real site
- Destructive migrations / table truncates
- Auth-token decrypt / credential extraction

## Screenshots

Synthetic HTML fixtures in `tests/fixtures/ui/` (banner: **SYNTHETIC FIXTURE**). Captures:

- `docs/screenshots/fixture-tasks-workspace.png`
- `docs/screenshots/fixture-clients-workspace.png`
- `docs/screenshots/fixture-stats-empty.png`
- `docs/screenshots/fixture-opportunities.png`

These are not live workrooms and do not contain real client names.
