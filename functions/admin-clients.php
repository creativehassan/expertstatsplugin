<?php
/**
 * Clients workspace.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register Clients submenu. New page; existing slugs are unchanged.
 *
 * @return void
 */
function wpcable_clients_menu() {
	add_submenu_page(
		'codeable_transcactions_stats',
		__( 'Clients', 'wpcable' ),
		__( 'Clients', 'wpcable' ),
		'manage_options',
		'codeable_clients',
		'codeable_clients_callback',
		20
	);
}
add_action( 'admin_menu', 'wpcable_clients_menu', 20 );

/**
 * Render the clients workspace.
 *
 * @return void
 */
function codeable_clients_callback() {
	codeable_page_requires_login( __( 'Clients', 'wpcable' ) );
	codeable_admin_notices();

	$tasks   = new wpcable_tasks();
	$stats   = new wpcable_stats();
	$account = wpcable_get_account_details();
	$counts  = $tasks->get_state_counts();
	$health  = wpcable_import_health::snapshot_from_counts(
		$counts['imported'],
		$stats->get_transaction_count(),
		! empty( $account['id'] )
	);

	$template = apply_filters( 'wpcable_admin_clients_template', WPCABLE_TEMPLATE_DIR . '/admin-clients.php' );
	ob_start();
	require $template;
	echo ob_get_clean();
}

/**
 * Ajax: paged client list from tasks ⋈ clients.
 */
function wpcable_ajax_query_clients() {
	if ( ! wpcable_ajax_can_manage() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$args = array(
		'page'     => isset( $_REQUEST['page_num'] ) ? (int) $_REQUEST['page_num'] : 1,
		'per_page' => isset( $_REQUEST['per_page'] ) ? (int) $_REQUEST['per_page'] : 25,
		'search'   => isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '',
		'filter'   => isset( $_REQUEST['filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter'] ) ) : 'all',
		'orderby'  => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_activity',
		'order'    => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
	);

	$repo = new wpcable_clients();
	wp_send_json_success( $repo->get_client_workspace( $args ) );
}
add_action( 'wp_ajax_wpcable_query_clients', 'wpcable_ajax_query_clients' );

/**
 * Ajax: one client with projects, evidence, and review-only draft.
 */
function wpcable_ajax_get_client() {
	if ( ! wpcable_ajax_can_manage() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$client_id = isset( $_REQUEST['client_id'] ) ? (int) $_REQUEST['client_id'] : 0;
	$repo      = new wpcable_clients();
	$client    = $repo->get_client_detail( $client_id );

	if ( ! $client ) {
		wp_send_json_error( array( 'message' => 'not_found' ), 404 );
	}

	$opps = new wpcable_opportunities();
	$cues = $opps->suggestions_for_client( $client );
	$draft = $cues ? $opps->outreach_draft( $client, $cues[0] ) : $opps->outreach_draft( $client );

	wp_send_json_success(
		array(
			'client'        => $client,
			'suggestions'   => $cues,
			'draft'         => $draft,
			'unsupported'   => wpcable_opportunities::unsupported_signals(),
			'read_only_api' => true,
		)
	);
}
add_action( 'wp_ajax_wpcable_get_client', 'wpcable_ajax_get_client' );
