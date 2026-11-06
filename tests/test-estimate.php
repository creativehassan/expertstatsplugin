<?php
/**
 * PERT estimate math must stay aligned with templates/admin-estimate.php JS.
 *
 * @package wpcable
 */

$client = wpcable_estimate::calculate(
	array(
		'optimistic'     => 1,
		'likely'         => 2,
		'pessimistic'    => 5,
		'rate'           => 80,
		'fee_type'       => 'client',
		'contractor_fee' => 10,
		'client_fee'     => 17.5,
	)
);

$hours = ( 1 + 4 * 2 + 5 ) / 6; // 2.333...
wpcable_assert( abs( $client['hours_standard'] - $hours ) < 0.0001, 'standard PERT hours' );

$base = $hours * 80;
$expected_payment  = wpcable_estimate::round_step( $base * 1.175 );
$expected_estimate = wpcable_estimate::round_step( $base * 1 );
$expected_earn     = wpcable_estimate::round_step( $base / ( 1 / ( 1 - 0.10 ) ) );

wpcable_assert_equals( $expected_payment, $client['payment'], 'client fee_type: paid by client includes client fee' );
wpcable_assert_equals( $expected_estimate, $client['estimate'], 'client fee_type: estimate is rate * hours' );
wpcable_assert_equals( $expected_earn, $client['earnings'], 'client fee_type: earnings strip contractor factor' );

$full = wpcable_estimate::calculate(
	array(
		'optimistic'     => 2,
		'likely'         => 2,
		'pessimistic'    => 2,
		'rate'           => 100,
		'fee_type'       => 'full',
		'contractor_fee' => 10,
		'client_fee'     => 17.5,
	)
);
$hours_flat = 2;
$base_flat  = 200;
$factor_c   = 1 / ( 1 - 0.10 );
$factor_cl  = 1.175;
wpcable_assert_equals(
	wpcable_estimate::round_step( $base_flat * $factor_c * $factor_cl ),
	$full['payment'],
	'full fee_type applies both fees to payment'
);
wpcable_assert_equals(
	wpcable_estimate::round_step( $base_flat * $factor_c ),
	$full['estimate'],
	'full fee_type estimate includes contractor fee'
);
wpcable_assert_equals(
	wpcable_estimate::round_step( $base_flat ),
	$full['earnings'],
	'full fee_type earnings equal rate * hours'
);

$none = wpcable_estimate::calculate(
	array(
		'optimistic'     => 1,
		'likely'         => 1,
		'pessimistic'    => 1,
		'rate'           => 80,
		'fee_type'       => 'none',
		'contractor_fee' => 10,
		'client_fee'     => 17.5,
	)
);
wpcable_assert_equals(
	wpcable_estimate::round_step( 80 ),
	$none['payment'],
	'none fee_type payment equals rate * hours'
);

$pess_hours = wpcable_estimate::hours_pessimistic( 1, 2, 5 );
wpcable_assert( abs( $pess_hours - ( ( 1 + 2 * 2 + 3 * 5 ) / 6 ) ) < 0.0001, 'cautious PERT hours' );
