<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Code to support GDPR compliances, WordPress version 4.9.6+
 * @since 1.1
 */


/**
 * Return the default suggested privacy policy content.
 *
 * @return string The default policy content.
 */
function wll_get_default_privacy_content() {
	$content = '<h2>' . esc_html__( 'What personal data we collect and why we collect it', 'when-last-login' ) . '</h2>';

	$content .= '<p>' . esc_html__( 'An IP address will be collected and anonymized before storing it to the database. Additional data such as login time and number of logins will be stored for analytical and security reasons.', 'when-last-login' ) . '</p>';

	$content .= '<h2>' . esc_html__( 'How long we retain your data', 'when-last-login' ) . '</h2>';

	$content .= '<p>' . esc_html__( 'Subscriber information is retained in the local database indefinitely for analytic purposes and for future export. Data is retained until requested or user has been deleted.', 'when-last-login') . '</p>';

	$content .= '<h2>' . esc_html__( 'Where we send your data', 'when-last-login' ) . '</h2>';

	$content .= '<p>' . esc_html__( 'When Last Login does not send any user data outside of your site by default.', 'when-last-login') . '</p>';

	$content .= '<p>' . esc_html__( 'If you have any Add Ons installed to send login data to a 3rd part service such as Zapier, user info may be passed to these external services. These services may be located abroad.', 'when-last-login') . '</p>';

	$content = apply_filters( 'wll_default_privacy_text', $content );

	return $content;
}

/**
 * Add the suggested privacy policy text to the policy postbox.
 */
function wll_add_suggested_privacy_content() {

	if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
		$content = wll_get_default_privacy_content();
		wp_add_privacy_policy_content( esc_html__( 'When Last Login', 'when-last-login' ), $content );
	}
	
}
add_action( 'admin_init', 'wll_add_suggested_privacy_content', 20 );


/**
 * Functions for data export.
 */
function wll_register_exporters( $exporters ) {
	$exporters[] = array(
		'exporter_friendly_name' => esc_html__( 'When Last Login', 'when-last-login' ),
		'callback'               => 'wll_user_data_exporter',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'wll_register_exporters' );

function wll_user_data_exporter( $email_address, $page = 1 ) {
	$export_items = array();
	$user = get_user_by( 'email', $email_address );
	if ( $user && $user->ID ) {
		$item_id = "when-last-login-{$user->ID}";
		$group_id = 'when-last-login';
		$group_label = esc_html__( 'Plugin: When Last Login', 'when-last-login' );

		// Build an array for data to export.
		$data = array();

		// Add the login time.
		$login_time = get_user_meta( $user->ID, 'when_last_login', true );
		if ( $login_time ) {
			$data[] = array(
				'name'  => esc_html__( 'Last Login Time', 'when-last-login' ),
				'value' => $login_time,
			);
		}

		// Add the login count.
		$login_count = get_user_meta( $user->ID, 'when_last_login_count', true );
		if ( $login_count ) {
			$data[] = array(
				'name'  => esc_html__( 'Login Count', 'when-last-login' ),
				'value' => $login_count,
			);
		}

		// Add the login count.
		$ip_address = get_user_meta( $user->ID, 'wll_user_ip_address', true );
		if ( $ip_address ) {
			$data[] = array(
				'name'  => esc_html__( 'Last Login IP Address', 'when-last-login' ),
				'value' => $ip_address,
			);
		}

		$data = apply_filters( 'wll_data_privacy_export', $data );

		// Add this group of items to the exporters data array.
		$export_items[] = array(
			'group_id'    => $group_id,
			'group_label' => $group_label,
			'item_id'     => $item_id,
			'data'        => $data,
		);
	}

	return array(
		'data' => $export_items,
		'done' => true,
	);
}


/**
 * Functions to erase data
 */

function plugin_register_erasers( $erasers = array() ) {
	$erasers[] = array(
		'eraser_friendly_name' => esc_html__( 'When Last Login', 'when-last-login' ),
		'callback'               => 'plugin_user_data_eraser',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'plugin_register_erasers' );

function plugin_user_data_eraser( $email_address, $page = 1 ) {
	if ( empty( $email_address ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
	$user = get_user_by( 'email', $email_address );
	$messages = array();
	$items_removed  = false;
	$items_retained = false;
	if ( $user && $user->ID ) {

		$deleted_when_last_login = delete_user_meta( $user->ID, 'when_last_login' );
		if ( $deleted_when_last_login ) {
			$items_removed = true;
		} else {
			$messages[] = __( 'Your last login timestamp was unable to be removed at this time.', 'when-last-login' );
			$items_retained = true;
		}
		
		$deleted_when_last_login_count = delete_user_meta( $user->ID, 'when_last_login_count' );
		if ( $deleted_when_last_login_count ) {
			$items_removed = true;
		} else {
			$messages[] = esc_html__( 'Your login count was unable to be removed at this time.', 'when-last-login' );
			$items_retained = true;
		}

		$deleted_ip_address = delete_user_meta( $user->ID, 'wll_user_ip_address' );
		if ( $deleted_ip_address ) {
			$items_removed = true;
		} else {
			$messages[] = esc_html__( 'Your IP address was unable to be removed at this time.', 'when-last-login' );
			$items_retained = true;
		}
	}
	// Returns an array of exported items for this pass, but also a boolean whether this exporter is finished.
	//If not it will be called again with $page increased by 1.
	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => true,
	);
}