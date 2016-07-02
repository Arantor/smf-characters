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
	global $context, $cur_profile, $scripturl, $txt, $modSettings;

	// Replacing with our wrapper
	$profile_areas['info']['areas']['summary']['function'] = 'char_summary';

	// Classical array of own/any
	$own_only = array(
		'own' => 'is_not_guest',
		'any' => array(),
	);
	$own_any = array(
		'own' => 'is_not_guest',
		'any' => 'profile_view',
	);
	$not_own_admin_only = array(
		'own' => array(),
		'any' => array('admin_forum'),
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
	$profile_areas['profile_action']['areas']['merge_acct'] = array(
		'label' => $txt['merge_char_account'],
		'function' => 'char_merge_account',
		'permission' => $not_own_admin_only,
		'icon' => 'merge',
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
	$char_sheet_override = allowedTo('admin_forum') || $context['user']['is_owner'];
	// Now we need to add the user's characters to the profile menu, "creatively".
	if (!empty($cur_profile['characters'])) {
		addInlineCss('
span.char_avatar { width: 25px; height: 25px; background-size: contain !important; background-position: 50% 50%; }
span.char_unknown { background-image: url(' . $modSettings['avatar_url'] . '/default.png); }');
		foreach ($cur_profile['characters'] as $id_character => $character) {
			if (!empty($character['avatar'])) {
				addInlineCss('
span.character_' . $id_character . ' { background-image: url(' . $character['avatar'] . '); background-size: cover }');
			}
			$insert_array['chars']['areas']['character_' . $id_character] = array(
				'function' => 'character_profile',
				'label' => $character['character_name'],
				'icon' => !empty($character['avatar']) ? 'char_avatar character_' . $id_character : 'char_avatar char_unknown',
				'enabled' => true,
				'permission' => $own_any,
				'select' => 'characters',
				'custom_url' => $scripturl . '?action=profile;area=characters;char=' . $id_character,
				'subsections' => array(
					'profile' => array($txt['char_profile'], array('is_not_guest', 'profile_view')),
					'sheet' => array($txt['char_sheet'], !empty($character['char_sheet']) || $char_sheet_override ? array('is_not_guest', 'profile_view') : array('admin_forum'), 'enabled' => empty($character['is_main'])),
					'posts' => array($txt['showPosts_char'], array('is_not_guest', 'profile_view')),
					'topics' => array($txt['showTopics_char'], array('is_not_guest', 'profile_view')),
					'stats' => array($txt['char_stats'], array('is_not_guest', 'profile_view')),
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

	// Whatever they had in session for theme, disregard it.
	unset ($_SESSION['id_theme']);

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
	global $user_profile, $context, $scripturl, $modSettings, $smcFunc, $txt;

	loadTemplate('Profile-Chars');

	$char_id = isset($_GET['char']) ? (int) $_GET['char'] : 0;
	if (!isset($user_profile[$memID]['characters'][$char_id])) {
		// character doesn't exist... bye.
		redirectexit('action=profile;u=' . $memID);
	}

	$context['character'] = $user_profile[$memID]['characters'][$char_id];
	$context['character']['editable'] = $context['user']['is_owner'] || allowedTo('admin_forum');

	$context['linktree'][] = array(
		'name' => $txt['chars_menu_title'],
		'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . '#user_char_list',
	);
	$context['linktree'][] = array(
		'name' => $context['character']['character_name'],
		'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=profile;char=' . $char_id,
	);
	$subactions = array(
		'edit' => 'char_edit',
		'theme' => 'char_theme',
		'sheet' => 'char_sheet',
		'sheet_edit' => 'char_sheet_edit',
		'sheet_approval' => 'char_sheet_approval',
		'sheet_approve' => 'char_sheet_approve',
		'sheet_compare' => 'char_sheet_compare',
		'delete' => 'char_delete',
		'posts' => 'char_posts',
		'topics' => 'char_posts',
		'stats' => 'char_stats',
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
			{
				$size = get_avatar_url_size($new_avatar);
				if (!$size)
					$context['form_errors'][] = $txt['char_error_avatar_link_invalid'];
				elseif (!empty($modSettings['avatar_max_width_external']))
				{
					if ($size[0] > $modSettings['avatar_max_width_external'] || $size[1] > $modSettings['avatar_max_height_external'])
					{
						$txt['char_error_avatar_oversize'] = sprintf(
							$txt['char_error_avatar_oversize'],
							$size[0],
							$size[1],
							$modSettings['avatar_max_width_external'],
							$modSettings['avatar_max_height_external']
						);
						$context['form_errors'][] = $txt['char_error_avatar_oversize'];
					}
					else
						$changes['avatar'] = $new_avatar;
				}
				else
					$changes['avatar'] = $new_avatar;
			}
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
			if ($context['character']['is_main'])
			{
				if (isset($changes['character_name']))
					updateMemberData($context['id_member'], array('real_name' => $changes['character_name']));
			}
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
						'action' => $context['character']['is_main'] && $key == 'character_name' ? 'real_name' : 'char_' . $key,
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
	global $context, $smcFunc, $modSettings;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$known_themes = explode(',', $modSettings['knownThemes']);
	$context['themes'] = array();
	foreach ($known_themes as $id_theme) {
		$context['themes'][$id_theme] = array(
			'name' => '',
			'theme_dir' => '',
			'images_url' => '',
			'thumbnail' => ''
		);
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = 0
			AND variable IN ({array_string:vars})',
		array(
			'vars' => array('name', 'images_url', 'theme_dir'),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	foreach ($context['themes'] as $id_theme => $theme)
	{
		if (empty($theme['name']) || empty($theme['images_url']) || !file_exists($theme['theme_dir']))
			unset ($context['themes'][$id_theme]);

		foreach (array('.png', '.gif', '.jpg') as $ext)
			if (file_exists($theme['theme_dir'] . '/images/thumbnail' . $ext))
			{
				$context['themes'][$id_theme]['thumbnail'] = $theme['images_url'] . '/thumbnail' . $ext;
				break;
			}

		if (empty($context['themes'][$id_theme]['thumbnail']))
			unset ($context['themes'][$id_theme]);
	}

	if (!empty($_POST['theme']) && is_array($_POST['theme']))
	{
		checkSession();
		list($id_theme) = array_keys($_POST['theme']);
		if (isset($context['themes'][$id_theme]))
		{
			updateCharacterData($context['character']['id_character'], array('id_theme' => $id_theme));
			redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
		}
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
	{
		$context['linktree'][] = array(
			'name' => $txt['show' . $title[$_GET['sa']] . '_char'],
			'url' => $scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';sa=' . $_GET['sa'] . ';u=' . $context['id_member'],
		);
		$context['page_title'] = $txt['show' . $title[$_GET['sa']]];
	}
	else
	{
		$context['linktree'][] = array(
			'name' => $txt['showPosts_char'],
			'url' => $scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';sa=posts;u=' . $context['id_member'],
		);
		$context['page_title'] = $txt['showPosts'];
	}

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

function char_stats()
{
	global $txt, $scripturl, $context, $user_profile, $user_info, $modSettings, $smcFunc;

	$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $context['character']['character_name'];
	$context['sub_template'] = 'char_stats';

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_userstats']) && $context['load_average'] >= $modSettings['loadavg_userstats'])
		fatal_lang_error('loadavg_userstats_disabled', false);

	$context['num_posts'] = comma_format($context['character']['posts']);
	// Menu tab
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['statPanel_generalStats'] . ' - ' . $context['character']['character_name'],
		'icon' => 'stats_info_hd.png'
	);

	$context['linktree'][] = array(
		'name' => $txt['char_stats'],
		'url' => $scripturl . '?action=profile;area=characters;char=' . $context['character']['id_character'] . ';sa=stats;u=' . $context['id_member'],
	);

	// Number of topics started.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics AS t
		INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE m.id_character = {int:id_character}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND t.id_board != {int:recycle_board}' : ''),
		array(
			'id_character' => $context['character']['id_character'],
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	list ($context['num_topics']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);
	$context['num_topics'] = comma_format($context['num_topics']);

	// Grab the board this character posted in most often.
	$result = $smcFunc['db_query']('', '
		SELECT
			b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_character = {int:id_character}
			AND b.count_posts = {int:count_enabled}
			AND {query_see_board}
		GROUP BY b.id_board
		ORDER BY message_count DESC
		LIMIT 10',
		array(
			'id_character' => $context['character']['id_character'],
			'count_enabled' => 0,
		)
	);
	$context['popular_boards'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['popular_boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'posts_percent' => $context['character']['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $context['character']['posts'],
			'total_posts' => $row['num_posts'],
			'total_posts_char' => $context['character']['posts'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Now get the 10 boards this user has most often participated in.
	$result = $smcFunc['db_query']('profile_board_stats', '
		SELECT
			b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
			CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_character = {int:id_character}
			AND {query_see_board}
		GROUP BY b.id_board, b.num_posts
		ORDER BY percentage DESC
		LIMIT 10',
		array(
			'id_character' => $context['character']['id_character'],
		)
	);
	$context['board_activity'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['board_activity'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'percent' => comma_format((float) $row['percentage'], 2),
			'posts_percent' => (float) $row['percentage'],
			'total_posts' => $row['num_posts'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Posting activity by time.
	$result = $smcFunc['db_query']('user_activity_by_time', '
		SELECT
			HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
			COUNT(*) AS post_count
		FROM {db_prefix}messages
		WHERE id_character = {int:id_character}' . ($modSettings['totalMessages'] > 100000 ? '
			AND id_topic > {int:top_ten_thousand_topics}' : '') . '
		GROUP BY hour',
		array(
			'id_character' => $context['character']['id_character'],
			'top_ten_thousand_topics' => $modSettings['totalTopics'] - 10000,
			'time_offset' => (($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
		)
	);
	$maxPosts = $realPosts = 0;
	$context['posts_by_time'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Cast as an integer to remove the leading 0.
		$row['hour'] = (int) $row['hour'];

		$maxPosts = max($row['post_count'], $maxPosts);
		$realPosts += $row['post_count'];

		$context['posts_by_time'][$row['hour']] = array(
			'hour' => $row['hour'],
			'hour_format' => stripos($user_info['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
			'posts' => $row['post_count'],
			'posts_percent' => 0,
			'is_last' => $row['hour'] == 23,
		);
	}
	$smcFunc['db_free_result']($result);

	if ($maxPosts > 0)
		for ($hour = 0; $hour < 24; $hour++)
		{
			if (!isset($context['posts_by_time'][$hour]))
				$context['posts_by_time'][$hour] = array(
					'hour' => $hour,
					'hour_format' => stripos($user_info['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
					'posts' => 0,
					'posts_percent' => 0,
					'relative_percent' => 0,
					'is_last' => $hour == 23,
				);
			else
			{
				$context['posts_by_time'][$hour]['posts_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
				$context['posts_by_time'][$hour]['relative_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
			}
		}

	// Put it in the right order.
	ksort($context['posts_by_time']);
}

function char_summary($memID)
{
	global $context, $user_profile;

	if (!empty($_SESSION['merge_success']))
	{
		$context['profile_updated'] = $_SESSION['merge_success'];
		unset ($_SESSION['merge_success']);
	}

	$cur_profile = $user_profile[$memID];
	$main_char = $cur_profile['characters'][$cur_profile['main_char']];
	loadTemplate('Profile-Chars');
	summary($memID);
	$context['member']['signature'] = $main_char['sig_parsed'];
	$user_groups = array();
	if (!empty($main_char['main_char_group']))
		$user_groups[] = $main_char['main_char_group'];
	if (!empty($cur_profile['id_group']))
		$user_groups[] = $cur_profile['id_group'];
	if (!empty($cur_profile['additional_groups']))
		$user_groups = array_merge($user_groups, explode(',', $cur_profile['additional_groups']));
	if (!empty($main_char['char_groups']))
		$user_groups = array_merge($user_groups, explode(',', $main_char['char_groups']));

	$details = get_labels_and_badges($user_groups);
	$context['member']['group'] = $details['title'];
	$context['member']['badges'] = $details['badges'];

	foreach ($context['member']['characters'] as $id_char => $char)
	{
		if ($char['is_main'])
			continue;

		$user_groups = array();
		if (!empty($char['main_char_group']))
			$user_groups[] = $char['main_char_group'];
		if (!empty($char['char_groups']))
			$user_groups = array_merge($user_groups, explode(',', $char['char_groups']));
		$details = get_labels_and_badges($user_groups);
		$context['member']['display_group'] = $details['title'];
	}
}

function char_sheet()
{
	global $context, $txt, $smcFunc, $scripturl;

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Fetch the current character sheet - for the owner + admin, show most recent
	// whatever, for everyone else show them the most recent approved
	if ($context['user']['is_owner'] || allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_character = {int:character}
			ORDER BY id_version DESC
			LIMIT 1',
			array(
				'character' => $context['character']['id_character'],
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
		}
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
			FROM {db_prefix}character_sheet_versions
			WHERE id_version = {int:version}',
			array(
				'version' => $context['character']['char_sheet'],
			)
		);
		$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	$context['linktree'][] = array(
		'name' => $txt['char_sheet'],
		'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet;char=' . $context['character']['id_character'],
	);

	$context['page_title'] = $txt['char_sheet'] . ' - ' . $context['character']['character_name'];
	$context['sub_template'] = 'char_sheet';

	$context['sheet_buttons'] = array();
	if ($context['user']['is_owner'] || allowedTo('admin_forum'))
	{
		// Always have an edit button
		$context['sheet_buttons']['edit'] = array(
			'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_edit;char=' . $context['character']['id_character'],
			'text' => 'char_sheet_edit',
		);
		// Only have a history button if there's actually some history
		if (!empty($context['character']['sheet_details']['sheet_text']))
		{
			$context['sheet_buttons']['history'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_history;char=' . $context['character']['id_character'],
				'text' => 'char_sheet_history',
			);
			// Only have a send-for-approval button if it hasn't been approved
			// and it hasn't yet been sent for approval either
			if (empty($context['character']['sheet_details']['id_approver']) && empty($context['character']['sheet_details']['approval_state']))
			{
				$context['sheet_buttons']['send_for_approval'] = array(
					'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_approval;char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'text' => 'char_sheet_send_for_approval',
				);
			}
		}
		// Compare to last approved only if we had a previous approval and the
		// current one isn't approved right now
		if (empty($context['character']['sheet_details']['id_approver']) && !empty($context['character']['char_sheet']))
		{
			$context['sheet_buttons']['compare'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_compare;char=' . $context['character']['id_character'],
				'text' => 'char_sheet_compare',
			);
		}
		// And the infamous approve button
		if (!empty($context['character']['sheet_details']['sheet_text']) && empty($context['character']['sheet_details']['id_approver']) && allowedTo('admin_forum'))
		{
			$context['sheet_buttons']['approve'] = array(
				'url' => $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=characters;sa=sheet_approve;version=' . $context['character']['sheet_details']['id_version'] . ';char=' . $context['character']['id_character'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'text' => 'char_sheet_approve',
				'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['char_sheet_approve_are_you_sure']) . ')"',
			);
		}
	}
}

function char_sheet_edit()
{
	global $context, $txt, $smcFunc, $scripturl, $sourcedir;

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_character = {int:character}
		ORDER BY id_version DESC
		LIMIT 1',
		array(
			'character' => $context['character']['id_character'],
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	// Make an editor box
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	if (isset($_POST['message']))
	{
		// Are we saving? Let's see if session's legit first.
		checkSession();
		// Then try to get some content.
		$message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
		preparsecode($message);

		if (!empty($message))
		{
			// So we have a character sheet. Let's do a comparison against
			// the last character sheet saved just in case the user did something
			// a little bit weird/silly.
			if (empty($context['character']['sheet_details']['sheet_text']) || $message != $context['character']['sheet_details']['sheet_text'])
			{
				// It's different, good. So insert it, making it await approval.
				$smcFunc['db_insert']('insert',
					'{db_prefix}character_sheet_versions',
					array(
						'sheet_text' => 'string', 'id_character' => 'int', 'id_member' => 'int',
						'created_time' => 'int', 'id_approver' => 'int', 'approved_time' => 'int', 'approval_state' => 'int'
					),
					array(
						$message, $context['character']['id_character'], $context['user']['id'],
						time(), 0, 0, 0
					),
					array('id_version')
				);
			}
		}

		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
	}

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => !empty($context['character']['sheet_details']['sheet_text']) ? un_preparsecode($context['character']['sheet_details']['sheet_text']) : '',
		'labels' => array(
			'post_button' => $txt['save'],
		),
		// add height and width for the editor
		'height' => '500px',
		'width' => '100%',
		'preview_type' => 0,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	$context['sheet_templates'] = array();
	// Go fetch the possible templates.
	$request = $smcFunc['db_query']('', '
		SELECT id_template, template_name, template
		FROM {db_prefix}character_sheet_templates
		ORDER BY position ASC');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['sheet_templates'][$row['id_template']] = array(
			'name' => $row['template_name'],
			'body' => un_preparsecode($row['template']),
		);
	}
	$smcFunc['db_free_result']($request);

	// Now fetch the comments
	$context['sheet_comments'] = array();
	if (!empty($context['character']['sheet_details']['created_time']) && empty($context['character']['sheet_details']['id_approver']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_comment, id_author, mem.real_name, time_posted, sheet_comment
			FROM {db_prefix}character_sheet_comments AS csc
				LEFT JOIN {db_prefix}members AS mem ON (csc.id_author = mem.id_member)
			WHERE id_character = {int:character}
				AND time_posted > {int:last_approved_time}
			ORDER BY NULL',
			array(
				'character' => $context['character']['id_character'],
				'last_approved_time' => $context['character']['sheet_details']['created_time'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['real_name']))
				$row['real_name'] = $txt['char_unknown'];
			$context['sheet_comments'][$row['id_comment']] = $row;
		}
		$smcFunc['db_free_result']($request);
		krsort($context['sheet_comments']);
	}

	$context['page_title'] = $txt['char_sheet'] . ' - ' . $context['character']['character_name'];
	$context['sub_template'] = 'char_sheet_edit';
}

function char_sheet_approval()
{
	global $smcFunc, $context, $sourcedir;

	checkSession('get');

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['user']['is_owner']))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// So which one are we offering up for approval?
	// First, find the last approved case.
	$last_approved = 0;
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_version) AS last_approved
		FROM {db_prefix}character_sheet_versions
		WHERE id_approver != 0
			AND id_character = {int:character}',
			array(
				'character' => $context['character']['id_character'],
			)
		);
	if ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$last_approved = (int) $row['last_approved'];
	}
	$smcFunc['db_free_result']($request);

	// Now find the highest version after the last approved (or highest ever)
	// for this character.
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_version) AS highest_id
		FROM {db_prefix}character_sheet_versions
		WHERE id_version > {int:last_approved}
			AND id_character = {int:character}',
			array(
				'last_approved' => $last_approved,
				'character' => $context['character']['id_character'],
			)
		);
	$row = $smcFunc['db_fetch_assoc']($request);
	if (empty($row))
	{
		// There isn't a version to mark as pending approval.
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	// OK, time to mark it as ready for approval.
	$request = $smcFunc['db_query']('', '
		UPDATE {db_prefix}character_sheet_versions
		SET approval_state = 1
		WHERE id_version = {int:version}',
		array(
			'version' => $row['highest_id'],
		)
	);

	// Now notify peoples that this is a thing.
	require_once($sourcedir . '/Subs-Members.php');
	$admins = membersAllowedTo('admin_forum');

	$alert_rows = array();
	foreach ($admins as $id_member)
	{
		$alert_rows[] = array(
			'alert_time' => time(),
			'id_member' => $id_member,
			'id_member_started' => $context['id_member'],
			'member_name' => $context['member']['name'],
			'content_type' => 'member',
			'content_id' => 0,
			'content_action' => 'char_sheet_approval',
			'is_read' => 0,
			'extra' => json_encode(array('chars_src' => $context['character']['id_character'])),
		);
	}

	if (!empty($alert_rows))
	{
		$smcFunc['db_insert']('',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$alert_rows,
			array()
		);
		updateMemberData($admins, array('alerts' => '+'));
	}

	redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
}

function char_sheet_approve()
{
	global $context, $smcFunc;

	checkSession('get');
	isAllowedTo('admin_forum');

	// If we're here, we have a valid character ID on a valid user ID.
	// We need to check that 1) we have a character sheet to approve,
	// 2) it requires approving, and 3) it's the most recent one.
	$version = isset($_GET['version']) ? (int) $_GET['version'] : 0;
	if (empty($version))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	$request = $smcFunc['db_query']('', '
		SELECT id_character, id_approver, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_version = {int:version}',
		array(
			'version' => $version,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		// Doesn't exist, so bail.
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Correct character?
	if ($row['id_character'] != $context['character']['id_character'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Has it already been approved?
	if (!empty($row['id_approver']))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Last test: any other rows for this user
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}character_sheet_versions
		WHERE id_version > {int:version}
			AND id_character = {int:character}',
		array(
			'version' => $version,
			'character' => $context['character']['id_character'],
		)
	);
	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($count > 0)
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// OK, so this version is good to go for approval. Approve the sheet...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}character_sheet_versions
		SET id_approver = {int:approver},
			approved_time = {int:time},
			approval_state = {int:zero}
		WHERE id_version = {int:version}',
		array(
			'approver' => $context['user']['id'],
			'time' => time(),
			'zero' => 0,
			'version' => $version,
		)
	);
	// And the character...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET char_sheet = {int:version}
		WHERE id_character = {int:character}',
		array(
			'version' => $version,
			'character' => $context['character']['id_character'],
		)
	);

	redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
}

function char_sheet_compare()
{
	global $context, $txt, $smcFunc, $scripturl, $sourcedir;

	// First, get rid of people shouldn't have a sheet at all - the OOC characters
	if ($context['character']['is_main'])
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// Then if we're looking at a character who doesn't have an approved one
	// and the user couldn't see it... you are the weakest link, goodbye.
	if (empty($context['character']['char_sheet']) && empty($context['user']['is_owner']) && !allowedTo('admin_forum'))
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);

	// So, does the user have a current-not-yet-approved one? We need to get
	// the latest to find this out.
	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_character = {int:character}
			AND id_version > {int:current_version}
		ORDER BY id_version DESC
		LIMIT 1',
		array(
			'character' => $context['character']['id_character'],
			'current_version' => $context['character']['char_sheet'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character'] . ';sa=sheet');
	}
	$context['character']['sheet_details'] = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Now we need to go get the currently approved one too.
	$request = $smcFunc['db_query']('', '
		SELECT id_version, sheet_text, created_time, id_approver, approved_time, approval_state
		FROM {db_prefix}character_sheet_versions
		WHERE id_version = {int:current_version}',
		array(
			'current_version' => $context['character']['char_sheet'],
		)
	);
	$context['character']['original_sheet'] = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$context['page_title'] = $txt['char_sheet_compare'];
	$context['sub_template'] = 'char_sheet_compare';
}

function char_merge_account($memID)
{
	global $context, $txt, $user_profile, $smcFunc;

	// Some basic sanity checks.
	if ($context['user']['is_owner'])
		fatal_lang_error('cannot_merge_self', false);
	if ($user_profile[$memID]['id_group'] == 1 || in_array('1', explode(',', $user_profile[$memID]['additional_groups'])))
		fatal_lang_error('cannot_merge_admin', false);

	loadTemplate('Profile-Chars');
	loadJavascriptFile('suggest.js', array('default_theme' => true, 'defer' => false), 'smf_suggest');
	$context['page_title'] = $txt['merge_char_account'];

	if (isset($_POST['merge_acct_id']))
	{
		checkSession();
		$result = merge_char_accounts($context['id_member'], $_POST['merge_acct_id']);
		if ($result !== true)
			fatal_lang_error('cannot_merge_' . $result, false);

		$_SESSION['merge_success'] = sprintf($txt['merge_success'], $context['member']['name']);

		redirectexit('action=profile;u=' . $_POST['merge_acct_id']);
	}
	elseif (isset($_POST['merge_acct']))
	{
		checkSession();

		// We picked an account to merge, let's see if we can find and if we can,
		// get its details so that we can check for sure it's what the user wants.
		$name = $smcFunc['htmlspecialchars']($_POST['merge_acct'], ENT_QUOTES);
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE real_name = {string:name}',
			array(
				'name' => $name,
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('cannot_merge_not_found', false);

		list ($dest) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		loadMemberData($dest);

		$context['merge_destination_id'] = $dest;
		$context['merge_destination'] = $user_profile[$dest];
		$context['sub_template'] = 'char_merge_account_confirm';
	}
}

function merge_char_accounts($source, $dest)
{
	global $user_profile, $sourcedir, $smcFunc;

	if ($source == $dest)
		return 'no_same';

	$loaded = loadMemberData(array($source, $dest));
	if (!in_array($source, $loaded) || !in_array($dest, $loaded))
		return 'no_exist';

	if ($user_profile[$source]['id_group'] == 1 || in_array('1', explode(',', $user_profile[$source]['additional_groups'])))
		return 'no_merge_admin';

	// Work out which the main characters are.
	$source_main = 0;
	$dest_main = 0;
	foreach ($user_profile[$source]['characters'] as $id_char => $char)
	{
		if ($char['is_main'])
		{
			$source_main = $id_char;
			break;
		}
	}
	foreach ($user_profile[$dest]['characters'] as $id_char => $char)
	{
		if ($char['is_main'])
		{
			$dest_main = $id_char;
			break;
		}
	}
	if (empty($source_main) || empty($dest_main))
		return 'no_main';

	// Move characters
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}characters
		SET id_member = {int:dest}
		WHERE id_member = {int:source}
			AND id_character != {int:source_main}',
		array(
			'source' => $source,
			'source_main' => $source_main,
			'dest' => $dest,
			'dest_main' => $dest_main,
		)
	);

	// Move posts over - main
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:dest},
			id_character = {int:dest_main}
		WHERE id_member = {int:source}
			AND id_character = {int:source_main}',
		array(
			'source' => $source,
			'source_main' => $source_main,
			'dest' => $dest,
			'dest_main' => $dest_main,
		)
	);

	// Move posts over - characters (i.e. whatever's left)
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:dest}
		WHERE id_member = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);

	// Fix post counts of destination accounts
	$total_posts = 0;
	foreach ($user_profile[$source]['characters'] as $char)
		$total_posts += $char['posts'];

	if (!empty($total_posts))
		updateMemberData($dest, array('posts' => 'posts + ' . $total_posts));

	if (!empty($user_profile[$source]['characters'][$source_main]['posts']))
		updateCharacterData($dest_main, array('posts' => 'posts + ' . $user_profile[$source]['characters'][$source_main]['posts']));

	// Reassign topics
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:dest}
		WHERE id_member_started = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:dest}
		WHERE id_member_updated = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);

	// Move PMs - sent items
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:dest}
		WHERE id_member_from = {int:source}',
		array(
			'source' => $source,
			'dest' => $dest,
		)
	);

	// Move PMs - received items
	// First we have to get all the existing recipient rows
	$rows = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_pm, bcc, is_read, is_new, deleted
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:source}',
		array(
			'source' => $source,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$rows[] = array(
			'id_pm' => $row['id_pm'],
			'id_member' => $dest,
			'bcc' => $row['bcc'],
			'is_read' => $row['is_read'],
			'is_new' => $row['is_new'],
			'deleted' => $row['deleted'],
			'is_inbox' => 1,
		);
	}
	$smcFunc['db_free_result']($request);
	if (!empty($rows))
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'is_read' => 'int',
				'is_new' => 'int', 'deleted' => 'int', 'is_inbox' => 'int',
			),
			$rows,
			array('id_pm', 'id_member')
		);
	}

	// Delete the source user
	require_once($sourcedir . '/Subs-Members.php');
	deleteMembers($source);

	return true;
}

function get_avatar_url_size($url)
{
	global $sourcedir;
	require_once($sourcedir . '/Class-CurlFetchWeb.php');

	$fetch_data = new curl_fetch_web_data(array(
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246',
		CURLOPT_RANGE => '0-16383',
	));

	$fetch_data->get_url_data($url);
	if (in_array($fetch_data->result('code'), array(200, 206)) && !$fetch_data->result('error'))
	{
		$data = $fetch_data->result('body');
		return get_image_size_from_string($data);
	}
	else
		return false;
}

function get_image_size_from_string($data) {
	if (empty($data)) {
		return false;
	}
	if (strpos($data, 'GIF8') === 0) {
		// It's a GIF. Doesn't really matter which subformat though. Note that things are little endian.
		$width = (ord(substr($data, 7, 1)) << 8) + (ord(substr($data, 6, 1)));
		$height = (ord(substr($data, 9, 1)) << 8) + (ord(substr($data, 8, 1)));
		if (!empty($width)) {
			return array($width, $height);
		}
	}

	if (strpos($data, "\x89PNG") === 0) {
		// Seems to be a PNG. Let's look for the signature of the header chunk, minimum 12 bytes in. PNG max sizes are (signed) 32 bits each way.
		$pos = strpos($data, 'IHDR');
		if ($pos >= 12) {
			$width = (ord(substr($data, $pos + 4, 1)) << 24) + (ord(substr($data, $pos + 5, 1)) << 16) + (ord(substr($data, $pos + 6, 1)) << 8) + (ord(substr($data, $pos + 7, 1)));
			$height = (ord(substr($data, $pos + 8, 1)) << 24) + (ord(substr($data, $pos + 9, 1)) << 16) + (ord(substr($data, $pos + 10, 1)) << 8) + (ord(substr($data, $pos + 11, 1)));
			if ($width > 0 && $height > 0) {
				return array($width, $height);
			}
		}
	}

	if (strpos($data, "\xFF\xD8") === 0)
	{
		// JPEG? Hmm, JPEG is tricky. Well, we found the SOI marker as expected and an APP0 marker, so good chance it is JPEG compliant.
		// Need to step through the file looking for JFIF blocks.
		$pos = 2;
		$filelen = strlen($data);
		while ($pos < $filelen) {
			$length = (ord(substr($data, $pos + 2, 1)) << 8) + (ord(substr($data, $pos + 3, 1)));
			$block = substr($data, $pos, 2);
			if ($block == "\xFF\xC0" || $block == "\xFF\xC2") {
				break;
			}
			$pos += $length + 2;
		}
		if ($pos > 2) {
			// Big endian. SOF block is marker (2 bytes), block size (2 bytes), bits/pixel density (1 byte), image height (2 bytes), image width (2 bytes)
			$width = (ord(substr($data, $pos + 7, 1)) << 8) + (ord(substr($data, $pos + 8, 1)));
			$height = (ord(substr($data, $pos + 5, 1)) << 8) + (ord(substr($data, $pos + 6, 1)));
			if ($width > 0 && $height > 0) {
				return array($width, $height);
			}
		}
	}

	return false;
}

function CharacterList()
{
	global $context, $smcFunc, $txt, $scripturl, $modSettings;
	global $image_proxy_enabled, $image_proxy_secret;

	isAllowedTo('view_mlist');
	loadTemplate('Profile-Chars');

	$context['page_title'] = $txt['chars_menu_title'];
	$context['sub_template'] = 'character_list';
	$context['linktree'][] = array(
		'name' => $txt['chars_menu_title'],
		'url' => $scripturl . '?action=characters',
	);

	$context['filterable_groups'] = array();
	foreach (get_char_membergroup_data() as $id_group => $group)
	{
		if ($group['is_character'])
			$context['filterable_groups'][$id_group] = $group;
	}

	$context['filter_groups'] = array();
	$filter = array();
	if (isset($_POST['filter']) && is_array($_POST['filter']))
	{
		$filter = $_POST['filter'];
	}
	elseif (isset($_GET['filter']))
	{
		$filter = explode(',', base64_decode($_GET['filter']));
	}

	if (!empty($filter))
	{
		if (allowedTo('admin_forum') && in_array(-1, $filter))
			$context['filter_groups'] = true;
		else
		{
			foreach ($filter as $filter_val)
			{
				if (isset($context['filterable_groups'][$filter_val]))
					$context['filter_groups'][] = (int) $filter_val;
			}
		}
	}

	$clauses = array(
		'chars.is_main = {int:not_main}',
	);
	$vars = array(
		'not_main' => 0,
	);

	$filter_url = '';
	if (!empty($context['filter_groups']))
	{
		if (is_array($context['filter_groups']))
		{
			$vars['filter_groups'] = $context['filter_groups'];
			$this_clause = array();
			foreach ($context['filter_groups'] as $group)
			{
				$this_clause[] = 'FIND_IN_SET(' . $group . ', chars.char_groups)';
			}
			$clauses[] = '(chars.main_char_group IN ({array_int:filter_groups}) OR (' . implode(' OR ', $this_clause) . '))';
			$filter_url = ';filter=' . base64_encode(implode(',', $context['filter_groups']));
		}
		else
		{
			$clauses[] = '(chars.main_char_group = 0 AND chars.char_groups = {empty})';
			$filter_url = ';filter=' . base64_encode('-1');
		}
	}

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_character)
		FROM {db_prefix}characters AS chars
		WHERE ' . implode(' AND ', $clauses),
		$vars
	);
	list($context['char_count']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['items_per_page'] = 12;
	$context['page_index'] = constructPageIndex($scripturl . '?action=characters' . $filter_url . ';start=%1$d', $_REQUEST['start'], $context['char_count'], $context['items_per_page'], true);
	$vars['start'] = $_REQUEST['start'];
	$vars['limit'] = $context['items_per_page'];

	$context['char_list'] = array();
	if (!empty($context['char_count']))
	{
		if (!empty($modSettings['avatar_max_width_external']))
		{
			addInlineCss('
.char_list_avatar { width: ' . $modSettings['avatar_max_width_external'] . 'px; height: ' . $modSettings['avatar_max_height_external'] . 'px; }
.char_list_name { max-width: ' . $modSettings['avatar_max_width_external'] . 'px; }');
		}

		$request = $smcFunc['db_query']('', '
			SELECT chars.id_character, chars.id_member, chars.character_name,
				chars.avatar, chars.posts, chars.date_created,
				chars.main_char_group, chars.char_groups
			FROM {db_prefix}characters AS chars
			WHERE ' . implode(' AND ', $clauses) . '
			ORDER BY chars.character_name
			LIMIT {int:start}, {int:limit}',
			$vars
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($image_proxy_enabled && !empty($row['avatar']) && stripos($row['avatar'], 'http://') !== false)
				$row['avatar'] = $boardurl . '/proxy.php?request=' . urlencode($row['avatar']) . '&hash=' . md5($row['avatar'] . $image_proxy_secret);
			elseif (empty($row['avatar']))
				$row['avatar'] = $modSettings['avatar_url'] . '/default.png';

			$groups = !empty($row['main_char_group']) ? array($row['main_char_group']) : array();
			$groups = array_merge($groups, explode(',', $row['char_groups']));
			$details = get_labels_and_badges($groups);
			$row['group_title'] = $details['title'];
			$row['group_color'] = $details['color'];
			$row['group_badges'] = $details['badges'];
			$context['char_list'][] = $row;
		}
		$smcFunc['db_free_result']($request);
	}
}

?>