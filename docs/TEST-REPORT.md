# Test report — Expert Stats 0.4.8

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
| 5 | 0.4.8 | Live `is_private` visibility + distinct `is_contractors_only`; local `user_id` author only; sanitized rejected-Link host/path/paging in the drawer. Allowlist not broadened. Bulk stays idle. |

Live Codeable calls were **not** made from this cloud agent and are not claimed.

## Results

`php tests/run.php` → **695 passed, 0 failed**.

Lean production ZIP `expertstatsplugin-0.4.8-hybrid.zip` is **893K** because `tests/` and `docs/screenshots/` are omitted. Archive root remains `expertstatsplugin-master/wp-codeable.php`.

| Suite | What it proves |
| --- | --- |
| Prior suites | Unchanged: pagination, estimate PERT, 0.4.4 Stats overflow fixtures, 0.4.5 403/no-overwrite/account-scope, 0.4.6 50-item pages / Link replay / shape, 0.4.7 bulk idle unless started. |
| 0.4.8 QA | `is_private` true/false maps private/public; missing stays unknown; `is_contractors_only` is not folded into visibility; no nested user keeps author empty; `user_id` maps only from stored account/client; no `GET /users/{id}`; relative Link is not replayed but path/paging are exposed; undocumented `/users/me/tasks/{id}/comments` stays `path_not_documented_comments` and is not requested; drawer `link_next` exposes host/path/`page=`/`replay_reason`; credential query key names only (values stripped, no userinfo); X-Records-Per-Page surfaced; missing X-Total-Count not invented complete; Load more hidden until path is verified; bulk job remains idle; JS renders rejected-Link diagnostic, `user_id`, and `contractors_only`. |
| Production JS | Load button still posts local `wpcable_load_task_history`. Stats/Estimate templates unchanged. |

## Not run (by design)

- Live Codeable API from this cloud agent
- Credential handling
- Live 1280 viewport
- Attachments / reviews
- Bulk comments backfill (queue stays unstarted until one-project pagination is proven)
