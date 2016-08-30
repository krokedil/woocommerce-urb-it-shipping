<form class="urb-it klarna-checkout-urb-it" <?php if(!$is_one_hour && !$is_specific_time): ?>style="display: none;"<?php endif; ?>>
	<input type="hidden" name="urb-it-street" value="<?php echo $shipping_street; ?>" />
	<input type="hidden" name="urb-it-postcode" value="<?php echo $shipping_postcode; ?>" data-valid="<?php echo $postcode_error ? 'false' : 'true'; ?>" />
	<input type="hidden" name="urb-it-city" value="<?php echo $shipping_city; ?>" />
	
	<div class="specific-time"<?php if(!$is_specific_time): ?> style="display: none;"<?php endif; ?>>
		<h4>När ska urb-it komma?</h4>
		<?php
			$this->template('checkout/field-delivery-time', array(
				'is_cart' => true,
				'selected_delivery_time' => $this->date(WC()->session->get('urb_it_delivery_time', $this->specific_time_offset())),
				'now' => $this->date('now'),
				'days' => $this->opening_hours->get()
			));
		?>
	</div>
	
	<div class="woocommerce-error delivery-time-error" style="display: none;"><?php _e('Please pick a valid delivery time.', self::LANG); ?></div>
	
	<div class="urb-it-shipping-address"<?php if(empty($shipping_street) || empty($shipping_postcode) || empty($shipping_city)): ?> style="display: none;"<?php endif; ?>>
		<h4>urb-it överlämnar din order till:</h4>
		<p class="urb-it-address"><?php echo $shipping_street . '<br />' . $shipping_postcode . ' ' . $shipping_city; ?></p>
		<p><input class="button urb-it-change" type="button" value="Ändra" /></p>
	</div>
	
	<script>jQuery('#urb_it_date').change();</script>
</form>