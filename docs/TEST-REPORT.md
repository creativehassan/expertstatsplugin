# Test report — Expert Stats 0.4.6

Run: `php tests/run.php`  
Environment: PHP 8 CLI, SQLite in-memory `$wpdb` stub, Node jsdom + jQuery 3, Chrome headless for overflow screenshots  
Network: no Codeable HTTP, no credentials, no WordPress site.

## Stages

| Stage | Version | What shipped |
| --- | --- | --- |
| 1 | 0.4.4 | Stats: one overview + one import-health; overflow CSS; ZIP root `expertstatsplugin-master/`. |
| 2 | 0.4.5 | Documented read-only `GET /tasks/{id}` + comments Load. Live: description + 50 comments on task 163186; Link not followed; authors/visibility unknown; raw ISO times. |
| 3 | 0.4.6 | Mapping/paging from that live evidence: 50-item pages, Link replay onto `.io`, keys-only shape, PKT DateTime, Load more. |

Live 1280 resize was **not** re-run for 0.4.6 and is not claimed.

## Results

`php tests/run.php` → **563 passed, 0 failed**.

| Suite | What it proves |
| --- | --- |
| Prior suites | Unchanged: pagination, estimate PERT, 0.4.4 Stats overflow fixtures, 0.4.5 403/no-overwrite/account-scope. |
| 0.4.6 QA | 50 comments despite `limit=20` stay partial (one GET, no offset loop); `.com` `/tasks/{id}/comments?page=2` replays on `.io` via Load more; `/v1` and arbitrary hosts are not followed; duplicate IDs do not inflate cache; missing `user` is not a fake author; `user.full_name` maps when present; ISO `2021-12-17T06:00:00Z` displays 11:00 PKT via DateTime; shape lists key names and omits body text and secrets. |
| Production JS | Load button still posts local `wpcable_load_task_history`. |

## Not run (by design)

- Live Codeable API from this cloud agent
- Credential handling
- Live 1280 viewport (unavailable this round)
- Bulk backfill
- Attachments / reviews
