<form method="post" action="options.php" class="wpcable-settings-grid">
	<?php settings_fields( 'wpcable_group' ); ?>
	<?php do_settings_sections( 'wpcable_group' ); ?>

	<section class="wpcable-settings-card">
		<h2><?php esc_html_e( 'Project list', 'wpcable' ); ?></h2>
		<p>
			<?php
			printf(
				esc_html__( 'Adjust how imported projects are flagged and how many list pages Refresh pulls. Open the %sprojects workspace%s.', 'wpcable' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=codeable_tasks' ) ) . '">',
				'</a>'
			);
			?>
		</p>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row">
					<label class="wpcable_label" for="wpcable_cancel_after_days">
						<?php esc_html_e( 'Flag as canceled after quiet days', 'wpcable' ); ?>
					</label>
				</th>
				<td>
					<input type="number" name="wpcable_cancel_after_days" id="wpcable_cancel_after_days" min="14" max="720" value="<?php echo (int) $wpcable_cancel_after_days; ?>" />
					<?php esc_html_e( 'days', 'wpcable' ); ?>
					<p class="description">
						<?php esc_html_e( 'Adds a local canceled flag when a project has had no recorded activity for this many days. Default 180. This is a local heuristic, not proof another expert was hired.', 'wpcable' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label class="wpcable_label" for="wpcable_tasks_stop_at_page">
						<?php esc_html_e( 'Stop Refresh after this many pages', 'wpcable' ); ?>
					</label>
				</th>
				<td>
					<input type="number" name="wpcable_tasks_stop_at_page" id="wpcable_tasks_stop_at_page" min="0" max="720" value="<?php echo (int) $wpcable_tasks_stop_at_page; ?>" />
					<?php esc_html_e( 'pages', 'wpcable' ); ?>
					<p class="description">
						<?php esc_html_e( '0 pulls every page (recommended the first time). Later, 1 or 2 updates only the newest pages.', 'wpcable' ); ?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>
	</section>

	<section class="wpcable-settings-card">
		<h2><?php esc_html_e( 'Estimate defaults', 'wpcable' ); ?></h2>
		<p>
			<?php
			printf(
				esc_html__( 'Used on the %sEstimate%s screen. PERT formulas themselves are not changed here.', 'wpcable' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=codeable_estimate' ) ) . '">',
				'</a>'
			);
			?>
		</p>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row">
					<label class="wpcable_label" for="wpcable_rate">
						<?php esc_html_e( 'Hourly rate', 'wpcable' ); ?>
					</label>
				</th>
				<td>
					<input
						id="wpcable_rate"
						type="number"
						min="35"
						max="1000"
						name="wpcable_rate"
						value="<?php echo (float) $wpcable_rate; ?>"
					/> $
					<p class="description">
						<?php esc_html_e( 'Default hourly rate on the Estimate screen.', 'wpcable' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label class="wpcable_label" for="wpcable_fee_type">
						<?php esc_html_e( 'Fee calculation', 'wpcable' ); ?>
					</label>
				</th>
				<td>
					<select id="wpcable_fee_type" name="wpcable_fee_type">
						<option value="full" <?php selected( 'full', $wpcable_fee_type ); ?>>
							<?php esc_html_e( 'My rate is what I want to get paid, without any fees', 'wpcable' ); ?>
						</option>
						<option value="client" <?php selected( 'client', $wpcable_fee_type ); ?>>
							<?php esc_html_e( 'My rate includes my fee (10%) but not the client fee', 'wpcable' ); ?>
						</option>
						<option value="none" <?php selected( 'none', $wpcable_fee_type ); ?>>
							<?php esc_html_e( 'My rate includes all fees', 'wpcable' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Controls how Estimate applies contractor and client fees. Same three options as before.', 'wpcable' ); ?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>
	</section>

	<section class="wpcable-settings-card">
		<h2><?php esc_html_e( 'Codeable connection', 'wpcable' ); ?></h2>
		<p>
			<?php esc_html_e( 'Sign-in obtains a user-specific token used only for read-only Refresh. Your password is not stored.', 'wpcable' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'This plugin does not send messages, estimates, or payouts, and it does not write to workrooms. Local notes and color flags stay in WordPress.', 'wpcable' ); ?>
		</p>

		<table class="form-table">
			<tbody>
			<?php if ( codeable_api_logged_in() ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Account', 'wpcable' ); ?></th>
					<td>
						<p>
							<?php
							printf(
								__( 'Signed in as %s', 'wpcable' ),
								'<b>' . esc_html( $wpcable_email ) . '</b>'
							);
							?>
							<input type="hidden" name="wpcable_email" value="<?php echo esc_attr( $wpcable_email ); ?>" />
						</p>
						<p class="wpcable-logout-note">
							<?php esc_html_e( 'Log out turns Refresh off and keeps saved projects, clients, notes, and profile. Clear all data permanently deletes those local records. Logout is not Clear all data.', 'wpcable' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( $logout_url ); ?>" class="button"><?php esc_html_e( 'Log out (keep saved data)', 'wpcable' ); ?></a>
							<a href="<?php echo esc_url( $flush_data_url ); ?>" class="button danger" onclick="return confirm('<?php echo esc_attr( $flush_data_warning ); ?>')"><?php esc_html_e( 'Clear all data', 'wpcable' ); ?></a>
						</p>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row">
						<label class="wpcable_label" for="wpcable_email">
							<?php esc_html_e( 'Email', 'wpcable' ); ?>
						</label>
					</th>
					<td>
						<input
							id="wpcable_email"
							type="email"
							name="wpcable_email"
							class="regular-text"
							value="<?php echo esc_attr( $wpcable_email ); ?>"
							autocomplete="email"
						/>
						<p class="description"><?php esc_html_e( 'The email you use on app.codeable.io', 'wpcable' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label class="wpcable_label" for="wpcable_password">
							<?php esc_html_e( 'Password', 'wpcable' ); ?>
						</label>
					</th>
					<td>
						<input
							id="wpcable_password"
							type="password"
							name="wpcable_password"
							class="regular-text"
							value=""
							autocomplete="password"
						/>
						<p class="description"><?php esc_html_e( 'Used once to obtain a token. The password is not saved.', 'wpcable' ); ?></p>
					</td>
				</tr>
				<?php if ( ! empty( $has_saved ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved data', 'wpcable' ); ?></th>
						<td>
							<p class="wpcable-logout-note">
								<?php esc_html_e( 'Disconnected — showing saved data. Refresh is off until you sign in again. Saved projects, clients, notes, and profile stay until you use Clear all data.', 'wpcable' ); ?>
							</p>
							<p>
								<a href="<?php echo esc_url( $flush_data_url ); ?>" class="button danger" onclick="return confirm('<?php echo esc_attr( $flush_data_warning ); ?>')"><?php esc_html_e( 'Clear all data', 'wpcable' ); ?></a>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<div class="action-buttons">
			<?php submit_button( __( 'Save Changes', 'wpcable' ) ); ?>
		</div>
	</section>
</form>
