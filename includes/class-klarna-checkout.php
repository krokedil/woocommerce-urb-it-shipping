<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Klarna_Checkout extends WooCommerce_Urb_It {
		private $last_updated_postcode = 0;
		
		
		public function __construct() {
			add_action('wp_enqueue_scripts', array($this, 'add_assets'));
			add_action('kco_widget_before_order_note', array($this, 'add_fields'));
			
			// Allways show shipping methods
			add_filter('kco_hide_singular_shipping_method', '__return_false');
			add_filter('http_request_timeout', array($this, 'timeout'));
			
			// Save specific time
			add_action('wc_ajax_urb_it_save_specific_time', array($this, 'save_specific_time'));
			
			// Don't hide shipping methods
			add_filter('woocommerce_urb_it_optional_postcode', '__return_true');
		}
		
		
		public function add_assets() {
			$this->add_asset('urb-it-checkout', 'urb-it-checkout.js');
			$this->add_asset('urb-it-klarna-checkout', 'klarna-checkout.js', array('klarna_checkout'));
		}
		
		
		public function add_fields($atts) {
			$shipping_postcode = WC()->customer->get_shipping_postcode();
			$postcode_error = ($shipping_postcode && !$this->validate->postcode($shipping_postcode));
			
			$shipping_methods = WC()->session->get( 'chosen_shipping_methods');
			$is_one_hour = ($shipping_methods && is_array($shipping_methods) && in_array('urb_it_one_hour', $shipping_methods));
			$is_specific_time = ($shipping_methods && is_array($shipping_methods) && in_array('urb_it_specific_time', $shipping_methods));
			?>
				<div class="urb-it"<?php if(!$is_one_hour && !$is_specific_time): ?> style="display: none;"<?php endif; ?>>
					<div class="woocommerce-error postcode-error"<?php if(!$postcode_error): ?> style="display: none;"<?php endif; ?>><?php echo sprintf(__('We can unfortunately not deliver to postcode %s.', self::LANG), '<span class="postcode">' . $shipping_postcode . '</span>'); ?></div>
					<div class="specific-time"<?php if($postcode_error || !$is_specific_time): ?> style="display: none;"<?php endif; ?>>
						<?php
							$this->template('checkout/field-delivery-time', array(
								'is_cart' => true,
								'selected_delivery_time' => $this->date(WC()->session->get('urb_it_delivery_time', $this->specific_time_offset())),
								'now' => $this->date('now'),
								'days' => $this->opening_hours->get()
							));
						?>
					</div>
				</div>
			<?php
		}
		
		
		public function timeout($seconds) {
			return 15;
		}
		
		
		public function save_specific_time() {
			$date = trim($_GET['urb_it_date']);
			$time = trim($_GET['urb_it_time']);
			
			if(!preg_match('/^\d{4}\-\d{2}-\d{2}$/', $date)) {
				return;
			}
			
			if(!preg_match('/^\d{2}\:\d{2}$/', $time)) {
				return;
			}
			
			$delivery_time = $date . ' ' . $time;
			
			WC()->session->set('urb_it_delivery_time', $delivery_time);
			
			if(WC()->session->order_awaiting_payment) {
				update_post_meta(WC()->session->order_awaiting_payment, '_urb_it_delivery_time', $delivery_time);
			}
		}
	}
	
	return new WooCommerce_Urb_It_Klarna_Checkout;