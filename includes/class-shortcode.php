<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Shortcode extends WooCommerce_Urb_It {
		public static function init() {
			add_shortcode('urb_it_postcode_validator', array(__CLASS__, 'postcode_validator'));
		}
		
		
		public static function postcode_validator($atts = array(), $content = null) {
			global $woocommerce;
			
			ob_start();
			
			$postcode = $woocommerce->customer->get_shipping_postcode();
			if(!$postcode) $postcode = $woocommerce->customer->get_postcode();
			
			if($postcode) $last_status = $woocommerce->session->get('urb_it_postcode_result');
			
			include(self::$path_templates . 'postcode-validator/form.php');
			
			add_action('wp_footer', array(__CLASS__, 'postcode_validator_assets'));
			
			return ob_get_clean();
		}
	}
	
	WooCommerce_Urb_It_Shortcode::init();
?>