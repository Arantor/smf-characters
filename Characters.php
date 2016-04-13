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

function removeCharactersFromGroups($characters, $groups)
{
	global $smcFunc, $sourcedir, $modSettings;

	updateSettings(array('settings_updated' => time()));

	if (!is_array($characters))
		$characters = array((int) $characters);
	else
		$characters = array_unique(array_map('intval', $characters));

	if (!is_array($groups))
		$groups = array((int) $groups);
	else
		$groups = array_unique(array_map('intval', $groups));

	$groups = array_diff($groups, array(-1, 0, 3));

	// Check against protected groups
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:current_group})',
			array(
				'current_group' => $groups,
			)
		);
		$protected = array();
		while ($row = $smcFunc['db_fetch_row']($request))
			$protected[] = $row[0];
		$smcFunc['db_free_result']($request);

		$groups = array_diff($groups, $protected);
	}

	if (empty($groups) || empty($characters))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:current_group})',
		array(
			'current_group' => $groups,
		)
	);
	$group_names = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// First, reset those who have this as their primary group - this is the easy one.
	$log_inserts = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_character, character_name, main_char_group
		FROM {db_prefix}characters AS characters
		WHERE main_char_group IN ({array_int:group_list})
			AND id_character IN ({array_int:char_list})',
		array(
			'group_list' => $groups,
			'char_list' => $characters,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$log_inserts[] = array('group' => $group_names[$row['id_group']], 'member' => $row['id_member'], 'character' => $row['character_name']);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET main_char_group = {int:regular_member}
		WHERE main_char_group IN ({array_int:group_list})
			AND id_character IN ({array_int:char_list})',
		array(
			'group_list' => $groups,
			'char_list' => $characters,
			'regular_member' => 0,
		)
	);

	// Those who have it as part of their additional group must be updated the long way... sadly.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_character, character_name, char_groups
		FROM {db_prefix}characters
		WHERE (FIND_IN_SET({raw:additional_groups_implode}, char_groups) != 0)
			AND id_character IN ({array_int:char_list})
		LIMIT ' . count($characters),
		array(
			'char_list' => $characters,
			'additional_groups_implode' => implode(', char_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// What log entries must we make for this one, eh?
		foreach (explode(',', $row['char_groups']) as $id_group)
			if (in_array($id_group, $groups))
				$log_inserts[] = array('group' => $group_names[$id_group], 'member' => $row['id_member'], 'character' => $row['character_name']);

		$updates[$row['char_groups']][] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);

	foreach ($updates as $char_groups => $memberArray)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}characters
			SET char_groups = {string:char_groups}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $memberArray,
				'char_groups' => implode(',', array_diff(explode(',', $char_groups), $groups)),
			)
		);

	// Do the log.
	if (!empty($log_inserts) && !empty($modSettings['modlog_enabled']))
	{
		require_once($sourcedir . '/Logging.php');
		foreach ($log_inserts as $extra)
			logAction('char_removed_from_group', $extra, 'admin');
	}

	return true;
}

function addCharactersToGroup($characters, $group)
{
	global $smcFunc, $sourcedir;

	updateSettings(array('settings_updated' => time()));

	if (!is_array($characters))
		$characters = array((int) $characters);
	else
		$characters = array_unique(array_map('intval', $characters));

	$group = (int) $group;
	if (in_array($group, array(-1, 0, 3)))
		return false;

	// Check against protected groups
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			array(
				'current_group' => $group,
				'limit' => 1,
			)
		);
		list ($is_protected) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Is it protected?
		if ($is_protected == 1)
			return false;
	}

	// Do the dirty deed
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET char_groups = CASE WHEN char_groups = {string:blank_string} THEN {string:id_group_string} ELSE CONCAT(char_groups, {string:id_group_string_extend}) END
		WHERE id_character IN ({array_int:char_list})
			AND main_char_group != {int:id_group}
			AND FIND_IN_SET({int:id_group}, char_groups) = 0',
		array(
			'char_list' => $characters,
			'id_group' => $group,
			'id_group_string' => (string) $group,
			'id_group_string_extend' => ',' . $group,
			'blank_string' => '',
		)
	);

	// Get the members for these characters.
	$members = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_character, character_name
		FROM {db_prefix}characters
		WHERE id_character IN ({array_int:char_list})',
		array(
			'char_list' => $characters,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[$row['id_character']] = $row;
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $group,
		)
	);
	$group_names = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// Log the data.
	require_once($sourcedir . '/Logging.php');
	foreach ($characters as $character)
	{
		logAction('char_added_to_group', array('group' => $group_names[$group], 'member' => $members[$character]['id_member'], 'character' => $members[$character]['character_name']), 'admin');
	}

	return true;
}

