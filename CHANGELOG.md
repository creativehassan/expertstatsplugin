### Changelog

*0.4.3 (2026-11-06)*
* Cohesive six-screen chrome: fluid WP-content width, readable type, Comfortable/Compact density, in-page nav. WP shell / Astra notices untouched.
* Stats overview from local DB (profile vs imported scopes, clients, opportunity cues, valid-date activity). Missing finances stay unavailable, not $0.
* Tasks/Clients drawers are progressive (no empty side panel). Opportunities are a ranked comparative list grouped by cue type — still unconfirmed needs.
* Estimate and Settings restyled; PERT formulas and settings controls unchanged.
* Disconnected browsing: token absent/expired disables Refresh but keeps and shows saved data. Logout is not Clear all data.
* Task description and public/private comments: mapped only when already present on the existing `users/me/tasks/{filter}` list payload. Missing key ≠ empty. No new HTTP routes. UI sections render genuine stored records and an honest missing state.

*0.4.2 (2026-11-06)*
* Tasks workspace: `applyCounts` no longer throws a `$root` ReferenceError under `'use strict'` (that exception was swallowed by jQuery 3 Deferred `exceptionHook`, leaving the Loading row after a valid `wpcable_query_tasks` JSON envelope). Renderer now scopes KPI updates, unwraps the WP `{success,data}` envelope, and replaces Loading on ajax failure.
* Legacy `wpcable.js` in-memory task list does not run on `.wpcable-workspace` pages.
* Date display uses `wp_date` (never `date_default_timezone_set`) and appends the WordPress timezone abbreviation so UTC vs PKT is labeled.
* Estimate PERT/fee formulas unchanged (1/1/1 = 1:00/$100/$90; sequential 2/4/10 = 4:40/$466.67/$420 and cautious 6:40/$666.67). Earlier 0.4.1 “broken estimator” report was a fill-order artifact.
* Production-JS integration tests (jsdom + jQuery) for Tasks renderer and Estimate page.

*0.4.1 (2026-11-06)*
* QA patch after 0.4.0 live install: reject invalid/future/Y2K38 activity timestamps so ages are not `-4092d ago` and 19 Jan 2038 is not shown
* Opportunity scores ignore sentinel/future activity
* Constrain plugin-scoped workspace avatars (max 32×32, object-fit)
* Import health distinguishes cached local rows vs instrumented attempts; never invents success times from `last_fetch` / MAX(activity)
* KPI filter counts use the same SQL as the matching table filter; state vs local flag labeled
* Page views and hourly cron no longer enqueue a new GET queue; explicit Refresh only; pending interrupted queues still resume; concurrent tab lock
* Project drawer: honest unavailable description/comments panels + existing workroom URL; no comments importer

*0.4.0 (2026-11-06)*
* Hybrid workspace inside this plugin: server-side Tasks pagination/search/filters/client grouping + Clients page + evidence-backed Opportunities
* Preserve existing slugs (`codeable_transcactions_stats`, `codeable_tasks`, `codeable_estimate`, `codeable_settings`); Estimate math and Settings options unchanged
* PHP 8 profile/avatar guards and invalid API JSON guards (from the repaired 0.3.1 baseline)
* Honest financial empty state when transactions are missing — no fake $0 or year 1970
* Per-source import health (profile / tasks / transactions) without inventing success times
* Local notes remain editable; Codeable access stays read-only
* Offline fixture tests (`php tests/run.php`)

*0.0.1*
* Initial build

*0.0.2*
* Fix database errors when importing due to empty rows. Temporary fix until loop logic can be improved
* Formatted all files for WordPress's style guide
* Spelling fixes

*0.0.3*
* Added deactivator class for future deactivation logic
* Added uninstall.php to completely remove all data on deletion
* Spelling fixes

*0.0.4*
* Testing GIT update

*0.0.5 (2017-03-01)*
* Text changes on charts [#28](https://github.com/codeablehq/expertstatsplugin/issues/28) @defunctl

*0.0.6 (2017-03-01)*
* Added invalid credentials logic and admin notice [#29](https://github.com/codeablehq/expertstatsplugin/issues/29) @defunctl
* Added WordPress Object Caching to stats and client DB calls [#19](https://github.com/codeablehq/expertstatsplugin/issues/19) @defunctl

*0.0.7 (2017-03-15)*
* PERT Calculator - @jin0x , @jonathanbossenger
* New charts - @spyrosvl
* Added offline export support via Highcharts JS @spyrosvl

*0.0.9 (2017-03-23)*
* Average in charts - @spyrosvl
* Condition to change text between best month/day - @spyrosvl

*0.0.10 (2017-05-18)*
* Fixes for missing user data, thanks @ahanzek

*0.0.20 (2019-07-07)*
* Update UI layout to match current WordPress standards
* Apply WordPress coding standards to code
