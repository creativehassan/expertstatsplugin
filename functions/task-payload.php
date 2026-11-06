<?php
/**
 * Map description/comments from an already-permitted task payload.
 * Does not invent HTTP routes. Missing key ≠ empty value.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pull a project description string from a task-list (or single-task) object
 * if the key is already present. Does not call a new endpoint.
 *
 * Provenance: this plugin historically mapped only title/state/prices/flags
 * from GET users/me/tasks/{filter}. Public Apiary blueprint
 * (https://codeable.docs.apiary.io/, resource `/tasks/{id}` GET) documents a
 * `description` field on the individual task. List samples vary: suggestions
 * include `description`; inquiring/in_progress/archive samples do not.
 * Dedicated comments live at GET `/tasks/{id}/comments` in that blueprint.
 * This mapper still only reads keys already on the permitted list object;
 * it does not add those extra HTTP routes (pagination/visibility on comments
 * are internally inconsistent in the blueprint — see docs/APIARY-CODEABLE.md).
 *
 * @param array $task Raw API task object.
 * @return array {present, key, text}
 */
function wpcable_payload_description( $task ) {
	$out = array(
		'present' => false,
		'key'     => '',
		'text'    => null,
	);
	if ( ! is_array( $task ) ) {
		return $out;
	}

	$keys = array( 'description', 'body', 'details', 'task_description' );
	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $task ) ) {
			continue;
		}
		$val = $task[ $key ];
		if ( is_array( $val ) ) {
			if ( isset( $val['body'] ) && is_string( $val['body'] ) ) {
				$val = $val['body'];
			} elseif ( isset( $val['text'] ) && is_string( $val['text'] ) ) {
				$val = $val['text'];
			} else {
				continue;
			}
		}
		if ( ! is_string( $val ) && ! is_numeric( $val ) ) {
			continue;
		}
		$out['present'] = true;
		$out['key']     = $key;
		$out['text']    = (string) $val;
		return $out;
	}

	return $out;
}

/**
 * Normalize one comment-like object from a payload array. Skips non-arrays.
 * Visibility flags (private / is_contractors_only) are stored when present;
 * they are not inferred. Only data already in the authorized payload is kept.
 *
 * @param mixed $row Raw comment.
 * @return array|null
 */
function wpcable_payload_normalize_comment( $row ) {
	if ( ! is_array( $row ) ) {
		return null;
	}

	$body = '';
	foreach ( array( 'body', 'text', 'content', 'message', 'comment' ) as $key ) {
		if ( isset( $row[ $key ] ) && ( is_string( $row[ $key ] ) || is_numeric( $row[ $key ] ) ) ) {
			$body = (string) $row[ $key ];
			break;
		}
	}

	$user = isset( $row['user'] ) && is_array( $row['user'] ) ? $row['user'] : array();
	$name = '';
	if ( isset( $user['full_name'] ) ) {
		$name = (string) $user['full_name'];
	} elseif ( isset( $user['first_name'] ) ) {
		$name = trim( $user['first_name'] . ' ' . ( isset( $user['last_name'] ) ? $user['last_name'] : '' ) );
	}

	$private = null;
	if ( array_key_exists( 'private', $row ) ) {
		$private = ! empty( $row['private'] );
	}

	$contractors_only = null;
	if ( array_key_exists( 'is_contractors_only', $row ) ) {
		$contractors_only = ! empty( $row['is_contractors_only'] );
	}

	$visibility = 'unknown';
	if ( true === $private ) {
		$visibility = 'private';
	} elseif ( true === $contractors_only ) {
		$visibility = 'contractors_only';
	} elseif ( false === $private && false === $contractors_only ) {
		$visibility = 'public';
	} elseif ( false === $private ) {
		$visibility = 'public';
	}

	$id = 0;
	if ( isset( $row['id'] ) && is_numeric( $row['id'] ) ) {
		$id = (int) $row['id'];
	}

	return array(
		'id'                 => $id,
		'body'               => $body,
		'author'             => $name,
		'role'               => isset( $user['role'] ) ? (string) $user['role'] : '',
		'private'            => $private,
		'is_contractors_only'=> $contractors_only,
		'visibility'         => $visibility,
		'created_at'         => isset( $row['created_at'] ) ? (string) $row['created_at'] : ( isset( $row['published_at'] ) ? (string) $row['published_at'] : '' ),
	);
}

/**
 * Pull comments from the same task object the list importer already fetches.
 * Does not call GET /task/{id}/comments (that path appears only in a public
 * third-party Chrome extension, not in official Codeable docs and not in this
 * plugin). Completeness of public/private/pagination is unknown unless the
 * payload itself includes those arrays.
 *
 * @param array $task Raw API task object.
 * @return array {present, complete, source, items}
 */
