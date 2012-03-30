<?php
require_once '../../../wp-load.php';
if (is_user_logged_in()) {
	session_start();
	unset($_SESSION['fb_disable']);
}
