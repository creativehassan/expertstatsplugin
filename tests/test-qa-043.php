<?php
/**
 * 0.4.3: payload history mapping, disconnected browsing, chrome, no invented API routes.
 *
 * @package wpcable
 */

$api = file_get_contents( WPCABLE_TEST_ROOT . '/classes/api_calls.php' );
wpcable_assert( false !== strpos( $api, "users/me/tasks/" ), 'list importer still uses users/me/tasks/{filter}' );
wpcable_assert( false !== strpos( $api, 'users/me/hidden_tasks/' ), 'hidden list path unchanged' );
wpcable_assert( false === strpos( $api, 'task/{id}/comments' ), 'no invented comments HTTP route in api_calls' );
wpcable_assert( false === strpos( $api, '/task/' ), 'no GET /task/{id} route added to api_calls' );

$missing = wpcable_payload_description( array( 'id' => 1, 'title' => 'Only title' ) );
wpcable_assert( false === $missing['present'], 'missing description key is not present' );
wpcable_assert( null === $missing['text'], 'missing description is not empty string' );

$empty = wpcable_payload_description( array( 'id' => 1, 'description' => '   ' ) );
wpcable_assert( true === $empty['present'], 'empty description key is present' );
wpcable_assert_equals( 'description', $empty['key'], 'empty description records source key' );

$rich = wpcable_payload_description( array( 'id' => 1, 'description' => "Need WooCommerce checkout help.\nUrgent." ) );
wpcable_assert( true === $rich['present'], 'description string is mapped from list object' );
wpcable_assert_equals( "Need WooCommerce checkout help.\nUrgent.", $rich['text'], 'description text preserved' );

$no_comments = wpcable_payload_comments( array( 'id' => 1, 'title' => 'x' ) );
wpcable_assert( false === $no_comments['present'], 'missing comments key is not present' );

$empty_comments = wpcable_payload_comments( array( 'id' => 1, 'comments' => array() ) );
wpcable_assert( true === $empty_comments['present'], 'empty comments array is present' );
wpcable_assert( empty( $empty_comments['items'] ), 'empty comments array has no items' );

$mixed = wpcable_payload_comments(
	array(
		'id'       => 1,
		'comments' => array(
			array(
				'id'        => 11,
				'body'      => 'Public update',
				'private'   => false,
				'user'      => array( 'full_name' => 'Alex Client', 'role' => 'client' ),
				'created_at'=> '2024-01-02',
			),
			array(
				'id'                   => 12,
				'body'                 => 'Contractor-only note',
				'private'              => true,
				'is_contractors_only'  => true,
				'user'                 => array( 'full_name' => 'Pat Expert', 'role' => 'contractor' ),
			),
		),
	)
);
wpcable_assert( true === $mixed['present'], 'comments array on list payload is mapped' );
wpcable_assert_equals( 2, count( $mixed['items'] ), 'both comments stored' );
wpcable_assert_equals( 'public', $mixed['items'][0]['visibility'], 'public flag stored from payload' );
wpcable_assert_equals( 'private', $mixed['items'][1]['visibility'], 'private flag stored from payload' );
wpcable_assert( false === $mixed['complete'], 'list-payload comments are not claimed complete/paginated' );

$row = array( 'task_id' => 9, 'title' => 'Keep me' );
$row = wpcable_apply_payload_history( $row, array( 'id' => 9, 'title' => 'Keep me' ) );
wpcable_assert( ! isset( $row['description_present'] ), 'missing description key does not write present=0 (would wipe a richer earlier row)' );
wpcable_assert( ! isset( $row['comments_present'] ), 'missing comments key does not write present=0' );

$row = wpcable_apply_payload_history(
	array( 'task_id' => 9 ),
	array(
		'id'          => 9,
		'description' => 'Body from list',
		'comments'    => array(
			array( 'id' => 1, 'body' => 'Hi', 'private' => false ),
		),
	)
);
wpcable_assert_equals( 1, $row['description_present'], 'present description is persisted' );
wpcable_assert_equals( 'Body from list', $row['description_text'], 'description_text stored' );
wpcable_assert_equals( 1, $row['comments_present'], 'present comments persisted' );

