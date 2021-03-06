<?php
	/**
	 * Plugin Name: WooCommerce Urb-it Shipping
	 * Plugin URI: http://urb-it.com/
	 * Description: Let your customers choose urb-it as shipping method.
	 * Version: 3.0.3
	 * Author: Webbmekanikern
	 * Author URI: http://www.webbmekanikern.se/
	 * Text Domain: woocommerce-urb-it
	 * Domain Path: /languages/
	 * License: GPL2
	 */
	
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It {
		const VERSION = '3.0.3';
		const LANG = 'woocommerce-urb-it';
		
		const COMPANY_URL = 'http://urb-it.com/';
		const UPDATE_URL = 'https://download.urb-it.com/woocommerce/woocommerce-urb-it/update.json';
		
		const ORDER_MAX_WEIGHT = 10; // kg
		const ORDER_MAX_VOLUME = 142000; // cm3 (1 liter = 1000 cm3)
		
		const OPTION_VERSION = 'wc-urb-it-version';
		const OPTION_GENERAL = 'wc-urb-it-general';
		const OPTION_CREDENTIALS = 'wc-urb-it-credentials';
		const OPTION_OPENING_HOURS = 'wc-urb-it-opening-hours';
		const OPTION_SPECIAL_OPENING_HOURS = 'wc-urb-it-special-opening-hours';
		
		const TRANSIENT_TTL = 60; // seconds
		const SPECIFIC_TIME_RANGE = '+4 days'; // Actually 3 days: today + 2 days forward
		
		const SETTINGS_PREFIX = 'urb_it_settings_';
		
		const DATE_FORMAT = DateTime::RFC3339;
		
		private static $_instance = null;
		private $timezone = null;
		private $log = null;
		private $initialized = false;
		
		protected static $_modules = array();
		protected $update_checker;
		protected $country_codes = array(
			'46', // Sweden
			'33'  // France
		);
		protected $mobile_prefixes = array(
			'07', // Generic
			'06'  // France
		);
		
		
		// Singelton
		public static function instance() {
			if(self::$_instance === null) {
				self::$_instance = include(dirname(__FILE__) . '/includes/class-' . (is_admin() ? 'admin' : 'frontend') . '.php');
				
				self::$_modules = array(
					'order' => include(dirname(__FILE__) . '/includes/class-order.php'),
					'validate' => include(dirname(__FILE__) . '/includes/class-validate.php'),
					'opening_hours' => include(dirname(__FILE__) . '/includes/class-opening-hours.php'),
					'coupon' => include(dirname(__FILE__) . '/includes/class-coupon.php')
				);
			}
			
			return self::$_instance;
		}
		
		
		public function __construct() {
			// Installation & plugin removal
			register_activation_hook(__FILE__, array(__CLASS__, 'install'));
			register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
			
			// Update checker
			require_once($this->path . 'includes/plugin-update-checker/plugin-update-checker.php');
			$this->update_checker = PucFactory::buildUpdateChecker(self::UPDATE_URL, __FILE__);
			
			// Load text domain
			add_action('plugins_loaded', array($this, 'load_textdomain'));
			
			// Add shipping methods
			add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
			add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
			
			// Add Klarna Checkout support
			add_action('plugins_loaded', array($this, 'klarna_checkout_support'));
			
			// Widget
			add_action('widgets_init', array($this, 'register_widget'));
		}
		
		
		public function __get($name) {
			if($name === 'path') {
				$this->{$name} = dirname(__FILE__) . '/';
			}
			elseif($name === 'url') {
				$this->{$name} = plugin_dir_url(__FILE__);
			}
			elseif($name === 'urbit') {
				try {
					if(!class_exists('UrbRequest')) {
						require(dirname(__FILE__) . '/includes/sdk/UrbRequest.php');
					}
					
					$this->{$name} = new UrbRequest($this->setting('store_key'), $this->setting('shared_secret'), ($this->setting('environment') === 'stage'));
				}
				catch(Exception $e) {
					$this->error($e->getMessage());
				}
			}
			elseif(isset(self::$_modules[$name])) {
				$this->{$name} = self::$_modules[$name];
			}
			
			return $this->{$name};
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
		
		
		public function specific_time_offset($with_margin = true) {
			return apply_filters('woocommerce_urb_it_specific_time_offset', ($with_margin ? '+1 hour 15 min' : '+1 hour 5 min'), $with_margin);
		}
		
		
		// Save the plugin version on activation, if it doesn't exist
		public static function install() {
			add_option(self::OPTION_VERSION, self::VERSION);
		}
		
		
		// Delete all options when the plugin is removed
		public static function uninstall() {
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
		
		
		// Klarna Checkout support
		public function klarna_checkout_support() {
			#echo 'WC_Gateway_Klarna_Checkout? ';
			#var_dump(class_exists('WC_Gateway_Klarna_Checkout'));
			
			self::$_modules['klarna_checkout'] = include(dirname(__FILE__) . '/includes/class-klarna-checkout.php');
		}
		
		
		public function register_widget() {
			register_widget('Urb_It_Postcode_Validator_Widget');
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
			$path = wc_locate_template($path . '.php', 'urb-it', $this->path . 'templates/');
			
			extract($vars);
			include($path);
		}
		
		
		public function format_price($price) {
			$decimal_separator  = wc_get_price_decimal_separator();
			$thousand_separator = wc_get_price_thousand_separator();
			$decimals           = wc_get_price_decimals();
			$price_format       = get_woocommerce_price_format();
			
			$price = apply_filters( 'formatted_woocommerce_price', number_format( $price, $decimals, $decimal_separator, $thousand_separator ), $price, $decimals, $decimal_separator, $thousand_separator );
			
			if(apply_filters('woocommerce_price_trim_zeros', false) && $decimals > 0) {
				$price = wc_trim_zeros($price);
			}
			
			return html_entity_decode(sprintf($price_format, get_woocommerce_currency_symbol(), $price));
		}
		
		
		public function add_asset($handle, $src, $depend = array()) {
			if(substr($src, -3) === '.js') {
				wp_enqueue_script($handle, $this->url . 'assets/js/' . $src, $depend, self::VERSION, true);
			}
			else {
				echo '<style>';
				
				include($this->path . 'assets/css/' . $src);
				
				echo '</style>';
			}
		}
		
		
		// Sanitize phone number to the format "0701234567"
		public function sanitize_phone($phone) {
			$phone = preg_replace(array('/\D/', '/^(00)?(' . implode('|', $this->country_codes) . ')0?/'), array('', '0'), $phone);
			
			if(!in_array(substr($phone, 0, 2), $this->mobile_prefixes) || strlen($phone) !== 10) return false;
			
			return $phone;
		}
		
		
		public function is_ajax() {
			return (defined('DOING_AJAX') && DOING_AJAX);
		}
		
		
		public function notify_urbit($message) {
			return wp_mail('support@urb-it.com', 'Problem at ' . site_url('/'), $message);
		}
		
		
		// Error log
		public function log() {
			if($this->setting('log') !== 'everything') return;
			
			$this->merge_to_log(func_get_args());
		}
		
		
		public function error() {
			$this->merge_to_log(func_get_args());
		}
		
		
		private function merge_to_log($args) {
			ob_start();
			
			foreach($args as $row) {
				if(is_string($row)) echo $row . ' ';
				else var_dump($row);
			}
			
			$this->write_to_log(ob_get_clean());
		}
		
		
		private function write_to_log($input) {
			if(!$this->log) {
				if(!class_exists('WC_Logger')) {
					return error_log($input);
				}
				
				$this->log = new WC_Logger();
			}
			
			$this->log->add('urb-it', $input);
		}
	}
	
	WooCommerce_Urb_It::instance();
	
	
	require_once(dirname(__FILE__) . '/includes/class-shortcode.php');
	require_once(dirname(__FILE__) . '/includes/class-widget.php');
