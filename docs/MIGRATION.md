# Migration and rollback — Expert Stats 0.4.5

This release stays inside the existing plugin directory and main file (`wp-codeable.php`). It is a patch of 0.4.4, not a separate app.

## Stages on this branch

1. **0.4.4** — Stats: one overview + one import-health; page overflow CSS; ZIP root `expertstatsplugin-master/`.
2. **0.4.5** — Opt-in documented `GET /tasks/{id}` + `GET /tasks/{id}/comments` (this ZIP).

## What changes in the database

Schema version moves **0.0.5 → 0.0.6** (additive `dbDelta`):

- `codeable_tasks.history_account_id` (Codeable account id that fetched the cache)
- `codeable_tasks.history_meta_json` (source time, route, HTTP, partial/complete, Link metadata)

0.0.5 description/comments columns from 0.4.3 remain. Existing notes, flags, clients, amounts, transactions, and settings are left in place. There is no truncate. Failed/403 reads do **not** wipe a previously stored description/comments row.

## Behavioral changes (not data)

- Project drawer adds **Load from Codeable (read-only)**. It is a local WordPress ajax POST (`wpcable_load_task_history`) gated by `manage_options` + nonce. The only remote calls are documented GETs on the existing working host `https://api.codeable.io/` using the already-authorized account. Redirects are disabled.
- Comments paging sends documented `limit` + `offset` only. Returned `Link` is inspected as metadata. `rel=next` is followed only as “there is another page”, still requested with `limit`/`offset` on the same host and path `/tasks/{id}/comments`. Other hosts (including documented `api.codeable.com`) and `/v1` prefixes are **not** followed.
- Completeness is never claimed unless X-Total-Count is met, the last page is short with no next Link, or 204 on the first page. A full page with no Link is **partial**.
- `private` absent on a comment → visibility **unknown**, not public.
- Cache is account-scoped. Connected as a different Codeable id: hide. Disconnected: show saved authorized-account cache. WP admin only.
- No auto bulk backfill on page views or hourly cron.
- Attachments and reviews endpoints are not called.

## Packaging (required ZIP root)

All installable ZIPs MUST unpack to **`expertstatsplugin-master/wp-codeable.php`**. Version belongs in the plugin header and the ZIP **filename** only (`expertstatsplugin-0.4.5-hybrid.zip`). Never name the archive root `expertstatsplugin-0.4.5/` (or any other versioned folder): WordPress will install a **second, inactive** plugin and leave the live `expertstatsplugin-master/` copy untouched.

Canonical command:

```
./scripts/package-zip.sh
./scripts/package-zip.sh /path/to/expertstatsplugin-0.4.5-hybrid.zip
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

Hard-refresh admin assets (file versions are `0.4.5`).

Do **not** Plugins → Delete a leftover `expertstatsplugin-0.4.3/` or `expertstatsplugin-0.4.4/` folder (`uninstall.php` drops shared tables). Overlay this ZIP onto `expertstatsplugin-master/`.

## First live validation (coordinator, one project)

Do **not** bulk-load. After deploy, with the existing connected Codeable account:

1. **Codeable Stats → Projects** (`codeable_tasks`).
2. Open **one already-imported** project (click the row so the drawer opens).
3. Click **Load from Codeable (read-only)**.
4. Expect two remote GETs only: `/tasks/{that task_id}` then `/tasks/{id}/comments?limit=20&offset=0` on `api.codeable.io`.
5. Description and comments should persist in the drawer after reload. Local notes are unchanged.

If 2015 Apiary docs differ from production, typical outcomes:

| HTTP | Meaning in this plugin | Cache |
| --- | --- | --- |
| 200 | Map `description`; map comments (`text`, `user.full_name`, `published_at`; `private` if present else **unknown**) | Saved |
| 204 | Empty comments list (complete only if first page) | Saved as present/empty when comments 204 |
| 401 | Token expired / not authorized | **Not overwritten** |
| 403 | Not owner and not assigned contractor (docs: full task / private comments denied) | **Not overwritten**; UI says 403, does not claim all private comments exist |
| 404 | Task or comments collection not found | **Not overwritten** |
| 5xx / network | Fetch failed | **Not overwritten** |
| 200 + full page, no `Link rel=next` | Partial — more comments may exist | Saved, **not** marked complete |
| `Link` to `api.codeable.com` or `/v1/tasks/…` | Not followed (token stays on `api.codeable.io`) | First page saved as partial |

No new credentials are collected. Do not run live API tests from this cloud agent.

## Rollback

1. Replace the plugin folder with the 0.4.4 ZIP (or earlier).
2. New 0.0.6 columns can remain unused; they are additive. Do **not** truncate tables to roll back — that would delete local notes.
3. 0.4.4 ZIP: `/opt/cursor/artifacts/expertstatsplugin-0.4.4-hybrid.zip` (keep it).

Uninstall (delete plugin from WP admin) still removes options/tables via `uninstall.php`. Do not use uninstall as a rollback.

## Compatibility

- Estimate page formulas are unchanged.
- Settings options `wpcable_rate`, `wpcable_fee_type`, `wpcable_cancel_after_days`, `wpcable_tasks_stop_at_page` are unchanged.
- Auth remains the existing encrypted `wpcable_auth_token` flow. Host stays `api.codeable.io` (documented HOST `api.codeable.com` is **not** used). Codeable access from this plugin is still read-only GETs.
- Local notes/flags continue to save through `wpcable_update_task` with a nonce and `manage_options`.
- List importer still uses `GET users/me/tasks/{filter}` and maps description/comments only when those keys are already on the list object.
