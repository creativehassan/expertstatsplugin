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
 * Format a date/time from a unix timestamp or datetime string.
 * Empty, zero, and Unix-epoch values are treated as missing — never "1970".
 *
 * @param mixed $value Datetime string or unix timestamp.
 * @return string
 */
function wpcable_date( $value ) {
	if ( '' === $value || null === $value || false === $value ) {
		return '';
	}

	if ( is_string( $value ) ) {
		if ( '0000-00-00 00:00:00' === $value || '0000-00-00' === $value ) {
			return '';
		}
		$time = strtotime( $value );
	} elseif ( is_numeric( $value ) ) {
		$time = (int) $value;
	} else {
		return '';
	}

	if ( ! $time || $time <= 0 ) {
		return '';
	}

	// Guard against epoch leakage (strtotime(false) / empty dateadded).
	$year = (int) gmdate( 'Y', $time );
	if ( $year < 2008 ) {
		return '';
	}

	$format_string = get_option( 'date_format' ) . ' (' . get_option( 'time_format' ) . ')';
	return date_i18n( $format_string, $time );
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
	if ( codeable_api_logged_in() ) {
		return;
	}

	?>
	<div class="wrap">
		<h1><?php echo esc_html( $page_title ); ?></h1>

		<div class="notice error">
			<p>
				<?php
				printf(
					/* translators: 1: opening anchor, 2: closing anchor */
					esc_html__( 'Please %1$slog in%2$s to access your stats.', 'wpcable' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=codeable_settings' ) ) . '">',
					'</a>'
				);
				?>
			</p>
		</div>
	</div>
	<?php
	exit;
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
 * Checks, whether we should (auto) refresh the stats/pull data from API.
 *
 * @param bool $force Force a queue rebuild.
 * @return void
 */
function codeable_maybe_refresh_data( $force = false ) {
	$sync_now = $force;

	if ( ! $sync_now ) {
		$last_fetch = (int) get_option( 'wpcable_last_fetch' );

		if ( ! $last_fetch ) {
			$sync_now = true;
		} else {
			$sync_now = time() - $last_fetch > HOUR_IN_SECONDS;
		}
	}

	if ( ! $sync_now ) {
		return;
	}

	$queue = get_option( 'wpcable_api_queue' );
	$data  = new wpcable_api_data();

	if ( empty( $queue ) || ! is_array( $queue ) ) {
		$queue = $data->prepare_queue();
		update_option( 'wpcable_api_queue', $queue );
	}
}

/**
 * Display a notice of last API fetch and a refresh-button, plus per-source health.
 *
 * @return void
 */
function codeable_last_fetch_info() {
	$health     = wpcable_import_health::get();
	$last_fetch = get_option( 'wpcable_last_fetch' );
	$queue_date = wpcable_date( $last_fetch );

	?>
	<div class="codeable-last-refresh wpcable-import-health">
		<strong><?php esc_html_e( 'Import health (per source)', 'wpcable' ); ?></strong>
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
					esc_html__( 'Last full refresh queue finished: %s. That is the queue clock, not proof every source succeeded.', 'wpcable' ),
					esc_html( $queue_date )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'No completed refresh queue is stored. Timestamps are shown only when that source actually succeeded.', 'wpcable' ); ?>
			<?php endif; ?>
			<span class="tooltip" tabindex="0">
				<span class="tooltip-text"><?php esc_html_e( 'The plugin loads data from Codeable once per hour, or when you click Refresh. Access is read-only. A missing transactions import is not the same as $0 revenue.', 'wpcable' ); ?></span>
				<i class="dashicons dashicons-info"></i>
			</span>
			|
			<a href="#refresh" class="sync-start">
				<?php esc_html_e( 'Refresh', 'wpcable' ); ?>
			</a>
		</p>
	</div>
	<div class="codeable-sync-progress" style="display:none">
		<span class="spinner is-active"></span>
		<span class="msg"></span>
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

	return 'https://app.codeable.io/tasks/' . $task_id;
}

/**
 * Known task states stored locally.
 *
 * @return array
 */
function wpcable_known_task_states() {
	return array( 'published', 'estimated', 'hired', 'paid', 'completed', 'refunded', 'canceled', 'lost' );
}
