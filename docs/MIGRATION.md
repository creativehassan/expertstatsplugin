# Migration and rollback — Expert Stats 0.4.8

This release stays inside the existing plugin directory and main file (`wp-codeable.php`). It is a patch of 0.4.7. Schema stays **0.0.6**. Overlay onto `expertstatsplugin-master/`. Do not start bulk until one project shows **Load more** or a verified next Link.

## 0.4.8 behavior

- Maps live `is_private` for visibility; keeps `is_contractors_only` distinct.
- Shows sanitized rejected Link host/path/paging in the project drawer (no tokens/bodies). Does **not** follow a guessed path such as `/users/me/tasks/{id}/comments`.
- Author: nested `user` if present; otherwise `user_id` plus a local account/client name only when that id is already stored. No extra Codeable GETs.
- Overlay `expertstatsplugin-0.4.8-hybrid.zip` (root `expertstatsplugin-master/wp-codeable.php`). Hard-refresh admin assets (file versions are `0.4.8`). Re-click **Load from Codeable** on 163186; the drawer should now show sanitized host/path/paging of the rejected next Link. Capture that route before any allowlist change.
- Rollback: overlay 0.4.7. Cached rows remain. Re-click **Load from Codeable** on 163186 to refresh mapping and the Link diagnostic.

# 0.4.7 notes

This release stays inside the existing plugin directory and main file (`wp-codeable.php`). It is a patch of 0.4.6, not a separate app. Schema stays **0.0.6** (no new columns). A new option `wpcable_history_backfill` stores the job cursor (task ids and counts only — no tokens, no comment bodies).

## Stages on this branch

1. **0.4.4** — Stats: one overview + one import-health; page overflow CSS; ZIP root `expertstatsplugin-master/`.
2. **0.4.5** — Opt-in documented `GET /tasks/{id}` + `GET /tasks/{id}/comments`.
3. **0.4.6** — Live mapping/paging fix: 50-item pages, Link replay onto `api.codeable.io`, keys-only shape, PKT DateTime, Load more. Two WP ZIP uploads of 0.4.6 failed Cloudflare 520 (archive too heavy); live site stayed on 0.4.5.
4. **0.4.7** — Resumable bulk comments backfill + lean production ZIP. Live overlay succeeded.
5. **0.4.8** — `is_private` mapping, local `user_id` author, sanitized rejected-Link diagnostics (this ZIP). Bulk still unstarted.

## What changes in the database

Schema remains **0.0.6**. No new columns. Existing notes, flags, clients, amounts, transactions, and settings are left in place. There is no truncate. Failed/401/403/429 reads do **not** wipe a previously stored description/comments row.

New options (safe to delete on rollback):

- `wpcable_history_backfill` — persistent cursor / progress
- `wpcable_history_backfill_lock` — tick mutex

## Behavioral changes (not data)

- Settings adds **Comments backfill (read-only)**. Start snapshots every imported `task_id` for the connected Codeable account and processes them with bounded WP-Cron ticks (default 8 GETs, 4 projects). Pause/resume keep the cursor. Already-complete caches for this account are skipped.
- Same documented GETs as the project-drawer Load buttons. One comments GET per call; the tick repeats **Load more** until an evidence-backed terminal status (X-Total-Count, 204, short last page, X-Page-Count) or a stop (duplicate IDs, repeated query, unverified Link, page cap). A 50-item page is **not** complete.
- 401 pauses the whole job. 403 is **denied**, distinct from empty (204) and complete. 429 uses `Retry-After` or exponential backoff. 5xx retries then records an error. Refresh queue / lock skips a backfill tick (no overlap).
- Default WP-Cron only runs on site traffic. If `DISABLE_WP_CRON` is true, a system cron must hit `wp-cron.php` or use **Run one batch now**. The browser does not need to stay on Settings once WP-Cron is firing.
- Comment timestamps always display in PKT (`Asia/Karachi`) even if the site timezone is empty. Author names and public/private stay unknown until live keys-only diagnostics show those fields — this plugin does not invent them.
- Drawer Load / Load more, Stats, and Estimate are unchanged.
- Production ZIP omits `tests/`, `docs/screenshots/`, `.git`, `.github`, `.cursor`, `node_modules`, and nested zip files. Plugin PHP/JS/CSS, CHANGELOG, and `docs/*.md` remain.

## Packaging (required ZIP root)

All installable ZIPs MUST unpack to **`expertstatsplugin-master/wp-codeable.php`**. Version belongs in the plugin header and the ZIP **filename** only (`expertstatsplugin-0.4.7-hybrid.zip`). Never name the archive root `expertstatsplugin-0.4.7/`.

Canonical command:

```
./scripts/package-zip.sh
./scripts/package-zip.sh /path/to/expertstatsplugin-0.4.7-hybrid.zip
```

`tests/test-package-zip.php` (via `php tests/run.php`) builds a temp archive and asserts that root plus lean exclusions. If a versioned duplicate was already uploaded, deactivate it in Plugins and delete the **folder on disk**. Do **not** use the WordPress plugin uninstaller / Delete on that duplicate — `uninstall.php` drops the **shared** `codeable_*` tables and options used by the active install.

## Install / start / verify

1. Keep the folder name `expertstatsplugin-master` so WordPress treats this as an update of the same plugin.
2. Overlay `expertstatsplugin-0.4.7-hybrid.zip` (root `expertstatsplugin-master/wp-codeable.php`). This ZIP is lean so it should clear Cloudflare 520 that blocked 0.4.6.
3. Hard-refresh admin assets (file versions are `0.4.7`).
4. Open **Codeable Stats → Settings**. Confirm Stats / Estimate / Projects still load.
5. Small pagination validation (one project, e.g. 163186): **Load from Codeable**, then **Load more** once if offered. Expect appended IDs, PKT times, no invented authors.
6. **Start comments backfill**. Optionally **Run one batch now** once. Confirm state moves from `running` and comment counts on a known project go past 50 if more pages exist. Leave the browser; WP-Cron continues if the site has traffic or system cron.
7. Pause / Resume from Settings. Do not start Refresh at the same time (backfill skips those ticks).

Do **not** Plugins → Delete a leftover `expertstatsplugin-0.4.3/` or other versioned folder.

## Rollback

Overlay the previous **0.4.6** ZIP (`expertstatsplugin-0.4.6-hybrid.zip`, git `4173768`) or the live **0.4.5** ZIP onto `expertstatsplugin-master/`. Schema 0.0.6 is compatible. Delete options `wpcable_history_backfill` and `wpcable_history_backfill_lock` if you want a clean cursor. Cached description/comments rows stay. 0.4.6 comment DateTime used the WP timezone; 0.4.7 forces PKT.

## Known limitations

- Cloud agents do not call Codeable. First live proof is on the site after this ZIP is overlayed.
- Author / visibility mapping remains unknown until a live keys-only shape is captured with working credentials. Do not infer names from earlier screenshots.
- Default WP-Cron is visitor-triggered. Quiet sites need system cron to `wp-cron.php` or **Run one batch now**.
- Full page with no terminal signal stays **partial**. `/v1` Links are not followed.
- Attachments and reviews endpoints are not called.
- Apiary lastUpdated **2015-03-02** — production may differ; see `docs/APIARY-CODEABLE.md`.
