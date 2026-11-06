<?php
/**
 * Opportunities must cite source rows and must not invent chat/satisfaction.
 *
 * @package wpcable
 */

$opps     = new wpcable_opportunities();
$clients  = new wpcable_clients();
$alpha    = $clients->get_client_detail( 101 );
$cues     = $opps->suggestions_for_client( $alpha );
$types    = array();

foreach ( $cues as $cue ) {
	$types[] = $cue['type'];
	wpcable_assert( ! empty( $cue['reasons'] ), $cue['type'] . ' has reasons' );
	wpcable_assert( ! empty( $cue['evidence']['source_task_ids'] ), $cue['type'] . ' cites source task ids' );
	wpcable_assert( false !== strpos( $cue['disclaimer'], 'Not a confirmed client need' ), $cue['type'] . ' is not a confirmed need' );
	wpcable_assert( in_array( $cue['confidence'], array( 'high', 'medium', 'low' ), true ), $cue['type'] . ' has confidence' );
	foreach ( $cue['reasons'] as $reason ) {
		wpcable_assert( false === stripos( $reason, 'chat said' ), 'no invented chat in ' . $cue['type'] );
		wpcable_assert( false === stripos( $reason, 'satisfaction' ) || false !== stripos( $reason, 'not' ), 'satisfaction is not claimed as known' );
	}
}

wpcable_assert( in_array( 'ebr_checkup', $types, true ), 'stale completed work yields follow-up cue' );
wpcable_assert( in_array( 'maintenance_retainer', $types, true ), 'repeat completed work yields maintenance cue' );
wpcable_assert( in_array( 'referral_ask', $types, true ), 'preferred + completed yields referral cue with low confidence' );

$referral = null;
foreach ( $cues as $cue ) {
	if ( 'referral_ask' === $cue['type'] ) {
		$referral = $cue;
	}
}
wpcable_assert_equals( 'low', $referral['confidence'], 'referral cue is low confidence without chat/reviews' );

$blocked = wpcable_opportunities::unsupported_signals();
$blocked_ids = array();
foreach ( $blocked as $item ) {
	$blocked_ids[] = $item['id'];
}
wpcable_assert( in_array( 'conversation_mining', $blocked_ids, true ), 'conversation mining is unsupported' );
wpcable_assert( in_array( 'seo_site_audit', $blocked_ids, true ), 'SEO audit stays unavailable without website URL' );
wpcable_assert( in_array( 'auto_outreach', $blocked_ids, true ), 'auto outreach is blocked' );
wpcable_assert( in_array( 'global_hire_proof', $blocked_ids, true ), 'lost is not hire proof' );

$draft = $opps->outreach_draft( $alpha, $cues[0] );
wpcable_assert( false === $draft['auto_send'], 'draft is not auto-send' );
wpcable_assert( false !== strpos( $draft['notice'], 'Review only' ), 'draft labeled review-only' );

$all = $opps->get_suggestions();
foreach ( $all as $cue ) {
	wpcable_assert( $cue['client_id'] > 0, 'suggestion tied to a real client_id' );
	wpcable_assert( false === strpos( strtolower( $cue['client_name'] ), 'acme corp' ), 'no fabricated marketing names' );
}

$beta = $clients->get_client_detail( 202 );
$beta_cues = $opps->suggestions_for_client( $beta );
$beta_types = array();
foreach ( $beta_cues as $cue ) {
	$beta_types[] = $cue['type'];
}
wpcable_assert( ! in_array( 'referral_ask', $beta_types, true ), 'Beta without preferred+completed does not get referral cue' );
