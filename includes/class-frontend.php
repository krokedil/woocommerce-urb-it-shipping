<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Frontend extends WooCommerce_Urb_It {
		public $checkout;
		
		public function __construct() {
			parent::__construct();
			
			$this->checkout = include($this->path . 'includes/class-frontend-checkout.php');
		}
	}
	
	return new WooCommerce_Urb_It_Frontend;