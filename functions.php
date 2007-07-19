<?php

function backpress_global_sanitize( $array, $trim = true ) {
	foreach ($array as $k => $v) {
		if ( is_array($v) ) {
			$array[$k] = backpress_global_sanitize($v);
		} else {
			if ( !get_magic_quotes_gpc() )
				$array[$k] = addslashes($v);
			if ( $trim )
				$array[$k] = trim($array[$k]);
		}
	}
	return $array;
}

function wp_parse_args( $args, $defaults = '' ) {
	if ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	else
		return $r;
}

function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if ( get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array ); // parse_str() adds slashes if magicquotes is on.  See: http://php.net/parse_str
	$array = apply_filters( 'wp_parse_str', $array );
}

function urlencode_deep($value) {
	return is_array($value) ? array_map('urlencode_deep', $value) : urlencode($value);
}

function stripslashes_deep($value) {
	return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
}

function maybe_serialize( $data ) {
	if ( is_string($data) )
		$data = trim($data);
	elseif ( is_array($data) || is_object($data) || is_bool($data) ) // bool too?
		return serialize($data);
	if ( is_serialized( $data ) )
		return serialize($data);
	return $data;
}

function maybe_unserialize( $data ) {
	if ( is_serialized( $data ) ) {
		if ( 'b:0;' === $data )
			return false;
		if ( false !== $_data = @unserialize($data) )
			return $_data;
	}
	return $data;
}

function is_serialized($data) {
	// if it isn't a string, it isn't serialized
	if ( !is_string($data) )
		return false;
	$data = trim($data);
	if ( 'N;' == $data )
		return true;
	if ( !preg_match('/^([adObis]):/', $data, $badions) )
		return false;
	switch ( $badions[1] ) :
	case 'a' :
	case 'O' :
	case 's' :
		if ( preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data) )
			return true;
		break;
	case 'b' :
	case 'i' :
	case 'd' :
		if ( preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data) )
			return true;
		break;
	endswitch;
	return false;
}

function is_serialized_string($data) {
	// if it isn't a string, it isn't a serialized string
	if ( !is_string($data) )
		return false;
	$data = trim($data);
	if ( preg_match('/^s:[0-9]+:.*;$/s',$data) ) // this should fetch all serialized strings
		return true;
	return false;
}

function backpress_gmt_strtotime( $string ) {
	if ( is_numeric($string) )
		return $string;
	if ( !is_string($string) )   
		return -1;

	if ( stristr($string, 'utc') || stristr($string, 'gmt') || stristr($string, '+0000') )
		return strtotime($string);

	if ( -1 == $time = strtotime($string . ' +0000') )
		return strtotime($string);

	return $time;
}

/*
function backpress_time( &$backpress, $type = 'timestamp', $gmt = true ) {
	if ( !is_backpress( $backpress ) )
		return new WP_Error( 'backpress', __('Invalid BackPress instance') );

	switch ( $type ) :
	case 'mysql':
		$d = $gmt ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', time() + $backpress->option( 'gmt_offset' ) * 3600);
		break;
	case 'timestamp':
		$d = $gmt ? time() : time() + $backpress->option( 'gmt_offset' ) * 3600;
		break;
	endswitch;

	return $d;
}
*/

function backpress_random_string( $length = 32 ) {
        $number = mt_rand(0, 32 - $length);
        $string = md5( uniqid( microtime() ) );
	return substr( $string, $number, $length );
        return $password;
}

function backpress_hash( &$backpress, $data ) { 
	$salt = $backpress->option( 'secret' );

	if ( function_exists('hash_hmac') ) {
		return hash_hmac('md5', $data, $salt);
	} else {
		return md5($data . $salt);
	}
}

function backpress_verify_nonce( &$backpress, $nonce, $action = -1 ) {
	$user = backpress_get_current_user( $backpress );

	$i = ceil(time() / 43200);

	//Allow for expanding range, but only do one check if we can
	return $nonce == substr(backpress_hash( $backpress, $i . $action . $user->ID ), -12, 10) || $nonce == substr( backpress_hash( $backpress, ($i - 1) . $action . $uid ), -12, 10);
}

function backpress_create_nonce( &$backpress, $action = -1 ) {
	$user = backpress_get_current_user( $backpress );

	$i = ceil(time() / 43200);
	
	return substr(backpress_hash( $backpress, $i . $action . $user->ID ), -12, 10);
}

function backpress_check_admin_referer( &$backpress, $action = -1 ) {
	if ( !backpress_verify_nonce( $backpress, $_REQUEST['_wpnonce'], $action ) ) {
		backpress_nonce_ays( $backpress, $action );
		die();
	}

	do_action( __FUNCTION__, $action );
}

function backpress_nonce_ays( &$backpress, $action, $title ) {
	if ( !$adminurl = wp_get_referer() )
		$adminurl = $backpress->option( 'admin_url' );

	$title    = wp_specialchars( $title );
	$adminurl = clean_url( $adminurl );

	// Remove extra layer of slashes.
	$_POST = stripslashes_deep( $_POST );

	$explain = apply_filters( "backpress_explain_nonce_$backpress->type", $action );
	if ( $explain == $action )
		$explain = __('Are you sure you want to do that?');
	$explain = wp_specialchars( $explain );

	if ( $_POST ) {
		$q = http_build_query($_POST);
		$q = explode( ini_get('arg_separator.output'), $q);
		$url = attribute_escape( remove_query_arg( '_wpnonce' ) );
		$html .= "\t<form method='post' action='$url'>\n";
		foreach ( (array) $q as $a ) {
			list($k, $v) = explode('=', $a, 2);
			$k = attribute_escape( urldecode($k) );
			$v = attribute_escape( urldecode($v) );
			$html .= "\t\t<input type='hidden' name='$k' value='$v' />\n";
		}
		$html .= "\t\t<input type='hidden' name='_wpnonce' value='" . backpress_create_nonce( $backpress, $action ) . "' />\n";
		$html .= "\t\t<div id='message' class='confirm fade'>\n\t\t<p>$explain</p>\n\t\t<p><a href='$adminurl'>" . wp_specialchars( __('No') ) . "</a> <input type='submit' value='" . attribute_escape( __('Yes') ) . "' /></p>\n\t\t</div>\n\t</form>\n";
	} else {
		$url = clean_url( add_query_arg( '_wpnonce', backpress_create_nonce( $backpress, $action ), $_SERVER['REQUEST_URI'] ) );
		$html .= "\t<div id='message' class='confirm fade'>\n\t<p>$explain</p>\n\t<p><a href='$adminurl'>" . wp_specialchars( __('No') ) . "</a> <a href='" . attribute_escape( $url ) . "'>" . wp_specialchars( __('Yes') ) . "</a></p>\n\t</div>\n";
	}
	$html .= "</body>\n</html>";
	backpress_die( $backpress, $html, $title );
}

function backpress_die( &$backpress, $html, $title ) {
	do_action( __FUNCTION__ . "_$backpress->type", $html, $title );

	if ( !$title )
		$title = __('Error');

	$title = wp_specialchars( $title );
	$html  = wp_specialchars( $html );

	$die = "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml'><head><title>$title</title></head><body><p>$html</p></body></html>";
	die( $die );
}

?>
