<?php
/**
 * 0.4.8: live list mapping (is_private, user_id), rejected-Link diagnostics.
 * Does not broaden the comments Link allowlist. No live Codeable calls.
 *
 * @package wpcable
 */

$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $php, 'Version:           0.4.8' ), 'plugin header is 0.4.8' );
wpcable_assert( false === strpos( file_get_contents( WPCABLE_TEST_ROOT . '/functions/task-detail.php' ), '#^/users/me/tasks/' ), 'allowlist is not broadened to /users/me/tasks from a path guess' );

$live = wpcable_payload_normalize_comment(
	array(
		'id'                   => 8801,
		'task_id'              => 163186,
		'user_id'              => 999001,
		'text'                 => 'Live list body',
		'is_private'           => true,
		'created_at'           => '2021-12-17T06:00:00Z',
		'updated_at'           => '2021-12-17T06:01:00Z',
		'is_contractors_only'  => false,
		'is_from_email'        => false,
	)
);
wpcable_assert_equals( 'private', $live['visibility'], 'is_private true maps visibility private' );
wpcable_assert_equals( 'is_private', $live['private_source'], 'private_source records is_private' );
wpcable_assert_equals( '', $live['author'], 'no nested user keeps author empty' );
wpcable_assert_equals( 999001, $live['user_id'], 'user_id is stored' );
wpcable_assert( false === $live['is_contractors_only'], 'is_contractors_only false stays distinct' );
wpcable_assert( false === $live['is_from_email'], 'is_from_email is stored' );

$co = wpcable_payload_normalize_comment(
	array(
		'id'                  => 8802,
		'user_id'             => 101,
		'text'                => 'Contractors flag',
		'is_private'          => false,
		'is_contractors_only' => true,
	)
);
wpcable_assert_equals( 'public', $co['visibility'], 'is_private false is public even when contractors_only' );
wpcable_assert( true === $co['is_contractors_only'], 'is_contractors_only stays its own flag' );

$unknown = wpcable_payload_normalize_comment(
	array(
		'id'      => 8803,
		'user_id' => 50,
		'text'    => 'No privacy key',
	)
);
wpcable_assert_equals( 'unknown', $unknown['visibility'], 'missing is_private stays unknown' );

$rel = wpcable_comments_link_replay( '/tasks/15/comments?page=2&access_token=secret', 15 );
wpcable_assert( empty( $rel['ok'] ), 'relative Link is not replayed (no host guess)' );
wpcable_assert_equals( 'relative_no_host', $rel['reason'], 'relative Link reason is relative_no_host' );
wpcable_assert_equals( '/tasks/15/comments', $rel['inspect']['path'], 'relative path is exposed' );
wpcable_assert_equals( 2, $rel['inspect']['paging']['page'], 'relative paging page is exposed' );
wpcable_assert( false === strpos( $rel['sanitized'], 'secret' ), 'relative sanitized omits token' );

$mismatch = wpcable_comments_link_replay( 'https://api.codeable.io/users/me/tasks/15/comments?page=3&foo=bar', 15 );
wpcable_assert( empty( $mismatch['ok'] ), 'undocumented /users/me/tasks path is not replayed' );
wpcable_assert_equals( 'path_not_documented_comments', $mismatch['reason'], 'mismatch reason stays path_not_documented_comments' );
wpcable_assert_equals( 'api.codeable.io', $mismatch['inspect']['host'], 'rejected host is visible' );
wpcable_assert_equals( '/users/me/tasks/15/comments', $mismatch['inspect']['path'], 'rejected path is visible for the next live capture' );
wpcable_assert_equals( 3, $mismatch['inspect']['paging']['page'], 'rejected paging page is visible' );
wpcable_assert( in_array( 'foo', $mismatch['inspect']['other_query_keys'], true ), 'non-paging query keys are named without values' );
wpcable_assert( false === strpos( $mismatch['sanitized'], 'bar' ), 'non-paging query values are omitted' );

$userinfo = wpcable_inspect_link_uri( 'https://user:pass@api.codeable.io/tasks/15/comments?page=2' );
wpcable_assert( ! empty( $userinfo['userinfo_stripped'] ), 'userinfo is stripped' );
wpcable_assert( false === strpos( $userinfo['sanitized'], 'user' ), 'sanitized URI has no userinfo' );
wpcable_assert_equals( 'api.codeable.io', $userinfo['host'], 'host remains after stripping userinfo' );

$v1 = wpcable_comments_link_replay( 'https://api.codeable.io/v1/tasks/15/comments?page=2', 15 );
wpcable_assert( empty( $v1['ok'] ), '/v1 is still not replayed' );

delete_option( 'wpcable_history_backfill' );
delete_option( 'wpcable_history_backfill_lock' );
$job = wpcable_history_backfill_job();
wpcable_assert_equals( 'idle', $job['state'], 'bulk job remains idle unless started' );
$tick = wpcable_history_backfill_tick();
wpcable_assert( ! empty( $tick['skipped'] ), 'cron tick does not start a new bulk job' );

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

global $wpcable_filters, $wpcable_http_calls, $wpcable_http_queue;
$wpcable_filters['wpcable_pre_http_get'] = array();
$wpcable_http_calls = array();
$wpcable_http_queue = array();
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

