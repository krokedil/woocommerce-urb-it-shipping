jQuery(function($) {
	console.log('urb-it', 'Loaded!');
	
	var kco = {
		blocked: false,
		suspended: false,
		
		block: function() {
			this.suspend();
			return;
			
			if(this.blocked) return;
			
			this.blocked = true;
			
			$('#urb-it-postcode').first();
			
			$('#klarna-checkout-container').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},
		
		unblock: function() {
			this.resume();
			return;
			
			if(!this.blocked) return;
			
			this.blocked = false;
			
			$('#klarna-checkout-container').unblock();
		},
		
		suspend: function() {
			if(this.suspended) return;
			
			this.suspended = true;
			
			console.log('Init suspention');
			
			
			
			this.keep_suspended();
		},
		
		keep_suspended: function() {
			var self = this;
			
			if(!this.suspended) return;
			
			if(typeof window._klarnaCheckout == 'function') {
				window._klarnaCheckout(function (api) {
					// Remove focus from KCO
					if(!$(':focus').length) document.activeElement.blur();
			
					api.suspend();
				});
			}
			
			setTimeout(function() {
				self.keep_suspended();
			}, 1000);
		},
		
		resume: function() {
			if(!this.suspended) return;
			
			this.suspended = false;
			
			if(typeof window._klarnaCheckout == 'function') {
				//var focused = $('input:focus, textarea:focus');
				
				//console.log('Focused:', focused);
				
				window._klarnaCheckout(function(api) {
					api.resume();
				});
				
				//if(focused.length) focused.focus();
			}
		},
		
		restrict: function() {
			var self = this,
					shipping_method = $('#kco-page-shipping input[type="radio"]:checked').val();
			
			console.log('urb-it', 'Selected shipping method: ', shipping_method);
			
			// If not urb-it, reset restrictions and abort
			if(shipping_method != 'urb_it_one_hour' && shipping_method != 'urb_it_specific_time') {
				$('.urb-it').slideUp(200);
				console.log('Unblocking, as shipping method is not urb-it', shipping_method);
				self.unblock();
				return;
			}
			
			// If urb-it, show fields
			$('.urb-it').slideDown(200);
			
			$('#urb-it-postcode').first();
			
			// If empty/invalid address, block KCO
			if($.trim($('#urb-it-postcode').val()) == ''
			|| $.trim($('#urb-it-street').val()) == ''
			|| $.trim($('#urb-it-city').val()) == ''
			|| !$('#urb-it-postcode').data('valid')) {
				console.log('urb-it', 'Invalid address - blocking KCO');
				self.block();
				return;
			}
			
			// Unblock KCO
			console.log('urb-it', 'Unblocking KCO as no restrictions occur');
			self.unblock();
		}
	};
	
	window.ivar = kco;
	
	kco.restrict();
	
	var submiting_form = false;
	
	// Change shipping method
	$(document).on('change', '#kco-page-shipping input[type="radio"]', function(event) {
		var shipping_method = $(this).val();
		
		kco.restrict();
		
		if(shipping_method == 'urb_it_specific_time' && $.trim($('.urb-it .postcode').val()) != '' && !$('.urb-it .postcode-error:visible').length) {
			console.log('Valid specific time!');
			$('.urb-it .specific-time').slideDown(200);
		}
		else {
			console.log('Invalid specific time...');
			$('.urb-it .specific-time').slideUp(200);
		}
		
	// Change postcode
	}).on('submit', '.urb-it', function(e) {
		e.preventDefault();
		
		if(submiting_form) return;
		
		submiting_form = true;
		
		console.log('urb-it', 'Postcode form submited!');
		
		kco.suspend();
		
		var postcode = $('#urb-it-postcode').val();
		
		$.get('/', {'wc-ajax': 'urb_it_validate_postcode', postcode: postcode, save: true}, function(valid) {
			valid = !!parseInt(valid);
			
			//kco.resume();
			
			$('#urb-it-postcode').data('valid', valid);
			kco.restrict();
			
			if(valid) {
				$('.urb-it .postcode-error').slideUp(200);
				$('.urb-it .shipping-address').slideDown(200, function() {
					$(this).find('.urb-it-street').focus();
				});
				
				console.log('Valid post code');
				
				if($('#kco-page-shipping input[type="radio"]:checked').val() == 'urb_it_specific_time') {
					$('.urb-it .specific-time').slideDown(200);
				}
			}
			else {
				$('.urb-it .postcode-error .postcode').text(postcode);
				$('.urb-it .postcode-error').slideDown(200);
				$('.urb-it .specific-time').slideUp(200);
				$('.urb-it .shipping-address').slideUp(200);
			}
			
			submiting_form = false;
		});
	}).on('change', '#urb-it-postcode', function(e) {
		$(this).closest('form').submit();
		
	// Change address
	}).on('change', '.urb-it .shipping-address', function() {
		/*if($.trim($('#urb-it-street').val() === '') || $.trim($('#urb-it-city').val() === '')) {
			kco.block();
			return;
		}
		
		kco.unblock();*/
		
		kco.restrict();
		
		if(kco.suspended) return;
		
		kco.suspend();
		
		$.get('/', $('.urb-it').serialize() + '&wc-ajax=urb_it_reinit_kco', function(data) {
			console.log('urb-it', 'Done!');
			
			var html = $(data).find('#klarna-checkout-container');
			
			console.log('urb-it', 'found', html.length, 'element(s).');
			
			if(!html.length) return;
			
			var focused = $('input:focus, textarea:focus');
			
			kco.suspended = false;
			
			$('#klarna-checkout-container').html(html);
			
			$('#urb-it-postcode').first();
			
			console.log('urb-it', 'Substituted!');
		});
	
	// Change delivery time
	}).on('blur', '.urb-it .specific-time', function() {
		var data = $(this).find(':input').serialize();
		
		$.get('/', data + '&wc-ajax=urb_it_kco_delivery_time', function(success) {
			success = !!success;
			
			if(success) {
				$('.delivery-time-error').slideUp(200);
				kco.unblock();
			}
			else {
				$('.delivery-time-error').slideDown(200);
				kco.block();
			}
		});
	});
	
	/*return;
	
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
	
	$('.urb-it .specific-time').change();*/
	
});