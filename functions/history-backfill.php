<?php
/**
 * Resumable bulk description + comments backfill (read-only GETs).
 * Uses WP-Cron single events plus the existing hourly hook. Never POST/PUT/PATCH/DELETE.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option key for the persistent backfill cursor. Separate from Refresh queue.
 *
 * @return string
 */
function wpcable_history_backfill_option_key() {
	return 'wpcable_history_backfill';
}

/**
 * Option key for the backfill lock (not the Refresh lock).
 *
 * @return string
 */
function wpcable_history_backfill_lock_key() {
	return 'wpcable_history_backfill_lock';
}

/**
 * Modest batch / backoff limits. Filterable for tests.
 *
 * @return array
 */
function wpcable_history_backfill_limits() {
	$defaults = array(
		'requests_per_tick'     => 8,
		'projects_per_tick'     => 4,
		'tick_delay'            => 20,
		'retry_after_default'   => 30,
		'retry_after_cap'       => 300,
		'backoff_start'         => 10,
		'backoff_cap'           => 120,
		'project_retries'       => 3,
		'max_pages_per_project' => 80,
	);
	$limits = apply_filters( 'wpcable_history_backfill_limits', $defaults );
	return is_array( $limits ) ? array_merge( $defaults, $limits ) : $defaults;
}

/**
 * Empty job document.
 *
 * @return array
 */
function wpcable_history_backfill_blank_job() {
	return array(
		'state'                  => 'idle',
		'account_id'             => '',
		'queued_ids'             => array(),
		'current_id'             => 0,
		'current_mode'           => 'first',
		'current_pages'          => 0,
		'current_retries'        => 0,
		'done_ids'               => array(),
		'denied_ids'             => array(),
		'partial_ids'            => array(),
		'error_ids'              => array(),
		'skipped_complete_ids'   => array(),
		'snapshot_count'         => 0,
		'started_at'             => 0,
		'updated_at'             => 0,
		'last_tick_at'           => 0,
		'requests_this_tick'     => 0,
		'requests_total'         => 0,
		'wait_until'             => 0,
		'backoff_seconds'        => 0,
		'consecutive_429'        => 0,
		'consecutive_5xx'        => 0,
		'last_query_sig'         => '',
		'last_error'             => '',
		'overlap_skipped'        => 0,
		'pause_reason'           => '',
	);
}

/**
 * Load the stored job.
 *
 * @return array
 */
