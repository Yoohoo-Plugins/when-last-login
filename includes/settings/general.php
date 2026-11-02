<?php
/**
 * General settings tab for When Last Login.
 *
 * @package When_Last_Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'wll_settings' );

if ( isset( $settings['record_ip_address'] ) && intval( $settings['record_ip_address'] ) == 1 ) { $checked = 1; } else { $checked = 0; }
$track_all_records = isset( $settings['track_all_records'] ) ? intval( $settings['track_all_records'] ) : 1;
$show_wc_last_active = isset( $settings['show_wc_last_active'] ) && intval( $settings['show_wc_last_active'] ) == 1;
$hide_admin_menu = isset( $settings['hide_admin_menu'] ) && intval( $settings['hide_admin_menu'] ) == 1;
$woocommerce_active = class_exists( 'WooCommerce' );
?>
<form method="post">
<table class="form-table">
	<tr>
		<th><h2><?php esc_html_e( 'Options' , 'when-last-login' ); ?></h2></th>
		<td></td>
	</tr>
	<tr>
		<th><label for="wll_record_user_ip_address"><?php esc_html_e( "Record user's IP address", "when-last-login" ); ?></label><br></th>
		<td><label>
			<input type='checkbox' id='wll_record_user_ip_address' value='1' name='wll_record_user_ip_address' <?php checked( 1, $checked ); ?>/>
			<small><?php esc_html_e( 'This will anonymize the IP address to support GDPR regulations.', 'when-last-login' ); ?></small>
		</label></td>
	</tr>
	<tr>
		<th><label for="wll_track_all_records"><?php esc_html_e( 'Enable All Login Records', 'when-last-login' ); ?></label><br></th>
		<td><label>
			<input type='checkbox' id='wll_track_all_records' value='1' name='wll_track_all_records' <?php checked( 1, $track_all_records ); ?>/>
			<small><?php
			printf(
				/* translators: %s: add-on name wrapped in a link */
				esc_html__( 'Please enable this option if using the %s', 'when-last-login' ),
				'<a href="' . esc_url( 'https://yoohooplugins.com/plugins/when-last-login-user-statistics/' ) . '" target="_blank" rel="noopener noreferrer"><strong>' . esc_html__( 'When Last Login - User Statistics Add On', 'when-last-login' ) . '</strong></a>'
			);
			?></small>
		</label></td>
	</tr>

	<?php if ( $woocommerce_active ) : ?>
	<tr>
		<th><label for="wll_show_wc_last_active"><?php esc_html_e( 'Show WooCommerce Last Active', 'when-last-login' ); ?></label><br></th>
		<td><label>
			<input type='checkbox' id='wll_show_wc_last_active' value='1' name='wll_show_wc_last_active' <?php checked( 1, $show_wc_last_active ); ?>/>
			<small><?php esc_html_e( 'Display the WooCommerce last active column on the users list. Shows when customers were last active on your store.', 'when-last-login' ); ?></small>
		</label></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th><label for="wll_hide_admin_menu"><?php esc_html_e( 'Hide Admin Menu (Streamline Mode)', 'when-last-login' ); ?></label><br></th>
		<td><label>
			<input type='checkbox' id='wll_hide_admin_menu' value='1' name='wll_hide_admin_menu' <?php checked( 1, $hide_admin_menu ); ?>/>
			<small><?php esc_html_e( 'Hide the WLL admin menu and dashboard widget. Login tracking continues in the background. Users list column still shows last login data.', 'when-last-login' ); ?></small>
		</label></td>
	</tr>

	<tr>
		<th><label for="wll_widget_user_count"><?php esc_html_e( 'Dashboard widget users', 'when-last-login' ); ?></label></th>
		<td>
			<?php
			$widget_count_default = defined( 'WLL_WIDGET_USER_COUNT' ) ? (int) WLL_WIDGET_USER_COUNT : 3;
			$widget_count_val = ( is_array( $settings ) && isset( $settings['widget_user_count'] ) ) ? absint( $settings['widget_user_count'] ) : $widget_count_default;
			if ( $widget_count_val < 3 || $widget_count_val > 10 ) {
				$widget_count_val = $widget_count_default;
			}
			?>
			<input type="number" min="3" max="10" step="1" name="wll_widget_user_count" id="wll_widget_user_count" value="<?php echo esc_attr( $widget_count_val ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'How many users the Dashboard widget lists. Choose from 3 to 10. The widget keeps showing 3 until you save a different number.', 'when-last-login' ); ?></p>
		</td>
	</tr>

	<tr>
		<input type="hidden" name="_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wll_settings_nonce' ) ); ?>">
	    <th><input type="submit" name="wll_save_settings"  class="button-primary" value="<?php esc_attr_e('Save Settings', 'when-last-login'); ?>" /></th>
	    <td></td>
	</tr>
</table>
</form>
<?php if ( $track_all_records ) : ?>
<table class="form-table">
	<tr>
		<th><h2><?php esc_html_e( 'Tools', 'when-last-login' ); ?></h2></th>
		<td></td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Clear old logs (90+ days)', 'when-last-login' ); ?></th>
		<td>
			<form method="post">
				<?php wp_nonce_field( 'wll_remove_old_records_nonce', 'wll_remove_old_records_nonce' ); ?>
				<input type="hidden" name="wll_remove_old_records" value="1" />
				<?php submit_button( __( 'Run Now', 'when-last-login' ), 'secondary', 'wll_remove_old_records_submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to clear records older than 90 days?', 'when-last-login' ) ) . "');" ) ); ?>
			</form>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Clear all login records', 'when-last-login' ); ?></th>
		<td>
			<form method="post">
				<?php wp_nonce_field( 'wll_remove_all_records_nonce', 'wll_remove_all_records_nonce' ); ?>
				<input type="hidden" name="wll_remove_all_records" value="1" />
				<?php submit_button( __( 'Run Now', 'when-last-login' ), 'secondary', 'wll_remove_all_records_submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to clear all login records?', 'when-last-login' ) ) . "');" ) ); ?>
			</form>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Clear all IP addresses', 'when-last-login' ); ?></th>
		<td>
			<form method="post">
				<?php wp_nonce_field( 'wll_remove_ip_nonce', 'wll_remove_ip_nonce' ); ?>
				<input type="hidden" name="remove_wll_ip_addresses" value="1" />
				<?php submit_button( __( 'Run Now', 'when-last-login' ), 'secondary', 'wll_remove_ip_submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to remove all IP addresses?', 'when-last-login' ) ) . "');" ) ); ?>
			</form>
		</td>
	</tr>
</table>
<?php endif; ?>
