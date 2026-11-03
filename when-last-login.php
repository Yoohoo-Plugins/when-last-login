<?php
/*
Plugin Name: When Last Login
Plugin URI: https://whenlastlogin.com
Description: See when a user logs into your WordPress site.
Version: 1.3.0
Author: Yoohoo Plugins
Author URI: https://yoohooplugins.com
Text Domain: when-last-login
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use geertw\IpAnonymizer\IpAnonymizer;

define( 'WLL_VER', '1.3.0' );
// Constants for magic numbers.
define( 'WLL_BATCH_SIZE', 500 );
define( 'WLL_CLEANUP_DAYS', 90 );
define( 'WLL_WIDGET_USER_COUNT', 3 );


class When_Last_Login {

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /**
    * Initializes the plugin by setting localization, filters, and administration functions.
    */
    private function __construct() {        

      define( 'WLL_BASENAME', plugin_basename( __FILE__ ) );
      define( 'WLL_DIR_PATH', plugin_dir_path( __FILE__ ) );
      define( 'WLL_PLUGIN', WP_PLUGIN_URL . '/when-last-login' );

      $settings = get_option( 'wll_settings' );

      include WLL_DIR_PATH . '/includes/lib/IpAnonymizer.php';
      include WLL_DIR_PATH . '/includes/privacy-policy.php';
      include WLL_DIR_PATH . '/includes/class-wll-db.php';

      add_action( 'admin_init', array( $this, 'admin_init' ) );
      add_action( 'plugins_loaded', array( $this, 'text_domain' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'load_js_for_notice' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_widget_scripts' ) );

      //Create the custom meta upon login
      add_action( 'wp_login', array( $this, 'last_login'), 10, 2 );
      add_action( 'user_register', array( $this, 'wll_user_register' ), 10, 1 );
      add_action( 'deleted_user', array( $this, 'delete_user_login_data' ), 10, 1 );
      add_action( 'two_factor_user_authenticated', array( $this, 'two_factor_user_authenticated' ), 10, 2 );

      //Admin actions - only load if not in streamline mode
      $hide_admin = isset( $settings['hide_admin_menu'] ) && intval( $settings['hide_admin_menu'] ) === 1;

      if ( ! $hide_admin ) {
        add_action( 'wp_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );
        add_action( 'admin_notices', array( $this, 'update_notice' ) );
      }

      add_action( 'wp_ajax_wll_hide_subscription_notice', array( $this, 'wll_hide_subscription_notice' ) );
      add_action( 'wp_ajax_wll_check_migration_status', array( $this, 'wll_check_migration_status' ) );
      add_action( 'wp_ajax_wll_migration_runner_status', array( $this, 'wll_migration_runner_status' ) );
      add_action( 'wp_ajax_wll_migration_run_batch', array( $this, 'wll_migration_run_batch' ) );
      add_action( 'wp_ajax_wll_migration_reset', array( $this, 'wll_migration_reset' ) );
      add_action( 'wp_ajax_wll_fetch_widget_data', array( $this, 'wll_fetch_widget_data_ajax' ) );
      add_action( 'admin_menu', array( $this, 'wll_register_migration_runner_page' ), 9 );
      add_action( 'admin_notices', array( $this, 'wll_migration_needed_notice' ) );

      //Setting up columns.
      add_filter( 'manage_users_columns', array( $this, 'column_header'), 10, 1 );
      add_action( 'manage_users_custom_column', array( $this, 'column_data'), 15, 3 );
      add_filter( 'manage_users_sortable_columns', array( $this, 'column_sortable' ) );
      add_action( 'pre_get_users', array( $this, 'sort_by_login_date') );

      //Integration for Paid Memberships Pro
      add_action( 'pmpro_memberslist_extra_cols_header', array( $this, 'pmpro_memberlist_add_header' ) );
      add_action( 'pmpro_memberslist_extra_cols_body', array( $this, 'pmpro_memberlist_add_column_data' ) );
      add_filter( 'pmpro_memberslist_csv_extra_columns', array( $this, 'pmpro_csv_export_columns' ) );
      add_filter( 'pmpro_memberslist_csv_extra_column_data', array( $this, 'pmpro_csv_export_row' ), 10, 2 );
      if ( ! $hide_admin ) {
        add_action( 'admin_menu', array( $this, 'wll_settings_page' ), 9 );
      } else {
        //Streamline mode: still expose the settings under Settings > When Last Login so it can be turned back off.
        add_action( 'admin_menu', array( $this, 'wll_settings_page_streamlined' ), 9 );
      }
      add_action( 'admin_init', array( $this, 'wll_settings_page_head' ) );
      add_action( 'admin_init', array( $this, 'wll_automatically_remove_logs' ) );
      add_action( 'admin_notices', array( $this, 'wll_admin_notices' ) );
      add_filter( 'plugin_row_meta', array( $this, 'wll_plugin_row_meta' ), 10, 2 );
      add_filter( 'plugin_action_links_' . WLL_BASENAME, array( $this, 'wll_plugin_action_links' ), 10, 2 );

      /**
      * Multisite support
      */
      add_action( 'wp_network_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );
      add_filter( 'wpmu_users_columns', array( $this, 'column_header'), 10, 1 );
      add_action( 'wpmu_users_custom_column', array( $this, 'column_data'), 15, 3 );
    }

    /**
    * Creates or returns an instance of this class.
    *
    * @return  When_Last_Login A single instance of this class.
    */
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    } // end get_instance;

    /**
    * When Last plugin functions.
    */
    public static function admin_init(){
    //init function
      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }

      do_action( 'wll_upgrade_check' );

      $current_version = floatval( get_option( 'wll_current_version' ) );

      // Clean up legacy data from versions before 1.0.
      // Note: This is retained for sites that may have skipped the 1.0 upgrade.
      if ( $current_version < 1.0 && ! empty( $current_version ) ) {
        global $wpdb;

        $delete_table = $wpdb->prefix . 'wll_login_attempts';
        $sql = "DROP TABLE IF EXISTS `$delete_table`";
        $wpdb->query( $sql );

        delete_transient( 'when_last_login_add_ons_page' );

        delete_option( 'wll_notice_hide' );
        delete_option( 'wll_notice_hide_1' );
        delete_option( 'wll_notice_hide_2' );

        update_option( 'wll_current_version', WLL_VER );
      }
    }

    public static function text_domain(){
      load_plugin_textdomain( 'when-last-login', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public static function update_notice(){

      if ( get_option( 'wll_notice_hide' ) !== '1' && isset( $_REQUEST['page'] ) && 'when-last-login-settings' === $_REQUEST['page'] ) {
        ?>
        <div class="notice notice-success  wll-update-notice-newsletter is-dismissible" >
        <h3><?php _e('Thank you for using When Last Login', 'when-last-login'); ?></h3>
        <p><?php printf( __( 'Please consider leaving an honest review for When Last Login by visiting %s', 'when-last-login' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/when-last-login/reviews/#new-post' ) . '" target="_blank">' . __( 'this link', 'when-last-login' ) . '</a>' ); ?></p>
        </div>
        <?php
      }
    }

        public function wll_hide_subscription_notice(){
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( -1 );
      }
      if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wll_hide_notice_nonce' ) ) {
        wp_die( __( 'Nonce is invalid', 'when-last-login' ) );
      }
      update_option( 'wll_notice_hide', '1' );
    }

    public function wll_check_migration_status() {
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( -1 );
      }
      check_ajax_referer( 'wll_migration_status', 'nonce' );
      $status = get_option( 'wll_migration_status', array() );
      wp_send_json_success( array(
        'complete' => empty( $status ) || ( isset( $status['status'] ) && $status['status'] === 'complete' ),
      ) );
    }

    /**
     * Register the migration runner as a hidden admin page.
     *
     * Linked from a notice on When Last Login screens when CPT records remain.
     *
     * @since 1.3.0
     */
    public function wll_register_migration_runner_page() {
      add_submenu_page(
        null,
        esc_html__( 'Migration Runner', 'when-last-login' ),
        '',
        'manage_options',
        'wll-migration-runner',
        array( $this, 'wll_migration_runner_callback' )
      );
    }

    /**
     * Notice on When Last Login admin pages when records still need migrating.
     *
     * @since 1.3.0
     */
    public function wll_migration_needed_notice() {
      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }

      $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
      if ( ! in_array( $page, array( 'when-last-login-settings', 'wll-login-records' ), true ) ) {
        return;
      }

      if ( ! class_exists( 'WLL_DB' ) ) {
        return;
      }

      $remaining = (int) WLL_DB::count_posts_to_migrate();
      if ( $remaining < 1 ) {
        return;
      }

      $url = admin_url( 'admin.php?page=wll-migration-runner' );
      ?>
      <div class="notice notice-warning">
        <p>
          <?php
          printf(
            esc_html(
              /* translators: %s: number of records */
              _n(
                'When Last Login needs to migrate %s login record to the new storage format.',
                'When Last Login needs to migrate %s login records to the new storage format.',
                $remaining,
                'when-last-login'
              )
            ),
            esc_html( number_format_i18n( $remaining ) )
          );
          ?>
          <a class="button button-primary" href="<?php echo esc_url( $url ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Run Migration', 'when-last-login' ); ?></a>
        </p>
      </div>
      <?php
    }

    /**
     * Migration Runner page callback.
     *
     * @since  1.3.0
     */
    public function wll_migration_runner_callback() {
      // Enqueue jQuery for AJAX.
      wp_enqueue_script( 'jquery' );

      // Localize script for AJAX nonce.
      wp_add_inline_script( 'jquery', '
        var wllMigration = { nonce: "' . wp_create_nonce( 'wll_migration_runner' ) . '", ajaxurl: "' . admin_url( 'admin-ajax.php' ) . '" };
      ', 'before' );

      // Add minimal CSS.
      echo '<style>
        .wll-migration-badge { display:inline-block; padding:2px 10px; border-radius:3px; font-size:12px; font-weight:bold; }
        .wll-status-complete { background:#d4edda; color:#155724; }
        .wll-status-running { background:#fff3cd; color:#856404; }
        .wll-status-pending { background:#d1ecf1; color:#0c5460; }
      </style>';

      include WLL_DIR_PATH . '/includes/settings/migration-runner.php';
    }

    /**
     * AJAX: Get migration runner status.
     *
     * @since  1.3.0
     */
    public function wll_migration_runner_status() {
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
      }

      $status    = get_option( 'wll_migration_status', array() );
      $migrated  = isset( $status['migrated'] ) ? (int) $status['migrated'] : 0;
      $total     = isset( $status['total'] ) ? (int) $status['total'] : 0;

      // Get live remaining count.
      $remaining = 0;
      if ( class_exists( 'WLL_DB' ) ) {
        $remaining = (int) WLL_DB::count_posts_to_migrate();
        if ( $remaining > 0 && $total > 0 ) {
          $total = $migrated + $remaining;
        }
      }

      wp_send_json_success( array(
        'status'    => isset( $status['status'] ) ? $status['status'] : 'unknown',
        'migrated'  => $migrated,
        'total'     => $total,
        'remaining' => $remaining,
        'complete'  => isset( $status['status'] ) && $status['status'] === 'complete',
      ) );
    }

    /**
     * AJAX: Run a single migration batch.
     *
     * @since  1.3.0
     */
    public function wll_migration_run_batch() {
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
      }

      if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wll_migration_runner' ) ) {
        wp_send_json_error( 'Invalid nonce' );
      }

      // Clear lock to allow manual batch run.
      delete_transient( 'wll_migration_lock' );

      // Run the batch directly (bypass cron).
      if ( class_exists( 'WLL_DB' ) ) {
        WLL_DB::migrate_batch();
      }

      $status    = get_option( 'wll_migration_status', array() );
      $migrated  = isset( $status['migrated'] ) ? (int) $status['migrated'] : 0;
      $total     = isset( $status['total'] ) ? (int) $status['total'] : 0;
      $remaining = 0;
      if ( class_exists( 'WLL_DB' ) ) {
        $remaining = (int) WLL_DB::count_posts_to_migrate();
        if ( $remaining > 0 && $total > 0 ) {
          $total = $migrated + $remaining;
        }
      }

      $is_complete = isset( $status['status'] ) && $status['status'] === 'complete';

      wp_send_json_success( array(
        'migrated_in_batch' => $migrated - ( isset( $_POST['prev_migrated'] ) ? (int) $_POST['prev_migrated'] : 0 ),
        'migrated'          => $migrated,
        'total'             => $total,
        'remaining'         => $remaining,
        'complete'          => $is_complete,
      ) );
    }

    /**
     * AJAX: Reset migration status.
     *
     * @since  1.3.0
     */
    public function wll_migration_reset() {
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
      }

      if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wll_migration_runner' ) ) {
        wp_send_json_error( 'Invalid nonce' );
      }

      delete_option( 'wll_db_version' );
      delete_option( 'wll_migration_status' );
      delete_transient( 'wll_migration_lock' );
      wp_clear_scheduled_hook( 'wll_migrate_login_records' );
      if ( function_exists( 'as_unschedule_all_actions' ) ) {
        as_unschedule_all_actions( 'wll_migrate_login_records' );
      }

      wp_send_json_success( 'Migration reset' );
    }


    public static function load_js_for_notice(){
      if( get_option( 'wll_notice_hide' ) !== '1'){
        wp_enqueue_script( 'wll_notice_update', plugins_url( 'js/notice-update.js', __FILE__ ), array( 'jquery' ), '1.0', false );

        wp_localize_script( 'wll_notice_update', 'wll_notice_update', array(
          		'ajaxurl' => admin_url( 'admin-ajax.php' ),
              'nonce' => wp_create_nonce( 'wll_hide_notice_nonce' )
        ) );
      }
      if( isset( $_GET['page'] ) && $_GET['page'] == 'when-last-login-settings' ){
        wp_enqueue_style( 'wll_admin_settings_styles', plugins_url( '/css/admin.css', __FILE__ ) );
      }
    }

	/**
	 * Track the login process for the Two Factor Authentication plugin.
	 *
	 * @since TBD
	 * 
	 * @param WP_User $user
	 * @param Two_Factor $two_factor (this is unused.)
	 */
	public static function two_factor_user_authenticated( $user, $two_factor ) {
		// Let's call last_login function to record the login.
		When_Last_Login::last_login( $user->user_login, $user );
	}

	/**
	 * Track the user's login timestamp and "All Time Record". 
	 *
	 * @param string $user_login The username that is logging in.
	 * @param WP_User $user The WordPress user object.
	 * @return void
	 */
     public static function last_login( $user_login, $user ) {

      $record_login = apply_filters( 'wll_record_login', true, $user, $user_login );

      // If filter isn't true, don't record login at all!
      if ( ! $record_login ) {
        return;
      }

      //get/update user meta 'when_last_login' on login and add time() to it.
      update_user_meta( $user->ID, 'when_last_login', time() );

      //get and update user meta 'when_last_login_count' on login for # of login counts. Thanks to Jarryd Long (@jarrydlong) for the assistance
      $wll_count = get_user_meta( $user->ID, 'when_last_login_count', true );

      if( $wll_count === false ){
        update_user_meta($user->ID, 'when_last_login_count', 1);
        $wll_new_value = 1;
      } else {
        $wll_new_value = intval($wll_count);
        $wll_new_value = $wll_new_value + 1;

        update_user_meta($user->ID, 'when_last_login_count', $wll_new_value);
      }

      // Get IP address.
      $wll_settings = get_option( 'wll_settings' );
      $ip = '';
      if( isset( $wll_settings['record_ip_address'] ) && intval( $wll_settings['record_ip_address'] ) == 1 ){
        $ip = When_Last_Login::wll_get_user_ip_address();
        update_user_meta( $user->ID, 'wll_user_ip_address', $ip );
      }

      // Save to database tables.
      if ( class_exists( 'WLL_DB' ) ) {
        $track_all_records = ! isset( $wll_settings['track_all_records'] ) || intval( $wll_settings['track_all_records'] ) === 1;

        $user_agent = filter_input( INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_SPECIAL_CHARS );
        $user_agent = $user_agent ? sanitize_text_field( wp_unslash( $user_agent ) ) : '';
        $browser    = $track_all_records ? When_Last_Login::parse_browser( $user_agent ) : '';
        $os         = $track_all_records ? When_Last_Login::parse_os( $user_agent ) : '';
        $device     = $track_all_records ? When_Last_Login::parse_device( $user_agent ) : '';

        WLL_DB::record_login( $user->ID, array(
          'ip_address' => $ip,
          'user_agent' => $user_agent,
          'browser'    => $browser,
          'os'         => $os,
          'device'     => $device,
        ), $track_all_records );
      }

      do_action( 'wll_logged_in_action', array( 'login_count' => $wll_new_value, 'user' => $user ), $wll_settings );

     }

     public function wll_user_register( $user_id ){

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && $wll_settings['record_ip_address'] == 1 ){
          
        $ip = When_Last_Login::wll_get_user_ip_address();
        update_user_meta( $user_id, 'wll_user_ip_address', $ip );

        }

        do_action( 'wll_register_action', $user_id, $wll_settings );

     }

    /**
     * Setup admin backend to display custom meta box for login count for admins.
     */
    public static function admin_dashboard_widget() {

        global $show_widget;

        $show_widget = apply_filters( 'when_last_login_show_admin_widget', true );

        if ( current_user_can( 'manage_options' ) && $show_widget ) {
            wp_add_dashboard_widget(
                'when_last_login_top_users',
                __( 'User Login Activity', 'when-last-login' ),
                array( 'When_Last_Login', 'admin_dashboard_widget_display' )
            );
        }
    }

    /**
     * Dashboard widget body.
     */
    public static function admin_dashboard_widget_display() {

        global $show_widget;

        if ( true != $show_widget ) {
            return;
        }

        $sort = self::get_widget_sort_preference();

        if ( is_network_admin() ) {
            $sites = get_sites();

            if ( is_array( $sites ) ) {
                foreach ( $sites as $site ) {
                    $blog_id      = (int) $site->blog_id;
                    $blog_details = get_site( $blog_id );

                    if ( ! $blog_details ) {
                        continue;
                    }

                    echo '<h3 style="text-align:center;margin:12px 0 8px;">' . esc_html( $blog_details->blogname ) . ' (<a href="' . esc_url( $blog_details->siteurl ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $blog_details->siteurl ) . '</a>)</h3>';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
                    echo self::render_widget_sort_controls( $blog_id, $sort );
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
                    echo self::render_widget_table( $sort, $blog_id );
                }

                $wll_widget_settings  = get_option( 'wll_settings', array() );
                $wll_widget_track_all = ! is_array( $wll_widget_settings ) || ! isset( $wll_widget_settings['track_all_records'] ) || intval( $wll_widget_settings['track_all_records'] ) === 1;
                echo '<a href="' . esc_url( admin_url( 'users.php?orderby=when_last_login&order=desc' ) ) . '">' . esc_html__( 'View All Users', 'when-last-login' ) . '</a>';
                if ( $wll_widget_track_all ) {
                    echo ' | <a href="' . esc_url( admin_url( 'admin.php?page=wll-login-records' ) ) . '">' . esc_html__( 'View Login Records', 'when-last-login' ) . '</a>';
                }
            }
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
            echo self::render_widget_sort_controls( 0, $sort );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
            echo self::render_widget_table( $sort, 0 );

            $wll_widget_settings  = get_option( 'wll_settings', array() );
            $wll_widget_track_all = ! is_array( $wll_widget_settings ) || ! isset( $wll_widget_settings['track_all_records'] ) || intval( $wll_widget_settings['track_all_records'] ) === 1;
            echo '<a href="' . esc_url( admin_url( 'users.php?orderby=when_last_login&order=desc' ) ) . '">' . esc_html__( 'View All Users', 'when-last-login' ) . '</a>';
            if ( $wll_widget_track_all ) {
                echo ' | <a href="' . esc_url( admin_url( 'admin.php?page=wll-login-records' ) ) . '">' . esc_html__( 'View Login Records', 'when-last-login' ) . '</a>';
            }
        }
    }

    /**
     * How many users the dashboard widget lists.
     *
     * The stored setting stays between 3 and 10. The wll_top_widget_user_count
     * filter still receives that value and may change it.
     *
     * @return int
     */
    private static function get_widget_user_limit() {
        $settings = get_option( 'wll_settings', array() );
        $default  = defined( 'WLL_WIDGET_USER_COUNT' ) ? (int) WLL_WIDGET_USER_COUNT : 3;
        $count    = ( is_array( $settings ) && isset( $settings['widget_user_count'] ) ) ? absint( $settings['widget_user_count'] ) : $default;

        if ( $count < 3 ) {
            $count = 3;
        }

        if ( $count > 10 ) {
            $count = 10;
        }

        return (int) apply_filters( 'wll_top_widget_user_count', $count );
    }

    /**
     * Last view chosen by the current user.
     *
     * @return string count|time
     */
    private static function get_widget_sort_preference() {
        $sort = get_user_meta( get_current_user_id(), 'wll_widget_sort', true );

        return ( 'time' === $sort ) ? 'time' : 'count';
    }

    /**
     * Build the user query for one widget table.
     *
     * @param string $sort_order count or time.
     * @param int    $blog_id    Multisite blog ID, or 0 for the current site.
     * @return array
     */
    private static function get_widget_user_query_args( $sort_order, $blog_id = 0 ) {
        $args = array(
            'meta_value'   => 0,
            'meta_compare' => '!=',
            'order'        => 'DESC',
            'orderby'      => 'meta_value_num',
            'number'       => self::get_widget_user_limit(),
            'role__not_in' => array( 'administrator' ),
            'meta_key'     => ( 'time' === $sort_order ) ? 'when_last_login' : 'when_last_login_count',
        );

        if ( $blog_id ) {
            $args['blog_id'] = (int) $blog_id;
        }

        return $args;
    }

    /**
     * Format a stored UTC unix timestamp in the site timezone.
     *
     * @param mixed $timestamp Login time stored by time().
     * @return string
     */
    private static function format_widget_login_time( $timestamp ) {
        $timestamp = (int) $timestamp;

        if ( $timestamp <= 0 ) {
            return __( 'Never', 'when-last-login' );
        }

        if ( function_exists( 'wp_date' ) ) {
            return wp_date( 'Y-m-d H:i:s', $timestamp );
        }

        return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'Y-m-d H:i:s' );
    }

    /**
     * Table rows for the widget.
     *
     * @param array $users Users returned by WP_User_Query.
     * @return string
     */
    private static function render_widget_rows( $users ) {
        if ( empty( $users ) ) {
            return '<tr><td colspan="4">' . esc_html__( 'No data yet', 'when-last-login' ) . '</td></tr>';
        }

        $html  = '';
        $count = 1;

        foreach ( $users as $wllusers ) {
            $login_count = get_user_meta( $wllusers->ID, 'when_last_login_count', true );
            $last_login  = get_user_meta( $wllusers->ID, 'when_last_login', true );

            $html .= '<tr>';
            $html .= '<td>' . esc_html( (string) $count ) . '</td>';
            $html .= '<td>' . esc_html( $wllusers->display_name ) . '</td>';
            $html .= '<td>' . esc_html( (string) $login_count ) . '</td>';
            $html .= '<td>' . esc_html( self::format_widget_login_time( $last_login ) ) . '</td>';
            $html .= '</tr>';
            $count++;
        }

        return $html;
    }

    /**
     * Most Frequent / Recently Logged In buttons.
     *
     * @param int    $blog_id      Site ID passed back by the button. 0 is the current site.
     * @param string $current_sort count or time.
     * @return string
     */
    private static function render_widget_sort_controls( $blog_id, $current_sort ) {
        $blog_id       = (int) $blog_id;
        $count_pressed = ( 'time' === $current_sort ) ? 'false' : 'true';
        $time_pressed  = ( 'time' === $current_sort ) ? 'true' : 'false';
        $count_class   = ( 'time' === $current_sort ) ? 'button button-small wll-widget-sort-btn' : 'button button-small button-primary wll-widget-sort-btn';
        $time_class    = ( 'time' === $current_sort ) ? 'button button-small button-primary wll-widget-sort-btn' : 'button button-small wll-widget-sort-btn';

        $html  = '<p class="wll-widget-sort" style="margin:0 0 8px;">';
        $html .= '<button type="button" class="' . esc_attr( $count_class ) . '" data-sort="count" data-blog-id="' . esc_attr( (string) $blog_id ) . '" aria-pressed="' . esc_attr( $count_pressed ) . '">' . esc_html__( 'Most Frequent', 'when-last-login' ) . '</button> ';
        $html .= '<button type="button" class="' . esc_attr( $time_class ) . '" data-sort="time" data-blog-id="' . esc_attr( (string) $blog_id ) . '" aria-pressed="' . esc_attr( $time_pressed ) . '">' . esc_html__( 'Recently Logged In', 'when-last-login' ) . '</button> ';
        $html .= '<span class="spinner" style="float:none;margin:0 0 0 4px;"></span>';
        $html .= '</p>';

        return $html;
    }

    /**
     * One widget table. The body id is what the sort buttons replace.
     *
     * @param string $sort    count or time.
     * @param int    $blog_id Multisite blog ID, or 0.
     * @return string
     */
    private static function render_widget_table( $sort, $blog_id ) {
        $blog_id    = (int) $blog_id;
        $rows_id    = $blog_id ? 'wll-widget-rows-' . $blog_id : 'wll-widget-rows';
        $user_query = new WP_User_Query( self::get_widget_user_query_args( $sort, $blog_id ) );

        $html  = '<table class="wp-list-table striped widefat">';
        $html .= '<thead><tr>';
        $html .= '<th><strong>#</strong></th>';
        $html .= '<th><strong>' . esc_html__( 'Users', 'when-last-login' ) . '</strong></th>';
        $html .= '<th><strong>' . esc_html__( 'Login Count', 'when-last-login' ) . '</strong></th>';
        $html .= '<th><strong>' . esc_html__( 'Last Logged In', 'when-last-login' ) . '</strong></th>';
        $html .= '</tr></thead>';
        $html .= '<tbody id="' . esc_attr( $rows_id ) . '">';
        $html .= self::render_widget_rows( $user_query->get_results() );
        $html .= '</tbody></table><br/>';

        return $html;
    }

    /**
     * Script for the dashboard widget buttons.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_dashboard_widget_scripts( $hook ) {
        if ( 'index.php' !== $hook ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! apply_filters( 'when_last_login_show_admin_widget', true ) ) {
            return;
        }

        wp_enqueue_script(
            'wll-dashboard-widget',
            plugins_url( 'js/wll-dashboard-widget.js', __FILE__ ),
            array( 'jquery' ),
            WLL_VER,
            true
        );

        wp_localize_script(
            'wll-dashboard-widget',
            'wllWidget',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'wll_widget_nonce' ),
                'errorMessage' => __( 'The login list could not be loaded. Please refresh the Dashboard and try again.', 'when-last-login' ),
            )
        );
    }

    /**
     * Replace the widget rows when a view button is clicked.
     */
    public function wll_fetch_widget_data_ajax() {
        if ( ! check_ajax_referer( 'wll_widget_nonce', 'nonce', false ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'The request could not be verified. Please refresh the Dashboard and try again.', 'when-last-login' ),
                ),
                403
            );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'You do not have permission to view this widget.', 'when-last-login' ),
                ),
                403
            );
        }

        $sort = isset( $_POST['sort_order'] ) ? sanitize_key( wp_unslash( $_POST['sort_order'] ) ) : 'count';
        if ( 'time' !== $sort ) {
            $sort = 'count';
        }

        $blog_id = isset( $_POST['blog_id'] ) ? absint( wp_unslash( $_POST['blog_id'] ) ) : 0;

        if ( $blog_id && (int) get_current_blog_id() !== $blog_id ) {
            $site = function_exists( 'get_site' ) ? get_site( $blog_id ) : get_blog_details( $blog_id );
            if ( ! is_multisite() || ! current_user_can( 'manage_network' ) || ! $site ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'You do not have permission to view this site.', 'when-last-login' ),
                    ),
                    403
                );
            }
        }

        update_user_meta( get_current_user_id(), 'wll_widget_sort', $sort );

        $user_query = new WP_User_Query( self::get_widget_user_query_args( $sort, $blog_id ) );

        wp_send_json_success(
            array(
                'html' => self::render_widget_rows( $user_query->get_results() ),
            )
        );
    }

     /**
     * Setup Column and data for users page with sortable
     */
     public static function column_header( $column ){
      $settings = get_option( 'wll_settings' );

      $column['when_last_login'] = esc_html__( 'Last Login', 'when-last-login' );

      if ( ! empty( $settings['record_ip_address'] ) ) {
        $column['when_last_login_ip_address'] = esc_html__( 'IP Address', 'when-last-login' );
      }

      // Add WooCommerce Last Active column if enabled and WooCommerce is active
      if ( ! empty( $settings['show_wc_last_active'] ) && class_exists( 'WooCommerce' ) ) {
        $column['wc_last_active'] = esc_html__( 'WC Last Active', 'when-last-login' );
      }

       return $column;
     }

     public static function column_data( $value, $column_name, $id ){

      $settings = get_option( 'wll_settings' );

      if ( $column_name == 'when_last_login' ){

        $when_last_login_meta = get_the_author_meta( 'when_last_login', $id );

          if( ! empty( $when_last_login_meta ) ){
            return human_time_diff( $when_last_login_meta );
          }

          return esc_html__( 'Never', 'when-last-login' );
        } else if( $column_name == 'when_last_login_ip_address' ){

          $when_last_login_ip_address = get_user_meta( $id, 'wll_user_ip_address', true );

          if ( ! empty( $when_last_login_ip_address ) && ! empty( $settings['record_ip_address'] ) ) {
            return "<a href='" . esc_url( 'https://www.ip-adress.com/ip_tracer/' . $when_last_login_ip_address ) . "' target='_BLANK' title='" . esc_attr__( 'Lookup', 'when-last-login' ) . "'>" . esc_html( $when_last_login_ip_address ) . "</a>";
          } else {
            return esc_html__( 'IP Address Not Recorded', 'when-last-login' );
          }


        } else if( $column_name == 'wc_last_active' ){
          // WooCommerce Last Active column
          if ( class_exists( 'WooCommerce' ) ) {
            $wc_last_active = get_user_meta( $id, 'wc_last_active', true );
            if ( ! empty( $wc_last_active ) ) {
              return human_time_diff( $wc_last_active );
            } else {
              return esc_html__( 'Never', 'when-last-login' );
            }
          }
          return '';
        }
      return $value;
     }

     public static function column_sortable( $columns ){
      $columns['when_last_login'] = 'when_last_login';
      return $columns;
     }

    public static function sort_by_login_date( $query ) {
      if ( 'when_last_login' == $query->get( 'orderby' ) ) {
        // Skip sorting during search to avoid filtering out users without login meta.
        if ( ! is_admin() || ! isset( $_GET['s'] ) || empty( $_GET['s'] ) ) {
          $query->set( 'orderby', 'meta_value_num' );
          $query->set( 'meta_key', 'when_last_login' );
        }
      }
    }

     /*
     * Support for Paid Memberships Pro
     * TODO: use existing PMPro usermeta if installed
     */
     public static function pmpro_memberlist_add_header( $users ){
       if( !defined( 'PMPRO_VERSION' ) ){
         return;
       }
      ?>
      <th><?php esc_html_e( 'Last Login', 'when-last-login' );?></th>
      <?php

     }

     public static function pmpro_memberlist_add_column_data( $users ){
       if( !defined( 'PMPRO_VERSION' ) ){
         return;
       }
?>
      <td>
<?php
      if( ! empty( $users->when_last_login ) ){
        echo esc_html( human_time_diff( $users->when_last_login ) );
      }else{
        echo esc_html__( 'Never', 'when-last-login' );
      }
?>
      </td>
<?php
     }

    /**
     * Add Last Login column to PMPro CSV export.
     *
     * @param array $columns Array of column key => label pairs.
     * @return array Modified columns array.
     */
    public static function pmpro_csv_export_columns( $columns ) {
        if ( ! defined( 'PMPRO_VERSION' ) ) {
            return $columns;
        }
        $columns['when_last_login'] = __( 'Last Login', 'when-last-login' );
        return $columns;
    }

    /**
     * Add Last Login data to PMPro CSV export rows.
     *
     * @param array $row The CSV row data.
     * @param object $user The user object.
     * @return array Modified row data.
     */
    public static function pmpro_csv_export_row( $row, $user ) {
        if ( ! defined( 'PMPRO_VERSION' ) ) {
            return $row;
        }
        $last_login = get_user_meta( $user->ID, 'when_last_login', true );
        if ( ! empty( $last_login ) ) {
            $row['when_last_login'] = wp_date( 'Y-m-d H:i:s', $last_login );
        } else {
            $row['when_last_login'] = __( 'Never', 'when-last-login' );
        }
        return $row;
    }

    public function wll_settings_page(){

      add_menu_page( __('When Last Login', 'when-last-login'), esc_html__('When Last Login', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ), 'dashicons-visibility');

      add_submenu_page( 'when-last-login-settings', esc_html__('Settings', 'when-last-login'), __('Settings', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );

      $wll_menu_settings = get_option( 'wll_settings' );
      if ( ! isset( $wll_menu_settings['track_all_records'] ) || intval( $wll_menu_settings['track_all_records'] ) === 1 ) {
        $records_hook = add_submenu_page( 'when-last-login-settings', esc_html__('Login Records', 'when-last-login'), __('Login Records', 'when-last-login'), 'manage_options', 'wll-login-records', array( $this, 'wll_login_records_callback' ) );
        add_action( 'load-' . $records_hook, array( $this, 'wll_login_records_load' ) );
      }

      add_submenu_page( 'when-last-login-settings', esc_html__('Add Ons', 'when-last-login'), __('Add Ons', 'when-last-login'), 'manage_options', 'when-last-login-settings&tab=add-ons', array( $this, 'wll_settings_callback' ) );

      do_action( 'wll_settings_admin_menu_item' );

    }

    /**
     * Registers the settings page under Settings instead of a top-level menu item.
     * Used when streamline mode ("hide_admin_menu") is enabled, so the setting can still be turned back off.
     *
     * @since  1.10.0
     */
    public function wll_settings_page_streamlined(){

      add_options_page( __('When Last Login', 'when-last-login'), esc_html__('When Last Login', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );

    }

    /**
     * Fires before the login records page renders, allowing redirects.
     *
     * @since  1.3.0
     */
    public function wll_login_records_load() {
      self::load_list_table();
      $list_table = new WLL_List_Table();
      $deleted = $list_table->process_bulk_action();

      if ( $deleted > 0 ) {
        wp_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=wll-login-records' ) ) );
        exit;
      }
    }

    /**
     * Login records page callback.
     *
     * @since  1.3.0
     */
    public function wll_login_records_callback() {
      // Display success notice after redirect.
      if ( ! empty( $_REQUEST['deleted'] ) ) {
        printf(
          '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
          sprintf(
            /* translators: %d: number of records deleted */
            _n( '%d record deleted.', '%d records deleted.', intval( $_REQUEST['deleted'] ), 'when-last-login' ),
            intval( $_REQUEST['deleted'] )
          )
        );
      }

      self::load_list_table();
      $list_table = new WLL_List_Table();
      $list_table->prepare_items();

      ?>
      <div class="wrap">
        <h1><?php esc_html_e( 'Login Records', 'when-last-login' ); ?></h1>
        <form method="post">
          <input type="hidden" name="page" value="<?php echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : 'wll-login-records' ); ?>" />
          <?php $list_table->search_box( __( 'Search', 'when-last-login' ), 'wll-records' ); ?>
          <?php $list_table->display(); ?>
        </form>
      </div>
      <?php
    }

    public function wll_settings_callback(){

      include WLL_DIR_PATH . '/includes/settings.php';

    }

    public function wll_settings_page_head(){

      if ( empty( $_GET['page'] ) || 'when-last-login-settings' !== $_GET['page'] ) {
        return;
      }

      if( isset( $_POST['wll_save_settings'] ) ){

        if ( ! current_user_can( 'manage_options' ) ) {
          wp_die( esc_html__( 'You do not have permission to save these settings.', 'when-last-login' ) );
        }

        if( isset( $_POST['_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'wll_settings_nonce' ) ) {

          $wll_settings = get_option( 'wll_settings', array() );
          if ( ! is_array( $wll_settings ) ) {
            $wll_settings = array();
          }

          if ( isset( $_POST['wll_login_record_user_access'] ) ) {
            $wll_settings['user_access'] = sanitize_text_field( wp_unslash( $_POST['wll_login_record_user_access'] ) );
          }

          $wll_settings['record_ip_address'] = isset( $_POST['wll_record_user_ip_address'] ) && sanitize_text_field( wp_unslash( $_POST['wll_record_user_ip_address'] ) ) == '1'  ? 1 : 0;
          $wll_settings['track_all_records'] = isset( $_POST['wll_track_all_records'] ) && sanitize_text_field( wp_unslash( $_POST['wll_track_all_records'] ) ) == '1' ? 1 : 0;
          $wll_settings['show_wc_last_active'] = isset( $_POST['wll_show_wc_last_active'] ) && sanitize_text_field( wp_unslash( $_POST['wll_show_wc_last_active'] ) ) == '1' ? 1 : 0;
          $wll_settings['hide_admin_menu'] = isset( $_POST['wll_hide_admin_menu'] ) && sanitize_text_field( wp_unslash( $_POST['wll_hide_admin_menu'] ) ) == '1' ? 1 : 0;

          if ( isset( $_POST['wll_widget_user_count'] ) ) {
            $widget_user_count = absint( wp_unslash( $_POST['wll_widget_user_count'] ) );
            if ( $widget_user_count < 3 ) {
              $widget_user_count = 3;
            }
            if ( $widget_user_count > 10 ) {
              $widget_user_count = 10;
            }
            $wll_settings['widget_user_count'] = $widget_user_count;
          }


          $wll_settings = apply_filters( 'wll_settings_filter', $wll_settings );

          update_option( 'wll_settings', $wll_settings );

          //Redirect back (rather than just rendering) so the admin menu, dashboard widget, and
          //notice all reflect the freshly-saved settings on a real page load - this matters most
          //for hide_admin_menu, since that changes which menu registers on 'admin_menu'.
          $redirect_args = array(
            'page' => 'when-last-login-settings',
            'wll-settings-updated' => '1',
          );
          if ( ! empty( $_GET['tab'] ) ) {
            $redirect_args['tab'] = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
          }
          $settings_base = ! empty( $wll_settings['hide_admin_menu'] ) ? 'options-general.php' : 'admin.php';
          wp_safe_redirect( add_query_arg( $redirect_args, admin_url( $settings_base ) ) );
          exit;
        } else {
          wp_die( esc_html__( 'Nonce is not valid', 'when-last-login' ) );
        }

      }

    }

    public function wll_admin_notices() {

      if ( empty( $_GET['wll-settings-updated'] ) ) {
        return;
      }
    ?>
      <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Settings saved successfully.', 'when-last-login' ); ?></p>
      </div>
    <?php
    }

    public function wll_remove_records_notice__success() {
    ?>
      <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Records have been removed successfully.', 'when-last-login' ); ?></p>
      </div>
    <?php
    }

    public function wll_remove_records_notice__warning() {
    ?>
      <div class="notice notice-warning is-dismissible">
        <p><?php esc_html_e( 'No old records to remove.', 'when-last-login' ); ?></p>
      </div>
    <?php
    }

    /**
     * Function to remove IP addresses from usermeta.
     * @since 1.0.0
     */
    public function wll_automatically_remove_logs() {
      global $pagenow, $wpdb;

      // Bail if there is not ?page=xxx parameter
      if ( empty( $_GET['page'] ) ) {
        return;
      }

      // Bail if user cannot manage options.
      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }

      // Bail if not on our settings page.
      if ( ! in_array( $pagenow, array( 'admin.php', 'options-general.php' ), true ) || ! isset( $_GET['page'] ) || 'when-last-login-settings' !== $_GET['page'] ) {
        return;
      }

      if ( isset( $_POST['remove_wll_ip_addresses'] ) ) {

          $nonce = isset( $_POST['wll_remove_ip_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wll_remove_ip_nonce'] ) ) : '';
          if ( $nonce && wp_verify_nonce( $nonce, 'wll_remove_ip_nonce' ) ) {

            $result = class_exists( 'WLL_DB' ) ? WLL_DB::clear_all_ip_addresses() : $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", 'wll_user_ip_address' ) );

            if ( $result > 0 ) {
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
            } else {
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
            }
          } else {
            wp_die( esc_html__( 'Nonce is not valid', 'when-last-login' ) );
          }
        }

        // Remove old login records (90+ days).
        if ( isset( $_POST['wll_remove_old_records'] ) ) {

          $nonce = isset( $_POST['wll_remove_old_records_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wll_remove_old_records_nonce'] ) ) : '';
          if ( wp_verify_nonce( $nonce, 'wll_remove_old_records_nonce' ) ) {
            $deleted = WLL_DB::delete_old_records( WLL_CLEANUP_DAYS );
            if ( $deleted > 0 ) {
              add_action( 'admin_notices', function() use ( $deleted ) {
                printf(
                  '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                  sprintf(
                    /* translators: %d: number of records deleted */
                    esc_html__( '%d old login records deleted.', 'when-last-login' ),
                    intval( $deleted )
                  )
                );
              } );
            } else {
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
            }
          } else {
            wp_die( esc_html__( 'Invalid nonce', 'when-last-login' ) );
          }
        }

        // Remove all login records.
        if ( isset( $_POST['wll_remove_all_records'] ) ) {

          $nonce = isset( $_POST['wll_remove_all_records_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wll_remove_all_records_nonce'] ) ) : '';
          if ( wp_verify_nonce( $nonce, 'wll_remove_all_records_nonce' ) ) {
            $deleted = WLL_DB::delete_all_records();
            if ( $deleted > 0 ) {
              add_action( 'admin_notices', function() use ( $deleted ) {
                printf(
                  '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                  sprintf(
                    /* translators: %d: number of records deleted */
                    esc_html__( '%d login records deleted.', 'when-last-login' ),
                    intval( $deleted )
                  )
                );
              } );
            } else {
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
            }
          } else {
            wp_die( esc_html__( 'Invalid nonce', 'when-last-login' ) );
          }
        }
    }

    public function wll_plugin_action_links( $links ) {
      $new_links = array(
        '<a href="' . admin_url('admin.php?page=when-last-login-settings') . '" title="' . esc_attr( __( 'View Settings', 'when-last-login' ) ) . '">' . __( 'Settings', 'when-last-login' ) . '</a>'
      );

      $new_links = apply_filters( 'wll_plugin_action_links', $new_links );

      return array_merge( $new_links, $links );
    }

    public function wll_plugin_row_meta( $links, $file ) {
      if ( strpos( $file, 'when-last-login.php' ) !== false ) {
        $new_links = array(
          '<a href="' . admin_url('admin.php?page=when-last-login-settings') . '" title="' . esc_attr( __( 'View Settings', 'when-last-login' ) ) . '">' . __( 'Settings', 'when-last-login' ) . '</a>',
          '<a href="' . esc_url( 'https://yoohooplugins.com/?s=when+last+login' ) . '" title="' . esc_attr__( 'View Documentation', 'when-last-login' ) . '">' . esc_html__( 'Docs', 'when-last-login' ) . '</a>',
          '<a href="' . esc_url( 'https://yoohooplugins.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'when-last-login' ) . '">' . esc_html__( 'Support', 'when-last-login' ) . '</a>',
        );

        $new_links = apply_filters( 'wll_plugin_row_meta', $new_links );
        $links = array_merge( $links, $new_links );
      }
      return $links;
    }

    public static function wll_get_user_ip_address(){

      $candidates = array();

      if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $candidates[] = wp_unslash( $_SERVER['HTTP_CLIENT_IP'] );
      }

      if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        foreach ( explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) as $part ) {
          $candidates[] = trim( $part );
        }
      }

      if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $candidates[] = wp_unslash( $_SERVER['REMOTE_ADDR'] );
      }

      $ip = '';
      foreach ( $candidates as $candidate ) {
        $candidate = sanitize_text_field( $candidate );
        if ( $candidate && filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
          $ip = $candidate;
          break;
        }
      }

      $ip = apply_filters( 'wll_user_ip_address', $ip );

      if ( apply_filters( 'wll_force_anon_ip', false ) ) {
        return $ip;
      }

      return IpAnonymizer::anonymizeIp( $ip );
    }

    /**
     * Parse browser from user agent.
     *
     * @since  1.3.0
     * @param  string $user_agent User agent string.
     * @return string            Browser name.
     */
    public static function parse_browser( $user_agent ) {
      $browsers = array(
        'Edg'       => 'Edge',
        'Chrome'    => 'Chrome',
        'Safari'    => 'Safari',
        'Firefox'   => 'Firefox',
        'MSIE'      => 'IE',
        'Trident'   => 'IE',
        'Opera'     => 'Opera',
        'OPR'       => 'Opera',
      );

      foreach ( $browsers as $pattern => $name ) {
        if ( strpos( $user_agent, $pattern ) !== false ) {
          return $name;
        }
      }

      return 'Unknown';
    }

    /**
     * Parse OS from user agent.
     *
     * @since  1.3.0
     * @param  string $user_agent User agent string.
     * @return string            OS name.
     */
    public static function parse_os( $user_agent ) {
      $systems = array(
        'Windows NT 11' => 'Windows 11',
        'Windows NT 10' => 'Windows 10',
        'Windows NT 6.3' => 'Windows 8.1',
        'Windows NT 6.2' => 'Windows 8',
        'Windows NT 6.1' => 'Windows 7',
        'Mac OS X'       => 'macOS',
        'iPhone'         => 'iOS',
        'iPad'           => 'iOS',
        'Android'        => 'Android',
        'Linux'           => 'Linux',
        'Ubuntu'         => 'Ubuntu',
      );

      foreach ( $systems as $pattern => $name ) {
        if ( strpos( $user_agent, $pattern ) !== false ) {
          return $name;
        }
      }

      return 'Unknown';
    }

    /**
     * Parse device type from user agent.
     *
     * @since  1.3.0
     * @param  string $user_agent User agent string.
     * @return string            Device type.
     */
    public static function parse_device( $user_agent ) {
      $mobile = array( 'Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone' );

      foreach ( $mobile as $pattern ) {
        if ( strpos( $user_agent, $pattern ) !== false ) {
          if ( strpos( $user_agent, 'iPad' ) !== false ) {
            return 'Tablet';
          }
          return 'Mobile';
        }
      }

      if ( strpos( $user_agent, 'Tablet' ) !== false ) {
        return 'Tablet';
      }

      return 'Desktop';
    }

    /**
     * Create custom tables on activation.
     *
     * @since 1.3.0
     */
    public static function activate() {
      if ( ! class_exists( 'WLL_DB' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-wll-db.php';
      }
      WLL_DB::activate_schema();
    }

    /**
     * Load the login records list table class in admin only.
     *
     * @since 1.3.0
     */
    public static function load_list_table() {
      if ( ! class_exists( 'WLL_List_Table' ) ) {
        require_once WLL_DIR_PATH . '/includes/class-wll-list-table.php';
      }
    }

    /**
     * Remove custom table rows when a user is deleted.
     *
     * @since 1.3.0
     *
     * @param int $user_id User ID.
     */
    public static function delete_user_login_data( $user_id ) {
      if ( class_exists( 'WLL_DB' ) ) {
        WLL_DB::delete_user_data( $user_id );
      }
    }

} // end class
When_Last_Login::get_instance();
register_activation_hook( __FILE__, array( 'When_Last_Login', 'activate' ) );
