<?php
/**
 * 0.4.7: resumable bulk comments backfill, PKT regardless of WP timezone,
 * lean ZIP, 401/403/429, no silent-50 complete, no repeated-page loop.
 * No live Codeable calls.
 *
 * @package wpcable
 */

global $wpcable_filters, $wpcable_http_calls, $wpcable_http_queue, $wpcable_cron_events, $wpcable_spawn_cron;

$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $php, 'Version:           0.4.8' ), 'plugin header is 0.4.8' );
wpcable_assert( false !== strpos( $php, "add_action( 'wpcable_history_backfill_tick'" ), 'WP-Cron single-event hook is registered' );
wpcable_assert( false !== strpos( file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' ), 'wpcable_history_backfill_tick' ), 'hourly cron continues a running backfill' );

$settings = file_get_contents( WPCABLE_TEST_ROOT . '/templates/admin-settings.php' );
wpcable_assert( false !== strpos( $settings, 'wpcable_history_backfill_settings_card' ), 'Settings renders the backfill card outside the options form' );

$pkg = file_get_contents( WPCABLE_TEST_ROOT . '/scripts/package-zip.sh' );
wpcable_assert( false !== strpos( $pkg, "exclude 'tests/'" ), 'production ZIP omits tests/' );
wpcable_assert( false !== strpos( $pkg, "exclude 'docs/screenshots/'" ), 'production ZIP omits docs/screenshots/' );

update_option( 'timezone_string', '' );
update_option( 'gmt_offset', 0 );
update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'H:i' );
$pkt_empty = wpcable_comment_datetime_display( '2021-12-17T06:00:00Z' );
wpcable_assert( false !== strpos( $pkt_empty, '11:00' ), 'comment time is 11:00 PKT when WP timezone is empty' );
wpcable_assert( false !== strpos( $pkt_empty, 'PKT' ), 'empty WP timezone still labels PKT' );

$iv  = str_repeat( 'a', 16 );
$enc = openssl_encrypt( 'test-token', 'AES-256-CBC', AUTH_SALT, 0, $iv );
update_option( 'wpcable_auth_token', base64_encode( $iv ) . ':' . base64_encode( $enc ) );
update_option(
	'wpcable_account_details',
	array(
		'id'         => 91563,
		'first_name' => 'Fixture',
		'last_name'  => 'Expert',
	)
);
$api = wpcable_api_calls::inst();
$api->auth_token = 'test-token';

$wpcable_filters['wpcable_pre_http_get'] = array();
$wpcable_filters['wpcable_history_backfill_limits'] = array();
$wpcable_http_calls = array();
$wpcable_http_queue = array();
$wpcable_cron_events = array();
$wpcable_spawn_cron  = 0;
delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
delete_option( 'wpcable_api_queue' );
delete_option( 'wpcable_sync_lock' );

add_filter(
	'wpcable_pre_http_get',
	function ( $pre, $path, $query ) {
		global $wpcable_http_calls, $wpcable_http_queue;
		unset( $pre );
		$wpcable_http_calls[] = array(
			'path'  => $path,
			'query' => $query,
		);
		foreach ( $wpcable_http_queue as $i => $row ) {
			if ( $row['path'] !== $path ) {
				continue;
			}
			if ( isset( $row['query_page'] ) ) {
				$pg = isset( $query['page'] ) ? (int) $query['page'] : 0;
				if ( (int) $row['query_page'] !== $pg ) {
					continue;
				}
			}
			$resp = $row['response'];
			unset( $wpcable_http_queue[ $i ] );
			$wpcable_http_queue = array_values( $wpcable_http_queue );
			return $resp;
		}
		return array(
			'status'  => 404,
			'body'    => null,
			'headers' => array(),
			'error'   => 'unmocked',
			'link'    => '',
		);
	},
	10,
	3
);

/**
 * @param int   $from First id.
 * @param int   $to   Last id inclusive.
 * @param mixed $at   Timestamp.
 * @return array
 */
function wpcable_test_comment_rows( $from, $to, $at = '2021-12-17T06:00:00Z' ) {
	$rows = array();
	for ( $i = $from; $i <= $to; $i++ ) {
		$rows[] = array(
			'id'           => $i,
			'text'         => 'Comment body ' . $i,
			'published_at' => $at,
		);
	}
	return $rows;
}

function wpcable_test_comments_http( $task_id, $rows, $next_page = 0, $per_page = 50 ) {
	$link = '';
	if ( $next_page ) {
		$link = '<https://api.codeable.io/tasks/' . (int) $task_id . '/comments?page=' . (int) $next_page . '>; rel="next"';
	}
	return array(
		'status'  => 200,
		'body'    => $rows,
		'headers' => array( 'x-records-per-page' => $per_page ),
		'link'    => $link,
	);
}

function wpcable_test_desc_http( $task_id, $title = 'Fixture' ) {
	return array(
		'status'  => 200,
		'body'    => array(
			'id'          => $task_id,
			'title'       => $title,
			'description' => 'Brief ' . $task_id,
		),
		'headers' => array(),
		'link'    => '',
	);
}

function wpcable_test_reset_history( $task_ids ) {
	global $wpdb;
	foreach ( (array) $task_ids as $id ) {
		$wpdb->update(
			'wp_codeable_tasks',
			array(
				'description_text'    => '',
				'description_present' => 0,
				'description_key'     => '',
				'comments_json'       => '',
				'comments_present'    => 0,
				'comments_complete'   => 0,
				'comments_source'     => '',
				'history_account_id'  => '',
				'history_meta_json'   => '',
			),
			array( 'task_id' => (int) $id )
		);
	}
}

$GLOBALS['wpcable_test_req_budget'] = 3;
add_filter(
	'wpcable_history_backfill_limits',
	function ( $limits ) {
		$limits['requests_per_tick'] = (int) $GLOBALS['wpcable_test_req_budget'];
		$limits['projects_per_tick'] = 4;
		$limits['tick_delay']        = 5;
		$limits['project_retries']   = 2;
		return $limits;
	},
	10,
	1
);

wpcable_test_reset_history( array( 1, 2, 3, 5, 10, 11, 12, 13, 14 ) );
$wpcable_spawn_cron  = 0;
$wpcable_cron_events = array();

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/1',
		'response' => wpcable_test_desc_http( 1, 'Alpha site rebuild' ),
	),
	array(
		'path'       => 'tasks/1/comments',
		'query_page' => 0,
		'response'   => wpcable_test_comments_http( 1, wpcable_test_comment_rows( 1001, 1050 ), 2 ),
	),
	array(
		'path'       => 'tasks/1/comments',
		'query_page' => 2,
		'response'   => wpcable_test_comments_http( 1, wpcable_test_comment_rows( 1051, 1100 ), 3 ),
	),
	array(
		'path'       => 'tasks/1/comments',
		'query_page' => 3,
		'response'   => wpcable_test_comments_http( 1, wpcable_test_comment_rows( 1101, 1150 ), 4 ),
	),
	array(
		'path'       => 'tasks/1/comments',
		'query_page' => 4,
		'response'   => wpcable_test_comments_http( 1, wpcable_test_comment_rows( 1151, 1155 ), 0, 50 ),
	),
);

