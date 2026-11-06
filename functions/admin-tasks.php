<?php
/**
 * Tasks list.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register submenu pages.
 *
 * @return void
 */
function wpcable_tasks_menu() {
	add_submenu_page(
		'codeable_transcactions_stats',
		__( 'Tasks', 'wpcable' ),
		__( 'Tasks', 'wpcable' ),
		'manage_options',
		'codeable_tasks',
		'codeable_tasks_callback',
		50
	);
}
add_action( 'admin_menu', 'wpcable_tasks_menu', 50 );

/**
 * Ajax handler that updates a single task in the DB (local notes/flags only).
 */
function wpcable_ajax_update_task() {
	if ( ! wpcable_ajax_can_manage( 'wpcable-task' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$task = isset( $_POST['task'] ) ? wp_unslash( $_POST['task'] ) : array();

	if ( ! $task || ! is_array( $task ) ) {
		wp_send_json_error( array( 'message' => 'invalid' ), 400 );
	}

	$wpcable_tasks = new wpcable_tasks();
	$wpcable_tasks->update_task( $task );

	$fresh = $wpcable_tasks->get_task( (int) $task['task_id'] );
	wp_send_json_success( $fresh );
}
add_action( 'wp_ajax_wpcable_update_task', 'wpcable_ajax_update_task' );

/**
 * Ajax handler that returns a paged task list in JSON format.
 */
function wpcable_ajax_query_tasks() {
	if ( ! wpcable_ajax_can_manage() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$args = array(
		'page'            => isset( $_REQUEST['page_num'] ) ? (int) $_REQUEST['page_num'] : 1,
		'per_page'        => isset( $_REQUEST['per_page'] ) ? (int) $_REQUEST['per_page'] : 25,
		'search'          => isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '',
		'state'           => isset( $_REQUEST['state'] ) ? sanitize_key( wp_unslash( $_REQUEST['state'] ) ) : 'all',
		'flag'            => isset( $_REQUEST['flag'] ) ? sanitize_key( wp_unslash( $_REQUEST['flag'] ) ) : '',
		'preferred'       => ! empty( $_REQUEST['preferred'] ),
		'promoted'        => ! empty( $_REQUEST['promoted'] ),
		'favored'         => ! empty( $_REQUEST['favored'] ),
		'subscribed'      => ! empty( $_REQUEST['subscribed'] ),
		'no_hidden'       => ! empty( $_REQUEST['no_hidden'] ),
		'has_notes'       => ! empty( $_REQUEST['has_notes'] ),
		'paid'            => ! empty( $_REQUEST['paid'] ),
		'estimated'       => ! empty( $_REQUEST['estimated'] ),
		'lost'            => ! empty( $_REQUEST['lost'] ),
		'completed'       => ! empty( $_REQUEST['completed'] ),
		'client_id'       => isset( $_REQUEST['client_id'] ) ? (int) $_REQUEST['client_id'] : 0,
		'group_by_client' => ! empty( $_REQUEST['group_by_client'] ),
		'orderby'         => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_activity',
		'order'           => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
	);

	$wpcable_tasks = new wpcable_tasks();
	wp_send_json_success( $wpcable_tasks->query_tasks( $args ) );
}
add_action( 'wp_ajax_wpcable_query_tasks', 'wpcable_ajax_query_tasks' );

/**
 * Ajax handler: one task drawer payload.
 */
function wpcable_ajax_get_task() {
	if ( ! wpcable_ajax_can_manage() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$task_id = isset( $_REQUEST['task_id'] ) ? (int) $_REQUEST['task_id'] : 0;
	$repo    = new wpcable_tasks();
	$task    = $repo->get_task( $task_id );

	if ( ! $task ) {
		wp_send_json_error( array( 'message' => 'not_found' ), 404 );
	}

	$client = null;
	if ( ! empty( $task['client_id'] ) ) {
		$clients = new wpcable_clients();
		$client  = $clients->get_client_detail( (int) $task['client_id'] );
	}

	$opps = new wpcable_opportunities();
	$cues = $client ? $opps->suggestions_for_client( $client ) : array();

	wp_send_json_success(
		array(
			'task'          => $task,
			'client'        => $client,
			'suggestions'   => $cues,
			'unsupported'   => wpcable_opportunities::unsupported_signals(),
			'history'       => wpcable_task_history_unavailable( $task ),
			'read_only_api' => true,
		)
	);
}
add_action( 'wp_ajax_wpcable_get_task', 'wpcable_ajax_get_task' );

/**
 * Legacy full-list reload kept for the hourly sync refresh on other screens.
 * Workspace pages should call wpcable_query_tasks instead.
 */
function wpcable_ajax_reload_tasks() {
	if ( ! wpcable_ajax_can_manage() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$wpcable_tasks = new wpcable_tasks();
	$task_list     = $wpcable_tasks->query_tasks(
		array(
			'page'     => 1,
			'per_page' => 25,
			'state'    => 'all',
		)
	);
	wp_send_json_success( $task_list );
}
add_action( 'wp_ajax_wpcable_reload_tasks', 'wpcable_ajax_reload_tasks' );

/**
 * Render the tasks workspace.
 *
 * @return void
 */
function codeable_tasks_callback() {
	codeable_page_requires_login( __( 'Your tasks', 'wpcable' ) );
	codeable_admin_notices();

	$color_flags   = wpcable_task_color_flags();
	$wpcable_tasks = new wpcable_tasks();
	$wpcable_stats = new wpcable_stats();
	$account       = wpcable_get_account_details();
	$counts        = $wpcable_tasks->get_state_counts();
	$tx_count      = $wpcable_stats->get_transaction_count();
	$health        = wpcable_import_health::snapshot_from_counts(
		$counts['imported'],
		$tx_count,
		! empty( $account['id'] )
	);

	wpcable_app_open(
		'tasks',
		__( 'Projects', 'wpcable' ),
		__( 'Imported Codeable projects, paginated on the server. Expert-profile totals are a different scope than these local rows. Access stays read-only; only your local notes and flags are saved here.', 'wpcable' )
	);
	$admin_task_table_template = apply_filters( 'wpcable_admin_task_table_template', WPCABLE_TEMPLATE_DIR . '/admin-task-table.php' );
	ob_start();
	require $admin_task_table_template;
	echo ob_get_clean();
	wpcable_app_close();
}
