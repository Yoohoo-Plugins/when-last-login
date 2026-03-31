<?php
/**
 * Upgrade script for version 1.3.0
 *
 * Creates database tables and migrates data from custom post type.
 *
 * @package When_Last_Login
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the 1.3.0 upgrade.
 *
 * Creates both database tables and schedules data migration.
 *
 * @since 1.3.0
 */
function wll_upgrade_1_3_0() {
	global $wpdb;

	// Check if already upgraded.
	$db_version = get_option( 'wll_db_version', '1.0.0' );
	if ( version_compare( $db_version, '1.3.0', '>=' ) ) {
		return;
	}

	$charset_collate = $wpdb->get_charset_collate();

	// Create summary table (per-user aggregated data).
	$summary_table = $wpdb->prefix . 'when_last_login';
	$sql_summary = "CREATE TABLE $summary_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		last_login datetime NOT NULL,
		login_count bigint(20) unsigned DEFAULT 1,
		PRIMARY KEY (id),
		UNIQUE KEY user_id (user_id),
		KEY last_login (last_login),
		KEY login_count (login_count)
	) $charset_collate;";

	// Create login records table (individual logins).
	$records_table = $wpdb->prefix . 'wll_login_records';
	$sql_records = "CREATE TABLE $records_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		login_time datetime NOT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent varchar(255) DEFAULT NULL,
		browser varchar(50) DEFAULT NULL,
		os varchar(50) DEFAULT NULL,
		device varchar(20) DEFAULT NULL,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY login_time (login_time),
		KEY user_id_login_time (user_id, login_time),
		KEY ip_address (ip_address),
		KEY browser (browser),
		KEY os (os),
		KEY device (device)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_summary );
	dbDelta( $sql_records );

	// Verify tables were created.
	$summary_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $summary_table ) ) === $summary_table;
	$records_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $records_table ) ) === $records_table;

	if ( ! $summary_exists || ! $records_exists ) {
		error_log( 'WLL: Failed to create database tables during 1.3.0 upgrade' );
		return;
	}

	// Populate summary table from existing user meta.
	wll_populate_summary_from_user_meta();

	// Count posts to migrate.
	$args = array(
		'post_type'      => 'wll_records',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	$query = new WP_Query( $args );
	$posts_count = $query->found_posts;

	if ( $posts_count > 0 ) {
		// Store migration status.
		update_option( 'wll_migration_status', array(
			'total'     => $posts_count,
			'migrated'  => 0,
			'started'   => current_time( 'mysql' ),
			'status'    => 'pending',
		) );

		// Schedule migration.
		if ( ! wp_next_scheduled( 'wll_migrate_login_records' ) ) {
			wp_schedule_single_event( time() + 30, 'wll_migrate_login_records' );
		}
	} else {
		// No posts to migrate.
		update_option( 'wll_migration_status', array(
			'total'     => 0,
			'migrated'  => 0,
			'started'   => current_time( 'mysql' ),
			'completed' => current_time( 'mysql' ),
			'status'    => 'complete',
		) );
	}

	// Update database version.
	update_option( 'wll_db_version', '1.3.0' );

	/**
	 * Fires after 1.3.0 upgrade completes.
	 *
	 * @since 1.3.0
	 */
	do_action( 'wll_upgrade_1_3_0_complete' );
}

/**
 * Populate summary table from existing user meta.
 *
 * @since 1.3.0
 */
function wll_populate_summary_from_user_meta() {
	global $wpdb;

	$summary_table = $wpdb->prefix . 'when_last_login';

	// Get all users with login data.
	$args = array(
		'meta_key'     => 'when_last_login',
		'meta_compare' => 'EXISTS',
		'fields'       => array( 'ID' ),
		'number'       => 500,
	);

	$page = 1;
	while ( true ) {
		$args['paged'] = $page;
		$users = get_users( $args );

		if ( empty( $users ) ) {
			break;
		}

		foreach ( $users as $user ) {
			$last_login_ts = get_user_meta( $user->ID, 'when_last_login', true );
			$login_count    = get_user_meta( $user->ID, 'when_last_login_count', true );

			if ( empty( $last_login_ts ) ) {
				continue;
			}

			// Convert timestamp to datetime.
			$login_time = is_numeric( $last_login_ts )
				? gmdate( 'Y-m-d H:i:s', $last_login_ts )
				: $last_login_ts;

			// Upsert into summary table.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $summary_table (user_id, last_login, login_count)
					 VALUES (%d, %s, %d)
					 ON DUPLICATE KEY UPDATE
					 last_login = VALUES(last_login),
					 login_count = VALUES(login_count)",
					$user->ID,
					$login_time,
					absint( $login_count ) ?: 1
				)
			);
		}

		$page++;

		// Prevent timeout on large sites.
		if ( $page % 10 === 0 ) {
			sleep( 1 );
		}
	}
}

/**
 * Migrate a batch of records from posts to table.
 *
 * @since 1.3.0
 */
function wll_migrate_records_batch() {
	global $wpdb;

	$status = get_option( 'wll_migration_status', array() );

	if ( empty( $status ) || $status['status'] === 'complete' ) {
		return;
	}

	$batch_size = apply_filters( 'wll_migration_batch_size', 500 );
	$records_table = $wpdb->prefix . 'wll_login_records';

	$args = array(
		'post_type'      => 'wll_records',
		'post_status'    => 'any',
		'posts_per_page' => $batch_size,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);

	$query = new WP_Query( $args );
	$post_ids = $query->posts;

	if ( empty( $post_ids ) ) {
		$status['status']    = 'complete';
		$status['completed'] = current_time( 'mysql' );
		update_option( 'wll_migration_status', $status );
		return;
	}

	$migrated = 0;

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			continue;
		}

		$user_id    = $post->post_author;
		$login_time = $post->post_date;
		$ip_address = get_post_meta( $post_id, 'wll_user_ip_address', true );

		// Insert into login records table.
		$wpdb->insert(
			$records_table,
			array(
				'user_id'    => $user_id,
				'login_time' => $login_time,
				'ip_address' => $ip_address,
			),
			array( '%d', '%s', '%s' )
		);

		$migrated++;
	}

	// Update status.
	$status['migrated'] += $migrated;
	$status['status']    = 'in_progress';
	update_option( 'wll_migration_status', $status );

	// Schedule next batch.
	if ( $migrated > 0 ) {
		wp_schedule_single_event( time() + 10, 'wll_migrate_login_records' );
	} else {
		$status['status']    = 'complete';
		$status['completed'] = current_time( 'mysql' );
		update_option( 'wll_migration_status', $status );
	}
}

// Hook up the migration.
add_action( 'wll_migrate_login_records', 'wll_migrate_records_batch' );