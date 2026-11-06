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
 * Whether a query key looks like a credential.
 *
 * @param string $key Query key.
 * @return bool
 */
function wpcable_link_query_key_is_secret( $key ) {
	return (bool) preg_match( '/token|secret|auth|password|bearer|key|credential|access/i', (string) $key );
}

/**
 * Integer paging params only (page/limit/offset/per_page).
 *
 * @param array $query Parsed query.
 * @return array
 */
function wpcable_comments_paging_query( $query ) {
	$safe = array();
	if ( ! is_array( $query ) ) {
		return $safe;
	}
	foreach ( array( 'page', 'limit', 'offset', 'per_page' ) as $k ) {
		if ( isset( $query[ $k ] ) && is_scalar( $query[ $k ] ) && preg_match( '/^[0-9]+$/', (string) $query[ $k ] ) ) {
			$safe[ $k ] = (int) $query[ $k ];
		}
	}
	return $safe;
}

/**
 * Keys-only inspection of a Link URI. Relative paths keep path + paging.
 * Never includes userinfo, tokens, or non-paging query values.
 *
 * @param string $url URL or relative reference from Link.
 * @return array
 */
function wpcable_inspect_link_uri( $url ) {
	$out = array(
		'host'              => '',
		'path'              => '',
		'paging'            => array(),
		'sanitized'         => '',
		'relative'          => false,
		'userinfo_stripped' => false,
		'credential_query_keys' => array(),
		'other_query_keys'  => array(),
	);
	if ( ! is_string( $url ) ) {
		return $out;
	}
	$url = trim( $url );
	if ( '' === $url ) {
		return $out;
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return $out;
	}
	if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
		$out['userinfo_stripped'] = true;
	}
	if ( empty( $parts['host'] ) && empty( $parts['scheme'] ) && ! empty( $parts['path'] ) ) {
		$out['relative'] = true;
	}
	$out['host'] = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$out['path'] = isset( $parts['path'] ) ? (string) $parts['path'] : '';

	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	if ( ! is_array( $query ) ) {
		$query = array();
	}
	$out['paging'] = wpcable_comments_paging_query( $query );
	foreach ( $query as $k => $v ) {
		$k = (string) $k;
		if ( wpcable_link_query_key_is_secret( $k ) ) {
			$out['credential_query_keys'][] = $k;
			continue;
		}
		if ( ! isset( $out['paging'][ $k ] ) ) {
			$out['other_query_keys'][] = $k;
		}
	}

	$q = $out['paging'] ? http_build_query( $out['paging'] ) : '';
	if ( $out['host'] ) {
		$scheme           = ! empty( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$out['sanitized'] = $scheme . '://' . $out['host'] . $out['path'] . ( $q ? '?' . $q : '' );
	} elseif ( $out['path'] ) {
		$out['sanitized'] = $out['path'] . ( $q ? '?' . $q : '' );
	}
	return $out;
}

/**
 * Strip credentials from a Link URI. Host + path + integer paging only.
 *
 * @param string $url URL.
 * @return string
 */
