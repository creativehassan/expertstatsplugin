<?php
/**
 * 0.4.9: live root-relative Link </tasks/{id}/comments?page=2> on 163186
 * (50 of 674, 14 pages). Resolve onto api.codeable.io with strict integer
 * page. No scheme-relative, userinfo, fragments, traversal, encoded bypass,
 * mismatched ids, or arbitrary hosts. No live Codeable calls.
 *
 * @package wpcable
 */

$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $php, 'Version:           0.4.9' ), 'plugin header is 0.4.9' );

$ok_rel = wpcable_comments_link_replay( '/tasks/15/comments?page=2', 15 );
wpcable_assert( ! empty( $ok_rel['ok'] ), 'live-shaped root-relative next is replayable' );
wpcable_assert_equals( 'resolved_root_relative_onto_api.codeable.io', $ok_rel['reason'], 'reason records same-host relative resolution' );
wpcable_assert_equals( 'tasks/15/comments', $ok_rel['path'], 'replay path is documented comments collection' );
wpcable_assert_equals( 2, (int) $ok_rel['query']['page'], 'strict integer page=2' );
wpcable_assert( ! isset( $ok_rel['query']['offset'] ), 'page-based next does not keep offset' );
wpcable_assert( ! isset( $ok_rel['query']['limit'] ), 'page-based next does not keep first-page limit' );

$rej = array(
	'//evil.example/tasks/15/comments?page=2'                 => 'scheme_relative_rejected',
	'//api.codeable.io/tasks/15/comments?page=2'              => 'scheme_relative_rejected',
	'https://user:pass@api.codeable.io/tasks/15/comments?page=2' => 'userinfo_rejected',
	'/tasks/15/comments?page=2#frag'                          => 'fragment_rejected',
	'/tasks/15/comments/../comments?page=2'                   => 'unsafe_relative_path',
	'/tasks/15/comments%2f../comments?page=2'                 => 'unsafe_relative_path',
	'tasks/15/comments?page=2'                                => 'not_root_relative',
	'/tasks/99/comments?page=2'                               => 'task_id_mismatch',
	'https://evil.example/tasks/15/comments?page=2'           => 'host_not_replayable',
	'/v1/tasks/15/comments?page=2'                            => 'v1_prefix_not_verified',
	'/users/me/tasks/15/comments?page=2'                      => 'path_not_documented_comments',
	'/tasks/15/comments'                                      => 'relative_page_not_integer',
	'/tasks/15/comments?page=foo'                             => 'relative_page_not_integer',
);
foreach ( $rej as $url => $want ) {
	$got = wpcable_comments_link_replay( $url, 15 );
	wpcable_assert( empty( $got['ok'] ), 'unsafe/mismatched Link is not replayed: ' . $url );
	wpcable_assert_equals( $want, $got['reason'], 'rejection reason for ' . $url );
}

$abs = wpcable_comments_link_replay( 'https://api.codeable.io/tasks/15/comments?page=2', 15 );
wpcable_assert( ! empty( $abs['ok'] ), 'absolute same-host documented next still replays' );

$mixed = wpcable_comments_follow_query( array( 'page' => 2, 'offset' => 0, 'limit' => 20 ) );
wpcable_assert_equals( array( 'page' => 2 ), $mixed, 'follow query drops first-page offset when page is present' );

/**
 * Live-shaped comments rows (keys from 163186).
 *
 * @param int $from First id.
 * @param int $to   Last id inclusive.
 * @return array
 */
function wpcable_test_049_rows( $from, $to ) {
	$rows = array();
	for ( $i = $from; $i <= $to; $i++ ) {
		$rows[] = array(
			'id'                  => $i,
			'task_id'             => 15,
			'user_id'             => ( 1 === ( $i % 3 ) ) ? 101 : ( ( 2 === ( $i % 3 ) ) ? 91563 : 999001 ),
			'text'                => 'Comment body ' . $i,
			'is_private'          => ( 0 === ( $i % 2 ) ),
			'created_at'          => '2021-12-17T06:00:00Z',
			'updated_at'          => '2021-12-17T06:00:00Z',
			'is_contractors_only' => false,
			'is_from_email'       => false,
		);
	}
	return $rows;
}

/**
 * One live-shaped comments HTTP page (relative next Link + totals).
 *
 * @param int $task_id Task id.
 * @param int $page    1-based page.
 * @param int $total   X-Total-Count.
 * @param int $per     X-Records-Per-Page.
 * @return array
 */
