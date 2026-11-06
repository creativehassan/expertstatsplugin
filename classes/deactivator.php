<?php
/**
 *
 * @package wpcable
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.0.3
 * @package    wpcable
 * @author     Justin Frydman <justin.frydman@gmail.com>
 */
class WpCable_deactivator {

	/**
	 * Fired on plugin deactivation.
	 *
	 * Removes all plugin tables and wp_option data
	 *
	 * @since    0.0.3
	 */
	public static function deactivate() {
		self::remove_cronjobs();
	}

	/**
	 * Remove all scheduled jobs
	 *
	 * @since    0.0.3
	 */
	private static function remove_cronjobs() {

		$timestamp = wp_next_scheduled( 'wpcable_cronjob' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpcable_cronjob' );
		}
		wp_clear_scheduled_hook( 'wpcable_cronjob' );
		wp_clear_scheduled_hook( 'wpcable_history_backfill_tick' );
	}

}
