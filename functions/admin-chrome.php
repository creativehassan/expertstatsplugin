<?php
/**
 * Shared admin chrome, connection state, and local-data overview.
 * Pages always read WordPress options/tables. Sync is a separate action.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin screens for the in-page nav. WP menu slugs are unchanged.
 *
 * @return array
 */
function wpcable_app_pages() {
	return array(
		'stats'          => array(
			'slug'  => 'codeable_transcactions_stats',
			'label' => __( 'Overview', 'wpcable' ),
		),
		'tasks'          => array(
			'slug'  => 'codeable_tasks',
			'label' => __( 'Projects', 'wpcable' ),
		),
		'clients'        => array(
			'slug'  => 'codeable_clients',
			'label' => __( 'Clients', 'wpcable' ),
		),
		'opportunities'  => array(
			'slug'  => 'codeable_opportunities',
			'label' => __( 'Opportunities', 'wpcable' ),
		),
		'estimate'       => array(
			'slug'  => 'codeable_estimate',
			'label' => __( 'Estimate', 'wpcable' ),
		),
		'settings'       => array(
			'slug'  => 'codeable_settings',
			'label' => __( 'Settings', 'wpcable' ),
		),
	);
}

/**
 * Whether Codeable GET/sync is allowed. Saved data is independent of this.
 *
 * @return bool
 */
function wpcable_sync_allowed() {
	return codeable_api_logged_in();
}

/**
 * Whether any non-secret local records exist.
 *
 * @return bool
 */
function wpcable_has_saved_workspace_data() {
	$account = wpcable_get_account_details();
	if ( ! empty( $account['id'] ) || ! empty( $account['first_name'] ) ) {
		return true;
	}

	$tasks = new wpcable_tasks();
	$counts = $tasks->get_state_counts();
	if ( ! empty( $counts['imported'] ) ) {
		return true;
	}

	$stats = new wpcable_stats();
	if ( $stats->get_transaction_count() > 0 ) {
		return true;
	}

	return false;
}

/**
 * Connection + data presence for chrome.
 *
 * @return array {state, connected, has_data, label, detail}
 */
function wpcable_connection_state() {
	$connected = wpcable_sync_allowed();
	$has_data  = wpcable_has_saved_workspace_data();

	if ( $connected ) {
		return array(
			'state'     => 'connected',
			'connected' => true,
			'has_data'  => $has_data,
			'label'     => __( 'Connected', 'wpcable' ),
			'detail'    => __( 'Refresh is available. Pages still read the local WordPress database.', 'wpcable' ),
		);
	}

	if ( $has_data ) {
		return array(
			'state'     => 'disconnected_with_data',
			'connected' => false,
			'has_data'  => true,
			'label'     => __( 'Disconnected — showing saved data', 'wpcable' ),
			'detail'    => __( 'Codeable sign-in is not active. Sync is off. Saved projects, clients, notes, and profile stay here until you use Clear all data.', 'wpcable' ),
		);
	}

	return array(
		'state'     => 'empty',
		'connected' => false,
		'has_data'  => false,
		'label'     => __( 'Not connected', 'wpcable' ),
		'detail'    => __( 'No saved Codeable records yet. Connect in Settings, then Refresh to import.', 'wpcable' ),
	);
}

/**
 * Honest freshness from instrumented import health — never invented from row dates.
 *
 * @return string
 */
function wpcable_freshness_copy() {
	$health = wpcable_import_health::get();
	$latest = 0;
	foreach ( $health as $row ) {
		if ( empty( $row['succeeded_at'] ) ) {
			continue;
		}
		$ts = wpcable_normalize_timestamp( $row['succeeded_at'] );
		if ( $ts > $latest ) {
			$latest = $ts;
		}
	}

	if ( ! $latest ) {
		return __( 'Source freshness unknown — no successful import is logged.', 'wpcable' );
	}

	$when = wpcable_date( $latest );
	return sprintf(
		/* translators: %s: formatted datetime with timezone */
		__( 'Last successful import logged: %s', 'wpcable' ),
		$when
	);
}

