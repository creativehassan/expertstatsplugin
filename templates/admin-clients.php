<?php
/**
 * Clients workspace (Option A): client_id join to imported projects.
 *
 * @package wpcable
 *
 * @var array $account
 * @var array $counts
 * @var array $health
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$avatar = wpcable_account_avatar_url( $account );
?>
	<div class="wpcable-kpi-row">
		<div class="wpcable-kpi wpcable-kpi--profile">
			<?php if ( $avatar ) : ?>
				<img class="wpcable-kpi__avatar" src="<?php echo esc_url( $avatar ); ?>" alt="" />
			<?php endif; ?>
			<div>
				<span class="wpcable-kpi__label"><?php esc_html_e( 'Expert profile', 'wpcable' ); ?></span>
				<span class="wpcable-kpi__value"><?php echo esc_html( trim( wpcable_account_detail( $account, 'first_name' ) . ' ' . wpcable_account_detail( $account, 'last_name' ) ) ); ?></span>
				<span class="wpcable-kpi__meta">
					<?php
					printf(
						/* translators: 1: role, 2: completed, 3: profile tasks */
						esc_html__( '%1$s · profile completed %2$s · profile projects %3$s', 'wpcable' ),
						esc_html( wpcable_account_detail( $account, 'role' ) ),
						esc_html( (string) wpcable_account_detail( $account, 'completed_tasks_count', '—' ) ),
						esc_html( (string) wpcable_account_detail( $account, 'tasks_count', '—' ) )
					);
					?>
				</span>
			</div>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Imported projects', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( (int) $counts['imported'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'Local database · Tasks page scope', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi wpcable-kpi--unavailable">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Financial import', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php esc_html_e( 'Unavailable', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'Not $0. Hide revenue until transactions succeed.', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Per-source sync', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__meta">
				<?php esc_html_e( 'Profile, tasks, and transactions are tracked separately. Missing success times are not invented.', 'wpcable' ); ?>
			</span>
		</div>
	</div>

	<div class="wpcable-workspace-layout">
		<section class="wpcable-table-card">
			<div class="wpcable-toolbar">
				<input type="search" id="wpcable-client-search" class="wpcable-search" placeholder="<?php esc_attr_e( 'Search name or client id…', 'wpcable' ); ?>" />
				<div class="wpcable-filters">
					<button type="button" class="button button-primary wpcable-client-filter is-active" data-filter="all"><?php esc_html_e( 'All', 'wpcable' ); ?></button>
					<button type="button" class="button wpcable-client-filter" data-filter="completed"><?php esc_html_e( 'Completed ≥ 1', 'wpcable' ); ?></button>
					<button type="button" class="button wpcable-client-filter" data-filter="preferred"><?php esc_html_e( 'Preferred', 'wpcable' ); ?></button>
					<button type="button" class="button wpcable-client-filter" data-filter="stale"><?php esc_html_e( 'Stale > 180d', 'wpcable' ); ?></button>
					<button type="button" class="button wpcable-client-filter" data-filter="lost"><?php esc_html_e( 'Lost only', 'wpcable' ); ?></button>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Lost is a local flag (hidden, canceled, stale, or private workroom), not proof another expert was hired.', 'wpcable' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Client', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Projects', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Flags', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Last activity', 'wpcable' ); ?></th>
					</tr>
				</thead>
				<tbody id="wpcable-client-rows">
					<tr class="wpcable-empty-row"><td colspan="4"><?php esc_html_e( 'Loading clients…', 'wpcable' ); ?></td></tr>
				</tbody>
			</table>
			<div class="wpcable-pagination" id="wpcable-client-pagination"></div>
		</section>

		<aside class="wpcable-drawer" id="wpcable-client-drawer" hidden>
			<div class="wpcable-drawer__inner">
				<button type="button" class="button-link wpcable-drawer__close"><?php esc_html_e( 'Close', 'wpcable' ); ?></button>
				<div id="wpcable-client-drawer-body">
					<p class="description"><?php esc_html_e( 'Select a client to see their imported projects, evidence, and a review-only outreach draft.', 'wpcable' ); ?></p>
				</div>
			</div>
		</aside>
	</div>
