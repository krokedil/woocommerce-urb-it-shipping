<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Frontend extends WooCommerce_Urb_It {
		public $checkout;
		public $postcode_validator;
		
		
		public function __construct() {
			parent::__construct();
			
			$this->checkout = include($this->path . 'includes/class-frontend-checkout.php');
			$this->postcode_validator = include($this->path . 'includes/class-frontend-postcode-validator.php');
		}
	}
	
	return new WooCommerce_Urb_It_Frontend;