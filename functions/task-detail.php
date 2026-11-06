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
 * Whether a Link URL is safe to treat as same-host documented comments pagination.
 * Does not follow the URL; only inspects it. Tokens never sent to another host.
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
	// Exact documented path only. Do not treat /v1/tasks/... as the same route.
	return (bool) preg_match( '#^/tasks/' . $id . '/comments/?$#', $path );
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
 * Fetch one imported project's description + comments via documented GETs.
 * Bound to a local task_id. Does not bulk backfill. Does not overwrite cache
 * when the remote call fails.
 *
 * @param int $task_id Local imported task id.
 * @return array
 */
function wpcable_load_task_history_run( $task_id ) {
	$task_id = (int) $task_id;
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

	$comments_fetch = wpcable_fetch_task_comments_pages( $api, $task_id );
	$result['calls'] = array_merge( $result['calls'], $comments_fetch['calls'] );
	$result['comments'] = array(
		'http'     => $comments_fetch['http'],
		'saved'    => false,
		'complete' => $comments_fetch['complete'],
		'partial'  => $comments_fetch['partial'],
		'reason'   => $comments_fetch['reason'],
		'forbidden'=> ! empty( $comments_fetch['forbidden'] ),
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
		'route'    => 'GET /tasks/{id}/comments',
		'http'     => $comments_fetch['http'],
		'source'   => 'tasks_comments',
		'at'       => time(),
		'complete' => $comments_fetch['complete'],
		'partial'  => $comments_fetch['partial'],
		'reason'   => $comments_fetch['reason'],
		'limit'    => $comments_fetch['limit'],
		'offset'   => $comments_fetch['offset'],
		'returned' => $comments_fetch['returned'],
		'total'    => $comments_fetch['total'],
		'link'     => $comments_fetch['link'],
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
 * Page comments with documented limit/offset. Completeness uses returned
 * Link / X-Total-Count only. Does not switch to ?page= as a probe.
 *
 * @param wpcable_api_calls $api     API client.
 * @param int               $task_id Task id.
 * @return array
 */
function wpcable_fetch_task_comments_pages( $api, $task_id ) {
	$limit     = 20;
	$offset    = 0;
	$max_pages = 10;
	$items     = array();
	$calls     = array();
	$last_http = 0;
	$last_link = '';
	$total     = null;
	$complete  = false;
	$partial   = false;
	$reason    = '';
	$forbidden = false;
	$ok        = false;

	for ( $n = 0; $n < $max_pages; $n++ ) {
		$res = $api->get_with_meta(
			'tasks/' . (int) $task_id . '/comments',
			array(
				'limit'  => $limit,
				'offset' => $offset,
			)
		);
		$last_http = (int) $res['status'];
		$last_link = isset( $res['link'] ) ? (string) $res['link'] : '';
		$calls[]   = array(
			'path'   => 'tasks/' . (int) $task_id . '/comments',
			'query'  => array( 'limit' => $limit, 'offset' => $offset ),
			'status' => $last_http,
		);

		if ( ! empty( $res['headers']['x-total-count'] ) ) {
			$total = (int) $res['headers']['x-total-count'];
		} elseif ( ! empty( $res['headers']['X-Total-Count'] ) ) {
			$total = (int) $res['headers']['X-Total-Count'];
		}

		if ( 403 === $last_http ) {
			$forbidden = true;
			$reason    = __( 'Codeable refused comments (403). Private comments are only visible to the client owner and assigned contractor. Saved comments were not overwritten.', 'wpcable' );
			break;
		}

		if ( 204 === $last_http ) {
			$ok       = true;
			$complete = ( 0 === $offset );
			$partial  = ! $complete;
			$reason   = $complete
				? __( 'No comments on this documented list (204).', 'wpcable' )
				: __( 'A later comments page returned 204. Treating the thread as complete for fetched pages.', 'wpcable' );
			break;
		}

		if ( 200 !== $last_http ) {
			$reason = __( 'Comments read failed. Saved comments were not overwritten.', 'wpcable' );
			break;
		}

		$body = $res['body'];
		if ( ! is_array( $body ) ) {
			$reason = __( 'Comments response was not a list. Saved comments were not overwritten.', 'wpcable' );
			break;
		}

		$batch = array();
		foreach ( $body as $row ) {
			$item = wpcable_payload_normalize_comment( $row );
			if ( $item ) {
				if ( is_numeric( $item['created_at'] ) && (int) $item['created_at'] > 100000 ) {
					$formatted = wpcable_date( (int) $item['created_at'] );
					if ( $formatted ) {
						$item['created_at'] = $formatted;
					}
				}
				$batch[] = $item;
			}
		}
		$items = array_merge( $items, $batch );
		$ok    = true;

		$rels = wpcable_parse_link_header( $last_link );
		if ( null !== $total && count( $items ) >= $total ) {
			$complete = true;
			$reason   = __( 'Fetched comments listed in X-Total-Count.', 'wpcable' );
			break;
		}

		if ( ! empty( $rels['next'] ) ) {
			if ( ! wpcable_link_is_same_host_comments( $rels['next'], $task_id ) ) {
				$partial = true;
				$complete = false;
				$reason   = __( 'Link rel=next is not the same api.codeable.io /tasks/{id}/comments URL, so it was not followed (no cross-domain token). This page is partial.', 'wpcable' );
				break;
			}
			$offset += $limit;
			continue;
		}

		if ( count( $batch ) < $limit ) {
			$complete = true;
			$reason   = __( 'Last comments page was short and no Link rel=next was returned.', 'wpcable' );
			break;
		}

		$partial  = true;
		$complete = false;
		$reason   = __( 'This comments page was full and no Link rel=next was returned, so more comments may exist. Not marked complete.', 'wpcable' );
		break;
	}

	if ( $ok && ! $complete && ! $partial && count( $items ) > 0 ) {
		$partial = true;
		$reason  = __( 'Stopped at the bounded comments page limit. Not marked complete.', 'wpcable' );
	}

	return array(
		'ok'        => $ok,
		'items'     => $items,
		'complete'  => $complete,
		'partial'   => $partial,
		'reason'    => $reason,
		'forbidden' => $forbidden,
		'http'      => $last_http,
		'calls'     => $calls,
		'limit'     => $limit,
		'offset'    => $offset,
		'returned'  => count( $items ),
		'total'     => $total,
		'link'      => $last_link,
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

	$history['cache_visible'] = $visible;
	$history['can_load']      = wpcable_sync_allowed();
	$history['load_label']    = __( 'Load from Codeable (read-only)', 'wpcable' );
	$history['load_help']     = __( 'Uses GET /tasks/{id} and GET /tasks/{id}/comments on the existing authorized account. Nothing is posted to Codeable. 2015 Apiary docs; production may differ.', 'wpcable' );
	$history['meta']          = $meta;

	if ( ! $visible ) {
		$history['description']['available'] = false;
		$history['description']['text']      = '';
		$history['description']['reason']    = __( 'Saved description belongs to a different connected Codeable account and is not shown.', 'wpcable' );
		$history['comments']['available']    = false;
		$history['comments']['items']        = array();
		$history['comments']['reason']       = __( 'Saved comments belong to a different connected Codeable account and are not shown.', 'wpcable' );
	}

	$source = isset( $task['comments_source'] ) ? (string) $task['comments_source'] : '';
	if ( ! empty( $meta['comments']['reason'] ) && ( empty( $history['comments']['reason'] ) || 0 === strpos( $source, 'GET ' ) ) ) {
		$history['comments']['reason'] = $meta['comments']['reason'];
	}

	return $history;
}
