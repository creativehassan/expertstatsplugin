<?php
/**
 * Per-source import health. Distinguishes attempts from successes.
 * Never fabricates a last-successful timestamp.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Tracks profile / tasks / transactions import outcomes.
 */
class wpcable_import_health {

	const OPTION = 'wpcable_import_health';

	/**
	 * Known sources and user-facing labels.
	 *
	 * @return array
	 */
	public static function sources() {
		return array(
			'profile'      => __( 'Expert profile (users/me)', 'wpcable' ),
			'tasks'        => __( 'Imported tasks', 'wpcable' ),
			'transactions' => __( 'Money transactions', 'wpcable' ),
		);
	}

	/**
	 * Return stored health, filling defaults without inventing success times.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$out = array();
		foreach ( array_keys( self::sources() ) as $key ) {
			$row = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
			$out[ $key ] = self::normalize_row( $row );
		}

		return $out;
	}

	/**
	 * Normalize a source row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function normalize_row( $row ) {
		$allowed = array( 'never', 'cached', 'in_progress', 'attempted', 'success', 'failed', 'missing', 'empty' );
		$status  = isset( $row['status'] ) ? $row['status'] : 'never';
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'never';
		}

		$attempted = isset( $row['attempted_at'] ) ? (int) $row['attempted_at'] : 0;
		$succeeded = isset( $row['succeeded_at'] ) ? (int) $row['succeeded_at'] : 0;

		// Only keep a success time when status is actually success.
		if ( 'success' !== $status ) {
			// Keep historical succeeded_at only if it is a real past success, never invent one.
			$succeeded = $succeeded > 0 ? $succeeded : 0;
		}

		return array(
			'status'       => $status,
			'attempted_at' => $attempted > 0 ? $attempted : 0,
			'succeeded_at' => $succeeded > 0 ? $succeeded : 0,
			'message'      => isset( $row['message'] ) ? (string) $row['message'] : '',
			'rows'         => isset( $row['rows'] ) ? (int) $row['rows'] : 0,
		);
	}

	/**
	 * Record an attempt/result for one source.
	 *
	 * @param string $source  profile|tasks|transactions.
	 * @param string $status  success|failed|missing|empty|attempted.
	 * @param string $message Optional note.
	 * @param int    $rows    Optional row count written/seen.
	 * @return void
	 */
	public static function record( $source, $status, $message = '', $rows = 0 ) {
		$health = self::get();
		if ( ! isset( $health[ $source ] ) ) {
			return;
		}

		$now = time();
		$row = $health[ $source ];
		$row['status']       = $status;
		$row['attempted_at'] = $now;
		$row['message']      = $message;
		$row['rows']         = (int) $rows;

		if ( 'success' === $status ) {
			$row['succeeded_at'] = $now;
		}

		$health[ $source ] = self::normalize_row( $row );
		update_option( self::OPTION, $health );
	}

	/**
	 * Status slug for CSS.
	 *
	 * @param array $row Health row.
	 * @return string
	 */
	public static function status_of( $row ) {
		$row = self::normalize_row( $row );
		return $row['status'];
	}

