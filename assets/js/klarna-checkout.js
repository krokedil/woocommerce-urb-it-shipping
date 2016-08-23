jQuery(function($) {
	console.log('urb-it', 'Loaded!');
	
	var kco = {
		blocked: false,
		suspended: false,
		html: false,
		
		maybe_block: function() {
			var self = this;
					shipping_method = $('#kco-page-shipping input[type="radio"]:checked').val();
			
			// If not urb-it, abort
			if(shipping_method != 'urb_it_one_hour' && shipping_method != 'urb_it_specific_time') {
				self.unblock();
				return;
			}
			
			$('.klarna-checkout-urb-it').slideDown(200);
			
			/*if(shipping_method == 'urb_it_specific_time') {
				
			}*/
			
			var hidden_street = $('.urb-it [name="urb-it-street"]'),
					hidden_postcode = $('.urb-it [name="urb-it-postcode"]'),
					hidden_city = $('.urb-it [name="urb-it-city"]');
			
			console.log('#29', $.trim(hidden_street.val()), $.trim(hidden_postcode.val()), $.trim(hidden_city.val()), !hidden_postcode.data('valid'));
			
			if($.trim(hidden_street.val()) == '' || $.trim(hidden_postcode.val()) == '' || $.trim(hidden_city.val()) == '' || !hidden_postcode.data('valid')) {
				self.block();
				$('.urb-it-shipping-address').hide();
			}
		},
		
		block: function() {
			var self = this;
			
			if(this.blocked) return;
			
			this.blocked = true;
			
			if(!self.html) {
				self.html = $('<form class="urb-it-modal">' + $('.urb-it-html').html() + '</form>');
			}
			
			self.populate_postcode();
			self.populate_address();
			
			$('#urb-it-postcode').first();
			
			$('#klarna-checkout-container').block({
				message: self.html,
				overlayCSS: {
					background: '#fff',
					opacity: 0.85,
					cursor: 'default'
				},
				css: {
					width: 'auto',
					'margin-top': '20px',
					padding: '20px',
					left: '20px',
					right: '20px',
					border: 'none',
					cursor: 'default',
					'box-shadow': '0 0 3px rgba(0, 0, 0, 0.2)'
				}
			});
			
			self.html.find('input[value=""]').first().focus();
		},
		
		unblock: function() {
			//this.resume();
			//return;
			
			if(!this.blocked) return;
			
			this.blocked = false;
			
			$('#klarna-checkout-container').unblock();
		},
		
		populate_postcode: function() {
			var self = this,
					form = $('.klarna-checkout-urb-it'),
					postcode = form.find('[name="urb-it-postcode"]');
			
			self.html.find('#urb-it-postcode').val(postcode.val());
			
			if(postcode.data('valid')) self.html.find('.postcode-error').hide();
			else self.html.find('.postcode-error').show();
		},
		
		populate_address: function() {
			var self = this,
					form = $('.klarna-checkout-urb-it'),
					street = form.find('[name="urb-it-street"]'),
					postcode = form.find('[name="urb-it-postcode"]'),
					city = form.find('[name="urb-it-city"]');
			
			self.html.find('#urb-it-street').val(street.val());
			self.html.find('#urb-it-city').val(city.val());
			
			if(postcode.val() != '' && postcode.data('valid')) self.html.find('.shipping-address').show();
			else self.html.find('.shipping-address').hide();
		},
		
		format_address: function() {
			var form = $('.klarna-checkout-urb-it'),
					street = form.find('[name="urb-it-street"]').val(),
					postcode = form.find('[name="urb-it-postcode"]').val(),
					city = form.find('[name="urb-it-city"]').val(),
					time = form.find('[name="urb-it-time"]').val();
					
			if(!street || !postcode || !city) {
				$('.urb-it-shipping-address').hide();
				return;
			}
			
			form.find('.urb-it-address').html(street + '<br />' + postcode + ' ' + city);
			
			$('.urb-it-shipping-address').show();
		},
		
		set_address: function(address) {
			var form = $('.klarna-checkout-urb-it');
			
			if(typeof address.street !== 'undefined') form.find('[name="urb-it-street"]').val(address.street);
			if(typeof address.postcode !== 'undefined') form.find('[name="urb-it-postcode"]').val(address.postcode);
			if(typeof address.city !== 'undefined') form.find('[name="urb-it-city"]').val(address.city);
			
			this.format_address();
		},
		
		// ---
		
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
	
	kco.maybe_block();
	
	var submiting_form = false;
	
	// Change shipping method
	$(document).on('change', '#kco-page-shipping input[type="radio"]', function(event) {
		kco.format_address();
		kco.maybe_block();
		return;
		
		var shipping_method = $(this).val();
		
		kco.restrict();
		
		if(shipping_method == 'urb_it_specific_time') {
			console.log('Valid specific time!');
			$('.urb-it .specific-time').slideDown(200);
		}
		else {
			console.log('Invalid specific time...');
			$('.urb-it .specific-time').slideUp(200);
		}
		
	// Change postcode
	}).on('click', '.urb-it-modal .check-postcode', function() {
		var btn = $(this);
		
		btn.attr('disabled', 'disabled');
		
		var postcode = btn.closest('.urb-it-postcode').find('[name="urb-it-postcode"]').val();
		
		console.log('Validating postcode...', postcode, $('#urb-it-postcode'), $('#urb-it-postcode').val());
		
		$.get('/', {'wc-ajax': 'urb_it_validate_postcode', postcode: postcode, save: true}, function(valid) {
			btn.removeAttr('disabled');
			
			valid = !!parseInt(valid);
			
			//kco.resume();
			
			$('.klarna-checkout-urb-it [name="urb-it-postcode"]').data('valid', valid);
			//kco.restrict();
			
			if(valid) {
				$('.urb-it-modal .postcode-error').slideUp(200);
				$('.urb-it-modal .shipping-address').slideDown(200, function() {
					$(this).find('.urb-it-street').focus();
				});
				
				console.log('Valid postcode', postcode);
			}
			else {
				$('.urb-it-modal .postcode-error .postcode').text(postcode);
				$('.urb-it-modal .postcode-error').slideDown(200);
				$('.urb-it-modal .shipping-address').slideUp(200);
				
				console.log('Invalid postcode', postcode);
			}
		});
	}).on('change', '#urb-it-postcode', function(e) {
		$(this).closest('.urb-it-postcode').find('.button').click();
		
	// Change address
	}).on('change', '.urb-it .shipping-addresssss', function() {
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
	
	// Form submit
	}).on('submit', '.urb-it-modal', function(e) {
		e.preventDefault();
		
		var form = $(this);
		
		// Check postcode if selected
		if(form.find('[name="urb-it-postcode"]:focus').length) {
			form.find('.check-postcode').click();
			return;
		}
		
		var hidden_street = $('.urb-it [name="urb-it-street"]'),
				hidden_postcode = $('.urb-it [name="urb-it-postcode"]'),
				hidden_city = $('.urb-it [name="urb-it-city"]'),
				new_street = $('.urb-it-modal [name="urb-it-street"]').val(),
				new_postcode = $('.urb-it-modal [name="urb-it-postcode"]').val(),
				new_city = $('.urb-it-modal [name="urb-it-city"]').val();
		
		if($.trim(new_street) == '' || $.trim(new_postcode) == '' || $.trim(new_city) == '') {
			return;
		}
		
		// Abort if address isn't changed
		if(new_street == hidden_street.val() && new_postcode == hidden_postcode.val() && new_city == hidden_city.val()) {
			kco.unblock();
			return;
		}
		
		hidden_street.val(new_street);
		hidden_postcode.val(new_postcode);
		hidden_city.val(new_city);
		
		kco.format_address();
		kco.suspend();
		kco.unblock();
		
		$.get('/', form.serialize() + '&wc-ajax=urb_it_reinit_kco', function(data) {
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
	
	// If user wants to change address
	}).on('click', '.urb-it-change', function() {
		kco.block();
	
	// Change delivery time
	}).on('blur', '.urb-it .specific-time', function() {
		var data = $(this).find(':input').serialize();
		
		$.get('/', data + '&wc-ajax=urb_it_kco_delivery_time', function(success) {
			success = !!success;
			
			if(success) {
				$('.delivery-time-error').slideUp(200);
				//kco.unblock();
			}
			else {
				$('.delivery-time-error').slideDown(200);
				//kco.block();
			}
		});
	});
	
	kco.format_address();
	
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