function wpcable_test_049_page_http( $task_id, $page, $total = 674, $per = 50 ) {
	$page_count = (int) ceil( $total / $per );
	$start      = ( ( $page - 1 ) * $per ) + 1;
	$end        = min( $page * $per, $total );
	$next       = ( $page < $page_count ) ? $page + 1 : 0;
	$link       = $next ? '</tasks/' . (int) $task_id . '/comments?page=' . (int) $next . '>; rel="next"' : '';
	return array(
		'status'  => 200,
		'body'    => wpcable_test_049_rows( $start, $end ),
		'headers' => array(
			'x-total-count'      => $total,
			'x-records-per-page' => $per,
			'x-page-count'       => $page_count,
		),
		'link'    => $link,
	);
}

/**
 * Queue: description + 14 comments pages for 674 unique ids.
 *
 * @param int $task_id Task id.
 * @return array
 */
function wpcable_test_049_queue( $task_id ) {
	$queue   = array(
		array(
			'path'     => 'tasks/' . (int) $task_id,
			'response' => array(
				'status'  => 200,
				'body'    => array(
					'id'          => (int) $task_id,
					'title'       => 'Live-shape 674',
					'description' => 'Brief ' . (int) $task_id,
				),
				'headers' => array(),
				'link'    => '',
			),
		),
	);
	$pages = 14;
	for ( $p = 1; $p <= $pages; $p++ ) {
		$queue[] = array(
			'path'       => 'tasks/' . (int) $task_id . '/comments',
			'query_page' => ( 1 === $p ) ? 0 : $p,
			'response'   => wpcable_test_049_page_http( $task_id, $p ),
		);
	}
	return $queue;
}

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

global $wpcable_filters, $wpcable_http_calls, $wpcable_http_queue, $wpdb;
$wpcable_http_calls = array();
$wpcable_http_queue = array();
$wpcable_filters['wpcable_pre_http_get'] = array();
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
	20,
	3
);

$wpdb->update(
	'wp_codeable_tasks',
	array(
		'comments_json'      => '',
		'comments_present'   => 0,
		'comments_complete'  => 0,
		'history_meta_json'  => '',
		'history_account_id' => '',
	),
	array( 'task_id' => 15 )
);

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$job_idle = wpcable_history_backfill_job();
wpcable_assert_equals( 'idle', $job_idle['state'], 'bulk job remains idle unless started' );

$wpcable_http_queue = wpcable_test_049_queue( 15 );
$wpcable_http_calls = array();

$first = wpcable_load_task_history_run( 15 );
wpcable_assert( ! empty( $first['ok'] ), 'first Load of live-shaped 50/674 saves' );
wpcable_assert( empty( $first['comments']['complete'] ), '50 of 674 stays partial' );
wpcable_assert( ! empty( $first['comments']['next_replay']['ok'] ), 'root-relative next is verified for Load more' );
wpcable_assert_equals( array( 'page' => 2 ), $first['comments']['next_replay']['query'], 'stored next query is only page=2' );

$repo = new wpcable_tasks();
$row  = $repo->get_task( 15 );
$hist = wpcable_task_history_for_display( $row );
wpcable_assert_equals( 50, count( $hist['comments']['items'] ), 'first page caches 50 comments' );
wpcable_assert_equals( 674, (int) $hist['comments']['total'], 'X-Total-Count 674 is stored' );
wpcable_assert_equals( 50, (int) $hist['comments']['records_per_page'], 'X-Records-Per-Page 50 informs progress' );
wpcable_assert_equals( 14, (int) $hist['comments']['page_count'], 'X-Page-Count 14 is stored' );
wpcable_assert( ! empty( $hist['can_load_more'] ), 'Load more is offered for verified root-relative next' );
wpcable_assert_equals( 'resolved_root_relative_onto_api.codeable.io', $hist['link_next']['replay_reason'], 'drawer reason is resolved root-relative' );

$first_comment_queries = array();
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/15/comments' === $call['path'] ) {
		$first_comment_queries[] = $call['query'];
	}
}
wpcable_assert_equals( 1, count( $first_comment_queries ), 'first Load makes one comments GET' );
wpcable_assert( isset( $first_comment_queries[0]['offset'] ), 'first comments GET is still limit/offset' );
wpcable_assert( ! isset( $first_comment_queries[0]['page'] ), 'first comments GET does not send page=' );

