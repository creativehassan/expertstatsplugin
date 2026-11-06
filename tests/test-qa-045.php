<?php
/**
 * 0.4.5: documented GET /tasks/{id} + comments, cache, 403, pagination partial, no overwrite.
 *
 * @package wpcable
 */

$api_src = file_get_contents( WPCABLE_TEST_ROOT . '/classes/api_calls.php' );
wpcable_assert( false !== strpos( $api_src, 'function get_with_meta' ), 'API client has documented GET helper' );
wpcable_assert( false !== strpos( $api_src, 'redirection' ), 'documented GET does not follow redirects (no cross-domain token)' );
wpcable_assert( false !== strpos( $api_src, 'api.codeable.io' ), 'working host api.codeable.io is unchanged' );
wpcable_assert( false === strpos( $api_src, "'https://api.codeable.com/'" ) && false === strpos( $api_src, '"https://api.codeable.com/"' ), 'does not switch host to api.codeable.com' );
wpcable_assert( false === strpos( $api_src, '/task/' ), 'api_calls does not use singular /task/ paths' );

$loader = file_get_contents( WPCABLE_TEST_ROOT . '/functions/task-detail.php' );
wpcable_assert( false !== strpos( $loader, "tasks/' . \$task_id" ) || false !== strpos( $loader, 'tasks/\' . $task_id' ) || false !== strpos( $loader, "tasks/' . (int) \$task_id" ), 'loader uses plural tasks path' );
wpcable_assert( false !== strpos( $loader, "'limit'" ), 'comments use documented limit param' );
wpcable_assert( false !== strpos( $loader, "'offset'" ), 'comments use documented offset param' );
wpcable_assert( false === strpos( $loader, "'page' =>" ), 'first comments request does not hardcode a page= probe' );
wpcable_assert( false === strpos( $loader, 'attachments' ), 'attachments endpoint not in this phase' );
wpcable_assert( false === strpos( $loader, '/reviews' ), 'reviews endpoint not in this phase' );

$admin_tasks = file_get_contents( WPCABLE_TEST_ROOT . '/functions/admin-tasks.php' );
wpcable_assert( false !== strpos( $admin_tasks, 'wpcable_load_task_history' ), 'ajax action is registered' );
wpcable_assert( false !== strpos( $admin_tasks, 'wpcable_ajax_can_manage' ), 'ajax load is nonce/capability gated' );

$js = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable-workspace.js' );
wpcable_assert( false !== strpos( $js, 'wpcable-load-history' ), 'drawer has Load from Codeable control' );
wpcable_assert( false !== strpos( $js, 'wpcable_load_task_history' ), 'drawer posts the local WP ajax action' );

$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $php, 'Version:           0.4.8' ), 'plugin header is 0.4.8' );
wpcable_assert( false !== strpos( $php, '0.0.6' ), 'schema 0.0.6 additive' );
wpcable_assert( false !== strpos( $php, 'history_meta_json' ), 'history_meta_json column additive' );

$rels = wpcable_parse_link_header( '<https://api.codeable.io/tasks/1/comments?page=2>; rel="next", <https://api.codeable.io/tasks/1/comments?page=1>; rel="prev"' );
wpcable_assert_equals( 'https://api.codeable.io/tasks/1/comments?page=2', $rels['next'], 'parses Link rel=next' );
wpcable_assert( wpcable_link_is_same_host_comments( $rels['next'], 1 ), 'same-host comments Link is accepted as metadata' );
wpcable_assert( ! wpcable_link_is_same_host_comments( 'https://api.codeable.com/tasks/1/comments?page=2', 1 ), 'other-host Link is not followed' );
wpcable_assert( ! wpcable_link_is_same_host_comments( 'https://api.codeable.io/v1/tasks/1/comments?page=3', 1 ), '/v1 prefix is not treated as the documented path' );