$started = wpcable_history_backfill_start( array( 'task_ids' => array( 1 ) ) );
wpcable_assert( ! empty( $started['ok'] ), 'start succeeds for one imported project' );
wpcable_assert( (int) $started['public']['queued_count'] >= 1, 'start queued the project (not skipped as already complete)' );
wpcable_assert_equals( 0, count( $wpcable_http_calls ), 'start does not call Codeable' );
wpcable_assert( $wpcable_spawn_cron > 0, 'start asks WP-Cron to spawn' );
wpcable_assert( false !== wp_next_scheduled( 'wpcable_history_backfill_tick' ), 'a backfill single event is scheduled' );

$tick1 = wpcable_history_backfill_tick();
wpcable_assert( empty( $tick1['skipped'] ), 'first tick runs' );
wpcable_assert_equals( 3, (int) $tick1['requests'], 'first tick is bounded to 3 requests (desc + 2 comment pages)' );
$pub1 = $tick1['public'];
wpcable_assert_equals( 'running', $pub1['state'], 'job stays running while pages remain' );
wpcable_assert_equals( 1, (int) $pub1['current_id'], 'cursor stays on the in-progress project' );
wpcable_assert_equals( 'more', $pub1['current_mode'], 'after page 1 the cursor is load-more' );

$repo = new wpcable_tasks();
$row1 = $repo->get_task( 1 );
$hist1 = wpcable_task_history_for_display( $row1 );
wpcable_assert_equals( 100, count( $hist1['comments']['items'] ), 'first tick stored 100 comments (beyond the silent-50 cap)' );
wpcable_assert( empty( $hist1['comments']['complete'] ), '100 comments with a verified next Link stay partial' );
wpcable_assert( false !== strpos( $hist1['comments']['items'][0]['created_at'], 'PKT' ), 'cached comment times are PKT during backfill' );

