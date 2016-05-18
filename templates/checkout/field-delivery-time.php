<?php
	$min_delivery_time = $this->date('+1 hour 15 min');
?>

<?php if(!$is_cart): ?>
	<tr class="urb-it-delivery-time">
		<th><?php _e('Delivery time', self::LANG); ?></th>
		<td>
<?php endif; ?>
			
			<?php if(!$hide_date_field): ?>
				<p id="urb_it_date_field" class="form-row form-row-wide">
					<label for="urb_it_date"><?php _e('Day', self::LANG); ?></label>
					<select id="urb_it_date" name="urb_it_date">
						<?php foreach($days as $day): ?>
							<?php
								if($onehour > $day->close) continue;
								
								$is_selected = ($day->open->format('Y-m-d') === $selected_delivery_time->format('Y-m-d'));
								$is_today = ($day->open->format('Y-m-d') === $now->format('Y-m-d'));
								
								if($is_today && $min_delivery_time > $day->open) {
									$day->open = $min_delivery_time;
								}
							?>
							<option value="<?php echo $day->open->format('Y-m-d'); ?>"<?php if($is_selected): ?> selected="selected"<?php endif; ?> data-open="<?php echo $day->open->format('H:i'); ?>" data-close="<?php echo $day->close->format('H:i'); ?>"<?php if($is_today): ?> data-today="true"<?php endif; ?>><?php echo ucfirst($is_today ? __('today', self::LANG) : date_i18n('l', $day->open->getTimestamp())); ?></option>
						<?php endforeach; ?>
					</select>
				</p><!-- #urb_it_date_field -->
			<?php endif; ?>
			
			<?php if(!$hide_time_field): ?>
				<p id="urb_it_time_field" class="form-row form-row-wide">
					<label for="urb_it_time"><?php _e('Time', self::LANG); ?> <span></span></label>
					<input id="urb_it_time" name="urb_it_time" type="time" value="<?php echo $selected_delivery_time->format('H:i'); ?>" placeholder="<?php _e('HH:MM', self::LANG); ?>" />
					<span class="error"><?php _e('Closed', self::LANG); ?></span>
				</p><!-- #urb_it_time_field -->
			<?php endif; ?>
			
<?php if(!$is_cart): ?>
		</td>
	</tr>
<?php endif; ?>