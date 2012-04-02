jQuery(document).ready(function($) {

	if ($("#fb_lazy").size() > 0) {
		var ajaxurl = config.ajaxurl;
		$.post(ajaxurl, {action: 'friends_action'}, function(response) {
			$("#fb_lazy").html(response);
			fb_friends($);
			fb_my_list($);
		});
	}

	fb_switcher($);
	fb_delete_post($);

});

function fb_switcher($) {
	$('.fb_switcher').click(function() {
		var item = $(this);
		item.addClass('fb_loading');
		$.ajax({
			type: 'get',
			url: item.attr('href'),
			success: function(data) {
				item.removeClass('fb_loading');
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

function fb_friends($) {
	$('.fb_friends li.fb_user').hover(function() {
		var current_marked = $(this).attr('class');
		$('.fb_friends li.fb_user').removeClass('fb_marked');
		$(this).attr('class', current_marked);
		$(this).toggleClass('fb_marked');

		var current_class = $(this).find('ul').attr('class');
		$('ul.fb_articles').addClass('hidden');
		$(this).find('ul').attr('class', current_class);
		$(this).find('ul').toggleClass('hidden');
	});
}

function fb_my_list($) {
	if ($(".fb_my_list").size() > 0) {
		var ajaxurl = config.ajaxurl;
		$.post(ajaxurl, {action: 'my_list_action'}, function(response) {
			$('.fb_my_list').html(response);
			fb_switcher($);
			fb_delete_post($);
		});
		$('.fb_me').hover(function() {
			$('.fb_my_list').toggleClass('hidden');
		});
	}
}

function fb_delete_post($) {
	$('.fb_delete_article').click(function() {
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
}
