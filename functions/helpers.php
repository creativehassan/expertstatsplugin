<?php
/**
 * Helper functions used by other modules.
 *
 * @package wpcable
 */

/**
 * Format a numeric amount for display. Does not add a currency symbol.
 *
 * @param mixed $money Amount.
 * @return string
 */
function wpcable_money( $money ) {
	if ( ! is_numeric( $money ) ) {
		return '';
	}

	return number_format( (float) $money, 2, '.', ',' );
}

/**
 * Display money when the source is available; otherwise an em dash.
 * Never presents a missing import as $0.00.
 *
 * @param mixed $money     Amount.
 * @param bool  $available Whether a real financial source exists.
 * @return string
 */
function wpcable_format_money_display( $money, $available = true ) {
	if ( ! $available || ! is_numeric( $money ) ) {
		return '—';
	}

	return '$' . wpcable_money( $money );
}

function wpcable_compare_values( $from = 0, $to = 0 ) {
	return $from >= $to ? 'increase' : 'decrease';
}

/**
 * Signed 32-bit max / Y2K38 sentinel (19 Jan 2038 03:14:07 UTC).
 * Stored last_activity values at this bound render as "Jan 19 2038" and
 * produce negative ages such as "-4092d ago" if treated as real activity.
 */
function wpcable_int32_sentinel() {
	return 2147483647;
}

/**
 * Earliest unix time treated as a plausible Codeable activity (2008-01-01 UTC).
 *
 * @return int
 */
function wpcable_min_activity_timestamp() {
	return 1199145600;
}

/**
 * Normalize a value to a usable activity unix timestamp.
 * Rejects empty, epoch, pre-Codeable years, Y2K38/INT32_MAX sentinels,
 * year >= 2038, and timestamps more than one day in the future.
 *
 * @param mixed $value Datetime string or unix timestamp.
 * @return int Valid unix time, or 0 when unusable.
 */
function wpcable_normalize_timestamp( $value ) {
	if ( '' === $value || null === $value || false === $value ) {
		return 0;
	}

	if ( is_string( $value ) ) {
		if ( '0000-00-00 00:00:00' === $value || '0000-00-00' === $value ) {
			return 0;
		}
		if ( is_numeric( $value ) ) {
			$time = (int) $value;
		} else {
			$parsed = strtotime( $value );
			$time   = false === $parsed ? 0 : (int) $parsed;
		}
	} elseif ( is_numeric( $value ) ) {
		$time = (int) $value;
	} else {
		return 0;
	}

	if ( $time <= 0 ) {
		return 0;
	}

	if ( $time >= wpcable_int32_sentinel() ) {
		return 0;
	}

	$year = (int) gmdate( 'Y', $time );
	if ( $year < 2008 || $year >= 2038 ) {
		return 0;
	}

	if ( $time > ( time() + DAY_IN_SECONDS ) ) {
		return 0;
	}

	if ( $time < wpcable_min_activity_timestamp() ) {
		return 0;
	}

	return $time;
}

/**
 * Abbreviation for the WordPress timezone used by wpcable_date().
 * Unix timestamps are UTC instants; display is WP settings timezone, labeled.
 *
 * @return string
 */
function wpcable_timezone_label() {
	if ( function_exists( 'wp_timezone' ) ) {
		try {
			$tz  = wp_timezone();
			$now = new DateTime( 'now', $tz );
			$abbr = $now->format( 'T' );
			if ( $abbr ) {
				return $abbr;
			}
			return $tz->getName();
		} catch ( Exception $e ) {
			// Fall through to options.
		}
	}

	$string = get_option( 'timezone_string' );
	if ( is_string( $string ) && '' !== $string ) {
		return $string;
	}

	$offset = (float) get_option( 'gmt_offset', 0 );
	if ( abs( $offset ) < 0.01 ) {
		return 'UTC';
	}

	$sign = $offset >= 0 ? '+' : '-';
	return 'UTC' . $sign . (string) abs( $offset );
}

