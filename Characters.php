<?php

if (!defined('SMF'))
	die('No direct access...');

function integrate_chars()
{
	global $user_info, $user_settings, $txt;

	$user_info += array(
		'id_character' => isset($user_settings['id_character']) ? (int) $user_settings['id_character'] : 0,
		'character_name' => isset($user_settings['character_name']) ? $user_settings['character_name'] : (isset($user_settings['real_name']) ? $user_settings['real_name'] : ''),
		'char_avatar' => isset($user_settings['char_avatar']) ? $user_settings['char_avatar'] : '',
		'char_signature' => isset($user_settings['char_signature']) ? $user_settings['char_signature'] : '',
	);

	// And since this is now done early in the process, but after language is identified...
	loadLanguage('characters/Characters');

	// We can now also hook the rest of the characters stuff, meaning we
	// only need to remember and manage one hook in the installer.
	add_integration_function(
		'integrate_profile_areas',
		'chars_profile_menu',
		false,
		'$sourcedir/Profile-Chars.php'
	);
	add_integration_function(
		'integrate_autosuggest',
		'integrate_character_autosuggest',
		false,
		'$sourcedir/AutoSuggest-Chars.php'
	);
	add_integration_function(
		'integrate_menu_buttons',
		'integrate_remove_logout',
		false
	);
}

function integrate_remove_logout(&$buttons)
{
	$buttons['logout']['show'] = false;
}

?>