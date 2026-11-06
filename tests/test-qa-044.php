<?php
/**
 * 0.4.4: Stats has one overview + one import-health; no legacy duplicate row; no page overflow CSS mins.
 *
 * @package wpcable
 */

require_once WPCABLE_TEST_ROOT . '/functions/admin-page.php';

$php = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( false !== strpos( $php, 'Version:           0.4.5' ), 'plugin header is current' );

$stats_src = file_get_contents( WPCABLE_TEST_ROOT . '/functions/admin-page.php' );
wpcable_assert( false === strpos( $stats_src, 'first-stats-row' ), 'legacy first-stats-row removed from Stats' );
wpcable_assert( false === strpos( $stats_src, 'codeable_last_fetch_info' ), 'Stats does not call import-health twice (only app_close)' );
wpcable_assert( false === strpos( $stats_src, 'box user-info' ), 'legacy user-info card removed' );
wpcable_assert( false === strpos( $stats_src, 'box total-revenue' ), 'legacy total-revenue card removed' );
wpcable_assert( false === strpos( $stats_src, 'box balance' ), 'legacy balance card removed' );
wpcable_assert( false !== strpos( $stats_src, 'wpcable_overview_panel' ), 'Stats still renders the canonical overview panel' );
wpcable_assert( false !== strpos( $stats_src, 'conversions' ), 'imported-transaction conversions block kept' );
wpcable_assert( false !== strpos( $stats_src, 'monthly-averages' ), 'imported-transaction monthly averages kept' );
wpcable_assert( false !== strpos( $stats_src, 'chart_wrap' ), 'imported-transaction money chart kept' );
wpcable_assert( false !== strpos( $stats_src, 'clients_table' ), 'imported-transaction clients table kept' );
wpcable_assert( false !== strpos( $stats_src, 'wpcable-table-scroll' ), 'wide clients table is in a local scroll container' );

$chrome_src = file_get_contents( WPCABLE_TEST_ROOT . '/functions/admin-chrome.php' );
wpcable_assert( 1 === substr_count( $chrome_src, 'function wpcable_overview_panel' ), 'one overview_panel function' );
wpcable_assert( false !== strpos( $chrome_src, 'id="wpcable-stats-overview"' ), 'overview has a stable id' );
wpcable_assert( 1 === substr_count( $chrome_src, 'codeable_last_fetch_info' ), 'app_close is the single import-health caller in chrome' );

$helpers = file_get_contents( WPCABLE_TEST_ROOT . '/functions/helpers.php' );
wpcable_assert( false !== strpos( $helpers, 'id="wpcable-import-health"' ), 'import-health section has a unique id' );

$css = file_get_contents( WPCABLE_TEST_ROOT . '/assets/css/wpcable-workspace.css' );
wpcable_assert( false !== strpos( $css, 'overflow-x: hidden' ), 'plugin chrome clips page-level overflow' );
wpcable_assert( false !== strpos( $css, 'minmax(min(100%' ), 'grid mins cannot exceed the container' );
wpcable_assert( false !== strpos( $css, '.wpcable-table-scroll' ), 'table-local scroll class exists' );
wpcable_assert( false === strpos( $css, 'min-width: 200px' ), 'stats cards do not force 200px min-width overflow' );
wpcable_assert( false === strpos( $css, 'min-width: 260px' ), 'search does not force 260px min-width overflow' );
wpcable_assert( false === strpos( $css, 'minmax(180px' ), 'KPI row does not use overflow-prone 180px mins' );

ob_start();
codeable_transcactions_stats_cb();
$stats_html = ob_get_clean();

wpcable_assert_equals( 1, substr_count( $stats_html, 'id="wpcable-stats-overview"' ), 'rendered Stats has exactly one overview' );
wpcable_assert_equals( 1, substr_count( $stats_html, 'wpcable-kpi--profile' ), 'rendered Stats has exactly one profile identity card' );
wpcable_assert_equals( 1, substr_count( $stats_html, 'id="wpcable-import-health"' ), 'rendered Stats has exactly one import-health section' );
wpcable_assert_equals( 1, substr_count( $stats_html, 'class="codeable-last-refresh wpcable-import-health"' ), 'import-health class appears once' );
wpcable_assert( false === strpos( $stats_html, 'first-stats-row' ), 'rendered Stats has no legacy first-stats-row' );
wpcable_assert( false === strpos( $stats_html, 'box user-info' ), 'rendered Stats has no duplicate user-info card' );
wpcable_assert( false !== strpos( $stats_html, 'wpcable-financial-empty' ), 'empty-finance fixture still explains hidden charts (not $0)' );
wpcable_assert( false === strpos( $stats_html, 'class="box balance"' ), 'rendered empty-finance Stats has no duplicate Balance card' );

$pack = file_get_contents( WPCABLE_TEST_ROOT . '/scripts/package-zip.sh' );
wpcable_assert( false !== strpos( $pack, 'PLUGIN_ROOT_NAME="expertstatsplugin-master"' ), 'packaging root remains expertstatsplugin-master' );

$payload = file_get_contents( WPCABLE_TEST_ROOT . '/functions/task-payload.php' );
wpcable_assert( false !== strpos( $payload, 'codeable.docs.apiary.io' ), 'payload mapper cites public Apiary docs' );
wpcable_assert( false === strpos( $payload, 'Official Codeable API docs were not found' ), 'no longer claims docs were not found' );