$wpcable_http_calls = array();
$more = wpcable_load_task_history_run( 15, array( 'more' => true ) );
wpcable_assert( ! empty( $more['ok'] ), 'Load more page 2 succeeds' );
$more_queries = array();
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/15/comments' === $call['path'] ) {
		$more_queries[] = $call['query'];
	}
}
wpcable_assert_equals( 1, count( $more_queries ), 'Load more makes one comments GET' );
wpcable_assert_equals( 2, (int) $more_queries[0]['page'], 'Load more sends page=2' );
wpcable_assert( ! isset( $more_queries[0]['offset'] ), 'Load more does not resend first-page offset' );
wpcable_assert( ! isset( $more_queries[0]['limit'] ), 'Load more does not resend first-page limit' );

$row  = $repo->get_task( 15 );
$hist = wpcable_task_history_for_display( $row );
$ids  = array();
foreach ( $hist['comments']['items'] as $item ) {
	$ids[ (string) $item['id'] ] = true;
}
wpcable_assert_equals( 100, count( $hist['comments']['items'] ), 'page 1+2 cache 100 comments' );
wpcable_assert_equals( 100, count( $ids ), 'page 2 does not duplicate the first 50 ids' );
wpcable_assert( empty( $hist['comments']['complete'] ), '100 of 674 stays partial' );

$pages = 2;
while ( ! empty( $hist['can_load_more'] ) && $pages < 20 ) {
	$step = wpcable_load_task_history_run( 15, array( 'more' => true ) );
	wpcable_assert( ! empty( $step['ok'] ), 'Load more page ' . ( $pages + 1 ) . ' succeeds' );
	$row  = $repo->get_task( 15 );
	$hist = wpcable_task_history_for_display( $row );
	$pages++;
}
wpcable_assert_equals( 14, $pages, 'manual Load more walks all 14 pages' );
$ids = array();
foreach ( $hist['comments']['items'] as $item ) {
	$ids[ (string) $item['id'] ] = true;
}
wpcable_assert_equals( 674, count( $hist['comments']['items'] ), 'manual Load more ends at 674 comments' );
wpcable_assert_equals( 674, count( $ids ), '674 comments are unique ids' );
wpcable_assert( ! empty( $hist['comments']['complete'] ), 'X-Total-Count 674 marks complete' );
wpcable_assert( empty( $hist['can_load_more'] ), 'Load more hides when complete' );

$js = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable-workspace.js' );
wpcable_assert( false !== strpos( $js, 'wpcable-comment-progress' ), 'drawer JS can show saved of total progress' );
wpcable_assert( false !== strpos( $js, 'replay_ok' ), 'drawer JS distinguishes verified vs rejected next Link' );

$wpdb->update(
	'wp_codeable_tasks',
	array(
		'comments_json'      => '',
		'comments_present'   => 0,
		'comments_complete'  => 0,
		'history_meta_json'  => '',
		'history_account_id' => '',
	),
	array( 'task_id' => 15 )
);
delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$GLOBALS['wpcable_test_req_budget'] = 8;
$wpcable_http_queue = wpcable_test_049_queue( 15 );
$wpcable_http_calls = array();

$started = wpcable_history_backfill_start( array( 'task_ids' => array( 15 ) ) );
wpcable_assert( ! empty( $started['ok'] ), 'bulk start for the 674-page project' );
wpcable_assert_equals( 0, count( $wpcable_http_calls ), 'bulk start still does not HTTP' );

$ticks = 0;
$pub   = array();
while ( $ticks < 8 ) {
	$tick = wpcable_history_backfill_tick();
	$ticks++;
	$pub = $tick['public'];
	if ( 'finished' === $pub['state'] ) {
		break;
	}
}
wpcable_assert_equals( 'finished', $pub['state'], 'bulk finishes the 14-page project' );
wpcable_assert( ! empty( $pub['honest_complete'] ), 'bulk 674 is evidence-backed complete' );
wpcable_assert_equals( 1, (int) $pub['done_count'], 'the 674 project is in the complete bucket' );

$row  = $repo->get_task( 15 );
$hist = wpcable_task_history_for_display( $row );
$ids  = array();
foreach ( $hist['comments']['items'] as $item ) {
	$ids[ (string) $item['id'] ] = true;
}
wpcable_assert_equals( 674, count( $hist['comments']['items'] ), 'bulk cache is 674 comments' );
wpcable_assert_equals( 674, count( $ids ), 'bulk 674 ids are unique' );
wpcable_assert( ! empty( $hist['comments']['complete'] ), 'bulk marks complete from X-Total-Count' );

