<?php
// Last sync [WP11537]

/**
 * HTML/XHTML filter that only allows some elements and attributes
 *
 * Added wp_ prefix to avoid conflicts with existing kses users
 *
 * @version 0.2.2
 * @copyright (C) 2002, 2003, 2005
 * @author Ulf Harnhammar <metaur@users.sourceforge.net>
 *
 * @package External
 * @subpackage KSES
 *
 * @internal
 * *** CONTACT INFORMATION ***
 * E-mail:      metaur at users dot sourceforge dot net
 * Web page:    http://sourceforge.net/projects/kses
 * Paper mail:  Ulf Harnhammar
 *              Ymergatan 17 C
 *              753 25  Uppsala
 *              SWEDEN
 *
 * [kses strips evil scripts!]
 */


/**
 * Filters content and keeps only allowable HTML elements.
 *
 * This function makes sure that only the allowed HTML element names, attribute
 * names and attribute values plus only sane HTML entities will occur in
 * $string. You have to remove any slashes from PHP's magic quotes before you
 * call this function.
 *
 * The default allowed protocols are 'http', 'https', 'ftp', 'mailto', 'news',
 * 'irc', 'gopher', 'nntp', 'feed', and finally 'telnet. This covers all common
 * link protocols, except for 'javascript' which should not be allowed for
 * untrusted users.
 *
 * @since 1.0.0
 *
 * @param string $string Content to filter through kses
 * @param array $allowed_html List of allowed HTML elements
 * @param array $allowed_protocols Optional. Allowed protocol in links.
 * @return string Filtered content with only allowed HTML elements
 */
function wp_kses($string, $allowed_html, $allowed_protocols = array ('http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet')) {
	$string = wp_kses_no_null($string);
	$string = wp_kses_js_entities($string);
	$string = wp_kses_normalize_entities($string);
	$allowed_html_fixed = wp_kses_array_lc($allowed_html);
	$string = wp_kses_hook($string, $allowed_html_fixed, $allowed_protocols); // WP changed the order of these funcs and added args to wp_kses_hook
	return wp_kses_split($string, $allowed_html_fixed, $allowed_protocols);
}

/**
 * You add any kses hooks here.
 *
 * There is currently only one kses WordPress hook and it is called here. All
 * parameters are passed to the hooks and expected to recieve a string.
 *
 * @since 1.0.0
 *
 * @param string $string Content to filter through kses
 * @param array $allowed_html List of allowed HTML elements
 * @param array $allowed_protocols Allowed protocol in links
 * @return string Filtered content through 'pre_kses' hook
 */
function wp_kses_hook($string, $allowed_html, $allowed_protocols) {
	$string = apply_filters('pre_kses', $string, $allowed_html, $allowed_protocols);
	return $string;
}

/**
 * This function returns kses' version number.
 *
 * @since 1.0.0
 *
 * @return string KSES Version Number
 */
function wp_kses_version() {
	return '0.2.2';
}

/**
 * Searches for HTML tags, no matter how malformed.
 *
 * It also matches stray ">" characters.
 *
 * @since 1.0.0
 *
 * @param string $string Content to filter
 * @param array $allowed_html Allowed HTML elements
 * @param array $allowed_protocols Allowed protocols to keep
 * @return string Content with fixed HTML tags
 */
function wp_kses_split($string, $allowed_html, $allowed_protocols) {
	global $pass_allowed_html, $pass_allowed_protocols;
	$pass_allowed_html = $allowed_html;
	$pass_allowed_protocols = $allowed_protocols;
	return preg_replace_callback('%((<!--.*?(-->|$))|(<[^>]*(>|$)|>))%',
		create_function('$match', 'global $pass_allowed_html, $pass_allowed_protocols; return wp_kses_split2($match[1], $pass_allowed_html, $pass_allowed_protocols);'), $string);
}

