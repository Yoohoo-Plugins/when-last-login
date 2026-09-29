<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'general' => array(
		'title' => __( 'General', 'when-last-login' ),
		'icon' => ''
	),
	'add-ons' => array(
		'title' => __( 'Add-ons', 'when-last-login' ),
		'icon' => ''
	)
);

$tabs = apply_filters( 'wll_settings_page_tabs', $tabs );

?>

<div id="wll-setting-header">
	<img src="<?php echo WLL_PLUGIN . '/includes/images/whenlastlogin.png'; ?>" width="300px" height="auto" style="margin-top:2%;"/><span style="position:relative;top:-15px;"><?php echo 'v' . WLL_VER; ?></span>
</div>
<div class='wrap'>

	<h2 class="nav-tab-wrapper"><?php

	foreach( $tabs as $key => $val ){
		
		$active = '';

		if( isset( $_GET['tab'] ) && $_GET['tab'] == $key ){
			$active = 'nav-tab-active';
		} else {
			if( $key == 'general' ){
				$active = 'nav-tab-active';
			}
		}

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
			'add-ons' => 'settings/add-ons.php'
		);

		$content = apply_filters( 'wll_settings_page_content', $content );

		if( isset( $_GET['tab'] ) ){
			$current_tab = $_GET['tab'];
		} else {
			$current_tab = 'general';
		}

		foreach( $content as $key => $val ){

			if( $key == $current_tab ){
				include $val;
			}

		}


	?>	

	</table></form>
	<?php } ?>
</div>
