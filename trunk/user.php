<?php

function _backpress_put_user( &$backpress, $args = null ) {
	global $backpress_user_login_cache;

	if ( !is_backpress( $backpress ) )
		return new WP_Error( 'backpress', __('Invalid BackPress instance') );

	$defaults = array(
		'ID' => false,
		'user_login' => '',
		'user_nicename' => '',
		'user_email' => '',
		'user_url' => '',
		'user_pass' => false,
		'user_registered' => time(),
		'display_name' => '',
		'user_status' => 0
	);

	$args = wp_parse_args( $args, $defaults );

	extract( $args, EXTR_SKIP );

	if ( !is_backpress_id($ID) )
		$ID = (int) $ID;
	
	$user_login = backpress_sanitize_user( $user_login, true );
	$user_nicename = backpress_sanitize_slug( $user_login );
	if ( !$user_login || !$user_nicename )
		return new WP_Error( 'user_login', __('Invalid login name') );

	if ( !$user_email = backpress_is_email( $user_email ) )
		return new WP_Error( 'user_email', __('Invalid email address') );

	$user_url = clean_url( $user_url );

	if ( !$user_pass || strlen($user_pass) < 6 )
		$user_pass = backpress_random_string( 6 );
	$plain_pass = $user_pass;
	$user_pass  = md5( $user_pass );

	if ( !is_numeric($user_registered) )
		$user_registered = backpress_gmt_strtotime( $user_registered );

	if ( !$user_registered || $user_registered < 0 )
		return new WP_Error( 'user_registered', __('Invalid registration time') );

	if ( !$user_registered = @gmdate('Y-m-d H:i:s', $user_registered) )
		return new WP_Error( 'user_registered', __('Invalid registration timestamp') );

	if ( !$display_name )
		$display_name = $user_login;

	$db_return = NULL;
	if ( $ID && NULL !== $backpress->db->get_var("SELECT ID FROM $backpress->users WHERE ID = '$ID'") ) {
		unset($args['ID']);
		unset($args['user_registered']);
		$db_return = $backpress->db->update($backpress->users, $args, array("ID" => $ID));
	}
	if ( $db_return === null ) { 
		$db_return = $backpress->db->insert($backpress->users, $args);
	}
	
	if ( !$db_return )
		return new WP_Error( 'BackPress::query', __('Query failed') );

	// Cache the result
	$user = (object) compact( array_keys($defaults) );
	backpress_append_meta( $backpress, $user );
	$backpress_user_login_cache[$user_login] = $ID;

	$args = compact( array_keys($args) );

	$args['backpress']  = $backpress->id;
	$args['plain_pass'] = $plain_pass;

	do_action( __FUNCTION__, $args );

	return $args;
}

function backpress_new_user( &$backpress, $args = null ) {
	$args = wp_parse_args( $args );
	$args['ID'] = false;

	$r = _backpress_put_user( $backpress, $args );

	if ( is_wp_error($r) )
		return $r;

	$args['backpress']  = $backpress->id;
	do_action( __FUNCTION__, $r, $args );

	return $r;
}

function backpress_update_user( &$backpress, $ID, $args = null ) {
	$args = wp_parse_args( $args );

	$user = backpress_get_user( $backpress, $ID, true, ARRAY_A );
	if ( is_wp_error( $user ) )
		return $user;

	$args = array_merge( $user, $args );
	$args['ID'] = $user['ID'];

	$r = _backpress_put_user( $backpress, $args );

	if ( is_wp_error($r) )
		return $r;

	$args['backpress']  = $backpress->id;
	do_action( __FUNCTION__, $r, $args );

	return $r;
}

