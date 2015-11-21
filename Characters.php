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
	add_integration_function(
		'integrate_query_message',
		'integrate_get_chars_messages',
		false
	);
	add_integration_function(
		'integrate_prepare_display_context',
		'integrate_display_chars_messages',
		false
	);
	add_integration_function(
		'integrate_create_post',
		'integrate_create_post_character',
		false
	);
	add_integration_function(
		'integrate_member_context',
		'integrate_membercontext_chars',
		false
	);
}

function integrate_remove_logout(&$buttons)
{
	global $context, $scripturl;

	$buttons['logout']['show'] = false;

	// While we're here in setupMenuContext, we might as well do other things we would
	// otherwise have done in that function.
	if (!$context['user']['is_guest'])
	{
		loadCSSFile('chars.css', array('default_theme' => true), 'chars');
		addInlineJavascript('
	user_menus.add("characters", "' . $scripturl . '?action=profile;area=characters_popup");', true);
	}
}

function integrate_get_chars_messages(&$msg_selects, &$msg_tables, &$msg_parameters) {
	$msg_selects[] = 'id_character';
}

function integrate_display_chars_messages(&$output, &$message) {
	global $memberContext;

	$output['id_character'] = $message['id_character'];

	// This is where it gets nasty. The original code pulls a reference to
	// memberContext to save memory. But that doesn't work so well when
	// we have characters, so we have to break the reference and replace it
	// with a copy-by-value.
	if (!empty($output['member']['id']))
	{
		unset ($output['member']);
		$output['member'] = $memberContext[$message['id_member']];
		// Now replace the values we need into the new version.
		if (!empty($message['member']['characters'][$message['id_character']])) {
			$character = $message['member']['characters'][$message['id_character']];
			if (!empty($character['avatar']))
			{
				$output['member']['avatar'] = array(
					'name' => $character['avatar'],
					'image' => '<img class="avatar" src="' . $character['avatar'] . '" alt="">',
					'href' => $character['avatar'],
					'url' => $character['avatar'],
				);
			}
		}
	}
}

function integrate_create_post_character(&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters)
{
	$posterOptions['char_id'] = empty($posterOptions['char_id']) ? 0 : (int) $posterOptions['char_id'];
	$message_columns['id_character'] = 'int';
	$message_parameters[] = $posterOptions['char_id'];
}

function integrate_membercontext_chars(&$mcUser, $user, $display_custom_fields)
{
	global $user_profile;

	$mcUser['characters'] = !empty($user_profile[$user]['characters']) ? $user_profile[$user]['characters'] : array();
}
?>