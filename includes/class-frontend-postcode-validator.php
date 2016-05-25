<?php
	// Exit if accessed directly
	if(!defined('ABSPATH')) exit;
	
	class WooCommerce_Urb_It_Frontend_Postcode_Validator extends WooCommerce_Urb_It_Frontend {
		private $added_assets = false;
		
		
		public function __construct() {
			add_action('wc_ajax_urb_it_validate_postcode', array($this, 'ajax'));
			add_action('woocommerce_single_product_summary', array($this, 'product_page'), 35);
		}
		
		
		// Postcode validator: Ajax
		public function ajax() {
			echo (isset($_GET['postcode']) && $this->validate->postcode($_GET['postcode'])) ? '1' : '0';
			exit;
		}
		
		
		// Postcode validator: Assets
		public function add_assets() {
			if($this->added_assets) return;
			
			$this->added_assets = true;
			?>
				<style>
					.urb-it-postcode-validator {
						background-image: url('<?php echo $this->url; ?>assets/img/urb-it-logotype.png');
						background-image: linear-gradient(transparent, transparent), url('<?php echo $this->url; ?>assets/img/urb-it-logotype.svg');
					}
					<?php include($this->path . 'assets/css/postcode-validator.css'); ?>
				</style>
				<script>
					<?php include($this->path . 'assets/js/postcode-validator.js'); ?>
				</script>
			<?php
		}
		
		
		// Postcode validator: Product page
		public function product_page() {
			$general = get_option(self::OPTION_GENERAL, array());
			
			if(!$general || !$general['postcode-validator-product-page']) return;
			
			global $product, $woocommerce;
			
			if(!$product->is_in_stock() || $product->urb_it_bulky || !$this->validate->product_weight($product) || !$this->validate->product_volume($product)) return;
			
			$postcode = $woocommerce->customer->get_shipping_postcode();
			if(!$postcode) $postcode = $woocommerce->customer->get_postcode();
			
			if($postcode) $last_status = $woocommerce->session->get('urb_it_postcode_result');
			
			include($this->path . 'templates/postcode-validator/form.php');
			
			add_action('wp_footer', array($this, 'add_assets'));
		}
	}
	
	return new WooCommerce_Urb_It_Frontend_Postcode_Validator;