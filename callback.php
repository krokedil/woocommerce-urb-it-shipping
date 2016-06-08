<?php
	define('WP_USE_THEMES', false);
	require('../../../wp-blog-header.php');
	
	if(!class_exists('WooCommerce_Urb_It')) exit;
	
	$urbit = WooCommerce_Urb_It::instance();
	
	$credentials = get_option(WooCommerce_Urb_It::OPTION_CREDENTIALS);
	
	if(empty($_GET['order_reference_id']) || empty($_GET['consumer_secret_hash']) || $_GET['consumer_secret_hash'] !== md5($urbit->setting('shared_secret'))) {
		status_header(403);
		exit;
	}
	
	global $wpdb;
	
	$order_id = $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key = "_urb_it_order_id" AND meta_value = %s', $_GET['order_reference_id']));
	
	if(!$order_id) {
		status_header(404);
		exit;
	}
	
	$order = wc_get_order($order_id);
	
	if(!$order) {
		status_header(404);
		exit;
	}
	
	$status = strtolower($_GET['order_status']);
	$statuses = array(
		'delivered' => 'completed',
		'pickedup' => 'picked-up'
	);
	
	if(isset($statuses[$status])) {
		$order->update_status($statuses[$status]);
	}
	
	status_header(200);
	exit;
?>