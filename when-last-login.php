<?php
/*
Plugin Name: When Last Login
Plugin URI: https://whenlastlogin.com
Description: See when a user logs into your WordPress site.
Version: 1.2.4
Author: Yoohoo Plugins
Author URI: https://yoohooplugins.com
Text Domain: when-last-login
Domain Path: /languages
*/

use geertw\IpAnonymizer\IpAnonymizer;

define( 'WLL_VER', '1.2.4' );

class When_Last_Login {

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /** User IDs already recorded during this request, so one login is stored once. */
    private static $recorded_login_user_ids = array();

    /** User IDs whose auth cookie was suppressed before login finished. */
    private static $incomplete_login_user_ids = array();

    /** User already logged in at the start of the request. A later cookie is a refresh. */
    private static $already_authenticated_user_id = 0;

    /** Whether the send_auth_cookies watcher is attached. */
    private static $watching_auth_cookies = false;

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

      //Create the custom meta upon login
      add_action( 'wp_login', array( $this, 'last_login'), 10, 2 );
      // Fires for wp_signon() and for logins that only call wp_set_auth_cookie()
      // (SSO, WooCommerce Subscriptions auto-login, other custom auth).
      add_action( 'set_logged_in_cookie', array( $this, 'prepare_login_from_cookie' ), 10, 6 );
      add_action( 'init', array( $this, 'remember_already_authenticated_user' ), 1 );
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
      //TODO: Improve integration with Member List and Paid Memberships Pro
      add_action( 'pmpro_memberslist_extra_cols_header', array( $this, 'pmpro_memberlist_add_header' ) );
      add_action( 'pmpro_memberslist_extra_cols_body', array( $this, 'pmpro_memberlist_add_column_data' ) );
      add_action( 'init', array( $this, 'login_record_cp' ) );

