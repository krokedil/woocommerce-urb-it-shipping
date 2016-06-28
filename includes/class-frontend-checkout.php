<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Frontend_Checkout extends WooCommerce_Urb_It_Frontend {
		private $added_assets = false;
		
		
		public function __construct() {
			add_action('woocommerce_review_order_after_shipping', array($this, 'fields'));
			add_action('woocommerce_after_checkout_validation', array($this, 'validate_fields'));
			add_action('woocommerce_after_checkout_form', array($this, 'add_assets'));
			
			// Notices
			add_action('woocommerce_before_checkout_form', array($this, 'notice_checkout'));
			add_action('woocommerce_before_cart', array($this, 'notice_checkout'));
			add_action('woocommerce_review_order_after_shipping', array($this, 'notice_checkout_shipping'));
		}
		
		
		// User notice: Checkout (and cart)
		public function notice_checkout() {
			if($this->setting('notice_checkout') !== 'yes') return;
			
			if(!$this->validate->cart_weight()) {
				wc_add_notice(sprintf(__('As the total weight of your cart is over %d kilos, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT), 'notice');
			}
			elseif(!$this->validate->cart_volume()) {
				wc_add_notice(sprintf(__('As the total volume of your cart is over %d liters, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_VOLUME / 1000), 'notice');
			}
			elseif(!$this->validate->cart_bulkiness()) {
				wc_add_notice(__('As your cart contains a bulky product, it can unfortunately not be delivered by urb-it.', self::LANG), 'notice');
			}
		}
		
		
		// User notice: Wrong postcode
		public function notice_checkout_shipping() {
			if(empty($_POST['s_postcode']) || $this->setting('notice_checkout') !== 'yes') return;
			if($this->validate->postcode($_POST['s_postcode'])) return;
			?>
				<tr class="urb-it-shipping">
					<th>&nbsp;</th>
					<td style="color: #d00;"><?php _e('As the delivery location is outside urb-it\'s availability zone, urb-it is disabled for this order.', self::LANG); ?></td>
				</tr>
			<?php
		}
		
		
		// Checkout: Fields
		public function fields($is_cart = false) {
			$shipping_method = WC()->session->get('chosen_shipping_methods', array(get_option('woocommerce_default_shipping_method')));
			
			$this->log('Chosen shipping method:', $shipping_method);
			
			if(empty($shipping_method)) return;
			
			$message = WC()->session->get('urb_it_message');
			
			if(in_array('urb_it_specific_time', $shipping_method)) {
				$this->template('checkout/field-delivery-time', array(
					'is_cart' => $is_cart,
					'selected_delivery_time' => $this->date(WC()->session->get('urb_it_delivery_time', $this->specific_time_offset())),
					'now' => $this->date('now'),
					'days' => $this->opening_hours->get()
				));
				
				$this->template('checkout/field-message', compact('is_cart', 'message'));
			}
			elseif(in_array('urb_it_one_hour', $shipping_method)) {
				$this->template('checkout/field-message', compact('is_cart', 'message'));
			}
		}
		
		
		// Validate: Checkout fields
		public function validate_fields($posted) {
			if(!isset($posted['shipping_method']) || (!in_array('urb_it_one_hour', $posted['shipping_method']) && !in_array('urb_it_specific_time', $posted['shipping_method']))) return;
			
			$phone = $this->sanitize_phone($posted['billing_phone']);
			
			if(!$phone) {
				throw new Exception(__('Please enter a valid cellphone number.', self::LANG));
			}
			
			#$now = $this->date('+1 hour');
			#$now->setTime($now->format('G'), $now->format('i'), 0);
			
			if(in_array('urb_it_specific_time', $posted['shipping_method'])) {
				$delivery_type = 'Specific';
				
				$valid_time = true;
				$date = trim($_POST['urb_it_date']);
				$time = trim($_POST['urb_it_time']);
				$date_limit = $this->date(self::SPECIFIC_TIME_RANGE);
				$date_limit->setTime(23, 59);
				
				if(!preg_match('/^\d{4}\-\d{2}-\d{2}$/', $date)) {
					$valid_time = false;
					throw new Exception(sprintf(__('Please enter a delivery date in the format YYYY-MM-DD, ex: %s.', self::LANG), date('Y-m-d')));
				}
				
				if(!preg_match('/^\d{2}\:\d{2}$/', $time)) {
					$valid_time = false;
					throw new Exception(sprintf(__('Please enter a delivery time in the format HH:MM, ex: %s.', self::LANG), date('H:i')));
				}
				
				if(!$valid_time) return;
				
				$delivery_time = $this->date($date . ' ' . $time);
				$min_time = $this->date($this->specific_time_offset(false));
				
				if($delivery_time < $min_time) {
					throw new Exception(sprintf(__('Please pick a time from %s and forward.', self::LANG), $min_time->format('H:i')));
					return;
				}
				if($delivery_time > $date_limit) {
					throw new Exception(sprintf(__('We can unfortunately not deliver this far in the future, please choose a date not later than %s.', self::LANG), date_i18n('j F', $date_limit->getTimestamp())));
					return;
				}
			}
			else {
				$delivery_type = 'OneHour';
				$delivery_time = $this->date($this->one_hour_offset());
			}
			
			$postcode = isset($posted['shipping_postcode']) ? $posted['shipping_postcode'] : $posted['billing_postcode'];
			
			// Check the weight of the order
			if(!$this->validate->cart_weight()) {
				throw new Exception(sprintf(__('As the total weight of your cart is over %d kilos, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT));
			}
			
			// Check the volume of the order
			if(!$this->validate->cart_volume()) {
				throw new Exception(sprintf(__('As the total volume of your cart is over %d liters, it can unfortunately not be delivered by urb-it.', self::LANG), self::ORDER_MAX_VOLUME / 1000));
			}
			
			if(apply_filters('woocommerce_urb_it_skip_validation', false)) {
				$this->log('Order validation skipped with filter ("woocommerce_urb_it_skip_validation") - aborting.');
				return;
			}
			
			$this->validate->order($delivery_time, $postcode, $delivery_type);
		}
		
		
		// Checkout: Assets
		public function add_assets() {
			if(!apply_filters('woocommerce_urb_it_add_checkout_assets', true) || $this->added_assets) return;
			?>
			<style>
				<?php include($this->path . 'assets/css/urb-it-checkout.css'); ?>
			</style>
			
			<script>
				if(!ajaxurl) var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
				<?php include($this->path . 'assets/js/urb-it-checkout.js'); ?>
			</script>
			<?php
			$this->added_assets = true;
		}
	}
	
	return new WooCommerce_Urb_It_Frontend_Checkout;