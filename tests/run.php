<?php
/**
 * Offline test runner. No WordPress, no Codeable network.
 *
 * Usage: php tests/run.php
 *
 * @package wpcable
 */

require_once __DIR__ . '/bootstrap.php';

global $wpdb, $wpcable_assertions;

$wpdb = new wpcable_test_wpdb();
wpcable_load_plugin_classes();
wpcable_seed_fixture_clients( $wpdb );
wpcable_seed_fixture_tasks( $wpdb );

echo "Codeable Expert Stats — offline tests\n";
echo "=====================================\n\n";

echo "1) PHP 8 profile / avatar / invalid API guards\n";
require __DIR__ . '/test-profile-guards.php';

echo "\n2) Server-side pagination, filters, client grouping\n";
require __DIR__ . '/test-pagination.php';

echo "\n3) No-data financial / date handling\n";
require __DIR__ . '/test-financial-empty.php';

echo "\n4) Estimate math regression\n";
require __DIR__ . '/test-estimate.php';

echo "\n5) Opportunity provenance\n";
require __DIR__ . '/test-opportunities.php';

echo "\n6) 0.4.1 QA: dates, health provenance, counts, concurrency\n";
require __DIR__ . '/test-qa-041.php';

echo "\n7) 0.4.2 QA: Tasks renderer scope, timezone label, estimator unchanged\n";
require __DIR__ . '/test-qa-042.php';

echo "\n7c) 0.4.3 QA: payload history, disconnected browsing, fluid chrome\n";
require __DIR__ . '/test-qa-043.php';

echo "\n7d) ZIP packaging: archive root expertstatsplugin-master/wp-codeable.php\n";
require __DIR__ . '/test-package-zip.php';

echo "\n7e) 0.4.4 QA: one Stats overview, one import-health, no legacy duplicate\n";
require __DIR__ . '/test-qa-044.php';

echo "\n7f) 0.4.5 QA: documented GET /tasks/{id} + comments cache\n";
require __DIR__ . '/test-qa-045.php';

echo "\n7g) 0.4.6 QA: 50-item pages, Link replay, PKT times, shape diagnostics\n";
require __DIR__ . '/test-qa-046.php';

echo "\n7h) 0.4.7 QA: resumable bulk comments backfill, PKT, lean ZIP\n";
require __DIR__ . '/test-qa-047.php';

echo "\n7i) 0.4.8 QA: is_private mapping and rejected-Link diagnostics\n";
require __DIR__ . '/test-qa-048.php';

echo "\n7j) 0.4.9 QA: root-relative next Link, 14 pages / 674 complete\n";
require __DIR__ . '/test-qa-049.php';

echo "\n8) Production JS integration (jsdom + jQuery, no Codeable network)\n";
$js_dir    = __DIR__ . '/js';
$js_status = 0;
if ( ! is_dir( $js_dir . '/node_modules/jquery' ) ) {
	$npm_cmd = 'npm install --prefix ' . escapeshellarg( $js_dir ) . ' --no-fund --no-audit --silent';
	passthru( $npm_cmd, $npm_code );
	if ( $npm_code ) {
		echo "  FAIL  npm install for JS tests\n";
		$js_status = 1;
	}
}
$vendor_jq = __DIR__ . '/fixtures/vendor/jquery.min.js';
$npm_jq    = $js_dir . '/node_modules/jquery/dist/jquery.min.js';
if ( ! $js_status && is_file( $npm_jq ) && ! is_file( $vendor_jq ) ) {
	if ( ! is_dir( dirname( $vendor_jq ) ) ) {
		mkdir( dirname( $vendor_jq ), 0777, true );
	}
	copy( $npm_jq, $vendor_jq );
}
if ( ! $js_status ) {
	passthru( 'node ' . escapeshellarg( $js_dir . '/test-workspace-renderer.mjs' ), $c1 );
	passthru( 'node ' . escapeshellarg( $js_dir . '/test-estimate-formula.mjs' ), $c2 );
	$js_status = ( $c1 || $c2 ) ? 1 : 0;
}

echo "\n9) Production templates + page-level overflow screenshots\n";
$shot_status = 0;
passthru( 'php ' . escapeshellarg( __DIR__ . '/render-production-screens.php' ), $render_code );
if ( $render_code ) {
	echo "  FAIL  render-production-screens.php\n";
	$shot_status = 1;
} else {
	if ( ! is_dir( $js_dir . '/node_modules/puppeteer-core' ) ) {
		passthru( 'npm install --prefix ' . escapeshellarg( $js_dir ) . ' --no-fund --no-audit --silent', $npm2 );
		if ( $npm2 ) {
			echo "  FAIL  npm install puppeteer-core\n";
			$shot_status = 1;
		}
	}
	if ( ! $shot_status ) {
		passthru( 'node ' . escapeshellarg( $js_dir . '/capture-production.mjs' ), $cap );
		$shot_status = $cap ? 1 : 0;
	}
}

echo "\n=====================================\n";
echo 'Passed: ' . $wpcable_assertions['pass'] . '  Failed: ' . $wpcable_assertions['fail'] . "\n";

if ( $wpcable_assertions['fail'] || $js_status || $shot_status ) {
	echo "Failures:\n";
	foreach ( $wpcable_assertions['failures'] as $f ) {
		echo " - $f\n";
	}
	if ( $js_status ) {
		echo " - production JS integration\n";
	}
	if ( $shot_status ) {
		echo " - production screenshots / overflow\n";
	}
	exit( 1 );
}

echo "All offline tests passed.\n";
exit( 0 );
