<?php
/**
 * Profile/avatar PHP 8 guards and malformed API bodies.
 *
 * @package wpcable
 */

$fixtures = json_decode( file_get_contents( __DIR__ . '/fixtures/account-details.json' ), true );

// Nested avatar array (legacy happy path).
$url = wpcable_account_avatar_url( $fixtures['nested_avatar'] );
wpcable_assert_equals( 'https://example.test/large.png', $url, 'nested large_url is used' );
wpcable_assert_equals( 911, wpcable_account_detail( $fixtures['nested_avatar'], 'completed_tasks_count' ), 'completed_tasks_count from profile' );
wpcable_assert_equals( 4.9, wpcable_account_stat( $fixtures['nested_avatar'], 'avg_rating' ), 'avg_rating from nested stats' );

// Avatar stored as a plain string.
$url = wpcable_account_avatar_url( $fixtures['string_avatar'] );
wpcable_assert_equals( 'https://example.test/plain.png', $url, 'string avatar does not TypeError' );

// Account details option is a string (malformed JSON stored as option).
update_option( 'wpcable_account_details', 'not-an-array' );
$details = wpcable_get_account_details();
wpcable_assert( is_array( $details ) && array() === $details, 'string option becomes empty array' );
wpcable_assert_equals( '', wpcable_account_avatar_url( $details ), 'no invented avatar on malformed option' );
wpcable_assert_equals( '', wpcable_account_detail( $details, 'first_name' ), 'missing first_name stays empty' );
wpcable_assert_equals( 0, wpcable_account_stat( $details, 'avg_rating', 0 ), 'missing stats default to 0' );

// Null / false option.
update_option( 'wpcable_account_details', false );
wpcable_assert_equals( array(), wpcable_get_account_details(), 'false option is empty array' );

// Missing avatar key.
$url = wpcable_account_avatar_url( array( 'id' => 1, 'first_name' => 'A' ) );
wpcable_assert_equals( '', $url, 'missing avatar is empty, not a notice' );

// Import health does not invent success times.
$health = wpcable_import_health::get();
wpcable_assert_equals( 'never', $health['transactions']['status'], 'transactions start as never' );
wpcable_assert_equals( 0, $health['transactions']['succeeded_at'], 'no fabricated success timestamp' );
wpcable_import_health::record( 'transactions', 'failed', 'fixture failure' );
$health = wpcable_import_health::get();
wpcable_assert_equals( 'failed', $health['transactions']['status'], 'failed status stored' );
wpcable_assert_equals( 0, $health['transactions']['succeeded_at'], 'failed attempt does not invent succeeded_at' );

wpcable_import_health::record( 'profile', 'success', 'ok', 1 );
$health = wpcable_import_health::get();
wpcable_assert( $health['profile']['succeeded_at'] > 0, 'success records a real timestamp' );
$label = wpcable_import_health::success_time_label( $health['profile'] );
wpcable_assert( false !== strpos( $label, 'Last successful import' ), 'success label mentions last successful import' );
