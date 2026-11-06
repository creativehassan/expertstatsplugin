# Codeable public API docs (Apiary)

Source (read-only documentation, no live API calls from this plugin release):

- HTML docs: https://codeable.docs.apiary.io/
- Raw API Blueprint: https://codeable.docs.apiary.io/api-description-document (`codeable.apib`)
- Same blueprint: https://jsapi.apiary.io/apis/codeable.apib
- Blueprint `HOST:` line: `https://api.codeable.com/`
- Link-header examples in the same file use `https://api.codeable.io`
- This plugin’s existing client calls `https://api.codeable.io/` (`classes/api_calls.php`)

Documented **current version header**: `X-API-Version: 2014-01-17` (text also says current version is **2014-02-14**). Changelog section is empty.

This 0.4.4 patch does **not** add new HTTP routes. The existing list importer stays `GET users/me/tasks/{filter}` and `users/me/hidden_tasks/`. Dedicated description/comments retrieval waits until pagination and visibility are confirmed (blueprint contradictions below). Coordinator extract may supersede this note.

## Documented read paths for project description

### `GET /tasks/{id}` — Individual Tasks

Blueprint section: `### Individual Tasks [/tasks/{id}]` → `#### Display a task [GET]`.

- Auth: `Authorization: Bearer …`
- Copy: “Returns full task to clients (task owner) and selected contractor (task owner). Returns public task attributes for contractors.”
- 200 example body includes `"description": "Website designer to complete custom design…"`
- 403: “Clients who are not task owners.”
- No query parameters documented on this GET.

### List payloads (description is not uniform)

| Blueprint resource | GET sample includes `description`? |
| --- | --- |
| `/users/me/tasks/suggestions` | Yes (`"description": "Website designer…"`) plus `"comments_count": 10` |
| `/users/me/tasks/inquiring` | No — sample is `id`, `title`, `price`, `last_event`, `state` |
| `/users/me/tasks/in_progress` | No (same shape as inquiring) |
| `/users/me/tasks/requesting_estimate` | No |
| `/users/me/tasks/archive` | No |
| `/users/me/tasks/favourites` | No |
| `/tasks` public collection | Yes, each listed task has `description` and `comments_count` |

This plugin’s `tasks_page()` filters (`pending`, `active`, `archived`, `preferred`, `hidden_tasks`) are **not named** in the blueprint’s `/users/me/tasks/…` list. Do not treat a missing `description` on one saved row as proof the field is absent from every list.

### `GET /tasks` public list

`### Tasks Collection [/tasks]` → `#### List all public Tasks [GET]`.

Parameters: `page` (required), optional `category`, `budget`, `urgency`, `special`. Pagination headers: `Link`, `X-Total-Count`, `X-Page-Count`, `X-Records-Per-Page` (20 per page in the example).

## Documented read paths for comments

### `GET /tasks/{id}/comments` — List comments

Blueprint section: `### Comments [/tasks/{id}/comments]` → `#### List comments for a particular task [GET]`.

- Auth: `Authorization: Bearer …`
- Visibility copy (resource intro): “Comments with private set to *true* will only be seen by the Client (task owner) and assigned Contractor.”
- **Parameters (as written):** `limit` (required, number, example `10`) “Return *n* records”; `offset` (required, number, example `20`) “Skip *n* records”.
- **Response headers** document `Link` with `?page=2` / `?page=1` / `?page=3`, plus `X-Total-Count`, `X-Page-Count`, `X-Records-Per-Page: 20`. One `rel="next"` example uses `/v1/tasks/…/comments?page=3` while the others omit `/v1`.
- 200 example items: `id`, `text`, `user`, `edited`, `published_at`. The list example does **not** include a `private` field.
- 204 / 401 / 403 (“Client who doesn't own task.”) / 404.

**Not confirmed (do not guess a client):** whether callers should send `limit`+`offset`, `page`, or both; whether list items include `private`; whether `/v1` is a real prefix.

`is_contractors_only` does **not** appear in this blueprint (that name was from a third-party Chrome extension, not this document).

### `GET /tasks/{id}/comments/{comment_id}` — Individual comment

200 example: `id`, `text`, `edited`, `published_at` (no `private` in the example). 403 copy distinguishes “comment is public” vs “comment is private”.

### Mutations (documented; this plugin stays read-only)

- `POST /tasks/{id}/comments` — body `{ "text": "…" }`; 201 example includes `"private": false`.
- `PUT /tasks/{id}/comments/{comment_id}` — update text.
- `POST /tasks`, `PUT /tasks/{id}`, sub-task create, etc.

Do not call these from Expert Stats.

## What 0.4.4 does with this

- Stops claiming “no public documentation”.
- Does **not** add `GET /tasks/{id}` or `GET /tasks/{id}/comments` until pagination (`limit`/`offset` vs `page`) and list visibility (`private` on GET) are confirmed.
- Keeps mapping `description` / comments only if those keys are already on the existing list payload.
- Live missing description/comments on one saved record remains **unverified** for the plugin’s actual filter paths, not proof of absence.