function integrate_chars()
{
	global $user_info, $user_settings, $txt, $modSettings;

	$user_info += array(
		'id_character' => isset($user_settings['id_character']) ? (int) $user_settings['id_character'] : 0,
		'character_name' => isset($user_settings['character_name']) ? $user_settings['character_name'] : (isset($user_settings['real_name']) ? $user_settings['real_name'] : ''),
		'char_avatar' => isset($user_settings['char_avatar']) ? $user_settings['char_avatar'] : '',
		'char_signature' => isset($user_settings['char_signature']) ? $user_settings['char_signature'] : '',
		'char_is_main' => !empty($user_settings['is_main']),
		'immersive_mode' => !empty($user_settings['immersive_mode']),
	);

	// Because we're messing with member groups, we need to tweak a few things.
	// We need to glue their groups together for the purposes of permissions.
	// But we also need to consider whether they're in immersive mode or not
	// to recalculate board access.

	$original_groups = $user_info['groups'];
	$with_char_groups = $user_info['groups'];
	if (!empty($user_settings['main_char_group']))
		$with_char_groups[] = $user_settings['main_char_group'];
	if (!empty($user_settings['char_groups']))
		$with_char_groups = array_merge($with_char_groups, array_diff(array_map('intval', explode(',', $user_settings['char_groups'])), array(0)));

	// At this point, we already built access based on account level groups
	// but if we're in immersive mode we need to include character groups
	// - unless admin, because admin implicitly has everything anyway.
	if ($user_info['immersive_mode'] && !$user_info['is_admin'])
	{
		$user_info['query_see_board'] = '((FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $with_char_groups) . ', b.member_groups) != 0)' . (!empty($modSettings['deny_boards_access']) ? ' AND (FIND_IN_SET(' . implode(', b.deny_member_groups) = 0 AND FIND_IN_SET(', $with_char_groups) . ', b.deny_member_groups) = 0)' : '') . (isset($user_info['mod_cache']) ? ' OR ' . $user_info['mod_cache']['mq'] : '') . ')';

		if (empty($user_info['ignoreboards']))
			$user_info['query_wanna_see_board'] = $user_info['query_see_board'];
		else
			$user_info['query_wanna_see_board'] = '(' . $user_info['query_see_board'] . ' AND b.id_board NOT IN (' . implode(',', $user_info['ignoreboards']) . '))';
	}
	// And now glue account plus current character together for permissions.
	$user_info['groups'] = $with_char_groups;

	// And since this is now done early in the process, but after language is identified...
	loadLanguage('characters/Characters');

	// We can now also hook the rest of the characters stuff, meaning we
	// only need to remember and manage one hook in the installer.
	add_integration_function(
		'integrate_buffer',
		'integrate_chars_buffer',
		false
	);
	add_integration_function(
		'integrate_pre_profile_areas',
		'chars_profile_menu',
		false,
		'$sourcedir/Profile-Chars.php'
	);
	add_integration_function(
		'integrate_admin_areas',
		'integrate_chars_admin_actions',
		false,
		'$sourcedir/Admin-Chars.php'
	);
	add_integration_function(
		'integrate_load_profile_fields',
		'chars_profile_field',
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
	add_integration_function(
		'integrate_register_check',
		'integrate_register_check_chars',
		false
	);
	add_integration_function(
		'integrate_register',
		'integrate_register_chars',
		false
	);
	add_integration_function(
		'integrate_post_register',
		'integrate_post_register_chars',
		false
	);
	add_integration_function(
		'integrate_change_member_data',
		'integrate_chars_change_member_data',
		false
	);
}

function integrate_chars_actions(&$actionArray)
{
	$actionArray['reattributepost'] = array('Characters.php', 'ReattributePost');
}