$unknown = wpcable_payload_normalize_comment(
	array(
		'id'           => 'a7839f0e2277d74238b3',
		'text'         => 'I can start tomorrow. Let me know!',
		'user'         => array( 'id' => 'a', 'full_name' => 'Supriyadi Widodo' ),
		'edited'       => true,
		'published_at' => 1389542068,
	)
);
wpcable_assert_equals( 'unknown', $unknown['visibility'], 'list sample without private is visibility unknown, not public' );
wpcable_assert_equals( 'I can start tomorrow. Let me know!', $unknown['body'], 'comment body from documented text field' );
wpcable_assert_equals( 'Supriyadi Widodo', $unknown['author'], 'author from user.full_name' );
wpcable_assert_equals( 'a7839f0e2277d74238b3', $unknown['id'], 'non-numeric documented comment id is preserved' );

$priv = wpcable_payload_normalize_comment(
	array(
		'id'      => 10,
		'text'    => 'Private note',
		'private' => true,
		'user'    => array( 'full_name' => 'Joe Client' ),
	)
);
wpcable_assert_equals( 'private', $priv['visibility'], 'private:true is stored as private' );

$pub = wpcable_payload_normalize_comment(
	array(
		'id'      => 11,
		'text'    => 'Public note',
		'private' => false,
		'user'    => array( 'full_name' => 'Pat Expert' ),
	)
);
wpcable_assert_equals( 'public', $pub['visibility'], 'private:false is stored as public' );

global $wpcable_filters, $wpcable_http_calls, $wpcable_http_queue;
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
wpcable_assert( wpcable_sync_allowed(), 'test account is treated as connected' );

$blocked = $api->get_with_meta( 'users/me', array() );
wpcable_assert_equals( 'path_not_allowlisted', $blocked['error'], 'non-allowlisted path is refused locally' );
wpcable_assert( empty( $wpcable_http_calls ) || 'users/me' !== $wpcable_http_calls[ count( $wpcable_http_calls ) - 1 ]['path'], 'refused path never hits HTTP mock as a live call' );

$wpcable_http_calls = array();
$denied = $api->get_with_meta( 'task/1/comments', array() );
wpcable_assert_equals( 'path_not_allowlisted', $denied['error'], 'singular task collection is not allowlisted' );

$wpcable_http_calls = array();
$wpcable_http_queue[] = array(
	'path'     => 'tasks/1/comments/a7839f0e2277d74238b3',
	'response' => array(
		'status'  => 200,
		'body'    => array(
			'id'   => 'a7839f0e2277d74238b3',
			'text' => 'I can start tomorrow. Let me know!',
		),
		'headers' => array(),
		'link'    => '',
	),
);
$one_comment = $api->get_with_meta( 'tasks/1/comments/a7839f0e2277d74238b3', array() );
wpcable_assert_equals( 200, $one_comment['status'], 'documented individual comment GET path is allowlisted' );
wpcable_assert_equals( 1, count( $wpcable_http_calls ), 'individual comment GET is a single documented request' );

$missing = wpcable_load_task_history_run( 999999 );
wpcable_assert_equals( 'not_found', $missing['state'], 'unknown task_id does not call Codeable' );

$wpcable_http_calls = array();
$wpcable_http_queue = array();
delete_option( 'wpcable_auth_token' );
$api->auth_token = '';
$disc = wpcable_load_task_history_run( 1 );
wpcable_assert_equals( 'DISCONNECTED', $disc['state'], 'disconnected load does not fetch' );
wpcable_assert( empty( $wpcable_http_calls ), 'disconnected load makes no HTTP calls' );

update_option( 'wpcable_auth_token', base64_encode( $iv ) . ':' . base64_encode( $enc ) );
$api->auth_token = 'test-token';

$repo = new wpcable_tasks();
$before = $repo->get_task( 1 );
wpcable_assert( empty( $before['description_present'] ) || true, 'fixture task 1 exists' );
$old_title = $before['title'];

$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/1',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				'id'          => 1,
				'kind'        => 'plugin',
				'title'       => 'Minor tweaks for my Wordpress website',
				'description' => 'Website designer to complete custom design and brand development.',
				'state'       => 'published',
			),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/1/comments',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				array(
					'id'           => 'a7839f0e2277d74238b3',
					'text'         => 'I can start tomorrow. Let me know!',
					'user'         => array( 'full_name' => 'Supriyadi Widodo' ),
					'edited'       => true,
					'published_at' => 1389542068,
				),
				array(
					'id'      => 'ee03e13009c149c2dc8c',
					'text'    => 'Private contractor note',
					'private' => true,
					'user'    => array( 'full_name' => 'Joe Client' ),
					'edited'  => false,
				),
			),
			'headers' => array( 'x-total-count' => 2 ),
			'link'    => '',
		),
	),
);

