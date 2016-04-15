<div class="wrap">
	<h2><?php _e('Settings for Urb-it', self::LANG); ?></h2>
	<?php settings_errors('wc-urb-it-settings'); ?>
	
	<form method="post">
		<?php submit_button(); ?>
		
		<hr />
		
		<h3><?php _e('API Credentials', self::LANG); ?></h3>
		<p><?php _e('These keys are available in the Retailer portal.', self::LANG); ?></p>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="consumer_key"><?php _e('Consumer key', self::LANG); ?></label></th>
					<td><input class="large-text code" id="consumer_key" name="credentials[consumer_key]" type="text" value="<?php echo $credentials['consumer_key']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="consumer_secret"><?php _e('Consumer secret', self::LANG); ?></label></th>
					<td><input class="large-text code" id="consumer_secret" name="credentials[consumer_secret]" type="text" value="<?php echo $credentials['consumer_secret']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="token"><?php _e('Token', self::LANG); ?></label></th>
					<td><input class="large-text code" id="token" name="credentials[token]" type="text" value="<?php echo $credentials['token']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="location_id"><?php _e('Pickup location ID', self::LANG); ?></label></th>
					<td><input class="large-text code" id="location_id" name="credentials[location_id]" type="text" value="<?php echo $credentials['location_id']; ?>" /></td>
				</tr>
				<?php if(is_ssl()): ?>
					<tr>
						<th scope="row"><label for="callback_url"><?php _e('Callback URL', self::LANG); ?></label></th>
						<td><input class="large-text code" id="callback_url" name="callback_url" type="text" value="<?php echo $callback_url; ?>" disabled="disabled" /><p class="description"><?php _e('Copy this URL and put it as callback URL in the Retailer portal.', self::LANG); ?></p></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php _e('Test environment', self::LANG); ?></th>
					<td>
						<label><input name="credentials[is_test]" type="checkbox" value="yes"<?php if($credentials['is_test']): ?> checked="checked"<?php endif; ?> /> <span><?php _e('Enable test mode', self::LANG); ?></span></label>
					</td>
				</tr>
			</tbody>
		</table>
		
		<hr />
		
		<h3><?php _e('General settings', self::LANG); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php _e('Notice on product page', self::LANG); ?></th>
					<td>
						<label><input name="general[notice-product-page]" type="checkbox" value="yes"<?php if($general['notice-product-page']): ?> checked="checked"<?php endif; ?> /> <span><?php _e('Let visitors know on the product page if the product can\'t be delivered by urb-it.', self::LANG); ?></span></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Notice after added product', self::LANG); ?></th>
					<td>
						<label><input name="general[notice-added-product]" type="checkbox" value="yes"<?php if($general['notice-added-product']): ?> checked="checked"<?php endif; ?> /> <span><?php _e('Tell the visitor when urb-it\'s deliver limits get exceeded.', self::LANG); ?></span></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Notice in checkout & cart', self::LANG); ?></th>
					<td>
						<label><input name="general[notice-checkout]" type="checkbox" value="yes"<?php if($general['notice-checkout']): ?> checked="checked"<?php endif; ?> /> <span><?php _e('Explain in checkout and cart why an order can\'t be delivered by urb-it.', self::LANG); ?></span></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Postcode validator on product page', self::LANG); ?></th>
					<td>
						<label><input name="general[postcode-validator-product-page]" type="checkbox" value="yes"<?php if($general['postcode-validator-product-page']): ?> checked="checked"<?php endif; ?> /> <span><?php _e('Add a postcode validator on the product page.', self::LANG); ?></span></label>
					</td>
				</tr>
			</tbody>
		</table>
		
		<hr />
		
		<h3><?php _e('Opening hours', self::LANG); ?></h3>
		<p><?php _e('The opening hours are from now on set in the Retailer portal.', self::LANG); ?></p>
		
		<?php submit_button(); ?>
	</form>
	
	<p>
		<a href="<?php echo admin_url('admin.php?page=wc-urb-it-settings&urb-it-log=true'); ?>"><?php _e('View log', self::LANG); ?></a> (<?php _e('only intended for urb-it developers', self::LANG); ?>)
	</p>
	
</div>