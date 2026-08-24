<?php
/**
 * Settings page template for When Last Login.
 *
 * @package When_Last_Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'general' => array(
		'title' => __( 'General', 'when-last-login' ),
		'icon' => ''
	),
	'add-ons' => array(
		'title' => __( 'Add Ons', 'when-last-login' ),
		'icon' => ''
	),
);

$tabs = apply_filters( 'wll_settings_page_tabs', $tabs );

//Add Ons should always render last, regardless of what filters add/reorder.
if ( isset( $tabs['add-ons'] ) ) {
	$wll_add_ons_tab = $tabs['add-ons'];
	unset( $tabs['add-ons'] );
	$tabs['add-ons'] = $wll_add_ons_tab;
}

$wll_migration_status = get_option( 'wll_migration_status', array() );
$wll_migration_active = ! empty( $wll_migration_status ) && isset( $wll_migration_status['status'] ) && $wll_migration_status['status'] !== 'complete';

?>

<?php if ( $wll_migration_active ) : ?>
<div id="wll-migration-notice" class="notice notice-info">
	<p><?php esc_html_e( 'When Last Login is migrating your login data in the background. This notice will disappear once migration is complete.', 'when-last-login' ); ?></p>
</div>
<script>
(function($) {
	var wllMigrationPoll = setInterval(function() {
		$.post(ajaxurl, {
			action: 'wll_check_migration_status'
		}, function(response) {
			if (response.success && response.data.complete) {
				$('#wll-migration-notice').fadeOut(400, function() { $(this).remove(); });
				clearInterval(wllMigrationPoll);
			}
		});
	}, 5000);
}(jQuery));
</script>
<?php endif; ?>

<?php
// Check for orphaned CPT records after migration is marked complete.
$wll_migration_status_check = get_option( 'wll_migration_status', array() );
$wll_migration_complete = ! empty( $wll_migration_status_check ) && isset( $wll_migration_status_check['status'] ) && $wll_migration_status_check['status'] === 'complete';
$wll_db_version_check = get_option( 'wll_db_version', '1.0.0' );

if ( $wll_migration_complete && version_compare( $wll_db_version_check, '1.3.0', '>=' ) ) {
	// Migration says it's done — check for orphaned CPT posts.
	$wll_orphaned_count = 0;
	$args = array(
		'post_type'      => 'wll_records',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
	);
	$wll_orphan_query = new WP_Query( $args );
	$wll_orphaned_count = $wll_orphan_query->found_posts;

	if ( $wll_orphaned_count > 0 ) :
?>
<div id="wll-orphaned-notice" class="notice notice-warning">
	<p>
		<strong><?php esc_html_e( 'When Last Login: Orphaned records detected', 'when-last-login' ); ?></strong><br>
		<?php
		printf(
			/* translators: %d: number of orphaned records */
			esc_html__( 'Migration is marked complete but %d CPT login records were found in the posts table. These were not migrated to the database tables and may need manual cleanup.', 'when-last-login' ),
			$wll_orphaned_count
		);
		?>
	</p>
	<p>
		<a href="<?php echo esc_url( add_query_arg( array( 'wll_cleanup_orphaned' => '1', 'wll_cleanup_nonce' => wp_create_nonce( 'wll_cleanup_orphaned' ) ), admin_url( 'admin.php?page=when-last-login-settings' ) ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Delete Orphaned Records', 'when-last-login' ); ?>
		</a>
	</p>
</div>
<?php
	endif;
}
?>

<div id="wll-setting-header">
	<img src="<?php echo esc_url( WLL_PLUGIN . '/includes/images/whenlastlogin.png' ); ?>" width="300px" height="auto" style="margin-top:2%;"/><span style="position:relative;top:-15px;"><?php echo esc_html( 'v' . WLL_VER ); ?></span>
</div>
<div class='wrap'>

	<?php $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general'; ?>

	<h2 class="nav-tab-wrapper"><?php

	foreach( $tabs as $key => $val ){

		$active = ( $current_tab == $key ) ? 'nav-tab-active' : '';

		echo '<a class="nav-tab ' . esc_attr( $active ) . '" href="?page=when-last-login-settings&tab=' . esc_attr( $key ) . '">' . esc_html( $val['title'] ) . '</a>';

	}

	?>
		
	</h2> 

	<?php
	if( isset( $_GET['tab'] ) && $_GET['tab'] == 'add-ons' ){
		include 'settings/add-ons.php';
	} else {
	?>
	<form method='POST'><table class="form-table">

	<?php

		$content = array(
			'general' => 'settings/general.php',
			'add-ons' => 'settings/add-ons.php',
		);

		$content = apply_filters( 'wll_settings_page_content', $content );

		foreach( $content as $key => $val ){

			if( $key == $current_tab ){
				include $val;
			}

		}


	?>	

	</table></form>
	<?php } ?>
</div>
