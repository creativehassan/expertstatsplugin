<?php
/**
 * Tasks workspace (Option B): server-side pagination, filters, drawer, collapsed notes.
 *
 * @package wpcable
 *
 * @var array $color_flags
 * @var array $counts
 * @var array $account
 * @var array $health
 * @var int   $tx_count
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$profile_completed = wpcable_account_detail( $account, 'completed_tasks_count', null );
$profile_tasks     = wpcable_account_detail( $account, 'tasks_count', null );
$financial_ok      = $tx_count > 0;
?>
<div class="wrap wpcable_wrap wpcable-workspace wpcable-workspace--tasks" data-workspace="tasks">
	<h1><?php esc_html_e( 'Projects workspace', 'wpcable' ); ?></h1>
	<p class="wpcable-lede">
		<?php esc_html_e( 'Imported Codeable projects, paginated on the server. Profile totals from users/me are a different scope than these local rows. Codeable access stays read-only; only your local notes and flags are saved here.', 'wpcable' ); ?>
	</p>

	<div class="wpcable-kpi-row">
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Profile completed', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo null === $profile_completed || '' === $profile_completed ? '—' : esc_html( (string) $profile_completed ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'users/me completed_tasks_count — not the local table', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Imported rows', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value" data-kpi="imported"><?php echo esc_html( number_format_i18n( (int) $counts['imported'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'COUNT(*) local codeable_tasks — matches All', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Published (filter)', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value" data-kpi="published"><?php echo esc_html( number_format_i18n( isset( $counts['filter_published'] ) ? (int) $counts['filter_published'] : (int) $counts['published'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'state = published AND flag ≠ lost — same SQL as the Published button', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Completed (filter)', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value" data-kpi="completed"><?php echo esc_html( number_format_i18n( isset( $counts['filter_completed'] ) ? (int) $counts['filter_completed'] : (int) $counts['flag_completed'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'state OR local flag = completed — same SQL as the Completed button', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Lost (filter)', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value" data-kpi="lost"><?php echo esc_html( number_format_i18n( isset( $counts['filter_lost'] ) ? (int) $counts['filter_lost'] : (int) $counts['flag_lost'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'state OR local flag = lost. Heuristic, not hire proof. Refresh may rewrite flags; page views do not.', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi <?php echo $financial_ok ? '' : 'wpcable-kpi--unavailable'; ?>">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Transactions', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo $financial_ok ? esc_html( number_format_i18n( (int) $tx_count ) ) : esc_html__( 'Missing', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__meta"><?php echo $financial_ok ? esc_html__( 'Local money rows', 'wpcable' ) : esc_html__( 'Unavailable — not $0 revenue', 'wpcable' ); ?></span>
		</div>
	</div>

	<div class="wpcable-toolbar">
		<div class="wpcable-filters" role="group" aria-label="<?php esc_attr_e( 'Status filters', 'wpcable' ); ?>">
			<button type="button" class="button button-primary wpcable-filter is-active" data-state="all" data-filter-key="all"><?php esc_html_e( 'All', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-state="published" data-filter-key="published"><?php esc_html_e( 'Published', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-completed="1" data-filter-key="completed"><?php esc_html_e( 'Completed', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-paid="1" data-filter-key="paid"><?php esc_html_e( 'Paid', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-estimated="1" data-filter-key="estimated"><?php esc_html_e( 'Estimated', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-lost="1" data-filter-key="lost"><?php esc_html_e( 'Lost', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-preferred="1" data-filter-key="preferred"><?php esc_html_e( 'Preferred', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-has_notes="1" data-filter-key="with_notes"><?php esc_html_e( 'Has notes', 'wpcable' ); ?></button>
		</div>
		<div class="wpcable-toolbar__right">
			<label class="wpcable-check">
				<input type="checkbox" id="wpcable-group-clients" />
				<?php esc_html_e( 'Group by client', 'wpcable' ); ?>
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Search client or project', 'wpcable' ); ?></span>
				<input type="search" id="wpcable-task-search" class="wpcable-search" placeholder="<?php esc_attr_e( 'Search name, title, id, notes…', 'wpcable' ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Per page', 'wpcable' ); ?>
				<select id="wpcable-per-page">
					<option value="25">25</option>
					<option value="50">50</option>
					<option value="100">100</option>
				</select>
			</label>
		</div>
	</div>
	<p class="description wpcable-lost-note">
		<?php esc_html_e( 'KPI cards use the same SQL as the matching filter button. State and local flag are different columns; a row can be state=published and flag=lost. Lost is a local heuristic (hidden / canceled / stale / private workroom), not proof another expert was hired. Click Refresh to run a read-only Codeable GET queue; ordinary page views do not start one and do not rewrite flags.', 'wpcable' ); ?>
	</p>
	<p class="wpcable-sync-banner" id="wpcable-sync-banner" hidden>
		<?php esc_html_e( 'A read-only Refresh is in progress. Filter counts can change as pages import. This banner is source state, not a completed import.', 'wpcable' ); ?>
	</p>

	<div class="wpcable-workspace-layout">
		<div class="wpcable-table-card">
			<table class="widefat striped wpcable-tasks-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Client', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Last activity', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Project', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Value', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Title', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Status / flags', 'wpcable' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'wpcable' ); ?></th>
					</tr>
				</thead>
				<tbody id="wpcable-task-rows">
					<tr class="wpcable-empty-row"><td colspan="7"><?php esc_html_e( 'Loading projects…', 'wpcable' ); ?></td></tr>
				</tbody>
			</table>
			<div class="wpcable-pagination" id="wpcable-task-pagination"></div>
		</div>

		<aside class="wpcable-drawer" id="wpcable-task-drawer" hidden>
			<div class="wpcable-drawer__inner">
				<button type="button" class="button-link wpcable-drawer__close"><?php esc_html_e( 'Close', 'wpcable' ); ?></button>
				<div id="wpcable-task-drawer-body"></div>
			</div>
		</aside>
	</div>

	<div class="notes-editor-layer" style="display:none">
		<div class="notes-editor">
			<h2 class="task-title"></h2>
			<p class="description"><?php esc_html_e( 'These notes stay in WordPress. They are never sent to Codeable.', 'wpcable' ); ?></p>
			<textarea></textarea>
			<div class="buttons">
				<button type="button" class="button btn-cancel"><?php esc_html_e( 'Cancel', 'wpcable' ); ?></button>
				<button type="button" class="button button-primary btn-save"><?php esc_html_e( 'Save local notes', 'wpcable' ); ?></button>
			</div>
		</div>
	</div>

	<?php codeable_last_fetch_info(); ?>
</div>
<style>
<?php
foreach ( $color_flags as $flag => $info ) {
	printf(
		'.flag-%1$s,[data-flag="%1$s"]{--color:%2$s;--bgcolor:%2$s10;}',
		esc_attr( $flag ),
		esc_attr( $info['color'] )
	);
}
?>
</style>
<script>
window.wpcable=window.wpcable||{};
wpcable.update_task_nonce=<?php echo wp_json_encode( wp_create_nonce( 'wpcable-task' ) ); ?>;
wpcable.colorFlags=<?php echo wp_json_encode( $color_flags ); ?>;
</script>
