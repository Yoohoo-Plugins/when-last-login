<?php
/**
 * Login Records List Table
 *
 * Displays login records using WP_List_Table API.
 *
 * @package When_Last_Login
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class WLL_List_Table
 *
 * Displays login records in the admin.
 *
 * @since 1.3.0
 */
class WLL_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since  1.3.0
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Login Record', 'when-last-login' ),
			'plural'   => __( 'Login Records', 'when-last-login' ),
			'ajax'     => false,
		) );
	}

	/**
	 * Get columns for the list table.
	 *
	 * @since  1.3.0
	 * @return array Columns array.
	 */
	public function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'user'       => __( 'User', 'when-last-login' ),
			'login_time' => __( 'Login Time', 'when-last-login' ),
			'ip_address' => __( 'IP Address', 'when-last-login' ),
			'browser'    => __( 'Browser', 'when-last-login' ),
			'os'         => __( 'Operating System', 'when-last-login' ),
			'device'     => __( 'Device', 'when-last-login' ),
		);

		return $columns;
	}

	/**
	 * Get sortable columns.
	 *
	 * @since  1.3.0
	 * @return array Sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'user'       => array( 'user_id', false ),
			'login_time' => array( 'login_time', true ),
			'ip_address' => array( 'ip_address', false ),
			'browser'    => array( 'browser', false ),
			'os'         => array( 'os', false ),
			'device'     => array( 'device', false ),
		);
	}

	/**
	 * Column default.
	 *
	 * @since  1.3.0
	 *
	 * @param  object $item        Row item.
	 * @param  string $column_name Column name.
	 * @return string             Column value.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'login_time':
				return get_date_from_gmt( $item->login_time, 'Y-m-d H:i:s' );

			case 'ip_address':
				if ( ! empty( $item->ip_address ) ) {
					return sprintf(
						'<a href="http://www.ip-adress.com/ip_tracer/%s" target="_blank" title="%s">%s</a>',
						esc_attr( $item->ip_address ),
						esc_attr__( 'Lookup', 'when-last-login' ),
						esc_html( $item->ip_address )
					);
				}
				return esc_html__( 'Not Recorded', 'when-last-login' );

			case 'browser':
			case 'os':
			case 'device':
				return ! empty( $item->$column_name ) ? esc_html( $item->$column_name ) : '—';

			default:
				return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
		}
	}

	/**
	 * Column checkbox.
	 *
	 * @since  1.3.0
	 *
	 * @param  object $item Row item.
	 * @return string      Checkbox HTML.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="record_id[]" value="%d" />',
			$item->id
		);
	}

	/**
	 * Column user.
	 *
	 * @since  1.3.0
	 *
	 * @param  object $item Row item.
	 * @return string      User HTML.
	 */
	public function column_user( $item ) {
		$user = get_user_by( 'id', $item->user_id );

		if ( ! $user ) {
			return sprintf(
				/* translators: %d: User ID */
				esc_html__( 'User #%d (deleted)', 'when-last-login' ),
				$item->user_id
			);
		}

		$user_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) ),
			esc_html( $user->display_name )
		);

		return $user_link;
	}

	/**
	 * Bulk actions.
	 *
	 * @since  1.3.0
	 * @return array Bulk actions.
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'when-last-login' ),
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @since  1.3.0
	 */
	public function process_bulk_action() {
		if ( 'delete' === $this->current_action() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '';

			if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				wp_die( esc_html__( 'Invalid nonce', 'when-last-login' ) );
			}

			$record_ids = isset( $_REQUEST['record_id'] ) ? array_map( 'intval', $_REQUEST['record_id'] ) : array();

			if ( ! empty( $record_ids ) ) {
				WLL_DB::delete_records( $record_ids );
			}
		}
	}

	/**
	 * Prepare items for display.
	 *
	 * @since  1.3.0
	 */
	public function prepare_items() {
		global $wpdb;

		// Process bulk actions.
		$this->process_bulk_action();

		// Columns.
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination.
		$per_page = $this->get_items_per_page( 'wll_records_per_page', 20 );
		$page = $this->get_pagenum();
		$offset = ( $page - 1 ) * $per_page;

		// Sorting.
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'login_time';
		$order = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC';

		// Map orderby to valid columns.
		$valid_orderby = array( 'user_id', 'login_time', 'ip_address', 'browser', 'os', 'device' );
		if ( ! in_array( $orderby, $valid_orderby, true ) ) {
			$orderby = 'login_time';
		}

		if ( ! in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$records_table = WLL_DB::get_table_name( WLL_DB::LOGIN_RECORDS_TABLE );

		// Search.
		$search = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		$search_sql = '';

		if ( ! empty( $search ) ) {
			$search_user = get_user_by( 'login', $search );
			if ( $search_user ) {
				$search_sql = $wpdb->prepare( ' WHERE user_id = %d', $search_user->ID );
			} else {
				$search_sql = $wpdb->prepare(
					' WHERE ip_address LIKE %s OR browser LIKE %s OR os LIKE %s OR device LIKE %s',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%'
				);
			}
		}

		// Total items.
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $records_table $search_sql" );

		// Get items.
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $records_table $search_sql ORDER BY $orderby $order LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		// Pagination.
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Message when no items found.
	 *
	 * @since  1.3.0
	 */
	public function no_items() {
		esc_html_e( 'No login records found.', 'when-last-login' );
	}

	/**
	 * Display the search box.
	 *
	 * @since  1.3.0
	 *
	 * @param  string $text     Button text.
	 * @param  string $input_id Input ID.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}
}