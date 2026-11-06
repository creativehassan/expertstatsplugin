<?php
/**
 * 0.4.1 live-QA regressions: invalid dates, health provenance, counts, sync lock.
 *
 * @package wpcable
 */

global $wpdb, $wpcable_options;

update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'H:i' );

$sentinel = wpcable_int32_sentinel();
$future   = time() + ( 4000 * DAY_IN_SECONDS );
$ok_ts    = strtotime( '2024-06-01 12:00:00 UTC' );

wpcable_assert_equals( 0, wpcable_normalize_timestamp( $sentinel ), 'Y2K38 sentinel is not a usable timestamp' );
wpcable_assert_equals( 0, wpcable_normalize_timestamp( $future ), 'far-future timestamp is rejected' );
wpcable_assert_equals( 0, wpcable_normalize_timestamp( 0 ), 'unix 0 is rejected' );
wpcable_assert_equals( '', wpcable_date( $sentinel ), 'sentinel does not format as Jan 19 2038' );
wpcable_assert_equals( '', wpcable_date( '2038-01-19 03:14:07' ), '2038 datetime string is blank' );
wpcable_assert( null === wpcable_stale_days( $sentinel ), 'sentinel stale_days is null, not negative' );
wpcable_assert( null === wpcable_stale_days( $future ), 'future stale_days is null, not -4092d' );
wpcable_assert( $ok_ts === wpcable_normalize_timestamp( $ok_ts ), 'valid 2024 timestamp is kept' );
wpcable_assert( wpcable_stale_days( $ok_ts ) >= 0, 'valid timestamp yields non-negative age' );
wpcable_assert( false !== strpos( wpcable_date( $ok_ts ), '2024' ) || '' !== wpcable_date( $ok_ts ), 'valid date formats' );

$wpdb->insert(
	'wp_codeable_clients',
	array(
		'client_id'       => 303,
		'full_name'       => 'Fixture Sentinel Client',
		'role'            => 'client',
		'last_sign_in_at' => '',
		'pro'             => 0,
		'timezone_offset' => 0,
		'medium'          => 'https://example.test/huge-avatar.png',
		'large'           => 'https://example.test/huge-avatar.png',
		'last_sync'       => time(),
	)
);
$wpdb->insert(
	'wp_codeable_tasks',
	array(
		'task_id'          => 3031,
		'client_id'        => 303,
		'title'            => 'Sentinel-dated completed project',
		'state'            => 'completed',
		'flag'             => 'completed',
		'preferred'        => 0,
		'value'            => 100,
		'value_client'     => 120,
		'last_activity'    => $sentinel,
		'last_activity_by' => 'Fixture',
		'notes'            => '',
		'kind'             => 'task',
		'last_sync'        => 1700000000,
	)
);
$wpdb->insert(
	'wp_codeable_tasks',
	array(
		'task_id'          => 4041,
		'client_id'        => 202,
		'title'            => 'Published but lost-flag mixed row',
		'state'            => 'published',
		'flag'             => 'lost',
		'preferred'        => 0,
		'value'            => 0,
		'value_client'     => 0,
		'last_activity'    => $ok_ts,
		'last_activity_by' => 'Fixture',
		'notes'            => '',
		'kind'             => 'task',
		'last_sync'        => $ok_ts,
	)
);

$tasks    = new wpcable_tasks();
$sentinel_task = $tasks->get_task( 3031 );
wpcable_assert_equals( 0, $sentinel_task['last_activity'], 'sanitize zeroes sentinel last_activity' );
wpcable_assert( empty( $sentinel_task['activity_valid'] ), 'sentinel activity_valid is false' );
wpcable_assert_equals( '', $sentinel_task['last_activity_date'], 'sentinel does not render a date' );
wpcable_assert( null === $sentinel_task['stale_days'], 'sentinel stale_days not a bogus age' );
wpcable_assert_equals( 'https://app.codeable.io/tasks/3031/workroom', $sentinel_task['workroom_url'], 'workroom URL matches Stats page pattern' );
wpcable_assert( empty( $sentinel_task['history']['description']['available'] ), 'description marked unavailable' );
wpcable_assert( empty( $sentinel_task['history']['comments']['available'] ), 'comments marked unavailable' );
wpcable_assert( false === $sentinel_task['history']['complete'], 'history is not claimed complete' );
wpcable_assert( false !== strpos( $sentinel_task['history']['comments']['reason'], 'not an empty comment' ), 'comments panel is not an empty list' );

