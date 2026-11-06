<?php
/**
 * @package wpcable
 *
 * Plugin Name:       Codeable - Expert's Stats
 * Plugin URI:        https://github.com/codeablehq/blackbook/wiki/Expert-Stats-Plugin
 * Description:       Get your Codeable data
 * Version:           0.4.2
 * Author:            Spyros Vlachopoulos
 * Contributors:      Panagiotis Synetos, John Leskas, Justin Frydman, Jonathan Bossenger, Rob Scott, Philipp Stracker, Peter Kakoma
 * Author URI:        https://codeable.io/developers/spyros-vlachopoulos/
 * License:           GPL2
 * Text Domain:       wpcable
 * GitHub Plugin URI: https://github.com/codeablehq/expertstatsplugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load plugin textdomain.
add_action( 'plugins_loaded', 'wpcable_load_textdomain' );
function wpcable_load_textdomain() {
	load_plugin_textdomain(
		'wpcable',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}

/** Define wpcable constants */
define( 'WPCABLE_TEMPLATE_DIR', dirname( __FILE__ ) . '/templates' );
define( 'WPCABLE_CLASSES_DIR', dirname( __FILE__ ) . '/classes' );
define( 'WPCABLE_FUNCTIONS_DIR', dirname( __FILE__ ) . '/functions' );
define( 'WPCABLE_ASSESTS_DIR', dirname( __FILE__ ) . '/assets' );
define( 'WPCABLE_DIR', dirname( __FILE__ ) );
define( 'WPCABLE_URI', rtrim( plugin_dir_url( __FILE__ ), '/' ) );

require_once WPCABLE_CLASSES_DIR . '/object_cache.php';
require_once WPCABLE_FUNCTIONS_DIR . '/helpers.php';
require_once WPCABLE_CLASSES_DIR . '/import_health.php';
require_once WPCABLE_CLASSES_DIR . '/estimate.php';
require_once WPCABLE_CLASSES_DIR . '/api_calls.php';
require_once WPCABLE_CLASSES_DIR . '/api_data.php';
require_once WPCABLE_CLASSES_DIR . '/stats.php';
require_once WPCABLE_CLASSES_DIR . '/tasks.php';
require_once WPCABLE_CLASSES_DIR . '/clients.php';
require_once WPCABLE_CLASSES_DIR . '/opportunities.php';
require_once WPCABLE_FUNCTIONS_DIR . '/admin-settings.php';
require_once WPCABLE_FUNCTIONS_DIR . '/admin-tasks.php';
require_once WPCABLE_FUNCTIONS_DIR . '/admin-page.php';
require_once WPCABLE_FUNCTIONS_DIR . '/admin-estimate.php';
require_once WPCABLE_FUNCTIONS_DIR . '/admin-clients.php';
require_once WPCABLE_FUNCTIONS_DIR . '/admin-opportunities.php';

/**
 * Hourly WP-Cron hook. 0.4.1 does not enqueue a new Codeable GET queue here.
 * Explicit Refresh remains the only new-queue trigger so page views cannot
 * race mark_tasks_lost() against KPI counts. Existing pending queues are
 * left untouched for resume.
 *
 * @return void
 */
function wpcable_cronjob() {
	codeable_maybe_refresh_data();
}
add_action( 'wpcable_cronjob', 'wpcable_cronjob' );

/**
 * Create a scheduled event (if it does not exist already).
 */
function wpcable_cronstarter_activation() {
	if ( ! wp_next_scheduled( 'wpcable_cronjob' ) ) {
		wp_schedule_event( time(), 'hourly', 'wpcable_cronjob' );
	}
}

// and make sure it's called whenever WordPress loads
add_action( 'wp', 'wpcable_cronstarter_activation' );

function wpcable_sync_start() {
	if ( ! wpcable_ajax_can_manage( 'wpcable-sync' ) ) {
		wp_send_json_error( [ 'state' => 'FORBIDDEN' ], 403 );
	}

	$queue = wpcable_sync_queue();

	// Resume an interrupted Refresh. Do not replace the pending queue.
	if ( ! empty( $queue ) ) {
		wp_send_json_error( [
			'state'  => 'RUNNING',
			'step'   => $queue[0],
			'source' => 'pending_queue',
		] );
	}

	$data  = new wpcable_api_data();
	$queue = $data->prepare_queue();
	update_option( 'wpcable_api_queue', $queue );
	wp_send_json_success( [
		'state' => 'READY',
		'step'  => isset( $queue[0] ) ? $queue[0] : null,
	] );
}
add_action( 'wp_ajax_wpcable_sync_start', 'wpcable_sync_start' );

