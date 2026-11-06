<?php
/**
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function wpcable_register_menu_page() {
	$icon_code = file_get_contents( WPCABLE_ASSESTS_DIR . '/images/ca-logo.svg' );

	add_menu_page(
		__( 'Codeable Stats', 'wpcable' ),
		__( 'Codeable Stats', 'wpcable' ),
		'manage_options',
		'codeable_transcactions_stats',
		'codeable_transcactions_stats_cb',
		'data:image/svg+xml;base64,' . base64_encode( $icon_code ),
		2
	);
}
add_action( 'admin_menu', 'wpcable_register_menu_page' );

function codeable_transcactions_stats_cb() {

	codeable_page_requires_login( __( 'Codeable Stats', 'wpcable' ) );
	codeable_admin_notices();

	add_thickbox();

	$account_details   = wpcable_get_account_details();
	$average_task_size = get_option( 'wpcable_average' );
	$balance           = get_option( 'wpcable_balance' );
	$revenue           = get_option( 'wpcable_revenue' );

	$wpcable_stats = new wpcable_stats();
	$wpcable_tasks = new wpcable_tasks();
	$local_counts  = $wpcable_tasks->get_state_counts();
	$tx_count      = $wpcable_stats->get_transaction_count();

	$get_first_task = $wpcable_stats->get_first_task();
	$get_last_task  = $wpcable_stats->get_last_task();

	$first_date_ok = ! empty( $get_first_task['dateadded'] ) && wpcable_is_valid_datetime( $get_first_task['dateadded'] );
	$last_date_ok  = ! empty( $get_last_task['dateadded'] ) && wpcable_is_valid_datetime( $get_last_task['dateadded'] );
	$financial_available = $wpcable_stats->has_transaction_data() && $first_date_ok && $last_date_ok;

	$first_day = $first_month = $first_year = $last_day = $last_month = $last_year = '';
	if ( $first_date_ok ) {
		$first_day   = date( 'd', strtotime( $get_first_task['dateadded'] ) );
		$first_month = date( 'm', strtotime( $get_first_task['dateadded'] ) );
		$first_year  = date( 'Y', strtotime( $get_first_task['dateadded'] ) );
	}
	if ( $last_date_ok ) {
		$last_day    = date( 'd', strtotime( $get_last_task['dateadded'] ) );
		$last_month  = date( 'm', strtotime( $get_last_task['dateadded'] ) );
		$last_year   = date( 'Y', strtotime( $get_last_task['dateadded'] ) );
	}

	if ( ! $financial_available ) {
		$_GET['date_from'] = '';
		$_GET['date_to']   = '';
		$from_day = $from_month = $from_year = $to_day = $to_month = $to_year = '';
	} elseif ( ! isset( $_GET['date_from'] ) ) {
		$_GET['date_from'] = $first_year . '-' . $first_month;
		$from_day          = $first_day;
		$from_month        = $first_month;
		$from_year         = $first_year;
	} else {
		$from_day   = '01';
		$from_month = date( 'm', strtotime( $_GET['date_from'] . '-01' ) );
		$from_year  = date( 'Y', strtotime( $_GET['date_from'] . '-01' ) );
	}

	if ( $financial_available && ! isset( $_GET['date_to'] ) ) {
		$_GET['date_to'] = $last_year . '-' . $last_month;
		$to_day          = $last_day;
		$to_month        = $last_month;
		$to_year         = $last_year;
	} elseif ( $financial_available ) {
		$to_day   = date( 't', strtotime( $_GET['date_to'] . '-01' ) );
		$to_month = date( 'm', strtotime( $_GET['date_to'] . '-01' ) );
		$to_year  = date( 'Y', strtotime( $_GET['date_to'] . '-01' ) );
	}

	$is_compare = '';

	if ( isset( $_GET['compare_date_from'] ) ) {
		$compare_from_day   = '01';
		$compare_from_month = date( 'm', strtotime( $_GET['compare_date_from'] . '-01' ) );
		$compare_from_year  = date( 'Y', strtotime( $_GET['compare_date_from'] . '-01' ) );
		$is_compare         = 'is_compare';
	}

	if ( isset( $_GET['compare_date_to'] ) ) {
		$compare_to_day   = date( 't', strtotime( $_GET['compare_date_to'] . '-01' ) );
		$compare_to_month = date( 'm', strtotime( $_GET['compare_date_to'] . '-01' ) );
		$compare_to_year  = date( 'Y', strtotime( $_GET['compare_date_to'] . '-01' ) );
		$is_compare       = 'is_compare';
	}

	if ( ! isset( $_GET['chart_display_method'] ) ) {
		$_GET['chart_display_method'] = 'months';
		$chart_display_method         = 'months';
	} else {
		$chart_display_method = $_GET['chart_display_method'];
	}

	// get initial stats — skip date math when transactions are missing (no fake 1970 / $0).
	$get_all_stats = array();
	if ( $financial_available ) {
		$get_all_stats = $wpcable_stats->get_all_stats( $from_day, $from_month, $from_year, $to_day, $to_month, $to_year, $chart_display_method );
		foreach ( $get_all_stats as $stats_key => $stats_data ) {
			${$stats_key} = $stats_data;
		}
	}

	// get compare stats
	if ( $financial_available && $is_compare ) {
		$get_compare_stats = $wpcable_stats->get_all_stats( $compare_from_day, $compare_from_month, $compare_from_year, $compare_to_day, $compare_to_month, $compare_to_year, $chart_display_method );

		foreach ( $get_compare_stats as $compare_stats_key => $compare_stats_data ) {
			${'compare_' . $compare_stats_key} = $compare_stats_data;
		}
	}

	$all_averages    = $financial_available
		? $wpcable_stats->get_dates_average( $first_day, $first_month, $first_year, $last_day, $last_month, $last_year )
		: array();
	$wpcable_clients = new wpcable_clients();
	$clients_data    = $wpcable_clients->get_clients();

	?>

	<div class="wrap cable_stats_wrap">
		<h1><?php echo __( 'Codeable Stats', 'wpcable' ); ?></h1>

		<div class="clearfix spacer"></div>

		<div class="flex-row first-stats-row">

			<div class="box user-info">
				<div class="avatar">
					<?php
					$avatar_url = wpcable_account_avatar_url( $account_details );
					if ( $avatar_url ) :
						?>
						<img class="round" src="<?php echo esc_url( $avatar_url ); ?>"/>
						<?php
					endif;
					?>
					<div class="rating" data-score="<?php echo esc_attr( wpcable_account_stat( $account_details, 'avg_rating', '' ) ); ?>"></div>
				</div>
				<div class="details">
					<div class="codeable-logo">
						<?php echo '<img src="' . esc_url( plugins_url( 'assets/images/codeable-full.png', dirname( __FILE__ ) ) ) . '" > '; ?>
					</div>
					<span class="name"><?php echo esc_html( wpcable_account_detail( $account_details, 'first_name' ) . ' ' . wpcable_account_detail( $account_details, 'last_name' ) ); ?></span>
					<span class="label role"><?php echo esc_html( wpcable_account_detail( $account_details, 'role' ) ); ?></span>
					<span class="user-id"><?php echo esc_html__( 'ID', 'wpcable' ) . ': <a href="https://app.codeable.io/tasks/new?preferredContractor=' . esc_attr( wpcable_account_detail( $account_details, 'id' ) ) . '" target="_blank" title="' . esc_attr__( 'Direct hire link', 'wpcable' ) . '">' . esc_html( wpcable_account_detail( $account_details, 'id' ) ); ?></a></span>
					<span class="wpcable-source-chip"><?php esc_html_e( 'Source: users/me profile', 'wpcable' ); ?></span>
				</div>
			</div>

			<div class="box completed-tasks">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo esc_html__( 'Profile completed', 'wpcable' ); ?></div>
						<div class="value"><?php echo esc_html( wpcable_account_detail( $account_details, 'completed_tasks_count', '—' ) ); ?></div>
					</div>
					<div class="footerdata">
						<span class="label"><?php echo esc_html__( 'Profile tasks_count', 'wpcable' ); ?></span>:
						<span class="value">
							<?php echo esc_html( wpcable_account_detail( $account_details, 'tasks_count', '—' ) ); ?>
						</span><br/>
						<span class="label"><?php echo esc_html__( 'Imported local rows', 'wpcable' ); ?></span>:
						<span class="value"><?php echo esc_html( number_format_i18n( (int) $local_counts['imported'] ) ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Profile counts and imported project rows are different scopes. Do not treat them as one number.', 'wpcable' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_tasks' ) ); ?>">
								<?php esc_html_e( 'Open the projects workspace', 'wpcable' ); ?>
							</a>
							|
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_clients' ) ); ?>">
								<?php esc_html_e( 'Clients', 'wpcable' ); ?>
							</a>
						</p>
					</div>
				</div>
			</div>

			<div class="box balance">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo esc_html__( 'Balance', 'wpcable' ); ?></div>
						<div class="value">
							<?php
							if ( $financial_available && is_numeric( $balance ) ) {
								echo esc_html( '$' . wpcable_money( $balance ) );
							} else {
								echo '<span class="wpcable-kpi--unavailable">' . esc_html__( 'Unavailable', 'wpcable' ) . '</span>';
							}
							?>
						</div>
					</div>
					<?php if ( ! $financial_available ) : ?>
						<p class="description"><?php esc_html_e( 'Money transactions are not imported. This is not $0.', 'wpcable' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="box total-revenue">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo esc_html__( 'Total Revenue', 'wpcable' ); ?></div>
						<div class="value">
							<?php
							if ( $financial_available && is_numeric( $revenue ) ) {
								echo esc_html( '$' . wpcable_money( $revenue ) );
							} else {
								echo '<span class="wpcable-kpi--unavailable">' . esc_html__( 'Unavailable', 'wpcable' ) . '</span>';
							}
							?>
						</div>
					</div>
					<div class="footerdata">
						<?php if ( $financial_available && ! empty( $get_first_task['dateadded'] ) ) : ?>
							<span class="label"><?php echo esc_html__( 'Since', 'wpcable' ); ?></span>:
							<span class="value"><?php echo esc_html( wpcable_date( $get_first_task['dateadded'] ) ); ?></span>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'No transaction date range — hiding 1970/empty defaults.', 'wpcable' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</div>
		<!-- end row  -->

		<?php if ( ! $financial_available ) : ?>
			<div class="wpcable-financial-empty">
				<h2><?php esc_html_e( 'Money charts are hidden', 'wpcable' ); ?></h2>
				<p>
					<?php esc_html_e( 'Transactions have not imported into the local database. Showing $0.00 or January 1970 would be misleading, so revenue, fees, monthly charts, and the transaction-based client table stay hidden until this source succeeds.', 'wpcable' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_tasks' ) ); ?>"><?php esc_html_e( 'Go to projects workspace', 'wpcable' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_clients' ) ); ?>"><?php esc_html_e( 'Open clients (from imported projects)', 'wpcable' ); ?></a>
				</p>
			</div>
		<?php else : ?>

		<!--  Row  -->
		<div class="flex-row second-stats-row">

			<div class="box conversions">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo __( 'Conversions', 'wpcable' ); ?></div>
					</div>
					<table class="stats_table">
						<tbody>
						<tr>
							<td>
								<span class="label"><?php echo __( 'Month conversion', 'wpcable' ); ?></span>
							</td>
							<td>
								<span class="value"><?php echo esc_html( wpcable_account_stat( $account_details, 'estimated_completed_conversion_1_month', 0 ) * 100 ); ?>
									%</span>
							</td>
						</tr>
						<tr>
							<td>
								<span class="label"><?php echo __( '6 Month conversion', 'wpcable' ); ?></span>
							</td>
							<td>
								<span class="value"><?php echo esc_html( wpcable_account_stat( $account_details, 'estimated_completed_conversion_6_months', 0 ) * 100 ); ?>
									%</span>
							</td>
						</tr>
						<tr>
							<td>
								<span class="label"><?php echo __( 'Average task', 'wpcable' ); ?></span>
							</td>
							<td>
								<span class="value">$<?php echo wpcable_money( $average_task_size ); ?></span>
							</td>
						</tr>
						<tr>
							<td>
								<span class="label"><?php echo __( 'Average including subtasks', 'wpcable' ); ?></span>
							</td>
							<td>
								<span class="value">$<?php echo wpcable_money( $all_averages['revenue'] ); ?></span>
							</td>
						</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="box monthly-averages">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo __( 'Monthly Averages', 'wpcable' ); ?></div>
					</div>
					<table class="stats_table">
						<tbody>
						<tr>
							<td class="firstcol">
								<span class="label"><?php echo __( 'Revenue', 'wpcable' ); ?></span>
							</td>
							<td class="text-right">
								<span class="value">$<?php echo wpcable_money( $averages['revenue'] ); ?></span>

							</td>
							<?php if ( $is_compare ) { ?>
								<td class="text-right">
								  <span class="value <?php echo wpcable_compare_values( $compare_averages['revenue'], $averages['revenue'] ); ?>">
									$<?php echo wpcable_money( $compare_averages['revenue'] ); ?>
								  </span>
								</td>
							<?php } ?>
						</tr>
						<tr>
							<td class="firstcol">
								<span class="label"><?php echo __( 'Total', 'wpcable' ); ?></span>
							</td>
							<td class="text-right">
								<span class="value">$<?php echo wpcable_money( $averages['total_cost'] ); ?></span>

							</td>
							<?php if ( $is_compare ) { ?>
								<td class="text-right">
								  <span class="value <?php echo wpcable_compare_values( $compare_averages['total_cost'], $averages['total_cost'] ); ?>">
									$<?php echo wpcable_money( $compare_averages['total_cost'] ); ?>
								  </span>
								</td>
							<?php } ?>
						</tr>
						<tr>
							<td class="firstcol">
								<span class="label"><?php echo __( 'Fees', 'wpcable' ); ?></span>
							</td>
							<td class="text-right">
								<span class="value">$<?php echo wpcable_money( $averages['contractor_fee'] ); ?></span>
							</td>
							<?php if ( $is_compare ) { ?>
								<td class="text-right">
								  <span class="value <?php echo wpcable_compare_values( $compare_averages['contractor_fee'], $averages['contractor_fee'] ); ?>">
									$<?php echo wpcable_money( $compare_averages['contractor_fee'] ); ?>
								  </span>
								</td>
								<?php } ?>
						</tr>
						</tbody>
					</table>
					<small><?php echo __( 'The above are from', 'wpcable' ) . ' ' . $from_month . '-' . $from_year . ' ' . __( 'to', 'wpcable' ) . ' ' . $to_month . '-' . $to_year; ?></small>

					<?php if ( $is_compare ) { ?>
					<span class="newline">
					  <small><?php echo __( 'Compared from', 'wpcable' ) . ' ' . $compare_from_month . '-' . $compare_from_year . ' ' . __( 'to', 'wpcable' ) . ' ' . $compare_to_month . '-' . $compare_to_year; ?></small>
					</span>
					<?php } ?>
				</div>
			</div>

		</div>


		<div class="flex-row box date-filters">
			<form method="get" id="date_form" data-start-year="<?php echo $first_year; ?>"
				  data-end-year="<?php echo $last_year; ?>">
				<input type="hidden" name="page" value="codeable_transcactions_stats"/>

				<div class="section">
				  <img class="compareicon" src="<?php echo esc_url( plugins_url( 'assets/images/calendar-add.svg', dirname( __FILE__ ) ) ); ?>" width="16" height="16" alt="<?php echo __( 'compare dates', 'wpcable' ); ?>" title="<?php echo __( 'compare dates', 'wpcable' ); ?>">
				</div>
				<div class="section">
					<div class="datefield">
					  <label class="label" for="date_from"><?php echo __( 'Start date', 'wpcable' ); ?></label>
					  <input class="datepicker" type="text" id="date_from" name="date_from"
							 value="<?php echo $_GET['date_from']; ?>"
							 data-icon="<?php echo WPCABLE_URI . '/assets/images/icon_datepicker_blue.png'; ?>">
					</div>

					<div class="datefield compareto <?php echo $is_compare; ?>">
					  <label class="label" for="compare_date_from"><?php echo __( 'Compare Start date', 'wpcable' ); ?></label>
					  <input class="datepicker" type="text" id="compare_date_from" name="compare_date_from"
						   value="<?php echo $_GET['compare_date_from']; ?>"
						   data-icon="<?php echo WPCABLE_URI . '/assets/images/icon_datepicker_blue.png'; ?>">
					</div>
				</div>


				<div class="section">

					<div class="datefield">
					  <label class="label" for="date_to"><?php echo __( 'End date', 'wpcable' ); ?></label>
					  <input class="datepicker" type="text" id="date_to" name="date_to"
							 value="<?php echo $_GET['date_to']; ?>"
							 data-icon="<?php echo WPCABLE_URI . '/assets/images/icon_datepicker_blue.png'; ?>">
					</div>

					<div class="datefield compareto <?php echo $is_compare; ?>">
					  <label class="label" for="compare_date_to"><?php echo __( 'Compare End date', 'wpcable' ); ?></label>
					  <input class="datepicker" type="text" id="compare_date_to" name="compare_date_to"
						   value="<?php echo $_GET['compare_date_to']; ?>"
						   data-icon="<?php echo WPCABLE_URI . '/assets/images/icon_datepicker_blue.png'; ?>">
					</div>
				</div>


				<div class="section">
					<label class="label"
						   for="chart_display_method"><?php echo __( 'Chart display', 'wpcable' ); ?></label>:&nbsp;&nbsp;
					<?php echo __( 'Months', 'wpcable' ); ?>&nbsp;<input type="radio" name="chart_display_method"
																		 value="months" <?php echo( $chart_display_method == 'months' ? 'checked="checked"' : '' ); ?> />
					<?php echo __( 'Days', 'wpcable' ); ?>&nbsp;<input type="radio" name="chart_display_method"
																	   value="days" <?php echo( $chart_display_method == 'days' ? 'checked="checked"' : '' ); ?> />
				</div>


				<button class="set-date button button-primary"><?php echo __( 'Set date', 'wpcable' ); ?></button>

			</form>
		</div>


		<div class="clearfix spacer"></div>

		<div class="row bests">
			<div class="col-md-12">
				<h2 class="text-center"><?php echo __( 'Your Highscore for This Range' ); ?></h2>
			</div>

			<div class="col-md-4 text-center">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo $chart_display_method == 'days' ? __( 'Best Day', 'wpcable' ) : __( 'Best Month' ); ?></div>
						<span class="value"><?php echo wordwrap( $max_month_totals_key[0], 4, '-', true ); ?></span>
						<?php
						if ( $is_compare ) {
							?>
							<span class="value newline small"><?php echo wordwrap( $compare_max_month_totals_key[0], 4, '-', true ); ?></span>
							<?php
						}
						?>
					</div>
				</div>
			</div>
			<div class="col-md-4 text-center">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo __( 'Revenue Best', 'wpcable' ); ?></div>
						<span class="value">$<?php echo wpcable_money( $max_month_totals['revenue'] ); ?></span>
						<?php
						if ( $is_compare ) {
							?>
							<span class="value newline small">$<?php echo wpcable_money( $compare_max_month_totals['revenue'] ); ?></span>
							<?php
						}
						?>
					</div>
				</div>
			</div>
			<div class="col-md-4 text-center">
				<div class="column_inner">
					<div class="maindata">
						<div class="label"><?php echo __( 'Revenue', 'wpcable' ); ?></div>
						<span class="value">$<?php echo wpcable_money( $all_month_totals['revenue'] ); ?></span>
						<?php
						if ( $is_compare ) {
							?>
							<span class="value newline small">$<?php echo wpcable_money( $compare_all_month_totals['revenue'] ); ?></span>
							<?php
						}
						?>
					</div>
				</div>
			</div>
		</div>

		<div class="clearfix spacer"></div>

		<div class="row">
			<div class="col-md-12">
				<div class="whitebox">
					<div id="chart_wrap">


					</div>
				</div>
			</div>
		</div>

		<div class="clearfix spacer"></div>

		<div class="row">

			<div class="col-md-12">
				<div class="whitebox">
					<div id="tasks_per_month_chart">


					</div>
				</div>
			</div>

		</div>

		<div class="clearfix spacer"></div>

		<div class="flex-row third-stats-row">

			<div class="box">
				<div id="amounts_range_chart">
				</div>
			</div>

			<div class="box">
				<div id="preferred_chart">
				</div>
			</div>

			<div class="box">
				<div id="tasks_type">
				</div>
			</div>

		</div>


		<div class="row clients_row">
			<div class="col-md-12">

				<h3><?php echo __( 'Clients Data', 'wpcable' ); ?>
					<small><em>(<?php echo __( 'all time, order by revenue', 'wpcable' ); ?>)</em></small>
				</h3>

				<div class="whitebox">
					<div id="clients_wrap">

						<table class="datatable widefat fixed striped posts" id="clients_table">
							<thead>
								<th class="avatar">Avatar</th>
								<th class="client_name">Name</th>
								<th>Last Login</th>
								<th>Total Tasks</th>
								<th>Tasks</th>
								<th>Subtasks</th>
								<th>Average per task</th>
								<th>Revenue</th>
							</thead>
							<tbody>
							<?php

							if ( is_array( $clients_data['clients'] ) ) {

								foreach ( $clients_data['clients'] as $client_id => $client ) {

									if ( $client['client_id'] == '' ) {
										continue;
									}

									?>
									<tr id="client_<?php echo $client['client_id']; ?>">
										<td class="avatar"><img class="round" src="<?php echo $client['avatar']; ?>"/>
										</td>
										<td class="client_name">
											<a href="#TB_inline?width=1000&height=550&inlineId=client_info_<?php echo $client_id; ?>"
											   class="thickbox"><?php echo $client['full_name']; ?></a>
											<div style="display:none;" id="client_info_<?php echo $client_id; ?>">

												<h2><?php echo $client['full_name']; ?></h2>

												<div class="clearfix spacer"></div>

												<table class="datatable datatable_inner widefat fixed striped posts">
													<thead>
													<th>Task</th>
													<th>Paid</th>
													<th>Revenue</th>
													<th>Fee</th>
													<th>Refund</th>
													<th>Pref.</th>
													<th>Type</th>
													<th>Pro</th>
													</thead>
													<tbody>
													<?php

													foreach ( $client['transactions'] as $tron ) {
														?>
														<tr class="<?php echo( $tron['is_refund'] == 1 ? 'refund' : 'complete' ); ?>">
															<td>
																<a href="https://app.codeable.io/tasks/<?php echo( $tron['parent_task_id'] > 0 ? $tron['parent_task_id'] : $tron['task_id'] ); ?>/workroom"
																   target="_blank">
																	<?php echo ( $tron['task_title'] ? $tron['task_title'] : 'Subtask of: ' . $tron['parent_task_id'] ) . ' - ' . $tron['task_id']; ?>
																</a>
															</td>
															<td><?php echo $tron['dateadded']; ?></td>
															<td><?php echo wpcable_money( $tron['revenue'] ); ?></td>
															<td><?php echo wpcable_money( $tron['fee_amount'] ); ?></td>
															<td><?php echo( $tron['is_refund'] == 0 ? 'N' : 'Y' ); ?></td>
															<td><?php echo( $tron['preferred'] == 0 ? 'N' : 'Y' ); ?></td>
															<td><?php echo $tron['task_type']; ?></td>
															<td><?php echo( $tron['pro'] == 0 ? 'N' : 'Y' ); ?></td>
														</tr>
														<?php
													}

													?>
													</tbody>
												</table>
											</div>
										</td>
										<td data-order="<?php echo $client['last_sign_in_at']; ?>"><span class="mobile_label">Last login:</span> <?php echo $client['last_sign_in_at']; ?></td>
										<td data-order="<?php echo intval( $client['total_tasks'] ); ?>"><span class="mobile_label">Total Tasks:</span>
											<a href="#TB_inline?width=1000&height=550&inlineId=client_info_<?php echo $client_id; ?>"
											   class="thickbox"><?php echo intval( $client['total_tasks'] ); ?></a></td>
										<td data-order="<?php echo intval( $client['tasks'] ); ?>"><span class="mobile_label">Tasks:</span> <?php echo intval( $client['tasks'] ); ?></td>
										<td data-order="<?php echo intval( $client['subtasks'] ); ?>"><span class="mobile_label">Subtasks:</span> <?php echo intval( $client['subtasks'] ); ?></td>
										<td data-order="<?php echo wpcable_money( $client['revenue'] / intval( $client['total_tasks'] ) ); ?>"><span class="mobile_label">Average per task:</span>
											$<?php echo wpcable_money( $client['revenue'] / intval( $client['total_tasks'] ) ); ?></td>
										<td data-order="<?php echo wpcable_money( $client['revenue'] ); ?>"><span class="mobile_label">Revenue:</span> $<?php echo wpcable_money( $client['revenue'] ); ?></td>
									</tr>
									<?php
								}
							}
							?>

							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<?php endif; ?>

	</div>

	<?php codeable_last_fetch_info(); ?>


	<?php if ( $financial_available ) : ?>
	<script>

		jQuery(function () {

			var $chart_tasks_count_json = <?php echo $chart_tasks_count_json; ?>;
			var $chart_revenue_json = <?php echo $chart_revenue_json; ?>;
			var $type_tasks_count_json = <?php echo $type_tasks_count_json; ?>;

			var $compare_chart_tasks_count_json = <?php echo ( isset( $compare_chart_tasks_count_json ) ? $compare_chart_tasks_count_json : '""' ); ?>;
			var $compare_chart_revenue_json = <?php echo ( isset( $compare_chart_revenue_json ) ? $compare_chart_revenue_json : '""' ); ?>;
			var $compare_chart_dates_json = <?php echo ( isset( $compare_chart_dates_json ) ? $compare_chart_dates_json : '""' ); ?>;
			var $compare_type_tasks_count_json = <?php echo ( isset( $compare_type_tasks_count_json ) ? $compare_type_tasks_count_json : '""' ); ?>;

			Highcharts.chart('chart_wrap', {
				exporting: {
					chartOptions: { // specific options for the exported image
						plotOptions: {
							series: {
								dataLabels: {
									enabled: true
								}
							}
						}
					},
					fallbackToExportServer: false
				},
				chart: {
					type: 'areaspline'
				},
				title: {
					text: "<?php echo $chart_display_method == 'days' ? __( 'Daily money chart', 'wpcable' ) : __( 'Monthly Money Chart', 'wpcable' ); ?>"
				},
				subtitle: {
					text: "<?php echo __( 'You Earned It!', 'wpcable' ); ?>"
				},
				xAxis: {
					categories: [<?php echo implode( ', ', $chart_categories ); ?>],
					type: 'datetime'
				},
				yAxis: {
					title: {
						text: '<?php echo __( 'Money ($)', 'wpcable' ); ?>'
					}
				},
				plotOptions: {
					line: {
						dataLabels: {
							enabled: true
						},
						enableMouseTracking: false
					}
				},
				tooltip: {
					formatter: function () {
					  if (!this.series.name.includes("Comparison")) {
						return '<b>' + this.x + '</b><br /></br >' + this.series.name + ': $<b>' + this.y + '</b><br /><?php echo __( 'Tasks', 'wpcable' ); ?>: ' + $chart_tasks_count_json[replaceAll(this.x, '-', '')];
					  } else {
						return '<b>' + $compare_chart_dates_json[this.series.data.indexOf( this.point )] + '</b><br /></br >' + this.series.name + ': $<b>' + this.y + '</b><br /><?php echo __( 'Tasks', 'wpcable' ); ?>: ' + $compare_chart_tasks_count_json[replaceAll($compare_chart_dates_json[this.series.data.indexOf( this.point )], '-', '')];
					  }
					}
				},
				series: [{
					name: '<?php echo __( 'Total Cost', 'wpcable' ); ?>',
					data: [<?php echo implode( ', ', $chart_total_cost ); ?>],
					visible: true
				}, {
					name: '<?php echo __( 'Revenue', 'wpcable' ); ?>',
					data: [<?php echo implode( ', ', $chart_revenue ); ?>]
				}, {
					name: '<?php echo __( 'Fees', 'wpcable' ); ?>',
					data: [<?php echo implode( ', ', $chart_contractor_fee ); ?>],
					visible: true
				}, {
					name: '<?php echo __( 'Average', 'wpcable' ); ?>',
					type: 'spline',
					data: [<?php echo implode( ', ', $chart_revenue_avg ); ?>],
					visible: true,
					marker: {
						enabled: false
					},
					dashStyle: 'shortdot'
				}
				<?php
				if ( $is_compare ) {
					?>
					,

				  {
					  name: '<?php echo __( 'Total Cost (Comparison)', 'wpcable' ); ?>',
					  data: [<?php echo implode( ', ', $compare_chart_total_cost ); ?>],
					  visible: true,
					  dashStyle: 'longdash'
				  }, {
					  name: '<?php echo __( 'Revenue (Comparison)', 'wpcable' ); ?>',
					  data: [<?php echo implode( ', ', $compare_chart_revenue ); ?>],
					  dashStyle: 'longdash'
				  }, {
					  name: '<?php echo __( 'Fees (Comparison)', 'wpcable' ); ?>',
					  data: [<?php echo implode( ', ', $compare_chart_contractor_fee ); ?>],
					  visible: true,
					  dashStyle: 'longdash'
				  }, {
					  name: '<?php echo __( 'Average (Comparison)', 'wpcable' ); ?>',
					  type: 'spline',
					  data: [<?php echo implode( ', ', $compare_chart_revenue_avg ); ?>],
					  visible: true,
					  marker: {
						  enabled: false
					  },
					  dashStyle: 'shortdot'
				  }

				<?php } ?>
				]
			});


			Highcharts.chart('amounts_range_chart', {
				exporting: {
					chartOptions: { // specific options for the exported image
						plotOptions: {
							series: {
								dataLabels: {
									enabled: true
								}
							}
						}
					},
					fallbackToExportServer: false
				},
				chart: {
					<?php if ( $is_compare ) { ?>

					type: 'column'

					<?php } else { ?>
					type: 'pie',
					options3d: {
						enabled: true,
						alpha: 45
					}
					<?php } ?>
				},
				title: {
					text: '<?php echo __( 'Amounts Range', 'wpcable' ); ?>'
				},
				subtitle: {
					text: '<?php echo __( 'Your tasks budget groups', 'wpcable' ); ?>'
				},
				xAxis: {
					categories: [<?php echo implode( ',', $get_available_ranges ); ?>],
					crosshair: true
				},
				plotOptions: {
					pie: {
						innerSize: 75,
						depth: 45,
						dataLabels: {
							formatter: function () {
								return this.point.name + ': ' + this.y;
							}
						}
					}
				},
				series: [{
					name: '<?php echo __( 'Tasks', 'wpcable' ); ?>',
					data: [<?php echo implode( ',', $chart_amounts_range ); ?>],
					pointPadding: 0,
					pointPlacement: 0,
					color: 'rgba(247,163,92,1)'
				}
				<?php
				if ( $is_compare ) {
					?>
					,
				{
					name: '<?php echo __( 'Tasks (Comparison)', 'wpcable' ); ?>',
					data: [<?php echo implode( ',', $compare_chart_amounts_range ); ?>],
					pointPadding: 0,
					pointPlacement: 0,
					color: 'rgba(244,91,91,.9)'
				}
				<?php } ?>
				]
			});


			Highcharts.chart('tasks_per_month_chart', {
				exporting: {
					chartOptions: { // specific options for the exported image
						plotOptions: {
							series: {
								dataLabels: {
									enabled: true
								}
							}
						}
					},
					fallbackToExportServer: false
				},
				chart: {
					type: 'column'
				},
				title: {
					text: '<?php echo $chart_display_method == 'days' ? __( 'Tasks per Day', 'wpcable' ) : __( 'Tasks per Month', 'wpcable' ); ?>'
				},
				subtitle: {
					text: '-'
				},
				xAxis: {
					categories: [
						<?php echo implode( ',', $chart_categories ); ?>
					],
					crosshair: true
				},
				yAxis: {
					min: 0,
					title: {
						text: '<?php echo __( '# of tasks', 'wpcable' ); ?>'
					}
				},
				tooltip: {
					formatter: function () {
					  if (!this.series.name.includes("Comparison")) {
						return this.x + '<br />-<br />' + this.series.name + ': <b>' + this.y + '</b><br />Revenue:<b>$' + parseFloat($chart_revenue_json[replaceAll(this.x, '-', '')]) + '</b>';
					  } else {
						return $compare_chart_dates_json[this.series.data.indexOf( this.point )] + '<br />-<br />' + this.series.name + ': <b>' + this.y + '</b><br />Revenue:<b>$' + parseFloat($compare_chart_revenue_json[replaceAll($compare_chart_dates_json[this.series.data.indexOf( this.point )], '-', '')]) + '</b>';
					  }
					}
				},
				plotOptions: {
					column: {
						borderWidth: 0,
						grouping: false,
						shadow: false,
					}
				},
				series: [{
					name: '<?php echo __( 'Tasks', 'wpcable' ); ?>',
					data: [<?php echo implode( ',', $chart_tasks_count ); ?>],
					pointPadding: 0.1,
					pointPlacement: -0.2,
					color: 'rgba(165,170,217,1)'

				},
				<?php if ( $is_compare ) { ?>
				  {
					name: '<?php echo __( 'Tasks (Comparison)', 'wpcable' ); ?>',
					data: [<?php echo implode( ',', $compare_chart_tasks_count ); ?>],
					pointPadding: 0.3,
					pointPlacement: -0.2,
					color: 'rgba(126,86,134,.9)'

				  },

				<?php } ?>
				{
					name: '<?php echo __( 'Average', 'wpcable' ); ?>',
					type: 'spline',
					data: [<?php echo implode( ',', $chart_tasks_count_avg ); ?>],
					visible: true,
					marker: {
						enabled: false
					},
					dashStyle: 'shortdot'

				}
				<?php
				if ( $is_compare ) {
					?>
					,
				  {
					name: '<?php echo __( 'Average (Comparison)', 'wpcable' ); ?>',
					type: 'spline',
					data: [<?php echo implode( ',', $compare_chart_tasks_count_avg ); ?>],
					visible: true,
					marker: {
						enabled: false
					},
					dashStyle: 'shortdot'

				  },

				<?php } ?>

				]
			});


			<?php if ( ! $is_compare ) { ?>

			// preferred semi circle donut
			Highcharts.chart('preferred_chart', {
				exporting: {
					chartOptions: {
						plotOptions: {
							series: {
								dataLabels: {
									enabled: true
								}
							}
						}
					},
					fallbackToExportServer: false
				},
				chart: {
					plotBackgroundColor: null,
					plotBorderWidth: 0,
					plotShadow: false
				},
				title: {
					text: 'Preferred vs non Preferred',
					align: 'center'
				},
				subtitle: {
				  text: '<b>Revenue</b> | Preferred: $<?php echo wpcable_money( $preferred_count['preferred']['revenue'] ); ?> | Non Preferred: $<?php echo wpcable_money( $preferred_count['nonpreferred']['revenue'] ); ?>
																 <?php
																	if ( $is_compare ) {
																		?>
						 <br /><b>Comparison</b> | Preferred: $<?php echo wpcable_money( $compare_preferred_count['preferred']['revenue'] ); ?> | Non Preferred: $<?php echo wpcable_money( $compare_preferred_count['nonpreferred']['revenue'] ); ?> <?php } ?>'
				},
				tooltip: {
					pointFormat: '{series.name}: <b> {point.y} ( {point.percentage:.1f}% )</b>'
				},
				plotOptions: {
					pie: {
						dataLabels: {
							enabled: true,
							distance: -50,
							style: {
								fontWeight: 'bold',
								color: 'white'
							}
						},
						startAngle: -90,
						endAngle: 90,
						center: ['50%', '75%']
					}
				},
				series: [{
					type: 'pie',
					name: 'Number of tasks',
					innerSize: '50%',
					data: [
						['Preferred',     <?php echo $preferred_count['preferred']['count']; ?>],
						['Non Preferred', <?php echo $preferred_count['nonpreferred']['count']; ?>],
						{
							name: 'Proprietary or Undetectable',
							y: 0.2,
							dataLabels: {
								enabled: false
							}
						}
					]
				}]
			});
			<?php } else { ?>

			// preferred comparison column chart
			Highcharts.chart('preferred_chart', {
				exporting: {
					chartOptions: {
						plotOptions: {
							series: {
								dataLabels: {
									enabled: true
								}
							}
						}
					},
					fallbackToExportServer: false
				},
				chart: {
					type: 'column',
				},
				title: {
					text: 'Preferred vs non Preferred',
					align: 'center'
				},
				subtitle: {
				  text: '<b>Revenue</b> | Preferred: $<?php echo wpcable_money( $preferred_count['preferred']['revenue'] ); ?> | Non Preferred: $<?php echo wpcable_money( $preferred_count['nonpreferred']['revenue'] ); ?>
																 <?php
																	if ( $is_compare ) {
																		?>
						 <br /><b>Comparison</b> | Preferred: $<?php echo wpcable_money( $compare_preferred_count['preferred']['revenue'] ); ?> | Non Preferred: $<?php echo wpcable_money( $compare_preferred_count['nonpreferred']['revenue'] ); ?> <?php } ?>'
				},
				xAxis: {
					categories: [
					  "# Preferred Tasks",
					  "# Non Preferred Tasks"
					],
					crosshair: true
				},
				tooltip: {
					formatter: function () {
						return this.series.name + ': <b>' + this.y + ' (' + parseFloat(this.y / this.series.options.totalcount).toFixed(2) * 100 +'%)</b>';
					}


				},
				plotOptions: {
					column: {
						borderWidth: 0,
						grouping: false,
						shadow: false,
					}
				},
				series: [{
					name: '# Tasks',
					data: [<?php echo $preferred_count['preferred']['count']; ?>, <?php echo $preferred_count['nonpreferred']['count']; ?>],
					pointPadding: 0.1,
					pointPlacement: -0.2,
					color: 'rgba(241,192,192,1)',
					totalcount: <?php echo $preferred_count['preferred']['count'] + $preferred_count['nonpreferred']['count']; ?>
				},{
					name: '# Tasks (Comparison)',
					data: [<?php echo $compare_preferred_count['preferred']['count']; ?>, <?php echo $compare_preferred_count['nonpreferred']['count']; ?>],
					pointPadding: 0.3,
					pointPlacement: -0.2,
					color: 'rgba(255,64,64,.9)',
					totalcount: <?php echo $compare_preferred_count['preferred']['count'] + $compare_preferred_count['nonpreferred']['count']; ?>
				}]
			});

			<?php } ?>


			// task type chart
			Highcharts.chart('tasks_type', {
				exporting: {
					chartOptions: { // specific options for the exported image
						plotOptions: {
							series: {
								dataLabels: {
									enabled: true
								}
							}
						}
					},
					fallbackToExportServer: false
				},
				chart: {
					type: 'column'
				},
				title: {
					text: '<?php echo __( 'Tasks Types', 'wpcable' ); ?>'
				},
				subtitle: {
					text: '-'
				},
				xAxis: {
					categories: [
					  <?php
						if ( ! $is_compare ) {
							echo implode( ',', $type_categories );
						} else {
							echo implode( ',', array_merge( $type_categories, $compare_type_categories ) );
						}
						?>
					]
				},
				yAxis: {
					min: 0,
					title: {
						text: '<?php echo __( 'Revenue', 'wpcable' ); ?>'
					}
				},
				legend: {
				  <?php if ( ! $is_compare ) { ?>
					enabled: false
					<?php } else { ?>
					enabled: true
					<?php } ?>
				},
				tooltip: {
					formatter: function () {
					  if (this.series.name == 'Revenue' ) {
						return this.x + '<br />-<br />' + this.series.name + ': <b>$' + this.y + '</b><br />Tasks:<b>' + parseFloat($type_tasks_count_json[this.x]) + '</b><br />Average:<b>' + ( parseFloat(this.y / parseFloat($type_tasks_count_json[this.x])).toFixed(2) ) + '</b>';
					  } else {
						return this.x + '<br />-<br />' + this.series.name + ': <b>$' + this.y + '</b><br />Tasks:<b>' + parseFloat($compare_type_tasks_count_json[this.x]) + '</b><br />Average:<b>' + ( parseFloat(this.y / parseFloat($compare_type_tasks_count_json[this.x])).toFixed(2) ) + '</b>';
					  }
					}
				},
				plotOptions: {
					column: {
						borderWidth: 0,
						grouping: false,
						<?php if ( ! $is_compare ) { ?>
						colorByPoint: true,
						<?php } ?>
						shadow: false
					}
				},
				series: [{
					name: ['<?php echo __( 'Revenue', 'wpcable' ); ?>'],
					data: [<?php echo implode( ',', $type_revenue ); ?>],
					pointPadding: 0.1,
					pointPlacement: -0.2,
					color: 'rgba(43,144,143,1)'

				}
				<?php
				if ( $is_compare ) {
					?>
					,
				{
					name: ['<?php echo __( 'Revenue (Comparison)', 'wpcable' ); ?>'],
					data: [<?php echo implode( ',', $compare_type_revenue ); ?>],
					pointPadding: 0.3,
					pointPlacement: -0.2,
					color: 'rgba(145,232,225,1)'

				}

				<?php } ?>
				]
			});


			function escapeRegExp(str) {
				return str.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, "\\$1");
			}

			function replaceAll(str, find, replace) {
				return str.replace(new RegExp(escapeRegExp(find), 'g'), replace);
			}


		});
	</script>
	<?php endif; ?>


	<?php
}
