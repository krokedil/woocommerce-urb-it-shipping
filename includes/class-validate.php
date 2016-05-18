<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Validate extends WooCommerce_Urb_It {
		public function __construct() {
			
		}
		
		
		// Validate: Product weight
		public function validate_product_weight($product) {
			if(wc_get_weight($product->get_weight(), 'kg') > self::ORDER_MAX_WEIGHT) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_product_weight', $valid, $product);
		}
		
		
		// Validate: Product volume
		public function validate_product_volume($product) {
			if(wc_get_dimension(intval($product->length), 'cm') * wc_get_dimension(intval($product->length), 'cm') * wc_get_dimension(intval($product->length), 'cm') > self::ORDER_MAX_VOLUME) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_product_volume', $valid, $product);
		}
		
		
		// Validate: Cart weight
		public function validate_cart_weight() {
			if(wc_get_weight(WC()->cart->cart_contents_weight, 'kg') > self::ORDER_MAX_WEIGHT) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_cart_weight', $valid);
		}
		
		
		// Validate: Cart volume
		public function validate_cart_volume() {
			$total_volume = 0;
			
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				$total_volume += wc_get_dimension(intval($_product->length), 'cm') * wc_get_dimension(intval($_product->length), 'cm') * wc_get_dimension(intval($_product->length), 'cm');
			}
			
			if($total_volume > self::ORDER_MAX_VOLUME) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_cart_volume', $valid);
		}
		
		
		// Validate: Cart bulkiness
		public function validate_cart_bulkiness() {
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				if($_product->urb_it_bulky) return false;
			}
			
			return true;
		}
		
		
		// DEPRECATED
		public function validate_all_opening_hours($delivery_time) {
			_deprecated_function('validate_all_opening_hours', '2.0.0', 'validate_opening_hours');
			return self::validate_opening_hours($delivery_time);
		}
		
		
		// Validate: Default opening hours
		public function validate_opening_hours($delivery_time) {
			$days = self::get_opening_hours();
				
			if(!$days) return false;
			
			foreach($days as $day) {
				if($delivery_time >= $day->open && $delivery_time <= $day->close) return true;
			}
			
			return false;
		}
		
		
		// DEPRECATED
		public function do_urbit_validation($delivery_time, $package, $delivery_type = 'OneHour') {
			_deprecated_function('do_urbit_validation', '2.0.0', 'validate_against_urbit');
			
			if(empty($package['destination']['postcode'])) return false;
			
			if(self::validate_against_urbit($delivery_time, $package['destination']['postcode'], $delivery_type) !== true) return false;
			
			return true;
		}
		
		
		// Validate: Urbit
		public function validate_against_urbit($delivery_time, $postcode = '', $delivery_type = 'OneHour') {
			if(empty($postcode)) return false;
			
			$postcode = str_replace(' ', '', $postcode);
			
			$credentials = get_option(self::OPTION_CREDENTIALS, array());
			
			if(!$credentials) return false;
			
			require_once(self::$path_includes . 'class-urbit.php');
			
			$urbit = new Urbit_Client($credentials);
			
			$urbit->set('delivery_type', $delivery_type);
			$urbit->set('postal_code', $postcode);
			$urbit->set('delivery_expected_at', $delivery_time);
			
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				// Abort if a bulky product is found
				if($_product->urb_it_bulky) {
					self::log('Product #' . $_product->id . ' is bulky - aborting.', true);
					wc_add_notice(__('Your cart contains a bulky product and can\'t be delivered by urb-it.', self::LANG), 'error');
					return false;
				}
				
				// The product cannot be out of stock
				if($_product->managing_stock() && $_product->get_stock_quantity() !== '' && $_product->get_stock_quantity() < $item['quantity']) {
					self::log('Product #' . $_product->id . ' is out of stock - aborting.', true);
					wc_add_notice(__('Your cart contains a product that\'s out of stock and can\'t be delivered by urb-it.', self::LANG), 'error');
					return false;
				}
				
				$sku = $_product->sku;
				
				$urbit->add_article(array(
					'identifier' => ($sku ? $sku : ('#' . $_product->id)),
					'quantity' => $item['quantity'],
					'description' => $_product->get_title()
				));
			}
			
			do_action('woocommerce_urb_it_before_validate_order', $urbit);
			
			$status = $urbit->validate();
			
			self::log($status);
			
			if($status !== 200) {
				if(empty($urbit->result)) return false;
				
				// This is more serious errors - log them
				if($status !== 500 || empty($urbit->result) || $urbit->result->code !== 'RET-002') {
					if(!empty($urbit->result)) {
						self::log('API error during validation: ' . (!empty($urbit->result->code) ? $urbit->result->code : ('HTTP ' . $status)) . ', ' . (!empty($urbit->result->developer_message) ? $urbit->result->developer_message : $urbit->result->message) . ' | Data: ' . $delivery_type . ', ' . $postcode . ', ' . $delivery_time->format('Y-m-d H:i') . '.');
					}
					else {
						self::log('API error during validation: ' . ('HTTP ' . $status) . ' | Data: ' . $delivery_type . ', ' . $postcode . ', ' . $delivery_time->format('Y-m-d H:i') . '.');
					}
				}
				
				return $urbit->result;
			}
			
			return true;
		}
		
		
		public function validate_postcode($postcode, $remember_postcode = true) {
			if(empty($postcode)) return false;
			
			$postcode = str_replace(' ', '', $postcode);
			
			$credentials = get_option(self::OPTION_CREDENTIALS, array());
			
			if(!$credentials) return false;
			
			require_once(self::$path_includes . 'class-urbit.php');
			
			$urbit = new Urbit_Client($credentials);
			
			$urbit->set('postal_code', $postcode);
			$result = $urbit->post('postalcode/validate');
			
			return $result !== false;
		}
	}
	
	return new WooCommerce_Urb_It_Validate;