$comment_gets = 0;
$page_gets    = array();
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/15/comments' !== $call['path'] ) {
		continue;
	}
	$comment_gets++;
	$page_gets[] = isset( $call['query']['page'] ) ? (int) $call['query']['page'] : 0;
}
wpcable_assert_equals( 14, $comment_gets, 'bulk fetched 14 comments pages (not a loop)' );
wpcable_assert_equals( 0, $page_gets[0], 'bulk first comments GET is offset/limit (no page)' );
wpcable_assert_equals( range( 2, 14 ), array_slice( $page_gets, 1 ), 'bulk pages 2-14 progress in order' );

$wpdb->update(
	'wp_codeable_tasks',
	array(
		'comments_json'      => '',
		'comments_present'   => 0,
		'comments_complete'  => 0,
		'history_meta_json'  => '',
		'history_account_id' => '',
	),
	array( 'task_id' => 15 )
);
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/15',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				'id'          => 15,
				'title'       => 'Dup page',
				'description' => 'Brief',
			),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'       => 'tasks/15/comments',
		'query_page' => 0,
		'response'   => wpcable_test_049_page_http( 15, 1 ),
	),
	array(
		'path'       => 'tasks/15/comments',
		'query_page' => 2,
		'response'   => array(
			'status'  => 200,
			'body'    => wpcable_test_049_rows( 1, 50 ),
			'headers' => array(
				'x-total-count'      => 674,
				'x-records-per-page' => 50,
				'x-page-count'       => 14,
			),
			'link'    => '</tasks/15/comments?page=3>; rel="next"',
		),
	),
);
$wpcable_http_calls = array();
wpcable_load_task_history_run( 15 );
$dup = wpcable_load_task_history_run( 15, array( 'more' => true ) );
wpcable_assert( empty( $dup['comments']['complete'] ), 'duplicate-ID page 2 is not marked complete' );
$row  = $repo->get_task( 15 );
$hist = wpcable_task_history_for_display( $row );
wpcable_assert_equals( 50, count( $hist['comments']['items'] ), 'duplicate page does not inflate ids' );
wpcable_assert( empty( $hist['can_load_more'] ), 'duplicate-ID page stops Load more' );

$wpdb->update(
	'wp_codeable_tasks',
	array(
		'comments_json'      => '',
		'comments_present'   => 0,
		'comments_complete'  => 0,
		'history_meta_json'  => '',
		'history_account_id' => '',
	),
	array( 'task_id' => 15 )
);
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/15',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				'id'          => 15,
				'title'       => 'Repeat page',
				'description' => 'Brief',
			),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'       => 'tasks/15/comments',
		'query_page' => 0,
		'response'   => array(
			'status'  => 200,
			'body'    => wpcable_test_049_rows( 1, 50 ),
			'headers' => array(
				'x-total-count'      => 674,
				'x-records-per-page' => 50,
				'x-page-count'       => 14,
			),
			'link'    => '</tasks/15/comments?page=2>; rel="next"',
		),
	),
	array(
		'path'       => 'tasks/15/comments',
		'query_page' => 2,
		'response'   => array(
			'status'  => 200,
			'body'    => wpcable_test_049_rows( 51, 100 ),
			'headers' => array(
				'x-total-count'      => 674,
				'x-records-per-page' => 50,
				'x-page-count'       => 14,
			),
			'link'    => '</tasks/15/comments?page=2>; rel="next"',
		),
	),
);
$wpcable_http_calls = array();
wpcable_load_task_history_run( 15 );
wpcable_load_task_history_run( 15, array( 'more' => true ) );
$repeat_calls_before = 0;
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/15/comments' === $call['path'] ) {
		$repeat_calls_before++;
	}
}
$repeat = wpcable_load_task_history_run( 15, array( 'more' => true ) );
wpcable_assert( ! empty( $repeat['comments']['loop_guard'] ) || false !== strpos( (string) $repeat['comments']['reason'], 'matched the last page' ), 'repeated page=2 is not sent again' );
$repeat_calls_after = 0;
foreach ( $wpcable_http_calls as $call ) {
	if ( 'tasks/15/comments' === $call['path'] ) {
		$repeat_calls_after++;
	}
}
wpcable_assert_equals( $repeat_calls_before, $repeat_calls_after, 'repeated-page Load more does not issue another comments GET' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$idle = wpcable_history_backfill_tick();
wpcable_assert( ! empty( $idle['skipped'] ), 'cron tick does not start a new bulk job' );
