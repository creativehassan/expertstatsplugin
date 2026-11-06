<?php
/**
 * Opt-in read-only GET /tasks/{id} and GET /tasks/{id}/comments.
 * Documented Codeable Apiary paths (plural tasks). Same host/auth as this plugin.
 * Never called from page views. Never POST/PUT/PATCH/DELETE.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse an RFC 5988 Link header into rel => URL.
 *
 * @param string $header Header value.
 * @return array
 */
function wpcable_parse_link_header( $header ) {
	$out = array(
		'next'  => '',
		'prev'  => '',
		'start' => '',
	);
	if ( ! is_string( $header ) || '' === $header ) {
		return $out;
	}
	foreach ( explode( ',', $header ) as $part ) {
		if ( ! preg_match( '/<([^>]+)>\s*;\s*rel="?([a-z]+)"?/i', $part, $m ) ) {
			continue;
		}
		$rel = strtolower( $m[2] );
		if ( isset( $out[ $rel ] ) ) {
			$out[ $rel ] = $m[1];
		}
	}
	return $out;
}

/**
 * Whether a PHP array is a list (0..n keys).
 *
 * @param mixed $arr Value.
 * @return bool
 */
function wpcable_is_list( $arr ) {
	if ( ! is_array( $arr ) ) {
		return false;
	}
	if ( function_exists( 'array_is_list' ) ) {
		return array_is_list( $arr );
	}
	if ( array() === $arr ) {
		return true;
	}
	return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
}

/**
 * JSON value kind for diagnostics (no payload values).
 *
 * @param mixed $value Value.
 * @return string
 */
function wpcable_value_kind( $value ) {
	if ( null === $value ) {
		return 'null';
	}
	if ( is_bool( $value ) ) {
		return 'bool';
	}
	if ( is_int( $value ) || is_float( $value ) ) {
		return 'number';
	}
	if ( is_string( $value ) ) {
		return 'string';
	}
	if ( is_array( $value ) ) {
		return wpcable_is_list( $value ) ? 'list' : 'object';
	}
	return 'other';
}

/**
 * Strip credentials from a Link URI. Host + path + non-secret query only.
 *
 * @param string $url URL.
 * @return string
 */
function wpcable_sanitize_link_uri( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
		return '';
	}
	$scheme = ! empty( $parts['scheme'] ) ? $parts['scheme'] : 'https';
	$path   = isset( $parts['path'] ) ? $parts['path'] : '';
	$query  = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		foreach ( $query as $k => $v ) {
			if ( preg_match( '/token|secret|auth|password|bearer|key|credential|access/i', (string) $k ) ) {
				$query[ $k ] = '[redacted]';
			}
		}
	}
	$q = $query ? http_build_query( $query ) : '';
	return $scheme . '://' . $parts['host'] . $path . ( $q ? '?' . $q : '' );
}

/**
 * Whether a Link URL is the exact same-host documented comments path.
 * Does not follow the URL. Tokens never sent to another host.
 *
 * @param string $url     Absolute URL from Link.
 * @param int    $task_id Local task id.
 * @return bool
 */
function wpcable_link_is_same_host_comments( $url, $task_id ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return false;
	}
	if ( 0 !== strcasecmp( $parts['host'], 'api.codeable.io' ) ) {
		return false;
	}
	$id   = preg_quote( (string) (int) $task_id, '#' );
	$path = isset( $parts['path'] ) ? $parts['path'] : '';
	return (bool) preg_match( '#^/tasks/' . $id . '/comments/?$#', $path );
}

/**
 * Derive a same-account .io comments GET from a returned Link.
 * Never requests the original host if it is not api.codeable.io.
 * Path must be documented /tasks/{numeric id}/comments. /v1 is not verified.
 * Query copied only for page/limit/offset/per_page integers from that Link.
 *
 * @param string $url     Link URL.
 * @param int    $task_id Imported task id.
 * @return array
 */
