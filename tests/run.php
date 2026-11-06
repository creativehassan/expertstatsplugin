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

echo "\n=====================================\n";
echo 'Passed: ' . $wpcable_assertions['pass'] . '  Failed: ' . $wpcable_assertions['fail'] . "\n";

if ( $wpcable_assertions['fail'] ) {
	echo "Failures:\n";
	foreach ( $wpcable_assertions['failures'] as $f ) {
		echo " - $f\n";
	}
	exit( 1 );
}

echo "All offline tests passed.\n";
exit( 0 );
