# Test report — Expert Stats 0.4.7

Run: `php tests/run.php`  
Environment: PHP 8 CLI, SQLite in-memory `$wpdb` stub, Node jsdom + jQuery 3, Chrome headless for overflow screenshots  
Network: no Codeable HTTP, no credentials, no WordPress site.

## Stages

| Stage | Version | What shipped |
| --- | --- | --- |
| 1 | 0.4.4 | Stats: one overview + one import-health; overflow CSS; ZIP root `expertstatsplugin-master/`. |
| 2 | 0.4.5 | Documented read-only `GET /tasks/{id}` + comments Load. Live: description + 50 comments on task 163186. |
| 3 | 0.4.6 | Mapping/paging from that live evidence: 50-item pages, Link replay onto `.io`, keys-only shape, PKT DateTime, Load more. Live ZIP uploads hit Cloudflare 520; site stayed 0.4.5. |
| 4 | 0.4.7 | Resumable bulk comments backfill via WP-Cron; PKT even if WP timezone is empty; lean production ZIP. |

Live Codeable calls were **not** made from this cloud agent and are not claimed.

## Results

`php tests/run.php` → **639 passed, 0 failed**.

Lean production ZIP `expertstatsplugin-0.4.7-hybrid.zip` is **890K** (0.4.6 was 5.6M) because `tests/` and `docs/screenshots/` are omitted. Archive root remains `expertstatsplugin-master/wp-codeable.php`.

| Suite | What it proves |
| --- | --- |
| Prior suites | Unchanged: pagination, estimate PERT, 0.4.4 Stats overflow fixtures, 0.4.5 403/no-overwrite/account-scope, 0.4.6 50-item pages / Link replay / shape. |
| 0.4.7 QA | Start does not HTTP; ticks page past 50 (155 unique ids, evidence-backed complete); multi-project pause/resume does not refetch a finished project; duplicate-ID and repeated-query pages stop without looping or inflating ids; 401 pauses and preserves prior cache; 403 denied ≠ 204 complete; 429 Retry-After + skip while waiting; lock/Refresh overlap skip; empty WP timezone still PKT; lean ZIP omits tests/screenshots; progress JSON has no bodies/tokens. |
| Production JS | Load button still posts local `wpcable_load_task_history`. Stats/Estimate templates unchanged. |

## Not run (by design)

- Live Codeable API from this cloud agent
- Credential handling
- Live 1280 viewport
- Attachments / reviews
