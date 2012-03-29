<?php
/**
 * Adds Foo_Widget widget.
 */

class Foo_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'foo_widget', // Base ID
			'Foo_Widget', // Name
			array( 'description' => __( 'A Foo Widget', 'text_domain' ), ) // Args
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
	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );

		if ($_SERVER['REMOTE_ADDR'] == '81.202.166.189')
		if (is_user_logged_in() && is_single()) {
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_wp.php');
			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_opts.php');

			@include_once(WP_CONTENT_DIR . '/WP-FB-AutoConnect-Premium.php');

			require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/facebook-platform/php-sdk-3.1.1/facebook.php');
			$facebook = new Facebook(array('appId' => get_option('jfb_app_id'), 'secret' => get_option('jfb_api_sec'), 'cookie' => true ));
			$facebook->getUser();
			$get_posts = 'https://graph.facebook.com/me/news.reads?access_token=' . $facebook->getAccessToken();
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $get_posts);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$response = curl_exec($ch);
			$articles = json_decode($response, true);
			
			if (count($articles)) {
				echo $before_widget;
				if ( ! empty( $title ) ) {
					echo $before_title . $title . $after_title;
				}
				echo '<ul class="fb_articles_list">';
			}
			
			foreach ($articles['data'] as $article) {
				echo '<li>';
					echo $article['data']['article']['title'];
					echo ' <span class="delete_article" var="' . $article['id'] . '"></span>';
				echo '</li>';
			}

			if (count($articles)) {
				echo '</ul>';
				echo $after_widget;
			}
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
