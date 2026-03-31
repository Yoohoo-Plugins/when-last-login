<?php

// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

$users_id = get_users( array(
  'fields' => 'ID'
) );

foreach( $users_id as $user_id ){
  delete_user_meta( $user_id, 'when_last_login' );
  delete_user_meta( $user_id, 'when_last_login_count' );
  delete_user_meta( $user_id, 'wll_user_ip_address' );
  delete_user_meta( $user_id, 'wll_consent_to_track' );
  delete_user_meta( $user_id, 'wll_consent_to_track_date' );
}

// Delete custom database tables.
$summary_table = $wpdb->prefix . 'when_last_login';
$records_table = $wpdb->prefix . 'wll_login_records';
$wpdb->query( "DROP TABLE IF EXISTS `$summary_table`" );
$wpdb->query( "DROP TABLE IF EXISTS `$records_table`" );

// Delete legacy table if it exists.
$delete_table = $wpdb->prefix . 'wll_login_attempts' ;
$sql = "DROP TABLE IF EXISTS `$delete_table`";
$wpdb->query( $sql );

$sqlQuery = "DELETE FROM $wpdb->options WHERE option_name LIKE 'wll%'";
$wpdb->query($sqlQuery);