global $wpdb;
$wpdb->update(
	'wp_codeable_tasks',
	array(
		'comments_json'       => '',
		'comments_present'    => 0,
		'comments_complete'   => 0,
		'history_meta_json'   => '',
		'history_account_id'  => '',
	),
	array( 'task_id' => 15 )
);

$page50 = array();
for ( $i = 1; $i <= 50; $i++ ) {
	$page50[] = array(
		'id'                  => 5000 + $i,
		'task_id'             => 15,
		'user_id'             => ( 1 === $i ) ? 101 : ( ( 2 === $i ) ? 91563 : 999001 ),
		'text'                => 'Comment body ' . $i,
		'is_private'          => ( $i % 2 ) === 0,
		'created_at'          => '2021-12-17T06:00:00Z',
		'updated_at'          => '2021-12-17T06:00:00Z',
		'is_contractors_only' => ( 3 === $i ),
		'is_from_email'       => false,
	);
}

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/15',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				'id'          => 15,
				'title'       => 'Fixture filler project 15',
				'description' => 'Brief 15',
			),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/15/comments',
		'response' => array(
			'status'  => 200,
			'body'    => $page50,
			'headers' => array(
				'x-records-per-page' => 50,
			),
			'link'    => '<https://api.codeable.io/users/me/tasks/15/comments?page=2&access_token=secret>; rel="next"',
		),
	),
);

$loaded = wpcable_load_task_history_run( 15 );
wpcable_assert( ! empty( $loaded['ok'] ), 'live-like 50-item list saves' );
wpcable_assert( empty( $loaded['comments']['complete'] ), '50-item undocumented next stays partial' );
wpcable_assert( empty( $loaded['comments']['next_replay']['ok'] ), 'undocumented next is not replayable' );
wpcable_assert( false !== strpos( $loaded['comments']['reason'], 'not the documented /tasks/{id}/comments path' ), 'rejection notice matches live wording' );

$repo = new wpcable_tasks();
$row  = $repo->get_task( 15 );
$hist = wpcable_task_history_for_display( $row );
wpcable_assert_equals( 50, count( $hist['comments']['items'] ), '50 comments cached' );
wpcable_assert( empty( $hist['can_load_more'] ), 'Load more stays hidden until the path is verified' );
wpcable_assert_equals( '/users/me/tasks/15/comments', $hist['link_next']['path'], 'drawer diagnostic exposes rejected path' );
wpcable_assert_equals( 'api.codeable.io', $hist['link_next']['host'], 'drawer diagnostic exposes rejected host' );
wpcable_assert_equals( 2, $hist['link_next']['paging']['page'], 'drawer diagnostic exposes page=' );
wpcable_assert_equals( 'path_not_documented_comments', $hist['link_next']['replay_reason'], 'drawer diagnostic exposes replay reason' );
wpcable_assert( false === strpos( wp_json_encode( $hist['link_next'] ), 'secret' ), 'rejected Link diagnostic omits token' );
wpcable_assert( false === strpos( wp_json_encode( $hist['shape'] ), 'Comment body 1' ), 'shape still omits comment bodies' );
wpcable_assert_equals( 50, (int) $hist['paging_headers']['x-records-per-page'], 'X-Records-Per-Page is surfaced' );
wpcable_assert( null === $hist['paging_headers']['x-total-count'] || '' === $hist['paging_headers']['x-total-count'], 'missing X-Total-Count stays absent (not invented complete)' );

wpcable_assert_equals( 'private', $hist['comments']['items'][1]['visibility'], 'even index is_private true is private' );
wpcable_assert_equals( 'public', $hist['comments']['items'][0]['visibility'], 'odd index is_private false is public' );
wpcable_assert_equals( 'Fixture Client Alpha', $hist['comments']['items'][0]['author'], 'user_id matching a saved client uses local full_name' );
wpcable_assert_equals( 'local_client', $hist['comments']['items'][0]['author_source'], 'local client mapping source is recorded' );
wpcable_assert_equals( 'Fixture Expert', $hist['comments']['items'][1]['author'], 'user_id matching the connected account uses stored profile name' );
wpcable_assert_equals( '', $hist['comments']['items'][2]['author'], 'unknown user_id does not invent a name' );
wpcable_assert_equals( 999001, (int) $hist['comments']['items'][2]['user_id'], 'unknown author still keeps user_id' );
wpcable_assert( true === $hist['comments']['items'][2]['is_contractors_only'], 'contractors_only on item 3 stays distinct' );

$paths = array();
foreach ( $wpcable_http_calls as $call ) {
	$paths[] = $call['path'];
}
wpcable_assert( ! in_array( 'users/999001', $paths, true ), 'no speculative GET /users/{id}' );
wpcable_assert( ! in_array( 'users/me/tasks/15/comments', $paths, true ), 'rejected next path is not requested' );

$js = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable-workspace.js' );
wpcable_assert( false !== strpos( $js, 'wpcable-link-next' ), 'drawer JS renders rejected Link diagnostic' );
wpcable_assert( false !== strpos( $js, 'user_id' ), 'drawer JS can show user_id when author is missing' );
wpcable_assert( false !== strpos( $js, 'contractors_only' ), 'drawer JS can show contractors_only separately' );