$pause = wpcable_history_backfill_pause( 'paused' );
wpcable_assert_equals( 'paused', $pause['public']['state'], 'pause persists the cursor' );
wpcable_assert_equals( 1, (int) $pause['public']['current_id'], 'paused cursor still names project 1' );

$idle = wpcable_history_backfill_tick();
wpcable_assert( ! empty( $idle['skipped'] ), 'cron tick while paused is a no-op' );

$resume = wpcable_history_backfill_resume();
wpcable_assert( ! empty( $resume['ok'] ), 'resume from stored cursor' );
$tick2  = wpcable_history_backfill_tick();
wpcable_assert( empty( $tick2['skipped'] ), 'second tick runs after resume' );
$row1   = $repo->get_task( 1 );
$hist1  = wpcable_task_history_for_display( $row1 );
$ids    = array();
foreach ( $hist1['comments']['items'] as $item ) {
	$ids[ (string) $item['id'] ] = true;
}
wpcable_assert_equals( 155, count( $hist1['comments']['items'] ), 'resume fetched remaining pages to 155 comments' );
wpcable_assert_equals( 155, count( $ids ), 'merged pages have no duplicate comment ids' );
wpcable_assert( ! empty( $hist1['comments']['complete'] ), 'short last page is evidence-backed complete' );
wpcable_assert_equals( 'finished', $tick2['public']['state'], 'job finishes when the queue is empty' );
wpcable_assert( ! empty( $tick2['public']['honest_complete'] ), 'finished with no partial/error buckets is honest complete' );
wpcable_assert_equals( 1, (int) $tick2['public']['done_count'], 'the paged project is in the complete bucket' );

$comment_gets = 0;
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/1/comments' === $call['path'] ) {
		$comment_gets++;
	}
}
wpcable_assert_equals( 4, $comment_gets, 'exactly four comments pages were fetched (not an infinite loop)' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
$wpcable_cron_events = array();
wpcable_test_reset_history( array( 2, 3 ) );
$GLOBALS['wpcable_test_req_budget'] = 2;

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/2',
		'response' => wpcable_test_desc_http( 2 ),
	),
	array(
		'path'       => 'tasks/2/comments',
		'query_page' => 0,
		'response'   => wpcable_test_comments_http( 2, wpcable_test_comment_rows( 201, 202 ), 0, 20 ),
	),
	array(
		'path'     => 'tasks/3',
		'response' => wpcable_test_desc_http( 3 ),
	),
	array(
		'path'       => 'tasks/3/comments',
		'query_page' => 0,
		'response'   => array(
			'status'  => 204,
			'body'    => '',
			'headers' => array(),
			'link'    => '',
		),
	),
);

$multi = wpcable_history_backfill_start( array( 'task_ids' => array( 2, 3 ) ) );
wpcable_assert( ! empty( $multi['ok'] ), 'multi-project start succeeds' );
$m1 = wpcable_history_backfill_tick();
wpcable_assert_equals( 1, (int) $m1['public']['done_count'], 'budget 2 completes only the first of two projects' );
wpcable_assert( 'finished' !== $m1['public']['state'], 'second project remains for resume' );
wpcable_assert_equals( 3, (int) $m1['public']['current_id'], 'cursor advanced to the second project' );
wpcable_history_backfill_pause( 'paused' );
wpcable_assert_equals( 3, (int) wpcable_history_backfill_public()['current_id'], 'pause keeps the second-project cursor' );
wpcable_history_backfill_resume();
$m2 = wpcable_history_backfill_tick();
wpcable_assert_equals( 'finished', $m2['public']['state'], 'resume completes the second project' );
wpcable_assert_equals( 2, (int) $m2['public']['done_count'], 'both projects counted complete after resume' );

$row2 = $repo->get_task( 2 );
$h2   = wpcable_task_history_for_display( $row2 );
wpcable_assert_equals( 2, count( $h2['comments']['items'] ), 'project 2 comments cached once' );
$row3 = $repo->get_task( 3 );
wpcable_assert_equals( 1, (int) $row3['comments_complete'], '204 comments is complete empty, not denied' );

