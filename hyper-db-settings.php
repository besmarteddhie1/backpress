<?php

// Where are we? Put new IP substrings here.
$dc_ips = array();
foreach ( $dc_ips as $dc_ip => $dc ) {
	if ( substr($_SERVER['SERVER_ADDR'], 0, strlen($dc_ip)) == $dc_ip ) {
		define( 'DATACENTER', $dc );
		break;
	}
}

if ( !defined('DATACENTER') )
	define('DATACENTER', 'global');

// Database servers grouped by dataset. (Totally tabular, dude!)
// R can be 0 (no reads) or a positive integer indicating the order
// in which to attempt communication (all locals, then all remotes)

// database and user are ignored for blog datasets

//dataset, partition, datacenter, R, W,                 internet host:port,         internal network host:port,      database,          user,            password
//add_db_server('misc', 0, 'lax', 1, 1,  'misc.db.example.com:3722',  'misc.db.example.lan:3722', 'wp-misc', 'miscuser',        'miscpassword');
//add_db_server('global', 0, 'nyc', 1, 1,'global.mysql.example.com:3509','global.mysql.example.lan:3509', 'global-db', 'globaluser',  'globalpassword');


// Three kinds of db connections are supported:
//  1. global (e.g. wp_users)
//  2. partitioned (e.g. wp_data_3e_views)
//  3. external (e.g. misc)

// External tables MUST be listed below with their dataset
// or wpdb with assume they are global and fail to find them.

// Tables in the global and partitioned databases do not have to be listed.

// ** NO DUPLICATE TABLE NAMES ALLOWED **
//add_db_table('misc', 'my_stuff');
//add_db_table('misc', 'hot_data');
//add_db_table('misc', 'bbq_sauces');

?>
