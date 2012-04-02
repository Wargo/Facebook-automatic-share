<?php
/**
 * Adds Foo_Widget widget.
 */

class FB_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */

	var $object = '';
	var $url = '';

	public function __construct() {
		$this->object = get_option('AFP_object');
		$this->url = get_option('AFP_url');

		parent::__construct(
			'fb_widget', // Base ID
			'Artículos de Facebook', // Name
			array('description' => __('Artículos de facebook compartidos, con posibilidad de eliminarlos', 'text_domain'))
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);

		if (is_user_logged_in() && is_single()) {
			$this->show_widget($title, $before_widget, $after_widget, false);
		}
	}

	function show_widget($title = null, $before_widget = null, $after_widget = null, $isAjax = true) {
		if (empty($title)) {
			$title = 'Mis artículos';
		}
		require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_wp.php');
		require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_opts.php');

		@include_once(WP_CONTENT_DIR . '/WP-FB-AutoConnect-Premium.php');

		require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/facebook-platform/php-sdk-3.1.1/facebook.php');
		$facebook = new Facebook(array('appId' => get_option('jfb_app_id'), 'secret' => get_option('jfb_api_sec'), 'cookie' => true));
		$facebook->getUser();
		$get_posts = 'https://graph.facebook.com/me/' . $this->url . '?access_token=' . $facebook->getAccessToken();
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $get_posts);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$response = curl_exec($ch);
		$articles = json_decode($response, true);

		if (count($articles['data'])) {
			echo $before_widget;
			if (empty($_SESSION['fb_disable'])) {
				echo '<a href="/wp-content/plugins/facebook-automatic-share/disable.php" class="fb_switcher fb_disable" title="' . __('Deshabilitar publicación automática en Facebook', true) . '"></a>';
			} else {
				echo '<a href="/wp-content/plugins/facebook-automatic-share/enable.php" class="fb_switcher fb_enable" title="' . __('Habilitar la publicación automática en Facebook', true) . '"></a>';
			}
			if (!empty($title)) {
				echo '<div class="fb_widget_title">' . $before_title . $title . $after_title . '</div>';
			}
			echo '<ul class="fb_articles_list clearfix">';
		}

		foreach ($articles['data'] as $article) {
			echo '<li>';
				echo '<span title="Borrar artículo" class="fb_delete_article" data="' . $facebook->getAccessToken() . '" var="' . $article['id'] . '"></span>';
				echo '<a href="' . $article['data'][$this->object]['url'] . '">';
					echo $article['data'][$this->object]['title'];
				echo '</a>';
			echo '</li>';
		}

		if (count($articles)) {
			echo '</ul>';
			echo $after_widget;
		}

		if ($isAjax) {
			die;
		}
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'text_domain' );
		}
		?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<?php 
	}

	function debug($array) {
		if ($_SERVER['REMOTE_ADDR'] == '81.202.166.189') {
			echo '<pre>';
			print_r($array);
			echo '</pre>';
		}
	}

} // class Foo_Widget
