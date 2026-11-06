<?php
/**
 * Opportunities: grounded suggestions only, ranked for comparison.
 *
 * @package wpcable
 *
 * @var array $cues
 * @var array $blocked
 * @var array $health
 * @var array $counts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$groups = array();
foreach ( $cues as $cue ) {
	$type = isset( $cue['type'] ) ? (string) $cue['type'] : 'other';
	if ( ! isset( $groups[ $type ] ) ) {
		$groups[ $type ] = array(
			'title' => isset( $cue['title'] ) ? $cue['title'] : $type,
			'items' => array(),
			'score' => 0,
		);
	}
	$groups[ $type ]['items'][] = $cue;
	$score = isset( $cue['score'] ) ? (int) $cue['score'] : 0;
	if ( $score > $groups[ $type ]['score'] ) {
		$groups[ $type ]['score'] = $score;
	}
}

uasort(
	$groups,
	function ( $a, $b ) {
		return $b['score'] - $a['score'];
	}
);
?>
	<div class="wpcable-callout wpcable-callout--info">
		<?php esc_html_e( 'These are timing and volume cues from imported records, not confirmed requests. Ranked by local score so you can scan; identical titles are grouped. Chat, files, websites, per-project reviews, and global hiring stay unverified unless those fields were on the saved payload.', 'wpcable' ); ?>
	</div>

	<?php if ( empty( $cues ) ) : ?>
		<div class="wpcable-empty">
			<p><?php esc_html_e( 'No grounded suggestions from the current local records.', 'wpcable' ); ?></p>
			<p class="description"><?php esc_html_e( 'Cues appear for completed + stale activity, repeat completed work, preferred + completed, or multi-project clients. Empty is honest.', 'wpcable' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $groups as $group ) : ?>
			<section class="wpcable-opp-group wpcable-table-card">
				<header>
					<h2><?php echo esc_html( $group['title'] ); ?></h2>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: cue count in this group */
								_n( '%d client in this cue type', '%d clients in this cue type', count( $group['items'] ), 'wpcable' ),
								count( $group['items'] )
							)
						);
						?>
						— <?php esc_html_e( 'not a confirmed need', 'wpcable' ); ?>
					</p>
				</header>
				<table class="wpcable-opp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rank', 'wpcable' ); ?></th>
							<th><?php esc_html_e( 'Client', 'wpcable' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'wpcable' ); ?></th>
							<th><?php esc_html_e( 'Why this fired', 'wpcable' ); ?></th>
							<th><?php esc_html_e( 'Action', 'wpcable' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $group['items'] as $index => $cue ) : ?>
							<tr class="wpcable-opp-card--<?php echo esc_attr( $cue['confidence'] ); ?>">
								<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
								<td>
									<strong><?php echo esc_html( $cue['client_name'] ); ?></strong>
									<div class="description"><?php echo esc_html( sprintf( __( 'Client %d', 'wpcable' ), (int) $cue['client_id'] ) ); ?></div>
								</td>
								<td><span class="wpcable-badge"><?php echo esc_html( strtoupper( $cue['confidence'] ) ); ?></span></td>
								<td>
									<?php echo esc_html( isset( $cue['reasons'][0] ) ? $cue['reasons'][0] : '' ); ?>
									<?php if ( count( $cue['reasons'] ) > 1 ) : ?>
										<details>
											<summary><?php esc_html_e( 'More evidence', 'wpcable' ); ?></summary>
											<ul class="wpcable-opp-reasons">
												<?php foreach ( array_slice( $cue['reasons'], 1 ) as $reason ) : ?>
													<li><?php echo esc_html( $reason ); ?></li>
												<?php endforeach; ?>
											</ul>
										</details>
									<?php endif; ?>
									<p class="description">
										<?php echo esc_html( $cue['disclaimer'] ); ?>
										<?php
										if ( ! empty( $cue['evidence']['source_task_ids'] ) ) {
											echo ' · ' . esc_html(
												sprintf(
													/* translators: %s: comma-separated task ids */
													__( 'Source projects: %s', 'wpcable' ),
													implode( ', ', array_map( 'strval', $cue['evidence']['source_task_ids'] ) )
												)
											);
										}
										?>
									</p>
								</td>
								<td>
									<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_clients&client_id=' . (int) $cue['client_id'] ) ); ?>">
										<?php esc_html_e( 'Open client', 'wpcable' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>

	<section class="wpcable-unsupported">
		<h2><?php esc_html_e( 'Not available from the current import', 'wpcable' ); ?></h2>
		<ul>
			<?php foreach ( $blocked as $item ) : ?>
				<li>
					<strong><?php echo esc_html( $item['title'] ); ?></strong>
					— <?php echo esc_html( $item['reason'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