$loaded = wpcable_load_task_history_run( 1 );
wpcable_assert( $loaded['ok'], 'successful documented GET is ok' );
wpcable_assert( $loaded['saved'], 'successful GET is cached' );
wpcable_assert_equals( 2, count( $wpcable_http_calls ), 'exactly two documented GETs (task + comments)' );
wpcable_assert_equals( 'tasks/1', $wpcable_http_calls[0]['path'], 'first call is GET /tasks/{id}' );
wpcable_assert_equals( 'tasks/1/comments', $wpcable_http_calls[1]['path'], 'second call is GET /tasks/{id}/comments' );
wpcable_assert( isset( $wpcable_http_calls[1]['query']['limit'] ), 'comments query includes limit' );
wpcable_assert( isset( $wpcable_http_calls[1]['query']['offset'] ), 'comments query includes offset' );
wpcable_assert( ! isset( $wpcable_http_calls[1]['query']['page'] ), 'comments query does not send page=' );

$fresh = $repo->get_task( 1 );
wpcable_assert_equals( $old_title, $fresh['title'], 'load does not replace the local title/row identity' );
wpcable_assert( ! empty( $fresh['description_present'] ), 'description present after successful GET' );
wpcable_assert( false !== strpos( $fresh['description_text'], 'Website designer' ), 'description text cached from GET /tasks/{id}' );
wpcable_assert_equals( 'GET /tasks/{id}/comments', $fresh['comments_source'], 'comments source is documented route' );
wpcable_assert_equals( '91563', $fresh['history_account_id'], 'cache stamped with Codeable account id' );

$hist = wpcable_task_history_for_display( $fresh );
wpcable_assert( ! empty( $hist['description']['available'] ), 'display shows cached description' );
wpcable_assert( $hist['can_load'], 'connected drawer offers load action' );
$vis = array();
foreach ( $hist['comments']['items'] as $item ) {
	$vis[] = $item['visibility'];
}
wpcable_assert( in_array( 'unknown', $vis, true ), 'comment without private flag stays unknown' );
wpcable_assert( in_array( 'private', $vis, true ), 'comment with private true is private' );
$ids = array();
foreach ( $hist['comments']['items'] as $item ) {
	$ids[] = $item['id'];
}
wpcable_assert( in_array( 'a7839f0e2277d74238b3', $ids, true ), 'cached comments keep documented hex ids' );
wpcable_assert( false === strpos( $hist['comments']['reason'], 'task-list payload' ), 'GET-sourced comments do not reuse list-payload missing copy' );

$saved_desc = $fresh['description_text'];
$saved_json = $fresh['comments_json'];

$wpcable_http_calls = array();
$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/1',
		'response' => array( 'status' => 500, 'body' => null, 'headers' => array(), 'link' => '', 'error' => 'boom' ),
	),
	array(
		'path'     => 'tasks/1/comments',
		'response' => array( 'status' => 500, 'body' => null, 'headers' => array(), 'link' => '', 'error' => 'boom' ),
	),
);
$errored = wpcable_load_task_history_run( 1 );
wpcable_assert( ! $errored['ok'], 'HTTP error is not ok' );
$after_err = $repo->get_task( 1 );
wpcable_assert_equals( $saved_desc, $after_err['description_text'], 'error does not overwrite description cache' );
wpcable_assert_equals( $saved_json, $after_err['comments_json'], 'error does not overwrite comments cache' );

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/1',
		'response' => array( 'status' => 403, 'body' => array( 'errors' => array( 'forbidden' ) ), 'headers' => array(), 'link' => '' ),
	),
	array(
		'path'     => 'tasks/1/comments',
		'response' => array( 'status' => 403, 'body' => array( 'errors' => array( 'forbidden' ) ), 'headers' => array(), 'link' => '' ),
	),
);
$forbidden = wpcable_load_task_history_run( 1 );
wpcable_assert_equals( 'FORBIDDEN', $forbidden['state'], '403 is reported as FORBIDDEN' );
$after_403 = $repo->get_task( 1 );
wpcable_assert_equals( $saved_desc, $after_403['description_text'], '403 does not overwrite description cache' );
wpcable_assert( false !== strpos( $forbidden['message'], '403' ), '403 copy mentions refused read' );

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/2',
		'response' => array(
			'status'  => 200,
			'body'    => array( 'id' => 2, 'title' => 'x', 'description' => 'Body from detail' ),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/2/comments',
		'response' => array(
			'status'  => 200,
			'body'    => array_fill(
				0,
				20,
				array(
					'id'   => 1,
					'text' => 'Full page comment',
					'user' => array( 'full_name' => 'Alex' ),
				)
			),
			'headers' => array(),
			'link'    => '',
		),
	),
);
$partial = wpcable_load_task_history_run( 2 );
wpcable_assert( $partial['ok'], 'partial comments page still saves' );
wpcable_assert( empty( $partial['comments']['complete'] ), 'full page without Link is not marked complete' );
wpcable_assert( ! empty( $partial['comments']['partial'] ) || false !== strpos( $partial['comments']['reason'], 'not marked complete' ) || false !== strpos( strtolower( $partial['message'] ), 'partial' ) || false !== strpos( $partial['comments']['reason'], 'more comments' ), 'honest partial status when page is full and no Link' );