function wpcable_comments_link_replay( $url, $task_id ) {
	$task_id = (int) $task_id;
	$out     = array(
		'ok'          => false,
		'reason'      => '',
		'path'        => '',
		'query'       => array(),
		'source_host' => '',
		'sanitized'   => wpcable_sanitize_link_uri( $url ),
	);
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		$out['reason'] = 'unparseable';
		return $out;
	}
	$out['source_host'] = strtolower( $parts['host'] );
	if ( ! in_array( $out['source_host'], array( 'api.codeable.io', 'api.codeable.com' ), true ) ) {
		$out['reason'] = 'host_not_replayable';
		return $out;
	}
	$path = $parts['path'];
	if ( preg_match( '#^/v1/#i', $path ) ) {
		$out['reason'] = 'v1_prefix_not_verified';
		return $out;
	}
	$id = preg_quote( (string) $task_id, '#' );
	if ( ! preg_match( '#^/tasks/' . $id . '/comments/?$#', $path ) ) {
		$out['reason'] = 'path_not_documented_comments';
		return $out;
	}
	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	$safe = array();
	foreach ( array( 'page', 'limit', 'offset', 'per_page' ) as $k ) {
		if ( isset( $query[ $k ] ) && is_scalar( $query[ $k ] ) && preg_match( '/^[0-9]+$/', (string) $query[ $k ] ) ) {
			$safe[ $k ] = (int) $query[ $k ];
		}
	}
	$out['ok']    = true;
	$out['path']  = 'tasks/' . $task_id . '/comments';
	$out['query'] = $safe;
	$out['reason'] = ( 'api.codeable.io' === $out['source_host'] )
		? 'same_host_documented_path'
		: 'replayed_onto_api.codeable.io';
	return $out;
}

/**
 * Keys-only fingerprint of a comments response. No body text, names, or secrets.
 *
 * @param mixed  $body    Decoded JSON.
 * @param array  $headers Response headers.
 * @param string $link    Raw Link header.
 * @return array
 */
function wpcable_comments_shape( $body, $headers = array(), $link = '' ) {
	$shape = array(
		'envelope'        => '',
		'top_keys'        => array(),
		'item_count'      => 0,
		'comment_keys'    => array(),
		'nested'          => array(),
		'headers_present' => array(),
		'link'            => wpcable_sanitize_link_uri( $link ),
	);
	if ( is_array( $headers ) ) {
		foreach ( $headers as $hk => $hv ) {
			$shape['headers_present'][] = strtolower( (string) $hk );
		}
	}
	if ( ! is_array( $body ) ) {
		$shape['envelope'] = is_string( $body ) ? 'string' : wpcable_value_kind( $body );
		return $shape;
	}
	if ( wpcable_is_list( $body ) ) {
		$shape['envelope']   = 'list';
		$shape['item_count'] = count( $body );
		$sample              = ( isset( $body[0] ) && is_array( $body[0] ) ) ? $body[0] : array();
	} else {
		$shape['envelope'] = 'object';
		$shape['top_keys'] = array_map( 'strval', array_keys( $body ) );
		$sample            = array();
		if ( isset( $body['comments'] ) && wpcable_is_list( $body['comments'] ) ) {
			$shape['item_count'] = count( $body['comments'] );
			$sample              = ( isset( $body['comments'][0] ) && is_array( $body['comments'][0] ) ) ? $body['comments'][0] : array();
		}
	}
	if ( $sample ) {
		$shape['comment_keys'] = array_map( 'strval', array_keys( $sample ) );
		foreach ( $sample as $k => $v ) {
			$entry = array( 'kind' => wpcable_value_kind( $v ) );
			if ( is_array( $v ) && ! wpcable_is_list( $v ) ) {
				$entry['keys'] = array_map( 'strval', array_keys( $v ) );
			}
			$shape['nested'][ (string) $k ] = $entry;
		}
	}
	return $shape;
}

/**
 * Pull a comments list from a documented array body (or object.comments).
 *
 * @param mixed $body Decoded JSON.
 * @return array {list, envelope}
 */
function wpcable_extract_comments_list( $body ) {
	if ( ! is_array( $body ) ) {
		return array(
			'list'     => array(),
			'envelope' => 'not_array',
		);
	}
	if ( wpcable_is_list( $body ) ) {
		return array(
			'list'     => $body,
			'envelope' => 'list',
		);
	}
	if ( isset( $body['comments'] ) && wpcable_is_list( $body['comments'] ) ) {
		return array(
			'list'     => $body['comments'],
			'envelope' => 'object.comments',
		);
	}
	return array(
		'list'     => array(),
		'envelope' => 'object_not_list',
	);
}

