(function($) {
	var blocker = {
		block: function(e) {
			e.block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
		},
		unblock: function(e) {
			e.unblock();
		}
	};

	$('.urb-it-postcode-validator').submit(function(e) {
		e.preventDefault();
		
		var self = $(this);
		
		blocker.block(self);
		
		var data = '?' + self.serialize() + '&wc-ajax=urb_it_validate_postcode',
				postcode = self.find('input[name="postcode"]').val();
		
		$.get(data, function(valid) {
			valid = !!parseInt(valid);
			
			blocker.unblock(self);
			
			var headline = self.find('.title');
			
			if(valid) {
				headline.text(headline.data('success'));
				
				self.find('.field').hide(200);
				self.find('.show-field').show(200);
			}
			else {
				headline.text(headline.data('failure'));
			}
			
			if(localStorage !== 'undefined') {
				localStorage.setItem('last_postcode', postcode);
				localStorage.setItem('last_postcode_result', valid);
			}
		});
	});
	
	$('.urb-it-postcode-validator .show-field').click(function() {
		$(this).closest('.urb-it-postcode-validator').find('.field').show(200).find('input').focus();
		$(this).hide();
	});
	
	if(localStorage !== 'undefined') {
		var postcode = localStorage.getItem('last_postcode');
		
		if(postcode) {
			var headline = $('.urb-it-postcode-validator .title'),
					result = localStorage.getItem('last_postcode_result');
			
			$('.urb-it-postcode-validator input[name="postcode"]').val(postcode);
			
			if(result === 'true') {
				headline.text(headline.data('success'));
				
				$('.urb-it-postcode-validator .field').hide();
				$('.urb-it-postcode-validator .show-field').show();
			}
			else {
				headline.text(headline.data('failure'));
			}
		}
	}
})(jQuery);