/**
 * Callback for wp_kses_split for fixing malformed HTML tags.
 *
 * This function does a lot of work. It rejects some very malformed things like
 * <:::>. It returns an empty string, if the element isn't allowed (look ma, no
 * strip_tags()!). Otherwise it splits the tag into an element and an attribute
 * list.
 *
 * After the tag is split into an element and an attribute list, it is run
 * through another filter which will remove illegal attributes and once that is
 * completed, will be returned.
 *
 * @access private
 * @since 1.0.0
 * @uses wp_kses_attr()
 *
 * @param string $string Content to filter
 * @param array $allowed_html Allowed HTML elements
 * @param array $allowed_protocols Allowed protocols to keep
 * @return string Fixed HTML element
 */
function wp_kses_split2($string, $allowed_html, $allowed_protocols) {
	$string = wp_kses_stripslashes($string);

	if (substr($string, 0, 1) != '<')
		return '&gt;';
	# It matched a ">" character

	if (preg_match('%^<!--(.*?)(-->)?$%', $string, $matches)) {
		$string = str_replace(array('<!--', '-->'), '', $matches[1]);
		while ( $string != $newstring = wp_kses($string, $allowed_html, $allowed_protocols) )
			$string = $newstring;
		if ( $string == '' )
			return '';
		// prevent multiple dashes in comments
		$string = preg_replace('/--+/', '-', $string);
		// prevent three dashes closing a comment
		$string = preg_replace('/-$/', '', $string);
		return "<!--{$string}-->";
	}
	# Allow HTML comments

	if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9]+)([^>]*)>?$%', $string, $matches))
		return '';
	# It's seriously malformed

	$slash = trim($matches[1]);
	$elem = $matches[2];
	$attrlist = $matches[3];

	if (!@isset($allowed_html[strtolower($elem)]))
		return '';
	# They are using a not allowed HTML element

	if ($slash != '')
		return "<$slash$elem>";
	# No attributes are allowed for closing elements

	return wp_kses_attr("$slash$elem", $attrlist, $allowed_html, $allowed_protocols);
}

/**
 * Removes all attributes, if none are allowed for this element.
 *
 * If some are allowed it calls wp_kses_hair() to split them further, and then
 * it builds up new HTML code from the data that kses_hair() returns. It also
 * removes "<" and ">" characters, if there are any left. One more thing it does
 * is to check if the tag has a closing XHTML slash, and if it does, it puts one
 * in the returned code as well.
 *
 * @since 1.0.0
 *
 * @param string $element HTML element/tag
 * @param string $attr HTML attributes from HTML element to closing HTML element tag
 * @param array $allowed_html Allowed HTML elements
 * @param array $allowed_protocols Allowed protocols to keep
 * @return string Sanitized HTML element
 */
function wp_kses_attr($element, $attr, $allowed_html, $allowed_protocols) {
	# Is there a closing XHTML slash at the end of the attributes?

	$xhtml_slash = '';
	if (preg_match('%\s/\s*$%', $attr))
		$xhtml_slash = ' /';

	# Are any attributes allowed at all for this element?

	if (@ count($allowed_html[strtolower($element)]) == 0)
		return "<$element$xhtml_slash>";

	# Split it

	$attrarr = wp_kses_hair($attr, $allowed_protocols);

	# Go through $attrarr, and save the allowed attributes for this element
	# in $attr2

	$attr2 = '';

	foreach ($attrarr as $arreach) {
		if (!@ isset ($allowed_html[strtolower($element)][strtolower($arreach['name'])]))
			continue; # the attribute is not allowed

		$current = $allowed_html[strtolower($element)][strtolower($arreach['name'])];
		if ($current == '')
			continue; # the attribute is not allowed

		if (!is_array($current))
			$attr2 .= ' '.$arreach['whole'];
		# there are no checks

		else {
			# there are some checks
			$ok = true;
			foreach ($current as $currkey => $currval)
				if (!wp_kses_check_attr_val($arreach['value'], $arreach['vless'], $currkey, $currval)) {
					$ok = false;
					break;
				}

			if ($ok)
				$attr2 .= ' '.$arreach['whole']; # it passed them
		} # if !is_array($current)
	} # foreach

	# Remove any "<" or ">" characters

	$attr2 = preg_replace('/[<>]/', '', $attr2);

	return "<$element$attr2$xhtml_slash>";
}

