<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Order extends WooCommerce_Urb_It {
		public function __construct() {
			add_action('init', array($this, 'register_order_status'));
			add_filter('wc_order_statuses', array($this, 'order_statuses'));
			
			add_action('woocommerce_checkout_update_order_meta', array($this, 'order_created'), 10, 2);
			add_action('woocommerce_order_status_processing', array($this, 'order_payed'));
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
		
		
		// Order: Created
		public function order_created($order_id, $posted) {
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
		
		
		// Order: Payed
		public function order_payed($order_id) {
			if(apply_filters('woocommerce_urb_it_abort_submition', false)) return;
			
			$order = wc_get_order($order_id);
			$valid_shipping_methods = array('urb_it_one_hour', 'urb_it_specific_time');
			$shipping_method = '';
			
			foreach($order->get_shipping_methods() as $method) {
				if(in_array($method['method_id'], $valid_shipping_methods)) {
					$shipping_method = $method['method_id'];
					break;
				}
			}
			
			if(empty($shipping_method) || isset($order->urb_it_order_id)) return;
			
			$delivery_type = ($shipping_method == 'urb_it_one_hour') ? 'OneHour' : 'Specific';
			$delivery_time = self::create_datetime(($delivery_type == 'OneHour') ? apply_filters('woocommerce_urb_it_one_hour_offset', '+1 hour') : (!empty($order->urb_it_delivery_time) ? $order->urb_it_delivery_time : apply_filters('woocommerce_urb_it_specific_time_offset', '+1 hour 15 min')));
			
			if(!self::validate_all_opening_hours($delivery_time)) {
				self::log('Order #' . $order_id . ' (type ' . $delivery_type . ') got an invalid delivery time of ' . $delivery_time->format('Y-m-d H:i:s') . '.');
			}
			
			$credentials = get_option(self::OPTION_CREDENTIALS, array());
			
			if(!$credentials) return;
			
			require_once(self::$path_includes . 'class-urbit.php');
			
			$urbit = new Urbit_Client($credentials);
			
			$urbit->set('retailer_reference_id', $order->get_order_number());
			$urbit->set('delivery_type', $delivery_type);
			$urbit->set('order_direction', 'StoreToConsumer');
			$urbit->set('delivery_expected_at', $delivery_time);
			$urbit->set('consumer', apply_filters('woocommerce_urb_it_consumer_fields', array(
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
				'cell_phone' => self::sanitize_phone($order->billing_phone),
				'consumer_comment' => $order->urb_it_message
			), $order));
			
			do_action('woocommerce_urb_it_before_articles_added', $urbit, $order);
			
			$order_total = 0;
			
			foreach($order->get_items() as $item_id => $item) {
				$_product = $order->get_product_from_item($item);
				
				$sku = $_product->get_sku();
				$order_total += $order->get_line_total($item);
				
				$urbit->add_article(array(
					'identifier' => ($sku ? $sku : ('#' . ($_product->is_type('variation') ? $_product->variation_id : $_product->id))),
					'quantity' => $item['qty'],
					'description' => $this->get_item_description($item_id, $_product, $order)
				));
			}
			
			$urbit->set('total_amount_excl_vat', $order_total);
			
			do_action('woocommerce_urb_it_before_create_order', $urbit, $order);
			
			$status = $urbit->create_order();
			
			if($status === 200) {
				update_post_meta($order_id, '_urb_it_order_id', $urbit->result->order_id);
				
				if($credentials['is_test']) update_post_meta($order_id, '_urb_it_is_stage', 'yes');
				
				do_action('woocommerce_urb_it_order_success', $urbit->result, $order_id);
				
				if(isset($urbit->result->delivery) && isset($urbit->result->delivery->expected_delivery_at)) {
					$delivery_time = new DateTime($urbit->result->delivery->expected_delivery_at);
					$delivery_time->setTimezone(new DateTimeZone('Europe/Stockholm'));
				}
				
				/*if($delivery_type == 'OneHour')*/ update_post_meta($order_id, '_urb_it_delivery_time', $delivery_time->format('Y-m-d H:i'));
				
				if(apply_filters('woocommerce_urb_it_send_thankyou_email', true)) $order->add_order_note(sprintf(__('Thank you for choosing urb-it as shipping method. Your order is confirmed and will be delivered at %s.', self::LANG), $delivery_time->format('Y-m-d H:i')), true);
				
				return;
			}
			
			do_action('woocommerce_urb_it_order_failure', $urbit->result, $order_id, $status);
			
			// Something went wrong
			if($status === 0) $status = 'Time-out';
			
			$error_message = $urbit->result ? $urbit->result->developer_message : ('HTTP ' . $status);
			
			self::log('API error while paying order #' . $order_id . ': ' . $status . ', ' . $error_message);
			
			$order->add_order_note('Urb-it error: ' . $error_message);
			wp_mail(get_option('admin_email'), __('Urb-it problem', self::LANG), sprintf(__('The problem below occured while serving order #%d. If you can\'t solve the problem, contact the urb-it support.', self::LANG), $order_id) . "\n\n" . $error_message);
		}
		
		
		// Get order item description
		public function get_item_description($item_id, $product, $order) {
			$attributes = array();
			$metadata = $order->has_meta($item_id);
			
			if(!$metadata) return $product->get_title();
			
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
			
			return apply_filters('woocommerce_urb_it_item_description', $product->get_title() . (!empty($attributes) ? (' - ' . implode(', ', $attributes)) : ''), $item_id, $product, $order);
		}
	}
	
	new WooCommerce_Urb_It_Order;