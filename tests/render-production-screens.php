<?php
/**
 * Render the six production templates into HTML for screenshots.
 * Offline fixtures only. Banner: SYNTHETIC FIXTURE.
 *
 * @package wpcable
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
	function add_menu_page() {}
	function add_submenu_page() {}
	function add_thickbox() {}
	function register_setting() {}
	function add_filter() {}
}

global $wpdb;
$wpdb = new wpcable_test_wpdb();
wpcable_load_plugin_classes();
wpcable_seed_fixture_clients( $wpdb );
wpcable_seed_fixture_tasks( $wpdb );

$wpdb->update(
	'wp_codeable_tasks',
	array(
		'description_present' => 1,
		'description_key'     => 'description',
		'description_text'    => 'Fixture project brief stored from a list-payload description key. Not invented.',
		'comments_present'    => 1,
		'comments_source'     => 'comments',
		'comments_complete'   => 0,
		'comments_json'       => wp_json_encode(
			array(
				array(
					'id'         => 1,
					'body'       => 'Public status update from the saved payload.',
					'author'     => 'Fixture Client Alpha',
					'visibility' => 'public',
					'private'    => false,
					'created_at' => '2024-02-01',
				),
				array(
					'id'         => 2,
					'body'       => 'Private contractor note from the saved payload.',
					'author'     => 'Fixture Expert',
					'visibility' => 'private',
					'private'    => true,
					'created_at' => '2024-02-02',
				),
			)
		),
	),
	array( 'task_id' => 1 )
);

update_option(
	'wpcable_account_details',
	array(
		'id'                    => 1001,
		'first_name'            => 'Fixture',
		'last_name'             => 'Expert',
		'role'                  => 'contractor',
		'completed_tasks_count' => 12,
		'tasks_count'           => 40,
		'stats'                 => array( 'avg_rating' => 5 ),
	)
);
update_option( 'wpcable_rate', 100 );
update_option( 'wpcable_fee_type', 'client' );
delete_option( 'wpcable_auth_token' );

require_once WPCABLE_TEST_ROOT . '/functions/admin-page.php';
require_once WPCABLE_TEST_ROOT . '/functions/admin-settings.php';
require_once WPCABLE_TEST_ROOT . '/functions/admin-estimate.php';
require_once WPCABLE_TEST_ROOT . '/functions/admin-tasks.php';
require_once WPCABLE_TEST_ROOT . '/functions/admin-clients.php';
require_once WPCABLE_TEST_ROOT . '/functions/admin-opportunities.php';

$out_dir = __DIR__ . '/fixtures/ui/production';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0777, true );
}

$css_workspace = '../../../../assets/css/wpcable-workspace.css';
$css_wpcable   = '../../../../assets/css/wpcable.css';
$css_shell     = '../_shell.css';

function wpcable_production_shell( $title, $current, $inner, $extra_head = '' ) {
	$pages = array(
		'stats'         => 'Overview',
		'tasks'         => 'Projects',
		'clients'       => 'Clients',
		'opportunities' => 'Opportunities',
		'estimate'      => 'Estimate',
		'settings'      => 'Settings',
	);
	$nav = '';
	foreach ( $pages as $key => $label ) {
		$cls  = $key === $current ? ' is-current' : '';
		$nav .= '<a class="' . $cls . '" href="production-' . $key . '.html">' . $label . '</a>';
	}

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" />'
		. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
		. '<title>SYNTHETIC FIXTURE — ' . htmlspecialchars( $title ) . '</title>'
		. '<link rel="stylesheet" href="' . $GLOBALS['css_wpcable'] . '" />'
		. '<link rel="stylesheet" href="' . $GLOBALS['css_workspace'] . '" />'
		. '<link rel="stylesheet" href="' . $GLOBALS['css_shell'] . '" />'
		. $extra_head
		. '</head><body><div class="wp-layout"><nav class="wp-sidebar"><h1>Codeable Stats</h1>'
		. $nav
		. '</nav><main class="wp-main">'
		. '<div class="banner"><strong>SYNTHETIC FIXTURE</strong> — production templates, offline fixture data. Not live Codeable. Not owner-authorised private clients.</div>'
		. $inner
		. '</main></div></body></html>';
}

function wpcable_capture( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
}

$pages = array();

$pages['stats'] = wpcable_capture(
	function () {
		codeable_transcactions_stats_cb();
	}
);

$pages['tasks'] = wpcable_capture(
	function () {
		codeable_tasks_callback();
	}
);

$pages['clients'] = wpcable_capture(
	function () {
		codeable_clients_callback();
	}
);

$pages['opportunities'] = wpcable_capture(
	function () {
		codeable_opportunities_callback();
	}
);

$pages['estimate'] = wpcable_capture(
	function () {
		codeable_estimate_callback();
	}
);

$pages['settings'] = wpcable_capture(
	function () {
		codeable_settings_callback();
	}
);

$jq = '<script src="../../vendor/jquery.min.js"></script>';
$jq .= '<script>window.ajaxurl="/wp-admin/admin-ajax.php";</script>';

$pages['tasks'] = wpcable_production_shell(
	'Projects',
	'tasks',
	$pages['tasks'],
	$jq . '<script src="../../integration/tasks-ajax-mock.js"></script>'
	. '<script src="../../../../assets/js/wpcable-workspace.js"></script>'
);

foreach ( array( 'stats', 'clients', 'opportunities', 'estimate', 'settings' ) as $key ) {
	$extra = '';
	if ( 'estimate' === $key ) {
		$extra = $jq . '<script src="../../../../assets/js/wpcable.js"></script>';
	}
	if ( 'clients' === $key ) {
		$extra = $jq . '<script src="../../integration/clients-ajax-mock.js"></script>'
			. '<script src="../../../../assets/js/wpcable-workspace.js"></script>';
	}
	$pages[ $key ] = wpcable_production_shell( ucfirst( $key ), $key, $pages[ $key ], $extra );
}

foreach ( $pages as $key => $html ) {
	file_put_contents( $out_dir . '/production-' . $key . '.html', $html );
	echo "Wrote production-{$key}.html (" . strlen( $html ) . " bytes)\n";
}
