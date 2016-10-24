<?php
	$min_delivery_time = $this->date($this->specific_time_offset());
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
						<option value="Day" disabled selected="selected">Day</option>
						<?php foreach($days as $day): ?>
							<?php
								if($min_delivery_time > $day->close) continue;

								$is_selected = ($day->open->format('Y-m-d') === $selected_delivery_time->format('Y-m-d'));
								$is_today = ($day->open->format('Y-m-d') === $now->format('Y-m-d'));

								if($is_today && $min_delivery_time > $day->open) {
									$day->open = $min_delivery_time;
								}
							?>
							<option value="<?php echo $day->open->format('Y-m-d'); ?>" data-open="<?php echo $day->open->format('H:i'); ?>" data-close="<?php echo $day->close->format('H:i'); ?>"<?php if($is_today): ?> data-today="true"<?php endif; ?>><?php echo ucfirst($is_today ? __('today', self::LANG) : date_i18n('l', $day->open->getTimestamp())); ?></option>
						<?php endforeach; ?>
					</select>
				</p><!-- #urb_it_date_field -->
			<?php endif; ?>

			<?php if(!$hide_time_field): ?>
				<p id="urb_it_time_field" class="form-row form-row-wide">
					<label for="urb_it_time"><?php _e('Time', self::LANG); ?> <span></span></label>

                    <!-- time dropdowns -->

                    <select id="urb_it_hour" name="urb_it_hour">

                        <option value="" disabled selected>Hour</option>

                        <?php foreach(range(0, 23, 1) as $hour): ?>

                            <option value="<?php echo $hour; ?>"><?php echo $hour; ?></option>

                        <?php endforeach; ?>

                    </select>

                    <select id="urb_it_minute" name="urb_it_minute">

                        <option value="" disabled selected>Minute</option>

                        <?php foreach(range(0, 45, 15) as $minute): ?>

                            <?php if($minute == 0) { $minute = '00'; } ?>

                            <option value="<?php echo $minute; ?>"><?php echo $minute; ?></option>

                        <?php endforeach; ?>

                    </select>

					<input id="urb_it_time" name="urb_it_time" type="time" value="<?php echo $selected_delivery_time->format('H:i'); ?>" placeholder="<?php _e('HH:MM', self::LANG); ?>" />
					<span class="error"><?php _e('Closed', self::LANG); ?></span>
				</p><!-- #urb_it_time_field -->
			<?php endif; ?>

<?php if(!$is_cart): ?>
		</td>
	</tr>
<?php endif; ?>