$clients = new wpcable_clients();
$sentinel_client = $clients->get_client_detail( 303 );
wpcable_assert( null === $sentinel_client['stale_days'], 'client workspace does not show -4092d ago' );
wpcable_assert( empty( $sentinel_client['activity_valid'] ), 'client activity_valid false for sentinel' );

$opps = new wpcable_opportunities();
$cues = $opps->suggestions_for_client( $sentinel_client );
$cue_types = array();
foreach ( $cues as $cue ) {
	$cue_types[] = $cue['type'];
	if ( 'ebr_checkup' === $cue['type'] ) {
		wpcable_assert( false, 'sentinel activity must not ground an EBR opportunity score' );
	}
}
wpcable_assert( ! in_array( 'ebr_checkup', $cue_types, true ), 'no EBR cue from invalid/future activity' );

$published_filter = $tasks->query_tasks(
	array(
		'page'     => 1,
		'per_page' => 100,
		'state'    => 'published',
	)
);
$filter_counts = $tasks->get_filter_counts();
wpcable_assert_equals( $published_filter['total'], $filter_counts['published'], 'Published KPI SQL matches Published table filter' );
foreach ( $published_filter['items'] as $row ) {
	wpcable_assert_equals( 'published', $row['state'], 'published filter state' );
	wpcable_assert( 'lost' !== $row['flag'], 'published filter excludes lost flag including mixed row 4041' );
}

$lost_filter = $tasks->query_tasks(
	array(
		'page'     => 1,
		'per_page' => 100,
		'lost'     => true,
	)
);
wpcable_assert_equals( $lost_filter['total'], $filter_counts['lost'], 'Lost KPI SQL matches Lost table filter' );

$completed_filter = $tasks->query_tasks(
	array(
		'page'      => 1,
		'per_page'  => 100,
		'completed' => true,
	)
);
wpcable_assert_equals( $completed_filter['total'], $filter_counts['completed'], 'Completed KPI SQL matches Completed table filter' );
wpcable_assert_equals( $filter_counts['all'], $tasks->query_tasks( array( 'state' => 'all', 'per_page' => 100 ) )['total'], 'All filter count matches unfiltered total' );

$state_counts = $tasks->get_state_counts();
wpcable_assert( $state_counts['published'] >= $filter_counts['published'], 'raw state published can exceed filter published when flag=lost is mixed' );
wpcable_assert_equals( $filter_counts['published'], $state_counts['filter_published'], 'KPI filter_published alias matches filter SQL' );

delete_option( 'wpcable_import_health' );
delete_option( 'wpcable_last_fetch' );
delete_option( 'wpcable_api_queue' );
update_option(
	'wpcable_account_details',
	array(
		'id'   => 42,
		'full_name' => 'Cached Expert',
	)
);

$snap = wpcable_import_health::snapshot_from_counts( 30, 0, true, array() );
wpcable_assert_equals( 'cached', $snap['profile']['status'], 'populated profile without attempt is cached, not success' );
wpcable_assert_equals( 0, $snap['profile']['succeeded_at'], 'cached profile does not invent succeeded_at' );
wpcable_assert( false !== strpos( $snap['profile']['message'], 'no recorded attempt' ), 'profile copy says no recorded attempt' );
wpcable_assert_equals( 'cached', $snap['tasks']['status'], 'local task rows without attempt are cached' );
wpcable_assert_equals( 0, $snap['tasks']['succeeded_at'], 'cached tasks do not invent success time from last_sync' );
wpcable_assert_equals( 'never', $snap['transactions']['status'], 'missing transactions stay no recorded attempt, not success' );
wpcable_assert_equals( 0, $snap['transactions']['succeeded_at'], 'absent transactions do not invent success' );
wpcable_assert( false !== strpos( $snap['transactions']['message'], 'not $0' ), 'transactions copy says missing is not $0' );
wpcable_assert_equals( '', wpcable_import_health::success_time_label( $snap['tasks'] ), 'success_time_label empty unless instrumented success' );

