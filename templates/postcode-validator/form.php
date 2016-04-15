<form method="get" class="urb-it-postcode-validator">
	<span class="title" data-success="<?php echo apply_filters('woocommerce_urb_it_postcode_success_msg', __('You can purchase with urb-it!', self::LANG)); ?>" data-failure="<?php echo apply_filters('woocommerce_urb_it_postcode_failure_msg', __('Right now you can\'t purchase with urb-it at this postcode.', self::LANG)); ?>"><?php echo apply_filters('woocommerce_urb_it_postcode_default_msg', __('Can you purchase with urb-it?', self::LANG));	?></span>
	<a class="show-field" style="display: none;"><?php _e('Check another postcode', self::LANG); ?></a>
	<div class="field">
		<input type="number" class="input-text" name="postcode" value="<?php echo $postcode; ?>" placeholder="<?php _e('Postcode', self::LANG); ?>" size="6" maxlength="6" />
		<input type="submit" class="button alt" value="<?php _e('Check', self::LANG); ?>" />
	</div>
</form>