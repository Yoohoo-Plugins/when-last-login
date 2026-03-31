<?php $settings = get_option( 'wll_settings' ); ?>

<?php if( isset( $settings['record_ip_address'] ) && intval( $settings['record_ip_address'] ) == 1 ){ $checked = 1; } else { $checked = 0; } ?>
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
		<th><h2><?php esc_html_e( 'Tools', 'when-last-login' ); ?></h2></th>
		<td></td>
	</tr>
	<!-- loaded from general.php -->
	<?php 
		$all_ip_message = esc_html__( 'Are you sure you want to remove all IP addresses?', 'when-last-login' );
		$remove_ip_nonce = wp_create_nonce( 'wll_remove_ip_nonce' );
	?>

		<script>
			function wll_remove_all_ips(){
				if( window.confirm('<?php echo $all_ip_message; ?>')) {
					window.location.href = "<?php echo add_query_arg( array( 'remove_wll_ip_addresses' => '1', 'wll_remove_ip_nonce' => $remove_ip_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ); ?>";
				}
			}
	</script>

	<tr>
		<th><?php esc_html_e( 'Clear all IP Addresses', 'when-last-login' ); ?></th>
		<td><a href="javascript:void(0);" onclick="wll_remove_all_ips(); return false;" class="button-primary"><?php esc_html_e( 'Run Now', 'when-last-login' ); ?></a></td>
	</tr>

	<tr>
		<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( 'wll_settings_nonce' ); ?>">
	    <th><input type="submit" name="wll_save_settings"  class="button-primary" value="<?php esc_attr_e('Save Settings', 'when-last-login'); ?>" /></th>
	    <td></td>
	</tr>
</table>
