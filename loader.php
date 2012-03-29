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
		add_action('wpfb_add_to_asyncinit', array(&$this, 'fb_autologin'));
		add_action('the_content', array(&$this, 'fb_publish_stream'));
		//add_action('the_content', array(&$this, 'profile'));
		
		add_action('wp_head', array(&$this, 'header_meta'));
		remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

		//add_action('widgets_init', create_function('', 'register_widget("Foo_Widget");'));
		add_action('widgets_init', array(&$this, 'add_widget'));
			
		//add_action('wpfb_add_to_asyncinit', array(&$this, 'fix_chrome'));

		add_filter('wpfb_extended_permissions', array(&$this, 'publish_action_permission'));

		add_action('admin_menu', array(&$this, 'menu')); // Añade al menú del administrador la función menu()

		add_action('wp_print_styles', array(&$this, 'styles'));
	}

	function add_widget() {
		register_widget('Foo_Widget');
	}

	function menu() {
		add_options_page('AFP', 'AFP', 'manage_options', 'AFP', array(&$this, 'options_page'));
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

			echo '<meta property="og:title" content="' . $post->post_title .'" />
				<meta property="og:type" content="' . $this->type . '" />
				<meta property="og:url" content="' . get_permalink($post->ID) .'" />
				<meta property="fb:app_id" content="' . get_option('jfb_app_id') . '" />
				<meta property="og:description" content="' .$description . '" />';
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
	function fb_publish_stream ( $content ) {
		if (is_user_logged_in() && is_single()) {
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_wp.php');
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_opts.php');

			@include_once(WP_CONTENT_DIR . '/WP-FB-AutoConnect-Premium.php');

			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/facebook-platform/php-sdk-3.1.1/facebook.php');

			global $user_ID, $post;

			$facebook = new Facebook(array('appId' => get_option('jfb_app_id'), 'secret' => get_option('jfb_api_sec'), 'cookie' => true ));

			if ($facebook->getUser()){
				// Creamos la descripción	
				if (! $description = $post->post_excerpt){
					$description = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $this->create_the_excerpt($post->post_content));;
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

				$response = curl_exec($ch);
			}
		}

		return $content;
	}

	function fb_autologin ( ) {
		global $post;
		if (! is_user_logged_in() && (! empty($_REQUEST['fb_action_ids']) )) {
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
		//wp_enqueue_js('fas_style', '/wp-content/plugins/facebook-automatic-share/js.js');
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
