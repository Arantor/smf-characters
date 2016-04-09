<?php

if (!defined('SMF'))
	die('No direct access...');

function chars_profile_field(&$profile_fields)
{
	global $txt;
	$profile_fields['immersive_mode'] = array(
		'type' => 'check',
		'label' => $txt['immersive_mode'],
		'subtext' => $txt['immersive_mode_desc'],
		'permission' => 'profile_identity',
	);
}

function chars_profile_menu(&$profile_areas) {
	global $context, $cur_profile, $scripturl, $txt;

	// Classical array of own/any
	$own_only = array(
		'own' => 'is_not_guest',
		'any' => array(),
	);
	$own_any = array(
		'own' => 'is_not_guest',
		'any' => 'profile_view',
	);

	$profile_areas['info']['areas']['characters_popup'] = array(
		'function' => 'characters_popup',
		'permission' => $own_only,
		'enabled' => $context['user']['is_owner'],
		'select' => 'summary',
	);
	$profile_areas['info']['areas']['char_switch'] = array(
		'function' => 'char_switch',
		'permission' => $own_only,
		'enabled' => $context['user']['is_owner'],
		'select' => 'summary',
	);
	$profile_areas['info']['areas']['char_switch_redir'] = array(
		'function' => 'char_switch_redir',
		'permission' => $own_only,
		'enabled' => $context['user']['is_owner'],
		'select' => 'summary',
	);

	$insert_array['chars'] = array(
		'title' => 'Characters',
		'areas' => array(
			'characters' => array(
				'file' => 'Profile-Chars.php',
				'function' => 'character_profile',
				'enabled' => true,
				'permission' => $own_any,
			),
		),
	);
	// Now we need to add the user's characters to the profile menu, "creatively".
	if (!empty($cur_profile['characters'])) {
		addInlineCss('
span.char_avatar { width: 25px; height: 25px; background-size: contain !important; background-position: 50% 50%; }');
		foreach ($cur_profile['characters'] as $id_character => $character) {
			if (!empty($character['avatar'])) {
				addInlineCss('
span.character_' . $id_character . ' { background-image: url(' . $character['avatar'] . '); background-size: cover }');
			}
			$insert_array['chars']['areas']['character_' . $id_character] = array(
				'function' => 'character_profile',
				'label' => $character['character_name'],
				'icon' => !empty($character['avatar']) ? 'char_avatar character_' . $id_character : '',
				'enabled' => true,
				'permission' => $own_any,
				'select' => 'characters',
				'custom_url' => $scripturl . '?action=profile;area=characters;char=' . $id_character,
				'subsections' => array(
					'profile' => array($txt['char_profile'], array('is_not_guest', 'profile_view')),
					'posts' => array($txt['showPosts_char'], array('is_not_guest', 'profile_view')),
					'topics' => array($txt['showTopics_char'], array('is_not_guest', 'profile_view')),
				),
			);
		}
	}

	$new_profile = array();
	foreach ($profile_areas as $k => $v) {
		$new_profile[$k] = $v;
		if ($k == 'info') {
			$new_profile['chars'] = $insert_array['chars'];
		}
	}
	$profile_areas = $new_profile;
}

function characters_popup($memID) {
	global $context, $user_info, $sourcedir, $db_show_debug, $cur_profile, $smcFunc;

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	loadTemplate('Profile-Chars');
	$context['template_layers'] = array();
}

function char_switch($memID, $char = null, $return = false) {
	global $smcFunc, $modSettings;

	if (!$return) {
		checkSession('get');
	}

	if ($char === null)
		$char = isset($_GET['char']) ? (int) $_GET['char'] : 0;

	if (empty($char)) {
		if ($return)
			return false;
		else
			die;
	}
	// Let's check the user actually owns this character
	$result = $smcFunc['db_query']('', '
		SELECT id_character, id_member
		FROM {db_prefix}characters
		WHERE id_character = {int:id_character}
			AND id_member = {int:id_member}',
		array(
			'id_character' => $char,
			'id_member' => $memID,
		)
	);
	$found = $smcFunc['db_num_rows']($result) > 0;
	$smcFunc['db_free_result']($result);

	if (!$found) {
		if ($return)
			return false;
		else
			die;
	}

	// So it's valid. Update the members table first of all.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET current_character = {int:id_character}
		WHERE id_member = {int:id_member}',
		array(
			'id_character' => $char,
			'id_member' => $memID,
		)
	);
	// Now the online log too.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_online
		SET id_character = {int:id_character}
		WHERE id_member = {int:id_member}',
		array(
			'id_character' => $char,
			'id_member' => $memID,
		)
	);
	// And last active
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET last_active = {int:last_active}
		WHERE id_character = {int:character}',
		array(
			'last_active' => time(),
			'character' => $char,
		)
	);

	// If caching would have cached the user's record, nuke it.
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
		cache_put_data('user_settings-' . $id_member, null, 60);

	if ($return)
		return true;
	else
		die;
}

