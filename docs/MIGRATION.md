# Migration and rollback — Expert Stats 0.4.2

This release stays inside the existing plugin directory and main file (`wp-codeable.php`). It is a patch of 0.4.1, not a separate app.

## What changes in the database

**Nothing.** Schema version stays `0.0.4`. No columns added or dropped. Existing task notes, flags, clients, amounts, transactions, and settings are left in place. There is no truncate and no destructive data migration.

Options that may already exist:

- `wpcable_import_health` — still attempt/success metadata only. 0.4.1 display treats empty rows as “cached locally — no recorded attempt” or “no recorded attempt” instead of inventing success.
- `wpcable_api_queue` — if a Refresh was interrupted, it is still resumed on the next admin page that loads sync JS. New queues are **not** created by page views or hourly WP-Cron.
- `wpcable_sync_lock` — short-lived lock so two tabs cannot process the same queue item. Safe to delete; it expires on its own.

## Behavioral changes (not data)

- Invalid / future / Y2K38 (`2147483647`) `last_activity` values are displayed as missing. Rows are not rewritten.
- Explicit **Refresh** is the only trigger that prepares a new Codeable GET queue. The first queue step is still preexisting `task:lost` → `mark_tasks_lost()` (local heuristic). Ordinary navigation does not run it.
- Hourly `wpcable_cronjob` remains registered but no longer enqueues a new queue.
- Tasks workspace AJAX renderer scopes KPI updates so a valid `wpcable_query_tasks` JSON envelope paints rows (0.4.1 left Loading after `applyCounts` threw).
- Formatted datetimes use `wp_date` and append the WordPress timezone abbreviation. PHP default timezone is not changed.

## Install

1. Keep the folder name `expertstatsplugin-master` (or your current plugin directory) so WordPress treats this as an update of the same plugin.
2. Replace the plugin files with the 0.4.2 ZIP, keeping `wp-codeable.php` as the bootstrap file.
3. Open **Codeable Stats**. Existing menu slugs stay:
   - `codeable_transcactions_stats` (Stats)
   - `codeable_tasks` (Tasks / projects workspace)
   - `codeable_estimate` (PERT estimate — math unchanged)
   - `codeable_settings` (Settings)
   - `codeable_clients`
   - `codeable_opportunities`

Hard-refresh the admin assets if the browser still shows 0.4.1 JS (file versions are `0.4.2`).

## Rollback

1. Replace the plugin folder with the 0.4.1 ZIP (or earlier).
2. Optionally delete option `wpcable_sync_lock`.
3. Do **not** truncate tables to roll back — that would delete local notes.

Uninstall (delete plugin from WP admin) still removes options/tables via `uninstall.php`. Do not use uninstall as a rollback.

## Compatibility

- Estimate page formulas are unchanged.
- Settings options `wpcable_rate`, `wpcable_fee_type`, `wpcable_cancel_after_days`, `wpcable_tasks_stop_at_page` are unchanged.
- Auth remains the existing encrypted `wpcable_auth_token` flow. No new remote endpoints. Codeable access is still read-only from this plugin.
- Local notes/flags continue to save through `wpcable_update_task` with a nonce and `manage_options`.
- Project description and workroom comments are **not** imported (see TEST-REPORT / PR notes). The drawer shows an honest unavailable panel and the existing workroom link.