/**
 * Builds an attribute list from string containing attributes.
 *
 * This function does a lot of work. It parses an attribute list into an array
 * with attribute data, and tries to do the right thing even if it gets weird
 * input. It will add quotes around attribute values that don't have any quotes
 * or apostrophes around them, to make it easier to produce HTML code that will
 * conform to W3C's HTML specification. It will also remove bad URL protocols
 * from attribute values.  It also reduces duplicate attributes by using the
 * attribute defined first (foo='bar' foo='baz' will result in foo='bar').
 *
 * @since 1.0.0
 *
 * @param string $attr Attribute list from HTML element to closing HTML element tag
 * @param array $allowed_protocols Allowed protocols to keep
 * @return array List of attributes after parsing
 */
function wp_kses_hair($attr, $allowed_protocols) {
	$attrarr = array ();
	$mode = 0;
	$attrname = '';
	$uris = array('xmlns', 'profile', 'href', 'src', 'cite', 'classid', 'codebase', 'data', 'usemap', 'longdesc', 'action');

	# Loop through the whole attribute list

	while (strlen($attr) != 0) {
		$working = 0; # Was the last operation successful?

		switch ($mode) {
			case 0 : # attribute name, href for instance

				if (preg_match('/^([-a-zA-Z]+)/', $attr, $match)) {
					$attrname = $match[1];
					$working = $mode = 1;
					$attr = preg_replace('/^[-a-zA-Z]+/', '', $attr);
				}

				break;

			case 1 : # equals sign or valueless ("selected")

				if (preg_match('/^\s*=\s*/', $attr)) # equals sign
					{
					$working = 1;
					$mode = 2;
					$attr = preg_replace('/^\s*=\s*/', '', $attr);
					break;
				}

				if (preg_match('/^\s+/', $attr)) # valueless
					{
					$working = 1;
					$mode = 0;
					if(FALSE === array_key_exists($attrname, $attrarr)) {
						$attrarr[$attrname] = array ('name' => $attrname, 'value' => '', 'whole' => $attrname, 'vless' => 'y');
					}
					$attr = preg_replace('/^\s+/', '', $attr);
				}

				break;

			case 2 : # attribute value, a URL after href= for instance

				if (preg_match('/^"([^"]*)"(\s+|$)/', $attr, $match))
					# "value"
					{
					$thisval = $match[1];
					if ( in_array($attrname, $uris) )
						$thisval = wp_kses_bad_protocol($thisval, $allowed_protocols);

					if(FALSE === array_key_exists($attrname, $attrarr)) {
						$attrarr[$attrname] = array ('name' => $attrname, 'value' => $thisval, 'whole' => "$attrname=\"$thisval\"", 'vless' => 'n');
					}
					$working = 1;
					$mode = 0;
					$attr = preg_replace('/^"[^"]*"(\s+|$)/', '', $attr);
					break;
				}

				if (preg_match("/^'([^']*)'(\s+|$)/", $attr, $match))
					# 'value'
					{
					$thisval = $match[1];
					if ( in_array($attrname, $uris) )
						$thisval = wp_kses_bad_protocol($thisval, $allowed_protocols);

					if(FALSE === array_key_exists($attrname, $attrarr)) {
						$attrarr[$attrname] = array ('name' => $attrname, 'value' => $thisval, 'whole' => "$attrname='$thisval'", 'vless' => 'n');
					}
					$working = 1;
					$mode = 0;
					$attr = preg_replace("/^'[^']*'(\s+|$)/", '', $attr);
					break;
				}

				if (preg_match("%^([^\s\"']+)(\s+|$)%", $attr, $match))
					# value
					{
					$thisval = $match[1];
					if ( in_array($attrname, $uris) )
						$thisval = wp_kses_bad_protocol($thisval, $allowed_protocols);

					if(FALSE === array_key_exists($attrname, $attrarr)) {
						$attrarr[$attrname] = array ('name' => $attrname, 'value' => $thisval, 'whole' => "$attrname=\"$thisval\"", 'vless' => 'n');
					}
					# We add quotes to conform to W3C's HTML spec.
					$working = 1;
					$mode = 0;
					$attr = preg_replace("%^[^\s\"']+(\s+|$)%", '', $attr);
				}

				break;
		} # switch

		if ($working == 0) # not well formed, remove and try again
		{
			$attr = wp_kses_html_error($attr);
			$mode = 0;
		}
	} # while

	if ($mode == 1 && FALSE === array_key_exists($attrname, $attrarr))
		# special case, for when the attribute list ends with a valueless
		# attribute like "selected"
		$attrarr[$attrname] = array ('name' => $attrname, 'value' => '', 'whole' => $attrname, 'vless' => 'y');

	return $attrarr;
}

