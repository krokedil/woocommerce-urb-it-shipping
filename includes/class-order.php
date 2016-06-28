<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Order extends WooCommerce_Urb_It {
		public function __construct() {
			add_action('init', array($this, 'register_order_status'));
			add_filter('wc_order_statuses', array($this, 'order_statuses'));
			
			add_action('woocommerce_checkout_update_order_meta', array($this, 'save_data'), 10, 2);
			add_action('woocommerce_order_status_processing', array($this, 'create'));
		}
		
		
		// Order status: Register
		public function register_order_status() {
			register_post_status('wc-picked-up', array(
				'label'											=> __('Picked up', self::LANG),
				'public'										=> true,
				'exclude_from_search'				=> false,
				'show_in_admin_all_list'		=> true,
				'show_in_admin_status_list'	=> true,
				'label_count'								=> _n_noop('Picked up <span class="count">(%s)</span>', 'Picked up <span class="count">(%s)</span>', self::LANG)
			));
		}
		
		
		// Order status: Add among others
		public function order_statuses($order_statuses) {
			$new_order_statuses = array();
			
			// Add new order status after processing
			foreach($order_statuses as $key => $status) {
				$new_order_statuses[$key] = $status;
				
				if($key === 'wc-processing') $new_order_statuses['wc-picked-up'] = __('Picked up', self::LANG);
			}
			
			return $new_order_statuses;
		}
		
		
		// Order created
		public function save_data($order_id, $posted) {
			$delivery_time = (!empty($_POST['urb_it_date']) && !empty($_POST['urb_it_time'])) ? (esc_attr($_POST['urb_it_date']) . ' ' . esc_attr($_POST['urb_it_time'])) : WC()->session->get('urb_it_delivery_time');
			$message = !empty($_POST['urb_it_message']) ? esc_attr($_POST['urb_it_message']) : WC()->session->get('urb_it_message');
			
			$order = wc_get_order($order_id);
			
			// If specific time, save the delivery time for later
			if(!empty($delivery_time)) {
				update_post_meta($order_id, '_urb_it_delivery_time', $delivery_time);
				
				$order->add_order_note(sprintf(__('Urb-it delivery time: %s', self::LANG), $delivery_time));
			}
			
			// If there's an message, save it
			if(!empty($message)) {
				update_post_meta($order_id, '_urb_it_message', $message);
				
				$order->add_order_note(sprintf(__('Urb-it message: %s', self::LANG), $message));
			}
		}
		
		
		// Order payed
		public function create($order_id) {
			if(apply_filters('woocommerce_urb_it_abort_submition', false)) {
				$this->log('urb-it order submition aborted by filter (woocommerce_urb_it_abort_submition).');
				return;
			}
			
			try {
				$order = wc_get_order($order_id);
				$valid_shipping_methods = array('urb_it_one_hour', 'urb_it_specific_time');
				$shipping_method = '';
				
				foreach($order->get_shipping_methods() as $method) {
					if(in_array($method['method_id'], $valid_shipping_methods)) {
						$shipping_method = $method['method_id'];
						break;
					}
				}
				
				if(empty($shipping_method)) {
					$this->log('Order #' . $order->get_order_number() . ' hasn\'t urb-it as delivery method - aborting.');
					return;
				}
				
				if(isset($order->urb_it_order_id)) {
					$this->error('Order #' . $order->get_order_number() . ' has already been sent to urb-it (#' . $order->urb_it_order_id . ') - aborting.');
					return;
				}
				
				$delivery_type = ($shipping_method == 'urb_it_one_hour') ? 'OneHour' : 'Specific';
				$delivery_time = $this->date(($delivery_type == 'OneHour') ? $this->one_hour_offset() : (!empty($order->urb_it_delivery_time) ? $order->urb_it_delivery_time : $this->specific_time_offset()));
				
				if(!$this->validate->opening_hours($delivery_time)) {
					$this->error('Order #' . $order_id . ' (type ' . $delivery_type . ') got an invalid delivery time of ' . $delivery_time->format('Y-m-d H:i:s') . '.');
				}
				
				$order_data = array(
					'retailer_reference_id' => $order->get_order_number(),
					'delivery_type' => $delivery_type,
					'order_direction' => 'StoreToConsumer',
					'consumer' => apply_filters('woocommerce_urb_it_consumer_fields', array(
						'address' => array(
							'company_name' => $order->shipping_company,
							'street' => $order->shipping_address_1,
							'street2' => $order->shipping_address_2,
							'postal_code' => str_replace(' ', '', $order->shipping_postcode),
							'city' => $order->shipping_city,
							'country' => $order->shipping_country
						),
						'first_name' => $order->shipping_first_name,
						'last_name' => $order->shipping_last_name,
						'email' => $order->billing_email,
						'cell_phone' => $this->sanitize_phone($order->billing_phone),
						'consumer_comment' => $order->urb_it_message
					), $order),
					'store_location' => array('id' => $this->setting('pickup_location_id')),
					'articles' => array()
				);
				
				if($delivery_type === 'Specific') $order_data['delivery_expected_at'] = $delivery_time->format(self::DATE_FORMAT);
				
				do_action('woocommerce_urb_it_before_articles_added', $order, $this->urbit);
				
				$order_total = 0;
				
				foreach($order->get_items(array('line_item', 'coupon')) as $item_id => $item) {
					if($item['type'] === 'coupon') {
						$line_total = (float)$item['discount_amount'] + (float)$item['discount_amount_tax'];
						
						$order_data['articles'][] = array(
							'identifier' => 'VOUCHER',
							'quantity' => 1,
							'description' => sprintf(__('Used %s of voucher: %s', self::LANG), $this->format_price($line_total), $item['name'])
						);
					}
					else {
						$_product = $order->get_product_from_item($item);
						
						$sku = $_product->get_sku();
						$order_total += $order->get_line_total($item);
						
						$order_data['articles'][] = array(
							'identifier' => ($sku ? $sku : ('#' . ($_product->is_type('variation') ? $_product->variation_id : $_product->id))),
							'quantity' => $item['qty'],
							'description' => $this->get_item_description($item_id, $_product, $order)
						);
					}
				}
				
				$order_data['total_amount_excl_vat'] = $order_total;
				
				do_action('woocommerce_urb_it_before_create_order', $this->urbit, $order);
				
				$order_data = apply_filters('woocommerce_urb_it_create_order_data', $order_data, $order);
				
				if(isset($order_data['beneficiary']['cell_phone'])) $order_data['beneficiary']['cell_phone'] = $this->sanitize_phone($order_data['beneficiary']['cell_phone']);
				
				$this->log('Creating order:', $this->urbit->storeKey, $order_data);
				
				$urbit_order = $this->urbit->CreateOrder($order_data);
				
				$this->log('Order created successfully in ' . $this->setting('environment') . ':', $urbit_order);
				
				update_post_meta($order_id, '_urb_it_order_id', $urbit_order->order_id);
				update_post_meta($order_id, '_urb_it_environment', $this->setting('environment'));
				
				if($this->setting('environment') === 'stage') update_post_meta($order_id, '_urb_it_is_stage', 'yes');
				
				do_action('woocommerce_urb_it_order_success', $urbit_order, $order_id);
				
				if(isset($urbit_order->delivery) && isset($urbit_order->delivery->expected_delivery_at)) {
					$delivery_time = $this->date($urbit_order->delivery->expected_delivery_at);
					$delivery_time->setTimezone($this->timezone);
				}
				
				update_post_meta($order_id, '_urb_it_delivery_time', $delivery_time->format('Y-m-d H:i'));
				
				if(apply_filters('woocommerce_urb_it_send_thankyou_email', true)) $order->add_order_note(sprintf(__('Thank you for choosing urb-it as shipping method. Your order is confirmed and will be delivered at %s.', self::LANG), $delivery_time->format('Y-m-d H:i')), true);
			}
			catch(Exception $e) {
				$this->error('Error while creating order #' . $order->get_order_number() . ': ' . $e->getMessage());
				
				do_action('woocommerce_urb_it_order_failure', $this->urbit->httpBody, $order_id, $status);
				
				$order->add_order_note('Urb-it error: ' . $e->getMessage());
				$this->notify_urbit('The problem below occured while serving order #' . $order->get_order_number() . '.' . "\n\n" . $e->getMessage());
			}
		}
		
		
		// Get order item description
		public function get_item_description($item_id, $product, $order) {
			$metadata = $order->has_meta($item_id);
			$title = $product->get_title();
			
			if($metadata) {
				$attributes = array();
				
				foreach($metadata as $meta) {
					// Skip hidden core fields
					if(in_array($meta['meta_key'], apply_filters('woocommerce_hidden_order_itemmeta', array(
						'_qty',
						'_tax_class',
						'_product_id',
						'_variation_id',
						'_line_subtotal',
						'_line_subtotal_tax',
						'_line_total',
						'_line_tax',
					)))) continue;
	
					// Skip serialised meta
					if(is_serialized($meta['meta_value'])) continue;
	
					// Get attribute data
					if(taxonomy_exists(wc_sanitize_taxonomy_name($meta['meta_key']))) {
						$term = get_term_by('slug', $meta['meta_value'], wc_sanitize_taxonomy_name($meta['meta_key']));
						if(isset($term->name)) $meta['meta_value'] = $term->name;
					}
					
					$attributes[] = rawurldecode($meta['meta_value']);
				}
				
				if($attributes) $title .= ' - ' . implode(', ', $attributes);
			}
			
			return apply_filters('woocommerce_urb_it_item_description', $title, $item_id, $product, $order);
		}
	}
	
	return new WooCommerce_Urb_It_Order;