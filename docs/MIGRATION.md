# Migration and rollback — Expert Stats 0.4.3

This release stays inside the existing plugin directory and main file (`wp-codeable.php`). It is a patch of 0.4.2, not a separate app.

## What changes in the database

Schema version moves **0.0.4 → 0.0.5** (additive `dbDelta` / SQLite test schema only):

- `codeable_tasks.description_text` (nullable)
- `codeable_tasks.description_present`
- `codeable_tasks.description_key`
- `codeable_tasks.comments_json` (nullable)
- `codeable_tasks.comments_present`
- `codeable_tasks.comments_complete`
- `codeable_tasks.comments_source`

Existing notes, flags, clients, amounts, transactions, and settings are left in place. There is no truncate. Missing keys on a later list import do **not** wipe a previously stored description/comments row.

New option (non-secret derived snapshot): `wpcable_opportunity_snapshot`. Deleted only by **Clear all data**.

## Behavioral changes (not data)

- Screens no longer `exit` when the auth token is absent. Saved profile/projects/notes remain visible. Refresh is refused with `DISCONNECTED`.
- **Log out** deletes the token only. **Clear all data** still truncates tables — the Settings copy now says so.
- Description/comments UI renders genuine stored payload fields, or an honest missing/empty state. No new Codeable HTTP routes.
- Overview, Projects, Clients, Opportunities, Estimate, and Settings share chrome and fluid width. Estimate formulas and Settings option keys are unchanged.

## Packaging (required ZIP root)

All installable ZIPs MUST unpack to **`expertstatsplugin-master/wp-codeable.php`**. Version belongs in the plugin header and the ZIP **filename** only (`expertstatsplugin-0.4.3-hybrid.zip`). Never name the archive root `expertstatsplugin-0.4.3/` (or any other versioned folder): WordPress will install a **second, inactive** plugin and leave the live `expertstatsplugin-master/` copy untouched.

Canonical command:

```
./scripts/package-zip.sh
./scripts/package-zip.sh /path/to/expertstatsplugin-0.4.3-hybrid.zip
```

`tests/test-package-zip.php` (via `php tests/run.php`) builds a temp archive and asserts that root. If a versioned duplicate was already uploaded, deactivate it in Plugins and delete the **folder on disk** (or upload a corrected ZIP over the intended `expertstatsplugin-master/` directory). Do **not** use the WordPress plugin uninstaller / Delete on that duplicate — `uninstall.php` drops the **shared** `codeable_*` tables and options used by the active install.

## Install

1. Keep the folder name `expertstatsplugin-master` so WordPress treats this as an update of the same plugin.
2. Replace the plugin files with a ZIP whose root is `expertstatsplugin-master/wp-codeable.php`.
3. Open **Codeable Stats**. Existing menu slugs stay:
   - `codeable_transcactions_stats` (Overview / Stats)
   - `codeable_tasks` (Projects)
   - `codeable_estimate` (PERT estimate — math unchanged)
   - `codeable_settings` (Settings)
   - `codeable_clients`
   - `codeable_opportunities`

Hard-refresh admin assets (file versions are `0.4.3`).

## Rollback

1. Replace the plugin folder with the 0.4.2 ZIP (or earlier).
2. New 0.0.5 columns can remain unused; they are additive. Do **not** truncate tables to roll back — that would delete local notes.
3. Optionally delete option `wpcable_opportunity_snapshot`.

Uninstall (delete plugin from WP admin) still removes options/tables via `uninstall.php`. Do not use uninstall as a rollback.

## Compatibility

- Estimate page formulas are unchanged.
- Settings options `wpcable_rate`, `wpcable_fee_type`, `wpcable_cancel_after_days`, `wpcable_tasks_stop_at_page` are unchanged.
- Auth remains the existing encrypted `wpcable_auth_token` flow. No new remote endpoints. Codeable access is still read-only from this plugin.
- Local notes/flags continue to save through `wpcable_update_task` with a nonce and `manage_options`.
- Project description and workroom comments are imported **only when those keys are already on the existing task-list payload**. Dedicated `GET /task/{id}` and `/comments` routes are not added (unofficial third-party usage is not an official contract). Missing vs empty vs stored are distinct. Pagination and full public/private coverage remain unverified for the list endpoint.