	/**
	 * User-facing status label.
	 *
	 * @param array $row Health row.
	 * @return string
	 */
	public static function status_label( $row ) {
		$row    = self::normalize_row( $row );
		$status = $row['status'];

		$labels = array(
			'never'       => __( 'No recorded attempt', 'wpcable' ),
			'cached'      => __( 'Cached locally — no recorded attempt', 'wpcable' ),
			'in_progress' => __( 'Refresh in progress', 'wpcable' ),
			'attempted'   => __( 'Attempted — result unknown', 'wpcable' ),
			'success'     => __( 'Imported', 'wpcable' ),
			'failed'      => __( 'Failed', 'wpcable' ),
			'missing'     => __( 'Unavailable', 'wpcable' ),
			'empty'       => __( 'Reached API, no rows', 'wpcable' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		if ( $row['message'] ) {
			$label .= ' — ' . $row['message'];
		}

		return $label;
	}

	/**
	 * Success timestamp label, or empty when we have no evidence of success.
	 *
	 * @param array $row Health row.
	 * @return string
	 */
	public static function success_time_label( $row ) {
		$row = self::normalize_row( $row );
		if ( 'success' !== $row['status'] || $row['succeeded_at'] <= 0 ) {
			return '';
		}

		$formatted = wpcable_date( $row['succeeded_at'] );
		if ( ! $formatted ) {
			return '';
		}

		return sprintf(
			/* translators: %s: datetime */
			__( 'Last successful import: %s', 'wpcable' ),
			$formatted
		);
	}

	/**
	 * Whether a source currently has a successful import on record.
	 *
	 * @param string $source Source key.
	 * @return bool
	 */
	public static function source_ok( $source ) {
		$health = self::get();
		if ( ! isset( $health[ $source ] ) ) {
			return false;
		}

		return 'success' === $health[ $source ]['status'];
	}

	/**
	 * Display snapshot. Never invents succeeded_at from last_fetch, MAX(last_sync),
	 * or MAX(last_activity). Cached rows without an instrumented attempt stay
	 * "cached" / "no recorded attempt", not "Imported".
	 *
	 * @param int   $task_count         Local task rows.
	 * @param int   $transaction_count  Local transaction rows.
	 * @param int   $profile_present    1 if profile option is an array with an id.
	 * @param array $queue              Optional pending API queue.
	 * @return array
	 */
	public static function snapshot_from_counts( $task_count, $transaction_count, $profile_present, $queue = null ) {
		$health = self::get();
		if ( null === $queue ) {
			$queue = get_option( 'wpcable_api_queue' );
		}
		$queue           = is_array( $queue ) ? $queue : array();
		$pending_sources = self::sources_in_queue( $queue );

		foreach ( array_keys( self::sources() ) as $key ) {
			$row = $health[ $key ];

			if ( in_array( $key, $pending_sources, true ) ) {
				$step          = isset( $queue[0] ) && is_array( $queue[0] ) ? $queue[0] : array();
				$label         = isset( $step['label'] ) ? $step['label'] : '';
				$page          = isset( $step['page'] ) ? (int) $step['page'] : 0;
				$row['status'] = 'in_progress';
				$row['message'] = $label
					? sprintf(
						/* translators: 1: step label, 2: optional page suffix */
						__( 'Refresh in progress (%1$s%2$s). Counts may change until it finishes.', 'wpcable' ),
						$label,
						$page ? ', page ' . $page : ''
					)
					: __( 'Refresh in progress. Counts may change until it finishes.', 'wpcable' );
				$health[ $key ] = self::normalize_row( $row );
				continue;
			}

			$instrumented = ( $row['attempted_at'] > 0 || $row['succeeded_at'] > 0 || 'never' !== $row['status'] );
			if ( $instrumented ) {
				continue;
			}

			if ( 'profile' === $key ) {
				if ( $profile_present ) {
					$row['status']  = 'cached';
					$row['message'] = __( 'Cached locally — no recorded attempt. This is not an instrumented import success.', 'wpcable' );
				} else {
					$row['status']  = 'never';
					$row['message'] = __( 'No recorded attempt and no cached profile.', 'wpcable' );
				}
			} elseif ( 'tasks' === $key ) {
				if ( $task_count > 0 ) {
					$row['status']  = 'cached';
					$row['rows']    = (int) $task_count;
					$row['message'] = sprintf(
						/* translators: %d: row count */
						__( '%d local rows — no recorded attempt. Cached imports are not the same as an instrumented Refresh.', 'wpcable' ),
						(int) $task_count
					);
				} else {
					$row['status']  = 'never';
					$row['message'] = __( 'No recorded attempt and no local task rows.', 'wpcable' );
				}
			} elseif ( 'transactions' === $key ) {
				if ( $transaction_count > 0 ) {
					$row['status']  = 'cached';
					$row['rows']    = (int) $transaction_count;
					$row['message'] = sprintf(
						/* translators: %d: row count */
						__( '%d local rows — no recorded attempt.', 'wpcable' ),
						(int) $transaction_count
					);
				} else {
					$row['status']  = 'never';
					$row['message'] = __( 'No recorded attempt and no local rows. Missing is not $0 revenue.', 'wpcable' );
				}
			}

			$row['succeeded_at'] = 0;
			$health[ $key ]      = self::normalize_row( $row );
		}

		return $health;
	}

	/**
	 * Map pending queue steps to health source keys.
	 *
	 * @param array $queue Queue items.
	 * @return array
	 */
	public static function sources_in_queue( $queue ) {
		$out = array();
		if ( ! is_array( $queue ) ) {
			return $out;
		}

		foreach ( $queue as $step ) {
			if ( ! is_array( $step ) || empty( $step['task'] ) ) {
				continue;
			}
			$task = (string) $step['task'];
			if ( 'profile' === $task ) {
				$out[] = 'profile';
			} elseif ( 'transactions' === $task ) {
				$out[] = 'transactions';
			} elseif ( 0 === strpos( $task, 'task' ) ) {
				$out[] = 'tasks';
			}
		}

		return array_values( array_unique( $out ) );
	}
}
