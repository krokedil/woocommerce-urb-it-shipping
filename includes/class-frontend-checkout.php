<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Frontend_Checkout extends WooCommerce_Urb_It_Frontend {
		public function __construct() {
			add_action('woocommerce_review_order_after_shipping', array($this, 'checkout_fields'));
			add_action('woocommerce_after_checkout_validation', array($this, 'validate_checkout_fields'));
			add_action('woocommerce_after_checkout_form', array($this, 'checkout_assets'));
			
			// Notices
			add_action('woocommerce_before_checkout_form', array($this, 'notice_checkout'));
			add_action('woocommerce_before_cart', array($this, 'notice_checkout'));
			add_action('woocommerce_review_order_after_shipping', array($this, 'notice_checkout_shipping'));
		}
		
		
		// User notice: Checkout (and cart)
		public function notice_checkout() {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['notice-checkout']) return;
			
			$is_too_heavy = !self::validate_cart_weight();
			$is_too_big = !self::validate_cart_volume();
			$has_bulky_product = !self::validate_cart_bulkiness();
			
			if($is_too_heavy || $is_too_big || $has_bulky_product) {
				?><div class="woocommerce-error"><?php
				
				if($is_too_heavy) {
					echo sprintf(__('As the total weight of your cart is over %d kilos, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT);
				}
				elseif($is_too_big) {
					echo sprintf(__('As the total volume of your cart is over %d liters, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_VOLUME / 1000);
				}
				elseif($has_bulky_product) {
					_e('As your cart contains a bulky product, it can unfortunately not be delivered by urb-it.', self::LANG);
				}
	
				?></div><?php
			}
		}
		
		
		// User notice: Wrong postcode
		public function notice_checkout_shipping() {
			if(empty($_POST['s_postcode'])) return;
			
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['notice-checkout']) return;
			
			if(self::validate_postcode($_POST['s_postcode'])) return;
			?>
				<tr class="urb-it-shipping">
					<th>&nbsp;</th>
					<td style="color: #d00;"><?php _e('As the delivery location is outside urb-it\'s availability zone, urb-it is disabled for this order.', self::LANG); ?></td>
				</tr>
			<?php
		}
		
		
		// Checkout: Fields
		public function checkout_fields($is_cart = false) {
			$shipping_method = WC()->session->get('chosen_shipping_methods', array(get_option('woocommerce_default_shipping_method')));
			
			if(empty($shipping_method)) return;
			
			$message = WC()->session->get('urb_it_message');
			
			if(in_array('urb_it_specific_time', $shipping_method)) {
				$selected_delivery_time = self::create_datetime(WC()->session->get('urb_it_delivery_time', '+1 hour'));
				$now = self::create_datetime('now');
				$onehour = self::create_datetime('+1 hour');
				$days = self::get_opening_hours();
				
				include(self::$path_templates . 'checkout/field-delivery-time.php');
				include(self::$path_templates . 'checkout/field-message.php');
			}
			elseif(in_array('urb_it_one_hour', $shipping_method)) {
				include(self::$path_templates . 'checkout/field-message.php');
			}
		}
		
		
		// Validate: Checkout fields
		public function validate_checkout_fields($posted) {
			if(!isset($posted['shipping_method']) || (!in_array('urb_it_one_hour', $posted['shipping_method']) && !in_array('urb_it_specific_time', $posted['shipping_method']))) return;
			
			$phone = self::sanitize_phone($posted['billing_phone']);
			
			if(!$phone) {
				wc_add_notice(__('Please enter a valid cellphone number.', self::LANG), 'error');
			}
			
			$now = self::create_datetime('+1 hour');
			$now->setTime($now->format('G'), $now->format('i'), 0);
			
			if(in_array('urb_it_specific_time', $posted['shipping_method'])) {
				$delivery_type = 'Specific';
				
				$valid_time = true;
				$date = trim($_POST['urb_it_date']);
				$time = trim($_POST['urb_it_time']);
				$date_limit = self::create_datetime(self::SPECIFIC_TIME_RANGE);
				$date_limit->setTime(23, 59);
				
				if(!preg_match('/^\d{4}\-\d{2}-\d{2}$/', $date)) {
					$valid_time = false;
					wc_add_notice(sprintf(__('Please enter a delivery date in the format YYYY-MM-DD, ex: %s.', self::LANG), date('Y-m-d')), 'error');
				}
				
				if(!preg_match('/^\d{2}\:\d{2}$/', $time)) {
					$valid_time = false;
					wc_add_notice(sprintf(__('Please enter a delivery time in the format HH:MM, ex: %s.', self::LANG), date('H:i')), 'error');
				}
				
				if(!$valid_time) return;
				
				$delivery_time = self::create_datetime($date . ' ' . $time);
				
				if($delivery_time < $now) {
					wc_add_notice(sprintf(__('Please pick a time from %s and forward.', self::LANG), $now->format('H:i')), 'error');
					return;
				}
				if($delivery_time > $date_limit) {
					wc_add_notice(sprintf(__('We can unfortunately not deliver this far in the future, please choose a date not later than %s.', self::LANG), date_i18n('j F', $date_limit->getTimestamp())), 'error');
					return;
				}
			}
			else {
				$delivery_type = 'OneHour';
				$delivery_time = clone $now;
			}
			
			$postcode = isset($posted['shipping_postcode']) ? $posted['shipping_postcode'] : $posted['billing_postcode'];
			
			// Check the weight of the order
			if(!self::validate_cart_weight()) {
				wc_add_notice(sprintf(__('As the total weight of your cart is over %d kilos, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT), 'error');
			}
			
			// Check the volume of the order
			if(!self::validate_cart_volume()) {
				wc_add_notice(sprintf(__('As the total volume of your cart is over %d liters, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_VOLUME / 1000), 'error');
			}
			
			if(apply_filters('woocommerce_urb_it_skip_validation', false)) return;
			
			$result = self::validate_against_urbit($delivery_time, $postcode, $delivery_type);
			
			if($result === true) return;
			
			switch($result->code) {
				case 'RET-002':
					wc_add_notice(__('Urb-it can unfortunately not deliver to this address.', self::LANG), 'error');
					break;
				case 'RET-004':
				case 'RET-005':
					wc_add_notice(__('We can unfortunately not deliver at this time, please choose another.', self::LANG), 'error');
					break;
				default:
					wc_add_notice($result->message, 'error');
			}
		}
		
		
		// Checkout: Assets
		public function checkout_assets() {
			if(!apply_filters('woocommerce_urb_it_add_checkout_assets', true) || self::$added_checkout_assets) return;
			?>
			<style>
				<?php include(self::$path_assets . 'css/urb-it-checkout.css'); ?>
			</style>
			
			<script>
				if(!ajaxurl) var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
				<?php include(self::$path_assets . 'js/urb-it-checkout.js'); ?>
			</script>
			<?php
			self::$added_checkout_assets = true;
		}
	}
	
	return new WooCommerce_Urb_It_Frontend_Checkout;