<?php

/*
add_query_arg: Returns a modified querystring by adding
a single key & value or an associative array.
Setting a key value to emptystring removes the key.
Omitting oldquery_or_uri uses the $_SERVER value.

Parameters:
add_query_arg(newkey, newvalue, oldquery_or_uri) or
add_query_arg(associative_array, oldquery_or_uri)
*/
function add_query_arg() {
	$ret = '';
	if ( is_array(func_get_arg(0)) ) {
		if ( @func_num_args() < 2 || false === @func_get_arg(1) )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = @func_get_arg(1);
	} else {
		if ( @func_num_args() < 3 || false === @func_get_arg(2) )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = @func_get_arg(2);
	}

	if ( $frag = strstr($uri, '#') )
		$uri = substr($uri, 0, -strlen($frag));
	else
		$frag = '';

	if ( preg_match('|^https?://|i', $uri, $matches) ) {
		$protocol = $matches[0];
		$uri = substr($uri, strlen($protocol));
	} else {
		$protocol = '';
	}

	if (strpos($uri, '?') !== false) {
		$parts = explode('?', $uri, 2);
		if ( 1 == count($parts) ) {
			$base = '?';
			$query = $parts[0];
		} else {
			$base = $parts[0] . '?';
			$query = $parts[1];
		}
	} elseif (!empty($protocol) || strpos($uri, '/') !== false) {
		$base = $uri . '?';
		$query = '';
	} else {
		$base = '';
		$query = $uri;
	}

	wp_parse_str($query, $qs);
	$qs = urlencode_deep($qs);
	if ( is_array(func_get_arg(0)) ) {
		$kayvees = func_get_arg(0);
		$qs = array_merge($qs, $kayvees);
	} else {
		$qs[func_get_arg(0)] = func_get_arg(1);
	}

	foreach($qs as $k => $v) {
		if ( $v !== FALSE ) {
			if ( $ret != '' )
				$ret .= '&';
			if ( empty($v) && !preg_match('|[?&]' . preg_quote($k, '|') . '=|', $query) )
				$ret .= $k;
			else
				$ret .= "$k=$v";
		}
	}
	$ret = trim($ret, '?');
	$ret = $protocol . $base . $ret . $frag;
	$ret = rtrim($ret, '?');
	return $ret;
}

/*
remove_query_arg: Returns a modified querystring by removing
a single key or an array of keys.
Omitting oldquery_or_uri uses the $_SERVER value.

Parameters:
remove_query_arg(removekey, [oldquery_or_uri]) or
remove_query_arg(removekeyarray, [oldquery_or_uri])
*/

function remove_query_arg($key, $query=FALSE) {
	if ( is_array($key) ) { // removing multiple keys
		foreach ( (array) $key as $k )
			$query = add_query_arg($k, FALSE, $query);
		return $query;
	}
	return add_query_arg($key, FALSE, $query);
}

function get_status_header_desc( $code ) {
	global $wp_header_to_desc;

	$code = (int) $code;

	if ( isset( $wp_header_to_desc[$code] ) ) {
		return $wp_header_to_desc[$code];
	} else {
		return '';
	}
}

function status_header( $header ) {
	$text = get_status_header_desc( $header );

	if ( empty( $text ) )
		return false;

	$protocol = $_SERVER["SERVER_PROTOCOL"];
	if ( ('HTTP/1.1' != $protocol) && ('HTTP/1.0' != $protocol) )
		$protocol = 'HTTP/1.0';
	$status_header = "$protocol $header $text";
	$status_header = apply_filters('status_header', $status_header, $header, $text, $protocol);

	if ( version_compare( phpversion(), '4.3.0', '>=' ) ) {
		return @header( $status_header, true, $header );
	} else {
		return @header( $status_header );
	}
}

function nocache_headers() { // [WP2623]
	@ header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	@ header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	@ header('Cache-Control: no-cache, must-revalidate, max-age=0');
	@ header('Pragma: no-cache');
}

function cache_javascript_headers() { // [WP5640] Not verbatim WP.  Charset hardcoded.
	$expiresOffset = 864000; // 10 days
	header("Content-Type: text/javascript; charset=utf-8");
	header("Vary: Accept-Encoding"); // Handle proxies
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expiresOffset) . " GMT");
}

function wp_referer_field() { // [WP4656]
	$ref = attribute_escape($_SERVER['REQUEST_URI']);
	echo '<input type="hidden" name="_wp_http_referer" value="'. $ref . '" />';
	if ( wp_get_original_referer() ) {
		$original_ref = attribute_escape(stripslashes(wp_get_original_referer()));
		echo '<input type="hidden" name="_wp_original_http_referer" value="'. $original_ref . '" />';
	}
}

function wp_original_referer_field() { // [WP4656]
	echo '<input type="hidden" name="_wp_original_http_referer" value="' . attribute_escape(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
}

function wp_get_referer() { // [WP3908]
	foreach ( array($_REQUEST['_wp_http_referer'], $_SERVER['HTTP_REFERER']) as $ref )
		if ( !empty($ref) )
			return $ref;
	return false;
}

function wp_get_original_referer() { // [WP3908]
	if ( !empty($_REQUEST['_wp_original_http_referer']) )
		return $_REQUEST['_wp_original_http_referer'];
	return false;
}

?>
