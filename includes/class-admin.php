<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Admin extends WooCommerce_Urb_It {
		public function __construct() {
			parent::__construct();
			
			// Create plugin settings menu
			add_action('admin_menu', array(__CLASS__, 'create_menu'));
			
			// Custom product field: Bulky product
			add_action('woocommerce_product_options_dimensions', array(__CLASS__, 'inform_limits'));
			add_action('woocommerce_product_options_dimensions', array(__CLASS__, 'bulky_product_field'));
			add_action('woocommerce_process_product_meta', array(__CLASS__, 'bulky_product_field_save'));
			
			// Notice admin if any required settings or functions are missing
			add_action('admin_notices', array(__CLASS__, 'check_requirements'));
			
			add_action('admin_head', array(__CLASS__, 'order_status_icon'));
			
			require_once($this->path . 'includes/class-admin-settings.php');
		}
		
		
		public function create_menu() {
			add_submenu_page('woocommerce', __('Urb-it', parent::LANG), __('Urb-it', parent::LANG), 'manage_woocommerce', 'wc-urb-it-settings', array(__CLASS__, 'page_settings'));
		}
		
		
		public function check_requirements($foobar) {
			$screen = get_current_screen();
			
			if($screen->id == 'woocommerce_page_wc-urb-it-settings') return;
			
			if(!function_exists('curl_version')) {
				?>
					<div class="error">
						<p><?php _e('Urb-it requires cURL to work. Please contact your hosting provider or urb-it for help.', self::LANG); ?></p>
					</div>
				<?php
				return;
			}
			
			$credentials = get_option(self::OPTION_CREDENTIALS, array());
			
			if(!empty($credentials['consumer_key']) && !empty($credentials['consumer_secret']) && !empty($credentials['token']) && !empty($credentials['location_id'])) return;
			?>
				<div class="update-nag">
					<p><?php echo sprintf(__('You have to <a href="%s">set up some settings</a> before your customers can use urb-it\'s shipping methods.', self::LANG), admin_url('admin.php?page=wc-urb-it-settings')); ?></p>
				</div>
			<?php
		}
		
		
		public function page_settings() {
			if(!isset($_GET['urb-it-log'])) {
				self::save_settings();
				
				$general = get_option(self::OPTION_GENERAL, array());
				$credentials = get_option(self::OPTION_CREDENTIALS, array());
				$callback_url = plugin_dir_url(self::$file) . 'callback.php';
				
				$weekdays = array(
					1 => __('Monday', self::LANG),
					2 => __('Tuesday', self::LANG),
					3 => __('Wednesday', self::LANG),
					4 => __('Thursday', self::LANG),
					5 => __('Friday', self::LANG),
					6 => __('Saturday', self::LANG),
					7 => __('Sunday', self::LANG)
				);
				
				require(self::$path_templates . 'admin/plugin-settings.php');
			}
			else {
				if(isset($_POST['clear-log'])) {
					$logger = new WC_Logger();
					$logger->clear('urb-it');
				}
				
				$log = file_get_contents(wc_get_log_file_path('urb-it'));
				
				require(self::$path_templates . 'admin/plugin-log.php');
			}
		}
		
		
		public function save_settings() {
			if(!isset($_POST['submit']) || !current_user_can('manage_woocommerce')) return;
			
			if(isset($_POST['general'])) {
				update_option(self::OPTION_GENERAL, array_map('esc_attr', $_POST['general']));
			}
			
			if(isset($_POST['credentials'])) {
				update_option(self::OPTION_CREDENTIALS, array_map('trim', $_POST['credentials']));
			}
			
			add_settings_error('wc-urb-it-settings', 'settings_updated', __('Changes saved successfully.', self::LANG), 'updated');
		}
		
		
		public function bulky_product_field() {
			woocommerce_wp_checkbox(array(
				'id'						=> '_urb_it_bulky',
				'label'					=> __('Bulky Product', self::LANG),
				'description'		=> __('Please check this if the product is too bulky for urb-it', self::LANG)
			));
		}
		
		
		public function bulky_product_field_save($post_id) {
			$bulky = sanitize_key($_POST['_urb_it_bulky']);
			
			if(!empty($bulky)) update_post_meta($post_id, '_urb_it_bulky', $bulky);
			else delete_post_meta($post_id, '_urb_it_bulky');
		}
		
		
		public function inform_limits() {
			?><p class="form-field"><?php echo sprintf(__('Note: If the product exceeds %d kilos and/or %d liters, it can\'t be delivered by urb-it.', self::LANG), self::ORDER_MAX_WEIGHT, self::ORDER_MAX_VOLUME / 1000); ?></p><?php
		}
		
		
		// Order status: Add icon
		public function order_status_icon() {
			?>
				<style>
					.widefat .column-order_status mark.picked-up {
						background: url('<?php echo plugin_dir_url(__FILE__); ?>assets/img/wc-picked-up.png') no-repeat center center;
					}
				</style>
			<?php
		}
	}
	
	return new WooCommerce_Urb_It_Admin;