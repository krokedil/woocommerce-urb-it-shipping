<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	if(!class_exists('WC_Urb_It_One_Hour')) {
		class WC_Urb_It_One_Hour extends WC_Shipping_Method {
			private $lang;
			
			
			public function __construct() {
				$this->lang = WooCommerce_Urb_It::LANG;
				
				$this->id = 'urb_it_one_hour';
				$this->method_title = __('urb-it 1 hour', $this->lang);
				$this->method_description = __('urb-it 1 hour allows deliveries within an hour.', $this->lang);
				$this->init();
			}
			
			
			public function init() {
				// Load the settings API
				$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
				$this->init_settings(); // This is part of the settings API. Loads settings you previously init.
				
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
						'title' 		=> __('Enable/Disable', $this->lang),
						'type' 			=> 'checkbox',
						'label' 		=> __('Enable urb-it 1 hour', $this->lang),
						'default' 		=> 'yes'
					),
					'title' => array(
						'title' 		=> __('Method Title', $this->lang),
						'type' 			=> 'text',
						'description' 	=> __( 'This controls the title which the user sees during checkout.', $this->lang),
						'default'		=> $this->method_title,
						'desc_tip'		=> true
					),
					'type' => array(
						'title'       => __('Fee Type', $this->lang),
						'type'        => 'select',
						'description' => __('How to calculate delivery charges', $this->lang),
						'default'     => 'fixed',
						'options'     => array(
							'fixed'       => __('Fixed amount', $this->lang),
							'percent'     => __('Percentage of cart total', $this->lang),
							'product'     => __('Fixed amount per product', $this->lang),
						),
						'desc_tip'    => true,
					),
					'fee' => array(
						'title' 		=> __('Price', $this->lang),
						'type' 			=> 'price',
						'description' => __('What fee do you want to charge for local delivery, disregarded if you choose free. Leave blank to disable.', $this->lang),
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
				if(!WooCommerce_Urb_It::validate_cart_weight()) return false;
				
				// Check the volume of the order
				if(!WooCommerce_Urb_It::validate_cart_volume()) return false;
				
				$delivery_time = WooCommerce_Urb_It::create_datetime('+1 hour');
				
				if(!WooCommerce_Urb_It::validate_opening_hours($delivery_time)) return false;
				
				$is_available = $optional_postcode ? true : WooCommerce_Urb_It::validate_postcode($package['destination']['postcode']);
				
				return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package);
			}
		}
	}
?>