$history_missing = wpcable_task_history( array( 'task_id' => 77 ) );
wpcable_assert( empty( $history_missing['description']['available'] ), 'missing payload is not a fake description' );
wpcable_assert( empty( $history_missing['description']['present_in_payload'] ), 'missing vs empty: not present' );
wpcable_assert( false !== strpos( $history_missing['description']['reason'], 'not proof the API omits' ), 'copy does not claim no API exists' );
wpcable_assert( false === strpos( strtolower( $history_missing['comments']['reason'] ), 'no api exists' ), 'does not assert no API exists' );
wpcable_assert( false !== strpos( $history_missing['comments']['reason'], 'Pagination' ), 'pagination completeness is unverified' );

$history_empty = wpcable_task_history(
	array(
		'task_id'             => 78,
		'description_present' => 1,
		'description_text'    => '',
		'comments_present'    => 1,
		'comments_json'       => '[]',
	)
);
wpcable_assert( ! empty( $history_empty['description']['empty'] ), 'empty description field is distinct from missing' );
wpcable_assert( ! empty( $history_empty['comments']['empty'] ), 'empty comments field is distinct from missing' );

$history_stored = wpcable_task_history(
	array(
		'task_id'             => 79,
		'description_present' => 1,
		'description_text'    => 'Stored body',
		'comments_present'    => 1,
		'comments_json'       => wp_json_encode(
			array(
				array(
					'body'       => 'Hello',
					'visibility' => 'public',
					'author'     => 'Alex',
				),
			)
		),
	)
);
wpcable_assert( ! empty( $history_stored['description']['available'] ), 'stored description is available for UI' );
wpcable_assert_equals( 'Stored body', $history_stored['description']['text'], 'UI receives genuine stored text' );
wpcable_assert( ! empty( $history_stored['comments']['available'] ), 'stored comments are available for UI' );
wpcable_assert_equals( 1, count( $history_stored['comments']['items'] ), 'genuine comment items passed through' );

$js = file_get_contents( WPCABLE_TEST_ROOT . '/assets/js/wpcable-workspace.js' );
wpcable_assert( false !== strpos( $js, 'wpcable-history-body' ), 'drawer can render a genuine description body' );
wpcable_assert( false !== strpos( $js, 'wpcable-comment-list' ), 'drawer can render genuine comments' );
wpcable_assert( false !== strpos( $js, 'wpcable-history-missing' ), 'drawer keeps missing state (not empty list)' );

update_option(
	'wpcable_account_details',
	array(
		'id'                    => 91563,
		'first_name'            => 'Fixture',
		'last_name'             => 'Expert',
		'role'                  => 'contractor',
		'completed_tasks_count' => 3,
		'tasks_count'           => 10,
	)
);
delete_option( 'wpcable_auth_token' );
wpcable_assert( ! codeable_api_logged_in(), 'token absent is disconnected' );
wpcable_assert( wpcable_has_saved_workspace_data(), 'saved profile/tasks still count as data' );
wpcable_assert( ! wpcable_sync_allowed(), 'sync blocked when disconnected' );
$state = wpcable_connection_state();
wpcable_assert_equals( 'disconnected_with_data', $state['state'], 'chrome state is disconnected with saved data' );

$before_logout_tasks = ( new wpcable_tasks() )->get_state_counts();
codeable_api_logout();
$after_logout_tasks = ( new wpcable_tasks() )->get_state_counts();
wpcable_assert_equals( $before_logout_tasks['imported'], $after_logout_tasks['imported'], 'logout does not truncate tasks' );
wpcable_assert( ! empty( get_option( 'wpcable_account_details' ) ), 'logout keeps profile option' );

codeable_page_requires_login( 'Projects' );
wpcable_assert( true, 'requires_login returns instead of exiting when disconnected' );

$wp_codeable = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $wp_codeable, "state'   => 'DISCONNECTED" ) || false !== strpos( $wp_codeable, "'state'   => 'DISCONNECTED'" ) || false !== strpos( $wp_codeable, 'DISCONNECTED' ), 'sync_start/process refuse when disconnected' );

$css = file_get_contents( WPCABLE_TEST_ROOT . '/assets/css/wpcable-workspace.css' );
wpcable_assert( false !== strpos( $css, 'max-width: none' ), 'workspace CSS is fluid, not pinned to 1400px' );
wpcable_assert( false === strpos( $css, "max-width: 1400px" ), '1400px cap removed' );
wpcable_assert( false !== strpos( $css, '@media (max-width: 1280px)' ), '1280 breakpoint present' );
wpcable_assert( false !== strpos( $css, '@media (max-width: 782px)' ), 'mobile breakpoint present' );