/**
 * Local overview metrics for Stats. All from WP DB/options.
 *
 * @return array
 */
function wpcable_overview_metrics() {
	$tasks   = new wpcable_tasks();
	$stats   = new wpcable_stats();
	$account = wpcable_get_account_details();
	$counts  = $tasks->get_state_counts();
	$filters = $tasks->get_filter_counts();
	$opps    = new wpcable_opportunities();
	$cues    = $opps->get_suggestions();
	$opps->persist_snapshot( $cues );

	$name = trim(
		wpcable_account_detail( $account, 'first_name', '' ) . ' ' .
		wpcable_account_detail( $account, 'last_name', '' )
	);

	return array(
		'account'            => $account,
		'display_name'       => $name ? $name : __( 'Saved expert profile', 'wpcable' ),
		'role'               => wpcable_account_detail( $account, 'role', '' ),
		'avatar'             => wpcable_account_avatar_url( $account ),
		'profile_completed'  => wpcable_account_detail( $account, 'completed_tasks_count', null ),
		'profile_projects'   => wpcable_account_detail( $account, 'tasks_count', null ),
		'imported'           => isset( $counts['imported'] ) ? (int) $counts['imported'] : 0,
		'published'          => isset( $filters['published'] ) ? (int) $filters['published'] : 0,
		'completed'          => isset( $filters['completed'] ) ? (int) $filters['completed'] : 0,
		'lost'               => isset( $filters['lost'] ) ? (int) $filters['lost'] : 0,
		'clients'            => $tasks->count_distinct_clients(),
		'opportunities'      => count( $cues ),
		'transactions'       => $stats->get_transaction_count(),
		'financial'          => $stats->has_transaction_data(),
		'activity'           => $tasks->get_activity_buckets(),
		'states'             => $tasks->get_state_distribution(),
		'connection'         => wpcable_connection_state(),
		'freshness'          => wpcable_freshness_copy(),
	);
}

/**
 * Open the shared plugin chrome. WP admin shell/menu stay in place.
 *
 * @param string $current Current page key from wpcable_app_pages().
 * @param string $title   Visible heading.
 * @param string $lede    One-line context.
 * @return void
 */
