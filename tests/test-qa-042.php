<?php
/**
 * 0.4.2: Tasks renderer $root scope, timezone provenance, estimator unchanged.
 *
 * @package wpcable
 */

$js  = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable-workspace.js' );
$leg = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable.js' );
$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );

wpcable_assert( false !== strpos( $js, 'function applyCounts(data, $scope)' ), 'applyCounts takes an explicit $scope' );
wpcable_assert( false !== strpos( $js, 'applyCounts(data, $root)' ), 'renderRows passes the tasks $root into applyCounts' );
wpcable_assert( false !== strpos( $js, 'function unwrapWpAjax' ), 'WP ajax envelope unwrap exists' );
wpcable_assert( false !== strpos( $js, '.fail(function' ), 'task load has an ajax fail handler' );
wpcable_assert( false !== strpos( $js, 'showTableMessage' ), 'Loading/error message helper exists' );

if ( preg_match( '/function applyCounts\(data, \$scope\) \{.*?\n\tfunction historyPanels/s', $js, $m ) ) {
	wpcable_assert( false === strpos( $m[0], '$root' ), 'applyCounts body does not reference unbound $root' );
} else {
	wpcable_assert( false, 'could not extract applyCounts body' );
}

wpcable_assert( false !== strpos( $leg, "jQuery('.wrap.wpcable-workspace').length" ), 'legacy wpcable.js skips workspace pages' );

$tasks_src = file_get_contents( WPCABLE_TEST_ROOT . '/classes/tasks.php' );
wpcable_assert( false === strpos( $tasks_src, 'date_default_timezone_set' ), 'sanitize_task never changes PHP default timezone' );

wpcable_assert( false !== strpos( $php, 'Version:           0.4.3' ), 'plugin header is 0.4.3' );

$estimate_js = file_get_contents( WPCABLE_TEST_ROOT . '/templates/admin-estimate.php' );
wpcable_assert( false !== strpos( $estimate_js, 'var estimate_hours_standard    = (optimistic + 4 * likely + pessimistic) / 6;' ), 'standard PERT formula unchanged' );
wpcable_assert( false !== strpos( $estimate_js, 'var estimate_hours_pessimistic = (optimistic + 2 * likely + 3 * pessimistic) / 6;' ), 'cautious PERT formula unchanged' );

$before_tz = date_default_timezone_get();
update_option( 'timezone_string', 'Asia/Karachi' );
update_option( 'gmt_offset', 5 );
update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'g:ia' );

$utc_1713 = gmmktime( 17, 13, 0, 11, 6, 2026 );
$formatted = wpcable_date( $utc_1713 );
$label     = wpcable_timezone_label();

wpcable_assert( date_default_timezone_get() === $before_tz, 'wpcable_date does not change PHP default timezone' );
wpcable_assert( false !== strpos( $formatted, '10:13pm' ) || false !== strpos( $formatted, '22:13' ), '17:13 UTC displays as 22:13 / 10:13pm in PKT' );
wpcable_assert( false !== stripos( $formatted, 'PKT' ) || false !== strpos( $formatted, 'Asia/Karachi' ) || false !== strpos( $label, 'PKT' ), 'formatted datetime is labeled PKT (or Asia/Karachi)' );
wpcable_assert( false === strpos( $formatted, '5:13pm' ) && false === strpos( $formatted, '17:13' ), 'does not show UTC wall clock labeled as local (no 5:13pm / 17:13)' );

$task_repo = new wpcable_tasks();
$wpdb_task = array(
	'task_id'          => 4242,
	'client_id'        => 101,
	'title'            => 'Timezone provenance row',
	'estimate'         => 0,
	'hidden'           => 0,
	'promoted'         => 0,
	'subscribed'       => 0,
	'favored'          => 0,
	'preferred'        => 0,
	'client_fee'       => 10,
	'state'            => 'published',
	'kind'             => 'task',
	'value'            => 0,
	'value_client'     => 0,
	'last_activity'    => $utc_1713,
	'last_activity_by' => 'Fixture',
	'last_sync'        => $utc_1713,
	'flag'             => '',
	'notes'            => '',
	'client_name'      => 'Fixture Client Alpha',
	'avatar'           => '',
);
$sanitized = $task_repo->sanitize_task( $wpdb_task );
wpcable_assert( date_default_timezone_get() === $before_tz, 'sanitize_task does not change PHP default timezone' );
wpcable_assert( ! empty( $sanitized['last_activity_date'] ), 'valid activity still formats a date' );

update_option( 'timezone_string', '' );
update_option( 'gmt_offset', 0 );
$utc_label = wpcable_timezone_label();
$utc_fmt   = wpcable_date( $utc_1713 );
wpcable_assert(
	false === strpos( $utc_fmt, 'PKT' ) && false === strpos( $utc_label, 'PKT' ),
	'UTC WordPress timezone is not labeled PKT'
);
wpcable_assert(
	false !== strpos( $utc_fmt, 'UTC' )
		|| false !== strpos( $utc_fmt, 'GMT' )
		|| in_array( $utc_label, array( 'UTC', 'GMT', '+00', '+0000' ), true ),
	'UTC WordPress timezone is labeled UTC/GMT'
);
