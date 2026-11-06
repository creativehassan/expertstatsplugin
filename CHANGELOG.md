### Changelog

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
