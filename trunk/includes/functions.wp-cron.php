<?php
/**
 * WordPress CRON API
 *
 * @package WordPress
 */



if (!class_exists('BP_Options'))
	die('BP_Options class has not been loaded for this application');



/**
 * Schedules a hook to run only once.
 *
 * Schedules a hook which will be executed once by the Wordpress actions core at
 * a time which you specify. The action will fire off when someone visits your
 * WordPress site, if the schedule time has passed.
 *
 * @since 2.1.0
 * @link http://codex.wordpress.org/Function_Reference/wp_schedule_single_event
 *
 * @param int $timestamp Timestamp for when to run the event.
 * @param callback $hook Function or method to call, when cron is run.
 * @param array $args Optional. Arguments to pass to the hook function.
 */
function wp_schedule_single_event( $timestamp, $hook, $args = array()) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	$crons[$timestamp][$hook][$key] = array( 'schedule' => false, 'args' => $args );
	uksort( $crons, "strnatcasecmp" );
	_set_cron_array( $crons );
}

/**
 * Schedule a periodic event.
 *
 * Schedules a hook which will be executed by the WordPress actions core on a
 * specific interval, specified by you. The action will trigger when someone
 * visits your WordPress site, if the scheduled time has passed.
 *
 * @since 2.1.0
 *
 * @param int $timestamp Timestamp for when to run the event.
 * @param string $recurrence How often the event should recur.
 * @param callback $hook Function or method to call, when cron is run.
 * @param array $args Optional. Arguments to pass to the hook function.
 * @return bool|null False on failure, null when complete with scheduling event.
 */
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array()) {
	$crons = _get_cron_array();
	$schedules = wp_get_schedules();
	$key = md5(serialize($args));
	if ( !isset( $schedules[$recurrence] ) )
		return false;
	$crons[$timestamp][$hook][$key] = array( 'schedule' => $recurrence, 'args' => $args, 'interval' => $schedules[$recurrence]['interval'] );
	uksort( $crons, "strnatcasecmp" );
	_set_cron_array( $crons );
}

/**
 * Reschedule a recurring event.
 *
 * @since 2.1.0
 *
 * @param int $timestamp Timestamp for when to run the event.
 * @param string $recurrence How often the event should recur.
 * @param callback $hook Function or method to call, when cron is run.
 * @param array $args Optional. Arguments to pass to the hook function.
 * @return bool|null False on failure. Null when event is rescheduled.
 */
function wp_reschedule_event( $timestamp, $recurrence, $hook, $args = array()) {
	$crons = _get_cron_array();
	$schedules = wp_get_schedules();
	$key = md5(serialize($args));
	$interval = 0;

	// First we try to get it from the schedule
	if ( 0 == $interval )
		$interval = $schedules[$recurrence]['interval'];
	// Now we try to get it from the saved interval in case the schedule disappears
	if ( 0 == $interval )
		$interval = $crons[$timestamp][$hook][$key]['interval'];
	// Now we assume something is wrong and fail to schedule
	if ( 0 == $interval )
		return false;

	while ( $timestamp < time() + 1 )
		$timestamp += $interval;

	wp_schedule_event( $timestamp, $recurrence, $hook, $args );
}

/**
 * Unschedule a previously scheduled cron job.
 *
 * The $timestamp and $hook parameters are required, so that the event can be
 * identified.
 *
 * @since 2.1.0
 *
 * @param int $timestamp Timestamp for when to run the event.
 * @param callback $hook Function or method to call, when cron is run.
 * @param array $args Optional. Arguments to pass to the hook function.
 */
function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	unset( $crons[$timestamp][$hook][$key] );
	if ( empty($crons[$timestamp][$hook]) )
		unset( $crons[$timestamp][$hook] );
	if ( empty($crons[$timestamp]) )
		unset( $crons[$timestamp] );
	_set_cron_array( $crons );
}

/**
 * Unschedule all cron jobs attached to a specific hook.
 *
 * @since 2.1.0
 *
 * @param callback $hook Function or method to call, when cron is run.
 * @param mixed $args,... Optional. Event arguments.
 */
function wp_clear_scheduled_hook( $hook ) {
	$args = array_slice( func_get_args(), 1 );

	while ( $timestamp = wp_next_scheduled( $hook, $args ) )
		wp_unschedule_event( $timestamp, $hook, $args );
}

/**
 * Retrieve the next timestamp for a cron event.
 *
 * @since 2.1.0
 *
 * @param callback $hook Function or method to call, when cron is run.
 * @param array $args Optional. Arguments to pass to the hook function.
 * @return bool|int The UNIX timestamp of the next time the scheduled event will occur.
 */
function wp_next_scheduled( $hook, $args = array() ) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	if ( empty($crons) )
		return false;
	foreach ( $crons as $timestamp => $cron ) {
		if ( isset( $cron[$hook][$key] ) )
			return $timestamp;
	}
	return false;
}

/**
 * Send request to run cron through HTTP request that doesn't halt page loading.
 *
 * @since 2.1.0
 *
 * @return null Cron could not be spawned, because it is not needed to run.
 */
