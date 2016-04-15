<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Coupon extends WooCommerce_Urb_It {
		public static function init() {
			// Coupon options
			add_action('woocommerce_coupon_options', array(__CLASS__, 'options_view'));
			add_action('woocommerce_coupon_options_save', array(__CLASS__, 'options_save'));
			
			// Populate coupon
			add_action('woocommerce_coupon_loaded', array(__CLASS__, 'populate'));
			
			// Set shipping cost
			add_filter('woocommerce_urb_it_shipping_cost', array(__CLASS__, 'set_shipping_cost'));
			
			// Validate coupon
			add_filter('woocommerce_coupon_is_valid_for_cart', array(__CLASS__, 'validate_coupon'), 10, 2);
			add_filter('woocommerce_coupon_is_valid_for_product', array(__CLASS__, 'validate_coupon_for_product'), 10, 4);
			
			// Coupon message
			add_filter('woocommerce_coupon_message', array(__CLASS__, 'message'), 10, 3);
		}
		
		
		public static function options_view() {
			woocommerce_wp_checkbox(array(
				'id' => 'urb_it_only',
				'label' => __('Only valid for urb-it', self::LANG),
				'description' => __('Check this box if the coupon only should be valid when urb-it is selected as shipping method.', self::LANG)
			));
			
			woocommerce_wp_checkbox(array(
				'id' => 'urb_it_free_shipping',
				'label' => __('Free shipping with urb-it', self::LANG),
				'description' => __('Check this box if the coupon grants free shipping with urb-it. This will remove the costs for the urb-it shipping methods when the coupon is applied.', self::LANG)
			));
		}
		
		
		public static function options_save($post_id) {
			$free_shipping = isset($_POST['urb_it_free_shipping']) ? 'yes' : 'no';
			$urb_it_only = isset($_POST['urb_it_only']) ? 'yes' : 'no';
			
			update_post_meta($post_id, 'urb_it_free_shipping', $free_shipping);
			update_post_meta($post_id, 'urb_it_only', $urb_it_only);
		}
		
		
		public static function populate($coupon) {
			if(!isset($coupon->id)) return;
			
			if(!isset($coupon->urb_it_free_shipping)) $coupon->urb_it_free_shipping = get_post_meta($coupon->id, 'urb_it_free_shipping', true);
			if(!isset($coupon->urb_it_only)) $coupon->urb_it_only = get_post_meta($coupon->id, 'urb_it_only', true);
		}
		
		
		public static function set_shipping_cost($cost) {
			if($coupons = WC()->cart->get_coupons()) {
				foreach($coupons as $code => $coupon) {
					if($coupon->is_valid() && $coupon->urb_it_free_shipping === 'yes') return 0;
				}
			}
			
			return $cost;
		}
		
		
		public static function validate_coupon($is_valid, $coupon) {
			if($coupon->urb_it_only !== 'yes') return $is_valid;
			
			$shipping_methods = WC()->session->get('chosen_shipping_methods', array(get_option('woocommerce_default_shipping_method')));
			
			foreach($shipping_methods as $shipping_method) {
				if(strncmp('urb_it_', $shipping_method, 7) === 0) return true;
			}
			
			return false;
		}
		
		
		public static function validate_coupon_for_product($is_valid, $product, $coupon, $values) {
			// This works exactly the same
			return self::validate_coupon($is_valid, $coupon);
		}
		
		
		public static function message($msg, $msg_code, $coupon) {
			if($coupon->urb_it_only !== 'yes' || $msg_code !== $coupon::WC_COUPON_SUCCESS) return $msg;
			
			return __('Awesome! Ensure you\'re selecting urb-it as shipping method during checkout, and the discount is yours.', self::LANG);
		}
	}
	
	WooCommerce_Urb_It_Coupon::init();
?>