function wpcable_history_backfill_job() {
	$stored = get_option( wpcable_history_backfill_option_key(), array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( wpcable_history_backfill_blank_job(), $stored );
}

/**
 * Persist the job. Never stores tokens, comment bodies, or raw headers.
 *
 * @param array $job Job.
 * @return array
 */
function wpcable_history_backfill_save( $job ) {
	$job                 = array_merge( wpcable_history_backfill_blank_job(), is_array( $job ) ? $job : array() );
	$job['updated_at']   = time();
	$job['queued_ids']   = array_values( array_map( 'intval', (array) $job['queued_ids'] ) );
	$job['done_ids']     = array_values( array_map( 'intval', (array) $job['done_ids'] ) );
	$job['denied_ids']   = array_values( array_map( 'intval', (array) $job['denied_ids'] ) );
	$job['partial_ids']  = array_values( array_map( 'intval', (array) $job['partial_ids'] ) );
	$job['error_ids']    = array_values( array_map( 'intval', (array) $job['error_ids'] ) );
	$job['skipped_complete_ids'] = array_values( array_map( 'intval', (array) $job['skipped_complete_ids'] ) );
	$job['current_id']   = (int) $job['current_id'];
	$job['last_error']   = is_string( $job['last_error'] ) ? substr( $job['last_error'], 0, 300 ) : '';
	update_option( wpcable_history_backfill_option_key(), $job );
	return $job;
}

/**
 * Public, secret-free snapshot for UI / tests.
 *
 * @param array|null $job Job.
 * @return array
 */
function wpcable_history_backfill_public( $job = null ) {
	$job = is_array( $job ) ? array_merge( wpcable_history_backfill_blank_job(), $job ) : wpcable_history_backfill_job();
	$queued = count( $job['queued_ids'] ) + ( $job['current_id'] > 0 ? 1 : 0 );
	$finished_buckets = count( $job['done_ids'] ) + count( $job['denied_ids'] ) + count( $job['partial_ids'] ) + count( $job['error_ids'] ) + count( $job['skipped_complete_ids'] );
	return array(
		'state'                => $job['state'],
		'account_id'           => (string) $job['account_id'],
		'current_id'           => (int) $job['current_id'],
		'current_mode'         => (string) $job['current_mode'],
		'current_pages'        => (int) $job['current_pages'],
		'queued_count'         => $queued,
		'done_count'           => count( $job['done_ids'] ),
		'denied_count'         => count( $job['denied_ids'] ),
		'partial_count'        => count( $job['partial_ids'] ),
		'error_count'          => count( $job['error_ids'] ),
		'skipped_complete_count' => count( $job['skipped_complete_ids'] ),
		'snapshot_count'       => (int) $job['snapshot_count'],
		'processed_count'      => $finished_buckets,
		'requests_total'       => (int) $job['requests_total'],
		'requests_this_tick'   => (int) $job['requests_this_tick'],
		'wait_until'           => (int) $job['wait_until'],
		'last_tick_at'         => (int) $job['last_tick_at'],
		'last_error'           => (string) $job['last_error'],
		'pause_reason'         => (string) $job['pause_reason'],
		'overlap_skipped'      => (int) $job['overlap_skipped'],
		'honest_complete'      => ( 'finished' === $job['state'] && empty( $job['partial_ids'] ) && empty( $job['error_ids'] ) && empty( $job['denied_ids'] ) && empty( $job['queued_ids'] ) && empty( $job['current_id'] ) ),
		'cron'                 => wpcable_history_backfill_cron_note(),
	);
}

/**
 * WP-Cron dependence copy. Default WP-Cron is visitor-triggered.
 *
 * @return string
 */
function wpcable_history_backfill_cron_note() {
	$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	if ( $disabled ) {
		return __( 'WP-Cron is disabled (DISABLE_WP_CRON). The browser does not need to stay open, but a system cron (or other scheduler) must request wp-cron.php or this backfill will not continue on its own. Use Run one batch now to process a bounded tick immediately.', 'wpcable' );
	}
	return __( 'This job uses WordPress WP-Cron (a scheduled single event after each tick, plus the existing hourly hook). Default WP-Cron only runs when someone visits the site. The browser does not need to stay on this page, but the site needs traffic or a real cron hitting wp-cron.php. Run one batch now processes one bounded tick immediately.', 'wpcable' );
}

/**
 * Local imported task ids, oldest first.
 *
 * @return int[]
 */
function wpcable_imported_task_ids() {
	global $wpdb;
	$table = $wpdb->prefix . 'codeable_tasks';
	$ids   = $wpdb->get_col( "SELECT task_id FROM {$table} ORDER BY task_id ASC" );
	if ( ! is_array( $ids ) ) {
		return array();
	}
	$out = array();
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$out[] = $id;
		}
	}
	return $out;
}

/**
 * Acquire the backfill lock.
 *
 * @param int $ttl Seconds.
 * @return bool
 */
function wpcable_history_backfill_lock_acquire( $ttl = 90 ) {
	$now  = time();
	$lock = get_option( wpcable_history_backfill_lock_key(), false );
	if ( is_array( $lock ) && ! empty( $lock['until'] ) && (int) $lock['until'] > $now ) {
		return false;
	}
	update_option(
		wpcable_history_backfill_lock_key(),
		array(
			'until'   => $now + max( 5, (int) $ttl ),
			'started' => $now,
		)
	);
	return true;
}

/**
 * Release the backfill lock.
 *
 * @return void
 */
function wpcable_history_backfill_lock_release() {
	delete_option( wpcable_history_backfill_lock_key() );
}

/**
 * Schedule the next WP-Cron tick.
 *
 * @param int $delay Seconds.
 * @return void
 */
function wpcable_history_backfill_schedule( $delay = 20 ) {
	$delay = max( 5, (int) $delay );
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'wpcable_history_backfill_tick' );
	}
	if ( function_exists( 'wp_schedule_single_event' ) ) {
		wp_schedule_single_event( time() + $delay, 'wpcable_history_backfill_tick' );
	}
	if ( function_exists( 'spawn_cron' ) ) {
		spawn_cron();
	}
}

/**
 * Clear scheduled backfill events.
 *
 * @return void
 */
function wpcable_history_backfill_unschedule() {
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'wpcable_history_backfill_tick' );
	}
}

/**
 * Whether the job is in an active (should tick) state.
 *
 * @param array $job Job.
 * @return bool
 */
function wpcable_history_backfill_is_active( $job ) {
	return in_array( $job['state'], array( 'running', 'waiting_retry' ), true );
}

