<?php
/**
 * Evidence-backed opportunities.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register Opportunities submenu.
 *
 * @return void
 */
function wpcable_opportunities_menu() {
	add_submenu_page(
		'codeable_transcactions_stats',
		__( 'Opportunities', 'wpcable' ),
		__( 'Opportunities', 'wpcable' ),
		'manage_options',
		'codeable_opportunities',
		'codeable_opportunities_callback',
		60
	);
}
add_action( 'admin_menu', 'wpcable_opportunities_menu', 60 );

/**
 * Render opportunities page.
 *
 * @return void
 */
function codeable_opportunities_callback() {
	codeable_page_requires_login( __( 'Opportunities', 'wpcable' ) );
	codeable_admin_notices();

	$opps    = new wpcable_opportunities();
	$cues    = $opps->get_suggestions();
	$blocked = wpcable_opportunities::unsupported_signals();
	$tasks   = new wpcable_tasks();
	$stats   = new wpcable_stats();
	$account = wpcable_get_account_details();
	$counts  = $tasks->get_state_counts();
	$health  = wpcable_import_health::snapshot_from_counts(
		$counts['imported'],
		$stats->get_transaction_count(),
		! empty( $account['id'] )
	);

	$template = apply_filters( 'wpcable_admin_opportunities_template', WPCABLE_TEMPLATE_DIR . '/admin-opportunities.php' );
	ob_start();
	require $template;
	echo ob_get_clean();
}

/**
 * Ajax: suggestions list.
 */
function wpcable_ajax_query_opportunities() {
	if ( ! wpcable_ajax_can_manage() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$client_id = isset( $_REQUEST['client_id'] ) ? (int) $_REQUEST['client_id'] : 0;
	$opps      = new wpcable_opportunities();

	wp_send_json_success(
		array(
			'items'       => $opps->get_suggestions( $client_id ),
			'unsupported' => wpcable_opportunities::unsupported_signals(),
		)
	);
}
add_action( 'wp_ajax_wpcable_query_opportunities', 'wpcable_ajax_query_opportunities' );