function char_switch_redir($memID) {
	checkSession('get');

	$char = isset($_GET['char']) ? (int) $_GET['char'] : 0;

	if (char_switch($memID, $char, true)) {
		redirectexit('action=profile;u=' . $memID . ';area=characters;char=' . $char);
	}

	redirectexit('action=profile;u=' . $memID);
}

function character_profile($memID) {
	global $user_profile, $context, $scripturl, $modSettings, $smcFunc;

	loadTemplate('Profile-Chars');

	$char_id = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	if (!isset($user_profile[$memID]['characters'][$char_id])) {
		// character doesn't exist... bye.
		redirectexit('action=profile;u=' . $memID);
	}

	$context['character'] = $user_profile[$memID]['characters'][$char_id];
	$context['character']['editable'] = $context['user']['is_owner'] || allowedTo('admin_forum');

	$subactions = array(
		'edit' => 'char_edit',
		'theme' => 'char_theme',
		'delete' => 'char_delete',
		'posts' => 'char_posts',
		'topics' => 'char_posts',
	);
	if (isset($_GET['sa'], $subactions[$_GET['sa']])) {
		$func = $subactions[$_GET['sa']];
		return $func();
	}

	$theme_id = !empty($context['character']['id_theme']) ? $context['character']['id_theme'] : $modSettings['theme_guests'];
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE id_theme = {int:id_theme}
			AND variable = {string:variable}
		LIMIT 1', array(
			'id_theme' => $theme_id,
			'variable' => 'name',
		)
	);
	list ($context['character']['theme_name']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
}

