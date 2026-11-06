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
			<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( (int) $counts['imported'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'Local codeable_tasks rows (all filters)', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Published', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( (int) $counts['published'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'Local state = published', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Completed flag', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( (int) $counts['flag_completed'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'Local flag = completed', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Lost (local)', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo esc_html( number_format_i18n( (int) $counts['flag_lost'] ) ); ?></span>
			<span class="wpcable-kpi__meta"><?php esc_html_e( 'Heuristic — not proof nobody else was hired', 'wpcable' ); ?></span>
		</div>
		<div class="wpcable-kpi <?php echo $financial_ok ? '' : 'wpcable-kpi--unavailable'; ?>">
			<span class="wpcable-kpi__label"><?php esc_html_e( 'Transactions', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__value"><?php echo $financial_ok ? esc_html( number_format_i18n( (int) $tx_count ) ) : esc_html__( 'Missing', 'wpcable' ); ?></span>
			<span class="wpcable-kpi__meta"><?php echo $financial_ok ? esc_html__( 'Local money rows', 'wpcable' ) : esc_html__( 'Unavailable — not $0 revenue', 'wpcable' ); ?></span>
		</div>
	</div>

	<div class="wpcable-toolbar">
		<div class="wpcable-filters" role="group" aria-label="<?php esc_attr_e( 'Status filters', 'wpcable' ); ?>">
			<button type="button" class="button button-primary wpcable-filter is-active" data-state="all"><?php esc_html_e( 'All', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-state="published"><?php esc_html_e( 'Published', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-completed="1"><?php esc_html_e( 'Completed', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-paid="1"><?php esc_html_e( 'Paid', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-estimated="1"><?php esc_html_e( 'Estimated', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-lost="1"><?php esc_html_e( 'Lost', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-preferred="1"><?php esc_html_e( 'Preferred', 'wpcable' ); ?></button>
			<button type="button" class="button wpcable-filter" data-has_notes="1"><?php esc_html_e( 'Has notes', 'wpcable' ); ?></button>
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
		<?php esc_html_e( 'Lost uses the local flag from hidden, canceled, stale, or private-workroom heuristics. It is not global hire proof.', 'wpcable' ); ?>
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
