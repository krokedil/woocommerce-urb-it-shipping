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
			
			// ---
			
			add_action('wc_ajax_urb_it_reinit_kco', array($this, 'reinit_kco'));
			add_filter('kco_create_order', array($this, 'add_attachment'));
			#add_filter('kco_set_shipping_address', array($this, 'set_shipping_address'), 10, 3);
			
			// Save initial shipping address to order
			add_action('woocommerce_checkout_update_order_meta', array($this, 'save_initial_shipping_address'));
			
			// Save postcode when set
			add_action('wc_ajax_urb_it_validate_postcode', array($this, 'set_shipping_postcode'), 9);
			
			// Save delivery time when set
			add_action('wc_ajax_urb_it_kco_delivery_time', array($this, 'save_delivery_time'));
			
			// Save shipping address when street and/or city is set
			add_action('kco_before_confirm_order', array($this, 'set_shipping_address'), 1);
			
			// Don't add order note
			add_filter('woocommerce_urb_it_add_delivery_time_order_note', array($this, 'add_delivery_time_order_note'), 10, 2);
		}
		
		
		public function reinit_kco() {
			if(!class_exists('WC_Gateway_Klarna_Checkout')) {
				$this->log('Re-initialization of KCO failed as class WC_Gateway_Klarna_Checkout is undefined.');
				exit;
			}
			
			$this->log($_GET);
			
			// Ensure shipping details are set
			if(!isset($_GET['urb-it-street']) || !isset($_GET['urb-it-postcode']) || !isset($_GET['urb-it-city'])) {
				$this->log('Re-initialization of KCO failed as no shipping information is set.');
				exit;
			}
			
			$this->log('Saving shipping address:', $_GET['urb-it-street'], $_GET['urb-it-postcode'], $_GET['urb-it-city']);
			
			// Save shipping address for later attachment
			$this->save_shipping_address(array(
				'street' => $_GET['urb-it-street'],
				'postcode' => $_GET['urb-it-postcode'],
				'city' => $_GET['urb-it-city']
			));
			
			#WC()->customer->set_shipping_address(sanitize_text_field($_GET['urb-it-street']));
			#WC()->customer->set_shipping_postcode(sanitize_text_field($_GET['urb-it-postcode']));
			#WC()->customer->set_shipping_city(sanitize_text_field($_GET['urb-it-city']));
			
			// Clear order session to force a new KCO session, and bypass resumetion.
			// We have to do this as the other_delivery_address attachment needs to be added on initialization.
			unset(WC()->session->klarna_checkout);
			
			$data = new WC_Gateway_Klarna_Checkout;
			
			echo $data->get_klarna_checkout_page();
			exit;
		}
		
		
		public function add_attachment($data) {
			$shipping_street = trim(WC()->customer->get_shipping_address());
			$shipping_postcode = str_replace(' ', '', WC()->customer->get_shipping_postcode());
			$shipping_city = trim(WC()->customer->get_shipping_city());
			
			// Do not attach an incomplete address
			if(!$shipping_street || !$shipping_postcode || !$shipping_city) {
				$this->log('Tried to attach an incomplete address to KCO.');
				return $data;
			}
			
			if(!preg_match('/([^\d]+)\s+([\d]+)/i', $shipping_street, $matches)) {
				$this->log('Could not find address street and number in string.');
				return $data;
			}
			
			$shipping_street = trim($matches[1]);
			$shipping_number = trim($matches[2]);
			
			$data['attachment'] = array(
				'content_type' => 'application/vnd.klarna.internal.emd-v2+json',
				'body' => json_encode(array(
					'other_delivery_address' => array(array(
						'shipping_method' => 'unregistered box',
						'shipping_type' => 'express',
						'street_address' => $shipping_street,
						'street_number' => $shipping_number,
						'postal_code' => $shipping_postcode,
						'city' => $shipping_city,
						'country' => 'se'
					))
				))
			);
			
			/*$body = array();
			
			$body['other_delivery_address'] = array(array(
				'shipping_method' => 'unregistered box',
				'shipping_type' => 'express',
				'street_address' => $shipping_street,
				'street_number' => $shipping_number,
				'postal_code' => $shipping_postcode,
				'city' => $shipping_city,
				'country' => 'se'
			));
			
			$data['attachment']['content_type'] = 'application/vnd.klarna.internal.emd-v2+json';
			$data['attachment']['body'] = json_encode($body);*/
			
			return $data;
		}
		
		
		public function save_initial_shipping_address($order_id) {
			if(!$this->is_urbit($order_id)) return;
			
			$shipping_street = get_post_meta($order_id, '_urbit_shipping_address_1', true);
			$shipping_postcode = get_post_meta($order_id, '_urbit_shipping_postcode', true);
			$shipping_city = get_post_meta($order_id, '_urbit_shipping_city', true);
			
			// Abort if already set
			if($shipping_street || $shipping_postcode || $shipping_city) return;
			
			$shipping_street = WC()->customer->get_shipping_address();
			$shipping_postcode = WC()->customer->get_shipping_postcode();
			$shipping_city = WC()->customer->get_shipping_city();
			
			// Fall back to billing fields
			if(!$shipping_street) $shipping_street = WC()->customer->get_address();
			if(!$shipping_postcode) $shipping_postcode = WC()->customer->get_postcode();
			if(!$shipping_city) $shipping_city = WC()->customer->get_city();
			
			$this->save_shipping_address(array(
				'street' => $shipping_street,
				'postcode' => $shipping_postcode,
				'city' => $shipping_city
			));
		}
		
		
		/*public function set_shipping_address($address, $order, $data) {
			$shipping_street = WC()->customer->get_shipping_address();
			$shipping_postcode = WC()->customer->get_shipping_postcode();
			$shipping_city = WC()->customer->get_shipping_city();
			
			if($shipping_street && $shipping_postcode && $shipping_city) {
				$address['country'] = 'SE';
				$address['address_1'] = $shipping_street;
				$address['address_2'] = '';
				$address['postcode'] = $shipping_postcode;
				$address['city'] = $shipping_city;
			}
			
			$this->log('Changed KCO shipping address:', $address);
			
			return $address;
		}*/
		
		
		public function set_shipping_postcode() {
			$this->log('Ah - ajax!');
			
			$this->save_shipping_address(array(
				'postcode' => $_GET['postcode']
			));
		}
		
		
		public function set_shipping_address($order_id) {
			if(!$this->is_urbit($order_id)) return;
			
			$shipping_street = get_post_meta($order_id, '_urbit_shipping_address_1', true);
			$shipping_postcode = get_post_meta($order_id, '_urbit_shipping_postcode', true);
			$shipping_city = get_post_meta($order_id, '_urbit_shipping_city', true);
			
			if($shipping_street && $shipping_postcode && $shipping_city) {
				update_post_meta($order_id, '_shipping_address_1', $shipping_street);
				update_post_meta($order_id, '_shipping_address_2', '');
				update_post_meta($order_id, '_shipping_postcode', $shipping_postcode);
				update_post_meta($order_id, '_shipping_city', $shipping_city);
				update_post_meta($order_id, '_shipping_country', 'SE');
				
				$shipping_street = delete_post_meta($order_id, '_urbit_shipping_address_1');
				$shipping_postcode = delete_post_meta($order_id, '_urbit_shipping_postcode');
				$shipping_city = delete_post_meta($order_id, '_urbit_shipping_city');
			}
		}
		
		
		public function save_delivery_time() {
			if($order_id = WC()->session->ongoing_klarna_order) {
				if(!isset($_GET['urb_it_date']) || !isset($_GET['urb_it_time'])) {
					echo '0';
					exit;
				}
				
				$delivery_time = $this->date($_GET['urb_it_date'] . ' ' . $_GET['urb_it_time']);
				
				if($this->validate->opening_hours($delivery_time)) {
					WC()->session->set('urb_it_delivery_time', $delivery_time->format('Y-m-d H:i:s'));
					update_post_meta($order_id, '_urb_it_delivery_time', $delivery_time->format('Y-m-d H:i:s'));
					
					$order = wc_get_order($order_id);
					$order->add_order_note(sprintf(__('Urb-it delivery time: %s', self::LANG), $delivery_time->format('Y-m-d H:i:s')));
					
					echo '1';
				}
				else {
					echo '0';
				}
				
				exit;
			}
		}
		
		
		protected function is_urbit($order) {
			$order = wc_get_order($order);
			
			return ($order->has_shipping_method('urb_it_one_hour') || $order->has_shipping_method('urb_it_specific_time'));
		}
		
		
		protected function save_shipping_address($address) {
			// Sanitize whole address
			$address = array_map('sanitize_text_field', $address);
			
			$this->log('Changing KCO shipping address:', $address);
			
			// Save to session
			if(isset($address['street'])) WC()->customer->set_shipping_address($address['street']);
			if(isset($address['postcode'])) WC()->customer->set_shipping_postcode($address['postcode']);
			if(isset($address['city'])) WC()->customer->set_shipping_city($address['city']);
			
			// Save to ongoing order
			if($order_id = WC()->session->ongoing_klarna_order) {
				if(isset($address['street'])) {
					update_post_meta($order_id, '_urbit_shipping_address_1', $address['street']);
					update_post_meta($order_id, '_shipping_address_1', $address['street']);
				}
				
				if(isset($address['postcode'])) {
					update_post_meta($order_id, '_urbit_shipping_postcode', $address['postcode']);
					update_post_meta($order_id, '_shipping_postcode', $address['postcode']);
				}
				
				if(isset($address['city'])) {
					update_post_meta($order_id, '_urbit_shipping_city', $address['city']);
					update_post_meta($order_id, '_shipping_city', $address['city']);
				}
				
				update_post_meta($order_id, '_shipping_address_2', '');
				update_post_meta($order_id, '_shipping_country', 'SE');
			}
		}
		
		
		public function add_assets() {
			$this->add_asset('urb-it-checkout', 'urb-it-checkout.js');
			$this->add_asset('urb-it-klarna-checkout', 'klarna-checkout.js', array('klarna_checkout'));
			$this->add_asset('urb-it-klarna-checkout', 'klarna-checkout.css');
		}
		
		
		public function add_fields($atts) {
			if($order_id = WC()->session->ongoing_klarna_order) {
				$shipping_street = get_post_meta($order_id, '_urbit_shipping_address_1', true);
				$shipping_postcode = get_post_meta($order_id, '_urbit_shipping_postcode', true);
				$shipping_city = get_post_meta($order_id, '_urbit_shipping_city', true);
				
				$this->log('Fetched from order #' . $order_id, $shipping_street, $shipping_postcode, $shipping_city);
			}
			else {
				$shipping_street = WC()->customer->get_shipping_address();
				$shipping_postcode = WC()->customer->get_shipping_postcode();
				$shipping_city = WC()->customer->get_shipping_city();
				
				// Fall back to billing fields
				if(!$shipping_street) $shipping_street = WC()->customer->get_address();
				if(!$shipping_postcode) $shipping_postcode = WC()->customer->get_postcode();
				if(!$shipping_city) $shipping_city = WC()->customer->get_city();
				
				$this->log('Fetched from session', $shipping_street, $shipping_postcode, $shipping_city);
			}
			
			$postcode_error = ($shipping_postcode && !$this->validate->postcode($shipping_postcode));
			
			$shipping_methods = WC()->session->get( 'chosen_shipping_methods');
			$is_one_hour = ($shipping_methods && is_array($shipping_methods) && in_array('urb_it_one_hour', $shipping_methods));
			$is_specific_time = ($shipping_methods && is_array($shipping_methods) && in_array('urb_it_specific_time', $shipping_methods));
			?>
				<form class="urb-it klarna-checkout-urb-it" <?php if(!$is_one_hour && !$is_specific_time): ?>style="display: none;"<?php endif; ?>>
					<input type="hidden" name="urb-it-street" value="<?php echo $shipping_street; ?>" />
					<input type="hidden" name="urb-it-postcode" value="<?php echo $shipping_postcode; ?>" data-valid="<?php echo $postcode_error ? 'false' : 'true'; ?>" />
					<input type="hidden" name="urb-it-city" value="<?php echo $shipping_city; ?>" />
					
					<div class="urb-it-shipping-address"<?php if(empty($shipping_street) || empty($shipping_postcode) || empty($shipping_city)): ?> style="display: none;"<?php endif; ?>>
						<h4>Urb-it överlämnar din order till:</h4>
						<p class="urb-it-address"><?php echo $shipping_street . '<br />' . $shipping_postcode . ' ' . $shipping_city; ?></p>
						<p><input class="button urb-it-change" type="button" value="Ändra" /></p>
					</div>
					
					<div class="specific-time"<?php if(!$is_specific_time): ?> style="display: none;"<?php endif; ?>>
						<h4><?php _e('When should we come?', self::LANG); ?></h4>
						<?php
							$this->template('checkout/field-delivery-time', array(
								'is_cart' => true,
								'selected_delivery_time' => $this->date(WC()->session->get('urb_it_delivery_time', $this->specific_time_offset())),
								'now' => $this->date('now'),
								'days' => $this->opening_hours->get()
							));
						?>
					</div>
					
					<div class="woocommerce-error delivery-time-error" style="display: none;"><?php _e('Please pick a valid delivery time.', self::LANG); ?></div>
					
					<script>jQuery('#urb_it_date').change();</script>
				</form>
				
				<div class="urb-it-html" style="display: none;">
					<!--<h4><?php _e('Can you receive deliveries from urb-it?', self::LANG); ?></h4>-->
					<p>Innan du kan få dina varor med urb-it behöver vi kontrollera att vi kan urba till dig. Knappa in ditt postnummer nedan.</p>
					<p class="urb-it-postcode">
						<input id="urb-it-postcode" class="input-text" name="urb-it-postcode" type="text" />
						<input class="button check-postcode" type="button" value="<?php _e('Check postcode', self::LANG); ?>" />
					</p>
					
					<div class="woocommerce-error postcode-error"><?php echo sprintf(__('We can unfortunately not deliver to postcode %s.', self::LANG), '<span class="postcode"></span>'); ?></div>
					
					<div class="shipping-address">
						<h4><?php _e('Were should we come?', self::LANG); ?></h4>
						<p>
							<input id="urb-it-street" class="input-text urb-it-street" name="urb-it-street" type="text" placeholder="<?php _e('Street Address', self::LANG); ?>" />
							<input id="urb-it-city" class="input-text urb-it-city" name="urb-it-city" type="text" placeholder="<?php _e('City', self::LANG); ?>" />
						</p>
						<p>
							<input class="button" type="submit" value="Fortsätt" />
						</p>
					</div>
				</div>
			<?php
			
			return;
			
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
		
		
		public function add_delivery_time_order_note($add_order_note, $order) {
			if($order->get_status() === 'kco-incomplete') {
				$add_order_note = false;
			}
			
			return $add_order_note;
		}
	}
	
	return new WooCommerce_Urb_It_Klarna_Checkout;