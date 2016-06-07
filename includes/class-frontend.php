<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Frontend extends WooCommerce_Urb_It {
		#public $checkout;
		#public $postcode_validator;
		
		
		public function __construct() {
			parent::__construct();
			
			self::$_modules['checkout'] = include($this->path . 'includes/class-frontend-checkout.php');
			self::$_modules['postcode_validator'] = include($this->path . 'includes/class-frontend-postcode-validator.php');
			
			#$this->checkout = include($this->path . 'includes/class-frontend-checkout.php');
			#$this->postcode_validator = include($this->path . 'includes/class-frontend-postcode-validator.php');
			
			// Shipping calculator
			add_action('woocommerce_after_shipping_calculator', array($this, 'shipping_calculator'));
			add_action('woocommerce_after_cart', array($this->checkout, 'add_assets'));
			
			// Notices
			add_action('woocommerce_add_to_cart', array($this, 'notice_added_product'), 10, 6);
			add_action('woocommerce_single_product_summary', array($this, 'notice_product_page'), 35);
		}
		
		
		// User notice: Product page
		public function notice_product_page() {
			if($this->setting('notice_product_page') !== 'yes') return;
			
			global $product;
			
			if($this->validate->product_volume($product) && $this->validate->product_weight($product)) return;
			
			?><p style="color: #ff3d4b;"><?php _e('This product can\'t be delivered by urb-it.', self::LANG); ?></p><?php
		}
		
		
		// User notice: Added product
		public function notice_added_product($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
			if($this->setting('notice_added_product') !== 'yes') return;
			
			WC()->cart->calculate_totals();
			
			if(!$this->validate->cart_weight()) {
				wc_add_notice(sprintf(__('Your cart\'s total weight exceeds %d kilos and can\'t be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT), 'notice');
			}
			elseif(!$this->validate->cart_volume()) {
				wc_add_notice(sprintf(__('Your cart\'s total volume exceeds %d liters and can\'t be delivered by urb-it.', self::LANG), self::ORDER_MAX_VOLUME / 1000), 'notice');
			}
			elseif(!$this->validate->cart_bulkiness()) {
				wc_add_notice(__('Your cart contains a bulky product and can\'t be delivered by urb-it.', self::LANG), 'notice');
			}
		}
		
		
		// Shipping calculator
		public function shipping_calculator() {
			$shipping_method = WC()->session->get('chosen_shipping_methods', array(get_option('woocommerce_default_shipping_method')));
			
			if(!in_array('urb_it_specific_time', $shipping_method) && !in_array('urb_it_one_hour', $shipping_method)) return;
			
			if(!empty($_POST['urb_it_date']) && !empty($_POST['urb_it_time'])) {
				WC()->session->set('urb_it_delivery_time', esc_attr($_POST['urb_it_date']) . ' ' . esc_attr($_POST['urb_it_time']));
			}
			
			if(!empty($_POST['urb_it_message'])) {
				WC()->session->set('urb_it_message', esc_attr($_POST['urb_it_message']));
			}
			
			?><form class="woocommerce-shipping-calculator" action="<?php echo esc_url( WC()->cart->get_cart_url() ); ?>" method="post"><?php
			
			$this->checkout->fields(true);
			
			?><p><input class="button" type="submit" name="calc_shipping" value="<?php _e('Save', self::LANG); ?>" /></p><?php
			?></form><?php
		}
	}
	
	return new WooCommerce_Urb_It_Frontend;