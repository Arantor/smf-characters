<?php

if (!defined('SMF'))
	die('No direct access...');

function chars_profile_menu(&$profile_areas) {
	global $context, $cur_profile, $scripturl;

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
			$context['form_errors'][] = 'character_must_have_name';
		elseif ($new_name != $context['character']['character_name'])
			$changes['character_name'] = $new_name;

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
				$context['form_errors'][] = 'avatar_must_be_real_url';
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

function char_theme() {
	global $context, $smcFunc;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$context['sub_template'] = 'char_theme';
}

?>