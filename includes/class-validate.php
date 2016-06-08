<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Validate extends WooCommerce_Urb_It {
		public function __construct() {
			#$this->plugin = WooCommerce_Urb_It::instance();
		}
		
		
		// Validate: Product weight
		public function product_weight($product) {
			if(wc_get_weight($product->get_weight(), 'kg') > self::ORDER_MAX_WEIGHT) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			$valid = apply_filters('woocommerce_urb_it_valid_product_weight', $valid, $product);
			
			if(!$valid) $this->log('Product #' . $product->id . ' has invalid weight.');
			
			return $valid;
		}
		
		
		// Validate: Product volume
		public function product_volume($product) {
			if(wc_get_dimension(intval($product->length), 'cm') * wc_get_dimension(intval($product->length), 'cm') * wc_get_dimension(intval($product->length), 'cm') > self::ORDER_MAX_VOLUME) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			$valid = apply_filters('woocommerce_urb_it_valid_product_volume', $valid, $product);
			
			if(!$valid) $this->log('Product #' . $product->id . ' has invalid volume.');
			
			return $valid;
		}
		
		
		// Validate: Cart weight
		public function cart_weight() {
			if(wc_get_weight(WC()->cart->cart_contents_weight, 'kg') > self::ORDER_MAX_WEIGHT) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			$valid = apply_filters('woocommerce_urb_it_valid_cart_weight', $valid);
			
			if(!$valid) $this->log('Cart has invalid weight.');
			
			return $valid;
		}
		
		
		// Validate: Cart volume
		public function cart_volume() {
			$total_volume = 0;
			
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				$total_volume += wc_get_dimension(intval($_product->length), 'cm') * wc_get_dimension(intval($_product->length), 'cm') * wc_get_dimension(intval($_product->length), 'cm');
			}
			
			if($total_volume > self::ORDER_MAX_VOLUME) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			$valid = apply_filters('woocommerce_urb_it_valid_cart_volume', $valid);
			
			if(!$valid) $this->log('Cart has invalid volume.');
			
			return $valid;
		}
		
		
		// Validate: Cart bulkiness
		public function cart_bulkiness() {
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				if($_product->urb_it_bulky) {
					$this->log('Cart contains a bulky product.');
					return false;
				}
			}
			
			return true;
		}
		
		
		// Validate: Opening hours
		public function opening_hours($delivery_time) {
			$days = $this->opening_hours->get();
				
			if(!$days) return false;
			
			foreach($days as $day) {
				if($delivery_time >= $day->open && $delivery_time <= $day->close) return true;
			}
			
			$this->log('Invalid delivery time - the store is closed. Input: ' . $delivery_time->format('Y-m-d H:i:s'));
			return false;
		}
		
		
		// Validate: Urbit
		public function order($delivery_time, $postcode = '', $delivery_type = 'OneHour') {
			if(empty($postcode)) return false;
			
			$postcode = str_replace(' ', '', $postcode);
			
			$order_data = array(
				'delivery_type' => $delivery_type,
				'postal_code' => $postcode,
				'delivery_expected_at' => $delivery_time->format(self::DATE_FORMAT),
				'pickup_location' => array('id' => $this->setting('pickup_location_id')),
				'articles' => array()
			);
			
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				// Abort if a bulky product is found
				if($_product->urb_it_bulky) {
					$this->log('Product #' . $_product->id . ' is bulky - aborting.');
					throw new Exception(__('Your cart contains a bulky product and can\'t be delivered by urb-it.', self::LANG));
					return false;
				}
				
				// The product cannot be out of stock
				if($_product->managing_stock() && $_product->get_stock_quantity() !== '' && $_product->get_stock_quantity() < $item['quantity']) {
					$this->log('Product #' . $_product->id . ' is out of stock - aborting.');
					throw new Exception(__('Your cart contains a product that\'s out of stock and can\'t be delivered by urb-it.', self::LANG));
					return false;
				}
				
				$sku = $_product->sku;
				
				$order_data['articles'][] = array(
					'identifier' => ($sku ? $sku : ('#' . $_product->id)),
					'quantity' => $item['quantity'],
					'description' => $_product->get_title()
				);
			}
			
			try {
				do_action('woocommerce_urb_it_before_validate_order', $this->urbit);
				
				$order_data = apply_filters('woocommerce_urb_it_validate_order_data', $order_data);
				
				$this->log('Validating order data:', $this->urbit->storeKey, $order_data);
				
				$urbit_result = $this->urbit->ValidateDelivery($order_data);
				
				$this->log('Validation result:', $this->urbit->httpStatus, $this->urbit->httpBody);
				
				return true;
			}
			catch(Exception $e) {
				$this->log('Error while validating order: ' . $e->getMessage());
				
				if(isset($this->urbit->httpBody->code)) {
					switch($this->urbit->httpBody->code) {
						case 'RET-002':
							throw new Exception(__('Urb-it can unfortunately not deliver to this address.', self::LANG));
							break;
						case 'RET-004':
						case 'RET-005':
							throw new Exception(__('We can unfortunately not deliver at this time, please choose another.', self::LANG));
							break;
						default:
							throw new Exception($e->getMessage());
					}
				}
				
				return false;
			}
		}
		
		
		public function postcode($postcode) {
			try {
				$valid = $this->urbit->ValidatePostalCode($postcode);
				
				if(!$valid) $this->log('Invalid postcode: ' . $postcode);
				
				return $valid;
			}
			catch(Exception $e) {
				$this->log('Error while validating postcode: ' . $e->getMessage());
				
				return false;
			}
		}
	}
	
	return new WooCommerce_Urb_It_Validate;