/**
 * Format a comment timestamp in the WordPress timezone using DateTime.
 * Does not call date_default_timezone_set. Unparsable values stay as stored.
 *
 * @param mixed $value Unix, ISO-8601, or already-labeled string.
 * @return string
 */
function wpcable_comment_datetime_display( $value ) {
	if ( '' === $value || null === $value || false === $value ) {
		return '';
	}
	$raw = trim( (string) $value );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '/\b(PKT|Asia\/Karachi)\b/', $raw ) ) {
		return $raw;
	}

	try {
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	} catch ( Exception $e ) {
		$tz = new DateTimeZone( 'UTC' );
	}

	$dt = null;
	if ( is_numeric( $raw ) && (int) $raw > 100000 ) {
		try {
			$dt = new DateTimeImmutable( '@' . (int) $raw );
		} catch ( Exception $e ) {
			$dt = null;
		}
	}
	if ( ! $dt ) {
		try {
			$dt = new DateTimeImmutable( $raw );
		} catch ( Exception $e ) {
			$dt = null;
		}
	}
	if ( ! $dt ) {
		return $raw;
	}

	$dt = $dt->setTimezone( $tz );
	$df = get_option( 'date_format' );
	$tf = get_option( 'time_format' );
	$df = ( is_string( $df ) && $df ) ? $df : 'Y-m-d';
	$tf = ( is_string( $tf ) && $tf ) ? $tf : 'H:i';
	$abbr = $dt->format( 'T' );
	return $dt->format( $df . ' ' . $tf ) . ( $abbr ? ' ' . $abbr : '' );
}

/**
 * Current saved Codeable account id (non-secret profile option).
 *
 * @return string
 */
function wpcable_current_account_id() {
	$account = wpcable_get_account_details();
	$id      = wpcable_account_detail( $account, 'id', '' );
	return (string) $id;
}

/**
 * Whether cached history for this row may be shown to the current WP admin.
 * Disconnected: show saved authorized-account cache. Connected as a different
 * Codeable account: do not render that cache.
 *
 * @param array $task Task row.
 * @return bool
 */
function wpcable_history_cache_visible( $task ) {
	$stamp = isset( $task['history_account_id'] ) ? (string) $task['history_account_id'] : '';
	if ( '' === $stamp ) {
		return true;
	}
	if ( ! wpcable_sync_allowed() ) {
		return true;
	}
	$current = wpcable_current_account_id();
	if ( '' === $current ) {
		return true;
	}
	return $stamp === $current;
}

/**
 * Decode history_meta_json.
 *
 * @param mixed $raw JSON string or array.
 * @return array
 */
