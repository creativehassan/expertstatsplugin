<?php
/**
 * PERT estimate math — kept in PHP so Estimate remains testable and stable.
 * The Estimate admin page JS must stay in lockstep with these formulas.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PERT estimator.
 */
class wpcable_estimate {

	/**
	 * Round to a given step (matches Estimate page JS).
	 *
	 * @param float $value Value.
	 * @param float $step  Step, default 0.01.
	 * @return float
	 */
	public static function round_step( $value, $step = 0.01 ) {
		$step = (float) $step;
		if ( $step <= 0 ) {
			$step = 0.01;
		}
		$inv = 1.0 / $step;
		return round( $value * $inv ) / $inv;
	}

	/**
	 * Standard PERT hours: (O + 4M + P) / 6.
	 *
	 * @param float $optimistic  Optimistic hours.
	 * @param float $likely      Most-likely hours.
	 * @param float $pessimistic Pessimistic hours.
	 * @return float
	 */
	public static function hours_standard( $optimistic, $likely, $pessimistic ) {
		return ( (float) $optimistic + 4 * (float) $likely + (float) $pessimistic ) / 6;
	}

	/**
	 * Cautious PERT hours: (O + 2M + 3P) / 6.
	 *
	 * @param float $optimistic  Optimistic hours.
	 * @param float $likely      Most-likely hours.
	 * @param float $pessimistic Pessimistic hours.
	 * @return float
	 */
	public static function hours_pessimistic( $optimistic, $likely, $pessimistic ) {
		return ( (float) $optimistic + 2 * (float) $likely + 3 * (float) $pessimistic ) / 6;
	}

	/**
	 * Fee multipliers matching templates/admin-estimate.php.
	 *
	 * fee_type:
	 * - full:   rate is take-home; apply contractor then client fee.
	 * - client: rate already includes contractor 10%; apply client fee only for "paid by client".
	 * - none:   rate already includes all fees.
	 *
	 * @param string $fee_type        full|client|none.
	 * @param float  $contractor_fee  Contractor fee percent.
	 * @param float  $client_fee      Client fee percent.
	 * @return array { all, mine, without }
	 */
	public static function fee_factors( $fee_type, $contractor_fee, $client_fee ) {
		$fee_contractor = 1 / ( 1 - ( (float) $contractor_fee ) / 100 );
		$fee_client     = 1 + ( (float) $client_fee ) / 100;

		if ( 'full' === $fee_type ) {
			return array(
				'all'     => $fee_contractor * $fee_client,
				'mine'    => $fee_contractor,
				'without' => 1,
			);
		}

		if ( 'none' === $fee_type ) {
			return array(
				'all'     => 1,
				'mine'    => 1 / $fee_client,
				'without' => $fee_contractor * $fee_client,
			);
		}

		// Default: client (rate includes contractor fee).
		return array(
			'all'     => $fee_client,
			'mine'    => 1,
			'without' => $fee_contractor,
		);
	}

	/**
	 * Full estimate payload used by tests and any server-side reuse.
	 *
	 * @param array $input {
	 *     @type float  $optimistic
	 *     @type float  $likely
	 *     @type float  $pessimistic
	 *     @type float  $rate
	 *     @type string $fee_type
	 *     @type float  $contractor_fee
	 *     @type float  $client_fee
	 * }
	 * @return array
	 */
	public static function calculate( $input ) {
		$optimistic     = isset( $input['optimistic'] ) ? (float) $input['optimistic'] : 1;
		$likely         = isset( $input['likely'] ) ? (float) $input['likely'] : 1;
		$pessimistic    = isset( $input['pessimistic'] ) ? (float) $input['pessimistic'] : 1;
		$rate           = isset( $input['rate'] ) ? (float) $input['rate'] : 80;
		$fee_type       = isset( $input['fee_type'] ) ? $input['fee_type'] : 'client';
		$contractor_fee = isset( $input['contractor_fee'] ) ? (float) $input['contractor_fee'] : 10;
		$client_fee     = isset( $input['client_fee'] ) ? (float) $input['client_fee'] : 17.5;

		$hours_std  = self::hours_standard( $optimistic, $likely, $pessimistic );
		$hours_pess = self::hours_pessimistic( $optimistic, $likely, $pessimistic );
		$factors    = self::fee_factors( $fee_type, $contractor_fee, $client_fee );

		$std_base  = $hours_std * $rate;
		$pess_base = $hours_pess * $rate;

		return array(
			'hours_standard'           => $hours_std,
			'hours_pessimistic'        => $hours_pess,
			'payment'                  => self::round_step( $std_base * $factors['all'] ),
			'payment_pessimistic'      => self::round_step( $pess_base * $factors['all'] ),
			'estimate'                 => self::round_step( $std_base * $factors['mine'] ),
			'estimate_pessimistic'     => self::round_step( $pess_base * $factors['mine'] ),
			'earnings'                 => self::round_step( $std_base / $factors['without'] ),
			'earnings_pessimistic'     => self::round_step( $pess_base / $factors['without'] ),
		);
	}
}
