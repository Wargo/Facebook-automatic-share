jQuery(document).ready(function($) {	

	$('.delete_article').click(function() {
		if (confirm('¿Deseas elmiminar este artículo?')) {
			$(this).parent('li').fadeOut('slow', function() {
				$(this).remove();	
			});
			var url = 'https://graph.facebook.com/' + $(this).attr('var');

			$.ajax({
				type: 'post',
				url: url,
				data: {"method":"delete","access_token":$(this).attr('data')},
				dataType: 'json' 
			});
		}
	});

	fb_switcher($);

});

function fb_switcher($) {
	$('.fb_switcher').click(function() {
		var item = $(this);
		item.html('<img src="/wp-content/plugins/facebook-automatic-share/images/ajax-loader.gif" />');
		$.ajax({
			type: 'get',
			url: item.attr('href'),
			success: function(data) {
				if (item.hasClass('fb_disable')) {
					item.attr('title', 'Habilitar la publicación automática en Facebook');
					item.attr('href', '/wp-content/plugins/facebook-automatic-share/enable.php');
					item.attr('class', 'fb_switcher fb_enable');
				} else {
					item.attr('title', 'Deshabilitar la publicación automática en Facebook');
					item.attr('href', '/wp-content/plugins/facebook-automatic-share/disable.php');
					item.attr('class', 'fb_switcher fb_disable');
				}
			}
		});
		return false;
	});
}
