<?php
/**
 * Database handler for When Last Login
 *
 * Handles table creation, migrations, and database operations.
 *
 * @package When_Last_Login
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Include migration helper.
require_once __DIR__ . "/migration-helper.php";
/**
 * Class WLL_DB
 *
 * Database operations for When Last Login.
 *
 * @since 1.3.0
 */
class WLL_DB {

	/**
	 * Current database version.
	 *
	 * @since  1.3.0
	 * @access private
	 * @var    string
	 */
	private static $db_version = '1.3.0';

	/**
	 * Option name for database version.
	 *
	 * @since  1.3.0
	 * @access private
	 * @var    string
	 */
	const VERSION_OPTION = 'wll_db_version';

	/**
	 * Summary table name (per-user aggregated data).
	 *
	 * @since  1.3.0
	 * @access public
	 * @var    string
	 */
	const SUMMARY_TABLE = 'when_last_login';

	/**
	 * Login records table name (individual logins).
	 *
	 * @since  1.3.0
	 * @access public
	 * @var    string
	 */
	const LOGIN_RECORDS_TABLE = 'wll_login_records';

	/**
	 * Initialize database handler.
	 *
	 * @since  1.3.0
	 * @access public
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'check_db_version' ) );
		add_action( 'wll_migrate_login_records', array( __CLASS__, 'migrate_batch' ) );
	}

	/**
	 * Check and run database migrations if needed.
	 *
	 * @since  1.3.0
	 * @access public
	 */
	public static function check_db_version() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_version = get_option( self::VERSION_OPTION, '1.0.0' );

		// Upgrade to 1.3.0 - Create tables and migrate data.
		if ( version_compare( $current_version, '1.3.0', '<' ) ) {
			self::upgrade_1_3_0();
		}

		/**
		 * Fires after database version check.
		 *
		 * @since 1.3.0
		 *
		 * @param string $current_version Current database version.
		 */
		do_action( 'wll_db_upgrade_check', $current_version );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  string $table Table name constant (SUMMARY_TABLE or LOGIN_RECORDS_TABLE).
	 * @return string        Full table name.
	 */
	public static function get_table_name( $table = self::SUMMARY_TABLE ) {
		global $wpdb;
		return $wpdb->prefix . $table;
	}

	/**
	 * Check if a table exists.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  string $table Table name constant.
	 * @return bool         True if table exists.
	 */
	public static function table_exists( $table ) {
		global $wpdb;

		$table_name = self::get_table_name( $table );
		$result     = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		return $result === $table_name;
	}

	/**
	 * Create all required tables.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @return bool True on success.
	 */
	public static function create_tables() {
		$summary_created = self::create_summary_table();
		$records_created = self::create_login_records_table();

		return $summary_created && $records_created;
	}

	/**
	 * Create the summary table (per-user aggregated data).
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @return bool True on success.
	 */
	public static function create_summary_table() {
		global $wpdb;

		$table_name = self::get_table_name( self::SUMMARY_TABLE );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			last_login datetime NOT NULL,
			login_count bigint(20) unsigned DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY last_login (last_login),
			KEY login_count (login_count)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return self::table_exists( self::SUMMARY_TABLE );
	}

	/**
	 * Create the login records table (individual logins).
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @return bool True on success.
	 */
	public static function create_login_records_table() {
		global $wpdb;

		$table_name = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
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
		dbDelta( $sql );

		return self::table_exists( self::LOGIN_RECORDS_TABLE );
	}

