<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Admin_Settings extends WooCommerce_Urb_It_Admin {
		public function __construct() {
			add_filter('woocommerce_settings_tabs_array', array($this, 'add_tab'), 50);
			add_action('woocommerce_settings_tabs_urb_it', array($this, 'tab_settings'));
			add_action('woocommerce_update_options_urb_it', array($this, 'save_settings'));
		}
		
		
		public function add_tab($tabs) {
			$tabs['urb_it'] = __('urb-it', self::LANG);
			
			return $tabs;
		}
		
		
		public function tab_settings() {
			woocommerce_admin_fields($this->get_technical_settings());
			woocommerce_admin_fields($this->get_api_settings('prod'));
			woocommerce_admin_fields($this->get_api_settings('stage'));
		}
		
		
		public function save_settings() {
			woocommerce_update_options($this->get_technical_settings());
			woocommerce_update_options($this->get_api_settings('prod'));
			woocommerce_update_options($this->get_api_settings('stage'));
		}
		

		protected function get_technical_settings() {
			$settings = array(
				'main_title' => array(
					'name'     => __('Technical Settings', self::LANG),
					'type'     => 'title'
				),
				'environment' => array(
					'name' => __('Environment', self::LANG),
					'type' => 'radio',
					'options' => array(
						'prod' => __('Production', self::LANG),
						'stage' => __('Stage', self::LANG)
					),
					'default' => 'stage',
					'id'   => self::SETTINGS_PREFIX . 'environment'
				),
				'log' => array(
					'name' => __('Log', self::LANG),
					'type' => 'radio',
					'options' => array(
						'errors' => __('Only errors', self::LANG),
						'everything' => __('Everything', self::LANG)
					),
					'default' => 'errors',
					'id'   => self::SETTINGS_PREFIX . 'log'
				),
				'section_end' => array(
					'type' => 'sectionend'
				)
			);
			
			return apply_filters('woocommerce_urb_it_technical_settings', $settings);
		}
		
		
		protected function get_api_settings($environment = 'stage') {
			$settings = array(
				$environment . '_title' => array(
					'name'     => sprintf(__('%s Credentials', self::LANG), ($environment === 'prod' ? 'Production' : ucfirst($environment))),
					'type'     => 'title'
				),
				$environment . '_store_key' => array(
					'name' => __('Store Key', self::LANG),
					'type' => 'text',
					'class' => 'large-text code',
					'id'   => self::SETTINGS_PREFIX . $environment . '_store_key'
				),
				$environment . '_shared_secret' => array(
					'name' => __('Shared Secret', self::LANG),
					'type' => 'text',
					'class' => 'large-text code',
					'id'   => self::SETTINGS_PREFIX . $environment . '_shared_secret'
				),
				$environment . '_pickup_location_id' => array(
					'name' => __('Pickup Location ID', self::LANG),
					'type' => 'text',
					'desc' => __('Leave empty to use the default ID.', self::LANG),
					'class' => 'large-text code',
					'id'   => self::SETTINGS_PREFIX . $environment . '_pickup_location_id'
				),
				$environment . '_section_end' => array(
					'type' => 'sectionend'
				)
			);
			
			return apply_filters('woocommerce_urb_it_settings', $settings, $environment);
		}
	}
	
	new WooCommerce_Urb_It_Admin_Settings;