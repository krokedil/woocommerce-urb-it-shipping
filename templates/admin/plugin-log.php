<div class="wrap">
	<h2>Urb-it log</h2>
	
	<form method="post" onsubmit="return confirm('All messages in the log will be removed. Proceed?');">
		<p>
			<a class="button button-primary" href="<?php echo admin_url('admin.php?page=wc-urb-it-settings'); ?>">Back to settings</a>
			<?php if($log): ?>
				<input type="hidden" name="clear-log" value="true" />
				<input class="button" type="submit" value="Clear log" />
			<?php endif; ?>
		</p>
	</form>
	
	<hr />
	
	<?php if($log): ?>
		<pre><?php echo $log; ?></pre>
	<?php else: ?>
		<p>Nothing to report.</p>
	<?php endif; ?>
</div>