function wpcable_history_meta( $raw ) {
	if ( is_array( $raw ) ) {
		return $raw;
	}
	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Persist history columns without touching notes/flags.
 *
 * @param int   $task_id Task id.
 * @param array $fields  Column => value.
 * @return void
 */
function wpcable_save_history_cache( $task_id, $fields ) {
	global $wpdb;
	$task_id = (int) $task_id;
	if ( $task_id <= 0 || empty( $fields ) || ! is_array( $fields ) ) {
		return;
	}
	$allowed = array(
		'description_text'    => true,
		'description_present' => true,
		'description_key'     => true,
		'comments_json'       => true,
		'comments_present'    => true,
		'comments_complete'   => true,
		'comments_source'     => true,
		'history_account_id'  => true,
		'history_meta_json'   => true,
	);
	$data = array_intersect_key( $fields, $allowed );
	if ( empty( $data ) ) {
		return;
	}
	$table = $wpdb->prefix . 'codeable_tasks';
	$wpdb->update( $table, $data, array( 'task_id' => $task_id ) );
}

/**
 * Merge comment pages by id. Items without id are appended.
 *
 * @param array $old Existing items.
 * @param array $new New items.
 * @return array {items, added, duplicate_only}
 */
function wpcable_merge_comment_items( $old, $new ) {
	$seen = array();
	$out  = array();
	foreach ( is_array( $old ) ? $old : array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$out[] = $item;
		if ( isset( $item['id'] ) && '' !== (string) $item['id'] && null !== $item['id'] && false !== $item['id'] ) {
			$seen[ (string) $item['id'] ] = true;
		}
	}
	$added = 0;
	foreach ( is_array( $new ) ? $new : array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$id = isset( $item['id'] ) ? (string) $item['id'] : '';
		if ( '' !== $id && isset( $seen[ $id ] ) ) {
			continue;
		}
		if ( '' !== $id ) {
			$seen[ $id ] = true;
		}
		$out[] = $item;
		$added++;
	}
	return array(
		'items'          => $out,
		'added'          => $added,
		'duplicate_only' => ( 0 === $added && ! empty( $new ) ),
	);
}

/**
 * Fetch one imported project's description + comments via documented GETs.
 * Bound to a local task_id. Does not bulk backfill. Does not overwrite cache
 * when the remote call fails. One comments HTTP GET per click.
 *
 * @param int   $task_id Local imported task id.
 * @param array $args    {more:bool}.
 * @return array
 */
function wpcable_load_task_history_run( $task_id, $args = array() ) {
	$task_id = (int) $task_id;
	$more    = ! empty( $args['more'] );
	$repo    = new wpcable_tasks();
	$existing = $repo->get_task( $task_id );

	$result = array(
		'ok'            => false,
		'saved'         => false,
		'state'         => '',
		'message'       => '',
		'task'          => $existing,
		'history'       => $existing ? wpcable_task_history_for_display( $existing ) : array(),
		'can_load'      => false,
		'description'   => array(),
		'comments'      => array(),
		'calls'         => array(),
	);

	if ( ! $existing ) {
		$result['state']   = 'not_found';
		$result['message'] = __( 'That project is not in the local imported rows.', 'wpcable' );
		return $result;
	}

	if ( ! wpcable_sync_allowed() ) {
		$result['state']   = 'DISCONNECTED';
		$result['message'] = __( 'Load from Codeable is off while disconnected. Saved data stays visible.', 'wpcable' );
		$result['history'] = wpcable_task_history_for_display( $existing );
		return $result;
	}

	$result['can_load'] = true;
	$api = wpcable_api_calls::inst();
	if ( ! $api->auth_token_known() ) {
		$result['state']   = 'DISCONNECTED';
		$result['message'] = __( 'Load from Codeable is off while disconnected. Saved data stays visible.', 'wpcable' );
		return $result;
	}

	$account_id = wpcable_current_account_id();
	$meta       = wpcable_history_meta( isset( $existing['history_meta_json'] ) ? $existing['history_meta_json'] : '' );
	$fields     = array();
	$saved      = false;
	$desc_res   = array( 'status' => 0 );

	if ( ! $more ) {
		$desc_res = $api->get_with_meta( 'tasks/' . $task_id, array() );
		$result['calls'][] = array(
			'path'   => 'tasks/' . $task_id,
			'status' => $desc_res['status'],
		);
		$result['description'] = array(
			'http'   => (int) $desc_res['status'],
			'error'  => isset( $desc_res['error'] ) ? $desc_res['error'] : '',
			'saved'  => false,
		);

		if ( 200 === (int) $desc_res['status'] && is_array( $desc_res['body'] ) ) {
			$mapped = wpcable_payload_description( $desc_res['body'] );
			if ( $mapped['present'] ) {
				$fields['description_present'] = 1;
				$fields['description_key']     = $mapped['key'] ? $mapped['key'] : 'description';
				$fields['description_text']    = $mapped['text'];
				$result['description']['saved'] = true;
				$saved = true;
			}
			$meta['description'] = array(
				'route'  => 'GET /tasks/{id}',
				'http'   => 200,
				'source' => 'tasks_detail',
				'at'     => time(),
				'shape'  => array(
					'envelope' => wpcable_is_list( $desc_res['body'] ) ? 'list' : 'object',
					'top_keys' => wpcable_is_list( $desc_res['body'] ) ? array() : array_map( 'strval', array_keys( $desc_res['body'] ) ),
				),
			);
		} elseif ( 403 === (int) $desc_res['status'] ) {
			$meta['description'] = array(
				'route'  => 'GET /tasks/{id}',
				'http'   => 403,
				'source' => 'tasks_detail',
				'at'     => time(),
				'note'   => 'forbidden_not_overwritten',
			);
			$result['description']['forbidden'] = true;
		} elseif ( 204 === (int) $desc_res['status'] ) {
			$meta['description'] = array(
				'route'  => 'GET /tasks/{id}',
				'http'   => 204,
				'source' => 'tasks_detail',
				'at'     => time(),
			);
		} else {
			$meta['description'] = array(
				'route'  => 'GET /tasks/{id}',
				'http'   => (int) $desc_res['status'],
				'error'  => isset( $desc_res['error'] ) ? $desc_res['error'] : 'fetch_failed',
				'at'     => time(),
				'note'   => 'error_not_overwritten',
			);
		}
	}

	$existing_items = array();
	if ( $more && ! empty( $existing['comments_json'] ) ) {
		$decoded = json_decode( $existing['comments_json'], true );
		if ( is_array( $decoded ) ) {
			$existing_items = $decoded;
		}
	}

	$next_query = array();
	if ( $more ) {
		if ( empty( $meta['comments']['next_replay']['ok'] ) || empty( $meta['comments']['next_replay']['path'] ) ) {
			$result['state']   = 'partial';
			$result['message'] = __( 'No verified next comments URL is stored. Last saved cache was not overwritten.', 'wpcable' );
			$result['history'] = wpcable_task_history_for_display( $existing );
			return $result;
		}
		$next_query = isset( $meta['comments']['next_replay']['query'] ) && is_array( $meta['comments']['next_replay']['query'] )
			? $meta['comments']['next_replay']['query']
			: array();
	}

	$comments_fetch = wpcable_fetch_task_comments_page(
		$api,
		$task_id,
		array(
			'more'           => $more,
			'query'          => $more ? $next_query : array( 'limit' => 20, 'offset' => 0 ),
			'existing_items' => $existing_items,
		)
	);
	$result['calls']    = array_merge( $result['calls'], $comments_fetch['calls'] );
	$result['comments'] = array(
		'http'                 => $comments_fetch['http'],
		'saved'                => false,
		'complete'             => $comments_fetch['complete'],
		'partial'              => $comments_fetch['partial'],
		'reason'               => $comments_fetch['reason'],
		'forbidden'            => ! empty( $comments_fetch['forbidden'] ),
		'shape'                => $comments_fetch['shape'],
		'server_ignored_limit' => ! empty( $comments_fetch['server_ignored_limit'] ),
	);

	if ( $comments_fetch['ok'] ) {
		$fields['comments_present']  = 1;
		$fields['comments_complete'] = $comments_fetch['complete'] ? 1 : 0;
		$fields['comments_source']   = 'GET /tasks/{id}/comments';
		$fields['comments_json']     = wp_json_encode( $comments_fetch['items'] );
		$result['comments']['saved'] = true;
		$saved = true;
	}

	$meta['comments'] = array(
		'route'                 => 'GET /tasks/{id}/comments',
		'http'                  => $comments_fetch['http'],
		'source'                => 'tasks_comments',
		'at'                    => time(),
		'complete'              => $comments_fetch['complete'],
		'partial'               => $comments_fetch['partial'],
		'reason'                => $comments_fetch['reason'],
		'limit'                 => $comments_fetch['limit'],
		'offset'                => $comments_fetch['offset'],
		'returned'              => $comments_fetch['returned'],
		'page_returned'         => $comments_fetch['page_returned'],
		'total'                 => $comments_fetch['total'],
		'link'                  => $comments_fetch['link_sanitized'],
		'server_ignored_limit'  => $comments_fetch['server_ignored_limit'],
		'duplicate_only'        => $comments_fetch['duplicate_only'],
		'next_replay'           => $comments_fetch['next_replay'],
		'shape'                 => $comments_fetch['shape'],
	);

	if ( $saved ) {
		$fields['history_account_id'] = $account_id;
	}
	$fields['history_meta_json'] = wp_json_encode( $meta );

	wpcable_save_history_cache( $task_id, $fields );
	$fresh = $repo->get_task( $task_id );
	$result['task']    = $fresh;
	$result['history'] = wpcable_task_history_for_display( $fresh ? $fresh : $existing );
	$result['saved'] = $saved;

	if ( $saved ) {
		$result['state']   = $comments_fetch['complete'] ? 'loaded' : 'partial';
		$result['message'] = $comments_fetch['complete']
			? __( 'Saved description and comments from Codeable (read-only).', 'wpcable' )
			: ( $comments_fetch['reason'] ? $comments_fetch['reason'] : __( 'Saved a partial comments page. More may exist.', 'wpcable' ) );
		$result['ok'] = true;
	} elseif ( ! empty( $comments_fetch['forbidden'] ) || 403 === (int) $desc_res['status'] ) {
		$result['state']   = 'FORBIDDEN';
		$result['message'] = __( 'Codeable refused this read (403). Full task and private comments are only for the owner and assigned contractor. Last saved cache was not overwritten.', 'wpcable' );
		$result['ok']      = false;
	} else {
		$result['state']   = 'error';
		$result['message'] = __( 'Codeable read failed. Last saved cache was not overwritten.', 'wpcable' );
		$result['ok']      = false;
	}

	return $result;
}

/**
 * One comments GET. Completeness uses returned Link / X-Total-Count / short page.
 * Does not probe page= on the first request. Does not mark complete from the
 * requested limit when the server returns a different page size.
 *
 * @param wpcable_api_calls $api  API client.
 * @param int               $task_id Task id.
 * @param array             $opts {more, query, existing_items}.
 * @return array
 */
function wpcable_fetch_task_comments_page( $api, $task_id, $opts = array() ) {
	$task_id        = (int) $task_id;
	$query          = ( isset( $opts['query'] ) && is_array( $opts['query'] ) ) ? $opts['query'] : array( 'limit' => 20, 'offset' => 0 );
	$existing_items = isset( $opts['existing_items'] ) && is_array( $opts['existing_items'] ) ? $opts['existing_items'] : array();
	$more           = ! empty( $opts['more'] );
	$requested_limit = isset( $query['limit'] ) ? (int) $query['limit'] : 20;

	$path = 'tasks/' . $task_id . '/comments';
	$res  = $api->get_with_meta( $path, $query );
	$http = (int) $res['status'];
	$link = isset( $res['link'] ) ? (string) $res['link'] : '';
	$rels = wpcable_parse_link_header( $link );
	$link_for_shape = ! empty( $rels['next'] ) ? $rels['next'] : $link;
	$calls = array(
		array(
			'path'   => $path,
			'query'  => $query,
			'status' => $http,
		),
	);

	$total = null;
	$records_per_page = null;
	$page_count       = null;
	if ( ! empty( $res['headers'] ) && is_array( $res['headers'] ) ) {
		foreach ( $res['headers'] as $hk => $hv ) {
			$lk = strtolower( (string) $hk );
			if ( 'x-total-count' === $lk ) {
				$total = (int) $hv;
			} elseif ( 'x-records-per-page' === $lk ) {
				$records_per_page = (int) $hv;
			} elseif ( 'x-page-count' === $lk ) {
				$page_count = (int) $hv;
			}
		}
	}

	$out = array(
		'ok'                   => false,
		'items'                => $existing_items,
		'complete'             => false,
		'partial'              => true,
		'reason'               => '',
		'forbidden'            => false,
		'http'                 => $http,
		'calls'                => $calls,
		'limit'                => $requested_limit,
		'offset'               => isset( $query['offset'] ) ? (int) $query['offset'] : 0,
		'returned'             => count( $existing_items ),
		'page_returned'        => 0,
		'total'                => $total,
		'link'                 => $link,
		'link_sanitized'       => wpcable_sanitize_link_uri( $link_for_shape ),
		'server_ignored_limit' => false,
		'duplicate_only'       => false,
		'next_replay'          => array( 'ok' => false ),
		'shape'                => wpcable_comments_shape( isset( $res['body'] ) ? $res['body'] : null, isset( $res['headers'] ) ? $res['headers'] : array(), $link_for_shape ),
	);

	if ( 403 === $http ) {
		$out['forbidden'] = true;
		$out['reason']    = __( 'Codeable refused comments (403). Private comments are only visible to the client owner and assigned contractor. Saved comments were not overwritten.', 'wpcable' );
		return $out;
	}

	if ( 204 === $http ) {
		$out['ok'] = true;
		if ( ! $more ) {
			$out['items']    = array();
			$out['complete'] = true;
			$out['partial']  = false;
			$out['reason']   = __( 'No comments on this documented list (204).', 'wpcable' );
		} else {
			$out['complete'] = true;
			$out['partial']  = false;
			$out['reason']   = __( 'A later comments page returned 204. Treating fetched pages as complete.', 'wpcable' );
		}
		$out['returned'] = count( $out['items'] );
		return $out;
	}

	if ( 200 !== $http ) {
		$out['reason'] = __( 'Comments read failed. Saved comments were not overwritten.', 'wpcable' );
		return $out;
	}

	$extracted = wpcable_extract_comments_list( $res['body'] );
	if ( 'not_array' === $extracted['envelope'] || 'object_not_list' === $extracted['envelope'] ) {
		$out['reason'] = __( 'Comments response was not a list. Saved comments were not overwritten.', 'wpcable' );
		return $out;
	}

	$batch = array();
	foreach ( $extracted['list'] as $row ) {
		$item = wpcable_payload_normalize_comment( $row );
		if ( $item ) {
			$batch[] = $item;
		}
	}

	$page_returned = count( $batch );
	$out['page_returned'] = $page_returned;
	if ( $requested_limit > 0 && $page_returned !== $requested_limit ) {
		$out['server_ignored_limit'] = true;
	}

	$merged = wpcable_merge_comment_items( $more ? $existing_items : array(), $batch );
	$out['ok']             = true;
	$out['items']          = $merged['items'];
	$out['duplicate_only'] = $merged['duplicate_only'];
	$out['returned']       = count( $merged['items'] );

	$replay = array( 'ok' => false, 'reason' => 'no_next' );
	if ( ! empty( $rels['next'] ) ) {
		$replay = wpcable_comments_link_replay( $rels['next'], $task_id );
	}
	$out['next_replay'] = $replay;

	$observed_size = $records_per_page ? $records_per_page : $page_returned;
	$has_next      = ! empty( $replay['ok'] );

	if ( null !== $total && count( $merged['items'] ) >= $total ) {
		$out['complete'] = true;
		$out['partial']  = false;
		$out['reason']   = __( 'Fetched comments listed in X-Total-Count.', 'wpcable' );
		$out['next_replay']['ok'] = false;
		return $out;
	}

	if ( $merged['duplicate_only'] ) {
		$out['partial'] = true;
		$out['complete'] = false;
		$out['reason']   = __( 'Next comments page repeated IDs already saved. Not marked complete.', 'wpcable' );
		$out['next_replay']['ok'] = false;
		return $out;
	}

	if ( $has_next ) {
		$out['partial']  = true;
		$out['complete'] = false;
		if ( $out['server_ignored_limit'] ) {
			$out['reason'] = __( 'Saved this comments page. The server returned more rows than the requested limit, so limit/offset was not used to guess the next page. Use Load more (read-only) for the verified Link on api.codeable.io. Not marked complete.', 'wpcable' );
		} elseif ( 'replayed_onto_api.codeable.io' === $replay['reason'] ) {
			$out['reason'] = __( 'Saved this comments page. Link rel=next was on another documented host; the path/query will be replayed on api.codeable.io only (token is not sent cross-domain). Not marked complete.', 'wpcable' );
		} else {
			$out['reason'] = __( 'Saved this comments page. Link rel=next is verified for api.codeable.io /tasks/{id}/comments. Use Load more (read-only) for the next page. Not marked complete.', 'wpcable' );
		}
		return $out;
	}

	if ( ! empty( $rels['next'] ) && empty( $replay['ok'] ) ) {
		$out['partial']  = true;
		$out['complete'] = false;
		$why             = isset( $replay['reason'] ) ? $replay['reason'] : '';
		if ( 'v1_prefix_not_verified' === $why ) {
			$out['reason'] = __( 'Link rel=next uses a /v1 prefix, which is not a verified documented path, so it was not followed. This page is partial.', 'wpcable' );
		} elseif ( 'host_not_replayable' === $why ) {
			$out['reason'] = __( 'Link rel=next host is not api.codeable.io / api.codeable.com, so it was not followed (no cross-domain token). This page is partial.', 'wpcable' );
		} else {
			$out['reason'] = __( 'Link rel=next is not the documented /tasks/{id}/comments path, so it was not followed (no cross-domain token). This page is partial.', 'wpcable' );
		}
		return $out;
	}

	if ( $page_returned > 0 && $observed_size > 0 && $page_returned < $observed_size ) {
		$out['complete'] = true;
		$out['partial']  = false;
		$out['reason']   = __( 'Last comments page was short and no Link rel=next was returned.', 'wpcable' );
		return $out;
	}

	if ( null !== $page_count && isset( $query['page'] ) && (int) $query['page'] >= $page_count ) {
		$out['complete'] = true;
		$out['partial']  = false;
		$out['reason']   = __( 'Link/X-Page-Count indicates this is the last comments page.', 'wpcable' );
		return $out;
	}

	$out['partial']  = true;
	$out['complete'] = false;
	$out['reason']   = __( 'This comments page has no verified terminal signal (no short last page, no X-Total-Count, no Link rel=next). Not marked complete.', 'wpcable' );
	return $out;
}

/**
 * @deprecated 0.4.6 Use wpcable_fetch_task_comments_page (one GET per click).
 *
 * @param wpcable_api_calls $api     API client.
 * @param int               $task_id Task id.
 * @return array
 */
function wpcable_fetch_task_comments_pages( $api, $task_id ) {
	return wpcable_fetch_task_comments_page(
		$api,
		$task_id,
		array(
			'query'          => array(
				'limit'  => 20,
				'offset' => 0,
			),
			'existing_items' => array(),
		)
	);
}

/**
 * History for the drawer, with account scoping and load-action flags.
 *
 * @param array $task Task row.
 * @return array
 */
function wpcable_task_history_for_display( $task = array() ) {
	$history = wpcable_task_history( $task );
	$visible = wpcable_history_cache_visible( $task );
	$meta    = wpcable_history_meta( isset( $task['history_meta_json'] ) ? $task['history_meta_json'] : '' );

	if ( ! empty( $history['comments']['items'] ) && is_array( $history['comments']['items'] ) ) {
		foreach ( $history['comments']['items'] as $i => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$raw = isset( $item['created_at'] ) ? $item['created_at'] : '';
			$history['comments']['items'][ $i ]['created_at'] = wpcable_comment_datetime_display( $raw );
		}
	}

	$history['cache_visible'] = $visible;
	$history['can_load']      = wpcable_sync_allowed();
	$history['can_load_more'] = $history['can_load']
		&& empty( $history['comments']['complete'] )
		&& ! empty( $meta['comments']['next_replay']['ok'] );
	$history['load_label']      = __( 'Load from Codeable (read-only)', 'wpcable' );
	$history['load_more_label'] = __( 'Load more comments (read-only)', 'wpcable' );
	$history['load_help']       = __( 'Uses GET /tasks/{id} and GET /tasks/{id}/comments on the existing authorized account. Nothing is posted to Codeable. 2015 Apiary docs; production may differ.', 'wpcable' );
	$history['meta']            = $meta;
	$history['shape']           = ( isset( $meta['comments']['shape'] ) && is_array( $meta['comments']['shape'] ) )
		? $meta['comments']['shape']
		: array();

	if ( ! $visible ) {
		$history['description']['available'] = false;
		$history['description']['text']      = '';
		$history['description']['reason']    = __( 'Saved description belongs to a different connected Codeable account and is not shown.', 'wpcable' );
		$history['comments']['available']    = false;
		$history['comments']['items']        = array();
		$history['comments']['reason']       = __( 'Saved comments belong to a different connected Codeable account and are not shown.', 'wpcable' );
		$history['can_load_more']            = false;
		$history['shape']                    = array();
	}

	$source = isset( $task['comments_source'] ) ? (string) $task['comments_source'] : '';
	if ( ! empty( $meta['comments']['reason'] ) && ( empty( $history['comments']['reason'] ) || 0 === strpos( $source, 'GET ' ) ) ) {
		$history['comments']['reason'] = $meta['comments']['reason'];
	}

	return $history;
}