function char_edit() {
	global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$context['sub_template'] = 'edit_char';
	loadJavascriptFile('chars.js', array('default_theme' => true), 'chars');

	$context['character']['groups_editable'] = false;
	if (allowedTo('manage_membergroups') && !$context['character']['is_main'])
	{
		$context['character']['groups_editable'] = true;
		profileLoadCharGroups();
	}

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Profile-Modify.php');
	profileLoadSignatureData();

	$context['form_errors'] = array();

	if (isset($_POST['edit_char']))
	{
		validateSession();
		validateToken('edit-char' . $context['character']['id_character'], 'post');

		$changes = array();
		$new_name = !empty($_POST['char_name']) ? $smcFunc['htmlspecialchars'](trim($_POST['char_name']), ENT_QUOTES) : '';
		if ($new_name == '')
			$context['form_errors'][] = $txt['char_error_character_must_have_name'];
		elseif ($new_name != $context['character']['character_name'])
		{
			// Check if the name already exists.
			$result = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}characters
				WHERE character_name LIKE {string:new_name}
					AND id_character != {int:char}',
				array(
					'new_name' => $new_name,
					'char' => $context['character']['id_character'],
				)
			);
			list ($matching_names) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			if ($matching_names)
				$context['form_errors'][] = $txt['char_error_duplicate_character_name'];
			else
				$changes['character_name'] = $new_name;
		}

		if ($context['character']['groups_editable'])
		{
			// Editing groups is a little bit complicated.
			$new_id_group = isset($_POST['id_group'], $context['member_groups'][$_POST['id_group']]) && $context['member_groups'][$_POST['id_group']]['can_be_primary'] ? (int) $_POST['id_group'] : $context['character']['main_char_group'];
			$new_char_groups = array();
			if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
			{
				foreach ($_POST['additional_groups'] as $id_group)
				{
					if (!isset($context['member_groups'][$id_group]))
						continue;
					if (!$context['member_groups'][$id_group]['can_be_additional'])
						continue;
					if ($id_group == $new_id_group)
						continue;
					$new_char_groups[] = (int) $id_group;
				}
			}
			$new_char_groups = implode(',', $new_char_groups);

			if ($new_id_group != $context['character']['main_char_group'])
				$changes['main_char_group'] = $new_id_group;
			if ($new_char_groups != $context['character']['char_groups'])
			$changes['char_groups'] = $new_char_groups;
		}

		$new_age = !empty($_POST['age']) ? $smcFunc['htmlspecialchars'](trim($_POST['age']), ENT_QUOTES) : '';
		if ($new_age != $context['character']['age'])
			$changes['age'] = $new_age;

		$new_avatar = !empty($_POST['avatar']) ? trim($_POST['avatar']) : '';
		$validatable_avatar = strpos($new_avatar, 'http') !== 0 ? 'http://' . $new_avatar : $new_avatar; // filter_var doesn't like // URLs
		if ($new_avatar != $context['character']['avatar'])
		{
			if (filter_var($validatable_avatar, FILTER_VALIDATE_URL))
				$changes['avatar'] = $new_avatar;
			elseif ($new_avatar != '')
				$context['form_errors'][] = $txt['char_error_avatar_must_be_real_url'];
		}

		$new_sig = !empty($_POST['char_signature']) ? $smcFunc['htmlspecialchars']($_POST['char_signature'], ENT_QUOTES) : '';
		$valid_sig = profileValidateSignature($new_sig);
		if ($valid_sig === true)
			$changes['signature'] = $new_sig; // sanitised by profileValidateSignature
		else
			$context['form_errors'][] = $valid_sig;

		if (!empty($changes) && empty($context['form_errors']))
		{
			if (!empty($modSettings['userlog_enabled'])) {
				$rows = array();
				foreach ($changes as $key => $new_value)
				{
					$change_array = array(
						'previous' => $context['character'][$key],
						'new' => $changes[$key],
						'applicator' => $context['user']['id'],
						'member_affected' => $context['id_member'],
						'id_character' => $context['character']['id_character'],
						'character_name' => !empty($changes['character_name']) ? $changes['character_name'] : $context['character']['character_name'],
					);
					if ($key == 'main_char_group')
					{
						$change_array['previous'] = $context['member_groups'][$context['character'][$key]]['name'];
						$change_array['new'] = $context['member_groups'][$changes[$key]]['name'];
					}
					if ($key == 'char_groups')
					{
						$previous = array();
						$new = array();
						foreach (explode(',', $context['character']['char_groups']) as $id_group)
							if (isset($context['member_groups'][$id_group]))
								$previous[] = $context['member_groups'][$id_group]['name'];

						foreach (explode(',', $changes['char_groups']) as $id_group)
							if (isset($context['member_groups'][$id_group]))
								$new[] = $context['member_groups'][$id_group]['name'];

						$change_array['previous'] = implode(', ', $previous);
						$change_array['new'] = implode(', ', $new);
					}
					$rows[] = array(
						'id_log' => 2, // 2 = profile edits log
						'log_time' => time(),
						'id_member' => $context['id_member'],
						'ip' => $user_info['ip'],
						'action' => 'char_' . $key,
						'id_board' => 0,
						'id_topic' => 0,
						'id_msg' => 0,
						'extra' => json_encode($change_array),
					);
				}
				if (!empty($rows)) {
					$smcFunc['db_insert']('insert',
						'{db_prefix}log_actions',
						array('id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int',
							'ip' => 'string', 'action' => 'string', 'id_board' => 'int',
							'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string'),
						$rows,
						array()
					);
				}
			}
			updateCharacterData($context['character']['id_character'], $changes);
			$_SESSION['char_updated'] = true;
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=edit');
		}

		// Put the new values back in for the form
		$context['character'] = array_merge($context['character'], $changes);
		if (isset($changes['main_char_group']) || isset($changes['char_groups']))
		{
			foreach (array_keys($context['member_groups']) as $id_group)
			{
				$context['member_groups']['is_primary'] = $id_group == $new_id_group;
				$context['member_groups']['is_additional'] = in_array($id_group, $new_char_groups);
			}
		}
	}

	$form_value = !empty($context['character']['signature']) ? $context['character']['signature'] : '';
	// Get it ready for the editor.
	$form_value = un_preparsecode($form_value);
	censorText($form_value);
	$form_value = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_value);

	require_once($sourcedir . '/Subs-Editor.php');
	$editorOptions = array(
		'id' => 'char_signature',
		'value' => $form_value,
		'disable_smiley_box' => false,
		'labels' => array(),
		'height' => '200px',
		'width' => '80%',
		'preview_type' => 0,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	addInlineJavascript('
	function update_preview() {
		if ($("#avatar").val() == "") {
			$("#avatar_preview").html(' . JavaScriptEscape($txt['no_avatar_yet']) . ');
		} else {
			$("#avatar_preview").html(\'<img src="\' + $("#avatar").val() + \'" class="avatar" alt="" />\');
		}
	}
	$(document).ready(function() { update_preview(); });
	$("#avatar").on("blur", function() { update_preview(); });', true);

	createToken('edit-char' . $context['character']['id_character'], 'post');

	$context['char_updated'] = !empty($_SESSION['char_updated']);
	unset ($_SESSION['char_updated']);
}

function char_delete() {
	global $context, $smcFunc, $txt, $sourcedir, $user_info, $modSettings;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	// Check the session; this is actually a less hardcore action than
	// editing so we don't really need the token thing - the character
	// cannot have done anything at this point in order to be removed.
	checkSession('get');

	// Can't delete main accounts
	if ($context['character']['is_main'])
	{
		fatal_lang_error('this_character_cannot_delete_main', false);
	}

	// Let's see how many posts they have (for really realz, not what their post count says)
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(id_msg)
		FROM {db_prefix}messages
		WHERE id_character = {int:char}',
		array(
			'char' => $context['character']['id_character'],
		)
	);
	list ($count) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if ($count > 0)
	{
		fatal_lang_error('this_character_cannot_delete_posts', false);
	}

	// Is the character currently in action?
	$result = $smcFunc['db_query']('', '
		SELECT current_character
		FROM {db_prefix}members
		WHERE id_member = {int:member}',
		array(
			'member' => $context['id_member'],
		)
	);
	list ($current_character) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);
	if ($current_character == $context['character']['id_character'])
	{
		fatal_lang_error($context['user']['is_owner'] ? 'this_character_cannot_delete_active_self' : 'this_character_cannot_delete_active', false);
	}

	// So we can delete them.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}characters
		WHERE id_character = {int:char}',
		array(
			'char' => $context['character']['id_character'],
		)
	);

	redirectexit('action=profile;u=' . $context['id_member']);
}

