<?php

//This is only used at initialization.
function backpress_set_current_user_from_env( &$backpress ) {
	list($user, $pass) = backpress_get_auth_cookie( $backpress );
	if ( empty($user) || empty($pass) )
		return false;

	$user = backpress_sanitize_user( $user );

	if ( $current_user = backpress_check_login( $backpress, $user, $pass, true, true ) )
		return backpress_set_current_user( $backpress, $current_user->ID);

	backpress_set_current_user( $backpress, 0 );
	return false;
}

function backpress_get_auth_cookie( &$backpress ) {
	$cookie = backpress_cookie_settings( $backpress );
	
	return array(
		empty($_COOKIE[$cookie['user']]) ? null: $_COOKIE[$cookie['user']],
		empty($_COOKIE[$cookie['pass']]) ? null: $_COOKIE[$cookie['pass']]
	);
}

function backpress_check_login( &$backpress, $user, $pass, $already_md5 = false, $cache_user = false ) {
	$user = backpress_sanitize_user( $user );
	if ( $already_md5 )
		$sql = $backpress->prepare( "SELECT * FROM $backpress->users WHERE user_login = '%s' AND MD5( user_pass ) = '%s'", $user, $pass );
	else
		$sql = $backpress->prepare( "SELECT * FROM $backpress->users WHERE user_login = '%s' AND SUBSTRING_INDEX( user_pass, '---', 1 ) = '%s'", $user, md5( $pass ) );

	if ( ( $user = $backpress->get_row( $sql ) ) && $cache_user )
		$user = backpress_append_meta( $backpress, $user );
	return $user;
}

function backpress_set_cookie( &$backpress, $name, $value, $remember = false ) {
	$cookie = backpress_cookie_settings( $backpress );

	$expires = $remember ? time() + 31536000 : 0;
	
	if ( $cookie['domain'] )
		setcookie( $name, $value, $expires, $cookie['path'], $cookie['domain'] );
	else
		setcookie( $name, $value, $expires, $cookie['path'] );
}

function backpress_set_current_user( &$backpress, $id ) {
	global $backpress_current_user;

	if ( isset($backpress_current_user) && ( $id == $backpress_current_user->ID ) )
		return $backpress_current_user;

	$backpress_current_user = backpress_get_user( $backpress, $id );
	if ( is_wp_error($backpress_current_user) )
		$backpress_current_user = 0;

	do_action( __FUNCTION__, $id, $backpress->id );

	$backpress_current_user->backpress = $backpress->id;

	return $backpress_current_user;
}

function backpress_get_current_user( &$backpress ) {
	global $backpress_current_user;

	if ( isset($backpress_current_user->backpress) && $backpress_current_user->backpress == $backpress->id )
		return $backpress_current_user;

	return backpress_set_current_user_from_env( $backpress );
}

function backpress_is_user_authorized( &$backpress ) {
	return backpress_is_user_logged_in( $backpress );
}

function backpress_is_user_logged_in( &$backpress ) {
	$user = backpress_get_current_user( $backpress );

	return (bool) $user;
}

function backpress_login( &$backpress, $login, $password, $remember = false ) {
	if ( $user = backpress_check_login( $backpress, $login, $password ) ) {
		$cookie = backpress_cookie_setting( $backpress );
		backpress_set_cookie( $backpress, $cookie['user'], $user->user_login, $remember );
		backpress_set_cookie( $backpress, $cookie['pass'], md5( $user->user_pass ), $remember );
		do_action( __FUNCTION__, (int) $user->ID, $remember, $backpress->id );
	}

	return $user;
}

function backpress_logout( &$backpress ) {
	$cookie = backpress_cookie_setting( $backpress );

	backpress_set_cookie( $backpress, $cookie['pass'] , ' ', time() - 31536000 );
	backpress_set_cookie( $backpress, $cookie['user'] , ' ', time() - 31536000 );
	do_action( __FUNCTION__ );
}

// Cookie safe redirect.  Works around IIS Set-Cookie bug.
// http://support.microsoft.com/kb/q176113/
if ( !function_exists('wp_redirect') ) : // [WP4407]
function wp_redirect($location, $status = 302) {
	global $is_IIS;

	$location = apply_filters('wp_redirect', $location, $status);

	if ( !$location ) // allows the wp_redirect filter to cancel a redirect
		return false; 

	$location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%]|i', '', $location);
	$location = wp_kses_no_null($location);

	$strip = array('%0d', '%0a');
	$location = str_replace($strip, '', $location);

	if ( $is_IIS ) {
		header("Refresh: 0;url=$location");
	} else {
		if ( php_sapi_name() != 'cgi-fcgi' )
			status_header($status); // This causes problems on IIS and some FastCGI setups
		header("Location: $location");
	}
}
endif;

?>