/**
 * Process the next queued API call. Does not start a new queue.
 * Concurrent tabs that lose the lock report RUNNING without shifting the queue.
 */
function wpcable_sync_process() {
	if ( ! wpcable_ajax_can_manage( 'wpcable-sync' ) ) {
		wp_send_json_error( [ 'state' => 'FORBIDDEN' ], 403 );
	}

	$queue = wpcable_sync_queue();

	if ( empty( $queue ) ) {
		wpcable_sync_lock_release();
		wp_send_json_error( [ 'state' => 'FINISHED' ] );
	}

	if ( ! wpcable_sync_lock_acquire() ) {
		wp_send_json_error( [
			'state'      => 'RUNNING',
			'step'       => $queue[0],
			'source'     => 'in_progress_locked',
			'concurrent' => true,
		] );
	}

	$data = new wpcable_api_data();
	$task = array_shift( $queue );
	$next = $data->process_queue( $task );

	if ( $next ) {
		array_unshift( $queue, $next );
	}

	if ( empty( $queue ) ) {
		update_option( 'wpcable_last_fetch', time() );
		delete_option( 'wpcable_api_queue' );
		wpcable_sync_lock_release();
		wp_send_json_error( [ 'state' => 'FINISHED' ] );
	}

	update_option( 'wpcable_api_queue', $queue );
	wpcable_sync_lock_release();
	wp_send_json_error( [
		'state' => 'RUNNING',
		'step'  => $task,
	] );
}
add_action( 'wp_ajax_wpcable_sync_process', 'wpcable_sync_process' );

// on install
function wpcable_install() {
	global $wpdb;

	$wpcable_db_version = '0.0.4';

	if ( get_option( 'wpcable_transcactions_version' ) !== $wpcable_db_version ) {

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$table_name = $wpdb->prefix . 'codeable_transcactions';

		$sql = "CREATE TABLE {$table_name} (
			`id` int(11) NOT NULL,
			`description` varchar(128) CHARACTER SET utf8 NOT NULL,
			`dateadded` datetime NOT NULL,
			`fee_percentage` decimal(10,0) DEFAULT NULL,
			`fee_amount` decimal(10,0) DEFAULT NULL,
			`task_type` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
			`task_id` int(11) DEFAULT NULL,
			`task_title` text DEFAULT NULL,
			`parent_task_id` int(11) DEFAULT NULL,
			`preferred` int(4) DEFAULT NULL,
			`client_id` int(11) DEFAULT NULL,
			`last_sync` int(11) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id)
		) $charset_collate;";

		$db_delta = dbDelta( $sql );

		$table_name = $wpdb->prefix . 'codeable_amounts';

		$sql = "CREATE TABLE {$table_name} (
			`task_id` int(11) NOT NULL,
			`client_id` int(11) NOT NULL,
			`credit_revenue_id` int(11) DEFAULT NULL,
			`credit_revenue_amount` int(11) DEFAULT NULL,
			`credit_fee_id` int(11) DEFAULT NULL,
			`credit_fee_amount` int(11) DEFAULT NULL,
			`credit_user_id` int(11) DEFAULT NULL,
			`credit_user_amount` int(11) DEFAULT NULL,
			`debit_cost_id` int(11) DEFAULT NULL,
			`debit_cost_amount` int(11) DEFAULT NULL,
			`debit_user_id` int(11) DEFAULT NULL,
			`debit_user_amount` int(11) DEFAULT NULL,
			PRIMARY KEY  (task_id),
			KEY client_id (client_id)
		) $charset_collate;";

		$db_delta = dbDelta( $sql );

		$table_name = $wpdb->prefix . 'codeable_clients';

		$sql = "CREATE TABLE {$table_name} (
			`client_id` int(11) NOT NULL,
			`full_name` varchar(255) NOT NULL,
			`role` varchar(255) DEFAULT NULL,
			`last_sign_in_at` datetime DEFAULT NULL,
			`pro` int(11) DEFAULT NULL,
			`timezone_offset` int(11) DEFAULT NULL,
			`tiny` varchar(255) DEFAULT NULL,
			`small` varchar(255) DEFAULT NULL,
			`medium` varchar(255) DEFAULT NULL,
			`large` varchar(255) DEFAULT NULL,
			`last_sync` int(11) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (client_id)
		) $charset_collate;";

		$db_delta = dbDelta( $sql );

		$table_name = $wpdb->prefix . 'codeable_tasks';

		$sql = "CREATE TABLE {$table_name} (
			`task_id` int(11) NOT NULL,
			`client_id` int(11) NOT NULL,
			`title` varchar(255) NOT NULL,
			`estimate` bit DEFAULT 0 NOT NULL,
			`hidden` bit DEFAULT 0 NOT NULL,
			`promoted` bit DEFAULT 0 NOT NULL,
			`subscribed` bit DEFAULT 0 NOT NULL,
			`favored` bit DEFAULT 0 NOT NULL,
			`preferred` bit DEFAULT 0 NOT NULL,
			`client_fee` float DEFAULT 17.5 NOT NULL,
			`state` varchar(50) DEFAULT '' NOT NULL,
			`kind` varchar(50) DEFAULT '' NOT NULL,
			`value` float DEFAULT 0 NOT NULL,
			`value_client` float DEFAULT 0 NOT NULL,
			`last_activity` int(11) DEFAULT 0 NOT NULL,
			`last_activity_by` varchar(200) DEFAULT '' NOT NULL,
			`last_sync` int(11) DEFAULT 0 NOT NULL,
			`flag` varchar(20) DEFAULT '' NOT NULL,
			`notes` text DEFAULT '' NOT NULL,
			PRIMARY KEY  (task_id),
			KEY client_id (client_id),
			KEY state (state),
			KEY flag (flag),
			KEY last_activity (last_activity)
		) $charset_collate;";

		$db_delta = dbDelta( $sql );

		update_option( 'wpcable_transcactions_version', $wpcable_db_version );
	}
}

