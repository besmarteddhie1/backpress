<?php

if ( defined('BACKPRESS_PATH') )
	return;

define( 'BACKPRESS_PATH', dirname(__FILE__) . '/' );

function backpress_load_pluggable() {
	static $loaded = 0;
	if ( !$loaded )
		require( BACKPRESS_PATH . 'pluggable.php' );
	$loaded = 1;
}

require( BACKPRESS_PATH . 'hyper-db.php' );
require( BACKPRESS_PATH . 'hyper-db-settings.php' );
require( BACKPRESS_PATH . 'backpress.php' );

include( BACKPRESS_PATH . 'gettext.php' );
include( BACKPRESS_PATH . 'streams.php' );

require( BACKPRESS_PATH . 'l10n.php' );
require( BACKPRESS_PATH . 'classes.php' );
require( BACKPRESS_PATH . 'compatability.php' );
require( BACKPRESS_PATH . 'formatting.php' );
require( BACKPRESS_PATH . 'functions.php' );
require( BACKPRESS_PATH . 'http.php' );
require( BACKPRESS_PATH . 'plugin-api.php' );
require( BACKPRESS_PATH . 'template.php' );
require( BACKPRESS_PATH . 'user.php' );
require( BACKPRESS_PATH . 'script-loader.php' );
require( BACKPRESS_PATH . 'kses.php' );

$_GET    = backpress_global_sanitize($_GET);
$_POST   = backpress_global_sanitize($_POST);
$_COOKIE = backpress_global_sanitize($_COOKIE, false);
$_SERVER = backpress_global_sanitize($_SERVER);

add_filter( 'pre_backpress_sanitize_slug', 'backpress_sanitize_slug_utf8' );

$wp_header_to_desc = apply_filters( 'wp_header_to_desc_array', array(
	100 => 'Continue',
	101 => 'Switching Protocols',

	200 => 'OK',
	201 => 'Created',
	202 => 'Accepted',
	203 => 'Non-Authoritative Information',
	204 => 'No Content',
	205 => 'Reset Content',
	206 => 'Partial Content',

	300 => 'Multiple Choices',
	301 => 'Moved Permanently',
	302 => 'Found',
	303 => 'See Other',
	304 => 'Not Modified',
	305 => 'Use Proxy',
	307 => 'Temporary Redirect',

	400 => 'Bad Request',
	401 => 'Unauthorized',
	403 => 'Forbidden',
	404 => 'Not Found',
	405 => 'Method Not Allowed',
	406 => 'Not Acceptable',
	407 => 'Proxy Authentication Required',
	408 => 'Request Timeout',
	409 => 'Conflict',
	410 => 'Gone',
	411 => 'Length Required',
	412 => 'Precondition Failed',
	413 => 'Request Entity Too Large',
	414 => 'Request-URI Too Long',
	415 => 'Unsupported Media Type',
	416 => 'Requested Range Not Satisfiable',
	417 => 'Expectation Failed',

	500 => 'Internal Server Error',
	501 => 'Not Implemented',
	502 => 'Bad Gateway',
	503 => 'Service Unavailable',
	504 => 'Gateway Timeout',
	505 => 'HTTP Version Not Supported'
) );

?>