	/**
	 * Get users by IP address.
	 *
	 * Useful for security audits and detecting duplicate accounts.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  string $ip_address IP address to search.
	 * @param  int    $limit      Maximum results.
	 * @return array              User IDs and login times.
	 */
	public static function get_users_by_ip( $ip_address, $limit = 100 ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT user_id, MAX(login_time) as last_login
				 FROM $records_table
				 WHERE ip_address = %s
				 GROUP BY user_id
				 ORDER BY last_login DESC
				 LIMIT %d",
				sanitize_text_field( $ip_address ),
				absint( $limit )
			)
		);
	}

	/**
	 * Get login statistics by browser.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $days Number of days to analyze.
	 * @return array    Browser usage counts.
	 */
	public static function get_browser_stats( $days = 30 ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT browser, COUNT(*) as count
				 FROM $records_table
				 WHERE login_time >= %s
				 AND browser IS NOT NULL AND browser != ''
				 GROUP BY browser
				 ORDER BY count DESC",
				$cutoff_date
			)
		);
	}

	/**
	 * Get login statistics by OS.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $days Number of days to analyze.
	 * @return array    OS usage counts.
	 */
	public static function get_os_stats( $days = 30 ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT os, COUNT(*) as count
				 FROM $records_table
				 WHERE login_time >= %s
				 AND os IS NOT NULL AND os != ''
				 GROUP BY os
				 ORDER BY count DESC",
				$cutoff_date
			)
		);
	}

	/**
	 * Get login statistics by device type.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $days Number of days to analyze.
	 * @return array    Device usage counts.
	 */
	public static function get_device_stats( $days = 30 ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device, COUNT(*) as count
				 FROM $records_table
				 WHERE login_time >= %s
				 AND device IS NOT NULL AND device != ''
				 GROUP BY device
				 ORDER BY count DESC",
				$cutoff_date
			)
		);
	}

	/**
	 * Get daily login counts for a date range.
	 *
	 * Useful for analytics dashboards.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  string $date_from Start date (Y-m-d).
	 * @param  string $date_to   End date (Y-m-d).
	 * @return array            Daily login counts.
	 */
	public static function get_daily_login_stats( $date_from, $date_to ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(login_time) as date, COUNT(*) as count
				 FROM $records_table
				 WHERE login_time >= %s
				 AND login_time <= %s
				 GROUP BY DATE(login_time)
				 ORDER BY date ASC",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			)
		);
	}

	/**
	 * Get unique logins per day (distinct users).
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  string $date_from Start date (Y-m-d).
	 * @param  string $date_to   End date (Y-m-d).
	 * @return array            Daily unique user counts.
	 */
	public static function get_daily_unique_logins( $date_from, $date_to ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(login_time) as date, COUNT(DISTINCT user_id) as unique_users
				 FROM $records_table
				 WHERE login_time >= %s
				 AND login_time <= %s
				 GROUP BY DATE(login_time)
				 ORDER BY date ASC",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			)
		);
	}

	/**
	 * Upgrade to version 1.3.0.
	 *
	 * Creates tables and schedules data migration.
	 *
	 * @since  1.3.0
	 * @access private
	 */
	private static function upgrade_1_3_0() {
		// Create tables.
		$tables_created = self::create_tables();

		if ( ! $tables_created ) {
			error_log( 'WLL: Failed to create database tables during 1.3.0 upgrade' );
			return;
		}

		// Populate summary table from user meta.
		self::populate_summary_table();

		// Check if there are posts to migrate.
		$posts_count = self::count_posts_to_migrate();

		if ( $posts_count > 0 ) {
			// Store migration status.
			update_option( 'wll_migration_status', array(
				'total'     => $posts_count,
				'migrated'  => 0,
				'started'   => current_time( 'mysql' ),
				'status'    => 'pending',
			) );

			// Schedule migration.
			wll_schedule_migration_batch( 30 );

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
		update_option( self::VERSION_OPTION, WLL_VER );

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
	 * @since  1.3.0
	 * @access public
	 */
	public static function populate_summary_table() {
		global $wpdb;

		$summary_table = self::get_table_name( self::SUMMARY_TABLE );

		// Ensure batch size constant is defined.
		$batch_size = defined( "WLL_BATCH_SIZE" ) ? WLL_BATCH_SIZE : 50;

		// Use offset-based pagination for reliability.
		$offset = 0;

		while ( true ) {
			$users = get_users( array(
				'meta_key'     => 'when_last_login',
				'meta_compare' => 'EXISTS',
				'fields'       => array( 'ID' ),
				'number'       => $batch_size,
				'offset'       => $offset,
			) );

			if ( empty( $users ) ) {
				break;
			}

			foreach ( $users as $user ) {
				$last_login = get_user_meta( $user->ID, 'when_last_login', true );
				$login_count = get_user_meta( $user->ID, 'when_last_login_count', true );

				if ( empty( $last_login ) ) {
					continue;
				}

				// Convert timestamp to datetime.
				$login_time = is_numeric( $last_login )
					? gmdate( 'Y-m-d H:i:s', $last_login )
					: $last_login;

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

			$offset += $batch_size;

			// Prevent timeout on large sites.
			if ( 0 === ( $offset / $batch_size ) % 10 ) {
				sleep( 1 );
			}
		}
	}

	/**
	 * Count posts to migrate from custom post type.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @return int Number of posts to migrate.
	 */
	public static function count_posts_to_migrate() {
		$args = array(
			'post_type'      => 'wll_records',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Migrate a batch of records.
	 *
	 * @since  1.3.0
	 * @access public
	 */
	public static function migrate_batch() {
		global $wpdb;

		$status = get_option( 'wll_migration_status', array() );

		if ( empty( $status ) || ! isset( $status['status'] ) || $status['status'] === 'complete' ) {
			return;
		}

		// Check migration lock to prevent concurrent runs.
		if ( get_transient( 'wll_migration_lock' ) ) {
			return;
		}
		set_transient( 'wll_migration_lock', true, 5 * MINUTE_IN_SECONDS );

		// Ensure batch size constant is defined.
		$default_batch = defined( "WLL_BATCH_SIZE" ) ? WLL_BATCH_SIZE : 50;
		$batch_size = apply_filters( 'wll_migration_batch_size', $default_batch );

		// Direct SQL avoids WP_Query overhead (filters, object cache, extra joins).
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT %d",
				'wll_records',
				$batch_size
			)
		);

		if ( empty( $post_ids ) ) {
			$status['status']    = 'complete';
			$status['completed'] = current_time( 'mysql' );
			update_option( 'wll_migration_status', $status );
			return;
		}

		$records_table   = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$ids_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// Fetch post data and IP meta in a single JOIN query instead of N+1 calls.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_date,
				        pm.meta_value AS ip_address
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				        ON pm.post_id = p.ID AND pm.meta_key = 'wll_user_ip_address'
				 WHERE p.ID IN ($ids_placeholder)",
				$post_ids
			)
		);

		// Build a single bulk INSERT instead of one query per record.
		$placeholders = array();
		$values       = array();

		foreach ( $posts as $post ) {
			$placeholders[] = '(%d, %s, %s)';
			$values[]       = absint( $post->post_author );
			$values[]       = $post->post_date;
			$values[]       = sanitize_text_field( (string) $post->ip_address );
		}

		if ( ! empty( $placeholders ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $records_table (user_id, login_time, ip_address) VALUES "
					. implode( ', ', $placeholders ),
					$values
				)
			);
		}

		// Delete migrated posts and meta immediately per batch.
		// This distributes the deletion load and avoids the separate cleanup step.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($ids_placeholder)",
				$post_ids
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts} WHERE ID IN ($ids_placeholder)",
				$post_ids
			)
		);

		$status['migrated'] += count( $posts );

		// Check whether any posts remain before deciding to schedule another batch.
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
				'wll_records'
			)
		);

		if ( $remaining > 0 ) {
			$status['status'] = 'in_progress';
			update_option( 'wll_migration_status', $status );
			wll_schedule_migration_batch( 5 );
		} else {
			$status['status']    = 'complete';
			$status['completed'] = current_time( 'mysql' );
			update_option( 'wll_migration_status', $status );
		}

		// Release lock after batch.
		delete_transient( 'wll_migration_lock' );
	}

	/**
	 * Record a user login.
	 *
	 * Updates summary table and inserts into login records.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int    $user_id    User ID.
	 * @param  array  $login_data Optional. Additional login data.
	 * @return bool              True on success.
	 */
	public static function record_login( $user_id, $login_data = array(), $include_record = true ) {
		global $wpdb;

		$summary_table = self::get_table_name( self::SUMMARY_TABLE );
		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );

		$now = current_time( 'mysql' );

		// Update summary table (upsert).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $summary_table (user_id, last_login, login_count)
				 VALUES (%d, %s, 1)
				 ON DUPLICATE KEY UPDATE
				 last_login = VALUES(last_login),
				 login_count = login_count + 1",
				$user_id,
				$now
			)
		);

		if ( ! $include_record ) {
			return true;
		}

		// Insert into login records.
		$defaults = array(
			'user_id'    => $user_id,
			'login_time' => $now,
			'ip_address' => '',
			'user_agent' => '',
			'browser'    => '',
			'os'         => '',
			'device'     => '',
		);

		$data = wp_parse_args( $login_data, $defaults );

		$wpdb->insert(
			$records_table,
			array(
				'user_id'    => absint( $data['user_id'] ),
				'login_time' => sanitize_text_field( $data['login_time'] ),
				'ip_address' => sanitize_text_field( $data['ip_address'] ),
				'user_agent' => sanitize_text_field( $data['user_agent'] ),
				'browser'    => sanitize_text_field( $data['browser'] ),
				'os'         => sanitize_text_field( $data['os'] ),
				'device'     => sanitize_text_field( $data['device'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Get user's last login from summary table.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $user_id User ID.
	 * @return object|null   Last login data.
	 */
	public static function get_last_login( $user_id ) {
		global $wpdb;

		$summary_table = self::get_table_name( self::SUMMARY_TABLE );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $summary_table WHERE user_id = %d",
				absint( $user_id )
			)
		);
	}

	/**
	 * Get user's login count.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $user_id User ID.
	 * @return int         Login count.
	 */
	public static function get_login_count( $user_id ) {
		$row = self::get_last_login( $user_id );
		return $row ? absint( $row->login_count ) : 0;
	}

	/**
	 * Get login history for a user.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  array $args Query arguments.
	 * @return array       Login records.
	 */
	public static function get_login_history( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id'   => 0,
			'per_page'  => 50,
			'page'      => 1,
			'orderby'   => 'login_time',
			'order'     => 'DESC',
			'date_from' => '',
			'date_to'   => '',
		);

		$args = wp_parse_args( $args, $defaults );
		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );

		$where = 'WHERE 1=1';
		$prepare = array();

		if ( ! empty( $args['user_id'] ) ) {
			$where .= ' AND user_id = %d';
			$prepare[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where .= ' AND login_time >= %s';
			$prepare[] = sanitize_text_field( $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where .= ' AND login_time <= %s';
			$prepare[] = sanitize_text_field( $args['date_to'] );
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$sql = "SELECT * FROM $records_table $where ORDER BY $orderby LIMIT %d OFFSET %d";
		$prepare[] = absint( $args['per_page'] );
		$prepare[] = absint( $offset );

		if ( ! empty( $prepare ) ) {
			$sql = $wpdb->prepare( $sql, $prepare );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get inactive users.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int   $days     Days of inactivity.
	 * @param  int   $limit    Maximum users to return.
	 * @param  array $excluded Excluded user IDs.
	 * @return array           User IDs.
	 */
	public static function get_inactive_users( $days = 90, $limit = 100, $excluded = array() ) {
		global $wpdb;

		$summary_table = self::get_table_name( self::SUMMARY_TABLE );
		$threshold_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$sql = "SELECT user_id FROM $summary_table WHERE last_login < %s";
		$prepare = array( $threshold_date );

		if ( ! empty( $excluded ) ) {
			$sql .= ' AND user_id NOT IN (' . implode( ',', array_fill( 0, count( $excluded ), '%d' ) ) . ')';
			$prepare = array_merge( $prepare, array_map( 'absint', $excluded ) );
		}

		$sql .= ' ORDER BY last_login ASC LIMIT %d';
		$prepare[] = absint( $limit );

		return $wpdb->get_col( $wpdb->prepare( $sql, $prepare ) );
	}

	/**
	 * Clean up old login records.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $days Days to keep.
	 * @return int      Number of records deleted.
	 */
	public static function cleanup_old_records( $days = 90 ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $records_table WHERE login_time < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Delete specific login records by ID.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  array $ids Record IDs to delete.
	 * @return int       Number of records deleted.
	 */
	public static function delete_records( $ids ) {
		global $wpdb;

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return 0;
		}

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		$ids = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $records_table WHERE id IN ($placeholders)",
				$ids
			)
		);
	}

	/**
	 * Clean up migrated posts.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @return int Number of posts deleted.
	 */
	public static function cleanup_migrated_posts() {
		$migration_status = get_option( 'wll_migration_status', array() );

		if ( empty( $migration_status ) || ! isset( $migration_status['status'] ) || $migration_status['status'] !== 'complete' ) {
			return 0;
		}

		global $wpdb;

		$sql = $wpdb->prepare(
			"DELETE p, pm FROM {$wpdb->posts} p 
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID 
			 WHERE p.post_type = %s",
			'wll_records'
		);

		$result = $wpdb->query( $sql );

		/**
		 * Fires after cleanup.
		 *
		 * @since 1.3.0
		 *
		 * @param int $result Number of posts deleted.
		 */
		do_action( 'wll_cleanup_migrated_posts', $result );

		return $result;
	}

	/**
	 * Delete login records older than a specified number of days.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @param  int $days Number of days.
	 * @return int      Number of records deleted.
	 */
	public static function delete_old_records( $days = 0 ) {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );
		if ( $days <= 0 ) {
			$days = WLL_CLEANUP_DAYS;
		}
		$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $records_table WHERE login_time < %s",
				$date_threshold
			)
		);
	}

	/**
	 * Delete all login records.
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 * @return int Number of records deleted.
	 */
	public static function delete_all_records() {
		global $wpdb;

		$records_table = self::get_table_name( self::LOGIN_RECORDS_TABLE );

		return $wpdb->query( "DELETE FROM {$records_table}" );
	}
}

// Initialize.
WLL_DB::init();