function wpcable_sanitize_link_uri( $url ) {
	$ins = wpcable_inspect_link_uri( $url );
	return isset( $ins['sanitized'] ) ? (string) $ins['sanitized'] : '';
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
	$inspect = wpcable_inspect_link_uri( $url );
	$out     = array(
		'ok'          => false,
		'reason'      => '',
		'path'        => '',
		'query'       => array(),
		'source_host' => $inspect['host'],
		'sanitized'   => $inspect['sanitized'],
		'inspect'     => array(
			'host'                  => $inspect['host'],
			'path'                  => $inspect['path'],
			'paging'                => $inspect['paging'],
			'sanitized'             => $inspect['sanitized'],
			'relative'              => ! empty( $inspect['relative'] ),
			'credential_query_keys' => $inspect['credential_query_keys'],
			'other_query_keys'      => $inspect['other_query_keys'],
		),
	);
	if ( ! empty( $inspect['relative'] ) || '' === $inspect['host'] ) {
		$out['reason'] = $inspect['path'] ? 'relative_no_host' : 'unparseable';
		return $out;
	}
	if ( ! in_array( $inspect['host'], array( 'api.codeable.io', 'api.codeable.com' ), true ) ) {
		$out['reason'] = 'host_not_replayable';
		return $out;
	}
	$path = $inspect['path'];
	if ( preg_match( '#^/v1/#i', $path ) ) {
		$out['reason'] = 'v1_prefix_not_verified';
		return $out;
	}
	$id = preg_quote( (string) $task_id, '#' );
	if ( ! preg_match( '#^/tasks/' . $id . '/comments/?$#', $path ) ) {
		$out['reason'] = 'path_not_documented_comments';
		return $out;
	}
	$out['ok']     = true;
	$out['path']   = 'tasks/' . $task_id . '/comments';
	$out['query']  = $inspect['paging'];
	$out['reason'] = ( 'api.codeable.io' === $inspect['host'] )
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
	$inspect = wpcable_inspect_link_uri( $link );
	$shape   = array(
		'envelope'        => '',
		'top_keys'        => array(),
		'item_count'      => 0,
		'comment_keys'    => array(),
		'nested'          => array(),
		'headers_present' => array(),
		'link'            => $inspect['sanitized'],
		'link_next'       => array(
			'host'             => $inspect['host'],
			'path'             => $inspect['path'],
			'paging'           => $inspect['paging'],
			'sanitized'        => $inspect['sanitized'],
			'relative'              => ! empty( $inspect['relative'] ),
			'credential_query_keys' => $inspect['credential_query_keys'],
			'other_query_keys'      => $inspect['other_query_keys'],
			'replay_ok'        => false,
			'replay_reason'    => '',
		),
		'paging_headers'  => array(
			'x-total-count'       => null,
			'x-records-per-page'  => null,
			'x-page-count'        => null,
		),
	);
	if ( is_array( $headers ) ) {
		foreach ( $headers as $hk => $hv ) {
			$lk = strtolower( (string) $hk );
			$shape['headers_present'][] = $lk;
			$val = is_array( $hv ) ? reset( $hv ) : $hv;
			if ( 'x-total-count' === $lk && is_numeric( $val ) ) {
				$shape['paging_headers']['x-total-count'] = (int) $val;
			} elseif ( 'x-records-per-page' === $lk && is_numeric( $val ) ) {
				$shape['paging_headers']['x-records-per-page'] = (int) $val;
			} elseif ( 'x-page-count' === $lk && is_numeric( $val ) ) {
				$shape['paging_headers']['x-page-count'] = (int) $val;
			}
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
 * Stamp replay inspect onto a keys-only shape. No secrets or bodies.
 *
 * @param array $shape  Shape.
 * @param array $replay Replay result.
 * @return array
 */
function wpcable_comments_shape_attach_link( $shape, $replay ) {
	if ( ! is_array( $shape ) ) {
		$shape = array();
	}
	if ( ! isset( $shape['link_next'] ) || ! is_array( $shape['link_next'] ) ) {
		$shape['link_next'] = array();
	}
	if ( isset( $replay['inspect'] ) && is_array( $replay['inspect'] ) ) {
		$shape['link_next'] = array_merge( $shape['link_next'], $replay['inspect'] );
	}
	if ( isset( $replay['sanitized'] ) ) {
		$shape['link_next']['sanitized'] = (string) $replay['sanitized'];
		$shape['link']                   = (string) $replay['sanitized'];
	}
	$shape['link_next']['replay_ok']     = ! empty( $replay['ok'] );
	$shape['link_next']['replay_reason'] = isset( $replay['reason'] ) ? (string) $replay['reason'] : '';
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
 * Comment timestamps always display in PKT (Asia/Karachi).
 * Ignores an empty or misconfigured WordPress timezone. Does not call
 * date_default_timezone_set. Unparsable values stay as stored.
 *
 * @return DateTimeZone
 */
function wpcable_comment_display_timezone() {
	try {
		return new DateTimeZone( 'Asia/Karachi' );
	} catch ( Exception $e ) {
		return new DateTimeZone( 'UTC' );
	}
}

/**
 * Format a comment timestamp in PKT using DateTime.
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

	$tz = wpcable_comment_display_timezone();

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
	if ( ! $abbr || 'UTC' === $abbr ) {
		$abbr = 'PKT';
	}
	return $dt->format( $df . ' ' . $tf ) . ' ' . $abbr;
}

/**
 * Stable comments GET signature for duplicate-page detection.
 *
 * @param string $path  Allowlisted path.
 * @param array  $query Query args.
 * @return string
 */
function wpcable_comments_query_sig( $path, $query = array() ) {
	$path = (string) $path;
	if ( ! is_array( $query ) ) {
		$query = array();
	}
	ksort( $query );
	$parts = array();
	foreach ( $query as $k => $v ) {
		if ( is_scalar( $v ) ) {
			$parts[] = (string) $k . '=' . (string) $v;
		}
	}
	return $path . ( $parts ? '?' . implode( '&', $parts ) : '' );
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
 * Bound to a local task_id. One comments HTTP GET per call. Bulk backfill ticks
 * call this repeatedly with a request budget. Does not overwrite cache when the
 * remote call fails.
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

		if ( 401 === (int) $desc_res['status'] ) {
			$fields['history_meta_json'] = wp_json_encode( $meta );
			wpcable_save_history_cache( $task_id, $fields );
			$result['state']       = 'AUTH_EXPIRED';
			$result['message']     = __( 'Codeable authorization expired (401). Saved cache was not overwritten. Sign in again, then resume.', 'wpcable' );
			$result['retry_after'] = isset( $desc_res['retry_after'] ) ? (int) $desc_res['retry_after'] : 0;
			$result['history']     = wpcable_task_history_for_display( $existing );
			return $result;
		}
		if ( 429 === (int) $desc_res['status'] ) {
			$fields['history_meta_json'] = wp_json_encode( $meta );
			wpcable_save_history_cache( $task_id, $fields );
			$result['state']       = 'RATE_LIMITED';
			$result['message']     = __( 'Codeable rate-limited this read (429). Saved cache was not overwritten.', 'wpcable' );
			$result['retry_after'] = isset( $desc_res['retry_after'] ) ? (int) $desc_res['retry_after'] : 0;
			$result['history']     = wpcable_task_history_for_display( $existing );
			return $result;
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

	$last_sig = isset( $meta['comments']['last_query_sig'] ) ? (string) $meta['comments']['last_query_sig'] : '';
	$comments_fetch = wpcable_fetch_task_comments_page(
		$api,
		$task_id,
		array(
			'more'            => $more,
			'query'           => $more ? $next_query : array( 'limit' => 20, 'offset' => 0 ),
			'existing_items'  => $existing_items,
			'last_query_sig'  => $more ? $last_sig : '',
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
		'next_replay'          => $comments_fetch['next_replay'],
		'query_sig'            => isset( $comments_fetch['query_sig'] ) ? $comments_fetch['query_sig'] : '',
		'duplicate_only'       => ! empty( $comments_fetch['duplicate_only'] ),
		'loop_guard'           => ! empty( $comments_fetch['loop_guard'] ),
	);
	$result['retry_after'] = isset( $comments_fetch['retry_after'] ) ? (int) $comments_fetch['retry_after'] : 0;

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
		'last_query_sig'        => isset( $comments_fetch['query_sig'] ) ? $comments_fetch['query_sig'] : $last_sig,
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

	if ( ! empty( $comments_fetch['loop_guard'] ) ) {
		$result['state']   = 'loop_guard';
		$result['message'] = __( 'The next comments request matched the last page already fetched. Stopped without looping. Last saved cache was not overwritten.', 'wpcable' );
		$result['ok']      = false;
	} elseif ( 401 === (int) $comments_fetch['http'] ) {
		$result['state']   = 'AUTH_EXPIRED';
		$result['message'] = __( 'Codeable authorization expired (401). Saved cache was not overwritten. Sign in again, then resume.', 'wpcable' );
		$result['ok']      = false;
	} elseif ( 429 === (int) $comments_fetch['http'] ) {
		$result['state']   = 'RATE_LIMITED';
		$result['message'] = __( 'Codeable rate-limited this read (429). Saved cache was not overwritten.', 'wpcable' );
		$result['ok']      = false;
	} elseif ( $comments_fetch['ok'] ) {
		$result['state']   = $comments_fetch['complete'] ? 'loaded' : 'partial';
		$result['message'] = $comments_fetch['complete']
			? __( 'Saved description and comments from Codeable (read-only).', 'wpcable' )
			: ( $comments_fetch['reason'] ? $comments_fetch['reason'] : __( 'Saved a partial comments page. More may exist.', 'wpcable' ) );
		$result['ok'] = true;
	} elseif ( ! empty( $comments_fetch['forbidden'] ) || 403 === (int) $desc_res['status'] ) {
		$result['state']   = 'FORBIDDEN';
		$result['message'] = __( 'Codeable refused this read (403). Full task and private comments are only for the owner and assigned contractor. Last saved cache was not overwritten.', 'wpcable' );
		$result['ok']      = false;
	} elseif ( (int) $comments_fetch['http'] >= 500 || ( 0 === (int) $comments_fetch['http'] && empty( $comments_fetch['loop_guard'] ) ) ) {
		$result['state']   = 'error';
		$result['message'] = __( 'Codeable read failed. Last saved cache was not overwritten.', 'wpcable' );
		$result['ok']      = false;
	} elseif ( $saved ) {
		$result['state']   = 'partial';
		$result['message'] = __( 'Saved description; comments were not updated. Last comments cache was not overwritten.', 'wpcable' );
		$result['ok']      = true;
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
	$last_query_sig  = isset( $opts['last_query_sig'] ) ? (string) $opts['last_query_sig'] : '';

	$path = 'tasks/' . $task_id . '/comments';
	$query_sig = wpcable_comments_query_sig( $path, $query );
	if ( $more && $last_query_sig && $query_sig === $last_query_sig ) {
		return array(
			'ok'                   => false,
			'items'                => $existing_items,
			'complete'             => false,
			'partial'              => true,
			'reason'               => __( 'The next comments request matched the last page already fetched. Not sent again.', 'wpcable' ),
			'forbidden'            => false,
			'http'                 => 0,
			'calls'                => array(),
			'limit'                => $requested_limit,
			'offset'               => isset( $query['offset'] ) ? (int) $query['offset'] : 0,
			'returned'             => count( $existing_items ),
			'page_returned'        => 0,
			'total'                => null,
			'link'                 => '',
			'link_sanitized'       => '',
			'server_ignored_limit' => false,
			'duplicate_only'       => true,
			'loop_guard'           => true,
			'next_replay'          => array( 'ok' => false, 'reason' => 'repeated_query' ),
			'shape'                => array(),
			'query_sig'            => $query_sig,
			'retry_after'          => 0,
		);
	}

	$res  = $api->get_with_meta( $path, $query );
	$http = (int) $res['status'];
	$link = isset( $res['link'] ) ? (string) $res['link'] : '';
	$rels = wpcable_parse_link_header( $link );
	$link_for_shape = ! empty( $rels['next'] ) ? $rels['next'] : $link;
	$replay         = array(
		'ok'        => false,
		'reason'    => ! empty( $rels['next'] ) ? '' : 'no_next',
		'sanitized' => wpcable_sanitize_link_uri( $link_for_shape ),
		'inspect'   => wpcable_inspect_link_uri( $link_for_shape ),
	);
	if ( ! empty( $rels['next'] ) ) {
		$replay = wpcable_comments_link_replay( $rels['next'], $task_id );
	}
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
		'link_sanitized'       => isset( $replay['sanitized'] ) ? (string) $replay['sanitized'] : wpcable_sanitize_link_uri( $link_for_shape ),
		'server_ignored_limit' => false,
		'duplicate_only'       => false,
		'loop_guard'           => false,
		'next_replay'          => $replay,
		'shape'                => wpcable_comments_shape_attach_link(
			wpcable_comments_shape( isset( $res['body'] ) ? $res['body'] : null, isset( $res['headers'] ) ? $res['headers'] : array(), $link_for_shape ),
			$replay
		),
		'query_sig'            => $query_sig,
		'retry_after'          => isset( $res['retry_after'] ) ? (int) $res['retry_after'] : 0,
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

	$out['next_replay'] = $replay;
	$out['shape']       = wpcable_comments_shape_attach_link( $out['shape'], $replay );

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
 * Resolve a comment author from already-stored local profile/clients only.
 * Does not call Codeable.
 *
 * @param int $user_id Codeable user id.
 * @return array {name, source}
 */
function wpcable_local_comment_author( $user_id ) {
	$out     = array(
		'name'   => '',
		'source' => '',
	);
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return $out;
	}

	$account = wpcable_get_account_details();
	$aid     = (int) wpcable_account_detail( $account, 'id', 0 );
	if ( $aid && $aid === $user_id ) {
		$full  = trim( (string) wpcable_account_detail( $account, 'full_name', '' ) );
		$first = (string) wpcable_account_detail( $account, 'first_name', '' );
		$last  = (string) wpcable_account_detail( $account, 'last_name', '' );
		$name  = $full ? $full : trim( $first . ' ' . $last );
		if ( $name ) {
			$out['name']   = $name;
			$out['source'] = 'local_account';
			return $out;
		}
	}

	global $wpdb;
	if ( ! $wpdb ) {
		return $out;
	}
	$table = $wpdb->prefix . 'codeable_clients';
	$row   = $wpdb->get_row(
		$wpdb->prepare( "SELECT full_name FROM {$table} WHERE client_id = %d LIMIT 1", $user_id ),
		ARRAY_A
	);
	if ( is_array( $row ) && ! empty( $row['full_name'] ) ) {
		$out['name']   = (string) $row['full_name'];
		$out['source'] = 'local_client';
	}
	return $out;
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
			if ( empty( $item['author'] ) && ! empty( $item['user_id'] ) ) {
				$mapped = wpcable_local_comment_author( $item['user_id'] );
				if ( $mapped['name'] ) {
					$history['comments']['items'][ $i ]['author']        = $mapped['name'];
					$history['comments']['items'][ $i ]['author_source'] = $mapped['source'];
				}
			}
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
	$history['link_next'] = array();
	if ( ! empty( $history['shape']['link_next'] ) && is_array( $history['shape']['link_next'] ) ) {
		$history['link_next'] = $history['shape']['link_next'];
	} elseif ( ! empty( $meta['comments']['next_replay']['inspect'] ) && is_array( $meta['comments']['next_replay']['inspect'] ) ) {
		$history['link_next'] = $meta['comments']['next_replay']['inspect'];
		$history['link_next']['replay_ok']     = ! empty( $meta['comments']['next_replay']['ok'] );
		$history['link_next']['replay_reason'] = isset( $meta['comments']['next_replay']['reason'] ) ? $meta['comments']['next_replay']['reason'] : '';
		$history['link_next']['sanitized']     = isset( $meta['comments']['next_replay']['sanitized'] ) ? $meta['comments']['next_replay']['sanitized'] : '';
	}
	$history['paging_headers'] = ( isset( $history['shape']['paging_headers'] ) && is_array( $history['shape']['paging_headers'] ) )
		? $history['shape']['paging_headers']
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
		$history['link_next']                = array();
		$history['paging_headers']           = array();
	}

	$source = isset( $task['comments_source'] ) ? (string) $task['comments_source'] : '';
	if ( ! empty( $meta['comments']['reason'] ) && ( empty( $history['comments']['reason'] ) || 0 === strpos( $source, 'GET ' ) ) ) {
		$history['comments']['reason'] = $meta['comments']['reason'];
	}

	return $history;
}