/**
 * Start a new snapshot of imported projects. Does not call Codeable until a tick.
 *
 * @param array $args {task_ids:int[]} Optional subset (tests). Default: every imported id.
 * @return array {ok, message, job, public}
 */
function wpcable_history_backfill_start( $args = array() ) {
	$existing = wpcable_history_backfill_job();
	if ( wpcable_history_backfill_is_active( $existing ) ) {
		return array(
			'ok'      => false,
			'message' => __( 'A comments backfill is already running. Pause it first, or wait for it to finish.', 'wpcable' ),
			'job'     => $existing,
			'public'  => wpcable_history_backfill_public( $existing ),
		);
	}

	if ( ! wpcable_sync_allowed() ) {
		return array(
			'ok'      => false,
			'message' => __( 'Comments backfill is off while disconnected. Saved data stays visible.', 'wpcable' ),
			'job'     => $existing,
			'public'  => wpcable_history_backfill_public( $existing ),
		);
	}

	$account_id = wpcable_current_account_id();
	if ( '' === $account_id ) {
		return array(
			'ok'      => false,
			'message' => __( 'No connected Codeable account id is stored. Sign in, then start backfill.', 'wpcable' ),
			'job'     => $existing,
			'public'  => wpcable_history_backfill_public( $existing ),
		);
	}

	if ( ! empty( $args['task_ids'] ) && is_array( $args['task_ids'] ) ) {
		$ids = array();
		foreach ( $args['task_ids'] as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
	} else {
		$ids = wpcable_imported_task_ids();
	}
	if ( empty( $ids ) ) {
		return array(
			'ok'      => false,
			'message' => __( 'No imported projects are stored locally. Run Refresh first, then start backfill.', 'wpcable' ),
			'job'     => $existing,
			'public'  => wpcable_history_backfill_public( $existing ),
		);
	}

	$repo    = new wpcable_tasks();
	$queued  = array();
	$skipped = array();
	foreach ( $ids as $id ) {
		$row = $repo->get_task( $id );
		if ( ! $row ) {
			continue;
		}
		$stamp = isset( $row['history_account_id'] ) ? (string) $row['history_account_id'] : '';
		if ( ! empty( $row['comments_complete'] ) && ( '' === $stamp || $stamp === $account_id ) ) {
			$skipped[] = $id;
			continue;
		}
		$queued[] = $id;
	}

	$job = wpcable_history_backfill_blank_job();
	$job['account_id']           = $account_id;
	$job['queued_ids']           = $queued;
	$job['skipped_complete_ids'] = $skipped;
	$job['snapshot_count']       = count( $ids );
	$job['started_at']           = time();
	$job['state']                = empty( $queued ) ? 'finished' : 'running';

	if ( empty( $queued ) ) {
		$job = wpcable_history_backfill_save( $job );
		wpcable_history_backfill_unschedule();
		return array(
			'ok'      => true,
			'message' => __( 'Every imported project already has an evidence-backed complete comments cache for this account. Nothing to fetch.', 'wpcable' ),
			'job'     => $job,
			'public'  => wpcable_history_backfill_public( $job ),
		);
	}

	$job = wpcable_history_backfill_take_next( $job, $repo, $account_id );
	$job = wpcable_history_backfill_save( $job );
	wpcable_history_backfill_schedule( 5 );

	return array(
		'ok'      => true,
		'message' => __( 'Comments backfill started. WP-Cron will continue it in bounded ticks. The browser does not need to stay open if WP-Cron runs.', 'wpcable' ),
		'job'     => $job,
		'public'  => wpcable_history_backfill_public( $job ),
	);
}

/**
 * Pause an active job. Cursor is kept.
 *
 * @param string $reason Pause reason key.
 * @return array
 */
function wpcable_history_backfill_pause( $reason = 'paused' ) {
	$job = wpcable_history_backfill_job();
	if ( 'idle' === $job['state'] || 'finished' === $job['state'] ) {
		return array(
			'ok'      => true,
			'message' => __( 'No running comments backfill to pause.', 'wpcable' ),
			'job'     => $job,
			'public'  => wpcable_history_backfill_public( $job ),
		);
	}
	$job['state']        = ( 'auth' === $reason || 'disconnected' === $reason ) ? 'auth_paused' : 'paused';
	$job['pause_reason'] = sanitize_key( $reason );
	$job['wait_until']   = 0;
	$job                 = wpcable_history_backfill_save( $job );
	wpcable_history_backfill_unschedule();
	return array(
		'ok'      => true,
		'message' => __( 'Comments backfill paused. Saved caches were not overwritten. Resume continues from the stored cursor.', 'wpcable' ),
		'job'     => $job,
		'public'  => wpcable_history_backfill_public( $job ),
	);
}

/**
 * Resume a paused / auth-paused / waiting job.
 *
 * @return array
 */
function wpcable_history_backfill_resume() {
	$job = wpcable_history_backfill_job();
	if ( ! in_array( $job['state'], array( 'paused', 'auth_paused', 'waiting_retry' ), true ) ) {
		return array(
			'ok'      => false,
			'message' => __( 'There is no paused comments backfill to resume.', 'wpcable' ),
			'job'     => $job,
			'public'  => wpcable_history_backfill_public( $job ),
		);
	}
	if ( ! wpcable_sync_allowed() ) {
		return array(
			'ok'      => false,
			'message' => __( 'Sign in again before resuming comments backfill. Saved data stays visible.', 'wpcable' ),
			'job'     => $job,
			'public'  => wpcable_history_backfill_public( $job ),
		);
	}
	$current = wpcable_current_account_id();
	if ( $job['account_id'] && $current && $job['account_id'] !== $current ) {
		return array(
			'ok'      => false,
			'message' => __( 'Connected Codeable account does not match the backfill cursor. Start a new backfill for this account.', 'wpcable' ),
			'job'     => $job,
			'public'  => wpcable_history_backfill_public( $job ),
		);
	}
	$job['state']        = 'running';
	$job['pause_reason'] = '';
	$job['wait_until']   = 0;
	$job                 = wpcable_history_backfill_save( $job );
	wpcable_history_backfill_schedule( 5 );
	return array(
		'ok'      => true,
		'message' => __( 'Comments backfill resumed from the stored cursor.', 'wpcable' ),
		'job'     => $job,
		'public'  => wpcable_history_backfill_public( $job ),
	);
}

/**
 * Pick current_id from the queue when empty.
 *
 * @param array         $job        Job.
 * @param wpcable_tasks $repo       Tasks repo.
 * @param string        $account_id Connected account.
 * @return array
 */
function wpcable_history_backfill_take_next( $job, $repo, $account_id ) {
	while ( empty( $job['current_id'] ) && ! empty( $job['queued_ids'] ) ) {
		$id = (int) array_shift( $job['queued_ids'] );
		if ( $id <= 0 ) {
			continue;
		}
		$row = $repo->get_task( $id );
		if ( ! $row ) {
			$job['error_ids'][] = $id;
			continue;
		}
		$stamp = isset( $row['history_account_id'] ) ? (string) $row['history_account_id'] : '';
		if ( ! empty( $row['comments_complete'] ) && ( '' === $stamp || $stamp === $account_id ) ) {
			$job['skipped_complete_ids'][] = $id;
			continue;
		}
		$meta = wpcable_history_meta( isset( $row['history_meta_json'] ) ? $row['history_meta_json'] : '' );
		$more = ! empty( $meta['comments']['next_replay']['ok'] )
			&& ! empty( $row['comments_json'] )
			&& ( '' === $stamp || $stamp === $account_id );
		$job['current_id']     = $id;
		$job['current_mode']   = $more ? 'more' : 'first';
		$job['current_pages']  = 0;
		$job['current_retries'] = 0;
		$job['last_query_sig'] = isset( $meta['comments']['last_query_sig'] ) ? (string) $meta['comments']['last_query_sig'] : '';
	}

	if ( empty( $job['current_id'] ) && empty( $job['queued_ids'] ) ) {
		$job['state'] = 'finished';
		$job['wait_until'] = 0;
	}

	return $job;
}

/**
 * Bounded tick. Safe to call from WP-Cron or Run one batch now.
 *
 * @param array $args {force:bool} Admin force runs a paused/waiting job once.
 * @return array
 */
function wpcable_history_backfill_tick( $args = array() ) {
	$force  = ! empty( $args['force'] );
	$limits = wpcable_history_backfill_limits();
	$job    = wpcable_history_backfill_job();
	$out    = array(
		'ok'       => true,
		'skipped'  => false,
		'reason'   => '',
		'requests' => 0,
		'job'      => $job,
		'public'   => wpcable_history_backfill_public( $job ),
	);
	$restore_pause = false;

	if ( $force && 'paused' === $job['state'] ) {
		$job['state']      = 'running';
		$job['wait_until'] = 0;
		$restore_pause     = true;
	} elseif ( $force && 'waiting_retry' === $job['state'] ) {
		$job['state']      = 'running';
		$job['wait_until'] = 0;
	}

	if ( ! wpcable_history_backfill_is_active( $job ) ) {
		$out['skipped'] = true;
		$out['reason']  = 'inactive';
		return $out;
	}

	if ( ! $force && 'waiting_retry' === $job['state'] && (int) $job['wait_until'] > time() ) {
		wpcable_history_backfill_schedule( max( 5, (int) $job['wait_until'] - time() ) );
		$out['skipped'] = true;
		$out['reason']  = 'waiting_retry';
		$out['public']  = wpcable_history_backfill_public( $job );
		return $out;
	}

	if ( ! wpcable_sync_allowed() ) {
		$paused = wpcable_history_backfill_pause( 'disconnected' );
		$out['ok']      = false;
		$out['reason']  = 'disconnected';
		$out['job']     = $paused['job'];
		$out['public']  = $paused['public'];
		return $out;
	}

	$account_id = wpcable_current_account_id();
	if ( $job['account_id'] && $account_id && $job['account_id'] !== $account_id ) {
		$paused = wpcable_history_backfill_pause( 'account_mismatch' );
		$out['ok']      = false;
		$out['reason']  = 'account_mismatch';
		$out['job']     = $paused['job'];
		$out['public']  = $paused['public'];
		return $out;
	}

	$refresh_queue = function_exists( 'wpcable_sync_queue' ) ? wpcable_sync_queue() : array();
	if ( ! empty( $refresh_queue ) || ( function_exists( 'wpcable_sync_lock_held' ) && wpcable_sync_lock_held() ) ) {
		$job['overlap_skipped'] = (int) $job['overlap_skipped'] + 1;
		$job                    = wpcable_history_backfill_save( $job );
		wpcable_history_backfill_schedule( (int) $limits['tick_delay'] );
		$out['skipped'] = true;
		$out['reason']  = 'refresh_overlap';
		$out['job']     = $job;
		$out['public']  = wpcable_history_backfill_public( $job );
		return $out;
	}

	if ( ! wpcable_history_backfill_lock_acquire( 90 ) ) {
		$job['overlap_skipped'] = (int) $job['overlap_skipped'] + 1;
		$job                    = wpcable_history_backfill_save( $job );
		$out['skipped']         = true;
		$out['reason']          = 'lock';
		$out['job']             = $job;
		$out['public']          = wpcable_history_backfill_public( $job );
		return $out;
	}

	$repo     = new wpcable_tasks();
	$budget   = (int) $limits['requests_per_tick'];
	$projects = 0;
	$max_proj = (int) $limits['projects_per_tick'];
	$used     = 0;
	$job['requests_this_tick'] = 0;
	$job['last_tick_at']       = time();
	if ( 'waiting_retry' === $job['state'] ) {
		$job['state']      = 'running';
		$job['wait_until'] = 0;
	}

	try {
		while ( $budget > 0 && $projects < $max_proj ) {
			if ( empty( $job['current_id'] ) ) {
				$job = wpcable_history_backfill_take_next( $job, $repo, $account_id );
			}
			if ( empty( $job['current_id'] ) ) {
				break;
			}

			$task_id = (int) $job['current_id'];
			$more    = ( 'more' === $job['current_mode'] );
			if ( $more && (int) $job['current_pages'] >= (int) $limits['max_pages_per_project'] ) {
				$job['partial_ids'][] = $task_id;
				$job['last_error']    = 'page_cap';
				$job                  = wpcable_history_backfill_clear_current( $job );
				$projects++;
				continue;
			}

			$result = wpcable_load_task_history_run(
				$task_id,
				array(
					'more' => $more,
				)
			);
			$calls = isset( $result['calls'] ) && is_array( $result['calls'] ) ? count( $result['calls'] ) : 1;
			$calls = max( 0, $calls );
			$budget -= max( 1, $calls );
			$used   += $calls;
			$job['requests_this_tick'] = (int) $job['requests_this_tick'] + $calls;
			$job['requests_total']     = (int) $job['requests_total'] + $calls;

			$state = isset( $result['state'] ) ? (string) $result['state'] : '';
			$http  = 0;
			if ( isset( $result['comments']['http'] ) ) {
				$http = (int) $result['comments']['http'];
			}
			if ( ! $http && isset( $result['description']['http'] ) ) {
				$http = (int) $result['description']['http'];
			}

			if ( 'AUTH_EXPIRED' === $state || 401 === $http ) {
				$job['last_error']   = '401';
				$job['pause_reason'] = 'auth';
				$job['state']        = 'auth_paused';
				$job['wait_until']   = 0;
				$job                 = wpcable_history_backfill_save( $job );
				wpcable_history_backfill_unschedule();
				$out['ok']     = false;
				$out['reason'] = '401';
				$out['job']    = $job;
				$out['public'] = wpcable_history_backfill_public( $job );
				$out['requests'] = $used;
				return $out;
			}

			if ( 'RATE_LIMITED' === $state || 429 === $http ) {
				$retry = 0;
				if ( isset( $result['retry_after'] ) ) {
					$retry = (int) $result['retry_after'];
				}
				$job['consecutive_429'] = (int) $job['consecutive_429'] + 1;
				$wait                   = wpcable_history_backfill_wait_seconds( $retry, (int) $job['consecutive_429'], $limits );
				$job['state']           = 'waiting_retry';
				$job['wait_until']      = time() + $wait;
				$job['backoff_seconds'] = $wait;
				$job['last_error']      = '429';
				$job                    = wpcable_history_backfill_save( $job );
				wpcable_history_backfill_schedule( $wait );
				$out['reason']   = '429';
				$out['job']      = $job;
				$out['public']   = wpcable_history_backfill_public( $job );
				$out['requests'] = $used;
				return $out;
			}

			$job['consecutive_429'] = 0;

			if ( 'FORBIDDEN' === $state ) {
				$job['denied_ids'][] = $task_id;
				$job['last_error']   = '403';
				$job                 = wpcable_history_backfill_clear_current( $job );
				$projects++;
				continue;
			}

			if ( 'loop_guard' === $state ) {
				$job['partial_ids'][] = $task_id;
				$job['last_error']    = 'repeated_page';
				$job                  = wpcable_history_backfill_clear_current( $job );
				$projects++;
				continue;
			}

			if ( 'loaded' === $state && ! empty( $result['ok'] ) ) {
				$job['done_ids'][]        = $task_id;
				$job['consecutive_5xx']   = 0;
				$job['current_retries']   = 0;
				$job                      = wpcable_history_backfill_clear_current( $job );
				$projects++;
				continue;
			}

			if ( 'partial' === $state && ! empty( $result['ok'] ) ) {
				$job['consecutive_5xx'] = 0;
				$job['current_retries'] = 0;
				$job['current_pages']   = (int) $job['current_pages'] + 1;
				$has_next = ! empty( $result['comments']['next_replay']['ok'] )
					|| wpcable_history_backfill_task_has_next( $repo, $task_id );
				if ( $has_next ) {
					$just = isset( $result['comments']['query_sig'] ) ? (string) $result['comments']['query_sig'] : '';
					$next = wpcable_history_backfill_task_query_sig( $repo, $task_id );
					if ( $just && $next && $just === $next ) {
						$job['partial_ids'][] = $task_id;
						$job['last_error']    = 'repeated_page';
						$job                  = wpcable_history_backfill_clear_current( $job );
						$projects++;
						continue;
					}
					$job['last_query_sig'] = $just;
					$job['current_mode']   = 'more';
					continue;
				}
				$job['partial_ids'][] = $task_id;
				$job['last_error']    = isset( $result['comments']['reason'] ) ? 'partial' : 'partial';
				$job                  = wpcable_history_backfill_clear_current( $job );
				$projects++;
				continue;
			}

			if ( $http >= 500 || 0 === $http || 'error' === $state ) {
				$job['consecutive_5xx'] = (int) $job['consecutive_5xx'] + 1;
				$job['current_retries'] = (int) $job['current_retries'] + 1;
				if ( (int) $job['current_retries'] >= (int) $limits['project_retries'] ) {
					$job['error_ids'][] = $task_id;
					$job['last_error']  = $http ? ( 'http_' . $http ) : 'fetch_failed';
					$job                = wpcable_history_backfill_clear_current( $job );
					$projects++;
					continue;
				}
				$wait                   = wpcable_history_backfill_server_backoff( (int) $job['consecutive_5xx'], $limits );
				$job['state']           = 'waiting_retry';
				$job['wait_until']      = time() + $wait;
				$job['backoff_seconds'] = $wait;
				$job['last_error']      = $http ? ( 'http_' . $http ) : 'fetch_failed';
				$job                    = wpcable_history_backfill_save( $job );
				wpcable_history_backfill_schedule( $wait );
				$out['reason']   = 'server_error';
				$out['job']      = $job;
				$out['public']   = wpcable_history_backfill_public( $job );
				$out['requests'] = $used;
				return $out;
			}

			$job['partial_ids'][] = $task_id;
			$job['last_error']    = $state ? $state : 'unknown';
			$job                  = wpcable_history_backfill_clear_current( $job );
			$projects++;
		}

		$job = wpcable_history_backfill_take_next( $job, $repo, $account_id );
		if ( 'finished' === $job['state'] ) {
			$restore_pause = false;
			$job           = wpcable_history_backfill_save( $job );
			wpcable_history_backfill_unschedule();
		} elseif ( $restore_pause ) {
			$job['state'] = 'paused';
			$job          = wpcable_history_backfill_save( $job );
			wpcable_history_backfill_unschedule();
		} else {
			$job['state'] = 'running';
			$job          = wpcable_history_backfill_save( $job );
			wpcable_history_backfill_schedule( (int) $limits['tick_delay'] );
		}
	} finally {
		wpcable_history_backfill_lock_release();
	}

	$out['requests'] = $used;
	$out['job']      = $job;
	$out['public']   = wpcable_history_backfill_public( $job );
	$out['reason']   = ( 'finished' === $job['state'] ) ? 'finished' : 'tick';
	return $out;
}

/**
 * Reset current cursor fields after leaving a project.
 *
 * @param array $job Job.
 * @return array
 */
function wpcable_history_backfill_clear_current( $job ) {
	$job['current_id']      = 0;
	$job['current_mode']    = 'first';
	$job['current_pages']   = 0;
	$job['current_retries'] = 0;
	$job['last_query_sig']  = '';
	return $job;
}

/**
 * Whether stored comments meta still has a verified next replay.
 *
 * @param wpcable_tasks $repo    Repo.
 * @param int           $task_id Task id.
 * @return bool
 */
function wpcable_history_backfill_task_has_next( $repo, $task_id ) {
	$row = $repo->get_task( $task_id );
	if ( ! $row ) {
		return false;
	}
	$meta = wpcable_history_meta( isset( $row['history_meta_json'] ) ? $row['history_meta_json'] : '' );
	return ! empty( $meta['comments']['next_replay']['ok'] );
}

/**
 * Stored next-query signature for loop detection.
 *
 * @param wpcable_tasks $repo    Repo.
 * @param int           $task_id Task id.
 * @return string
 */
function wpcable_history_backfill_task_query_sig( $repo, $task_id ) {
	$row = $repo->get_task( $task_id );
	if ( ! $row ) {
		return '';
	}
	$meta = wpcable_history_meta( isset( $row['history_meta_json'] ) ? $row['history_meta_json'] : '' );
	if ( empty( $meta['comments']['next_replay']['ok'] ) ) {
		return '';
	}
	$path  = isset( $meta['comments']['next_replay']['path'] ) ? $meta['comments']['next_replay']['path'] : '';
	$query = isset( $meta['comments']['next_replay']['query'] ) && is_array( $meta['comments']['next_replay']['query'] )
		? $meta['comments']['next_replay']['query']
		: array();
	return wpcable_comments_query_sig( $path, $query );
}

/**
 * 429 wait from Retry-After or exponential backoff.
 *
 * @param int   $retry_after Header seconds.
 * @param int   $consecutive Consecutive 429s.
 * @param array $limits      Limits.
 * @return int
 */
function wpcable_history_backfill_wait_seconds( $retry_after, $consecutive, $limits ) {
	$wait = (int) $retry_after;
	if ( $wait <= 0 ) {
		$wait = (int) $limits['retry_after_default'] * max( 1, (int) $consecutive );
	}
	return max( 5, min( (int) $limits['retry_after_cap'], $wait ) );
}

/**
 * 5xx backoff.
 *
 * @param int   $consecutive Consecutive 5xx.
 * @param array $limits      Limits.
 * @return int
 */
function wpcable_history_backfill_server_backoff( $consecutive, $limits ) {
	$start = (int) $limits['backoff_start'];
	$wait  = $start * (int) pow( 2, max( 0, (int) $consecutive - 1 ) );
	return max( 5, min( (int) $limits['backoff_cap'], $wait ) );
}

/**
 * WP-Cron callback.
 *
 * @return void
 */
function wpcable_history_backfill_cron() {
	wpcable_history_backfill_tick();
}

/**
 * Settings card (outside the options.php form).
 *
 * @return void
 */
function wpcable_history_backfill_settings_card() {
	$public = wpcable_history_backfill_public();
	$job    = wpcable_history_backfill_job();
	$active = wpcable_history_backfill_is_active( $job );
	$paused = in_array( $job['state'], array( 'paused', 'auth_paused' ), true );

	$start_url  = wp_nonce_url( add_query_arg( 'action', 'history_backfill_start' ), 'history_backfill_start' );
	$pause_url  = wp_nonce_url( add_query_arg( 'action', 'history_backfill_pause' ), 'history_backfill_pause' );
	$resume_url = wp_nonce_url( add_query_arg( 'action', 'history_backfill_resume' ), 'history_backfill_resume' );
	$tick_url   = wp_nonce_url( add_query_arg( 'action', 'history_backfill_tick' ), 'history_backfill_tick' );
	?>
	<section class="wpcable-settings-card wpcable-backfill-card">
		<h2><?php esc_html_e( 'Comments backfill (read-only)', 'wpcable' ); ?></h2>
		<p>
			<?php esc_html_e( 'Loads each imported project’s description and every accessible comments page into the local cache. Uses the same documented GETs as Load from Codeable. Nothing is posted to Codeable. Pagination stays evidence-backed: a 50-item page is not treated as complete.', 'wpcable' ); ?>
		</p>
		<p class="description">
			<?php echo esc_html( $public['cron'] ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Author names and public/private visibility stay empty/unknown until a live keys-only shape shows those fields. This plugin does not invent them.', 'wpcable' ); ?>
		</p>
		<ul class="wpcable-backfill-stats">
			<li><?php echo esc_html( sprintf( __( 'State: %s', 'wpcable' ), $public['state'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Snapshot: %1$d imported · queued %2$d · complete %3$d · denied 403 %4$d · partial %5$d · errors %6$d · already complete %7$d', 'wpcable' ), (int) $public['snapshot_count'], (int) $public['queued_count'], (int) $public['done_count'], (int) $public['denied_count'], (int) $public['partial_count'], (int) $public['error_count'], (int) $public['skipped_complete_count'] ) ); ?></li>
			<?php if ( $public['current_id'] ) : ?>
				<li><?php echo esc_html( sprintf( __( 'Current project %1$d (%2$s, page %3$d)', 'wpcable' ), (int) $public['current_id'], $public['current_mode'], (int) $public['current_pages'] ) ); ?></li>
			<?php endif; ?>
			<li><?php echo esc_html( sprintf( __( 'Requests this job: %d', 'wpcable' ), (int) $public['requests_total'] ) ); ?></li>
			<?php if ( $public['wait_until'] > time() ) : ?>
				<li><?php echo esc_html( sprintf( __( 'Waiting until %s (rate limit or server backoff)', 'wpcable' ), wpcable_date( $public['wait_until'] ) ) ); ?></li>
			<?php endif; ?>
			<?php if ( $public['last_error'] ) : ?>
				<li><?php echo esc_html( sprintf( __( 'Last status: %s', 'wpcable' ), $public['last_error'] ) ); ?></li>
			<?php endif; ?>
			<?php if ( 'finished' === $public['state'] ) : ?>
				<li>
					<?php
					echo esc_html(
						$public['honest_complete']
							? __( 'Finished: every queued project reached an evidence-backed complete or empty comments cache (403s listed separately as denied).', 'wpcable' )
							: __( 'Finished: some projects stayed partial or errored. Those are listed separately — not silently marked complete.', 'wpcable' )
					);
					?>
				</li>
			<?php endif; ?>
		</ul>
		<p class="wpcable-backfill-actions">
			<?php if ( wpcable_sync_allowed() && ! $active && ! $paused ) : ?>
				<a href="<?php echo esc_url( $start_url ); ?>" class="button button-primary"><?php esc_html_e( 'Start comments backfill', 'wpcable' ); ?></a>
			<?php endif; ?>
			<?php if ( $active ) : ?>
				<a href="<?php echo esc_url( $pause_url ); ?>" class="button"><?php esc_html_e( 'Pause', 'wpcable' ); ?></a>
				<a href="<?php echo esc_url( $tick_url ); ?>" class="button"><?php esc_html_e( 'Run one batch now', 'wpcable' ); ?></a>
			<?php endif; ?>
			<?php if ( $paused ) : ?>
				<a href="<?php echo esc_url( $resume_url ); ?>" class="button button-primary"><?php esc_html_e( 'Resume', 'wpcable' ); ?></a>
				<a href="<?php echo esc_url( $tick_url ); ?>" class="button"><?php esc_html_e( 'Run one batch now', 'wpcable' ); ?></a>
			<?php endif; ?>
			<?php if ( ! wpcable_sync_allowed() ) : ?>
				<span class="wpcable-sync-disabled"><?php esc_html_e( 'Sign in to start or resume backfill. Saved caches stay visible while disconnected.', 'wpcable' ); ?></span>
			<?php endif; ?>
		</p>
	</section>
	<?php
}
