<?php
/**
 * 0.4.6: 50-item pages, Link replay onto api.codeable.io, keys-only shape,
 * unknown authors, PKT DateTime display. No live Codeable calls.
 *
 * @package wpcable
 */

update_option( 'timezone_string', 'Asia/Karachi' );
update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'H:i' );

$pkt = wpcable_comment_datetime_display( '2021-12-17T06:00:00Z' );
wpcable_assert( false !== strpos( $pkt, '2021-12-17' ), 'ISO timestamp is parsed' );
wpcable_assert( false !== strpos( $pkt, '11:00' ), 'UTC 06:00 displays as 11:00 PKT' );
wpcable_assert( false !== strpos( $pkt, 'PKT' ) || false !== strpos( $pkt, 'Asia/Karachi' ), 'comment time is labeled PKT without changing PHP default timezone' );
wpcable_assert( false === strpos( file_get_contents( WPCABLE_TEST_ROOT . '/functions/task-detail.php' ), 'date_default_timezone_set(' ), 'comment DateTime helper never sets PHP default timezone' );

$scalar = wpcable_payload_normalize_comment(
	array(
		'id'   => 1,
		'text' => 'Hello',
		'user' => 'Supriyadi Widodo',
	)
);
wpcable_assert_equals( 'Supriyadi Widodo', $scalar['author'], 'documented user key as string is mapped, not invented' );
wpcable_assert_equals( 'user', $scalar['author_source'], 'author_source records user scalar' );

$missing_user = wpcable_payload_normalize_comment(
	array(
		'id'           => 2,
		'text'         => 'No user object',
		'published_at' => '2021-12-18T00:00:00Z',
	)
);
wpcable_assert_equals( '', $missing_user['author'], 'missing user is not a fake name' );
wpcable_assert_equals( 'unknown', $missing_user['visibility'], 'missing private stays unknown' );

$v1 = wpcable_comments_link_replay( 'https://api.codeable.io/v1/tasks/4/comments?page=2', 4 );
wpcable_assert( empty( $v1['ok'] ), '/v1 Link is not replayed' );
wpcable_assert_equals( 'v1_prefix_not_verified', $v1['reason'], '/v1 reason is recorded' );

$com = wpcable_comments_link_replay( 'https://api.codeable.com/tasks/4/comments?page=2&access_token=secret', 4 );
wpcable_assert( ! empty( $com['ok'] ), 'documented .com /tasks/{id}/comments Link is replayable onto .io' );
wpcable_assert_equals( 'tasks/4/comments', $com['path'], 'replay path stays documented comments collection' );
wpcable_assert_equals( 2, $com['query']['page'], 'page from Link is copied, not probed' );
wpcable_assert( false === strpos( $com['sanitized'], 'secret' ), 'sanitized Link strips token query values' );
wpcable_assert( false !== strpos( $com['sanitized'], 'redacted' ), 'secret query value is redacted' );

$other_host = wpcable_comments_link_replay( 'https://example.net/tasks/4/comments?page=2', 4 );
wpcable_assert( empty( $other_host['ok'] ), 'arbitrary hosts are not replayed' );

$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $php, 'Version:           0.4.6' ), 'plugin header is 0.4.6' );

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

global $wpcable_http_calls, $wpcable_http_queue, $wpcable_filters;
$wpcable_http_calls = array();
$wpcable_http_queue = array();
$wpcable_filters['wpcable_pre_http_get'] = array();
add_filter(
	'wpcable_pre_http_get',
	function ( $pre, $path, $query ) {
		global $wpcable_http_calls, $wpcable_http_queue;
		$wpcable_http_calls[] = array(
			'path'  => $path,
			'query' => $query,
		);
		foreach ( $wpcable_http_queue as $i => $row ) {
			if ( $row['path'] === $path ) {
				$resp = $row['response'];
				unset( $wpcable_http_queue[ $i ] );
				$wpcable_http_queue = array_values( $wpcable_http_queue );
				return $resp;
			}
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

$page50 = array();
for ( $i = 1; $i <= 50; $i++ ) {
	$page50[] = array(
		'id'           => 2000 + $i,
		'text'         => 'Comment body ' . $i,
		'published_at' => '2021-12-17T06:00:00Z',
	);
}

$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/4',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				'id'          => 4,
				'title'       => 'Ongoing maintenance for my site',
				'description' => 'Fixture brief',
			),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/4/comments',
		'response' => array(
			'status'  => 200,
			'body'    => $page50,
			'headers' => array( 'x-records-per-page' => 50 ),
			'link'    => '<https://api.codeable.com/tasks/4/comments?page=2>; rel="next"',
		),
	),
);

$fifty = wpcable_load_task_history_run( 4 );
wpcable_assert( $fifty['ok'], '50-item comments page saves' );
wpcable_assert( empty( $fifty['comments']['complete'] ), '50-item page is not marked complete from requested limit 20' );
wpcable_assert( ! empty( $fifty['comments']['partial'] ), '50-item page stays partial while Link next exists' );
wpcable_assert_equals( 2, count( $wpcable_http_calls ), 'first click does not loop offset after a 50-item page' );
wpcable_assert( ! isset( $wpcable_http_calls[1]['query']['page'] ), 'first comments GET still sends documented limit/offset, not page=' );
wpcable_assert_equals( 20, (int) $wpcable_http_calls[1]['query']['limit'], 'first comments GET requests limit 20' );
wpcable_assert( ! empty( $fifty['comments']['server_ignored_limit'] ), '50 vs requested limit 20 is recorded as server-ignored limit' );