function wpcable_app_open( $current, $title, $lede = '' ) {
	$pages  = wpcable_app_pages();
	$state  = wpcable_connection_state();
	$status = $state['state'];
	?>
	<div
		class="wrap wpcable-app wpcable-workspace wpcable-workspace--<?php echo esc_attr( $current ); ?>"
		data-workspace="<?php echo esc_attr( $current ); ?>"
		data-connected="<?php echo $state['connected'] ? '1' : '0'; ?>"
		data-has-data="<?php echo $state['has_data'] ? '1' : '0'; ?>"
	>
		<header class="wpcable-app-header">
			<div class="wpcable-app-heading">
				<p class="wpcable-app-kicker"><?php esc_html_e( 'Codeable Stats', 'wpcable' ); ?></p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $lede ) : ?>
					<p class="wpcable-lede"><?php echo esc_html( $lede ); ?></p>
				<?php endif; ?>
			</div>
			<div class="wpcable-status wpcable-status--<?php echo esc_attr( $status ); ?>">
				<strong><?php echo esc_html( $state['label'] ); ?></strong>
				<span><?php echo esc_html( $state['detail'] ); ?></span>
				<span class="wpcable-status__fresh"><?php echo esc_html( wpcable_freshness_copy() ); ?></span>
				<div class="wpcable-density" role="group" aria-label="<?php echo esc_attr__( 'Preview density', 'wpcable' ); ?>">
					<button type="button" class="button" data-density="comfortable"><?php esc_html_e( 'Comfortable', 'wpcable' ); ?></button>
					<button type="button" class="button" data-density="compact"><?php esc_html_e( 'Compact', 'wpcable' ); ?></button>
				</div>
			</div>
		</header>
		<nav class="wpcable-app-nav" aria-label="<?php esc_attr_e( 'Codeable Stats sections', 'wpcable' ); ?>">
			<?php foreach ( $pages as $key => $page ) : ?>
				<a
					class="<?php echo $key === $current ? 'is-current' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page['slug'] ) ); ?>"
				><?php echo esc_html( $page['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php
}

/**
 * Close chrome, including import-health footer.
 *
 * @return void
 */
function wpcable_app_close() {
	codeable_last_fetch_info();
	?>
	<script>
	(function () {
		var root = document.querySelector('.wpcable-app');
		if (!root || !window.localStorage) {
			return;
		}
		var key = 'wpcable-density';
		var saved = localStorage.getItem(key) || 'comfortable';
		root.classList.toggle('wpcable-app--compact', saved === 'compact');
		root.querySelectorAll('[data-density]').forEach(function (btn) {
			btn.classList.toggle('button-primary', btn.getAttribute('data-density') === saved);
			btn.addEventListener('click', function () {
				var next = btn.getAttribute('data-density');
				localStorage.setItem(key, next);
				root.classList.toggle('wpcable-app--compact', next === 'compact');
				root.querySelectorAll('[data-density]').forEach(function (other) {
					other.classList.toggle('button-primary', other.getAttribute('data-density') === next);
				});
			});
		});
	})();
	</script>
	<?php
	echo '</div>';
}

/**
 * Local overview strip used on Stats. Grounded in WP tables/options only.
 *
 * @return void
 */
function wpcable_overview_panel() {
	$m     = wpcable_overview_metrics();
	$max   = max( 1, $m['imported'], $m['clients'], $m['opportunities'] );
	$states = $m['states'];
	$state_max = 1;
	foreach ( $states as $row ) {
		$state_max = max( $state_max, (int) $row['count'] );
	}
	?>
	<section class="wpcable-overview">
		<div class="wpcable-kpi-row">
			<div class="wpcable-kpi wpcable-kpi--profile">
				<?php if ( $m['avatar'] ) : ?>
					<img class="wpcable-kpi__avatar" src="<?php echo esc_url( $m['avatar'] ); ?>" alt="" />
				<?php endif; ?>
				<div>
					<span class="wpcable-kpi__label"><?php esc_html_e( 'Expert profile', 'wpcable' ); ?></span>
					<span class="wpcable-kpi__value"><?php echo esc_html( $m['display_name'] ); ?></span>
					<span class="wpcable-kpi__meta">
						<?php
						echo esc_html( $m['role'] ? $m['role'] : __( 'Role not in saved profile', 'wpcable' ) );
						echo ' · ';
						esc_html_e( 'Saved expert profile — different scope from imported rows', 'wpcable' );
						?>
					</span>
				</div>
			</div>
			<div class="wpcable-kpi">
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Profile completed', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value"><?php echo null === $m['profile_completed'] || '' === $m['profile_completed'] ? '—' : esc_html( (string) $m['profile_completed'] ); ?></span>
				<span class="wpcable-kpi__meta"><?php esc_html_e( 'Expert profile completed count, not the local table', 'wpcable' ); ?></span>
			</div>
			<div class="wpcable-kpi">
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Profile projects', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value"><?php echo null === $m['profile_projects'] || '' === $m['profile_projects'] ? '—' : esc_html( (string) $m['profile_projects'] ); ?></span>
				<span class="wpcable-kpi__meta"><?php esc_html_e( 'Expert profile project count — not imported rows', 'wpcable' ); ?></span>
			</div>
			<div class="wpcable-kpi">
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Imported projects', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( $m['imported'] ) ); ?></span>
				<span class="wpcable-kpi__meta"><?php esc_html_e( 'Local imported project rows', 'wpcable' ); ?></span>
			</div>
			<div class="wpcable-kpi">
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Clients', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( $m['clients'] ) ); ?></span>
				<span class="wpcable-kpi__meta"><?php esc_html_e( 'Distinct clients on imported projects', 'wpcable' ); ?></span>
			</div>
			<div class="wpcable-kpi">
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Opportunity cues', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( $m['opportunities'] ) ); ?></span>
				<span class="wpcable-kpi__meta"><?php esc_html_e( 'Grounded from local records, not confirmed needs', 'wpcable' ); ?></span>
			</div>
			<div class="wpcable-kpi<?php echo $m['financial'] ? '' : ' wpcable-kpi--unavailable'; ?>">
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Money transactions', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value">
					<?php
					if ( $m['financial'] ) {
						echo esc_html( number_format_i18n( $m['transactions'] ) );
					} else {
						esc_html_e( 'Unavailable', 'wpcable' );
					}
					?>
				</span>
				<span class="wpcable-kpi__meta"><?php echo $m['financial'] ? esc_html__( 'Imported transaction rows', 'wpcable' ) : esc_html__( 'Not imported — this is not $0', 'wpcable' ); ?></span>
			</div>
		</div>
		<div class="wpcable-overview-charts">
			<div class="wpcable-chart-card">
				<h2><?php esc_html_e( 'Imported project states', 'wpcable' ); ?></h2>
				<?php if ( empty( $states ) ) : ?>
					<p class="description"><?php esc_html_e( 'No imported project rows yet.', 'wpcable' ); ?></p>
				<?php else : ?>
					<?php foreach ( $states as $row ) : ?>
						<?php wpcable_meter( $row['state'], $row['count'], $state_max, 'state' ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<div class="wpcable-chart-card">
				<h2><?php esc_html_e( 'Recorded activity (valid dates only)', 'wpcable' ); ?></h2>
				<?php if ( empty( $m['activity'] ) ) : ?>
					<p class="description"><?php esc_html_e( 'No valid last-activity dates to chart. Invalid or sentinel timestamps are excluded, not plotted as 1970.', 'wpcable' ); ?></p>
				<?php else : ?>
					<?php
					$act_max = 1;
					foreach ( $m['activity'] as $bucket ) {
						$act_max = max( $act_max, (int) $bucket['count'] );
					}
					foreach ( $m['activity'] as $bucket ) {
						wpcable_meter( $bucket['label'], $bucket['count'], $act_max, 'activity' );
					}
					?>
				<?php endif; ?>
			</div>
			<div class="wpcable-chart-card">
				<h2><?php esc_html_e( 'Local workspace mix', 'wpcable' ); ?></h2>
				<?php wpcable_meter( __( 'Imported projects', 'wpcable' ), $m['imported'], $max, 'ok' ); ?>
				<?php wpcable_meter( __( 'Published (filter)', 'wpcable' ), $m['published'], $max, 'state' ); ?>
				<?php wpcable_meter( __( 'Completed (filter)', 'wpcable' ), $m['completed'], $max, 'ok' ); ?>
				<?php wpcable_meter( __( 'Lost (local heuristic)', 'wpcable' ), $m['lost'], $max, 'warn' ); ?>
				<?php wpcable_meter( __( 'Clients', 'wpcable' ), $m['clients'], $max ); ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Horizontal meter used on Overview charts.
 *
 * @param string $label Label.
 * @param int    $value Count.
 * @param int    $max   Scale.
 * @param string $tone  CSS tone.
 * @return void
 */
function wpcable_meter( $label, $value, $max, $tone = '' ) {
	$value = (int) $value;
	$max   = max( 1, (int) $max );
	$pct   = min( 100, round( ( $value / $max ) * 100 ) );
	?>
	<div class="wpcable-meter">
		<span class="wpcable-meter__label"><?php echo esc_html( $label ); ?></span>
		<span class="wpcable-meter__bar" title="<?php echo esc_attr( (string) $value ); ?>">
			<span class="wpcable-meter__fill<?php echo $tone ? ' wpcable-meter__fill--' . esc_attr( $tone ) : ''; ?>" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span>
		</span>
		<span class="wpcable-meter__value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
	</div>
	<?php
}
