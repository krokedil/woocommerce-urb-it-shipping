<?php
	/**
	 * Plugin Name: WooCommerce Urb-it Shipping
	 * Plugin URI: http://urb-it.com/
	 * Description: Let your customers choose urb-it as shipping method.
	 * Version: 3.0.0
	 * Author: Webbmekanikern
	 * Author URI: http://www.webbmekanikern.se/
	 * Text Domain: woocommerce-urb-it
	 * Domain Path: /languages/
	 * License: GPL2
	 */
	
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It {
		const VERSION = '3.0.0';
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
		
		const SETTINGS_PREFIX = 'urb_it_settings_';
		
		static $file;
		protected $path;
		static $path_includes;
		static $path_templates;
		static $path_assets;
		static $url;
		static $timezone;
		static $update_checker;
		static $added_checkout_assets = false;
		static $postcode_validator_assets_included = false;
		protected $log = null;
		static $debug = false;
		
		public $order;
		
		
		public function __construct() {
			// Define paths
			self::$file = __FILE__;
			$this->path = dirname(__FILE__) . '/';
			self::$path_includes = $this->path . 'includes/';
			self::$path_templates = $this->path . 'templates/';
			self::$path_assets = $this->path . 'assets/';
			self::$url = plugin_dir_url(__FILE__);
			
			// Installation & plugin removal
			register_activation_hook(self::$file, array(__CLASS__, 'install'));
			register_uninstall_hook(self::$file, array(__CLASS__, 'uninstall'));
			
			// Update checker
			require_once($this->path . 'plugin-update-checker/plugin-update-checker.php');
			self::$update_checker = PucFactory::buildUpdateChecker(self::UPDATE_URL, __FILE__);
			
			// Load text domain
			add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
			
			// Add shipping methods
			add_action('woocommerce_shipping_init', array(__CLASS__, 'shipping_init'));
			add_filter('woocommerce_shipping_methods', array(__CLASS__, 'add_shipping_method'));
			
			// Shipping calculator
			add_action('woocommerce_after_shipping_calculator', array(__CLASS__, 'shipping_calculator'));
			add_action('woocommerce_after_cart', array(__CLASS__, 'checkout_assets'));
			
			// Notices
			add_action('woocommerce_add_to_cart', array(__CLASS__, 'notice_added_product'), 10, 6);
			add_action('woocommerce_single_product_summary', array(__CLASS__, 'notice_product_page'), 35);
			
			// Postcode validator
			add_action('wp_ajax_urb_it_validate_postcode', array(__CLASS__, 'ajax_postcode_validator'));
			add_action('wp_ajax_nopriv_urb_it_validate_postcode', array(__CLASS__, 'ajax_postcode_validator'));
			add_action('woocommerce_single_product_summary', array(__CLASS__, 'postcode_validator_product_page'), 35);
			
			// Widget
			add_action('widgets_init', array(__CLASS__, 'register_widget'));
			
			// Turn off caching of shipping method
			add_filter('option_woocommerce_status_options', array(__CLASS__, 'turn_off_shipping_cache'));
			
			$this->order = include($this->path . 'includes/class-order.php');
		}
		
		
		public function __get($name) {
			if($name === 'urbit') {
				try {
					if(!class_exists('UrbRequest')) {
						require(dirname(__FILE__) . '/includes/sdk/UrbRequest.php');
					}
					
					$credentials = get_option(self::OPTION_CREDENTIALS, array());
					
					if(!$credentials) {
						throw new Exception('Could not fetch credentials.');
					}
					
					$this->{$name} = new UrbRequest($this->setting('store_key'), $this->setting('shared_secret'), ($this->setting('environment') === 'stage'));
				}
				catch(Exception $e) {
					$this->error($e->getMessage());
				}
			}
		}
		
		
		public function setting($name, $raw = false) {
			if(!$raw && in_array($name, array('store_key', 'shared_secret', 'pickup_location_id'))) {
				$environment = get_option(self::SETTINGS_PREFIX . 'environment');
				
				$name = $environment . '_' . $name;
			}
			
			return get_option(self::SETTINGS_PREFIX . $name);
		}
		
		
		// Save the plugin version on activation, if it doesn't exist
		public function install() {
			add_option(self::OPTION_VERSION, self::VERSION);
			
			// Opening hours are deprecated
			delete_option(self::OPTION_OPENING_HOURS);
		}
		
		
		// Delete all options when the plugin is removed
		public function uninstall() {
			delete_option(self::OPTION_VERSION);
			delete_option(self::OPTION_GENERAL);
			delete_option(self::OPTION_CREDENTIALS);
			delete_option(self::OPTION_OPENING_HOURS);
			delete_option(self::OPTION_SPECIAL_OPENING_HOURS);
		}
		
		
		// Add multilingual support
		public function load_textdomain() {
			load_plugin_textdomain(self::LANG, false, dirname(plugin_basename(self::$file)) . '/languages'); 
		}
		
		
		// Include shipping classes
		public function shipping_init() {
			include(self::$path_includes . 'class-shipping-one-hour.php');
			include(self::$path_includes . 'class-shipping-specific-time.php');
		}
		
		
		// Define shipping classes
		public function add_shipping_method($methods) {
			$methods[] = 'WC_Urb_It_One_Hour'; 
			$methods[] = 'WC_Urb_It_Specific_Time';
			
			return $methods;
		}
		
		
		public function register_widget() {
			register_widget('Urb_It_Postcode_Validator_Widget');
		}
		
		
		// User notice: Product page
		public function notice_product_page() {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['notice-product-page']) return;
			
			global $product;
			
			if(self::validate_product_volume($product) && self::validate_product_weight($product)) return;
			
			?><p style="color: #ff3d4b;"><?php _e('This product can\'t be delivered by urb-it.', self::LANG); ?></p><?php
		}
		
		
		// User notice: Added product
		public function notice_added_product($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
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
			
			self::checkout_fields(true);
			
			?><p><input class="button" type="submit" name="calc_shipping" value="<?php _e('Save', self::LANG); ?>" /></p><?php
			?></form><?php
		}
		
		
		// Get opening hours from the Retailer portal
		public function get_opening_hours() {
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
		public function get_delivery_days() {
			_deprecated_function('get_delivery_days', '2.0.0');
		}
		
		
		// Postcode validator: Ajax
		public function ajax_postcode_validator() {
			echo (isset($_GET['postcode']) && self::validate_postcode($_GET['postcode'])) ? '1' : '0';
			exit;
		}
		
		
		// Postcode validator: Assets
		public function postcode_validator_assets() {
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
		public function postcode_validator_product_page() {
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
		
		
		// Turn off shipping cache, otherwise there might be problems with the opening hours
		public function turn_off_shipping_cache($status_options = array()) {
			$status_options['shipping_debug_mode'] = '1';
			
			return $status_options;
		}
		
		
		// Create a DateTime object with the correct timezone
		public function create_datetime($string) {
			if(!self::$timezone) {
				$timezone_string = get_option('timezone_string');
				
				self::$timezone = new DateTimeZone($timezone_string ? $timezone_string : 'Europe/Stockholm');
			}
			
			return new DateTime($string, self::$timezone);
		}
		
		
		// Include template file
		public function template($path, $vars = array()) {
			extract($vars);
			include(self::$path_templates . $path . '.php');
		}
		
		
		// Sanitize phone number to the format "0701234567"
		public function sanitize_phone($phone) {
			$phone = preg_replace(array('/\D/', '/^(00)?460?/'), array('', '0'), $phone);
			
			if(strncmp($phone, '07', 2) !== 0 || strlen($phone) !== 10) return false;
			
			return $phone;
		}
		
		
		// Error log
		public function log($input, $debug_only = false) {
			if($debug_only && !self::$debug) return;
			
			if(!is_string($input) && !is_numeric($input)) $input = print_r($input, true);
			
			if(!self::$log) {
				if(!class_exists('WC_Logger')) return;
				
				self::$log = new WC_Logger();
			}
			
			self::$log->add('urb-it', $input);
		}
		
		
		public function error($input) {
			if(!is_string($input) && !is_numeric($input)) $input = print_r($input, true);
			
			if(!$this->log) {
				if(!class_exists('WC_Logger')) return;
				
				$this->log = new WC_Logger();
			}
			
			$this->log->add('urb-it', $input);
		}
	}
	
	$woocommerce_urb_it = include(dirname(__FILE__) . '/includes/class-' . (is_admin() ? 'admin' : 'frontend') . '.php');
	
	require_once(dirname(__FILE__) . '/includes/class-coupon.php');
	require_once(dirname(__FILE__) . '/includes/class-shortcode.php');
	require_once(dirname(__FILE__) . '/includes/class-widget.php');