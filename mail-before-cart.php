<?php

/**
 * Plugin Name: Mail Before Cart
 * Description: Requires customers to enter their email before adding a product to the cart and stores it for abandoned cart recovery.
 * Version: 1.0
 * Author: Mohamed Alamin
 * Author URI: https://crafely.com
 * License: GPL2
 * Text Domain: mail-before-cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mbcart_update_db() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'mbcart_tracking';

	$reminder_column = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reminder_sent'",
			DB_NAME,
			$table_name
		)
	);

	if ( empty( $reminder_column ) ) {
		$wpdb->query( "ALTER TABLE $table_name ADD reminder_sent TINYINT(1) DEFAULT 0" );
	}

	$status_column = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
			DB_NAME,
			$table_name
		)
	);

	if ( empty( $status_column ) ) {
		$wpdb->query( "ALTER TABLE $table_name ADD status varchar(50) DEFAULT 'pending'" );
		$wpdb->query( "UPDATE $table_name SET status = 'pending' WHERE status IS NULL" );
	}

	$last_reminder_column = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'last_reminder_sent'",
			DB_NAME,
			$table_name
		)
	);

	if ( empty( $last_reminder_column ) ) {
		$wpdb->query( "ALTER TABLE $table_name ADD last_reminder_sent datetime DEFAULT NULL" );
	}
}
register_activation_hook( __FILE__, 'mbcart_update_db' );

function wc_clear_abandoned_cart_emails() {
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->__unset( 'customer_email' );
	}
}
add_action( 'wp_scheduled_delete', 'wc_clear_abandoned_cart_emails' );

if ( ! wp_next_scheduled( 'wp_scheduled_delete' ) ) {
	wp_schedule_event( time(), 'daily', 'wp_scheduled_delete' );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-email-reminder.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mbcart-plugin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mbcart-admin.php';

function wc_track_order_status( $order_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'mbcart_tracking';
	$order      = wc_get_order( $order_id );
	$email      = $order->get_billing_email();

	$wpdb->update(
		$table_name,
		array( 'status' => 'purchased' ),
		array( 'email' => $email )
	);
}
add_action( 'woocommerce_order_status_completed', 'wc_track_order_status' );
add_action( 'woocommerce_order_status_processing', 'wc_track_order_status' );

function mbcart_deactivate() {
	wp_clear_scheduled_hook( 'mbcart_send_reminders' );
}
register_deactivation_hook( __FILE__, 'mbcart_deactivate' );