// $ID can be user ID#, user_login, or array( ID#s )
function backpress_get_user( &$backpress, $ID = 0, $cache = true, $output = OBJECT ) {
	global $backpress_user_cache, $backpress_user_login_cache;

	if ( !is_backpress( $backpress ) )
		return new WP_Error( 'backpress', __('Invalid BackPress instance') );
	if ( is_numeric($ID) ) {
		$ID = (int) $ID;
		if ( $cache && isset($backpress_user_cache[$ID]) )
			return $backpress_user_cache[$ID];
		$sql = "SELECT * FROM $backpress->users WHERE ID = '%d'";
	} elseif ( is_array($ID) ) { // NO RETURN VALUE - just for cache
		if ( $cache && is_array($backpress_user_cache) )
			$ID = array_diff($ID, array_keys($backpress_user_cache));
		$ID = array_unique($ID);
		$ID = join(',', $ID);
		$ID = preg_replace( '/[^0-9,]/', '', $ID );
		$ID = preg_replace( '/,+/', ',', $ID );
		if ( !$ID )
			return;
		if ( $users = (array) $backpress->get_results( "SELECT * FROM $backpress->users WHERE ID IN ($ID)" ) )
			backpress_append_meta( $backpress, $users );
		$ids = array_flip(explode(',', $ID));
		if ( count($ids) > count($users) ) { // Some of the users don't exist.  Cache that fact.
			foreach ( $users as $user )
				unset($ids[$user->ID]);
			foreach ( $ids as $id => $k)
				$backpress_user_cache[(int) $id] = false;
		}
		return;
	} else {
		$ID = backpress_sanitize_user( $ID );
		if ( $cache && isset($backpress_user_cache[$backpress_user_login_cache[$ID]]) && is_numeric($backpress_user_login_cache[$ID]) )
			return $backpress_user_cache[$backpress_user_login_cache[$ID]];
		$sql = "SELECT * FROM $backpress->users WHERE user_login = '%s'";
	}

	if ( !$ID )
		return new WP_Error( 'ID', __('Invalid user id') );

	$user = $backpress->get_row( $backpress->prepare( $sql, $ID ), $output );

	if ( !$user ) { // Cache non-existant users.
		if ( is_numeric($ID) )
			$backpress_user_cache[$ID] = false;
		else
			$backpress_user_login_cache[$ID] = false;
		return new WP_Error( 'user', __('User does not exist' ) );
	}

	$user = backpress_append_meta( $backpress, $user );

	$backpress_user_login_cache[$user->user_login] = (int) $user->ID;

	return $user;
}

function backpress_delete_user( &$backpress, $ID ) {
	$user = backpress_get_user( $ID );

	if ( is_wp_error( $user ) )
		return $user;

	do_action( 'pre_' . __FUNCTION__, $ID, $backpress->id );

	$r = $backpress->query( $backpress->prepare( "DELETE FROM $backpress->users WHERE ID = '%d'", $user->ID ) );
	$backpress->query( $backpress->prepare( "DELETE FROM $backpress->usermeta WHERE user_id = '%d'", $user->ID ) );

	return $r;
}

// Used for user meta, but can be used for other meta data (such as bbPress' topic meta)
function backpress_append_meta( &$backpress, $object, $args = null ) {
	$defaults = array( 'cache' => 'backpress_user_cache', 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'id_field' => 'ID' );
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( is_array($object) ) :
		$trans = array();
		foreach ( array_keys($object) as $i )
			$trans[$object[$i]->$id_field] =& $object[$i];
		$ids = join(',', array_keys($trans));
		if ( $metas = $backpress->get_results("SELECT $meta_field, meta_key, meta_value FROM {$backpress->$meta_table} WHERE $meta_field IN ($ids)") )
			foreach ( $metas as $meta ) :
				$trans[$meta->$meta_field]->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $backpress->table_prefix) === 0 )
					$trans[$meta->$meta_field]->{substr($meta->meta_key, strlen($backpress->table_prefix))} = maybe_unserialize( $meta->meta_value );
			endforeach;
		foreach ( array_keys($trans) as $i ) {
			$GLOBALS[$cache][(int) $i] = $trans[$i];
			if ( 'backpress_user_cache' == $cache )
				$GLOBALS['backpress_user_login_cache'][$trans[$i]->user_login] = (int) $i;
		}
		return $object;
	elseif ( $object ) :
		if ( $metas = $backpress->get_results("SELECT meta_key, meta_value FROM {$backpress->$meta_table} WHERE $meta_field = '{$object->$id_field}'") )
			foreach ( $metas as $meta ) :
				$object->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $backpress->table_prefix) === 0 )
					$object->{substr($meta->meta_key, strlen($backpress->table_prefix))} = maybe_unserialize( $meta->meta_value );
			endforeach;
		$GLOBALS[$cache][(int) $object->$id_field] = $object;
		if ( 'backpress_user_cache' == $cache )
			$GLOBALS['backpress_user_login_cache'][$object->user_login] = (int) $object->$id_field;
		return $object;
	endif;
}