/**
 * Format a date/time from a unix timestamp or datetime string.
 * Empty, zero, epoch, future, and Y2K38 sentinel values stay blank —
 * never "1970" and never "19 Jan 2038".
 * Uses WP timezone (wp_date / date_i18n) without changing PHP default TZ.
 * Appends the zone label so UTC vs PKT is visible.
 *
 * @param mixed $value Datetime string or unix timestamp.
 * @return string
 */
function wpcable_date( $value ) {
	$time = wpcable_normalize_timestamp( $value );
	if ( ! $time ) {
		return '';
	}

	$format_string = get_option( 'date_format' ) . ' (' . get_option( 'time_format' ) . ')';
	if ( function_exists( 'wp_date' ) ) {
		$formatted = wp_date( $format_string, $time );
	} else {
		$formatted = date_i18n( $format_string, $time );
	}

	if ( ! $formatted ) {
		return '';
	}

	return $formatted . ' ' . wpcable_timezone_label();
}

/**
 * Whole days since a usable activity timestamp, or null when the source is invalid.
 * Invalid/future/sentinel values must not become negative ages or "0 days ago".
 *
 * @param mixed $value Datetime string or unix timestamp.
 * @return int|null
 */
function wpcable_stale_days( $value ) {
	$time = wpcable_normalize_timestamp( $value );
	if ( ! $time ) {
		return null;
	}

	$days = (int) floor( ( time() - $time ) / DAY_IN_SECONDS );
	return max( 0, $days );
}

/**
 * Whether a value is a usable datetime (not empty / epoch).
 *
 * @param mixed $value Datetime string or unix timestamp.
 * @return bool
 */
function wpcable_is_valid_datetime( $value ) {
	return '' !== wpcable_date( $value );
}

/**
 * Prepares an URL for a redirect to show certain messages.
 *
 * @param string $type   Either [error|success].
 * @param mixed  $values The values to send (a single string or array of strings).
 * @param string $url    Optionally a different target URL.
 * @return string Full URL containing the custom query params.
 */
function codeable_add_message_param( $type, $values, $url = '' ) {
	if ( ! $url ) {
		$url = admin_url( 'admin.php?page=codeable_settings' );
	} else {
		$url = remove_query_arg( [ '_wpnonce', 'action' ], $url );
	}

	if ( 'error' !== $type ) {
		$type = 'success';
	}
	if ( is_scalar( $values ) ) {
		$values = [ $values ];
	}

	$values     = array_merge( $values, codeable_get_message_param( $type, $url ) );
	$values     = array_unique( $values );
	$values_enc = array_map( 'urlencode', $values );
	$values_enc = base64_encode( implode( ',', $values_enc ) );

	return add_query_arg( $type, $values_enc, $url );
}

/**
 * Returns the required message params from the given URL (or current URL, when no
 * URL is specified in params)
 *
 * @param string $type Which messages to return [error|success].
 * @param string $url  Optionally a custom URL to parse.
 * @return array List of message params.
 */
function codeable_get_message_param( $type, $url = '' ) {
	$values = [];

	if ( $url ) {
		$parts = parse_url( $url );
		parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $params );
	} else {
		$params = $_REQUEST;
	}

	if ( 'error' !== $type ) {
		$type = 'success';
	}

	if ( isset( $params[ $type ] ) ) {
		$values = base64_decode( $params[ $type ] );

		if ( $values ) {
			$values = explode( ',', $values );
			$values = array_map( 'urldecode', $values );
		}
	}

	return is_array( $values ) ? $values : [];
}

/**
 * Check if an SSL warning should be displayed.
 *
 * @return bool
 */
