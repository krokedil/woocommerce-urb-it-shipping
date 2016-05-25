<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	if(!class_exists('WC_Urb_It_Specific_Time')) {
		class WC_Urb_It_Specific_Time extends WC_Shipping_Method {
			const LANG = WooCommerce_Urb_It::LANG;
			
			private $plugin;
			
			
			public function __construct() {
				$this->id = 'urb_it_specific_time';
				$this->method_title = __('urb-it specific time', self::LANG);
				$this->method_description = __('urb-it specific time allows deliveries at a specific time.', self::LANG);
				$this->init();
			}
			
			
			public function init() {
				// Load the settings API
				$this->init_form_fields();
				$this->init_settings();
				
				$this->plugin = WooCommerce_Urb_It::instance();
				
				// Define user set variables
				$this->enabled = $this->get_option('enabled');
				$this->title = $this->get_option('title');
				$this->type = $this->get_option('type');
				$this->fee = floatval($this->get_option('fee'));
	
				// Save settings in admin if you have any defined
				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}
			
			
			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' 		=> __('Enable/Disable', self::LANG),
						'type' 			=> 'checkbox',
						'label' 		=> __('Enable urb-it specific time', self::LANG),
						'default' 		=> 'yes'
					),
					'title' => array(
						'title' 		=> __('Method Title', self::LANG),
						'type' 			=> 'text',
						'description' 	=> __( 'This controls the title which the user sees during checkout.', self::LANG),
						'default'		=> $this->method_title,
						'desc_tip'		=> true
					),
					'type' => array(
						'title'       => __('Fee Type', self::LANG),
						'type'        => 'select',
						'description' => __('How to calculate delivery charges', self::LANG),
						'default'     => 'fixed',
						'options'     => array(
							'fixed'       => __('Fixed amount', self::LANG),
							'percent'     => __('Percentage of cart total', self::LANG),
							'product'     => __('Fixed amount per product', self::LANG),
						),
						'desc_tip'    => true,
					),
					'fee' => array(
						'title' 		=> __('Price', self::LANG),
						'type' 			=> 'price',
						'description' => __('What fee do you want to charge for local delivery, disregarded if you choose free. Leave blank to disable.', self::LANG),
						'default'		=> 0,
						'desc_tip'		=> true
					)
				);
			}
			
			
			public function calculate_shipping($package) {
				$shipping_total = 0;
				$fee = $this->fee;
		
				if($this->type =='fixed') {
					$shipping_total 	= $this->fee;
				}
				elseif($this->type == 'percent') {
					$shipping_total = $package['contents_cost'] * ($this->fee / 100);
				}
				elseif($this->type == 'product') {
					foreach($package['contents'] as $item_id => $values) {
						$_product = $values['data'];
		
						if($values['quantity'] > 0 && $_product->needs_shipping()) $shipping_total += $this->fee * $values['quantity'];
					}
				}
		
				$rate = array(
					'id'    => $this->id,
					'label' => $this->title,
					'cost'  => apply_filters('woocommerce_urb_it_shipping_cost', $shipping_total, $this)
				);
		
				$this->add_rate($rate);
			}
			
			
			public function is_available($package) {
				$optional_postcode = apply_filters('woocommerce_urb_it_optional_postcode', empty($package['destination']['postcode']), $package, $this);
				
				if($this->enabled != 'yes') return false;
				
				// Check the weight of the order
				if(!$this->plugin->validate->cart_weight()) return false;
				
				// Check the volume of the order
				if(!$this->plugin->validate->cart_volume()) return false;
				
				$is_available = $optional_postcode ? true : $this->plugin->validate->postcode($package['destination']['postcode']);
				
				return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package);
			}
		}
	}
?>