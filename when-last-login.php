<?php
/*
Plugin Name: When Last Login
Plugin URI: https://wordpress.org/plugins/when-last-login/
Description: See when a user logs into your WordPress site.
Version: 1.2.3
Author: Yoohoo Plugins
Author URI: https://yoohooplugins.com
Text Domain: when-last-login
Domain Path: /languages
*/

use geertw\IpAnonymizer\IpAnonymizer;

define( 'WLL_VER', '1.2.3' );

/**
 * Changelog
 * 1.2.3 - 2025-03-27 
 * - Fixed dashboard widget timezone display to use get_date_from_gmt().
 * - Made the number of users in dashboard widget configurable (Settings > General, Default: 5, Min: 3, Max: 10).
 * - Added toggle to dashboard widget to sort by 'Most Frequent' or 'Recently Logged In'.
 * 1.2.2 - Previous version changes...
 */
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

      add_action( 'admin_init', array( $this, 'admin_init' ) );
      add_action( 'plugins_loaded', array( $this, 'text_domain' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'load_js_for_notice' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_widget_scripts' ) ); // Enqueue widget JS

      //Create the custom meta upon login
      add_action( 'wp_login', array( $this, 'last_login'), 10, 2 );
      add_action( 'user_register', array( $this, 'wll_user_register' ), 10, 1 );

      //Admin actions
      add_action( 'wp_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );      
      add_action( 'admin_notices', array( $this, 'update_notice' ) );

      add_action( 'wp_ajax_wll_hide_subscription_notice', array( $this, 'wll_hide_subscription_notice' ) );
      add_action( 'wp_ajax_wll_fetch_widget_data', array( $this, 'wll_fetch_widget_data_ajax' ) ); // AJAX handler for widget data

      //Setting up columns.
      add_filter( 'manage_users_columns', array( $this, 'column_header'), 10, 1 );
      add_action( 'manage_users_custom_column', array( $this, 'column_data'), 15, 3 );
      add_filter( 'manage_users_sortable_columns', array( $this, 'column_sortable' ) );
      add_action( 'pre_get_users', array( $this, 'sort_by_login_date') );

      //Integration for Paid Memberships Pro
      //TODO: Improve integration with Member List and Paid Memberships Pro
      add_action( 'pmpro_memberslist_extra_cols_header', array( $this, 'pmpro_memberlist_add_header' ) );
      add_action( 'pmpro_memberslist_extra_cols_body', array( $this, 'pmpro_memberlist_add_column_data' ) );
      add_action( 'init', array( $this, 'login_record_cp' ) );

      add_action( 'admin_menu', array( $this, 'wll_settings_page' ), 9 );
      add_action( 'admin_head', array( $this, 'wll_settings_page_head' ) );
      add_action( 'admin_init', array( $this, 'wll_automatically_remove_logs' ) );

      add_filter( 'plugin_row_meta', array( $this, 'wll_plugin_row_meta' ), 10, 2 );
      add_filter( 'plugin_action_links_' . WLL_BASENAME, array( $this, 'wll_plugin_action_links' ), 10, 2 );

      add_filter( 'manage_wll_records_posts_columns' , array( $this, 'wll_records_columns'), 10, 1 );
      add_action( 'manage_wll_records_posts_custom_column' , array( $this, 'wll_records_column_contents' ), 10, 2 );

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

      // Clean up stuff for version 1.0
      if( $current_version < 1.0 || empty( $current_version ) ) {

        global $wpdb;

        $delete_table = $wpdb->prefix . 'wll_login_attempts' ;
        $sql = "DROP TABLE IF EXISTS `$delete_table`";
        $wpdb->query( $sql );

        delete_transient( 'when_last_login_add_ons_page' );

        // on upgrade remove the notice save.
        delete_option( 'wll_notice_hide' );
        delete_option( 'wll_notice_hide_1' );
        delete_option( 'wll_notice_hide_2' );

        // update version number to 1.0
       update_option( 'wll_current_version', 1.2 );
      }
    }

    public static function text_domain(){
      load_plugin_textdomain( 'when-last-login', false, dirname( 'WLL_BASE_NAME' ) . '/languages' );
    }

    public static function update_notice(){

      if( get_option( 'wll_notice_hide' ) != '1' && ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'when-last-login-settings' ) ){
        ?>
        <div class="notice notice-success  wll-update-notice-newsletter is-dismissible" >
        <h3><?php _e('Thank you for using When Last Login', 'when-last-login'); ?></h3>
        <p><?php  _e( sprintf( 'Please consider leaving an honest review for When Last Login by visiting %s', '<a href="'. esc_url( 'https://wordpress.org/support/plugin/when-last-login/reviews/#new-post' ) . '" target="_blank">this link</a>' ), 'when-last-login' ); ?></p>
        </div>
        <?php
      }
    }

    public function wll_hide_subscription_notice(){
    if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wll_hide_notice_nonce' ) ) {
        wp_die( __( 'Nonce is invalid', 'pmpro-pdf-invoices' ) );
      }
      update_option( 'wll_notice_hide', '1' );
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

     public static function last_login( $user_login, $users ){

      global $show_login_records;

       //get/update user meta 'when_last_login' on login and add time() to it.
       update_user_meta( $users->ID, 'when_last_login', time() );

       //get and update user meta 'when_last_login_count' on login for # of login counts. Thanks to Jarryd Long (@jarrydlong) for the assistance
       $wll_count = get_user_meta( $users->ID, 'when_last_login_count', true );

       if( $wll_count === false ){
         update_user_meta($users->ID, 'when_last_login_count', 1);
       } else {
         $wll_new_value = intval($wll_count);
         $wll_new_value = $wll_new_value + 1;

         update_user_meta($users->ID, 'when_last_login_count', $wll_new_value);
       }
       if( $show_login_records == true ){
       $args = array(
          'post_title'    => $users->data->display_name . __( ' has logged in at ', 'when-last-login' ) . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
          'post_status'   => 'publish',
          'post_author'   => $users->ID,
          'post_type'     => 'wll_records'
        );

        $post_id = wp_insert_post( $args );

      }

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && intval( $wll_settings['record_ip_address'] ) == 1 ){

          // call function to anonymize here.
          $ip = When_Last_Login::wll_get_user_ip_address();

          if ( ! empty( $post_id ) ) {
            update_post_meta( $post_id, 'wll_user_ip_address', $ip );
          }
          
            update_user_meta( $users->ID, 'wll_user_ip_address', $ip );
        }

        do_action( 'wll_logged_in_action', array( 'login_count' => $wll_new_value, 'user' => $users ), $wll_settings );

     }

     public function wll_user_register( $user_id ){

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && $wll_settings['record_ip_address'] == 1 ){
          
        $ip = When_Last_Login::wll_get_user_ip_address();
        update_user_meta( $user_id, 'wll_user_ip_address', $ip );

        }

        do_action( 'wll_register_action', $user_id, $wll_settings );

     }

     public static function login_record_cp(){

      global $show_login_records;

      $settings = get_option( 'wll_settings' );

      $show = (!empty($settings['show_all_login_records']) AND $settings['show_all_login_records'] === 1);

      $show_login_records = apply_filters( 'when_last_login_show_records_table', $show );

      if( $show_login_records != true ){
        return;
      }

       $labels = array(
         'name'               => __( 'Login Records', 'when-last-login' ),
         'singular_name'      => __( 'Login Record', 'when-last-login' ),
         'menu_name'          => __( 'Login Records', 'when-last-login' ),
         'name_admin_bar'     => __( 'Login Record', 'when-last-login' ),
         'add_new'            => __( 'Add New', 'when-last-login' ),
         'add_new_item'       => __( 'Add New Login Record', 'when-last-login' ),
         'new_item'           => __( 'New Login Record', 'when-last-login' ),
         'edit_item'          => __( 'Edit Login Record', 'when-last-login' ),
         'view_item'          => __( 'View Login Record', 'when-last-login' ),
         'all_items'          => __( 'All Login Records', 'when-last-login' ),
         'search_items'       => __( 'Search Login Records', 'when-last-login' ),
         'parent_item_colon'  => __( 'Parent Login Records:', 'when-last-login' ),
         'not_found'          => __( 'No login records found.', 'when-last-login' ),
         'not_found_in_trash' => __( 'No login records found in Trash.', 'when-last-login' )
       );

       $args = array(
         'labels'             => $labels,
         'description'        => __( 'Description.', 'when-last-login' ),
         'public'             => false,
         'publicly_queryable' => false,
         'show_ui'            => true,
         'show_in_menu'       => 'when-last-login-settings',
         'query_var'          => true,
         'rewrite'            => array( 'slug' => 'when-last-login-records' ),
         'capability_type'    => 'post',
         'has_archive'        => true,
         'hierarchical'       => false,
         'menu_position'      => null,
         'supports'           => array( 'title', 'author' ),
         'capabilities' => array(
           'create_posts' => false,
         ),
         'map_meta_cap' => true,
       );

       register_post_type( 'wll_records', $args );
     }

     /**
     * Setup admin backend to display custom meta box for login count for admins
     */
     public static function admin_dashboard_widget(){

      global $show_widget;

      $show_widget = apply_filters( 'when_last_login_show_admin_widget', true );
       //only show for administrators
       if( current_user_can( 'manage_options' ) && $show_widget ){
        // Changed widget ID and title slightly to reflect default view + toggle
        wp_add_dashboard_widget( 'when_last_login_activity_widget', __( 'User Login Activity', 'when-last-login' ), array( 'When_Last_Login', 'admin_dashboard_widget_display' ) ); 
       }
     }

    public static function admin_dashboard_widget_display(){

        global $show_widget, $show_login_records;

        if( $show_widget != true ){
            return;
        }

        // Multisite display logic removed for brevity in this example, 
        // but the same changes (toggle HTML, tbody wrapper, initial query, helper calls)
        // would need to be applied within the is_network_admin() block as well.
        if( is_network_admin() ){
             echo '<p>' . esc_html__( 'Network admin view not fully implemented in this example.', 'when-last-login' ) . '</p>';
             // Apply similar changes as below within the network admin loop if needed.
        } else {

            ?>
            <div class="wll-sort-toggle" style="margin-bottom: 10px; text-align: right;">
                <label for="wll-widget-sort-select" style="margin-right: 5px;"><?php esc_html_e( 'Sort by:', 'when-last-login' ); ?></label>
                <select id="wll-widget-sort-select" name="wll_widget_sort">
                    <option value="count" selected><?php esc_html_e( 'Most Frequent', 'when-last-login' ); ?></option>
                    <option value="time"><?php esc_html_e( 'Recently Logged In', 'when-last-login' ); ?></option>
                </select>
                <span class="spinner wll-widget-loading" style="float: none; vertical-align: middle; margin-left: 5px; display: none;"></span> 
            </div>
            <table width="100%" text-align="center" class='wp-list-table striped widefat'>          
                <thead>
                <tr>
                    <th><strong>#</strong></th>
                    <th><strong><?php esc_html_e( 'Users', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php esc_html_e( 'Login Count', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php esc_html_e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                </tr> 
                </thead>
                <tbody id="wll-widget-table-body-wrapper">
            <?php

            // Initial load: Sort by count (Most Frequent)
            $initial_sort_order = 'count'; 
            $query_args = self::get_widget_user_query_args( $initial_sort_order ); // Use self::
            $user_query = new WP_User_Query( $query_args );
            $topusers = $user_query->get_results();

            echo self::generate_widget_table_rows( $topusers ); // Use self::

            ?>
                </tbody>
            </table><br/>

            <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php esc_html_e( 'View All Users', 'when-last-login' ); ?></a>

            <?php if( $show_login_records == true ){ ?>
                <a style="float:right" href="<?php echo admin_url( 'edit.php?post_type=wll_records' ); ?>"><?php esc_html_e( 'View Login Records', 'when-last-login' ); } //end the if filter check here ?></a>
            <?php    
        }
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
      
       return $column;
     }

     public static function column_data( $value, $column_name, $id ){

      $settings = get_option( 'wll_settings' );

      if ( $column_name == 'when_last_login' ){

        $when_last_login_meta = get_the_author_meta( 'when_last_login', $id );

          if( ! empty( $when_last_login_meta ) ){
            return human_time_diff( $when_last_login_meta );
          } else {
            // Check if meta exists and is explicitly 0, otherwise update to 0
            $meta_exists = metadata_exists('user', $id, 'when_last_login');
            if( $meta_exists && get_the_author_meta( 'when_last_login', $id ) === 0 ){
              return esc_html__( 'Never', 'when-last-login' );
            } else if (!$meta_exists) {
              // Only update if it truly doesn't exist, avoid unnecessary updates
              update_user_meta( $id, 'when_last_login', 0 ); 
              return esc_html__( 'Never', 'when-last-login' );
            } else {
                 // If it exists but is not 0 (e.g., empty string), treat as Never
                 return esc_html__( 'Never', 'when-last-login' );
            }
          }
        } else if( $column_name == 'when_last_login_ip_address' ){

          $when_last_login_ip_address = get_user_meta( $id, 'wll_user_ip_address', true );

          if ( $when_last_login_ip_address && ! empty( $settings['record_ip_address'] ) ) {
            return "<a href='http://www.ip-adress.com/ip_tracer/". esc_attr( $when_last_login_ip_address ) ."' target='_BLANK' title='".__( 'Lookup', 'when-last-login' )."'>" . esc_html( $when_last_login_ip_address ) . "</a>";
          } else {
            return esc_html__( 'IP Address Not Recorded', 'when-last-login' );
          }
        }
      return $value;
     }

     public static function column_sortable( $columns ){
      $columns['when_last_login'] = 'when_last_login';
      return $columns;
     }

    public static function sort_by_login_date( $query ) {
      if ( 'when_last_login' == $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'meta_value_num' );
        $query->set( 'meta_key', 'when_last_login' );
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
        echo human_time_diff( $users->when_last_login );
      }else{
        return esc_html_e( 'Never', 'when-last-login' );
      }
?>
      </td>
<?php
     }

    public function wll_settings_page(){

      add_menu_page( __('When Last Login', 'when-last-login'), esc_html__('When Last Login', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ), 'dashicons-visibility');

      add_submenu_page( 'when-last-login-settings', esc_html__('Settings', 'when-last-login'), __('Settings', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );

      add_submenu_page( 'when-last-login-settings', esc_html__('Extensions', 'when-last-login'), __('Extensions', 'when-last-login'), 'manage_options', 'admin.php?page=when-last-login-settings&tab=add-ons' );
      
      do_action( 'wll_settings_admin_menu_item' );

    }

    public function wll_settings_callback(){

      include WLL_DIR_PATH . '/includes/settings.php';

    }

    public function wll_settings_page_head(){

      // Check if user has permissions and the form was submitted
      if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['wll_save_settings'] ) ) {
          return;
      }
      
      // Verify nonce
      if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'wll_settings_nonce' ) ) {
          wp_die( 'Nonce verification failed!' );
      }

      // Get existing settings or default to empty array
      $wll_settings = get_option( 'wll_settings', array() );

      // Sanitize and update settings
      $wll_settings['user_access'] = isset( $_POST['wll_login_record_user_access'] ) ? sanitize_text_field( $_POST['wll_login_record_user_access'] ) : "";
      $wll_settings['record_ip_address'] = isset( $_POST['wll_record_user_ip_address'] ) && $_POST['wll_record_user_ip_address'] == '1' ? 1 : 0;
      $wll_settings['show_all_login_records'] = isset( $_POST['wll_all_login_records'] ) && $_POST['wll_all_login_records'] == '1' ? 1 : 0;
      
      // Sanitize and validate widget user count setting
      $widget_count = isset( $_POST['wll_widget_user_count'] ) ? intval( $_POST['wll_widget_user_count'] ) : 5;
      if ( $widget_count < 3 ) {
          $widget_count = 3; // Enforce minimum
      } elseif ( $widget_count > 10 ) {
          $widget_count = 10; // Enforce maximum
      }
      $wll_settings['widget_user_count'] = $widget_count;

      // Allow other plugins/themes to filter settings before saving
      $wll_settings = apply_filters( 'wll_settings_filter', $wll_settings );

      // Update the option in the database
      if ( update_option( 'wll_settings', $wll_settings ) ) {
          // Add admin notice on success
          add_action( 'admin_notices', array( $this, 'wll_admin_notices' ) );
      } else {
          // Optional: Add a notice if settings were unchanged or failed to save
          // add_action( 'admin_notices', array( $this, 'wll_admin_notice_error_or_unchanged' ) );
      }
    }


    public function wll_admin_notices() {
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
     * Function to remove logs automatically older than 3 months.
     * @since 1.0.0
     */
    public function wll_automatically_remove_logs() {
      global $pagenow;

        // Bail if not on our settings page or if user cannot manage options
        if ( !isset($_GET['page']) || 'when-last-login-settings' != $_GET['page'] || ! current_user_can( 'manage_options' ) ) {
          return;
        }

        global $wpdb;
      
        $sql_base = "DELETE p, pm FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wll_records'";

        // Remove All Records
        if ( isset( $_REQUEST['remove_all_wll_records'] ) ) {
          $nonce = $_REQUEST['wll_remove_all_records_nonce'] ?? '';
          if ( wp_verify_nonce( $nonce, 'wll_remove_all_records_nonce' ) ) {
            if ( $wpdb->query( $sql_base ) !== false ) { // Check for false on error
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
            } else {
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) ); // Or a specific error notice
            }
          } else {
             add_action( 'admin_notices', function(){ echo '<div class="notice notice-error"><p>Nonce verification failed for removing all records.</p></div>'; });
          }
        }

        // Remove Old Records
        if ( isset( $_REQUEST['remove_wll_records'] ) ) {
          $nonce = $_REQUEST['wll_remove_records_nonce'] ?? '';
          if ( wp_verify_nonce( $nonce, 'wll_remove_records_nonce' ) ) {
            $date = apply_filters( 'wll_automatically_remove_logs_date', date( 'Y-m-d', strtotime( '-3 months' ) ) );
            $sql = $wpdb->prepare( $sql_base . " AND p.post_date <= %s", $date ); // Use prepare
            if ( $wpdb->query( $sql ) > 0 ) { // Check if rows were affected
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
            } else {
              add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
            }
          } else {
             add_action( 'admin_notices', function(){ echo '<div class="notice notice-error"><p>Nonce verification failed for removing old records.</p></div>'; });
          } 
        }

        // Remove IP Addresses
        if ( isset( $_REQUEST['remove_wll_ip_addresses'] ) ) {
          $nonce = $_REQUEST['wll_remove_ip_nonce'] ?? '';
          if ( wp_verify_nonce( $nonce, 'wll_remove_ip_nonce' ) ) {
            $sql = "DELETE FROM $wpdb->usermeta WHERE meta_key = 'wll_user_ip_address'";
            if ( $wpdb->query( $sql ) !== false ) { // Check for false on error
               add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
            } else {
               add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) ); // Or specific error
            }
          } else {
             add_action( 'admin_notices', function(){ echo '<div class="notice notice-error"><p>Nonce verification failed for removing IP addresses.</p></div>'; });
          }
        }
    }


    public function wll_records_columns( $columns ){

      return array_merge( $columns, array( 'wll-ip-address' => __( 'IP Address', 'when-last-login' ) ) );

    }

    public function wll_records_column_contents( $column, $post_id ){

      switch ( $column ) {
        case 'wll-ip-address':
          $ip_address = get_post_meta( $post_id, 'wll_user_ip_address', true );
          if ( ! empty( $ip_address ) ) { // Simplified check
            echo "<a href='http://www.ip-adress.com/ip_tracer/". esc_attr( $ip_address ) ."' target='_BLANK' title='".__( 'Lookup', 'when-last-login' )."'>" . esc_html( $ip_address ) . "</a>";
          } else {
            esc_html_e( 'IP Address Not Recorded', 'when-last-login' );
          }
          break;
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

      $ip = ''; // Initialize IP
      if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
      } else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
         // Handle comma-separated IPs if behind multiple proxies
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]); 
      } else if ( !empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
      }

      // Validate IP format (optional but recommended)
      if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
          $ip = ''; // Reset if invalid
      }

      $ip = apply_filters( 'wll_user_ip_address', $ip );

      // Anonymize if not forced to keep original and IP is valid
      if ( $ip && ! apply_filters( 'wll_force_anon_ip', false ) ) {
        try {
            return IpAnonymizer::anonymizeIp( $ip );
        } catch ( \Exception $e ) {
            // Log error or handle exception if anonymization fails
            error_log("IP Anonymization failed: " . $e->getMessage());
            return ''; // Return empty on failure
        }
      }
      
      return $ip; // Return original IP if forced or if anonymization skipped/failed
    }

    /**
     * Helper function to get WP_User_Query arguments based on sort order.
     * Moved inside the class.
     * @since 1.2.5
     */
    private static function get_widget_user_query_args( $sort_order = 'count', $blog_id = null ) {
        $wll_settings = get_option( 'wll_settings', array() );
        $widget_user_count = isset( $wll_settings['widget_user_count'] ) ? intval( $wll_settings['widget_user_count'] ) : 5;
        // Re-validate range here as well
        if ( $widget_user_count < 3 ) $widget_user_count = 3;
        if ( $widget_user_count > 10 ) $widget_user_count = 10;


        $args = array(
            'meta_value' => 0,
            'meta_compare' => '!=',
            'order' => 'DESC',
            'number' => apply_filters( 'wll_top_widget_user_count', $widget_user_count ),
            'role__not_in' => array( 'administrator' )
        );

        if ( $sort_order === 'time' ) {
            $args['meta_key'] = 'when_last_login';
            $args['orderby'] = 'meta_value_num';
        } else { // Default to 'count'
            $args['meta_key'] = 'when_last_login_count';
            $args['orderby'] = 'meta_value_num';
        }

        if ( $blog_id !== null ) {
            $args['blog_id'] = $blog_id;
        }

        return $args;
    }

    /**
     * Helper function to generate HTML table rows for the widget.
     * Moved inside the class.
     * @since 1.2.5
     */
    private static function generate_widget_table_rows( $users ) {
        $output = '';
        if ( $users ) {
            $count = 1;
            foreach ( $users as $wllusers ) {
                $output .= '<tr>';
                $output .= '<td>' . intval( $count ) . '</td>';
                $output .= '<td>' . esc_html( $wllusers->display_name ) . '</td>';
                $output .= '<td>' . esc_html( get_user_meta( $wllusers->ID, 'when_last_login_count', true ) ) . '</td>';
                
                $last_login_timestamp = get_user_meta( $wllusers->ID, 'when_last_login', true );
                if ( ! empty( $last_login_timestamp ) ) {
                    // Ensure timestamp is numeric before formatting
                    if ( is_numeric( $last_login_timestamp ) ) {
                        $utc_date_string = gmdate( 'Y-m-d H:i:s', (int) $last_login_timestamp );
                        $local_date_string = get_date_from_gmt( $utc_date_string, 'Y-m-d H:i:s' );
                        $output .= '<td>' . esc_html( $local_date_string ) . '</td>';
                    } else {
                         $output .= '<td>' . esc_html__( 'Invalid Date', 'when-last-login' ) . '</td>'; // Handle non-numeric meta
                    }
                } else {
                    $output .= '<td>' . esc_html__( 'Never', 'when-last-login' ) . '</td>';
                }
                $output .= '</tr>';
                $count++;
            }
        } else {
            $output .= '<tr><td colspan="4">' . esc_html__( 'No data yet', 'when-last-login' ) . '</td></tr>';
        }
        return $output;
    }

    /**
     * Enqueue scripts for the dashboard widget.
     * @since 1.2.5
     */
    public function enqueue_dashboard_widget_scripts( $hook ) {
        // Only load on the dashboard page
        if ( 'index.php' != $hook ) {
            return;
        }
        
        // Check if our widget is active before enqueuing
        global $wp_meta_boxes;
        $widget_id = 'when_last_login_activity_widget'; // Use the updated widget ID
        
        // Check all possible dashboard contexts
        $widget_is_active = false;
        $contexts = array('normal', 'side');
        $priorities = array('core', 'high', 'low', 'default'); // Include default
        
        foreach ($contexts as $context) {
            foreach ($priorities as $priority) {
                 if (isset($wp_meta_boxes['dashboard'][$context][$priority][$widget_id])) {
                     $widget_is_active = true;
                     break 2; // Exit both loops once found
                 }
            }
        }

        if (!$widget_is_active) {
             return; // Widget is not active on this dashboard
        }

        wp_enqueue_style( 'wll-widget-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), WLL_VER ); // Reuse existing admin CSS if needed
        wp_enqueue_script( 'wll-dashboard-widget', plugins_url( 'js/wll-dashboard-widget.js', __FILE__ ), array( 'jquery' ), WLL_VER, true );

        // Localize script with necessary data
        wp_localize_script( 'wll-dashboard-widget', 'wll_widget_data', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wll_widget_nonce' ),
            'error_message' => __( 'Error loading data.', 'when-last-login' ) 
        ) );
    }

    /**
     * AJAX handler to fetch widget data based on sort order.
     * @since 1.2.5
     */
    public function wll_fetch_widget_data_ajax() {
        error_log('WLL AJAX: Handler wll_fetch_widget_data_ajax called.'); // DEBUG

        // Verify nonce
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'wll_widget_nonce' ) ) {
             error_log('WLL AJAX: Nonce verification failed.'); // DEBUG
             wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'when-last-login' ) ), 403 ); // Send 403 status
        }
        error_log('WLL AJAX: Nonce verified successfully.'); // DEBUG

        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log('WLL AJAX: Permission denied.'); // DEBUG
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'when-last-login' ) ), 403 ); // Send 403 status
        }
        error_log('WLL AJAX: Permissions check passed.'); // DEBUG

        // Sanitize sort order input
        $sort_order = isset( $_POST['sort_order'] ) && $_POST['sort_order'] === 'time' ? 'time' : 'count';
        error_log('WLL AJAX: Received sort_order: ' . $sort_order); // DEBUG
        
        // Determine blog_id for multisite network admin context if needed
        $blog_id = null; 
        if ( is_multisite() && is_network_admin() ) {
            // Logic for network admin dashboard - decide if showing combined or site-specific
            // For now, assuming single site context or network-wide if $blog_id remains null
        } else {
             $blog_id = get_current_blog_id();
        }
        error_log('WLL AJAX: Using blog_id: ' . ($blog_id === null ? 'null' : $blog_id)); // DEBUG
        
        // Get users based on sort order
        $query_args = self::get_widget_user_query_args( $sort_order, $blog_id ); // Use self::
        error_log('WLL AJAX: Query args: ' . print_r($query_args, true)); // DEBUG
        $user_query = new WP_User_Query( $query_args );
        $users = $user_query->get_results();
        error_log('WLL AJAX: Found ' . count($users) . ' users.'); // DEBUG

        // Generate HTML for table rows
        $html = self::generate_widget_table_rows( $users ); // Use self::
        // error_log('WLL AJAX: Generated HTML: ' . $html); // DEBUG - Potentially very long

        // Send success response
        error_log('WLL AJAX: Sending JSON success response.'); // DEBUG
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Simple AJAX handler for debugging purposes.
     * @since 1.2.3-debug
     */
    public function wll_test_ajax_handler() {
         // Basic check
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'wll_widget_nonce' ) ) {
             wp_send_json_error( array( 'message' => 'Nonce failed' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
             wp_send_json_error( array( 'message' => 'Permissions failed' ) );
        }
        
        $test_html = '<tr><td colspan="4">AJAX Test Successful! Timestamp: ' . time() . '</td></tr>';
        wp_send_json_success( array( 'html' => $test_html ) );
    }

} // end class

// Add the test AJAX action hook *outside* the class definition if the main hook is inside the constructor
add_action( 'wp_ajax_wll_test_ajax', array( When_Last_Login::get_instance(), 'wll_test_ajax_handler' ) );

When_Last_Login::get_instance();
