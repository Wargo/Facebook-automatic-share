<?php
/*
 Plugin Name: Automatic Facebook Posting
 Description: Postea a FB lo que vayas leyendo. Este plugin requiere el plugin wp-fb-autoconnect
 Version: 1.1
 Author: Guille & Arques
 Author URI: http://artvisual.net
 */

if ($_SERVER['REMOTE_ADDR'] == '81.202.166.189') {
	//error_reporting(E_ALL);ini_set('display_errors', '1');	
}

require_once 'widget.php';

class FacebookAutomaticShare  {

	var $db_version = '1.1';
	var $table_name = 'fb_autoshare';

	// Examples 
	var $namespace = ''; // 'elmundoenrosacom', 'muysencillo'
	var $action = ''; // 'read', 'learn'
	var $object = ''; // 'article', 'tip'
	var $type = ''; // 'article', 'muysencillo:tip'
	var $url = ''; // 'news.reads', 'muysencillo:learn'
	var $image = ''; // 'http://www.elmundoenrosa.com/wp-content/themes/cuidado_infantil/images/logoblog.png'

	var $current_post_id = '';

	var $fields = array(
		'namespace' => 'Namespace',
		'action' => 'Acción',
		'object' => 'Objeto',
		'type' => 'Tipo',
		'url' => 'URL',
		'image' => 'Imagen por defecto',
	);

	function __construct() {
		// Assign params
		foreach ($this->fields as $key => $value) {
			$this->$key = get_option('AFP_' . $key);
		}

		// Creación de la tabla
		//register_activation_hook(__FILE__, array(&$this, 'install'));

		// Acciones 
		add_action('the_content', array(&$this, 'global_post'));
		add_action('wpfb_add_to_asyncinit', array(&$this, 'fb_autologin'));
		add_action('the_content', array(&$this, 'fb_publish_stream'));
		//add_action('the_post', array(&$this, 'friends'));

		// Cargar por Ajax
		add_action('wp_ajax_friends_action', array(&$this, 'friends'));
		add_action('wp_ajax_nopriv_friends_action', array(&$this, 'friends'));
		
		add_action('wp_head', array(&$this, 'header_meta'));
		remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

		add_action('widgets_init', array(&$this, 'add_widget'));
			
		//add_action('wpfb_add_to_asyncinit', array(&$this, 'fix_chrome'));

		add_filter('wpfb_extended_permissions', array(&$this, 'publish_action_permission'));

		add_action('admin_menu', array(&$this, 'menu')); // Añade al menú del administrador la función menu()

		add_action('wp_print_styles', array(&$this, 'styles'));
	}

	function global_post($content) {
		if (is_single()) {
			global $post;
			$this->current_post_id = $post->ID;
		}
		return $content;
	}

