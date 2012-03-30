<?php
//error_reporting(E_ALL);ini_set('display_errors', '1');	

require_once '../../../wp-load.php';

if (is_user_logged_in()) {
	require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_wp.php');
	require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/__inc_opts.php');
	@include_once(WP_CONTENT_DIR . '/WP-FB-AutoConnect-Premium.php');
	require_once(WP_PLUGIN_DIR . '/wp-fb-autoconnect/facebook-platform/php-sdk-3.1.1/facebook.php');
	$facebook = new Facebook(array('appId' => get_option('jfb_app_id'), 'secret' => get_option('jfb_api_sec'), 'cookie' => true ));
	$_SESSION['fb_disable'] = $facebook->getUser();
}
