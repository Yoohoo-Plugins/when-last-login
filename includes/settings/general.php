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

	<?php if( isset( $settings['show_all_login_records'] ) && intval( $settings['show_all_login_records'] ) == 1 ){ $checked = 1; } else { $checked = 0; } ?>
	<tr>
		<th><?php esc_html_e('Enable "All Login Records"', 'when-last-login'); ?></th>
		<td><input type='checkbox' value='1' name='wll_all_login_records' <?php checked( 1, $checked ); ?>/>			<small><?php 
			echo esc_html( 'Please enable this option if using the', 'when-last-login' ) . " <a href='https://yoohooplugins.com/plugins/when-last-login-user-statistics/' target='_blank'><strong>" . esc_html( 'When Last Login - User Statistics Add On', 'when-last-login' ) . "</strong></a>";
		?></small></td>
</td>
	</tr>

	<tr>
		<th><label for="wll_widget_user_count"><?php esc_html_e( 'Dashboard Widget Users', 'when-last-login' ); ?></label></th>
		<td>
			<?php $widget_count_val = isset( $settings['widget_user_count'] ) ? intval( $settings['widget_user_count'] ) : 5; ?>
			<input type="number" min="3" max="10" step="1" name="wll_widget_user_count" id="wll_widget_user_count" value="<?php echo esc_attr( $widget_count_val ); ?>" class="small-text" />
			<p class="description"><?php esc_html_e( 'Number of users to show in the "User Login Activity" dashboard widget (Min: 3, Max: 10).', 'when-last-login' ); ?></p>
		</td>
	</tr>

	<tr>
		<th><h2><?php esc_html_e( 'Tools', 'when-last-login' ); ?></h2></th>
		<td></td>
	</tr>
	<!-- loaded from general.php -->
	<?php 
		$old_records_message = esc_html__( 'Are you sure you want to remove all records older than 3 months?', 'when-last-login' );
		$all_records_message = esc_html__( 'Are you sure you want to remove all login records?', 'when-last-login' );
		$all_ip_message = esc_html__( 'Are you sure you want to remove all IP addresses?', 'when-last-login' );


		$remove_records_nonce = wp_create_nonce( 'wll_remove_records_nonce' );
		$remove_all_records_nonce = wp_create_nonce( 'wll_remove_all_records_nonce' );
		$remove_ip_nonce = wp_create_nonce( 'wll_remove_ip_nonce' );
	?>

		<script>
			function wll_remove_old_records(){
				if( window.confirm('<?php echo $old_records_message; ?>')) {
					window.location.href = "<?php echo add_query_arg( array( 'remove_wll_records' => '1', 'wll_remove_records_nonce' => $remove_records_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ); ?>";
				}
			}

			function wll_remove_all_records(){
				if( window.confirm('<?php echo $all_records_message; ?>')) {
					window.location.href = "<?php echo add_query_arg( array( 'remove_all_wll_records' => '1', 'wll_remove_all_records_nonce' => $remove_all_records_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ); ?>";
				}
			}

			function wll_remove_all_ips(){
				if( window.confirm('<?php echo $all_ip_message; ?>')) {
					window.location.href = "<?php echo add_query_arg( array( 'remove_wll_ip_addresses' => '1', 'wll_remove_ip_nonce' => $remove_ip_nonce ), admin_url( 'admin.php?page=when-last-login-settings' ) ); ?>";
				}
			}
	</script>

	<tr>
		<th><?php esc_html_e( 'Clear old logs', 'when-last-login' ); ?></th>
		<td><a href="javascript:void(0);" onclick="wll_remove_old_records(); return false;" class="button-primary"><?php esc_html_e( 'Run Now', 'when-last-login' ); ?></a></td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Clear all logs', 'when-last-login' ); ?></th>
		<td><a href="javascript:void(0);" onclick="wll_remove_all_records(); return false;" class="button-primary"><?php esc_html_e( 'Run Now', 'when-last-login' ); ?></a></td>
	</tr>

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