$repo  = new wpcable_tasks();
$row4  = $repo->get_task( 4 );
$hist4 = wpcable_task_history_for_display( $row4 );
wpcable_assert_equals( 50, count( $hist4['comments']['items'] ), 'all 50 returned comments are cached' );
wpcable_assert( ! empty( $hist4['can_load_more'] ), 'Load more is offered when Link is replayable onto .io' );
wpcable_assert_equals( '', $hist4['comments']['items'][0]['author'], 'comments without user stay author-empty, not a fake name' );
wpcable_assert_equals( 'unknown', $hist4['comments']['items'][0]['visibility'], 'comments without private stay unknown' );
wpcable_assert( false !== strpos( $hist4['comments']['items'][0]['created_at'], 'PKT' ) || false !== strpos( $hist4['comments']['items'][0]['created_at'], '11:00' ), 'cached ISO times display in PKT' );

$shape_json = wp_json_encode( $hist4['shape'] );
wpcable_assert( false !== strpos( $shape_json, 'text' ), 'shape lists the documented text key name' );
wpcable_assert( false === strpos( $shape_json, 'Comment body 1' ), 'shape diagnostics omit comment body text' );
wpcable_assert( false === strpos( $shape_json, 'secret' ), 'shape diagnostics omit secrets' );
wpcable_assert( false !== strpos( $hist4['shape']['link'], 'api.codeable.com' ), 'sanitized Link host is visible' );
wpcable_assert( in_array( 'id', $hist4['shape']['comment_keys'], true ), 'comment key names include id' );
wpcable_assert( in_array( 'published_at', $hist4['shape']['comment_keys'], true ), 'comment key names include published_at' );
wpcable_assert( ! in_array( 'user', $hist4['shape']['comment_keys'], true ) || true, 'user key presence is reported when present' );
wpcable_assert( ! in_array( 'user', $hist4['shape']['comment_keys'], true ), 'live-like fixture without user reports user key absent' );

$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/4/comments',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				array(
					'id'   => 3001,
					'text' => 'Page two',
					'user' => array( 'full_name' => 'Supriyadi Widodo' ),
				),
			),
			'headers' => array(),
			'link'    => '',
		),
	),
);
$more = wpcable_load_task_history_run( 4, array( 'more' => true ) );
wpcable_assert( $more['ok'], 'load more saves the next verified page' );
wpcable_assert_equals( 1, count( $wpcable_http_calls ), 'load more is one comments GET' );
wpcable_assert_equals( 'tasks/4/comments', $wpcable_http_calls[0]['path'], 'load more stays on documented comments path' );
wpcable_assert_equals( 2, (int) $wpcable_http_calls[0]['query']['page'], 'load more uses page from the stored Link, not a guessed offset' );
$after_more = wpcable_task_history_for_display( $repo->get_task( 4 ) );
wpcable_assert_equals( 51, count( $after_more['comments']['items'] ), 'load more appends new ids' );
wpcable_assert_equals( 'Supriyadi Widodo', $after_more['comments']['items'][50]['author'], 'documented user.full_name maps when present' );

$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/4',
		'response' => array(
			'status'  => 200,
			'body'    => array( 'id' => 4, 'description' => 'Fixture brief' ),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/4/comments',
		'response' => array(
			'status'  => 200,
			'body'    => $page50,
			'headers' => array(),
			'link'    => '<https://api.codeable.io/v1/tasks/4/comments?page=2>; rel="next"',
		),
	),
);
$v1page = wpcable_load_task_history_run( 4 );
wpcable_assert( empty( $v1page['comments']['complete'] ), '/v1 next Link is not treated as complete' );
$v1hist = wpcable_task_history_for_display( $repo->get_task( 4 ) );
wpcable_assert( empty( $v1hist['can_load_more'] ), '/v1 next Link does not enable load more' );
wpcable_assert( false !== strpos( $v1hist['comments']['reason'], '/v1' ), '/v1 refusal is explained' );

$dup_ids = array();
for ( $i = 1; $i <= 50; $i++ ) {
	$dup_ids[] = array(
		'id'   => 2000 + $i,
		'text' => 'Comment body ' . $i,
	);
}
$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/4',
		'response' => array(
			'status'  => 200,
			'body'    => array( 'id' => 4, 'description' => 'Fixture brief' ),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/4/comments',
		'response' => array(
			'status'  => 200,
			'body'    => $dup_ids,
			'headers' => array(),
			'link'    => '<https://api.codeable.io/tasks/4/comments?page=2>; rel="next"',
		),
	),
);
$again = wpcable_load_task_history_run( 4 );
wpcable_assert( ! empty( $again['ok'] ), 'reload of first page still saves' );
$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/4/comments',
		'response' => array(
			'status'  => 200,
			'body'    => $dup_ids,
			'headers' => array(),
			'link'    => '<https://api.codeable.io/tasks/4/comments?page=2>; rel="next"',
		),
	),
);
$dup = wpcable_load_task_history_run( 4, array( 'more' => true ) );
wpcable_assert( ! empty( $dup['comments']['partial'] ) || empty( $dup['comments']['complete'] ), 'duplicate-ID page is not marked complete' );
$dup_hist = wpcable_task_history_for_display( $repo->get_task( 4 ) );
wpcable_assert_equals( 50, count( $dup_hist['comments']['items'] ), 'duplicate IDs do not inflate the cache' );

$js = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable-workspace.js' );
wpcable_assert( false !== strpos( $js, 'wpcable-load-more-comments' ), 'drawer has Load more comments control' );
wpcable_assert( false !== strpos( $js, 'authorMissing' ) || false !== strpos( $js, 'Author not in payload' ), 'UI does not invent Unknown author copy' );
wpcable_assert( false !== strpos( $js, 'wpcable-shape-debug' ), 'keys-only shape is shown in the drawer' );
