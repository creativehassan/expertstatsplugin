<?php
/**
 * Missing transactions must not surface as $0 or year 1970.
 *
 * @package wpcable
 */

$stats = new wpcable_stats();

wpcable_assert_equals( 0, $stats->get_transaction_count(), 'fixture starts with zero money rows' );
wpcable_assert( ! $stats->has_transaction_data(), 'has_transaction_data is false' );

$first = $stats->get_first_task();
wpcable_assert( empty( $first['dateadded'] ), 'first task date is empty when no transactions' );

wpcable_assert_equals( '', wpcable_date( null ), 'null date is blank' );
wpcable_assert_equals( '', wpcable_date( 0 ), 'unix 0 is blank' );
wpcable_assert_equals( '', wpcable_date( '0000-00-00 00:00:00' ), 'MySQL zero date is blank' );
wpcable_assert_equals( '', wpcable_date( 1 ), 'epoch-adjacent timestamp is blank (no 1970)' );
wpcable_assert( ! wpcable_is_valid_datetime( false ), 'false is not a valid datetime' );

$formatted = wpcable_format_money_display( 0, false );
wpcable_assert_equals( '—', $formatted, 'unavailable money is an em dash, not $0.00' );
$formatted_zero = wpcable_format_money_display( null, true );
wpcable_assert_equals( '—', $formatted_zero, 'null amount is an em dash even if source flagged available' );

$all = $stats->get_all_stats( '01', '01', '1970', '31', '12', '1970', 'months' );
wpcable_assert( empty( $all['available'] ), '1970 range is rejected as unavailable' );
wpcable_assert( empty( $all['chart_categories'] ), 'no chart categories invented for empty finance' );

$averages = $stats->get_months_average( '01', '1970', '12', '1970' );
wpcable_assert( empty( $averages['available'] ), 'monthly average without data is unavailable' );

$clients = new wpcable_clients();
$from_tx = $clients->get_clients();
wpcable_assert( empty( $from_tx['available'] ), 'legacy client revenue table is unavailable without transactions' );
wpcable_assert( false !== strpos( $from_tx['unavailable_reason'], 'not $0' ), 'reason says not $0' );

$range_ok = $stats->has_valid_range( '01', '01', '2018', '31', '12', '2019' );
wpcable_assert( $range_ok, 'post-2008 range is valid' );
$range_bad = $stats->has_valid_range( '01', '01', '1970', '31', '01', '1970' );
wpcable_assert( ! $range_bad, '1970 range is invalid' );