register_activation_hook( __FILE__, 'wpcable_install' );
add_action( 'plugins_loaded', 'wpcable_install' );

// on deactivation
function wpcable_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'classes/deactivator.php';
	WpCable_deactivator::deactivate();

}

register_deactivation_hook( __FILE__, 'wpcable_deactivate' );

function wpcable_admin_scripts( $hook ) {
	$plugin_hooks = [
		'toplevel_page_codeable_transcactions_stats',
		'codeable-stats_page_codeable_tasks',
		'codeable-stats_page_codeable_clients',
		'codeable-stats_page_codeable_opportunities',
		'codeable-stats_page_codeable_estimate',
		'codeable-stats_page_codeable_settings',
	];

	if ( ! in_array( $hook, $plugin_hooks, true ) ) {
		return;
	}

	wp_enqueue_style(
		'gridcss',
		plugins_url( 'assets/css/grid12.css', __FILE__ )
	);
	wp_enqueue_style(
		'wpcablecss',
		plugins_url( 'assets/css/wpcable.css', __FILE__ ),
		[],
		'0.4.2'
	);
	wp_enqueue_style(
		'ratycss',
		plugins_url( 'assets/css/jquery.raty.css', __FILE__ )
	);
	wp_enqueue_style(
		'datatablecss',
		plugins_url( 'assets/css/jquery.dataTables.min.css', __FILE__ )
	);
	wp_enqueue_style(
		'simplemde',
		plugins_url( 'assets/css/simplemde.min.css', __FILE__ )
	);

	wp_enqueue_script(
		'highchartsjs',
		plugins_url( 'assets/js/highcharts.js', __FILE__ ),
		[ 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ],
		null,
		true
	);
	wp_enqueue_script(
		'highcharts_export_js',
		plugins_url( 'assets/js/exporting.js', __FILE__ ),
		[ 'jquery', 'highchartsjs' ],
		null,
		true
	);
	wp_enqueue_script(
		'highcharts_offline_export_js',
		plugins_url( 'assets/js/offline-exporting.js', __FILE__ ),
		[ 'jquery', 'highcharts_export_js' ],
		null,
		true
	);
	wp_enqueue_style( 'jquery-ui-datepicker' );

	wp_enqueue_script(
		'highcharts3djs',
		plugins_url( 'assets/js/highcharts-3d.js', __FILE__ ),
		[ 'highchartsjs' ]
	);
	wp_enqueue_script(
		'ratyjs',
		plugins_url( 'assets/js/jquery.raty.js', __FILE__ )
	);
	wp_enqueue_script(
		'datatablesjs',
		plugins_url( 'assets/js/jquery.dataTables.min.js', __FILE__ )
	);
	wp_enqueue_script(
		'matchheightjs',
		plugins_url( 'assets/js/jquery.matchHeight-min.js', __FILE__ )
	);
	wp_enqueue_script(
		'simplemde',
		plugins_url( 'assets/js/simplemde.min.js', __FILE__ )
	);
	wp_enqueue_script(
		'wpcablejs',
		plugins_url( 'assets/js/wpcable.js', __FILE__ ),
		[ 'wp-util' ],
		'0.4.2',
		true
	);

	wp_enqueue_style(
		'wpcable-workspace',
		plugins_url( 'assets/css/wpcable-workspace.css', __FILE__ ),
		[ 'wpcablecss' ],
		'0.4.2'
	);

	$workspace_hooks = [
		'codeable-stats_page_codeable_tasks',
		'codeable-stats_page_codeable_clients',
		'codeable-stats_page_codeable_opportunities',
	];

	if ( in_array( $hook, $workspace_hooks, true ) ) {
		wp_enqueue_script(
			'wpcable-workspace',
			plugins_url( 'assets/js/wpcable-workspace.js', __FILE__ ),
			[ 'jquery', 'wp-util', 'simplemde', 'wpcablejs' ],
			'0.4.2',
			true
		);

		$task_repo = new wpcable_tasks();
		$stats     = new wpcable_stats();
		$account   = wpcable_get_account_details();

		wp_localize_script(
			'wpcable-workspace',
			'wpcableWorkspace',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpcable-workspace' ),
				'taskNonce' => wp_create_nonce( 'wpcable-task' ),
				'flags'   => wpcable_task_color_flags(),
				'i18n'    => [
					'noTasks'          => __( 'No matching projects', 'wpcable' ),
					'loading'          => __( 'Loading…', 'wpcable' ),
					'unavailable'      => __( 'Unavailable', 'wpcable' ),
					'notZero'          => __( 'Not $0 — this source has not imported.', 'wpcable' ),
					'lostHeuristic'    => __( 'Lost is a local heuristic, not proof another expert was hired.', 'wpcable' ),
					'reviewOnly'       => __( 'Review only — this plugin never sends outreach.', 'wpcable' ),
					'notesLocal'       => __( 'Local notes only. Nothing is written back to Codeable.', 'wpcable' ),
					'suggested'        => __( 'Suggested from local records. Not a confirmed client need.', 'wpcable' ),
					'profileCompleted'    => __( 'Profile completed (users/me) — different scope from imported rows', 'wpcable' ),
					'importedRows'        => __( 'Imported project rows (local database)', 'wpcable' ),
					'historyUnavailable'  => __( 'Unavailable in this plugin. The local table stores the title, not the full project body.', 'wpcable' ),
					'commentsUnavailable' => __( 'Workroom comments are not imported. This is not an empty comment history.', 'wpcable' ),
					'notesNotComments'    => __( 'Local notes below are yours in WordPress. They are not Codeable comments.', 'wpcable' ),
				],
				'kpis'    => [
					'profileCompleted' => wpcable_account_detail( $account, 'completed_tasks_count', null ),
					'profileTasks'     => wpcable_account_detail( $account, 'tasks_count', null ),
					'local'            => $task_repo->get_state_counts(),
					'transactions'     => $stats->get_transaction_count(),
					'financialAvailable' => $stats->has_transaction_data(),
				],
			]
		);
	}
}

add_action( 'admin_enqueue_scripts', 'wpcable_admin_scripts' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpcable_action_links' );

function wpcable_action_links( $links ) {
	$link = sprintf(
		'<a href="%s">Settings</a>',
		esc_url( get_admin_url( null, 'admin.php?page=codeable_settings' ) )
	);

	array_unshift( $links, $link );

	return $links;
}
