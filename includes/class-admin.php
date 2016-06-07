<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Admin extends WooCommerce_Urb_It {
		public function __construct() {
			parent::__construct();
			
			// Create plugin settings menu
			add_action('admin_menu', array($this, 'add_menu_item'));
			
			// Custom product field: Bulky product
			add_action('woocommerce_product_options_dimensions', array($this, 'inform_limits'));
			add_action('woocommerce_product_options_dimensions', array($this, 'bulky_product_field'));
			add_action('woocommerce_process_product_meta', array($this, 'bulky_product_field_save'));
			
			// Notice admin if any required settings or functions are missing
			add_action('admin_notices', array($this, 'notices'));
			
			add_action('admin_head', array($this, 'order_status_icon'));
			
			require_once($this->path . 'includes/class-admin-settings.php');
		}
		
		
		public function add_menu_item() {
			add_submenu_page('woocommerce', __('Urb-it', self::LANG), __('Urb-it', self::LANG), 'manage_woocommerce', $this->settings_url(false), '');
		}
		
		
		public function notices() {
			$screen = get_current_screen();
			
			if($screen->id !== 'woocommerce_page_wc-settings' || $_GET['tab'] !== 'urb_it') {
				if(!function_exists('curl_version')) {
					?>
						<div class="notice notice-error">
							<p><?php _e('Urb-it requires cURL to work. Please contact your hosting provider or urb-it for help.', self::LANG); ?></p>
						</div>
					<?php
					return;
				}
				
				$credentials = get_option(self::OPTION_CREDENTIALS, array());
				
				if(!$this->setting('store_key') || !$this->setting('shared_secret')) {
					?>
						<div class="notice notice-warning">
							<p><?php echo sprintf(__('You have to <a href="%s">set up some settings</a> before your customers can use urb-it\'s shipping methods.', self::LANG), $this->settings_url()); ?></p>
						</div>
					<?php
				}
			}
			
			if($this->setting('environment') === 'prod' && $this->setting('log') === 'everything') {
				?>
					<div class="notice notice-warning">
						<p><?php echo sprintf(__('You are in production environment and are currently logging everything, which isn\'t recommended. Please set the logging option to "%s" in <a href="%s">the settings</a>.', self::LANG), __('Only errors', self::LANG), $this->settings_url()); ?></p>
					</div>
				<?php
			}
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
		
		
		public function settings_url($absolute = true) {
			$path = 'admin.php?page=wc-settings&tab=urb_it';
			
			return $absolute ? admin_url($path) : $path;
		}
	}
	
	return new WooCommerce_Urb_It_Admin;