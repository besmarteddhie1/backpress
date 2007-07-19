<?php
function get_locale() {
	global $locale;

	if ( isset($locale) )
		return $locale;

	if ( defined('BACKPRESS_LANG') )
		$locale = BACKPRESS_LANG;

	if ( empty($locale) )
		$locale = 'en_US';

	$locale = apply_filters('locale', $locale);

	return $locale;
}

function translate($text, $domain) {
	global $l10n;

	if (isset($l10n[$domain]))
		return apply_filters('gettext', $l10n[$domain]->translate($text), $text);
	else
		return $text;
}

// Return a translated string.
function __($text, $domain = 'default') {
	return translate($text, $domain);
}

// Echo a translated string.
function _e($text, $domain = 'default') {
	echo translate($text, $domain);
}

function _c($text, $domain = 'default') {
	$whole = translate($text, $domain);
	$last_bar = strrpos($whole, '|');
	if ( false == $last_bar ) {
		return $whole;
	} else {
		return substr($whole, 0, $last_bar);
	}
}

// Return the plural form.
function __ngettext($single, $plural, $number, $domain = 'default') {
	global $l10n;

	if (isset($l10n[$domain])) {
		return apply_filters('ngettext', $l10n[$domain]->ngettext($single, $plural, $number), $single, $plural, $number);
	} else {
		if ($number != 1)
			return $plural;
		else
			return $single;
	}
}

function load_textdomain($domain, $mofile) {
	global $l10n;

	if (isset($l10n[$domain]))
		return;

	if ( is_readable($mofile))
		$input = new CachedFileReader($mofile);
	else
		return;

	$l10n[$domain] = new gettext_reader($input);
}

?>