function integrate_remove_logout(&$buttons)
{
	global $context, $scripturl, $txt;

	$buttons['logout']['show'] = false;

	// While we're here in setupMenuContext, we might as well do other things we would
	// otherwise have done in that function.
	if (!$context['user']['is_guest'])
	{
		loadCSSFile('chars.css', array('default_theme' => true), 'chars');
		addInlineJavascript('
	$(\'#top_info\').append(\'<li><a href="' . $scripturl . '?action=logout;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['logout'] . '</a></li>\');
	user_menus.add("characters", "' . $scripturl . '?action=profile;area=characters_popup");', true);
	}
}

function integrate_chars_buffer($buffer)
{
	global $context, $scripturl, $user_info, $txt;

	if ($context['user']['is_guest'])
		return $buffer;

	$ul_pos = strpos($buffer, 'id="top_info"');
	$first_li = stripos($buffer, '</li>', $ul_pos);
	if ($ul_pos === false || $first_li === false)
		return $buffer;

	return substr($buffer, 0, $first_li + 5) . '
			<li>
				<a href="' . $scripturl . '?action=profile;area=characters" id="characters_menu_top" onclick="return false;">
				'  . sprintf($txt['posting_as'], $user_info['character_name']) . ' &#9660;</a>
				<div id="characters_menu" class="top_menu"></div>
			</li>' . substr($buffer, $first_li + 5);
}

function integrate_get_chars_messages(&$msg_selects, &$msg_tables, &$msg_parameters) {
	$msg_selects[] = 'id_character';
}

function integrate_display_chars_messages(&$output, &$message, $counter) {
	global $memberContext, $smcFunc, $txt, $scripturl, $board_info, $user_profile;

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
			// We need to fix display of badges and everything - for reasons
			// of online behaviour we can't trust what we might have now.
			// In any case this lets us handle multiple badges.
			if (!empty($character['is_main']))
			{
				// We use the main account groups for this.
				$group_list = array_merge(
					array($user_profile[$message['id_member']]['id_group']),
					!empty($user_profile[$message['id_member']]['additional_groups']) ? explode(',', $user_profile[$message['id_member']]['additional_groups']) : array()
				);
			}
			else
			{
				// We use the character's group(s)
				$group_list = array_merge(
					array($character['main_char_group']),
					!empty($character['char_groups']) ? explode(',', $character['char_groups']) : array()
				);
			}
			$group_info = get_labels_and_badges($group_list);
			$output['member']['username_color'] = '<span ' . (!empty($group_info['color']) ? 'style="color:' . $group_info['color'] . ';"' : '') . '>' . $character['character_name'] . '</span>';
			$output['member']['name_color'] = '<span ' . (!empty($group_info['color']) ? 'style="color:' . $group_info['color'] . ';"' : '') . '>' . $character['character_name'] . '</span>';
			$output['member']['group'] = $group_info['title'];
			$output['member']['group_color'] = $group_info['color'];
			$output['member']['group_icons'] = $group_info['badges'];
			$output['member']['link_color'] = '<a href="' . $scripturl . '?action=profile;u=' . $message['id_member'] . ';area=characters;char=' . $output['id_character'] . '"' . (!empty($group_info['color']) ? ' style="color:' . $group_info['color'] . ';"' : '') . '>' . $character['character_name'] . '</a>';

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
	{
		$select_columns .= ', lo.id_character AS online_character, chars.is_main, chars.main_char_group, chars.char_groups,
			cg.online_color AS char_group_color, COALESCE(cg.group_name, {string:blank_string}) AS character_group, mem.immersive_mode';
		$select_tables .= '
			LEFT JOIN {db_prefix}characters AS chars ON (lo.id_character = chars.id_character)
			LEFT JOIN {db_prefix}membergroups AS cg ON (chars.main_char_group = cg.id_group)';
	}
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
			m.id_character, c.character_name AS old_character
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			INNER JOIN {db_prefix}characters AS c ON (m.id_character = c.id_character)
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
		SELECT character_name
		FROM {db_prefix}characters
		WHERE id_character = {int:char}
			AND id_member = {int:member}
			AND is_main = 0',
		array(
			'char' => $character,
			'member' => $row['id_member_posted'],
		)
	);
	$owned_char = false;
	if ($smcFunc['db_num_rows']($result)) {
		list ($owned_char) = $smcFunc['db_fetch_row']($result);
	}
	$smcFunc['db_free_result']($result);

	if (empty($owned_char))
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

	// 7. Add it to the moderation log.
	logAction('char_reattribute', array(
		'member' => $row['id_member_posted'],
		'old_character' => $row['old_character'],
		'new_character' => $owned_char,
		'message' => $msg,
	), 'moderate');

	// 8. All done. Exit back to the post.
	redirectexit('topic=' . $topic . '.msg' . $msg . '#msg' . $msg);
}

function integrate_register_check_chars(&$regOptions, &$reg_errors)
{
	global $smcFunc;

	// First, gotta have a character name.
	if (empty($regOptions['extra_register_vars']['first_char']))
	{
		$reg_errors[] = array('lang', 'no_character_added', false);
		return;
	}

	// If we do, make sure it's unique.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}characters
		WHERE character_name LIKE {string:new_name}',
		array(
			'new_name' => $regOptions['extra_register_vars']['first_char'],
		)
	);
	list ($matching_names) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if ($matching_names) {
		$reg_errors[] = array('lang', 'char_error_duplicate_character_name', false);
		return;
	}
}