	function friends() {
		if (!is_user_logged_in()) {
			echo '<span class="title">' . __('Descubre qué trucos les han gustado a tus amigos', true) . '</span>';
			echo '<ul class="fb_friends">';
				if ($image = get_option('AFP_social')) {
					echo '<fb:login-button scope="email,publish_actions" v="2" size="small" onlogin="jfb_js_login_callback();"><img src="' . $image . '" class="fb_social" /></fb:login-button>';
				}
			echo '</ul>';
		} else {
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_wp.php');
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_opts.php');
			@include_once(WP_CONTENT_DIR . '/WP-FB-AutoConnect-Premium.php');
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/facebook-platform/php-sdk-3.1.1/facebook.php');
			$facebook = new Facebook(array('appId' => get_option('jfb_app_id'), 'secret' => get_option('jfb_api_sec'), 'cookie' => true ));
			$facebook_user = $facebook->getUser();
			$friends = $facebook->api('/me/friends');
			$aux = array();
			global $wpdb;
			$table_name = $wpdb->prefix . $this->table_name;

			if (count($friends['data'])) {
				echo '<span class="title">' . __('Descubre qué trucos les han gustado a tus amigos', true) . '</span>';
				echo '<ul class="fb_friends">';
					foreach ($friends['data'] as $friend) {
						$aux[] = $friend['id'];
					}
					$sql = "SELECT fb_id, count(*) as num FROM $table_name WHERE fb_id in (" . implode(',', $aux) . ") GROUP BY fb_id ORDER BY num desc LIMIT 10";
					$friends = $wpdb->get_results($sql);
					foreach ($friends as $friend) {
						$user = $facebook->api('/' . $friend->fb_id, array(
							'fields' => array(
								'name',
								'picture',
							),
						));
						echo '<li class="fb_user" title="' . $user['name'] . ' ' . sprintf('ha visto %s artículo(s)', $friend->num) . '">';
							echo '<img src="' . $user['picture'] . '" />';
							echo '<ul class="hidden fb_articles">';
								echo '<li class="fb_title_articles">' . sprintf(__('Artículos leídos por %s', true), $user['name']) . '</li>';
								$sql = "SELECT post_id FROM $table_name WHERE fb_id = $friend->fb_id";
								$articles = $wpdb->get_results($sql);
								foreach ($articles as $article) {
									$image = '';
									$post_thumbnail_id = get_post_meta($article->post_id, '_thumbnail_id', true );

									if ($post_thumbnail_id) {
										$image = wp_get_attachment_image_src($post_thumbnail_id);
										if ($image) {
											$image = $image[0];
										}
									}
									if (empty($image)) {
										$post = get_post($article->post_id);
										$image = $this->catch_that_image($post->post_content);
									}
									echo '<li class="fb_post_image">';
										echo '<a title="' . get_the_title($article->post_id) . '" href="' . get_permalink($article->post_id) . '"><img src="' . $image . '" alt="' . get_the_title($article->post_id) . '" /></a>';
									echo '</li>';
								}
							echo '</ul>';
						echo '</li>';
					}
				echo '</ul>';
			}
		}
		die;
	}

	function add_widget() {
		register_widget('FB_Widget');
	}

	function menu() {
		add_options_page('AFP', 'Automatic Facebook Posting', 'manage_options', 'AFP', array(&$this, 'options_page'));
	}

	function options_page() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		if (!empty($_POST)) {
			foreach ($this->fields as $key => $value) {
				if (!empty($_POST[$key])) {
					update_option('AFP_' . $key, $_POST[$key]);
				}
			}
			if (!empty($_FILES['logo']['size'])) {
				$logo = wp_handle_upload($_FILES['logo']);
				update_option('AFP_social', $logo['url']);
			}
		}

		global $wpdb;

