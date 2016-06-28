<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Admin_IMS extends WooCommerce_Urb_It {
		protected $pickup_locations = array();
		
		
		public function __construct() {
			add_filter('woocommerce_get_sections_urb_it', array($this, 'add_section'));
			add_filter('woocommerce_get_settings_urb_it', array($this, 'add_settings'), 10, 2);
			
			add_action('woocommerce_sections_urb_it', array($this, 'fetch_pickup_locations'), 5);
			add_action('update_option_' . self::SETTINGS_PREFIX . 'ims_sync', array($this, 'reset_cursor'), 10, 2);
		}
		
		
		public function add_section($sections = array()) {
			$sections['ims'] = __('IMS', self::LANG);
			
			return $sections;
		}
		
		
		public function fetch_pickup_locations() {
			global $current_section;
			
			if($current_section !== 'ims') return;
			
			try {
				$ims = include($this->path . 'includes/class-ims.php');
				
				$this->pickup_locations = $ims->get_pickup_locations(true);
				?>
					<div class="notice notice-success is-dismissible">
						<p><?php echo sprintf(__('Fetched %d pickup locations successfully.', self::LANG), sizeof($this->pickup_locations)); ?></p>
					</div>
				<?php
			}
			catch(Exception $e) {
				?>
					<div class="notice notice-error is-dismissible">
						<p><?php echo sprintf(__('Failed to fetch pickup locations. Message: %s', self::LANG), $e->getMessage()); ?></p>
					</div>
				<?php
			}
		}
		
		
		public function add_settings($settings, $current_section) {
			if($current_section === 'ims') {
				$settings = array(
					'main_title' => array(
						'name'     => __('Inventory Management System', self::LANG),
						'desc'     => __('<strong>Warning:</strong> Synchronizing products will overwrite the matching WooCommerce products.', self::LANG),
						'type'     => 'title'
					),
					'ims_active' => array(
						'name' => __('Activate', self::LANG),
						'desc' => __('Activate urb-it IMS', self::LANG),
						'type' => 'checkbox',
						'default' => '',
						'id'   => self::SETTINGS_PREFIX . 'ims_active'
					),
					'ims_sync' => array(
						'name' => __('Synchronize', self::LANG),
						'desc' => __('Synchronization will be done on matching SKUs.<br />Warning: If whole products are synchronized, matching WooCommerce products are overwritten by the IMS data.', self::LANG),
						'desc_tip' => true,
						'type' => 'radio',
						'options' => array(
							'stocks' => __('Stock Levels', self::LANG),
							'products' => __('Products', self::LANG) . ' (' . __('please read the warning above', self::LANG) . ')'
						),
						'default' => 'stock',
						'id'   => self::SETTINGS_PREFIX . 'ims_sync'
					),
					'ims_pickup_location' => array(
						'name' => __('Pickup Location', self::LANG),
						'type' => 'select',
						'options' => array(
							0 => __('All', self::LANG)
						),
						'default' => 0,
						'id'   => self::SETTINGS_PREFIX . 'ims_pickup_location'
					),
					'ims_trash' => array(
						'name' => __('Trash', self::LANG),
						'desc' => __('Put deleted products in trash (instead of deleting them permanently)', self::LANG),
						'type' => 'checkbox',
						'default' => '',
						'id'   => self::SETTINGS_PREFIX . 'ims_trash'
					),
					'section_end' => array(
						'type' => 'sectionend'
					)
				);
				
				if(sizeof($this->pickup_locations)) {
					foreach($this->pickup_locations as $location) {
						$settings['ims_pickup_location']['options'][$location->id] = $location->name . ' (' . $location->address . ')';
					}
				}
			}
			
			return $settings;
		}
		
		
		public function reset_cursor($old_value, $value) {
			if($value === $old_value) return;
			
			update_option(self::SETTINGS_PREFIX . 'ims_cursor', false);
		}
	}
	
	return new WooCommerce_Urb_It_Admin_IMS;