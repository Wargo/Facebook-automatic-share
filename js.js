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

	$('.disable_fb').click(function() {
		$(this).html('<img src="/wp-content/plugins/facebook-automatic-share/images/ajax-loader.gif" />');
		$.ajax({
			type: 'get',
			url: url,
			success: function() {
				$(this).html('Deshabilitado');
				$(this).attr('title', 'Publicación automática en Facebook deshabilitada');
			}
		});
		return false;
	});

});