function char_theme() {
	global $context, $smcFunc;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$context['sub_template'] = 'char_theme';
}

function char_posts()
{
	global $txt, $user_info, $scripturl, $modSettings;
	global $context, $user_profile, $sourcedir, $smcFunc, $board;

	// Some initial context.
	$context['start'] = (int) $_REQUEST['start'];
	$context['sub_template'] = 'char_posts';

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['showPosts'],
		'description' => $txt['showPosts_help_char'],
		'icon' => 'profile_hd.png',
		'tabs' => array(
			'posts' => array(
			),
			'topics' => array(
			),
		),
	);

	// Shortcut used to determine which $txt['show*'] string to use for the title, based on the SA
	$title = array(
		'posts' => 'Posts',
		'topics' => 'Topics'
	);

	// Set the page title
	if (isset($_GET['sa']) && array_key_exists($_GET['sa'], $title))
		$context['page_title'] = $txt['show' . $title[$_GET['sa']]];
	else
		$context['page_title'] = $txt['showPosts'];

	$context['page_title'] .= ' - ' . $context['character']['character_name'];

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_show_posts']) && $context['load_average'] >= $modSettings['loadavg_show_posts'])
		fatal_lang_error('loadavg_show_posts_disabled', false);

	// Are we just viewing topics?
	$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = '10';

	if ($context['is_topics'])
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
				INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
				AND t.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND t.approved = {int:is_approved}'),
			array(
				'current_member' => $context['character']['id_character'],
				'is_approved' => 1,
				'board' => $board,
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND m.approved = {int:is_approved}'),
			array(
				'current_member' => $context['character']['id_character'],
				'is_approved' => 1,
				'board' => $board,
			)
		);
	list ($msgCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m
		WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $context['character']['id_character'],
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($min_msg_member, $max_msg_member) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$reverse = false;
	$range_limit = '';

	if ($context['is_topics'])
		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	else
		$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	$maxIndex = $maxPerPage;

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';u=' . $context['id_member'] . ($context['is_topics'] ? ';sa=topics' : ';sa=posts') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $maxPerPage + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : $maxPerPage;
		$start = $msgCount < $context['start'] + $maxPerPage + 1 || $msgCount < $context['start'] + $maxPerPage ? 0 : $msgCount - $context['start'] - $maxPerPage;
	}

	// Guess the range of messages to be shown.
	if ($msgCount > 1000)
	{
		$margin = floor(($max_msg_member - $min_msg_member) * (($start + $maxPerPage) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
		// Make a bigger margin for topics only.
		if ($context['is_topics'])
		{
			$margin *= 5;
			$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
		}
		else
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
	}

	// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
	$looped = false;
	while (true)
	{
		if ($context['is_topics'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
					t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
					AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $context['character']['id_character'],
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_character = {int:current_member}' . (!empty($board) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $context['character']['id_character'],
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}

		// Make sure we quit this loop.
		if ($smcFunc['db_num_rows']($request) === $maxIndex || $looped)
			break;
		$looped = true;
		$range_limit = '';
	}

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor....
		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'body' => $row['body'],
			'counter' => $counter,
			'category' => array(
				'name' => $row['cname'],
				'id' => $row['id_cat']
			),
			'board' => array(
				'name' => $row['bname'],
				'id' => $row['id_board']
			),
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'start' => 'msg' . $row['id_msg'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'can_reply' => false,
			'can_mark_notify' => !$context['user']['is_guest'],
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
			'approved' => $row['approved'],
			'css_class' => $row['approved'] ? 'windowbg' : 'approvebg',
		);

		if ($user_info['id'] == $row['id_member_started'])
			$board_ids['own'][$row['id_board']][] = $counter;
		$board_ids['any'][$row['id_board']][] = $counter;
	}
	$smcFunc['db_free_result']($request);

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);

	// These are all the permissions that are different from board to board..
	if ($context['is_topics'])
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
			)
		);
	else
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'delete_any' => 'can_delete',
			)
		);

	// For every permission in the own/any lists...
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// Get the boards they can do this on...
			$boards = boardsAllowedTo($permission);

			// Hmm, they can do it on all boards, can they?
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Now go through each board they can do the permission on.
			foreach ($boards as $board_id)
			{
				// There aren't any posts displayed from this board.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Set the permission to true ;).
				foreach ($board_ids[$type][$board_id] as $counter)
					$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	// Clean up after posts that cannot be deleted and quoted.
	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}

	// Allow last minute changes.
	call_integration_hook('integrate_profile_showPosts');
}

function profileLoadCharGroups()
{
	global $cur_profile, $txt, $context, $smcFunc, $user_settings;

	$context['member_groups'] = array(
		0 => array(
			'id' => 0,
			'name' => $txt['no_primary_character_group'],
			'is_primary' => $context['character']['main_char_group'] == 0,
			'can_be_additional' => false,
			'can_be_primary' => true,
		)
	);
	$curGroups = explode(',', $context['character']['char_groups']);

	// Load membergroups, but only those groups the user can assign.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group, hidden
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
			AND min_posts = {int:min_posts}
			AND is_character = 1' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'moderator_group' => 3,
			'min_posts' => -1,
			'is_protected' => 1,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['member_groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'is_primary' => $context['character']['main_char_group'] == $row['id_group'],
			'is_additional' => in_array($row['id_group'], $curGroups),
			'can_be_additional' => true,
			'can_be_primary' => $row['hidden'] != 2,
		);
	}
	$smcFunc['db_free_result']($request);

	$context['member']['group_id'] = $user_settings['id_group'];

	return true;
}

?>