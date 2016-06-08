<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_it_Opening_Hours extends WooCommerce_Urb_It {
		public function __construct() {
			// Turn off caching of shipping method
			add_filter('option_woocommerce_status_options', array($this, 'turn_off_shipping_cache'));
		}
		
		
		// Get opening hours from the Retailer portal
		public function get() {
			$today = $this->date('today');
			$max_time = clone $today;
			$max_time->modify(self::SPECIFIC_TIME_RANGE);
			
			$days = apply_filters('woocommerce_urb_it_debug', false) ? false : get_transient('woocommerce_urb_it_delivery_days');
			
			if($days === false) {
				$this->log('Fetching opening hours from API...');
				
				try {
					$opening_hours = $this->urbit->GetOpeningHours($today->format('Y-m-d'), $max_time->format('Y-m-d'));
					
					if(!$opening_hours) {
						throw new Exception('Empty result');
					}
					
					$this->log('API result:', $opening_hours);
				}
				catch(Exception $e) {
					$this->error('Error while fetching opening hours: ' . $e->getMessage());
				}
				
				$days = array();
				
				foreach($opening_hours as $day) {
					if($day->closed) continue;
					
					$hours = (object)array(
						'open' => $this->date($day->from),
						'close' => $this->date($day->to)
					);
					
					$days[] = $hours;
				}
				
				set_transient('woocommerce_urb_it_delivery_days', $days, self::TRANSIENT_TTL);
			}
			else {
				$this->log('Fetched opening hours from cache.');
			}
			
			return $days;
		}
		
		
		// Turn off shipping cache, otherwise there might be problems with the opening hours
		public function turn_off_shipping_cache($status_options = array()) {
			$status_options['shipping_debug_mode'] = '1';
			
			return $status_options;
		}
	}
	
	return new WooCommerce_Urb_it_Opening_Hours;