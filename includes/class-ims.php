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
				
				$this->debug();
				return;
				
				if($this->plugin->setting('ims_sync') === 'stocks') {
					$this->sync_stocks();
				}
				elseif($this->plugin->setting('ims_sync') === 'products') {
					$this->sync_products();
				}
			}
			
			
			protected function debug() {
				var_dump(class_exists('WC_API_Products'));
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
				
				$this->clear_stock_cache();
				
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
						$this->create_product($product);
					}
					else {
						$product_id = &$sku_to_id[$product->sku];
						
						// Delete product
						if($product->deleted_at) {
							wp_delete_post($product_id, $force_delete);
						}
						
						// Update product
						else {
							$this->update_product($sku_to_id[$product->sku], $product);
						}
					}
				}
				
				$this->clear_stock_cache();
				
				echo 'Product sync done.';
			}
			
			
			protected function create_product($data) {
				try {
					$status = strtolower($data->status);
					
					$new_product = array(
						'post_title'   => wc_clean($data->name),
						'post_status'  => ($status === 'active' ? 'publish' : 'draft'),
						'post_type'    => 'product',
						'post_content' => $data->description,
						'post_author'  => 0,
						'post_date_gmt' => $data->created_at,
						'post_modified_gmt' => $data->updated_at
					);
					
					$new_product = apply_filters('woocommerce_urb_it_ims_create_product', $new_product, $data);
					
					// Attempts to create the new product
					$id = wp_insert_post($new_product, true);
					
					// Checks for an error in the product creation
					if(is_wp_error($id)) {
						throw new Exception($id->get_error_message());
					}
					
					// Set product type
					wp_set_object_terms($id, 'simple', 'product_type');
					
					$meta_data = array(
						'backorders' => 'no',
						'downloadable' => 'no',
						'featured' => 'no',
						'height' => '',
						'length' => '',
						'manage_stock' => 'yes',
						'price' => $data->price,
						'product_attributes' => array(),
						'product_image_gallery' => '',
						'product_version' => WC_VERSION,
						'purchase_note' => '',
						'regular_price' => $data->price,
						'sale_price' => '',
						'sale_price_dates_from' => '',
						'sale_price_dates_to' => '',
						'sku' => $data->sku,
						'sold_individually' => '',
						'stock' => (is_array($data->stock) && isset($data->stock[0])) ? $data->stock[0]->quantity : 0,
						'tax_class' => '',
						'tax_status' => 'taxable',
						'thumbnail_id' => '',
						'virtual' => 'no',
						'visibility' => 'visible',
						'weight' => '',
						'width' => '',
						'urbit_ims_product_id' => $data->id,
						'urbit_ims_store_id' => $data->store_id
					);
					
					$meta_data['stock_status'] = $meta_data['stock'] ? 'instock' : 'outofstock';
					
					$this->update_media($data->media, $id);
					
					// Clear product cache
					wc_delete_product_transients($id);
					
					$this->plugin->log('Created product #' . $id);
				}
				catch(Exception $e) {
					$this->plugin->error('IMS: Error while creating product: ' . $e->getMessage());
				}
			}
			
			
			protected function update_product($product_id, $data) {
				try {
					$status = strtolower($data->status);
					
					$update_product = array(
						'ID' => $product_id,
						'post_title'   => wc_clean($data->name),
						'post_status'  => ($status === 'active' ? 'publish' : 'draft'),
						'post_content' => $data->description,
						'post_modified_gmt' => $data->updated_at
					);
					
					$update_product = apply_filters('woocommerce_urb_it_ims_update_product', $update_product, $data);
					
					// Attempts to create the new product
					$id = wp_insert_post($update_product, true);
					
					// Checks for an error in the product creation
					if(is_wp_error($id)) {
						throw new Exception($id->get_error_message());
					}
					
					// Set product type
					wp_set_object_terms($id, 'simple', 'product_type');
					
					$meta_data = array(
						'price' => $data->price,
						'product_version' => WC_VERSION,
						'regular_price' => $data->price,
						'sku' => $data->sku,
						'stock' => (is_array($data->stock) && isset($data->stock[0])) ? $data->stock[0]->quantity : 0,
						'urbit_ims_product_id' => $data->id,
						'urbit_ims_store_id' => $data->store_id
					);
					
					$meta_data['stock_status'] = $meta_data['stock'] ? 'instock' : 'outofstock';
					
					$this->update_media($data->media, $id);
					
					// Clear product cache
					wc_delete_product_transients($id);
					wp_cache_delete($id, 'post_meta');
					
					$this->plugin->log('Updated product #' . $id);
				}
				catch(Exception $e) {
					$this->plugin->error('IMS: Error while updating product: ' . $e->getMessage());
				}
			}
			
			
			protected function save_product_meta($product_id = 0, $meta_data = array()) {
				$meta_data = apply_filters('woocommerce_urb_it_ims_save_product_meta_data', $meta_data, $product_id);
				
				foreach($meta_data as $meta_key => $meta_value) {
					update_post_meta($product_id, '_' . $meta_key, $meta_value);
				}
			}
			
			
			protected function update_media($media = array(), $product_id = 0) {
				global $wpdb;
				
				$existing_image_ids = get_post_meta($product_id, '_product_image_gallery', true);
				
				if($existing_image_ids) {
					$existing_image_ids = array_map('absint', explode(',', $existing_image_ids));
					
					$query = '
						SELECT post_id, meta_value
						FROM ' . $wpdb->postmeta . '
						WHERE post_id IN (' . implode(', ', $existing_image_ids) . ')
							AND meta_key = "_urbit_ims_media_id"
					';
					
					$results = $wpdb->get_results($query);
					
					foreach($results as $row) {
						
					}
				}
				
				
			}
			
			
			protected function clear_stock_cache() {
				delete_transient('wc_low_stock_count');
				delete_transient('wc_outofstock_count');
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