function codeable_ssl_warning() {
	$check = 'auto';
	if ( defined( 'CODEABLE_SSL_CHECK' ) ) {
		$check = CODEABLE_SSL_CHECK;
	}

	if ( 'off' === $check ) {
		return false;
	}

	if ( 'auto' === $check ) {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && '127.0.0.1' === $_SERVER['REMOTE_ADDR'] ) {
			// "Remote" server is on local machine, i.e. development server.
			return false;
		} elseif ( isset( $_SERVER['HTTP_HOST'] ) && preg_match( '/\.local$/', $_SERVER['HTTP_HOST'] ) ) {
			// A ".local" domain, i.e. development server.
			return false;
		}
	}

	return ! is_ssl();
}

/**
 * @return bool
 * @deprecated
 * Check if an PHP Timout warning should be displayed.
 */
function codeable_timeout_warning() {
	$timeout = ini_get( 'max_execution_time' );

	if ( $timeout < 120 ) {
		return true;
	}

	return false;
}

/**
 * Outputs default notices on all Codeable Stats pages.
 *
 * @return void
 */
function codeable_admin_notices() {
	$errors  = codeable_get_message_param( 'error' );
	$success = codeable_get_message_param( 'success' );

	if ( $errors ) :
		?>
		<div class="notice error">
			<?php if ( in_array( 'credentials', $errors, true ) ) : ?>
				<p><?php esc_html_e( 'Invalid username or password', 'wpcable' ); ?></p>
			<?php else : ?>
				<p><?php echo implode( '<br />', array_map( 'esc_html', $errors ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	endif;

	if ( $success ) :
		?>
		<div class="notice updated">
			<?php if ( in_array( 'fetched', $success, true ) ) : ?>
				<p><?php esc_html_e( 'Fetched latest details from API.', 'wpcable' ); ?></p>
			<?php else : ?>
				<p><?php echo implode( '<br />', array_map( 'esc_html', $success ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	endif;

	if ( codeable_ssl_warning() ) :
		?>
		<div class="update-nag notice">
			<p><?php esc_html_e( 'Please consider installing this plugin on a secure website', 'wpcable' ); ?></p>
		</div>
		<?php
	endif;
}

/**
 * Return stored Codeable account details as an array.
 * Malformed API/option values (string/bool/null) are treated as empty.
 *
 * @return array
 */
function wpcable_get_account_details() {
	$account_details = get_option( 'wpcable_account_details' );

	return is_array( $account_details ) ? $account_details : array();
}

/**
 * Resolve an avatar URL from account details without assuming nested shape.
 * Supports legacy nested avatar arrays and a plain string URL.
 * Does not invent profile data when the field is missing.
 *
 * @param array $account_details Account details option value.
 * @return string
 */
function wpcable_account_avatar_url( $account_details ) {
	if ( ! is_array( $account_details ) || ! isset( $account_details['avatar'] ) ) {
		return '';
	}

	$avatar = $account_details['avatar'];

	if ( is_string( $avatar ) ) {
		return $avatar;
	}

	if ( ! is_array( $avatar ) ) {
		return '';
	}

	foreach ( array( 'large_url', 'medium_url', 'small_url', 'tiny_url' ) as $key ) {
		if ( ! empty( $avatar[ $key ] ) && is_string( $avatar[ $key ] ) ) {
			return $avatar[ $key ];
		}
	}

	return '';
}

/**
 * Safely read a top-level account detail field.
 *
 * @param array  $account_details Account details.
 * @param string $key             Field name.
 * @param mixed  $default         Default when missing/invalid container.
 * @return mixed
 */
function wpcable_account_detail( $account_details, $key, $default = '' ) {
	if ( ! is_array( $account_details ) || ! array_key_exists( $key, $account_details ) ) {
		return $default;
	}

	return $account_details[ $key ];
}

/**
 * Safely read a nested stats field from account details.
 *
 * @param array  $account_details Account details.
 * @param string $key             Stats field name.
 * @param mixed  $default         Default when missing.
 * @return mixed
 */
function wpcable_account_stat( $account_details, $key, $default = 0 ) {
	if ( ! is_array( $account_details ) ) {
		return $default;
	}

	$stats = isset( $account_details['stats'] ) ? $account_details['stats'] : null;
	if ( ! is_array( $stats ) || ! array_key_exists( $key, $stats ) ) {
		return $default;
	}

	return $stats[ $key ];
}

/**
 * Displays a login-nag when the user is not logged in
 *
 * @param string $page_title Page heading.
 * @return void
 */
function codeable_page_requires_login( $page_title ) {
	unset( $page_title );
	// 0.4.3: do not block screens. Saved profile/projects/notes remain visible
	// when the auth token is absent. Sync is gated separately.
}

/**
 * Simply expires the current auth_token, so the API sync does not work anymore.
 * The user will see the login form again when he visits the settings page the next
 * time.
 *
 * @return void
 */
function codeable_api_logout() {
	delete_option( 'wpcable_auth_token' );

	if ( function_exists( 'wpcable_history_backfill_pause' ) ) {
		$job = wpcable_history_backfill_job();
		if ( wpcable_history_backfill_is_active( $job ) ) {
			wpcable_history_backfill_pause( 'disconnected' );
		}
	}

	// Flush object cache.
	wpcable_cache::flush();
}

/**
 * Flushes all locally stored data and forgets the authentication token.
 *
 * @return void
 */
function codeable_flush_all_data() {
	global $wpdb;

	$tables = [
		$wpdb->prefix . 'codeable_transcactions' => __( 'Transcactions', 'wpcable' ),
		$wpdb->prefix . 'codeable_clients'       => __( 'Clients', 'wpcable' ),
		$wpdb->prefix . 'codeable_amounts'       => __( 'Amounts', 'wpcable' ),
		$wpdb->prefix . 'codeable_tasks'         => __( 'Tasks', 'wpcable' ),
	];

	$redirect_to = '';

	foreach ( $tables as $db_table => $db_label ) {
		if ( true === $wpdb->query( "TRUNCATE `{$db_table}`;" ) ) {
			$redirect_to = codeable_add_message_param(
				'success',
				sprintf(
					/* translators: %s: table label */
					__( 'All %s removed.', 'wpcable' ),
					'<b>' . esc_html( $db_label ) . '</b>'
				),
				$redirect_to
			);
		} else {
			$redirect_to = codeable_add_message_param(
				'error',
				sprintf(
					/* translators: %s: table label */
					__( '%s could not be removed.', 'wpcable' ),
					'<b>' . esc_html( $db_label ) . '</b>'
				),
				$redirect_to
			);
		}
	}

	// Flush object cache.
	wpcable_cache::flush();

	delete_option( 'wpcable_email' );
	delete_option( 'wpcable_average' );
	delete_option( 'wpcable_balance' );
	delete_option( 'wpcable_revenue' );
	delete_option( 'wpcable_last_fetch' );
	delete_option( 'wpcable_account_details' );
	delete_option( 'wpcable_import_health' );
	delete_option( 'wpcable_opportunity_snapshot' );
	delete_option( 'wpcable_history_backfill' );
	delete_option( 'wpcable_history_backfill_lock' );
	if ( function_exists( 'wpcable_history_backfill_unschedule' ) ) {
		wpcable_history_backfill_unschedule();
	}

	codeable_api_logout();

	$redirect_to = codeable_add_message_param(
		'success',
		__( 'Forgot your <b>authentication</b> and <b>profile</b> details.', 'wpcable' ),
		$redirect_to
	);

	wp_safe_redirect( $redirect_to );
	exit;
}

/**
 * Pending Codeable GET queue, or an empty array.
 *
 * @return array
 */
function wpcable_sync_queue() {
	$queue = get_option( 'wpcable_api_queue' );
	return is_array( $queue ) ? $queue : array();
}

/**
 * Whether a Refresh queue is still pending (interrupted or in flight).
 *
 * @return bool
 */
function wpcable_sync_is_running() {
	return ! empty( wpcable_sync_queue() );
}

/**
 * Head of the pending queue, or null.
 *
 * @return array|null
 */
function wpcable_sync_current_step() {
	$queue = wpcable_sync_queue();
	if ( empty( $queue ) ) {
		return null;
	}
	$head = reset( $queue );
	return is_array( $head ) ? $head : null;
}

/**
 * Acquire a short-lived sync lock so two admin tabs cannot process the same
 * queue item (including mark_tasks_lost) at once.
 *
 * @param int $ttl Seconds the lock is valid.
 * @return bool True when this caller owns the lock.
 */
function wpcable_sync_lock_acquire( $ttl = 45 ) {
	$now  = time();
	$lock = get_option( 'wpcable_sync_lock', false );
	if ( is_array( $lock ) && ! empty( $lock['until'] ) && (int) $lock['until'] > $now ) {
		return false;
	}

	update_option(
		'wpcable_sync_lock',
		array(
			'until'   => $now + max( 5, (int) $ttl ),
			'started' => $now,
		)
	);

	return true;
}

/**
 * Release the sync lock.
 *
 * @return void
 */
function wpcable_sync_lock_release() {
	delete_option( 'wpcable_sync_lock' );
}

/**
 * Whether another request currently holds the sync lock.
 *
 * @return bool
 */
function wpcable_sync_lock_held() {
	$lock = get_option( 'wpcable_sync_lock', false );
	return is_array( $lock ) && ! empty( $lock['until'] ) && (int) $lock['until'] > time();
}

/**
 * 0.4.1: Ordinary page views and WP-Cron must not enqueue a new Codeable GET
 * queue. The first queue step is still `task:lost` → mark_tasks_lost(), which
 * rewrites published/estimated/hired rows. Navigation used to race KPI counts.
 *
 * New queue work starts only from explicit Refresh (`wpcable_sync_start`).
 * An already-pending queue is left in place so an interrupted Refresh can resume.
 * `$force` is kept for signature compatibility and does not enqueue.
 *
 * @param bool $force Unused. Explicit Refresh is wpcable_sync_start.
 * @return void
 */
function codeable_maybe_refresh_data( $force = false ) {
	unset( $force );
}

/**
 * Display a notice of last API fetch and a refresh-button, plus per-source health.
 *
 * @return void
 */
function codeable_last_fetch_info() {
	$tasks      = new wpcable_tasks();
	$stats      = new wpcable_stats();
	$account    = wpcable_get_account_details();
	$counts     = $tasks->get_state_counts();
	$queue      = wpcable_sync_queue();
	$health     = wpcable_import_health::snapshot_from_counts(
		$counts['imported'],
		$stats->get_transaction_count(),
		! empty( $account['id'] ),
		$queue
	);
	$last_fetch = get_option( 'wpcable_last_fetch' );
	$queue_date = wpcable_date( $last_fetch );
	$running    = ! empty( $queue );
	$step       = wpcable_sync_current_step();
	$nonce      = wp_create_nonce( 'wpcable-sync' );

	?>
	<div id="wpcable-import-health" class="codeable-last-refresh wpcable-import-health">
		<strong><?php esc_html_e( 'Import health (per source)', 'wpcable' ); ?></strong>
		<?php if ( $running ) : ?>
			<p class="wpcable-sync-banner">
				<?php
				$label = isset( $step['label'] ) ? $step['label'] : __( 'Codeable GET refresh', 'wpcable' );
				$page  = isset( $step['page'] ) ? (int) $step['page'] : 0;
				printf(
					/* translators: 1: current step label, 2: page number or em dash */
					esc_html__( 'Refresh in progress: %1$s (page %2$s). Counts can change until this queue finishes. Page navigation does not start a new queue.', 'wpcable' ),
					esc_html( $label ),
					esc_html( $page ? (string) $page : '—' )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
		if ( function_exists( 'wpcable_history_backfill_public' ) ) {
			$backfill = wpcable_history_backfill_public();
			if ( in_array( $backfill['state'], array( 'running', 'waiting_retry', 'paused', 'auth_paused' ), true ) ) {
				echo '<p class="wpcable-sync-banner">';
				echo esc_html(
					sprintf(
						/* translators: 1: job state, 2: done count, 3: snapshot count, 4: current task id */
						__( 'Comments backfill %1$s — %2$d of %3$d projects processed (current %4$s). Settings starts/pauses this job. Default WP-Cron needs site traffic or a real cron to wp-cron.php.', 'wpcable' ),
						$backfill['state'],
						(int) $backfill['processed_count'],
						(int) $backfill['snapshot_count'],
						$backfill['current_id'] ? (string) (int) $backfill['current_id'] : '—'
					)
				);
				echo '</p>';
			}
		}
		?>
		<ul class="wpcable-import-health-list">
			<?php foreach ( wpcable_import_health::sources() as $source_key => $source_label ) : ?>
				<?php $row = isset( $health[ $source_key ] ) ? $health[ $source_key ] : array(); ?>
				<li class="wpcable-import-health-<?php echo esc_attr( wpcable_import_health::status_of( $row ) ); ?>">
					<span class="wpcable-import-health-label"><?php echo esc_html( $source_label ); ?></span>
					<span class="wpcable-import-health-status"><?php echo esc_html( wpcable_import_health::status_label( $row ) ); ?></span>
					<?php
					$success_label = wpcable_import_health::success_time_label( $row );
					if ( $success_label ) :
						?>
						<span class="wpcable-import-health-when"><?php echo esc_html( $success_label ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="description">
			<?php if ( $queue_date ) : ?>
				<?php
				printf(
					/* translators: %s: formatted datetime */
					esc_html__( 'Last full refresh queue finished: %s. That is the queue clock in the WordPress timezone (abbreviation shown), not proof every source succeeded, and not MAX(last_activity) / MAX(last_sync).', 'wpcable' ),
					esc_html( $queue_date )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'No completed refresh queue is stored. Per-source timestamps appear only after an instrumented attempt recorded success — never from cached row dates.', 'wpcable' ); ?>
			<?php endif; ?>
			<span class="tooltip" tabindex="0">
				<span class="tooltip-text"><?php esc_html_e( 'Click Refresh to start a read-only Codeable GET queue. Page views and hourly WP-Cron do not enqueue a new queue (0.4.1). An interrupted Refresh still resumes. Access is read-only. A missing transactions import is not $0 revenue. Cached rows without a recorded attempt are labeled as cached, not as a successful import.', 'wpcable' ); ?></span>
				<i class="dashicons dashicons-info"></i>
			</span>
			|
			<?php if ( wpcable_sync_allowed() ) : ?>
				<a href="#refresh" class="sync-start">
					<?php esc_html_e( 'Refresh', 'wpcable' ); ?>
				</a>
			<?php else : ?>
				<span class="wpcable-sync-disabled"><?php esc_html_e( 'Refresh is off while disconnected. Saved data stays visible.', 'wpcable' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_settings' ) ); ?>">
					<?php esc_html_e( 'Connect in Settings', 'wpcable' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>
	<div class="codeable-sync-progress" style="<?php echo $running ? '' : 'display:none'; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-resume-pending="1" data-sync-allowed="<?php echo wpcable_sync_allowed() ? '1' : '0'; ?>">
		<span class="spinner is-active"></span>
		<span class="msg">
			<?php
			if ( $running && isset( $step['label'] ) ) {
				echo esc_html( $step['label'] );
			}
			?>
		</span>
	</div>
	<?php
}

/**
 * Tests, whether the user is currently logged into the codeable API.
 *
 * @return bool
 */
function codeable_api_logged_in() {
	$token = get_option( 'wpcable_auth_token', false );

	if ( ! $token ) {
		return false;
	}

	$api = wpcable_api_calls::inst();

	return $api->auth_token_known();
}

/**
 * Authenticate the user with given email/password and store the auth_token for
 * later usage in the DB (encrypted).
 *
 * @param  string $email    Users email address.
 * @param  string $password Password for authentication.
 * @return void
 */
function codeable_api_authenticate( $email, $password ) {
	$api = wpcable_api_calls::inst();
	$api->login( $email, $password );

	if ( $api->auth_token_known() ) :
		?>
		<div class="updated notice">
			<p>
				<?php esc_html_e( 'You are now logged in!', 'wpcable' ); ?>
			</p>
		</div>
		<?php
	endif;
}

/**
 * Shared AJAX permission gate: capability + nonce. Never returns tokens.
 *
 * @param string $nonce_action Nonce action.
 * @param string $nonce_field  Request field holding the nonce.
 * @return bool
 */
function wpcable_ajax_can_manage( $nonce_action = 'wpcable-workspace', $nonce_field = '_wpnonce' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	$nonce = isset( $_REQUEST[ $nonce_field ] ) ? wp_unslash( $_REQUEST[ $nonce_field ] ) : '';
	if ( ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
		return false;
	}

	return true;
}

/**
 * Color flags used on the task list (local labels, not Codeable API states).
 *
 * @return array
 */
function wpcable_task_color_flags() {
	return array(
		''            => array(
			'label' => __( 'New', 'wpcable' ),
			'color' => '',
		),
		'prio'        => array(
			'label' => __( 'Priority', 'wpcable' ),
			'color' => '#cc0000',
		),
		'completed'   => array(
			'label' => __( 'Won (completed)', 'wpcable' ),
			'color' => '#b39ddb',
		),
		'won'         => array(
			'label' => __( 'Won (active)', 'wpcable' ),
			'color' => '#673ab7',
		),
		'estimated'   => array(
			'label' => __( 'Estimated', 'wpcable' ),
			'color' => '#9ccc65',
		),
		'optimistic'  => array(
			'label' => __( 'Active rapport', 'wpcable' ),
			'color' => '#00b0ff',
		),
		'neutral'     => array(
			'label' => __( 'Normal', 'wpcable' ),
			'color' => '#80d8ff',
		),
		'tough'       => array(
			'label' => __( 'Difficult', 'wpcable' ),
			'color' => '#607d8b',
		),
		'pessimistic' => array(
			'label' => __( 'Unresponsive', 'wpcable' ),
			'color' => '#90a4ae',
		),
		'lost'        => array(
			'label' => __( 'Lost (local heuristic)', 'wpcable' ),
			'color' => '#cfd8dc',
		),
	);
}

/**
 * Public workroom URL for a task. Read-only link into Codeable; not an API write.
 *
 * @param int $task_id Task ID.
 * @return string
 */
function wpcable_workroom_url( $task_id ) {
	$task_id = (int) $task_id;
	if ( $task_id <= 0 ) {
		return '';
	}

	// Pattern already used on the Stats clients table in this plugin
	// (functions/admin-page.php). Read-only browser link, not an API write.
	return 'https://app.codeable.io/tasks/' . $task_id . '/workroom';
}

/**
 * History panel for a task row, including account-scoped cache and load flags.
 *
 * @param array $task Sanitized task.
 * @return array
 */
function wpcable_task_history_unavailable( $task = array() ) {
	return function_exists( 'wpcable_task_history_for_display' )
		? wpcable_task_history_for_display( $task )
		: wpcable_task_history( $task );
}

/**
 * Known task states stored locally.
 *
 * @return array
 */
function wpcable_known_task_states() {
	return array( 'published', 'estimated', 'hired', 'paid', 'completed', 'refunded', 'canceled', 'lost' );
}