function backpress_update_meta( &$backpress, $args = null ) {
	$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'global' => false, 'cache' => 'backpress_user_cache', 'meta_table' => 'usermeta', 'meta_field' => 'user_id' );
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( !is_numeric($id) || empty($id) && !$global )
		return false;

	if ( is_null($meta_key) || is_null($meta_value) )
		return false;

	$id = (int) $id;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( 'usermeta' == $meta_table && 'capabilities' == $meta_key )
		$meta_key = $backpress->table_prefix . 'capabilities';

	$meta_tuple = compact('id', 'meta_key', 'meta_value', 'meta_table');
	$meta_tuple = apply_filters( __FUNCTION__, $meta_tuple, $backpress->id );
	extract($meta_tuple, EXTR_OVERWRITE);

	$_meta_value = maybe_serialize( $meta_value );

	$cur = $backpress->get_row( $backpress->prepare( "SELECT * FROM $meta_table WHERE $meta_field = '%d' AND meta_key = '%s'", $id, $meta_key ) );
	if ( !$cur ) {
		$backpress->query( $backpress->prepare(
			"INSERT INTO $table ( $meta_field, meta_key, meta_value ) VALUES ( '%d', '%s', '%s' )",
			$id,
			$meta_key,
			$_meta_value
		) );
	} elseif ( $cur->meta_value != $meta_value ) {
		$backpress->query( $backpress->prepare( 
			"UPDATE $meta_table SET meta_value = '%s' WHERE $meta_field = '%d' AND meta_key = '%s'",
			$_meta_value,
			$id,
			$meta_key
		) );
	}

	if ( isset($GLOBALS[$cache][$id]) ) {
		$GLOBALS[$cache][$id]->{$meta_key} = $meta_value;
		if ( 0 === strpos($meta_key, $backpress->table_prefix) )
			$GLOBALS[$cache][$id]->{substr($meta_key, strlen($backpress->table_prefix))} = $GLOBALS[$cache][$id]->{$meta_key};
	}

	return true;
}

function backpress_delete_meta( &$backpress, $args = null ) {
	$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'global' => false, 'cache' => 'backpress_user_cache', 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'meta_id_field' => 'umeta_id' );
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( !is_numeric($id) || empty($id) && !$global )
		return false;

	if ( is_null($meta_key) )
		return false;

	$id = (int) $id;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);

	$meta_tuple = compact('id', 'meta_key', 'meta_value', 'meta_table');
	$meta_tuple = apply_filters( __FUNCTION__, $meta_tuple, $backpress->id );
	extract($meta_tuple, EXTR_OVERWRITE);

	$_meta_value = is_null($meta_value) ? null : maybe_serialize( $meta_value );

	if ( is_null($_meta_value) )
		$meta_id = $backpress->get_var( $backpress->prepare( "SELECT $meta_id_field FROM $meta_table WHERE $meta_field = '%d' AND meta_key = '%s'", $id, $meta_key ) );
	else
		$meta_id = $backpress->get_var( $backpress->prepare( "SELECT $meta_id_field FROM $meta_table WHERE $meta_field = '%d' AND meta_key = '%s' AND meta_value = '%s'", $id, $meta_key, $_meta_value ) );

	if ( !$meta_id )
		return false;

	if ( is_null($_meta_value) )
		$backpress->query( $backpress->prepare( "DELETE FROM $meta_table WHERE $meta_field = '%d' AND meta_key = '%s'", $id, $meta_key ) );
	else
		$backpress->query( $backpress->prepare( "DELETE FROM $meta_table WHERE $meta_id_field = '%d'", $meta_id ) );

	unset($GLOBALS[$cache][$id]->{$meta_key});
	if ( 0 === strpos($meta_key, $backpress->table_prefix) )
		unset($GLOBALS[$cache][$id]->{substr($meta_key, strlen($backpress->table_prefix))});

	return true;
}

?>
