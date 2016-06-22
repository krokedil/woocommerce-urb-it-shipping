<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	if(!class_exists('WooCommerce_Urb_It_IMS')) {
		class WooCommerce_Urb_It_IMS {
			const PING_SLUG = 'urb-it-ims-ping';
			const DATE_FORMAT = DateTime::ISO8601;
			
			protected $plugin;
			
			
			public function __construct() {
				$this->plugin = WooCommerce_Urb_It::instance();
				
				add_action('wp_footer', array($this, 'test'));
				add_action('parse_request', array($this, 'callback_route'));
			}
			
			
			public function __get($name) {
				if($name === 'ims') {
					try {
						if(!class_exists('UrbRequest')) {
							require($this->plugin->path . '/includes/sdk/UrbRequest.php');
						}
						
						if(!class_exists('UrbRequestIMS')) {
							require($this->plugin->path . '/includes/sdk/UrbRequestIMS.php');
						}
						
						$this->{$name} = new UrbRequestIMS($this->plugin->setting('store_key'), $this->plugin->setting('shared_secret'), ($this->plugin->setting('environment') === 'stage'));
						
						return $this->{$name};
					}
					catch(Exception $e) {
						$this->plugin->error($e->getMessage());
					}
				}
			}
			
			
			public function callback_route($wp) {
				$uri = rtrim($_SERVER['REQUEST_URI'], '/');
				
				if(substr($uri, -1 * strlen(self::PING_SLUG)) !== self::PING_SLUG) return;
				
				$this->ping();
				exit;
			}
			
			
			protected function ping() {
				set_time_limit(0);
				
				if($this->plugin->setting('ims_sync') === 'stocks') {
					$this->sync_stocks();
				}
				elseif($this->plugin->setting('ims_sync') === 'products') {
					$this->sync_products();
				}
			}
			
			
			protected function sync_stocks() {
				global $wpdb;
				
				$now = $this->plugin->date('now');
				$cursor = $this->plugin->setting('ims_cursor') ? $this->plugin->date($this->plugin->setting('ims_cursor')) : false;
				
				$params = array();
				
				if($cursor) $params['changed_since'] = $cursor->format(self::DATE_FORMAT);
				if($this->plugin->setting('ims_pickup_location')) $params['pickup_location_id'] = (int)$this->plugin->setting('ims_pickup_location');
				
				try {
					$changes = $this->ims->GetStockLevels($params);
				}
				catch(UrbException $e) {
					$this->plugin->error('Error while fetching stock levels:', $e->getMessage(), $e->getParams());
				}
				
				$this->set_cursor($now);
				
				if(!$changes) {
					$this->plugin->log('No changes during stock level sync.');
					return;
				}
				
				$stocks = array();
				
				// Save stock levels as: SKU => stock
				foreach($changes as $row) {
					$stocks[$row->product_sku] = $row->stock[0]->quantity;
				}
				
				// Fetch product IDs by SKUs
				$query = '
					SELECT post_id AS id, meta_value AS sku
					FROM ' . $wpdb->postmeta . '
					WHERE meta_key = "_sku"
						AND meta_value IN ("' . implode('", "', array_keys($stocks)) . '")
				';
				
				$products = $wpdb->get_results($query);
				
				foreach($products as $product) {
					
					// Silly check - we really don't want to change stock levels for invalid SKUs
					if(!isset($stocks[$product->sku])) continue;
					
					$query = '
						UPDATE ' . $wpdb->postmeta . '
						SET meta_value = "' . (int)$stocks[$product->sku] . '"
						WHERE post_id = ' . (int)$product->id . '
							AND meta_key = "_stock"
						LIMIT 1
					';
					
					$updated = $wpdb->query($query);
					
					if(!$updated) {
						$this->plugin->log('Could not update stock to ' . $stocks[$product->sku] . ' for product #' . $product->id);
					}
				}
				
				echo 'Stock level sync done.';
			}
			
			
			protected function sync_products() {
				global $wpdb;
				
				$now = $this->plugin->date('now');
				$cursor = $this->plugin->setting('ims_cursor') ? $this->plugin->date($this->plugin->setting('ims_cursor')) : false;
				
				$params = array();
				
				if($cursor) $params['changed_since'] = $cursor->format(self::DATE_FORMAT);
				if($this->plugin->setting('ims_pickup_location')) $params['pickup_location_id'] = (int)$this->plugin->setting('ims_pickup_location');
				
				try {
					$products = $this->ims->GetProducts($params);
				}
				catch(UrbException $e) {
					$this->plugin->error('Error while fetching products:', $e->getMessage(), $e->getParams());
				}
				
				$this->set_cursor($now);
				
				if(!$products) {
					$this->plugin->log('No changes during product sync.');
					return;
				}
				
				$skus = array();
				$force_delete = ($this->plugin->setting('ims_trash') !== 'yes');
				
				// Save products SKUs
				foreach($products as $product) {
					$skus[] = $row->sku;
				}
				
				// Fetch product IDs by SKUs
				$query = '
					SELECT post_id AS id, meta_value AS sku
					FROM ' . $wpdb->postmeta . '
					WHERE meta_key = "_sku"
						AND meta_value IN ("' . implode('", "', $skus) . '")
				';
				
				unset($skus);
				
				$rows = $wpdb->get_results($query);
				$sku_to_id = array();
				
				foreach($rows as $row) {
					$sku_to_id[$row->sku] = (int)$row->id;
				}
				
				foreach($products as $product) {
					
					// Create product
					if(!isset($sku_to_id[$product->sku])) {
						// <- Continue here
					}
					else {
						$product_id = &$sku_to_id[$product->sku];
						
						// Delete product
						if($product->deleted_at) {
							wp_delete_post($product_id, $force_delete);
						}
						
						// Update product
						else {
							// <- Continue here
						}
					}
				}
				
				echo 'Product sync done.';
			}
			
			
			protected function set_cursor($date) {
				return update_option(WooCommerce_Urb_It::SETTINGS_PREFIX . 'ims_cursor', $date->format('Y-m-d H:i:s'));
			}
			
			
			public function test() {
				?>
					<pre>IMS active</pre>
					<pre><?php
						
						try {
							var_dump($this->ims->GetProducts());
						}
						catch(Exception $e) {
							var_dump($e->getMessage());
						}
						
					?></pre>
				<?php
			}
			
			
			public function get_pickup_locations($throw = false) {
				try {
					$pickup_locations = $this->ims->GetPickupLocations();
					
					$this->plugin->log('Fetched pickup locations:', $pickup_locations);
					
					return $pickup_locations;
				}
				catch(Exception $e) {
					$this->plugin->error($e->getMessage());
					
					if($throw) throw $e;
				}
			}
		}
	}
	
	return new WooCommerce_Urb_It_IMS;