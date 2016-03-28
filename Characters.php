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
		'char_is_main' => !empty($user_settings['is_main']),
	);

	// And since this is now done early in the process, but after language is identified...
	loadLanguage('characters/Characters');

	// We can now also hook the rest of the characters stuff, meaning we
	// only need to remember and manage one hook in the installer.
	add_integration_function(
		'integrate_pre_profile_areas',
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
	add_integration_function(
		'integrate_search_message_context',
		'integrate_search_message_chars',
		false
	);
	add_integration_function(
		'integrate_actions',
		'integrate_chars_actions',
		false
	);
	add_integration_function(
		'integrate_delete_members',
		'integrate_delete_members_chars',
		false,
		'$sourcedir/Admin-Chars.php'
	);
}

function integrate_chars_actions(&$actionArray)
{
	$actionArray['reattributepost'] = array('Characters.php', 'ReattributePost');
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

function integrate_display_chars_messages(&$output, &$message, $counter) {
	global $memberContext, $smcFunc, $txt, $scripturl, $board_info;

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
			$output['member']['link'] = '<a href="' . $scripturl . '?action=profile;u=' . $message['id_member'] . ';area=characters;char=' . $output['id_character'] . '">' . $character['character_name'] . '</a>';
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

	// Now we indicate whether we can potentially migrate this to another character.
	// But that requires us having characters to migrate to, and follow the OOC/IC rules.
	$output['can_switch_char'] = false;
	if ($board_info['in_character'])
	{
		if (!empty($output['member']['characters'])) {
			$output['possible_characters'] = array();
			foreach ($output['member']['characters'] as $char_id => $char) {
				// We can't switch to the character that already posted it.
				if ($char_id == $message['id_character']) {
					continue;
				}
				// You can't switch it to a main character.
				if ($char['is_main']) {
					continue;
				}
				$output['possible_characters'][$char_id] = $char['character_name'];
			}
			if (!empty($output['possible_characters'])) {
				asort($output['possible_characters']);
			}
		}
		$output['can_switch_char'] = !empty($output['possible_characters']) && $output['can_modify'];
		if (!$output['can_switch_char']) {
			unset ($output['possible_characters']);
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

function integrate_search_message_chars(&$output, &$message, $counter)
{
	global $memberContext, $scripturl, $smcFunc, $txt;

	foreach ($output['matches'] as $match_id => $match)
	{
		if (!empty($match['member']['id']))
		{
			unset ($output['matches'][$match_id]['member']);
			$output['matches'][$match_id]['member'] = $memberContext[$message['id_member']];
			// Now replace the values we need into the new version.
			if (!empty($output['matches'][$match_id]['member']['characters'][$message['id_character']])) {
				$character = $output['matches'][$match_id]['member']['characters'][$message['id_character']];
				if (!empty($character['avatar']))
				{
					$output['matches'][$match_id]['member']['avatar'] = array(
						'name' => $character['avatar'],
						'image' => '<img class="avatar" src="' . $character['avatar'] . '" alt="">',
						'href' => $character['avatar'],
						'url' => $character['avatar'],
					);
				}
				$output['matches'][$match_id]['member']['link'] = '<a href="' . $scripturl . '?action=profile;u=' . $message['id_member'] . ';area=characters;char=' . $message['id_character'] . '">' . $character['character_name'] . '</a>';
				$output['matches'][$match_id]['member']['signature'] = $character['sig_parsed'];
				$output['matches'][$match_id]['member']['posts'] = comma_format($character['posts']);
				$is_online = $message['id_character'] == $output['matches'][$match_id]['member']['current_character'];
				$output['matches'][$match_id]['member']['online'] = array(
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
}

function ReattributePost() {
	global $topic, $smcFunc, $modSettings, $user_info, $board_info;

	// 1. Session check, quick and easy to get out the way before we forget.
	checkSession('get');

	// 2. Check this is an 'in character' board. We don't want this working outside.
	if (!$board_info['in_character'])
		fatal_lang_error('no_access', false);

	// 3. Get the message id and verify that it exists inside the topic in question.
	$msg = isset($_GET['msg']) ? (int) $_GET['msg'] : 0;
	$result = $smcFunc['db_query']('', '
		SELECT t.id_topic, t.locked, t.id_member_started, m.id_member AS id_member_posted,
			m.id_character
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
		WHERE m.id_msg = {int:msg}',
		array(
			'msg' => $msg,
		)
	);

	// 3a. Doesn't exist?
	if ($smcFunc['db_num_rows']($result) == 0)
		fatal_lang_error('no_access', false);

	$row = $smcFunc['db_fetch_assoc']($result);
	$smcFunc['db_free_result']($result);

	// 3b. Not the topic we thought it was?
	if ($row['id_topic'] != $topic)
		fatal_lang_error('no_access', false);

	// 4. Verify we have permission. We loaded $topic's board's permissions earlier.
	// Now verify that we have the relevant powers.
	$is_poster = $user_info['id'] == $row['id_member_posted'];
	$is_topic_starter = $user_info['id'] == $row['id_member_started'];
	$can_modify = (!$row['locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $is_topic_starter) || (allowedTo('modify_own') && $is_poster));
	if (!$can_modify)
		fatal_lang_error('no_access', false);

	// 4. Verify that the requested character belongs to the person we're changing to.
	$character = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(id_character)
		FROM {db_prefix}characters
		WHERE id_character = {int:char}
			AND id_member = {int:member}
			AND is_main = 0',
		array(
			'char' => $character,
			'member' => $row['id_member_posted'],
		)
	);
	list ($owned_char) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if (!$owned_char)
		fatal_lang_error('no_access', false);

	// 5. So we've verified the topic matches the message, the user has power
	// to edit the message, and the message owner's new character exists.
	// Time to reattribute the message!
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_character = {int:char}
		WHERE id_msg = {int:msg}',
		array(
			'char' => $character,
			'msg' => $msg,
		)
	);

	// 6. Having reattributed the post, now let's also fix the post count.
	// If we're supposed to, that is.
	if ($board_info['posts_count'])
	{
		// Subtract one from the post count of the current owner.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}characters
			SET posts = (CASE WHEN posts <= 1 THEN 0 ELSE posts - 1 END)
			WHERE id_character = {int:char}',
			array(
				'char' => $row['id_character'],
			)
		);

		// Add one to the new owner.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}characters
			SET posts = posts + 1
			WHERE id_character = {int:char}',
			array(
				'char' => $character,
			)
		);
	}

	// 7. All done. Exit back to the post.
	redirectexit('topic=' . $topic . '.msg' . $msg . '#msg' . $msg);
}

?>