function integrate_register_chars(&$regOptions, &$theme_vars, &$knownInts, &$knownFloats)
{
	unset($regOptions['register_vars']['first_char']);
}

function integrate_post_register_chars(&$regOptions, &$theme_vars, &$memberID)
{
	global $smcFunc;

	// So at this point we've created the account, and we're going to be creating
	// a character. More accurately, two - one for the 'main' and one for the 'character'.
	$smcFunc['db_insert']('',
		'{db_prefix}characters',
		array('id_member' => 'int', 'character_name' => 'string', 'avatar' => 'string',
			'signature' => 'string', 'id_theme' => 'int', 'posts' => 'int', 'age' => 'string',
			'date_created' => 'int', 'last_active' => 'int', 'is_main' => 'int'),
		array(
			$memberID, $regOptions['register_vars']['real_name'], '',
			'', 0, 0, '',
			time(), 0, 1
		),
		array('id_character')
	);
	$real_account = $smcFunc['db_insert_id']('{db_prefix}characters', 'id_character');

	$smcFunc['db_insert']('',
		'{db_prefix}characters',
		array('id_member' => 'int', 'character_name' => 'string', 'avatar' => 'string',
			'signature' => 'string', 'id_theme' => 'int', 'posts' => 'int', 'age' => 'string',
			'date_created' => 'int', 'last_active' => 'int', 'is_main' => 'int'),
		array(
			$memberID, $regOptions['extra_register_vars']['first_char'], '',
			'', 0, 0, '',
			time(), 0, 0
		),
		array('id_character')
	);

	// Now we mark the current character into the user table.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET current_character = {int:char}
		WHERE id_member = {int:member}',
		array(
			'char' => $real_account,
			'member' => $memberID,
		)
	);
}

function get_char_membergroup_data() {
	global $smcFunc;
	// We will want to get all the membergroups since potentially we're doing display
	// of multiple per character. We need to fetch them in the order laid down
	// by admins for display purposes and we will need to cache it.
	if (($groups = cache_get_data('char_membergroups', 300)) === null)
	{
		$groups = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name, online_color, icons
			FROM {db_prefix}membergroups
			WHERE hidden != 2
			ORDER BY badge_order');
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$groups[$row['id_group']] = $row;

		$smcFunc['db_free_result']($request);
		cache_put_data('char_membergroups', $groups, 300);
	}

	return $groups;
}

function get_labels_and_badges($group_list)
{
	global $settings, $context;

	$group_title = null;
	$group_color = '';
	$groups = get_char_membergroup_data();
	$group_limit = 2;

	$badges = '';
	$badges_done = 0;
	foreach ($group_list as $id_group) {
		if (empty($groups[$id_group]))
			continue;

		if ($group_title === null) {
			$group_title = $groups[$id_group]['group_name'];
			$group_color = $groups[$id_group]['online_color'];
		}

		if (empty($groups[$id_group]['icons']))
			continue;

		list($qty, $badge) = explode('#', $groups[$id_group]['icons']);
		if ($qty == 0)
			continue;

		if (file_exists($settings['actual_theme_dir'] . '/images/membericons/' . $badge))
			$group_icon_url = $settings['images_url'] . '/membericons/' . $badge;
		elseif (isset($profile['icons'][1]))
			$group_icon_url = $settings['default_images_url'] . '/membericons/' . $badge;
		else
			$group_icon_url = '';

		if (empty($group_icon_url))
			continue;

		$badges .= '<div>' . str_repeat('<img src="' . str_replace('$language', $context['user']['language'], $group_icon_url) . '" alt="*">', $qty) . '</div>';

		$badges_done++;
		if ($badges_done >= $group_limit) {
			break;
		}
	}

	return array(
		'title' => $group_title,
		'color' => $group_color,
		'badges' => $badges,
	);
}

function integrate_chars_change_member_data($member_names, $var, &$data, &$knownInts, &$knownFloat)
{
	global $smcFunc;

	// We're only interested in the real name here.
	if ($var != 'real_name')
		return;

	// We need to translate the member_names into member_ids
	$map = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE real_name IN ({array_string:members})',
		array(
			'members' => $member_names,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$map[$row['real_name']] = (int) $row['id_member'];
	$smcFunc['db_free_result']($request);

	// Now we have member ids, let's update them
	foreach ($map as $id_member)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}characters
			SET character_name = {string:name}
			WHERE id_member = {int:member}
				AND is_main = 1',
			array(
				'name' => $data,
				'member' => $id_member,
			)
		);
	}
}
?>