$queue = array(
	array(
		'task'  => 'task:preferred',
		'label' => 'Tasks (preferred)',
		'page'  => 2,
		'paged' => true,
	),
);
$busy = wpcable_import_health::snapshot_from_counts( 30, 0, true, $queue );
wpcable_assert_equals( 'in_progress', $busy['tasks']['status'], 'pending task queue shows in-progress source state' );
wpcable_assert_equals( 'cached', $busy['profile']['status'], 'profile not in queue stays cached' );
wpcable_assert( false !== strpos( $busy['tasks']['message'], 'Refresh in progress' ), 'in-progress message names the running refresh' );

update_option( 'wpcable_last_fetch', $ok_ts );
update_option( 'wpcable_api_queue', array() );
codeable_maybe_refresh_data();
wpcable_assert( empty( get_option( 'wpcable_api_queue' ) ), 'maybe_refresh does not enqueue a new queue' );
codeable_maybe_refresh_data( true );
wpcable_assert( empty( get_option( 'wpcable_api_queue' ) ), 'maybe_refresh($force) still does not enqueue' );

$pending = array(
	array(
		'task'  => 'task:archived',
		'label' => 'Tasks (archived)',
		'page'  => 4,
	),
);
update_option( 'wpcable_api_queue', $pending );
codeable_maybe_refresh_data();
wpcable_assert_equals( $pending, get_option( 'wpcable_api_queue' ), 'maybe_refresh leaves an existing pending queue in place' );

delete_option( 'wpcable_sync_lock' );
wpcable_assert( wpcable_sync_lock_acquire( 30 ), 'first lock acquire succeeds' );
wpcable_assert( ! wpcable_sync_lock_acquire( 30 ), 'second lock acquire fails while held (concurrent safeguard)' );
wpcable_assert( wpcable_sync_lock_held(), 'lock reports held' );
wpcable_sync_lock_release();
wpcable_assert( ! wpcable_sync_lock_held(), 'lock release clears the lock' );
wpcable_assert( wpcable_sync_lock_acquire( 30 ), 'lock can be acquired after release' );
update_option(
	'wpcable_sync_lock',
	array(
		'until'   => time() - 10,
		'started' => time() - 20,
	)
);
wpcable_assert( wpcable_sync_lock_acquire( 30 ), 'expired lock can be acquired' );
wpcable_sync_lock_release();

$css = file_get_contents( WPCABLE_TEST_ROOT . '/assets/css/wpcable-workspace.css' );
wpcable_assert( false !== strpos( $css, 'img.wpcable-avatar' ), 'plugin CSS targets workspace avatars' );
wpcable_assert( false !== strpos( $css, 'max-width: 32px' ), 'avatar max-width constrained' );
wpcable_assert( false !== strpos( $css, 'max-height: 32px' ), 'avatar max-height constrained' );
wpcable_assert( false !== strpos( $css, 'object-fit: cover' ), 'avatar uses object-fit cover' );

$js = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable.js' );
wpcable_assert( false !== strpos( $js, 'wpcable_sync_process' ), 'sync process still used to resume pending work' );
wpcable_assert( false !== strpos( $js, '_wpnonce' ), 'sync posts a nonce' );
wpcable_assert( false === strpos( $js, 'Check for new sync process every 5 minutes' ), 'five-minute auto poll for new queues is gone' );

$history = wpcable_task_history_unavailable( $sentinel_task );
wpcable_assert_equals( 'https://app.codeable.io/tasks/{id}/workroom', $history['workroom']['pattern'], 'workroom pattern is the existing Stats URL shape' );
wpcable_assert( empty( $history['comments']['available'] ), 'no fabricated comments feed' );