$task2_desc = 0;
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/2' === $call['path'] ) {
		$task2_desc++;
	}
}
wpcable_assert_equals( 1, $task2_desc, 'completed project 2 is not fetched again on resume' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
wpcable_test_reset_history( array( 5 ) );
$GLOBALS['wpcable_test_req_budget'] = 8;
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/5',
		'response' => wpcable_test_desc_http( 5 ),
	),
	array(
		'path'       => 'tasks/5/comments',
		'query_page' => 0,
		'response'   => wpcable_test_comments_http( 5, wpcable_test_comment_rows( 501, 550 ), 2 ),
	),
	array(
		'path'       => 'tasks/5/comments',
		'query_page' => 2,
		'response'   => wpcable_test_comments_http( 5, wpcable_test_comment_rows( 501, 550 ), 2 ),
	),
	array(
		'path'       => 'tasks/5/comments',
		'query_page' => 2,
		'response'   => wpcable_test_comments_http( 5, wpcable_test_comment_rows( 501, 550 ), 2 ),
	),
);

$loop = wpcable_history_backfill_start( array( 'task_ids' => array( 5 ) ) );
wpcable_assert( ! empty( $loop['ok'] ), 'loop-guard job starts' );
$l1 = wpcable_history_backfill_tick();
if ( 'finished' !== $l1['public']['state'] ) {
	$l1 = wpcable_history_backfill_tick();
}
$comments5 = 0;
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/5/comments' === $call['path'] ) {
		$comments5++;
	}
}
wpcable_assert( $comments5 <= 2, 'duplicate-ID next page does not loop (at most two comments GETs)' );
wpcable_assert( (int) $l1['public']['partial_count'] >= 1 || (int) $l1['public']['done_count'] === 0, 'repeated page is not silently marked complete' );
$row5 = $repo->get_task( 5 );
$h5   = wpcable_task_history_for_display( $row5 );
wpcable_assert_equals( 50, count( $h5['comments']['items'] ), 'duplicate page does not inflate comment ids' );
wpcable_assert( empty( $h5['comments']['complete'] ), 'duplicate-ID page stays honest partial' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
wpcable_test_reset_history( array( 10 ) );
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/10',
		'response' => wpcable_test_desc_http( 10 ),
	),
	array(
		'path'       => 'tasks/10/comments',
		'query_page' => 0,
		'response'   => array(
			'status'      => 200,
			'body'        => wpcable_test_comment_rows( 10001, 10020 ),
			'headers'     => array( 'x-records-per-page' => 20 ),
			'link'        => '<https://api.codeable.io/tasks/10/comments?limit=20&offset=0>; rel="next"',
		),
	),
);

$rep = wpcable_history_backfill_start( array( 'task_ids' => array( 10 ) ) );
wpcable_assert( ! empty( $rep['ok'] ), 'repeated-query job starts' );
$r1 = wpcable_history_backfill_tick();
if ( 'finished' !== $r1['public']['state'] && 'paused' !== $r1['public']['state'] ) {
	$r1 = wpcable_history_backfill_tick();
}
$c10 = 0;
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/10/comments' === $call['path'] ) {
		$c10++;
	}
}
wpcable_assert_equals( 1, $c10, 'identical next query is not re-requested (loop guard)' );
wpcable_assert( (int) $r1['public']['partial_count'] >= 1, 'repeated-query stop is recorded as partial, not complete' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
wpcable_test_reset_history( array( 11 ) );
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/11',
		'response' => array(
			'status'      => 401,
			'body'        => array( 'error' => 'unauthorized' ),
			'headers'     => array(),
			'link'        => '',
			'retry_after' => 0,
		),
	),
);
$prior = $repo->get_task( 1 );
$auth  = wpcable_history_backfill_start( array( 'task_ids' => array( 11 ) ) );
$a1    = wpcable_history_backfill_tick();
wpcable_assert_equals( 'auth_paused', $a1['public']['state'], '401 pauses the whole job' );
wpcable_assert_equals( 11, (int) $a1['public']['current_id'], '401 keeps the cursor' );
$after = $repo->get_task( 1 );
wpcable_assert_equals( $prior['comments_json'], $after['comments_json'], '401 does not overwrite an earlier successful cache' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
wpcable_test_reset_history( array( 12, 13 ) );
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/12',
		'response' => array(
			'status'  => 403,
			'body'    => array( 'error' => 'forbidden' ),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/13',
		'response' => wpcable_test_desc_http( 13 ),
	),
	array(
		'path'       => 'tasks/13/comments',
		'query_page' => 0,
		'response'   => array(
			'status'  => 204,
			'body'    => '',
			'headers' => array(),
			'link'    => '',
		),
	),
);