/**
 * Performs different checks for attribute values.
 *
 * The currently implemented checks are "maxlen", "minlen", "maxval", "minval"
 * and "valueless" with even more checks to come soon.
 *
 * @since 1.0.0
 *
 * @param string $value Attribute value
 * @param string $vless Whether the value is valueless or not. Use 'y' or 'n'
 * @param string $checkname What $checkvalue is checking for.
 * @param mixed $checkvalue What constraint the value should pass
 * @return bool Whether check passes (true) or not (false)
 */
function wp_kses_check_attr_val($value, $vless, $checkname, $checkvalue) {
	$ok = true;

	switch (strtolower($checkname)) {
		case 'maxlen' :
			# The maxlen check makes sure that the attribute value has a length not
			# greater than the given value. This can be used to avoid Buffer Overflows
			# in WWW clients and various Internet servers.

			if (strlen($value) > $checkvalue)
				$ok = false;
			break;

		case 'minlen' :
			# The minlen check makes sure that the attribute value has a length not
			# smaller than the given value.

			if (strlen($value) < $checkvalue)
				$ok = false;
			break;

		case 'maxval' :
			# The maxval check does two things: it checks that the attribute value is
			# an integer from 0 and up, without an excessive amount of zeroes or
			# whitespace (to avoid Buffer Overflows). It also checks that the attribute
			# value is not greater than the given value.
			# This check can be used to avoid Denial of Service attacks.

			if (!preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/', $value))
				$ok = false;
			if ($value > $checkvalue)
				$ok = false;
			break;

		case 'minval' :
			# The minval check checks that the attribute value is a positive integer,
			# and that it is not smaller than the given value.

			if (!preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/', $value))
				$ok = false;
			if ($value < $checkvalue)
				$ok = false;
			break;

		case 'valueless' :
			# The valueless check checks if the attribute has a value
			# (like <a href="blah">) or not (<option selected>). If the given value
			# is a "y" or a "Y", the attribute must not have a value.
			# If the given value is an "n" or an "N", the attribute must have one.

			if (strtolower($checkvalue) != $vless)
				$ok = false;
			break;
	} # switch

	return $ok;
}

/**
 * Sanitize string from bad protocols.
 *
 * This function removes all non-allowed protocols from the beginning of
 * $string. It ignores whitespace and the case of the letters, and it does
 * understand HTML entities. It does its work in a while loop, so it won't be
 * fooled by a string like "javascript:javascript:alert(57)".
 *
 * @since 1.0.0
 *
 * @param string $string Content to filter bad protocols from
 * @param array $allowed_protocols Allowed protocols to keep
 * @return string Filtered content
 */
function wp_kses_bad_protocol($string, $allowed_protocols) {
	$string = wp_kses_no_null($string);
	$string = preg_replace('/\xad+/', '', $string); # deals with Opera "feature"
	$string2 = $string.'a';

	while ($string != $string2) {
		$string2 = $string;
		$string = wp_kses_bad_protocol_once($string, $allowed_protocols);
	} # while

	return $string;
}

/**
 * Removes any NULL characters in $string.
 *
 * @since 1.0.0
 *
 * @param string $string
 * @return string
 */
function wp_kses_no_null($string) {
	$string = preg_replace('/\0+/', '', $string);
	$string = preg_replace('/(\\\\0)+/', '', $string);

	return $string;
}

/**
 * Strips slashes from in front of quotes.
 *
 * This function changes the character sequence  \"  to just  ". It leaves all
 * other slashes alone. It's really weird, but the quoting from
 * preg_replace(//e) seems to require this.
 *
 * @since 1.0.0
 *
 * @param string $string String to strip slashes
 * @return string Fixed strings with quoted slashes
 */
function wp_kses_stripslashes($string) {
	return preg_replace('%\\\\"%', '"', $string);
}

/**
 * Goes through an array and changes the keys to all lower case.
 *
 * @since 1.0.0
 *
 * @param array $inarray Unfiltered array
 * @return array Fixed array with all lowercase keys
 */
function wp_kses_array_lc($inarray) {
	$outarray = array ();

	foreach ( (array) $inarray as $inkey => $inval) {
		$outkey = strtolower($inkey);
		$outarray[$outkey] = array ();

		foreach ( (array) $inval as $inkey2 => $inval2) {
			$outkey2 = strtolower($inkey2);
			$outarray[$outkey][$outkey2] = $inval2;
		} # foreach $inval
	} # foreach $inarray

	return $outarray;
}

/**
 * Removes the HTML JavaScript entities found in early versions of Netscape 4.
 *
 * @since 1.0.0
 *
 * @param string $string
 * @return string
 */
function wp_kses_js_entities($string) {
	return preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $string);
}

/**
 * Handles parsing errors in wp_kses_hair().
 *
 * The general plan is to remove everything to and including some whitespace,
 * but it deals with quotes and apostrophes as well.
 *
 * @since 1.0.0
 *
 * @param string $string
 * @return string
 */
function wp_kses_html_error($string) {
	return preg_replace('/^("[^"]*("|$)|\'[^\']*(\'|$)|\S)*\s*/', '', $string);
}

/**
 * Sanitizes content from bad protocols and other characters.
 *
 * This function searches for URL protocols at the beginning of $string, while
 * handling whitespace and HTML entities.
 *
 * @since 1.0.0
 *
 * @param string $string Content to check for bad protocols
 * @param string $allowed_protocols Allowed protocols
 * @return string Sanitized content
 */
function wp_kses_bad_protocol_once($string, $allowed_protocols) {
	global $_kses_allowed_protocols;
	$_kses_allowed_protocols = $allowed_protocols;

	$string2 = preg_split('/:|&#58;|&#x3a;/i', $string, 2);
	if ( isset($string2[1]) && !preg_match('%/\?%', $string2[0]) )
		$string = wp_kses_bad_protocol_once2($string2[0]) . trim($string2[1]);
	else
		$string = preg_replace_callback('/^((&[^;]*;|[\sA-Za-z0-9])*)'.'(:|&#58;|&#[Xx]3[Aa];)\s*/', 'wp_kses_bad_protocol_once2', $string);

	return $string;
}

/**
 * Callback for wp_kses_bad_protocol_once() regular expression.
 *
 * This function processes URL protocols, checks to see if they're in the
 * white-list or not, and returns different data depending on the answer.
 *
 * @access private
 * @since 1.0.0
 *
 * @param mixed $matches string or preg_replace_callback() matches array to check for bad protocols
 * @return string Sanitized content
 */
function wp_kses_bad_protocol_once2($matches) {
	global $_kses_allowed_protocols;

	if ( is_array($matches) ) {
		if ( ! isset($matches[1]) || empty($matches[1]) )
			return '';

		$string = $matches[1];
	} else {
		$string = $matches;
	}

	$string2 = wp_kses_decode_entities($string);
	$string2 = preg_replace('/\s/', '', $string2);
	$string2 = wp_kses_no_null($string2);
	$string2 = preg_replace('/\xad+/', '', $string2);
	# deals with Opera "feature"
	$string2 = strtolower($string2);

	$allowed = false;
	foreach ( (array) $_kses_allowed_protocols as $one_protocol)
		if (strtolower($one_protocol) == $string2) {
			$allowed = true;
			break;
		}

	if ($allowed)
		return "$string2:";
	else
		return '';
}

/**
 * Converts and fixes HTML entities.
 *
 * This function normalizes HTML entities. It will convert "AT&T" to the correct
 * "AT&amp;T", "&#00058;" to "&#58;", "&#XYZZY;" to "&amp;#XYZZY;" and so on.
 *
 * @since 1.0.0
 *
 * @param string $string Content to normalize entities
 * @return string Content with normalized entities
 */
function wp_kses_normalize_entities($string) {
	# Disarm all entities by converting & to &amp;

	$string = str_replace('&', '&amp;', $string);

	# Change back the allowed entities in our entity whitelist

	$string = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]{0,19});/', '&\\1;', $string);
	$string = preg_replace_callback('/&amp;#0*([0-9]{1,5});/', 'wp_kses_normalize_entities2', $string);
	$string = preg_replace_callback('/&amp;#([Xx])0*(([0-9A-Fa-f]{2}){1,2});/', 'wp_kses_normalize_entities3', $string);

	return $string;
}