      add_action( 'admin_menu', array( $this, 'wll_settings_page' ), 9 );
      add_action( 'admin_init', array( $this, 'wll_settings_page_head' ) );
      add_action( 'admin_notices', array( $this, 'wll_admin_notices' ) );
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
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( -1 );
      }
      if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'wll_hide_notice_nonce' ) ) {
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
	 * Remember who was already logged in once WordPress has validated the cookie.
	 */
	public static function remember_already_authenticated_user() {
		self::$already_authenticated_user_id = (int) get_current_user_id();
	}

	/**
	 * Attach the cookie watcher after authentication filters.
	 *
	 * set_logged_in_cookie runs before send_auth_cookies, so this is added
	 * after Two Factor's PHP_INT_MAX suppression on the password step.
	 */
	public static function prepare_login_from_cookie( $logged_in_cookie, $expire, $expiration, $user_id, $scheme = 'logged_in', $token = '' ) {
		unset( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token );

		if ( self::$watching_auth_cookies ) {
			return;
		}

		add_filter( 'send_auth_cookies', array( __CLASS__, 'record_login_if_cookies_sent' ), PHP_INT_MAX, 6 );
		self::$watching_auth_cookies = true;
	}

	/**
	 * Record a login only when the auth cookie will be sent.
	 *
	 * wp_set_current_user() runs on every request and is not a login.
	 *
	 * @return bool
	 */
	public static function record_login_if_cookies_sent( $send, $expire = 0, $expiration = 0, $user_id = 0, $scheme = '', $token = '' ) {
		unset( $expire, $expiration, $scheme, $token );

		$user_id = (int) $user_id;
		if ( $user_id < 1 ) {
			return $send;
		}

		/*
		 * Two Factor blocks the cookie at PHP_INT_MAX until the second factor
		 * succeeds, then fires wp_login anyway. A global "do not send cookies"
		 * filter at a normal priority (the WordPress test suite does this) is
		 * not that challenge: wp_login still represents a finished login.
		 */
		if ( self::login_cookies_blocked_for_challenge() ) {
			self::$incomplete_login_user_ids[ $user_id ] = true;
			return $send;
		}

		unset( self::$incomplete_login_user_ids[ $user_id ] );

		if ( ! $send ) {
			return $send;
		}

		if ( self::$already_authenticated_user_id === $user_id ) {
			return $send;
		}

		$user = get_user_by( 'id', $user_id );
		if ( $user instanceof WP_User ) {
			self::last_login( $user->user_login, $user );
		}

		return $send;
	}

	/**
	 * Two Factor adds __return_false at PHP_INT_MAX on the password step and
	 * removes it before the real cookie. A normal-priority suppression is not that.
	 *
	 * @return bool
	 */
	private static function login_cookies_blocked_for_challenge() {
		global $wp_filter;

		if ( ! isset( $wp_filter['send_auth_cookies'] ) || empty( $wp_filter['send_auth_cookies']->callbacks[ PHP_INT_MAX ] ) ) {
			return false;
		}

		foreach ( $wp_filter['send_auth_cookies']->callbacks[ PHP_INT_MAX ] as $callback ) {
			if ( isset( $callback['function'] ) && '__return_false' === $callback['function'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Track the user's login timestamp and "All Time Record". 
	 *
	 * @param string $user_login The username that is logging in.
	 * @param WP_User $user The WordPress user object.
	 * @return void
	 */
     public static function last_login( $user_login, $user ) {

      if ( ! $user instanceof WP_User ) {
        $user = get_user_by( 'login', (string) $user_login );
      }

      if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
        return;
      }

      // Password step of a challenge that suppressed the auth cookie.
      if ( ! empty( self::$incomplete_login_user_ids[ $user->ID ] ) ) {
        return;
      }

      if ( isset( self::$recorded_login_user_ids[ $user->ID ] ) ) {
        return;
      }

      global $show_login_records;

      $record_login = apply_filters( 'wll_record_login', true, $user, $user_login );

      // If filter isn't true, don't record login at all!
      if ( ! $record_login ) {
        return;
      }

      self::$recorded_login_user_ids[ $user->ID ] = true;

      //get/update user meta 'when_last_login' on login and add time() to it.
      update_user_meta( $user->ID, 'when_last_login', time() );

      //get and update user meta 'when_last_login_count' on login for # of login counts. Thanks to Jarryd Long (@jarrydlong) for the assistance
      $wll_count = get_user_meta( $user->ID, 'when_last_login_count', true );

      if( $wll_count === false ){
        update_user_meta($user->ID, 'when_last_login_count', 1);
      } else {
        $wll_new_value = intval($wll_count);
        $wll_new_value = $wll_new_value + 1;

        update_user_meta($user->ID, 'when_last_login_count', $wll_new_value);
      }

      if( $show_login_records == true ){
        $args = array(
          'post_title'    => $user->data->display_name . __( ' has logged in at ', 'when-last-login' ) . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
          'post_status'   => 'publish',
          'post_author'   => $user->ID,
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
        
          update_user_meta( $user->ID, 'wll_user_ip_address', $ip );
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
        wp_add_dashboard_widget( 'when_last_login_top_users', __( 'Most Frequent Logins', 'when-last-login' ), array( 'When_Last_Login', 'admin_dashboard_widget_display' ) );
       }
     }

    public static function admin_dashboard_widget_display(){

        global $show_widget, $show_login_records;

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
                            echo '<td>' . esc_html( get_user_meta( $wllusers->ID, 'when_last_login_count', true ) ) . '</td>';
                            echo '<td>' . esc_html( date_i18n( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) ) . '</td></tr>';
                            $count++;
                        }
                      
                    } else {

                        echo '<tr><td colspan="4">'. esc_html__('No data yet', 'when-last-login').'</td></tr>';
            
                    }

                    ?></table><br/><?php

                }
             
                ?>

                <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php _e( 'View All Users', 'when-last-login' ); ?></a>

                <?php if( $show_login_records == true ){ ?>
                    <a style="float:right" href="<?php echo admin_url( 'edit.php?post_type=wll_records' ); ?>"><?php _e( 'View Login Records', 'when-last-login' ); } //end the if filter check here ?></a>
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
                    echo '<td>' . esc_html( $wllusers->display_name ) . '</td>';
                    echo '<td>' . esc_html( get_user_meta( $wllusers->ID, 'when_last_login_count', true ) ) . '</td>';
                    echo '<td>' . esc_html( date_i18n( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) ) . '</td></tr>';
                    $count++;
                }
              
            } else {

                echo '<tr><td colspan="4">'. esc_html__( 'No data yet', 'when-last-login' ).'</td></tr>';
    
            }

            ?></table><br/><?php

        ?>

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
            return "<a href='" . esc_url( 'https://www.ip-adress.com/ip_tracer/' . $when_last_login_ip_address ) . "' target='_BLANK' title='" . esc_attr__( 'Lookup', 'when-last-login' ) . "'>" . esc_html( $when_last_login_ip_address ) . "</a>";
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
      if ( ! $query instanceof WP_User_Query ) {
        return;
      }

      $orderby = $query->get( 'orderby' );
      if ( is_array( $orderby ) || 'when_last_login' !== $orderby ) {
        return;
      }

      // meta_key would INNER JOIN usermeta and drop users who have no
      // when_last_login row (never logged in, and never given a 0).
      add_action( 'pre_user_query', array( __CLASS__, 'include_users_without_login_meta' ) );
    }

    /**
     * LEFT JOIN so users with no when_last_login row still sort. Missing meta sorts as 0.
     *
     * @param WP_User_Query $query User query.
     */
    public static function include_users_without_login_meta( $query ) {
      global $wpdb;

      if ( ! $query instanceof WP_User_Query ) {
        return;
      }

      if ( 'when_last_login' !== $query->query_vars['orderby'] ) {
        return;
      }

      if ( false !== strpos( $query->query_from, 'wll_last_login' ) ) {
        return;
      }

      $order = 'DESC';
      if ( isset( $query->query_vars['order'] ) && 'ASC' === strtoupper( (string) $query->query_vars['order'] ) ) {
        $order = 'ASC';
      }

      $query->query_from .= $wpdb->prepare(
        " LEFT JOIN {$wpdb->usermeta} AS wll_last_login ON ( {$wpdb->users}.ID = wll_last_login.user_id AND wll_last_login.meta_key = %s )",
        'when_last_login'
      );

      if ( ! preg_match( '/\bDISTINCT\b/', $query->query_fields ) ) {
        $query->query_fields = preg_replace( '/^(SQL_CALC_FOUND_ROWS\s+)?/', '$1DISTINCT ', $query->query_fields, 1 );
      }

      $query->query_orderby = "ORDER BY CAST(COALESCE(wll_last_login.meta_value, '0') AS UNSIGNED) {$order}, {$wpdb->users}.ID {$order}";
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

      if ( empty( $_GET['page'] ) || 'when-last-login-settings' !== $_GET['page'] ) {
        return;
      }

      if ( isset( $_POST['wll_save_settings'] ) ) {

        if ( ! current_user_can( 'manage_options' ) ) {
          wp_die( esc_html__( 'You do not have permission to save these settings.', 'when-last-login' ) );
        }

        if ( isset( $_POST['_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'wll_settings_nonce' ) ) {

          $wll_settings = array();
          $wll_settings['user_access'] = isset( $_POST['wll_login_record_user_access'] ) ? sanitize_text_field( wp_unslash( $_POST['wll_login_record_user_access'] ) ) : '';
          $wll_settings['record_ip_address'] = isset( $_POST['wll_record_user_ip_address'] ) && sanitize_text_field( wp_unslash( $_POST['wll_record_user_ip_address'] ) ) == '1' ? 1 : 0;
          $wll_settings['show_all_login_records'] = isset( $_POST['wll_all_login_records'] ) && sanitize_text_field( wp_unslash( $_POST['wll_all_login_records'] ) ) == '1' ? 1 : 0;

          $wll_settings = apply_filters( 'wll_settings_filter', $wll_settings );

          update_option( 'wll_settings', $wll_settings );

          $redirect_args = array(
            'page' => 'when-last-login-settings',
            'wll-settings-updated' => '1',
          );
          if ( ! empty( $_GET['tab'] ) ) {
            $redirect_args['tab'] = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
          }
          wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
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
     * Function to remove logs automatically older than 3 months.
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

      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }
      
      $sql = "DELETE p, pm FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wll_records'";

      if ( isset( $_REQUEST['remove_all_wll_records'] ) ) {

        $nonce = $_REQUEST['wll_remove_all_records_nonce'];
        if ( wp_verify_nonce( $nonce, 'wll_remove_all_records_nonce' ) ) {

          if ( $wpdb->query( $sql ) > 0 ) {
            add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
          } else {
            add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
          }
        } else {
          die( 'nonce not valid.' );
        }
      }

      if ( isset( $_REQUEST['remove_wll_records'] ) ) {

        $nonce = $_REQUEST['wll_remove_records_nonce'];
        if ( wp_verify_nonce( $nonce, 'wll_remove_records_nonce' ) ) {

          $date = apply_filters( 'wll_automatically_remove_logs_date', gmdate( 'Y-m-d', strtotime( '-3 months' ) ) );

          $sql = $wpdb->prepare(
            "DELETE p, pm FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_date <= %s",
            'wll_records',
            $date
          );

          if ( $wpdb->query( $sql ) > 0 ) {
            add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
          } else {
            add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
          }
        } else {
          die( 'nonce not valid.' );
        } 
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


    public function wll_records_columns( $columns ){

      return array_merge( $columns, array( 'wll-ip-address' => __( 'IP Address', 'when-last-login' ) ) );

    }

    public function wll_records_column_contents( $column, $post_id ){

      switch ( $column ) {
        case 'wll-ip-address':
          $ip_address = get_post_meta( $post_id, 'wll_user_ip_address', true );
          if ( ! empty( $ip_address ) && $ip_address != "" ) {
            echo "<a href='" . esc_url( 'https://www.ip-adress.com/ip_tracer/' . $ip_address ) . "' target='_BLANK' title='" . esc_attr__( 'Lookup', 'when-last-login' ) . "'>" . esc_html( $ip_address ) . "</a>";
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

} // end class
When_Last_Login::get_instance();
