# Test report — Expert Stats 0.4.9

Run: `php tests/run.php`  
Environment: PHP 8 CLI, SQLite in-memory `$wpdb` stub, Node jsdom + jQuery 3, Chrome headless for overflow screenshots  
Network: no Codeable HTTP, no credentials, no WordPress site.

## Stages

| Stage | Version | What shipped |
| --- | --- | --- |
| 1 | 0.4.4 | Stats: one overview + one import-health; overflow CSS; ZIP root `expertstatsplugin-master/`. |
| 2 | 0.4.5 | Documented read-only `GET /tasks/{id}` + comments Load. Live: description + 50 comments on task 163186. |
| 3 | 0.4.6 | Mapping/paging from that live evidence: 50-item pages, Link replay onto `.io`, keys-only shape, PKT DateTime, Load more. Live ZIP uploads hit Cloudflare 520; site stayed 0.4.5. |
| 4 | 0.4.7 | Resumable bulk comments backfill via WP-Cron; PKT even if WP timezone is empty; lean production ZIP. Live overlay succeeded (2137 rows, PKT dates). |
| 5 | 0.4.8 | Live `is_private` visibility + distinct `is_contractors_only`; local `user_id` author only; sanitized rejected-Link host/path/paging in the drawer. Live capture: root-relative `/tasks/163186/comments?page=2`, 50 of 674. |
| 6 | 0.4.9 | Resolve that root-relative next onto `api.codeable.io` with strict integer page; 14-page/674 fixtures for Load more and bulk; unsafe relative forms rejected. Bulk idle until started. |

Live Codeable calls were **not** made from this cloud agent and are not claimed.

## Results

`php tests/run.php` → **791 passed, 0 failed**.

Lean production ZIP `expertstatsplugin-0.4.9-hybrid.zip` is **895K** because `tests/` and `docs/screenshots/` are omitted. Archive root remains `expertstatsplugin-master/wp-codeable.php`.

| Suite | What it proves |
| --- | --- |
| Prior suites | Unchanged: pagination, estimate PERT, 0.4.4 Stats overflow, 0.4.5 403/account-scope, 0.4.6 50-item pages / absolute Link replay, 0.4.7 bulk idle unless started, 0.4.8 is_private + diagnostics. |
| 0.4.9 QA | Documented `/tasks/{id}/comments?page=2` root-relative resolves onto `api.codeable.io` with only integer `page`; `//evil`, `//api.codeable.io`, userinfo, fragments, `..` / percent-encoding, path-relative, mismatched ids, other hosts, `/v1`, `/users/me/tasks`, missing page all rejected; first GET stays limit/offset; Load more page=2 does not resend offset and yields 100 unique ids; 14 pages / 674 unique complete from X-Total-Count; bulk same 674 complete; duplicate-ID and repeated page=2 stop without looping; bulk cron stays idle unless started. |
| Production JS | Load button still posts local `wpcable_load_task_history`. Stats/Estimate templates unchanged. |

## Not run (by design)

- Live Codeable API from this cloud agent
- Credential handling
- Live 1280 viewport
- Attachments / reviews
- Starting bulk on the live site (queue stays idle until 163186 Load more is proven)
