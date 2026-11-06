<?php
/**
 * Opportunities: grounded suggestions only.
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
?>
<div class="wrap wpcable_wrap wpcable-workspace wpcable-workspace--opportunities" data-workspace="opportunities">
	<h1><?php esc_html_e( 'Suggested opportunities', 'wpcable' ); ?></h1>
	<p class="wpcable-lede">
		<?php esc_html_e( 'These are grounded suggestions from imported records — not confirmed client needs. Each card lists the source rows, a confidence label, and why the cue fired. Nothing is auto-sent.', 'wpcable' ); ?>
	</p>

	<div class="wpcable-callout wpcable-callout--info">
		<?php esc_html_e( 'Shown only when evidence exists from imported fields. Chat, files, websites, per-task reviews, and global hiring are not in this plugin.', 'wpcable' ); ?>
	</div>

	<?php if ( empty( $cues ) ) : ?>
		<div class="wpcable-empty">
			<p><?php esc_html_e( 'No grounded suggestions from the current local records.', 'wpcable' ); ?></p>
			<p class="description"><?php esc_html_e( 'Cues appear for completed + stale activity, repeat completed work, preferred + completed, or multi-project clients. Empty is honest.', 'wpcable' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpcable-opp-grid">
			<?php foreach ( $cues as $cue ) : ?>
				<article class="wpcable-opp-card wpcable-opp-card--<?php echo esc_attr( $cue['confidence'] ); ?>">
					<header>
						<span class="wpcable-badge"><?php echo esc_html( strtoupper( $cue['confidence'] ) ); ?></span>
						<h2><?php echo esc_html( $cue['title'] ); ?></h2>
					</header>
					<p>
						<strong><?php echo esc_html( $cue['client_name'] ); ?></strong>
						<span class="description"> · <?php echo esc_html( sprintf( __( 'client_id %d', 'wpcable' ), (int) $cue['client_id'] ) ); ?></span>
					</p>
					<ul>
						<?php foreach ( $cue['reasons'] as $reason ) : ?>
							<li><?php echo esc_html( $reason ); ?></li>
						<?php endforeach; ?>
					</ul>
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
					<p>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=codeable_clients&client_id=' . (int) $cue['client_id'] ) ); ?>">
							<?php esc_html_e( 'Open client', 'wpcable' ); ?>
						</a>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
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

	<?php codeable_last_fetch_info(); ?>
</div>