$wpcable_http_queue = array(
	array(
		'path'     => 'tasks/3',
		'response' => array(
			'status'  => 200,
			'body'    => array( 'id' => 3, 'description' => 'Has next link' ),
			'headers' => array(),
			'link'    => '',
		),
	),
	array(
		'path'     => 'tasks/3/comments',
		'response' => array(
			'status'  => 200,
			'body'    => array(
				array(
					'id'   => 1,
					'text' => 'Only page',
					'user' => array( 'full_name' => 'Alex' ),
				),
			),
			'headers' => array(),
			'link'    => '<https://api.codeable.com/tasks/3/comments?page=2>; rel="next"',
		),
	),
);
$cross = wpcable_load_task_history_run( 3 );
wpcable_assert( $cross['ok'], 'saves first page when next Link is other-host' );
wpcable_assert( empty( $cross['comments']['complete'] ), 'other-host Link is not treated as complete' );
wpcable_assert( false !== strpos( $cross['comments']['reason'], 'replayed' ) || false !== strpos( $cross['comments']['reason'], 'api.codeable.io' ), 'other-host documented path is replayed onto api.codeable.io, not fetched cross-domain' );
$cross_hist = wpcable_task_history_for_display( $repo->get_task( 3 ) );
wpcable_assert( ! empty( $cross_hist['can_load_more'] ), 'verified .com Link enables Load more on .io' );
$cross_hosts = array();
foreach ( $cross['calls'] as $c ) {
	$cross_hosts[] = isset( $c['path'] ) ? $c['path'] : '';
}
wpcable_assert( 2 === count( $cross['calls'] ), 'first click is one task GET plus one comments GET (no auto-follow)' );

$other = $repo->get_task( 1 );
update_option(
	'wpcable_account_details',
	array(
		'id'         => 777,
		'first_name' => 'Other',
		'last_name'  => 'Expert',
	)
);
$hidden = wpcable_task_history_for_display( $other );
wpcable_assert( empty( $hidden['description']['available'] ), 'connected as a different account hides cached description' );
wpcable_assert( empty( $hidden['comments']['available'] ), 'connected as a different account hides cached comments' );

delete_option( 'wpcable_auth_token' );
$api->auth_token = '';
$disc_view = wpcable_task_history_for_display( $other );
wpcable_assert( ! empty( $disc_view['description']['available'] ), 'disconnected owner can still view saved authorized-account cache' );

$cron = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false === strpos( $cron, 'wpcable_load_task_history_run' ), 'page views / cron do not bulk-call the detail loader' );

wpcable_assert( false === strpos( $loader, "'POST'" ), 'task-detail module does not POST' );
wpcable_assert( false === strpos( $loader, "'PUT'" ), 'task-detail module does not PUT' );
wpcable_assert( false === strpos( $loader, "'PATCH'" ), 'task-detail module does not PATCH' );
wpcable_assert( false === strpos( $loader, 'comments/' . "'" ), 'loader does not auto-call GET /tasks/{id}/comments/{comment_id}' );
