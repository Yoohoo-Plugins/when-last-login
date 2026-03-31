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

use geertw\IpAnonymizer\IpAnonymizer;

define( 'WLL_VER', '1.3.0' );
define( 'WLL_DB_VER', '1.3.0' );

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
      add_action( 'admin_init', array( $this, 'check_db_version' ) );
      add_action( 'plugins_loaded', array( $this, 'text_domain' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'load_js_for_notice' ) );

      //Create the custom meta upon login
      add_action( 'wp_login', array( $this, 'last_login'), 10, 2 );
      add_action( 'user_register', array( $this, 'wll_user_register' ), 10, 1 );
      add_action( 'two_factor_user_authenticated', array( $this, 'two_factor_user_authenticated' ), 10, 2 );

      //Admin actions
      add_action( 'wp_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );      
      add_action( 'admin_notices', array( $this, 'update_notice' ) );

      add_action( 'wp_ajax_wll_hide_subscription_notice', array( $this, 'wll_hide_subscription_notice' ) );

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
      add_action( 'admin_menu', array( $this, 'wll_settings_page' ), 9 );
      add_action( 'admin_head', array( $this, 'wll_settings_page_head' ) );
      add_action( 'admin_init', array( $this, 'wll_automatically_remove_logs' ) );

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

    /**
     * Check and run database migrations.
     *
     * @since 1.3.0
     */
    public static function check_db_version() {
      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }

      $current_db_version = get_option( 'wll_db_version', '1.0.0' );

      // Upgrade to 1.3.0 - Create login records table.
      if ( version_compare( $current_db_version, '1.3.0', '<' ) ) {
        // Load the upgrade file.
        if ( file_exists( WLL_DIR_PATH . 'includes/updates/upgrade-1.3.0.php' ) ) {
          include_once WLL_DIR_PATH . 'includes/updates/upgrade-1.3.0.php';
          wll_upgrade_1_3_0();
        }
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
        <p><?php printf( __( 'Please consider leaving an honest review for When Last Login by visiting %s', 'when-last-login' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/when-last-login/reviews/#new-post' ) . '" target="_blank">' . __( 'this link', 'when-last-login' ) . '</a>' ); ?></p>
        </div>
        <?php
      }
    }

    public function wll_hide_subscription_notice(){
    if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wll_hide_notice_nonce' ) ) {
        wp_die( __( 'Nonce is invalid', 'when-last-login' ) );
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
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $browser    = When_Last_Login::parse_browser( $user_agent );
        $os         = When_Last_Login::parse_os( $user_agent );
        $device     = When_Last_Login::parse_device( $user_agent );

        WLL_DB::record_login( $user->ID, array(
          'ip_address' => $ip,
          'user_agent' => $user_agent,
          'browser'    => $browser,
          'os'         => $os,
          'device'     => $device,
        ) );
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
     * Setup admin backend to display custom meta box for login count for admins
     */
     public static function admin_dashboard_widget(){

      global $show_widget;

      $show_widget = apply_filters( 'when_last_login_show_admin_widget', true );
       //only show for administrators
       if( current_user_can( 'manage_options' ) && $show_widget ){
        wp_add_dashboard_widget( 'when_last_login_top_users', __( 'Most Frequent Logins', 'when-last-login' ), array( 'When_Last_Login', 'admin_dashboard_widget_display' ) );
       }
     }

    public static function admin_dashboard_widget_display(){

        global $show_widget;

        if( $show_widget != true ){
            return;
        }

        if( is_network_admin() ){

            $sites = get_sites();
                        
            if( is_array( $sites ) ){
            
                foreach( $sites as $site ){
                
                    $blog_id = $site->blog_id;
                    $blog_details = get_blog_details( $blog_id );
                
                    ?><table width="100%" text-align="center" class='wp-list-table striped widefat'>          
                    <tr>
                        <th colspan='4' style='text-align: center;'><strong><?php echo esc_html( $blog_details->blogname ) .' (<a href="'. esc_url( $blog_details->siteurl ).'" target="_BLANK">'. esc_html( $blog_details->siteurl ) . ')</a>'; ?></strong></th>
                    </tr>                      
                    <?php

                    $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'DESC', 'orderby' => 'meta_value_num', 'number' => apply_filters( 'wll_top_widget_user_count', 3 ), 'blog_id' => $blog_id, 'role__not_in' => array( 'administrator' ) ) );

                    $topusers = $user_query->get_results();

                    if( $topusers ){
                        ?>
                        <tr>
                            <th><strong>#</strong></th>
                            <th><strong><?php esc_html_e( 'Users', 'when-last-login' ); ?></strong></th>
                            <th><strong><?php esc_html_e( 'Login Count', 'when-last-login' ); ?></strong></th>
                            <th><strong><?php esc_html_e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                        </tr> 
                    <?php
                        
                        $count = 1;
                        
                        foreach($topusers as $wllusers){
                            echo '<tr><td>' . intval( $count ) . '</td>';
                            echo '<td>' . esc_html( $wllusers->display_name ) . '</td>';
                            echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td>';
                            echo '<td>' . date_i18n( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) . '</td></tr>';
                            $count++;
                        }
                      
                    } else {

                        echo '<tr><td colspan="4">'. esc_html__('No data yet', 'when-last-login').'</td></tr>';
            
                    }

                    ?></table><br/><?php

                }
             
                ?>

                <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php _e( 'View All Users', 'when-last-login' ); ?></a>
                <?php                
                    
            }
        
        } else {

            ?><table width="100%" text-align="center" class='wp-list-table striped widefat'>          

            <?php

            $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'DESC', 'orderby' => 'meta_value_num', 'number' => apply_filters( 'wll_top_widget_user_count', 3 ), 'role__not_in' => array( 'administrator' ) ) );

            $topusers = $user_query->get_results();

            if( $topusers ){
                ?>
                <tr>
                    <th><strong>#</strong></th>
                    <th><strong><?php esc_html_e( 'Users', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php esc_html_e( 'Login Count', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php esc_html_e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                </tr> 
            <?php
                
                $count = 1;
                
                foreach($topusers as $wllusers){
                    echo '<tr><td>' . intval( $count ) . '</td>';
                    echo '<td>' . $wllusers->display_name . '</td>';
                    echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td>';
                    echo '<td>' . date_i18n( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) . '</td></tr>';
                    $count++;
                }
              
            } else {

                echo '<tr><td colspan="4">'. esc_html__( 'No data yet', 'when-last-login' ).'</td></tr>';
    
            }

            ?></table><br/><?php

        ?>

        <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php esc_html_e( 'View All Users', 'when-last-login' ); ?></a>
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
            if( get_the_author_meta( 'when_last_login', $id ) === 0 ){
              return esc_html__( 'Never', 'when-last-login' );
            } else {
              update_user_meta( $id, 'when_last_login', 0 );
              return esc_html__( 'Never', 'when-last-login' );
            }
          }
        } else if( $column_name == 'when_last_login_ip_address' ){

          $when_last_login_ip_address = get_user_meta( $id, 'wll_user_ip_address', true );

          if ( $when_last_login_ip_address && $when_last_login_ip_address != "" && $settings['record_ip_address'] != "") {
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
            $row['when_last_login'] = date( 'Y-m-d H:i:s', $last_login );
        } else {
            $row['when_last_login'] = __( 'Never', 'when-last-login' );
        }
        return $row;
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

      $wll_settings = array();

      if( isset( $_POST['wll_save_settings'] ) ){

        if( wp_verify_nonce( $_POST['_nonce'], 'wll_settings_nonce' ) ) {

          $wll_settings['user_access'] = isset( $_POST['wll_login_record_user_access'] ) ? sanitize_text_field( $_POST['wll_login_record_user_access'] ) : "";
          $wll_settings['record_ip_address'] = isset( $_POST['wll_record_user_ip_address'] ) && sanitize_text_field( $_POST['wll_record_user_ip_address'] ) == '1'  ? 1 : 0;

          $wll_settings = apply_filters( 'wll_settings_filter', $wll_settings );

          if ( update_option( 'wll_settings', $wll_settings ) ) {
            //show admin notice here.
            add_action( 'admin_notices', array( $this, 'wll_admin_notices' ) );
          }
        } else {
          die( 'nonce not valid' );
        }

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
     * Function to remove IP addresses from usermeta.
     * @since 1.0.0
     */
    public function wll_automatically_remove_logs() {
      global $pagenow, $wpdb;

      // Bail if there is not ?page=xxx parameter
      if ( empty( $_GET['page'] ) ) {
        return;
      }

      // Bail if not on our settings page
      if ( 'admin.php' == $pagenow && 'when-last-login-settings' != $_GET['page'] ) {
        return;
      }

      if ( isset( $_REQUEST['remove_wll_ip_addresses'] ) ) {

        $nonce = $_REQUEST['wll_remove_ip_nonce'];
        if ( wp_verify_nonce( $nonce, 'wll_remove_ip_nonce' ) ) {

          $sql = "DELETE FROM $wpdb->usermeta WHERE meta_key = 'wll_user_ip_address'";

          if ( $wpdb->query( $sql ) > 0 ) {
            add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
          } else {
            add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
          }
        } else {
          die( 'nonce not valid.' );
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

      if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
      } else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
      } else {
        $ip = $_SERVER['REMOTE_ADDR'];
      }

      $ip = apply_filters( 'wll_user_ip_address', $ip );

      if ( apply_filters( 'wll_force_anon_ip', false ) ) {
        return $ip;
      } else {
        return IpAnonymizer::anonymizeIp( $ip );
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

} // end class
When_Last_Login::get_instance();
