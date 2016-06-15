jQuery(function($) {
	console.log('urb-it', 'Loaded!');
	
	// Event: Change
	$(document.body).on('kco_change', function(e, data) {
		console.log('urb-it', 'change', data);
	
	// Event: Shipping Change
	}).on('kco_shipping_address_change', function(e, data) {
		console.log('urb-it', 'shipping_address_change', data);
		
		$.get('/', {'wc-ajax': 'urb_it_validate_postcode', postcode: data.postal_code, save: true}, function(valid) {
			valid = !!parseInt(valid);
			
			if(valid) {
				$('.urb-it .postcode-error').slideUp(200);
				
				console.log('Valid post code. Enabling shipping method:', $('#kco-page-shipping input[type="radio"]').val());
				
				if($('#kco-page-shipping input[type="radio"]').val() == 'urb_it_specific_time') {
					$('.urb-it .specific-time').slideDown(200);
				}
			}
			else {
				$('.urb-it .postcode-error .postcode').text(data.postal_code);
				$('.urb-it .postcode-error').slideDown(200);
				$('.urb-it .specific-time').slideUp(200);
			}
		});
	
	// Event: Total Change
	}).on('kco_order_total_change', function(e, data) {
		console.log('urb-it', 'order_total_change', data);
		
	}).on('kco_widget_updated', function() {
		$(document.body).trigger('urbit_set_time_field');
	});
	
	// Change shipping method
	$(document).on('change', '#kco-page-shipping input[type="radio"]', function(event) {
		var new_method = $(this).val();
		
		if(new_method != 'urb_it_one_hour' && new_method != 'urb_it_specific_time') {
			window.kco_skip_postal_code = false;
			$('.urb-it').slideUp(200);
			return;
		}
		
		window.kco_skip_postal_code = true;
		$('.urb-it').slideDown(200);
		
		if(new_method == 'urb_it_specific_time' && !$('.urb-it .postcode-error:visible').length) {
			$('.urb-it .specific-time').slideDown(200);
		}
		else {
			$('.urb-it .specific-time').slideUp(200);
		}
		
	// Change specific time
	}).on('blur', '.urb-it .specific-time', function() {
		var data = $(this).find(':input').serialize();
		
		$.get('/', data + '&wc-ajax=urb_it_save_specific_time');
	}).ajaxComplete(function(event, xhr, ajaxOpts) {
		$(document.body).trigger('urbit_set_time_field');
	});
	
	var current_shipping_method = $('#kco-page-shipping input[type="radio"]').val();
	
	if(current_shipping_method == 'urb_it_one_hour' || current_shipping_method == 'urb_it_specific_time') {
		window.kco_skip_postal_code = true;
	}
	
	$('.urb-it .specific-time').change();
	
});