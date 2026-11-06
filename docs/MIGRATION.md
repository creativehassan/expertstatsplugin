# Migration and rollback — Expert Stats 0.4.0

This release stays inside the existing plugin directory and main file (`wp-codeable.php`). It is an update, not a separate app.

## What changes in the database

Schema version option `wpcable_transcactions_version` moves from `0.0.3` to `0.0.4`.

`dbDelta` adds indexes on `wp_codeable_tasks`:

- `KEY client_id (client_id)`
- `KEY state (state)`
- `KEY flag (flag)`
- `KEY last_activity (last_activity)`

No columns are dropped. Existing task notes, flags, clients, amounts, and transactions are left in place. There is no truncate and no destructive data migration.

A new option `wpcable_import_health` stores per-source attempt/success metadata. It does not invent success times for sources that never succeeded.

Upgrade runs on plugin activation and on `plugins_loaded` when the stored schema version differs, so replacing the plugin folder is enough — you do not have to deactivate/reactivate unless indexes fail to appear.

## Install

1. Keep the folder name `expertstatsplugin-master` (or your current plugin directory) so WordPress treats this as an update of the same plugin.
2. Replace the plugin files, keeping `wp-codeable.php` as the bootstrap file.
3. Open **Codeable Stats**. Existing menu slugs stay:
   - `codeable_transcactions_stats` (Stats)
   - `codeable_tasks` (Tasks / projects workspace)
   - `codeable_estimate` (PERT estimate — math unchanged)
   - `codeable_settings` (Settings)
4. New pages (same plugin menu):
   - `codeable_clients`
   - `codeable_opportunities`

## Rollback

1. Replace the plugin folder with the previous ZIP (0.3.1 / repaired baseline).
2. Indexes on `codeable_tasks` can remain; older code ignores them.
3. Optionally delete option `wpcable_import_health`.
4. Optionally set `wpcable_transcactions_version` back to `0.0.3`.
5. Do **not** truncate tables to roll back — that would delete local notes.

Uninstall (delete plugin from WP admin) still removes options/tables via `uninstall.php`. That path is unchanged in intent and remains destructive. Do not use uninstall as a rollback.

## Compatibility

- Estimate page formulas are unchanged (`classes/estimate.php` matches the existing JS).
- Settings options `wpcable_rate`, `wpcable_fee_type`, `wpcable_cancel_after_days`, `wpcable_tasks_stop_at_page` are unchanged.
- Auth remains the existing encrypted `wpcable_auth_token` flow. No new remote endpoints. Codeable access is still read-only from this plugin.
- Local notes/flags continue to save through `wpcable_update_task` with a nonce and `manage_options`.
