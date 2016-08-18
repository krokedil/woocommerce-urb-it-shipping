<?php
	define('WP_USE_THEMES', false);
	require('../../../wp-blog-header.php');
	
	if(!class_exists('WooCommerce_Urb_It')) exit;
	
	$urbit = WooCommerce_Urb_It::instance();
	
	$urbit->log('Callback:', $_GET);
	
	if(empty($_GET['order_reference_id']) || empty($_GET['consumer_secret_hash']) || apply_filters('woocommerce_urb_it_callback_invalid', (strtolower($_GET['consumer_secret_hash']) !== md5($urbit->setting('shared_secret'))), $_GET)) {
		$urbit->log('Invalid callback');
		status_header(403);
		exit;
	}
	
	global $wpdb;
	
	$order_id = $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key = "_order_number_formatted" AND meta_value = %s', $_GET['order_reference_id']));
	
	if(!$order_id && is_numeric($_GET['order_reference_id'])) {
		$order_id = (int)$_GET['order_reference_id'];
	}
	
	if(!$order_id) {
		$urbit->log('No order found: ', $order_id);
		status_header(404);
		exit;
	}
	
	$order = wc_get_order($order_id);
	
	if(!$order) {
		$urbit->log('Invalid order number');
		status_header(404);
		exit;
	}
	
	do_action('woocommerce_urb_it_callback', $_GET, $order);
	
	$status = strtolower($_GET['order_status']);
	$statuses = array(
		'delivered' => 'completed',
		'pickedup' => 'picked-up'
	);
	
	if(isset($statuses[$status])) {
		$order->update_status($statuses[$status]);
	}
	
	$urbit->log('Callback done for order ID: ', $order_id);
	
	status_header(200);
	exit;
?>