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
<table class="form-table">
	<tr>
		<th><h2><?php esc_html_e( 'Options' , 'when-last-login' ); ?></h2></th>
		<td></td>
	</tr>
	<tr>
		<th><?php esc_html_e( "Record user's IP address", "when-last-login" ); ?><br></th>
		<td><input type='checkbox' value='1' name='wll_record_user_ip_address' <?php checked( 1, $checked ); ?>/>
			<small><?php esc_html_e( 'This will anonymize the IP address to support GDPR regulations.', 'when-last-login' ); ?></small></td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Enable All Login Records', 'when-last-login' ); ?><br></th>
		<td><input type='checkbox' value='1' name='wll_track_all_records' <?php checked( 1, $track_all_records ); ?>/>
			<small><?php 
			echo esc_html( 'Please enable this option if using the', 'when-last-login' ) . " <a href='" . esc_url( 'https://yoohooplugins.com/plugins/when-last-login-user-statistics/' ) . "' target='_blank'><strong>" . esc_html( 'When Last Login - User Statistics Add On', 'when-last-login' ) . "</strong></a>";
		?></small></td>
	</tr>

	<?php if ( $woocommerce_active ) : ?>
	<tr>
		<th><?php esc_html_e( 'Show WooCommerce Last Active', 'when-last-login' ); ?><br></th>
		<td><input type='checkbox' value='1' name='wll_show_wc_last_active' <?php checked( 1, $show_wc_last_active ); ?>/>
			<small><?php esc_html_e( 'Display the WooCommerce last active column on the users list. Shows when customers were last active on your store.', 'when-last-login' ); ?></small></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th><?php esc_html_e( 'Hide Admin Menu (Streamline Mode)', 'when-last-login' ); ?><br></th>
		<td><input type='checkbox' value='1' name='wll_hide_admin_menu' <?php checked( 1, $hide_admin_menu ); ?>/>
			<small><?php esc_html_e( 'Hide the WLL admin menu and dashboard widget. Login tracking continues in the background. Users list column still shows last login data.', 'when-last-login' ); ?></small></td>
	</tr>

	<?php if ( $track_all_records ) : ?>
	<tr>
		<th><h2><?php esc_html_e( 'Tools', 'when-last-login' ); ?></h2></th>
		<td></td>
	</tr>
	<?php
		$old_records_message = esc_html__( 'Are you sure you want to clear records older than 90 days?', 'when-last-login' );
		$all_records_message = esc_html__( 'Are you sure you want to clear all login records?', 'when-last-login' );
		$all_ip_message = esc_html__( 'Are you sure you want to remove all IP addresses?', 'when-last-login' );
		$remove_old_nonce = wp_create_nonce( 'wll_remove_old_records_nonce' );
		$remove_all_nonce = wp_create_nonce( 'wll_remove_all_records_nonce' );
		$remove_ip_nonce = wp_create_nonce( 'wll_remove_ip_nonce' );
	?>
	<script>
		function wll_remove_old_records(){
			if( window.confirm('<?php echo esc_js( $old_records_message ); ?>')) {
				window.location.href = "<?php echo esc_js( add_query_arg( array( 'wll_remove_old_records' => '1', 'wll_remove_old_records_nonce' => $remove_old_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ) ); ?>";
			}
		}
		function wll_remove_all_records(){
			if( window.confirm('<?php echo esc_js( $all_records_message ); ?>')) {
				window.location.href = "<?php echo esc_js( add_query_arg( array( 'wll_remove_all_records' => '1', 'wll_remove_all_records_nonce' => $remove_all_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ) ); ?>";
			}
		}
		function wll_remove_all_ips(){
			if( window.confirm('<?php echo esc_js( $all_ip_message ); ?>')) {
				window.location.href = "<?php echo esc_js( add_query_arg( array( 'remove_wll_ip_addresses' => '1', 'wll_remove_ip_nonce' => $remove_ip_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ) ); ?>";
			}
		}
	</script>

	<tr>
		<th><?php esc_html_e( 'Clear old logs (90+ days)', 'when-last-login' ); ?></th>
		<td><a href="javascript:void(0);" onclick="wll_remove_old_records(); return false;" class="button"><?php esc_html_e( 'Run Now', 'when-last-login' ); ?></a></td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Clear all login records', 'when-last-login' ); ?></th>
		<td><a href="javascript:void(0);" onclick="wll_remove_all_records(); return false;" class="button"><?php esc_html_e( 'Run Now', 'when-last-login' ); ?></a></td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Clear all IP addresses', 'when-last-login' ); ?></th>
		<td><a href="javascript:void(0);" onclick="wll_remove_all_ips(); return false;" class="button"><?php esc_html_e( 'Run Now', 'when-last-login' ); ?></a></td>
	</tr>
	<?php endif; ?>

	<tr>
		<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( 'wll_settings_nonce' ); ?>">
	    <th><input type="submit" name="wll_save_settings"  class="button-primary" value="<?php esc_attr_e('Save Settings', 'when-last-login'); ?>" /></th>
	    <td></td>
	</tr>
</table>
