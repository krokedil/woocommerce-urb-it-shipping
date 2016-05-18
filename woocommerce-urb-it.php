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
		
		const DATE_FORMAT = DateTime::RFC3339;
		
		private static $_instance = null;
		private $timezone = null;
		private $log = null;

		protected $update_checker;
		
		public $path;
		public $url;
		public $order;
		public $validate;
		
		
		// Singelton
		public static function instance() {
			if(self::$_instance === null) {
				self::$_instance = include(dirname(__FILE__) . '/includes/class-' . (is_admin() ? 'admin' : 'frontend') . '.php');
			}
			
			return self::$_instance;
		}
		
		
		public function __construct() {
			// Define paths
			$this->path = dirname(__FILE__) . '/';
			$this->url = plugin_dir_url(__FILE__);
			
			// Installation & plugin removal
			register_activation_hook(__FILE__, array($this, 'install'));
			register_uninstall_hook(__FILE__, array($this, 'uninstall'));
			
			// Update checker
			require_once($this->path . 'plugin-update-checker/plugin-update-checker.php');
			$this->update_checker = PucFactory::buildUpdateChecker(self::UPDATE_URL, __FILE__);
			
			// Load text domain
			add_action('plugins_loaded', array($this, 'load_textdomain'));
			
			// Add shipping methods
			add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
			add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
			
			// Shipping calculator
			add_action('woocommerce_after_shipping_calculator', array($this, 'shipping_calculator'));
			add_action('woocommerce_after_cart', array($this, 'checkout_assets'));
			
			// Notices
			add_action('woocommerce_add_to_cart', array($this, 'notice_added_product'), 10, 6);
			add_action('woocommerce_single_product_summary', array($this, 'notice_product_page'), 35);
			
			// Widget
			add_action('widgets_init', array($this, 'register_widget'));
			
			if(is_null($this->order)) $this->order = include($this->path . 'includes/class-order.php');
			if(is_null($this->validate)) $this->validate = include($this->path . 'includes/class-validate.php');
			if(is_null($this->opening_hours)) $this->opening_hours = include($this->path . 'includes/class-opening-hours.php');
			if(is_null($this->coupon)) $this->coupon = include($this->path . 'includes/class-coupon.php');
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
		
		
		public function one_hour_offset() {
			return apply_filters('woocommerce_urb_it_one_hour_offset', '+1 hour');
		}
		
		
		public function specific_time_offset() {
			return apply_filters('woocommerce_urb_it_specific_time_offset', '+1 hour 15 min');
		}
		
		
		// Save the plugin version on activation, if it doesn't exist
		public function install() {
			add_option(self::OPTION_VERSION, self::VERSION);
		}
		
		
		// Delete all options when the plugin is removed
		public function uninstall() {
			delete_option(self::OPTION_VERSION);
		}
		
		
		// Add multilingual support
		public function load_textdomain() {
			load_plugin_textdomain(self::LANG, false, dirname(plugin_basename(__FILE__)) . '/languages'); 
		}
		
		
		// Include shipping classes
		public function shipping_init() {
			include($this->path . 'includes/class-shipping-one-hour.php');
			include($this->path . 'includes/class-shipping-specific-time.php');
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
			
			if($this->validate->product_volume($product) && $this->validate->product_weight($product)) return;
			
			?><p style="color: #ff3d4b;"><?php _e('This product can\'t be delivered by urb-it.', self::LANG); ?></p><?php
		}
		
		
		// User notice: Added product
		public function notice_added_product($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['notice-added-product']) return;
			
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
			
			self::checkout_fields(true);
			
			?><p><input class="button" type="submit" name="calc_shipping" value="<?php _e('Save', self::LANG); ?>" /></p><?php
			?></form><?php
		}
		
		
		// Create a DateTime object with the correct timezone
		public function create_datetime($string) {
			if($this->timezone === null) {
				$timezone_string = get_option('timezone_string');
				
				$this->timezone = new DateTimeZone($timezone_string ? $timezone_string : 'Europe/Stockholm');
			}
			
			return new DateTime($string, $this->timezone);
		}
		
		
		public function date($string) {
			if($this->timezone === null) {
				$timezone_string = get_option('timezone_string');
				
				if(!$timezone_string) {
					$gmt_offset = get_option('gmt_offset');
					
					if(!is_numeric($gmt_offset)) $gmt_offset = 0;
					
					$timezone_string = 'Etc/GMT' . ($gmt_offset >= 0 ? '+' : '') . (string)$gmt_offset;
				}
				
				$this->timezone = new DateTimeZone($timezone_string);
			}
			
			return new DateTime($string, $this->timezone);
		}
		
		
		// Include template file
		public function template($path, $vars = array()) {
			extract($vars);
			include($this->path . 'templates/' . $path . '.php');
		}
		
		
		// Sanitize phone number to the format "0701234567"
		public function sanitize_phone($phone) {
			$phone = preg_replace(array('/\D/', '/^(00)?460?/'), array('', '0'), $phone);
			
			if(strncmp($phone, '07', 2) !== 0 || strlen($phone) !== 10) return false;
			
			return $phone;
		}
		
		
		// Error log
		public function log($input, $debug_only = false) {
			if($this->setting('log') !== 'everything') return;
			
			$this->error($input);
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
	
	WooCommerce_Urb_It::instance();
	
	
	require_once(dirname(__FILE__) . '/includes/class-shortcode.php');
	require_once(dirname(__FILE__) . '/includes/class-widget.php');