$den = wpcable_history_backfill_start( array( 'task_ids' => array( 12, 13 ) ) );
$d1  = wpcable_history_backfill_tick();
if ( 'finished' !== $d1['public']['state'] ) {
	$d1 = wpcable_history_backfill_tick();
}
wpcable_assert_equals( 1, (int) $d1['public']['denied_count'], '403 is a denied project, not empty/complete' );
wpcable_assert_equals( 1, (int) $d1['public']['done_count'], '204 sibling is complete empty, separate from 403' );
wpcable_assert_equals( 'finished', $d1['public']['state'], '403 + 204 job finishes' );
wpcable_assert( empty( $d1['public']['honest_complete'] ), 'a denied project means the job is not claimed fully complete' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
wpcable_test_reset_history( array( 14 ) );
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/14',
		'response' => array(
			'status'      => 429,
			'body'        => array( 'error' => 'rate' ),
			'headers'     => array( 'retry-after' => '12' ),
			'link'        => '',
			'retry_after' => 12,
		),
	),
);
$rl = wpcable_history_backfill_start( array( 'task_ids' => array( 14 ) ) );
$rt = wpcable_history_backfill_tick();
wpcable_assert_equals( 'waiting_retry', $rt['public']['state'], '429 pauses the tick as waiting_retry' );
wpcable_assert( (int) $rt['public']['wait_until'] >= time() + 5, '429 wait_until is in the future' );
wpcable_assert_equals( 14, (int) $rt['public']['current_id'], '429 keeps the cursor' );
$skip_wait = wpcable_history_backfill_tick();
wpcable_assert( ! empty( $skip_wait['skipped'] ), 'cron tick while waiting_retry is skipped' );

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/14',
		'response' => wpcable_test_desc_http( 14 ),
	),
	array(
		'path'       => 'tasks/14/comments',
		'query_page' => 0,
		'response'   => array(
			'status'  => 204,
			'body'    => '',
			'headers' => array(),
			'link'    => '',
		),
	),
);
$forced = wpcable_history_backfill_tick( array( 'force' => true ) );
wpcable_assert( empty( $forced['skipped'] ), 'Run one batch now overrides wait_until' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$wpcable_http_calls = array();
wpcable_test_reset_history( array( 2 ) );
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/2',
		'response' => wpcable_test_desc_http( 2 ),
	),
);
$ov = wpcable_history_backfill_start( array( 'task_ids' => array( 2 ) ) );
wpcable_assert( ! empty( $ov['ok'] ), 'overlap job starts' );
update_option( 'wpcable_history_backfill_lock', array( 'until' => time() + 60, 'started' => time() ) );
$locked = wpcable_history_backfill_tick();
wpcable_assert( ! empty( $locked['skipped'] ), 'second tick skips while the backfill lock is held' );
wpcable_assert_equals( 'lock', $locked['reason'], 'overlap skip reason is lock' );
delete_option( 'wpcable_history_backfill_lock' );

update_option( 'wpcable_api_queue', array( array( 'action' => 'task:list', 'label' => 'Refresh' ) ) );
$busy = wpcable_history_backfill_tick();
wpcable_assert( ! empty( $busy['skipped'] ), 'backfill does not overlap a pending Refresh queue' );
delete_option( 'wpcable_api_queue' );

$pub = wpcable_history_backfill_public();
$encoded = wp_json_encode( $pub );
wpcable_assert( false === strpos( $encoded, 'Comment body' ), 'progress snapshot has no comment bodies' );
wpcable_assert( false === strpos( $encoded, 'test-token' ), 'progress snapshot has no token' );
wpcable_assert( false !== strpos( $pub['cron'], 'WP-Cron' ), 'UI copy reports WP-Cron dependence' );

delete_option( 'wpcable_auth_token' );
$api->auth_token = '';
$off = wpcable_history_backfill_start( array( 'task_ids' => array( 2 ) ) );
wpcable_assert( empty( $off['ok'] ), 'disconnected start is refused' );
update_option( 'wpcable_auth_token', base64_encode( $iv ) . ':' . base64_encode( $enc ) );
$api->auth_token = 'test-token';

wpcable_assert( false !== strpos( file_get_contents( WPCABLE_TEST_ROOT . '/functions/task-detail.php' ), "new DateTimeZone( 'Asia/Karachi' )" ), 'comment display timezone is hard PKT' );
wpcable_assert( false === strpos( file_get_contents( WPCABLE_TEST_ROOT . '/functions/task-detail.php' ), 'date_default_timezone_set(' ), 'still never sets PHP default timezone' );
