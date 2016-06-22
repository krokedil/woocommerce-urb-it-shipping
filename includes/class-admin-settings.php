<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Admin_Settings extends WC_Settings_Page {
		const LANG = WooCommerce_Urb_It::LANG;
		const SETTINGS_PREFIX = WooCommerce_Urb_It::SETTINGS_PREFIX;
		
		private $plugin;
		
		
		public function __construct() {
			$this->id = 'urb_it';
			$this->label = __('urb-it', self::LANG);
			$this->plugin = WooCommerce_Urb_It::instance();
			
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
			add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
			add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		}
		
		
		public function get_sections() {
			$sections = array(
				''         => __('General Settings', self::LANG),
				'integration'     => __('Integration', self::LANG)
			);
	
			return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
		}
		
		
		public function save() {
			global $current_section;
	
			$settings = $this->get_settings($current_section);
			WC_Admin_Settings::save_fields($settings);
		}
		
		
		public function get_settings($current_section = '') {
			
			// Integration
			if($current_section === 'integration') {
				$settings = array(
					'main_title' => array(
						'name'     => __('Integration', self::LANG),
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
				) + $this->get_api_settings('prod') + $this->get_api_settings('stage');
			}
			
			// General
			else {
				$settings = array(
					'main_title' => array(
						'name'     => __('General Settings', self::LANG),
						'type'     => 'title'
					),
					'notice_product_page' => array(
						'name' => __('Notice: Undeliverable Product', self::LANG),
						'desc' => __('Let visitors know on the product page if the product can\'t be delivered by urb-it.', self::LANG),
						'type' => 'checkbox',
						'default' => 'yes',
						'id'   => self::SETTINGS_PREFIX . 'notice_product_page'
					),
					'notice_added_product' => array(
						'name' => __('Notice: Exceeded Cart Limit', self::LANG),
						'desc' => __('Tell the visitor when urb-it\'s deliver limits get exceeded.', self::LANG),
						'type' => 'checkbox',
						'default' => 'yes',
						'id'   => self::SETTINGS_PREFIX . 'notice_added_product'
					),
					'notice_checkout' => array(
						'name' => __('Notice: Undeliverable Order', self::LANG),
						'desc' => __('Explain in checkout and cart why an order can\'t be delivered by urb-it.', self::LANG),
						'type' => 'checkbox',
						'default' => 'yes',
						'id'   => self::SETTINGS_PREFIX . 'notice_checkout'
					),
					'postcode_validator_product_page' => array(
						'name' => __('Postcode Validator', self::LANG),
						'desc' => __('Add a postcode validator on the product page.', self::LANG),
						'type' => 'checkbox',
						'default' => 'yes',
						'id'   => self::SETTINGS_PREFIX . 'postcode_validator_product_page'
					),
					'section_end' => array(
						'type' => 'sectionend'
					)
				);
			}
			
			return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
		}
		
		
		protected function get_api_settings($environment = 'stage') {
			$settings = array(
				$environment . '_title' => array(
					'name'     => sprintf(__('%s Credentials', self::LANG), ($environment === 'prod' ? 'Production' : ucfirst($environment))) . (($this->plugin->setting('environment') === $environment) ? (' (' . __('active', self::LANG) . ')') : ''),
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
			
			return apply_filters('woocommerce_urb_it_api_settings', $settings, $environment);
		}
		
		
		public function output() {
			global $current_section;
	
			$settings = $this->get_settings($current_section);
			WC_Admin_Settings::output_fields($settings);
		}
	}
	
	new WooCommerce_Urb_It_Admin_Settings;