<?php

// We do not extend HyperDB:
// All BackPress objects share the same HyperDB object
// Keep table names separate from other BackPress objects' table names
class BackPress {
	var $type; // wordpress, bbpress, ...
	var $table_prefix;
	var $id;
	var $cookie;

	var $_option_func;

/*	HyperDB
	var $insert_id;
	var $last_query;
	var $num_queries;
	var $queries;
	var $last_result;
	var $func_call;
	var $rows_affected;
	var $result;
*/

	/**
	 * Constructor for BackPress objects
	 * 
	 * @param string   $type The name of the app: 'wordpress', 'bbpress'
	 * @param string   $table_prefix The main table prefix for this object: 'wp_', 'bb_'
	 * @param array    $tables List of tables this app knows about.  Table names are encoded in array values or in name => dataset key => value pairs array( 'posts', 'comments', 'users' => 'user_dataset' )
	 * @param callback $option_func The function used by your app to return options (or a wrapper).  Must handle 'siteurl', 'admin_url', 'secret', 'gmt_offset', 'db_version'
	 * @param array    $db_args Parameters for (Hyper)DB connection.  See $db_defaults array defined within this function.
	 * @param array    $cookie If present, defines cookie parameters ( domain, path, ... ) and authentication cookie names.  See $cookie_defaults array defined in this function.
	 * @return object  BackPress object.  Globalizes a reference in $GLOBALS['backpresses'][$this->id].  If first BackPress object created, globalizes reference in $GLOBALS['backpress_prime'].  backpress_prime can be used to handle cookies from ALL other backpress objects
	 */
	function BackPress( $type, $table_prefix, $tables, $option_func, $db_args = null, $cookie = false ) {
		global $hyperdb, $db_servers;

		$db_defaults = array(
			'host' => '',
			'localhost' => '', // not required
			'name' => '',
			'user' => '',
			'password' => '',

			'dataset' => 'global',
			'partition' => 0,
			'datacenter' => 'global',
			'read' => 1,
			'write' => 1
		);

		$db_args = wp_parse_args( $db_args, $db_defaults );
		extract( $db_args, EXTR_SKIP );

		// Add to hyperdb's server list
		if ( !isset($db_servers[$dataset]) )
			add_db_server( $dataset, $partition, $datacenter, $read, $write, $host, $localhost, $name, $user, $password );

		// Reference HyperDB and mirror it's useful properties here
		$this->db =& $hyperdb;
		foreach ( array('insert_id', 'last_query', 'num_queries', 'queries', 'last_result', 'func_call', 'rows_affected', 'result') as $prop ) 
			$this->$prop =& $this->db->$prop;

		$this->type = $type;
		$this->table_prefix = $table_prefix;
		$this->id = "$type:$table_prefix";

		if ( is_callable( $option_func ) )
			$this->_option_func = $option_func;

		foreach ( (array) $tables as $k => $v ) {
			if ( is_numeric($k) ) {
				$table = $v;
				$ds = $dataset;
			} else {
				$table = $k;
				$ds = $v;
			}
			$this->$table = $table_prefix . $table;
			add_db_table( $dataset, $table_prefix . $table );
		}

		$this->cookie = false;
		$cookie_defaults = array( 'user' => 'backpress_user', 'pass' => 'backpress_pass', 'path' => '', 'sitepath' => '', 'domain' => '' );
		if ( $cookie || !backpress_cookie_setting( $this ) )
			$this->cookie = wp_parse_args( $cookie, $cookie_defaults );

		$GLOBALS['backpresses'][$this->id] =& $this;
		if ( !isset($GLOBALS['backpress_prime']) )
			$GLOBALS['backpress_prime'] =& $this;
	}

	function option( $option ) {
		return call_user_func( $this->_option_func, $option );
	}

	/* HyperDB */

	function escape( $str ) {
		return $this->db->escape( $str );
	}

	function escape_deep( $array ) {
		return is_array($array) ? array_map(array(&$this, 'escape_deep'), $array) : $this->db->escape( $array );
	}

	function print_error( $str = '' ) {
		return $this->db->print_error( $str );
	}

	function show_errors() {
		$this->db->show_errors();
	}
	
	function hide_errors() {
		$this->db->hide_errors();
	}

	function flush() {
		$this->db->flush();
	}

	function prepare() {
		$args = func_get_args();
		return call_user_func_array( array(&$this->db, 'prepare'), $args );
	}

	function query( $query ) {
		return $this->db->query( $query );
	}

	function get_var( $query = null, $x = 0, $y = 0 ) {
		return $this->db->get_var( $query, $x, $y );
	}

	function get_row( $query = null, $output = OBJECT, $y = 0 ) {
		return $this->db->get_row( $query, $output, $y );
	}

	function get_col( $query = null, $x = 0 ) {
		return $this->db->get_col( $query, $x );
	}

	function get_results( $query = null, $output = OBJECT ) {
		return $this->db->get_results( $query, $output );
	}

	function get_col_info( $info_type = 'name', $col_offset = -1 ) {
		return $this->db->get_col_info( $info_type, $col_offset );
	}

	function bail( $message ) { // Just wraps errors in a nice header and footer
		return $this->db->bail( $message );
	}
}

function is_backpress( $object ) {
	return is_object($object) && is_a($object, 'BackPress');
}

function backpress_cookie_settings( &$backpress ) {
	global $backpress_prime;

	if ( empty($backpress->cookie) && is_backpress($backpress_prime) )
		$bp =& $backpress_prime;
	else
		$bp =& $backpress;

	return $bp->cookie;
}

?>