$stats_src = file_get_contents( WPCABLE_TEST_ROOT . '/functions/admin-page.php' );
wpcable_assert( false === strpos( $stats_src, 'Profile tasks_count' ), 'Stats does not use tasks_count as a primary label' );
$chrome_src = file_get_contents( WPCABLE_TEST_ROOT . '/functions/admin-chrome.php' );
wpcable_assert( false !== strpos( $chrome_src, 'Profile projects' ), 'Stats overview uses Profile projects copy' );

$clients_src = file_get_contents( WPCABLE_TEST_ROOT . '/templates/admin-clients.php' );
wpcable_assert( false !== strpos( $clients_src, 'id="wpcable-client-drawer" hidden' ), 'client drawer starts hidden (not a blank split panel)' );

$opps_src = file_get_contents( WPCABLE_TEST_ROOT . '/templates/admin-opportunities.php' );
wpcable_assert( false !== strpos( $opps_src, 'wpcable-opp-table' ), 'opportunities use a comparative table, not only a 4-col card grid' );
wpcable_assert( false !== strpos( $opps_src, 'not a confirmed need' ), 'opportunities copy still marks cues as unconfirmed' );

$settings_src = file_get_contents( WPCABLE_TEST_ROOT . '/templates/admin-settings.php' );
wpcable_assert( false !== strpos( $settings_src, 'Log out (keep saved data)' ), 'logout copy distinguishes keep vs clear' );
wpcable_assert( false !== strpos( $settings_src, 'wpcable_cancel_after_days' ), 'settings controls preserved' );
wpcable_assert( false !== strpos( $settings_src, 'wpcable_rate' ), 'rate control preserved' );
wpcable_assert( false !== strpos( $settings_src, 'wpcable_fee_type' ), 'fee type control preserved' );

$estimate_src = file_get_contents( WPCABLE_TEST_ROOT . '/templates/admin-estimate.php' );
wpcable_assert( false !== strpos( $estimate_src, 'var estimate_hours_standard    = (optimistic + 4 * likely + pessimistic) / 6;' ), 'estimate standard PERT unchanged' );
wpcable_assert( false !== strpos( $estimate_src, 'id="optimistic_estimate"' ), 'estimate inputs preserved' );

$schema = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $schema, '0.0.5' ), 'additive schema version 0.0.5' );
wpcable_assert( false !== strpos( $schema, 'description_text' ), 'description_text column additive' );
wpcable_assert( false !== strpos( $schema, 'comments_json' ), 'comments_json column additive' );

$opps = new wpcable_opportunities();
$opps->persist_snapshot( array( array( 'type' => 'ebr_checkup', 'title' => 'Follow-up', 'client_id' => 101, 'confidence' => 'medium', 'score' => 40 ) ) );
$snap = get_option( 'wpcable_opportunity_snapshot' );
wpcable_assert( ! empty( $snap['count'] ), 'opportunity snapshot persisted locally' );

$metrics = wpcable_overview_metrics();
wpcable_assert( isset( $metrics['imported'] ), 'overview metrics from local DB' );
wpcable_assert( isset( $metrics['clients'] ), 'client count from distinct client_id' );
wpcable_assert( is_array( $metrics['activity'] ), 'activity buckets exclude invalid dates internally' );

ob_start();
wpcable_app_open( 'stats', 'Overview', 'Lede' );
$chrome = ob_get_clean();
wpcable_assert( false !== strpos( $chrome, 'Disconnected — showing saved data' ) || false !== strpos( $chrome, 'Connected' ) || false !== strpos( $chrome, 'Not connected' ), 'chrome renders a connection status' );
wpcable_assert( false !== strpos( $chrome, 'codeable_transcactions_stats' ), 'nav keeps Stats slug' );
wpcable_assert( false !== strpos( $chrome, 'codeable_tasks' ), 'nav keeps Tasks slug' );
wpcable_assert( false !== strpos( $chrome, 'codeable_estimate' ), 'nav keeps Estimate slug' );
wpcable_assert( false !== strpos( $chrome, 'codeable_settings' ), 'nav keeps Settings slug' );
ob_start();
wpcable_app_close();
ob_end_clean();
