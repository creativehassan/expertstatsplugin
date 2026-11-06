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

echo "\n=====================================\n";
echo 'Passed: ' . $wpcable_assertions['pass'] . '  Failed: ' . $wpcable_assertions['fail'] . "\n";

if ( $wpcable_assertions['fail'] || $js_status ) {
	echo "Failures:\n";
	foreach ( $wpcable_assertions['failures'] as $f ) {
		echo " - $f\n";
	}
	if ( $js_status ) {
		echo " - production JS integration\n";
	}
	exit( 1 );
}

echo "All offline tests passed.\n";
exit( 0 );
