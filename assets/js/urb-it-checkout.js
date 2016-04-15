jQuery(function($) {
	var remember_message = '';
	
	$(document).on('change', '#urb_it_date', function() {
		set_time_field();
	}).ready(function() {
		set_time_field();
	}).ajaxComplete(function(event, xhr, ajaxOpts) {
		if(ajaxOpts.url.indexOf('update_order_review') === -1 && ajaxOpts.url.indexOf('urb_it') === -1) return;
		
		set_time_field();
		
		if(remember_message) $('#urb_it_message').val(remember_message);
	}).on('change', '#urb_it_message', function() {
		remember_message = $(this).val();
	});
	
	function set_time_field() {
		var date = $('#urb_it_date'),
				time = $('#urb_it_time');
		
		if(!date.length || !time.length) return;
		
		var option = date.find(':selected'),
				time_open = option.data('open'),
				time_close = option.data('close'),
				is_today = !!option.data('today'),
				now = new Date(),
				time_now = ('0' + (now.getHours() + 1)).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2),
				date_now = now.getFullYear() + '-' + now.getMonth() + '-' + now.getDay(),
				speed = 200;
		
		// Open all the time
		if(time_open === time_close) {
			$('#urb-it-time').removeAttr('min').removeAttr('max');
			
			if(!time.value) time.val(time_now);
		}
		
		// Specific opening hours
		else {
			// Modify label
			$('#urb_it_time_field label span').text('(' + time_open + ' - ' + time_close + ')');
			
			// Set min/max time attributes
			time.attr('min', time_open).attr('max', time_close);
			
			if(!time.val()) time.val(time_now);
			
			// Modify time value if not valid
			if(is_today && time_now > time_close) {
				time.hide().closest('p').find('.error').show();
				time.closest('p').find('label').hide();
			}
			else {
				time.show().closest('p').find('.error').hide();
				time.closest('p').find('label').show();
				
				if(time_now < time_open || time.val() > time_close || time.val() < time_open) time.val(time_open);
			}
		}
	}
	
	// Adjust the lower time each second
	function adjust_time() {
		var option = $('#urb_it_date :selected'),
				is_today = (option.length && option.data('today'));
		
		if(is_today) {
			var now = new Date(),
					time = $('#urb_it_time'),
					time_now = ('0' + (now.getHours() + 1)).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2),
					time_open = option.data('open'),
					time_close = option.data('close');
			
			if(time_open < time_now) {
				if(time.val() === time_open) {
					time.val(time_now).blur();
				}
				option.data('open', time_now);
				$('#urb_it_time_field label span').text('(' + time_now + ' - ' + time_close + ')');
				time.attr('min', time_now);
			}
		}
		
		setTimeout(adjust_time, 1000);
	}
	
	adjust_time();
});