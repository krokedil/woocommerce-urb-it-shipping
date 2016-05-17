<?php
	/**
	 * Plugin Name: WooCommerce Urb-it Shipping
	 * Plugin URI: http://urb-it.com/
	 * Description: Let your customers choose urb-it as shipping method.
	 * Version: 2.1.3
	 * Author: Webbmekanikern
	 * Author URI: http://www.webbmekanikern.se/
	 * Text Domain: woocommerce-urb-it
	 * Domain Path: /languages/
	 * License: GPL2
	 */
	
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It {
		const VERSION = '2.1.3';
		const LANG = 'woocommerce-urb-it';
		
		const COMPANY_URL = 'http://urb-it.com/';
		const UPDATE_URL = 'http://download.urb-it.com/woocommerce/update.json';
		
		const ORDER_MAX_WEIGHT = 10; // kg
		const ORDER_MAX_VOLUME = 142000; // cm2 (1 liter = 1000 cm2)
		
		const OPTION_VERSION = 'wc-urb-it-version';
		const OPTION_GENERAL = 'wc-urb-it-general';
		const OPTION_CREDENTIALS = 'wc-urb-it-credentials';
		const OPTION_OPENING_HOURS = 'wc-urb-it-opening-hours';
		const OPTION_SPECIAL_OPENING_HOURS = 'wc-urb-it-special-opening-hours';
		
		const TRANSIENT_TTL = 60; // seconds
		const SPECIFIC_TIME_RANGE = '+2 days'; // Actually 3 days: today + 2 days forward
		
		static $file;
		static $path;
		static $path_includes;
		static $path_templates;
		static $path_assets;
		static $url;
		static $timezone;
		static $update_checker;
		static $added_checkout_assets = false;
		static $postcode_validator_assets_included = false;
		static $log = null;
		static $debug = false;
		
		
		public static function init() {
			// Define paths
			self::$file = __FILE__;
			self::$path = dirname(self::$file) . '/';
			self::$path_includes = self::$path . 'includes/';
			self::$path_templates = self::$path . 'templates/';
			self::$path_assets = self::$path . 'assets/';
			self::$url = plugin_dir_url(self::$file);
			
			// Installation & plugin removal
			register_activation_hook(self::$file, array(__CLASS__, 'install'));
			register_uninstall_hook(self::$file, array(__CLASS__, 'uninstall'));
			
			// Update checker
			require_once(self::$path . 'plugin-update-checker/plugin-update-checker.php');
			self::$update_checker = PucFactory::buildUpdateChecker(self::UPDATE_URL, self::$file);
			
			// Load text domain
			add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
			
			// Add shipping methods
			add_action('woocommerce_shipping_init', array(__CLASS__, 'shipping_init'));
			add_filter('woocommerce_shipping_methods', array(__CLASS__, 'add_shipping_method'));
			
			// Shipping calculator
			add_action('woocommerce_after_shipping_calculator', array(__CLASS__, 'shipping_calculator'));
			add_action('woocommerce_after_cart', array(__CLASS__, 'checkout_assets'));
			
			// Checkout page
			add_action('woocommerce_review_order_after_shipping', array(__CLASS__, 'checkout_fields'));
			add_action('woocommerce_after_checkout_form', array(__CLASS__, 'checkout_assets'));
			add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'validate_checkout_fields'));
			add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'order_created'), 10, 2);
			add_action('woocommerce_order_status_processing', array(__CLASS__, 'order_payed'));
			
			// Custom order status
			add_action('init', array(__CLASS__, 'register_order_status'));
			add_filter('wc_order_statuses', array(__CLASS__, 'order_statuses'));
			add_action('admin_head', array(__CLASS__, 'order_status_icon'));
			
			// Notices
			add_action('woocommerce_add_to_cart', array(__CLASS__, 'notice_added_product'), 10, 6);
			add_action('woocommerce_single_product_summary', array(__CLASS__, 'notice_product_page'), 35);
			add_action('woocommerce_before_checkout_form', array(__CLASS__, 'notice_checkout'));
			add_action('woocommerce_review_order_after_shipping', array(__CLASS__, 'notice_checkout_shipping'));
			add_action('woocommerce_before_cart', array(__CLASS__, 'notice_checkout'));
			
			// Postcode validator
			add_action('wp_ajax_urb_it_validate_postcode', array(__CLASS__, 'ajax_postcode_validator'));
			add_action('wp_ajax_nopriv_urb_it_validate_postcode', array(__CLASS__, 'ajax_postcode_validator'));
			add_action('woocommerce_single_product_summary', array(__CLASS__, 'postcode_validator_product_page'), 35);
			
			// Widget
			add_action('widgets_init', array(__CLASS__, 'register_widget'));
			
			// Turn off caching of shipping method
			add_filter('option_woocommerce_status_options', array(__CLASS__, 'turn_off_shipping_cache'));
		}
		
		
		// Save the plugin version on activation, if it doesn't exist
		public static function install() {
			add_option(self::OPTION_VERSION, self::VERSION);
			
			// Opening hours are deprecated
			delete_option(self::OPTION_OPENING_HOURS);
		}
		
		
		// Delete all options when the plugin is removed
		public static function uninstall() {
			delete_option(self::OPTION_VERSION);
			delete_option(self::OPTION_GENERAL);
			delete_option(self::OPTION_CREDENTIALS);
			delete_option(self::OPTION_OPENING_HOURS);
			delete_option(self::OPTION_SPECIAL_OPENING_HOURS);
		}
		
		
		// Add multilingual support
		public static function load_textdomain() {
			load_plugin_textdomain(self::LANG, false, dirname(plugin_basename(self::$file)) . '/languages'); 
		}
		
		
		// Include shipping classes
		public static function shipping_init() {
			include(self::$path_includes . 'class-shipping-one-hour.php');
			include(self::$path_includes . 'class-shipping-specific-time.php');
		}
		
		
		// Define shipping classes
		public static function add_shipping_method($methods) {
			$methods[] = 'WC_Urb_It_One_Hour'; 
			$methods[] = 'WC_Urb_It_Specific_Time';
			
			return $methods;
		}
		
		
		// Order status: Register
		public static function register_order_status() {
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
		public static function order_statuses($order_statuses) {
			$new_order_statuses = array();
			
			// Add new order status after processing
			foreach($order_statuses as $key => $status) {
				$new_order_statuses[$key] = $status;
				
				if($key === 'wc-processing') $new_order_statuses['wc-picked-up'] = __('Picked up', self::LANG);
			}
			
			return $new_order_statuses;
		}
		
		
		// Order status: Add icon
		public static function order_status_icon() {
			?>
				<style>
					.widefat .column-order_status mark.picked-up {
						background: url('<?php echo plugin_dir_url(__FILE__); ?>assets/img/wc-picked-up.png') no-repeat center center;
					}
				</style>
			<?php
		}
		
		
		public static function register_widget() {
			register_widget('Urb_It_Postcode_Validator_Widget');
		}
		
		
		// User notice: Product page
		public static function notice_product_page() {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['notice-product-page']) return;
			
			global $product;
			
			if(self::validate_product_volume($product) && self::validate_product_weight($product)) return;
			
			?><p style="color: #ff3d4b;"><?php _e('This product can\'t be delivered by urb-it.', self::LANG); ?></p><?php
		}
		
		
		// User notice: Added product
		public static function notice_added_product($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['notice-added-product']) return;
			
			WC()->cart->calculate_totals();
			
			if(!self::validate_cart_weight()) {
				wc_add_notice(sprintf(__('Your cart\'s total weight exceeds %d kilos and can\'t be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT), 'notice');
			}
			elseif(!self::validate_cart_volume()) {
				wc_add_notice(sprintf(__('Your cart\'s total volume exceeds %d liters and can\'t be delivered by urb-it.', self::LANG), self::ORDER_MAX_VOLUME / 1000), 'notice');
			}
			elseif(!self::validate_cart_bulkiness()) {
				wc_add_notice(__('Your cart contains a bulky product and can\'t be delivered by urb-it.', self::LANG), 'notice');
			}
		}
		
		
		// User notice: Checkout (and cart)
		public static function notice_checkout() {
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
		public static function notice_checkout_shipping() {
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
		public static function checkout_fields($is_cart = false) {
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
		
		
		// Checkout: Assets
		public static function checkout_assets() {
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
		
		
		// Shipping calculator
		public static function shipping_calculator() {
			$shipping_method = WC()->session->get('chosen_shipping_methods', array(get_option('woocommerce_default_shipping_method')));
			
			if(!in_array('urb_it_specific_time', $shipping_method) && !in_array('urb_it_one_hour', $shipping_method)) return;
			
			if(!empty($_POST['urb_it_date']) && !empty($_POST['urb_it_time'])) {
				WC()->session->set('urb_it_delivery_time', esc_attr($_POST['urb_it_date']) . ' ' . esc_attr($_POST['urb_it_time']));
			}
			
			if(!empty($_POST['urb_it_message'])) {
				WC()->session->set('urb_it_message', esc_attr($_POST['urb_it_message']));
			}
			
			?><form class="woocommerce-shipping-calculator" action="<?php echo esc_url( WC()->cart->get_cart_url() ); ?>" method="post"><?php
			
			self::checkout_fields(true);
			
			?><p><input class="button" type="submit" name="calc_shipping" value="<?php _e('Save', self::LANG); ?>" /></p><?php
			?></form><?php
		}
		
		
		// Order: Created
		public static function order_created($order_id, $posted) {
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
		public static function order_payed($order_id) {
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
					'description' => self::get_item_description($item_id, $_product, $order)
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
		public static function get_item_description($item_id, $product, $order) {
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
		
		
		// Get opening hours from the Retailer portal
		public static function get_opening_hours() {
			$today = self::create_datetime('today');
			$gmt = new DateTimeZone('GMT');
			$local = new DateTimeZone(get_option('timezone_string') ? get_option('timezone_string') : 'Europe/Stockholm');
			$max_time = clone $today;
			$max_time->modify(self::SPECIFIC_TIME_RANGE);
			
			$days = self::$debug ? false : get_transient('woocommerce_urb_it_delivery_days');
			
			if($days === false) {
				$credentials = get_option(self::OPTION_CREDENTIALS, array());
				
				if(!$credentials) return false;
				
				require_once(self::$path_includes . 'class-urbit.php');
				
				$urbit = new Urbit_Client($credentials);
				$urbit->set('from', $today->format('Y-m-d'));
				$urbit->set('to', $max_time->format('Y-m-d'));
				
				if($urbit->get_opening_hours() === 200) {
					$days = array();
					
					self::log('Fetched opening hours:');
					self::log($urbit->result);
					
					foreach($urbit->result as $day) {
						if($day->closed) continue;
						
						$hours = (object)array(
							'open' => new DateTime($day->from),
							'close' => new DateTime($day->to)
						);
						
						#$hours->open->setTimezone($local);
						#$hours->open->setTimezone($local);
						
						#$hours->open->modify('+1 hour');
						
						$days[] = $hours;
					}
					
					set_transient('woocommerce_urb_it_delivery_days', $days, self::TRANSIENT_TTL);
				}
				else {
					self::log('Couldn\'t fetch opening hours.');
					self::log($urbit->result, true);
				}
			}
			
			return $days;
		}
		
		
		// DEPRECATED
		public static function get_delivery_days() {
			_deprecated_function('get_delivery_days', '2.0.0');
		}
		
		
		// Postcode validator: Ajax
		public static function ajax_postcode_validator() {
			echo (isset($_GET['postcode']) && self::validate_postcode($_GET['postcode'])) ? '1' : '0';
			exit;
		}
		
		
		// Postcode validator: Assets
		public static function postcode_validator_assets() {
			if(self::$postcode_validator_assets_included) return;
			
			self::$postcode_validator_assets_included = true;
			?>
				<style>
					.urb-it-postcode-validator {
						background-image: url('<?php echo self::$url; ?>assets/img/urb-it-logotype.png');
						background-image: linear-gradient(transparent, transparent), url('<?php echo self::$url; ?>assets/img/urb-it-logotype.svg');
					}
					<?php include(self::$path_assets . 'css/postcode-validator.css'); ?>
				</style>
				<script>
					if(!ajaxurl) var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
					<?php include(self::$path_assets . 'js/postcode-validator.js'); ?>
				</script>
			<?php
		}
		
		
		// Postcode validator: Product page
		public static function postcode_validator_product_page() {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['postcode-validator-product-page']) return;
			
			global $product, $woocommerce;
			
			if(!$product->is_in_stock() || $product->urb_it_bulky || !self::validate_product_weight($product) || !self::validate_product_volume($product)) return;
			
			$postcode = $woocommerce->customer->get_shipping_postcode();
			if(!$postcode) $postcode = $woocommerce->customer->get_postcode();
			
			if($postcode) $last_status = $woocommerce->session->get('urb_it_postcode_result');
			
			include(self::$path_templates . 'postcode-validator/form.php');
			
			add_action('wp_footer', array(__CLASS__, 'postcode_validator_assets'));
		}
		
		
		// Validate: Product weight
		public static function validate_product_weight($product) {
			if(wc_get_weight($product->get_weight(), 'kg') > self::ORDER_MAX_WEIGHT) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_product_weight', $valid, $product);
		}
		
		
		// Validate: Product volume
		public static function validate_product_volume($product) {
			if(wc_get_dimension(intval($product->length), 'cm') * wc_get_dimension(intval($product->length), 'cm') * wc_get_dimension(intval($product->length), 'cm') > self::ORDER_MAX_VOLUME) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_product_volume', $valid, $product);
		}
		
		
		// Validate: Cart weight
		public static function validate_cart_weight() {
			if(wc_get_weight(WC()->cart->cart_contents_weight, 'kg') > self::ORDER_MAX_WEIGHT) $valid = false;
			else $valid = true;
			
			// Please don't use this filter without urb-it's knowledge
			return apply_filters('woocommerce_urb_it_valid_cart_weight', $valid);
		}
		
		
		// Validate: Cart volume
		public static function validate_cart_volume() {
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
		public static function validate_cart_bulkiness() {
			foreach(WC()->cart->get_cart() as $item) {
				$_product = $item['data'];
				
				if($_product->urb_it_bulky) return false;
			}
			
			return true;
		}
		
		
		// Validate: Checkout fields
		public static function validate_checkout_fields($posted) {
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
		
		
		// DEPRECATED
		public static function validate_all_opening_hours($delivery_time) {
			_deprecated_function('validate_all_opening_hours', '2.0.0', 'validate_opening_hours');
			return self::validate_opening_hours($delivery_time);
		}
		
		
		// Validate: Default opening hours
		public static function validate_opening_hours($delivery_time) {
			$days = self::get_opening_hours();
				
			if(!$days) return false;
			
			foreach($days as $day) {
				if($delivery_time >= $day->open && $delivery_time <= $day->close) return true;
			}
			
			return false;
		}
		
		
		// DEPRECATED
		public static function do_urbit_validation($delivery_time, $package, $delivery_type = 'OneHour') {
			_deprecated_function('do_urbit_validation', '2.0.0', 'validate_against_urbit');
			
			if(empty($package['destination']['postcode'])) return false;
			
			if(self::validate_against_urbit($delivery_time, $package['destination']['postcode'], $delivery_type) !== true) return false;
			
			return true;
		}
		
		
		// Validate: Urbit
		public static function validate_against_urbit($delivery_time, $postcode = '', $delivery_type = 'OneHour') {
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
		
		
		/*public static function validate_postcode($postcode, $remember_postcode = true) {
			if(!function_exists('curl_version')) return;
			
			$credentials = get_option(self::OPTION_CREDENTIALS, array());
			
			if(!$credentials) return;
			
			$postcode = preg_replace('/\D/', '', $postcode);
			
			if(strlen($postcode) !== 5) return false;
			
			$status_code = self::$debug ? false : get_transient('woocommerce_urb_it_zip_' . $postcode);
			
			if($status_code === false) {
				global $woocommerce;
				
				$json = json_encode(array(
					'postal_code' => $postcode
				));
				
				if($credentials['is_test']) $endpoint = 'https://stage-consumer-api.urb-it.com/api/consumer/postalcode/validate';
				else $endpoint = 'https://consumer-api.urb-it.com/api/consumer/postalcode/validate';
				
				$ch = curl_init($endpoint);
				
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				
				$headers = array(
					'Content-Type: application/json',
					'Cache-Control: no-cache',
					'Content-Length: ' . strlen($json)
				);
				
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				
				curl_exec($ch);
				
				$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
				curl_close($ch);
				
				$woocommerce->customer->set_shipping_postcode($postcode);
				$woocommerce->customer->set_postcode($postcode);
				$woocommerce->session->set('urb_it_postcode_result', $status_code);
				
				set_transient('woocommerce_urb_it_zip_' . $postcode, $status_code, self::TRANSIENT_TTL);
			}
			
			return ((int)$status_code === 200);
		}*/
		
		
		public static function validate_postcode($postcode, $remember_postcode = true) {
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
		
		
		// Turn off shipping cache, otherwise there might be problems with the opening hours
		public static function turn_off_shipping_cache($status_options = array()) {
			$status_options['shipping_debug_mode'] = '1';
			
			return $status_options;
		}
		
		
		// Create a DateTime object with the correct timezone
		public static function create_datetime($string) {
			if(!self::$timezone) {
				$timezone_string = get_option('timezone_string');
				
				self::$timezone = new DateTimeZone($timezone_string ? $timezone_string : 'Europe/Stockholm');
			}
			
			return new DateTime($string, self::$timezone);
		}
		
		
		// Include template file
		public static function template($path, $vars = array()) {
			extract($vars);
			include(self::$path_templates . $path . '.php');
		}
		
		
		// Sanitize phone number to the format "0701234567"
		public static function sanitize_phone($phone) {
			$phone = preg_replace(array('/\D/', '/^(00)?460?/'), array('', '0'), $phone);
			
			if(strncmp($phone, '07', 2) !== 0 || strlen($phone) !== 10) return false;
			
			return $phone;
		}
		
		
		// Error log
		public static function log($input, $debug_only = false) {
			if($debug_only && !self::$debug) return;
			
			if(!is_string($input) && !is_numeric($input)) $input = print_r($input, true);
			
			if(!self::$log) {
				if(!class_exists('WC_Logger')) return;
				
				self::$log = new WC_Logger();
			}
			
			self::$log->add('urb-it', $input);
		}
	}
	
	WooCommerce_Urb_It::init();
	
	require_once(dirname(__FILE__) . '/includes/class-coupon.php');
	require_once(dirname(__FILE__) . '/includes/class-shortcode.php');
	require_once(dirname(__FILE__) . '/includes/class-widget.php');
	
	if(is_admin()) require_once(dirname(__FILE__) . '/includes/class-admin.php');
?>