function spawn_cron() {
	$crons = _get_cron_array();

	if ( !is_array($crons) )
		return;

	$keys = array_keys( $crons );
	if ( array_shift( $keys ) > time() )
		return;

	$cron_url = BP_Options::get('cron_uri');

	wp_remote_post($cron_url, array('timeout' => 0.01, 'blocking' => false));
}

/**
 * Run scheduled callbacks or spawn cron for all scheduled events.
 *
 * @since 2.1.0
 *
 * @return null When doesn't need to run Cron.
 */
function wp_cron() {
	// Prevent infinite loops caused by cron page requesting itself
	$request_uri = parse_url($_SERVER['REQUEST_URI']);
	$cron_uri = parse_url(BP_Options::get('cron_uri'));
	
	if ( $request_uri['host'] == $cron_uri['host'] && $request_uri['path'] == $cron_uri['path'] )
		return;

	$crons = _get_cron_array();

	if ( !is_array($crons) )
		return;

	$keys = array_keys( $crons );
	if ( isset($keys[0]) && $keys[0] > time() )
		return;

	$schedules = wp_get_schedules();
	foreach ( $crons as $timestamp => $cronhooks ) {
		if ( $timestamp > time() ) break;
		foreach ( (array) $cronhooks as $hook => $args ) {
			if ( isset($schedules[$hook]['callback']) && !call_user_func( $schedules[$hook]['callback'] ) )
				continue;
			spawn_cron();
			break 2;
		}
	}
}

/**
 * Retrieve supported and filtered Cron recurrences.
 *
 * The supported recurrences are 'hourly' and 'daily'. A plugin may add more by
 * hooking into the 'cron_schedules' filter. The filter accepts an array of
 * arrays. The outer array has a key that is the name of the schedule or for
 * example 'weekly'. The value is an array with two keys, one is 'interval' and
 * the other is 'display'.
 *
 * The 'interval' is a number in seconds of when the cron job should run. So for
 * 'hourly', the time is 3600 or 60*60. For weekly, the value would be
 * 60*60*24*7 or 604800. The value of 'interval' would then be 604800.
 *
 * The 'display' is the description. For the 'weekly' key, the 'display' would
 * be <code>__('Once Weekly')</code>.
 *
 * For your plugin, you will be passed an array. you can easily add your
 * schedule by doing the following.
 * <code>
 * // filter parameter variable name is 'array'
 *	$array['weekly'] = array(
 *		'interval' => 604800,
 *		'display' => __('Once Weekly')
 *	);
 * </code>
 *
 * @since 2.1.0
 *
 * @return array
 */
function wp_get_schedules() {
	$schedules = array(
		'hourly' => array( 'interval' => 3600, 'display' => __('Once Hourly') ),
		'twicedaily' => array( 'interval' => 43200, 'display' => __('Twice Daily') ),
		'daily' => array( 'interval' => 86400, 'display' => __('Once Daily') ),
	);
	return array_merge( apply_filters( 'cron_schedules', array() ), $schedules );
}

/**
 * Retrieve Cron schedule for hook with arguments.
 *
 * @since 2.1.0
 *
 * @param callback $hook Function or method to call, when cron is run.
 * @param array $args Optional. Arguments to pass to the hook function.
 * @return string|bool False, if no schedule. Schedule on success.
 */
function wp_get_schedule($hook, $args = array()) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	if ( empty($crons) )
		return false;
	foreach ( $crons as $timestamp => $cron ) {
		if ( isset( $cron[$hook][$key] ) )
			return $cron[$hook][$key]['schedule'];
	}
	return false;
}

//
// Private functions
//

/**
 * Retrieve cron info array option.
 *
 * @since 2.1.0
 * @access private
 *
 * @return array CRON info array.
 */
function _get_cron_array()  {
	$cron = get_option('cron');
	if ( ! is_array($cron) )
		return false;

	if ( !isset($cron['version']) )
		$cron = _upgrade_cron_array($cron);

	unset($cron['version']);

	return $cron;
}

/**
 * Updates the CRON option with the new CRON array.
 *
 * @since 2.1.0
 * @access private
 *
 * @param array $cron Cron info array from {@link _get_cron_array()}.
 */
function _set_cron_array($cron) {
	$cron['version'] = 2;
	update_option( 'cron', $cron );
}

/**
 * Upgrade a Cron info array.
 *
 * This function upgrades the Cron info array to version 2.
 *
 * @since 2.1.0
 * @access private
 *
 * @param array $cron Cron info array from {@link _get_cron_array()}.
 * @return array An upgraded Cron info array.
 */
function _upgrade_cron_array($cron) {
	if ( isset($cron['version']) && 2 == $cron['version'])
		return $cron;

	$new_cron = array();

	foreach ( (array) $cron as $timestamp => $hooks) {
		foreach ( (array) $hooks as $hook => $args ) {
			$key = md5(serialize($args['args']));
			$new_cron[$timestamp][$hook][$key] = $args;
		}
	}

	$new_cron['version'] = 2;
	update_option( 'cron', $new_cron );
	return $new_cron;
}

?>
