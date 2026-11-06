# Codeable public API docs (Apiary)

Source (read-only documentation, no live API calls from the cloud agent):

- HTML docs: https://codeable.docs.apiary.io/
- Raw API Blueprint: https://codeable.docs.apiary.io/api-description-document (`codeable.apib`)
- Same blueprint: https://jsapi.apiary.io/apis/codeable.apib
- Blueprint `HOST:` line: `https://api.codeable.com/`
- Link-header examples in the same file use `https://api.codeable.io`
- Blueprint **lastUpdated: 2015-03-02**. This is **not** a guarantee of current production behavior.
- Documented **current version header**: `X-API-Version: 2014-01-17` (text also says current version is **2014-02-14**). Changelog section is empty.
- This plugin’s existing client calls `https://api.codeable.io/` (`classes/api_calls.php`) and does **not** switch to `api.codeable.com`. Documented GETs use the same host and Bearer token, with `redirection => 0` so a 3xx cannot leak the token cross-domain.

## Documented read paths used in 0.4.5

### `GET /tasks/{id}` — Individual Tasks

Blueprint section: `### Individual Tasks [/tasks/{id}]` → `#### Display a task [GET]`.

- Auth: `Authorization: Bearer …`
- Copy: “Returns full task to clients (task owner) and selected contractor (task owner). Returns public task attributes for contractors.”
- 200 example body includes `"description": "Website designer to complete custom design…"`
- 403: “Clients who are not task owners.”
- No query parameters documented on this GET.

This plugin maps `description` (and fallback keys already used by the list mapper). It does not replace the local title/row identity.

### `GET /tasks/{id}/comments` — List comments

Blueprint section: `### Comments [/tasks/{id}/comments]` → `#### List comments for a particular task [GET]`.

- Auth: `Authorization: Bearer …`
- Visibility copy: “Comments with private set to *true* will only be seen by the Client (task owner) and assigned Contractor.”
- **Parameters (as written):** `limit` (required) and `offset` (required).
- **Response headers** document `Link` with `?page=2` / `?page=1`, plus `X-Total-Count`. One `rel="next"` example uses `/v1/tasks/…/comments?page=3`.
- 200 list example items: `id`, `text`, `user{id,full_name,avatar}`, `edited`, `published_at`. The list example does **not** include `private`.
- 204 / 401 / 403 / 404.

**How 0.4.5 handles the limit/offset vs Link `page` contradiction:** send only documented `limit` + `offset`. Inspect returned `Link` as metadata. Do not probe `?page=` as an alternate route. Treat `rel=next` as “another page exists” and continue with `offset += limit` **only** when the Link host is `api.codeable.io` and the path is exactly `/tasks/{id}/comments`. Other hosts and `/v1` prefixes are not followed. A full page with no next Link is **partial**, not complete.

**Visibility:** missing `private` → `unknown`, not public. `private: true` → private. `private: false` → public. Never claim all private comments are available after a 403.

### `GET /tasks/{id}/comments/{comment_id}` — Individual comment

Allowlisted on the HTTP helper (documented GET). **Not** called automatically by the per-project loader (list GET is the load path).

## List payloads (description is not uniform)

| Blueprint resource | GET sample includes `description`? |
| --- | --- |
| `/users/me/tasks/suggestions` | Yes |
| `/users/me/tasks/inquiring` | No |
| `/users/me/tasks/archive` | No |
| `/tasks` public collection | Yes |

The list importer (`users/me/tasks/{filter}`) still maps description/comments only when those keys are already on the list object. Missing on a saved row is unverified, not empty.

## Mutations (documented; this plugin stays read-only)

- `POST /tasks/{id}/comments`, `PUT /tasks/{id}/comments/{comment_id}`, `POST /tasks`, `PUT /tasks/{id}`, etc.

Do not call these from Expert Stats. No comments composer.

## Out of scope this phase

- Attachments endpoints
- Reviews endpoints
- Public `GET /tasks` collection
- Switching host to `api.codeable.com`
- Undocumented singular `/task/{id}` (third-party Chrome extension)

## What 0.4.5 does with this

- Adds opt-in **Load from Codeable (read-only)** for one imported `task_id` at a time.
- Uses existing authorized account abstraction. Does not mint or extract credentials.
- Caches successful bodies locally with account stamp and provenance.
- Respects 403; does not overwrite good cache on errors.
- Does not auto-backfill on page views.
