<?php
/**
 * Migration helper — schedules batches via Action Scheduler.
 *
 * Falls back to WP-Cron if Action Scheduler is not available.
 *
 * @package When_Last_Login
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule the next migration batch using Action Scheduler if available,
 * falling back to WP-Cron.
 *
 * @since 1.3.0
 */
function wll_schedule_migration_batch( $delay = 5 ) {
	$action_hook = 'wll_migrate_login_records';

	if ( class_exists( 'ActionScheduler' ) ) {
		if ( ! as_next_scheduled_action( $action_hook ) ) {
			as_schedule_single_action( time() + $delay, $action_hook );
		}
	} else {
		// Fallback to WP-Cron.
		if ( ! wp_next_scheduled( $action_hook ) ) {
			wp_schedule_single_event( time() + $delay, $action_hook );
		}
	}
}
