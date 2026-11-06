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
		$allowed = array( 'never', 'attempted', 'success', 'failed', 'missing', 'empty' );
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
			'never'     => __( 'Never attempted', 'wpcable' ),
			'attempted' => __( 'Attempted — result unknown', 'wpcable' ),
			'success'   => __( 'Imported', 'wpcable' ),
			'failed'    => __( 'Failed', 'wpcable' ),
			'missing'   => __( 'Unavailable', 'wpcable' ),
			'empty'     => __( 'Reached API, no rows', 'wpcable' ),
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
		if ( $row['succeeded_at'] <= 0 ) {
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
	 * Derive a display snapshot from local tables when option is empty.
	 * Does not invent success times.
	 *
	 * @param int $task_count         Local task rows.
	 * @param int $transaction_count  Local transaction rows.
	 * @param int $profile_present    1 if profile option is an array with an id.
	 * @return array
	 */
	public static function snapshot_from_counts( $task_count, $transaction_count, $profile_present ) {
		$health = self::get();

		if ( 'never' === $health['tasks']['status'] && $task_count > 0 ) {
			$health['tasks']['status'] = 'success';
			$health['tasks']['rows']   = (int) $task_count;
			$health['tasks']['message'] = sprintf(
				/* translators: %d: row count */
				__( '%d local rows (no recorded attempt timestamp)', 'wpcable' ),
				(int) $task_count
			);
		}

		if ( 'never' === $health['transactions']['status'] ) {
			if ( $transaction_count > 0 ) {
				$health['transactions']['status']  = 'success';
				$health['transactions']['rows']    = (int) $transaction_count;
				$health['transactions']['message'] = sprintf(
					/* translators: %d: row count */
					__( '%d local rows (no recorded attempt timestamp)', 'wpcable' ),
					(int) $transaction_count
				);
			} else {
				$health['transactions']['status']  = 'missing';
				$health['transactions']['message'] = __( 'No local transaction rows. Not $0 revenue.', 'wpcable' );
			}
		}

		if ( 'never' === $health['profile']['status'] && $profile_present ) {
			$health['profile']['status']  = 'success';
			$health['profile']['message'] = __( 'Profile option present (no recorded attempt timestamp)', 'wpcable' );
		}

		return $health;
	}
}