		echo '
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h2>' . __('Ajustes', true) . '</h2>
			<p>' . __('Configuración del plugin', true) . '</p>
			<form action="" method="post" enctype="multipart/form-data">
				';
				foreach ($this->fields as $key => $value) {
					$option = get_option('AFP_' . $key);
					echo '
					<div>
						<label for="' . $key . '">' . $value . '</label>
						<input type="text" name="' . $key . '" id="' . $key . '" value="' . $option . '" />
					<div>
					';
				}
				echo '
				<div>
				    <label for="logo">' . __('Adjunta una imagen para el módulo de compartir', true) . '</label>
				    <input type="file" name="logo" id="logo" />';
				    if ($img = get_option('AFP_social')) {
					echo '<br /><img src="' . $img . '" style="max-width: 200px;" />';
				    }   
				    wp_nonce_field('upload_AFP_social');
				    echo '
				    <input type="hidden" name="action" value="wp_handle_upload" />
				</div>';
				echo '<p class="submit"><input type="submit" value="Guardar cambios" class="button-primary" id="submit" name="submit"></p>
			</form>
		</div>
		';
	}

	function fix_chrome () {
		echo 'FB.Flash.hasMinVersion = function () { return false; };';
	}

	function publish_action_permission( $permissions ) {
		return 'email,publish_actions';
		if ($permissions == '') {
			return 'publish_actions';
		} else {
			return $permissions . ',publish_actions';
		}
	}

	function install() {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			   time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			   user_id bigint(20) NOT NULL,
			   fb_id bigint(30) NOT NULL,
			   post_id bigint(20) NOT NULL,
			   blog_id bigint(20) DEFAULT NULL,
			   UNIQUE KEY id (id)
		   );";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		add_option("fb_autoshare_db_version", $this->db_version);
	}

	function header_meta () {
		global $post;

		if (is_single()) {

			$post_thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );

			if ( $post_thumbnail_id ) {
				$image = wp_get_attachment_image_src( $post_thumbnail_id );
				if ($image) {
					$image = $image[0];
				}
			}
			if ( empty($image)) {
				$image = $this->catch_that_image($post->post_content);
			}

			// Creamos la descripción	
			if (! $description = $post->post_excerpt){
				$description = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $this->create_the_excerpt( $post->post_content ));;
			}

			echo '<meta property="og:title" content="' . str_replace('"', '', $post->post_title) .'" />
				<meta property="og:type" content="' . $this->type . '" />
				<meta property="og:url" content="' . get_permalink($post->ID) .'" />
				<meta property="fb:app_id" content="' . get_option('jfb_app_id') . '" />
				<meta property="og:description" content="' . str_replace('"', '', $description) . '" />';
			if ($image) {
				echo '<meta property="og:image" content="' . $image .'" />';
			} else {
				echo '<meta property="og:image" content="' . $this->image . '" />';
			}
		}

	}

	/**
	 * Publica en el muro
	 *
	 * @param unknown_type $content
	 * @return unknown
	 */
	function fb_publish_stream($content) {
		if (is_user_logged_in() && is_single() && empty($_SESSION['fb_disable'])) {
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_wp.php');
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_opts.php');

			@include_once(WP_CONTENT_DIR . '/WP-FB-AutoConnect-Premium.php');

			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/facebook-platform/php-sdk-3.1.1/facebook.php');

			global $user_ID, $post, $wpdb;

			$facebook = new Facebook(array('appId' => get_option('jfb_app_id'), 'secret' => get_option('jfb_api_sec'), 'cookie' => true ));

			if ($fb_id = $facebook->getUser()){
				// Creamos la descripción	
				if (! $description = $post->post_excerpt){
					$description = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $this->create_the_excerpt($post->post_content));
				}

				$image = $this->catch_that_image($post->post_content);

				$ch = curl_init();

				$data = array(
					'access_token' => $facebook->getAccessToken(),
					$this->object => get_permalink($post->ID) . '?fb-autologin=1',
				);

				curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/me/' . $this->url);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

				$response = json_decode(curl_exec($ch), true);

				if (!empty($response['id'])) {
					$table_name = $wpdb->prefix . $this->table_name;
					$sql = "INSERT INTO $table_name (time, user_id, fb_id, post_id) values (NOW(), '$user_ID', '$fb_id', '$post->ID')";
					$wpdb->query($sql);
				}
			}
		}

		return $content;
	}

	function fb_autologin() {
		global $post;
		if (!is_user_logged_in() && (!empty($_REQUEST['fb_action_ids']))) {
			// Revisar el like del plugin de FB
			echo 'jfb_js_login_callback();';
		} 
	}

	function catch_that_image($contenido) {
		$first_img = '';
		ob_start();
		ob_end_clean();
		$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $contenido, $matches);
		if (!empty($matches[1][0])) {
			return $matches[1][0];
		} else { //Defines a default image
			return false;
		}
	}

	function create_the_excerpt ($contenido, $excerpt_length = 30){
		$content = strip_shortcodes($contenido);
		$content = str_replace(']]>', ']]&gt;', $content);
		$content = strip_tags($content);
		$words = explode(' ', $content, $excerpt_length + 1);
		if(count($words) > $excerpt_length) :
			array_pop($words);
		array_push($words, '...');
		$content = implode(' ', $words);
		endif;

		return $content;
	}

	function styles () {
		wp_enqueue_style('fas_style', '/wp-content/plugins/facebook-automatic-share/style.css');
		wp_enqueue_script('fas_script', '/wp-content/plugins/facebook-automatic-share/js.js', array('jquery'));
		$data = array('ajaxurl' => admin_url('admin-ajax.php'));
		wp_localize_script('fas_script', 'config', $data);
	} 

	function debug($array) {
		if ($_SERVER['REMOTE_ADDR'] == '81.202.166.189') {
			echo '<pre>';
			print_r($array);
			echo '</pre>';
		}
	}

}

$fb_autoshare = new FacebookAutomaticShare();