/**
 * Callback for wp_kses_normalize_entities() regular expression.
 *
 * This function helps wp_kses_normalize_entities() to only accept 16 bit values
 * and nothing more for &#number; entities.
 *
 * @access private
 * @since 1.0.0
 *
 * @param array $matches preg_replace_callback() matches array
 * @return string Correctly encoded entity
 */
function wp_kses_normalize_entities2($matches) {
	if ( ! isset($matches[1]) || empty($matches[1]) )
		return '';

	$i = $matches[1];
	return ( ( ! valid_unicode($i) ) || ($i > 65535) ? "&amp;#$i;" : "&#$i;" );
}

/**
 * Callback for wp_kses_normalize_entities() for regular expression.
 *
 * This function helps wp_kses_normalize_entities() to only accept valid Unicode
 * numeric entities in hex form.
 *
 * @access private
 *
 * @param array $matches preg_replace_callback() matches array
 * @return string Correctly encoded entity
 */
function wp_kses_normalize_entities3($matches) {
	if ( ! isset($matches[2]) || empty($matches[2]) )
		return '';

	$hexchars = $matches[2];
	return ( ( ! valid_unicode(hexdec($hexchars)) ) ? "&amp;#x$hexchars;" : "&#x$hexchars;" );
}

/**
 * Helper function to determine if a Unicode value is valid.
 *
 * @param int $i Unicode value
 * @return bool true if the value was a valid Unicode number
 */
function valid_unicode($i) {
	return ( $i == 0x9 || $i == 0xa || $i == 0xd ||
			($i >= 0x20 && $i <= 0xd7ff) ||
			($i >= 0xe000 && $i <= 0xfffd) ||
			($i >= 0x10000 && $i <= 0x10ffff) );
}

/**
 * Convert all entities to their character counterparts.
 *
 * This function decodes numeric HTML entities (&#65; and &#x41;). It doesn't do
 * anything with other entities like &auml;, but we don't need them in the URL
 * protocol whitelisting system anyway.
 *
 * @since 1.0.0
 *
 * @param string $string Content to change entities
 * @return string Content after decoded entities
 */
function wp_kses_decode_entities($string) {
	$string = preg_replace_callback('/&#([0-9]+);/', create_function('$match', 'return chr($match[1]);'), $string);
	$string = preg_replace_callback('/&#[Xx]([0-9A-Fa-f]+);/', create_function('$match', 'return chr(hexdec($match[1]));'), $string);

	return $string;
}