function wpcable_payload_comments( $task ) {
	$out = array(
		'present'  => false,
		'complete' => false,
		'source'   => '',
		'items'    => array(),
	);
	if ( ! is_array( $task ) ) {
		return $out;
	}

	$keys = array(
		'comments',
		'public_comments',
		'private_comments',
		'messages',
		'events',
	);

	$collected = array();
	$found_key = '';
	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $task ) || ! is_array( $task[ $key ] ) ) {
			continue;
		}
		$found_key = $found_key ? $found_key . '+' . $key : $key;
		foreach ( $task[ $key ] as $row ) {
			$item = wpcable_payload_normalize_comment( $row );
			if ( $item && ( '' !== $item['body'] || $item['id'] ) ) {
				if ( 'private_comments' === $key ) {
					$item['visibility'] = 'private';
					$item['private']    = true;
				} elseif ( 'public_comments' === $key && 'unknown' === $item['visibility'] ) {
					$item['visibility'] = 'public';
					$item['private']    = false;
				}
				$collected[] = $item;
			}
		}
	}

	if ( $found_key ) {
		$out['present']  = true;
		$out['source']   = $found_key;
		$out['items']    = $collected;
		$out['complete'] = false;
		return $out;
	}

	return $out;
}

/**
 * Merge payload extractors into the local task row (additive columns).
 *
 * @param array $new_task Local row being built.
 * @param array $task     Raw API object.
 * @return array
 */
function wpcable_apply_payload_history( $new_task, $task ) {
	$desc = wpcable_payload_description( $task );
	if ( $desc['present'] ) {
		$new_task['description_present'] = 1;
		$new_task['description_key']     = $desc['key'];
		$new_task['description_text']    = $desc['text'];
	}

	$comments = wpcable_payload_comments( $task );
	if ( $comments['present'] ) {
		$new_task['comments_present']  = 1;
		$new_task['comments_complete'] = $comments['complete'] ? 1 : 0;
		$new_task['comments_source']   = $comments['source'];
		$new_task['comments_json']     = wp_json_encode( $comments['items'] );
	}

	return $new_task;
}

/**
 * History panel payload: stored records vs missing-from-payload vs empty.
 * Does not claim the Codeable API lacks description/comments.
 *
 * @param array $task Sanitized/local task row.
 * @return array
 */
function wpcable_task_history( $task = array() ) {
	$task_id = isset( $task['task_id'] ) ? (int) $task['task_id'] : 0;

	$desc_present = ! empty( $task['description_present'] );
	$desc_text    = array_key_exists( 'description_text', $task ) ? $task['description_text'] : null;
	$desc_empty   = $desc_present && is_string( $desc_text ) && '' === trim( $desc_text );

	$description = array(
		'available'         => $desc_present && ! $desc_empty,
		'present_in_payload'=> $desc_present,
		'empty'             => $desc_empty,
		'text'              => $desc_present ? (string) $desc_text : '',
		'source_key'        => isset( $task['description_key'] ) ? (string) $task['description_key'] : '',
		'complete'          => $desc_present,
		'label'             => __( 'Project description', 'wpcable' ),
		'reason'            => '',
	);
	if ( ! $desc_present ) {
		$description['reason'] = __( 'Not in the saved task-list payload. Codeable workrooms have a project description; this plugin historically stored title only. This is not an empty description, and it is not proof the API omits the field.', 'wpcable' );
	} elseif ( $desc_empty ) {
		$description['reason'] = __( 'The saved import includes an empty description field.', 'wpcable' );
	}

	$comments_present = ! empty( $task['comments_present'] );
	$items            = array();
	if ( ! empty( $task['comments_json'] ) ) {
		$decoded = json_decode( $task['comments_json'], true );
		if ( is_array( $decoded ) ) {
			$items = $decoded;
		}
	}
	$comments_complete = ! empty( $task['comments_complete'] );

	$comments = array(
		'available'          => $comments_present && ! empty( $items ),
		'present_in_payload' => $comments_present,
		'empty'              => $comments_present && empty( $items ),
		'items'              => $items,
		'complete'           => $comments_complete,
		'source'             => isset( $task['comments_source'] ) ? (string) $task['comments_source'] : '',
		'visibility_known'   => false,
		'label'              => __( 'Workroom comments', 'wpcable' ),
		'reason'             => '',
	);
	foreach ( $items as $item ) {
		if ( isset( $item['visibility'] ) && 'unknown' !== $item['visibility'] ) {
			$comments['visibility_known'] = true;
			break;
		}
	}
	if ( ! $comments_present ) {
		$comments['reason'] = __( 'Not in the saved task-list payload. Public and private workroom comments exist in the Codeable app; this plugin did not previously persist them. This is not an empty thread. Pagination and full public/private coverage are unverified for the list endpoint.', 'wpcable' );
	} elseif ( empty( $items ) ) {
		$comments['reason'] = __( 'The saved import includes a comments field that was empty.', 'wpcable' );
	} elseif ( ! $comments_complete ) {
		$comments['reason'] = __( 'Showing comments that were present on the saved payload. This may not be the full public and private thread, and pagination is unverified.', 'wpcable' );
	}

	return array(
		'description' => $description,
		'comments'    => $comments,
		'workroom'    => array(
			'url'     => $task_id ? wpcable_workroom_url( $task_id ) : '',
			'pattern' => 'https://app.codeable.io/tasks/{id}/workroom',
			'label'   => __( 'Open workroom (read-only)', 'wpcable' ),
		),
		'complete'    => $description['available'] && $comments['available'] && $comments_complete,
	);
}
