# Offline tests

```
php tests/run.php
```

Requires PHP 8+ with PDO SQLite, and Node.js with npm for the production-JS integration tests (`tests/js`). Does not load WordPress, does not call Codeable, and does not use credentials.

`tests/fixtures/integration/` loads the real `assets/js/wpcable-workspace.js` against a mocked WP `{success,data}` ajax envelope, plus the Estimate page formulas with `fee_type=client`.
