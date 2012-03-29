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

});
