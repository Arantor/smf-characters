<?php

if (!defined('SMF'))
	die('No direct access...');

function updateCharacterData($char_id, $data)
{
	global $smcFunc;

	$setString = '';
	$condition = 'id_character = {int:id_character}';
	$parameters = array('id_character' => $char_id);
	foreach ($data as $var => $val)
	{
		$type = 'string';
		if (in_array($var, array('id_theme', 'posts', 'last_active')))
			$type = 'int';

		// Doing an increment?
		if ($type == 'int' && ($val === '+' || $val === '-'))
		{
			$val = $var . ' ' . $val . ' 1';
			$type = 'raw';
		}

		// Ensure posts don't overflow or underflow.
		if (in_array($var, array('posts')))
		{
			if (preg_match('~^' . $var . ' (\+ |- |\+ -)([\d]+)~', $val, $match))
			{
				if ($match[1] != '+ ')
					$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
				$type = 'raw';
			}
		}

		$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
		$parameters['p_' . $var] = $val;
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET' . substr($setString, 0, -1) . '
		WHERE ' . $condition,
		$parameters
	);
}

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
		'integrate_load_member_data',
		'integrate_load_member_data_chars',
		false
	);
	add_integration_function(
		'integrate_member_context',
		'integrate_membercontext_chars',
		false
	);
	add_integration_function(
		'integrate_after_create_post',
		'integrate_character_post_count',
		false
	);
	add_integration_function(
		'integrate_load_permissions',
		'integrate_chars_permissions',
		false,
		'$sourcedir/Admin-Chars.php'
	);
	add_integration_function(
		'integrate_create_board',
		'integrate_chars_create_board',
		false,
		'$sourcedir/Admin-Chars.php'
	);
	add_integration_function(
		'integrate_message_index',
		'integrate_message_index_chars',
		false
	);
	add_integration_function(
		'integrate_display_topic',
		'integrate_display_topic_chars',
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
	global $memberContext, $smcFunc, $txt, $scripturl;

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
		if (!empty($output['member']['characters'][$message['id_character']])) {
			$character = $output['member']['characters'][$message['id_character']];
			if (!empty($character['avatar']))
			{
				$output['member']['avatar'] = array(
					'name' => $character['avatar'],
					'image' => '<img class="avatar" src="' . $character['avatar'] . '" alt="">',
					'href' => $character['avatar'],
					'url' => $character['avatar'],
				);
			}
			$output['member']['signature'] = $character['sig_parsed'];
			$output['member']['posts'] = comma_format($character['posts']);
			$is_online = $message['id_character'] == $output['member']['current_character'];
			$output['member']['online'] = array(
				'is_online' => $is_online,
				'text' => $smcFunc['htmlspecialchars']($txt[$is_online ? 'online' : 'offline']),
				'member_online_text' => sprintf($txt[$is_online ? 'member_is_online' : 'member_is_offline'], $smcFunc['htmlspecialchars']($character['character_name'])),
				'href' => $scripturl . '?action=pm;sa=send;u=' . $message['id_member'],
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $message['id_member'] . '">' . $txt[$is_online ? 'online' : 'offline'] . '</a>',
				'label' => $txt[$is_online ? 'online' : 'offline']
			);
		}
	}
}

function integrate_create_post_character(&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters)
{
	$posterOptions['char_id'] = empty($posterOptions['char_id']) ? 0 : (int) $posterOptions['char_id'];
	$message_columns['id_character'] = 'int';
	$message_parameters[] = $posterOptions['char_id'];
}

function integrate_load_member_data_chars(&$select_columns, &$select_tables, &$set)
{
	if ($set != 'minimal')
		$select_columns .= ', lo.id_character AS online_character';
}

function integrate_membercontext_chars(&$mcUser, $user, $display_custom_fields)
{
	global $user_profile;

	$mcUser['characters'] = !empty($user_profile[$user]['characters']) ? $user_profile[$user]['characters'] : array();
	$mcUser['current_character'] = !empty($user_profile[$user]['online_character']) ? $user_profile[$user]['online_character'] : 0;
}

function integrate_character_post_count($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters) {
	if ($msgOptions['approved'] && !empty($posterOptions['char_id']) && !empty($posterOptions['update_post_count'])) {
		updateCharacterData($posterOptions['char_id'], array('posts' => '+'));
	}
}

function integrate_message_index_chars(&$message_index_selects, &$message_index_tables, &$message_index_parameters)
{
	$message_index_selects[] = 'cf.id_character AS first_character';
	$message_index_selects[] = 'IFNULL(cf.character_name, IFNULL(memf.real_name, mf.poster_name)) AS first_display_name';
	$message_index_selects[] = 'cl.id_character AS last_character';
	$message_index_selects[] = 'IFNULL(cl.character_name, IFNULL(meml.real_name, ml.poster_name)) AS last_display_name';

	$message_index_tables[] = 'LEFT JOIN {db_prefix}characters AS cf ON (cf.id_character = mf.id_character)';
	$message_index_tables[] = 'LEFT JOIN {db_prefix}characters AS cl ON (cl.id_character = ml.id_character)';
}

function integrate_display_topic_chars(&$topic_selects, &$topic_tables, &$topic_parameters)
{
	$topic_selects[] = 'IFNULL(chars.character_name, IFNULL(mem.real_name, ms.poster_name)) AS topic_started_name';
	$topic_tables[] = 'LEFT JOIN {db_prefix}characters AS chars ON (chars.id_character = ms.id_character)';
}

?>