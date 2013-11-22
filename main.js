var _app = {
	init: function (){
		$('#Newsletter').on('click', 'a', _app.newsletterHandler);
	},

	/*
	 * This function is reliant upon the wp_ajax variable,
	 * which points to the wp-admin/admin-ajax.php script
	 * within WordPress
	 */
	newsletterHandler: function (){
		var email = $(this).prev('input').val(),
			regex = new RegExp('^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$'),
			$newsletter = $('#Newsletter'),
			isValid = email.match(regex);
		if ( isValid !== null && isValid.length ) {
			var json_link = wp_ajax+"?action=cc_signup&EmailAddress="+email;
			$newsletter.addClass('processing');
			$.getJSON(json_link, function(data) {
				if ( $newsletter.find('span').length < 1 ) {
					$newsletter.append('<span>'+data.title+' '+data.message+'</span>');
				} else {
					$newsletter.find('span').html(data.title+' '+data.message);
				}
				$newsletter.find('span').addClass('open');
				setTimeout( function (){
					$newsletter.removeClass('processing').find('span').removeClass('open');
				}, 8000);
			}).fail(function(e) { 
				// should always be successful with failure message
			});
		}
		return false;
	},
}

$(document).ready( function (){
	_app.init();
});
