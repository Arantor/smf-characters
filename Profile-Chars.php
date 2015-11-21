<?php

if (!defined('SMF'))
	die('No direct access...');

function chars_profile_menu(&$profile_areas) {
	global $context, $cur_profile, $scripturl;
	static $called = false;

	// SMF 2.1, GH #3118, this can be called twice.
	if ($called) {
		return;
	} else {
		$called = true;
	}

	// So how are we calling this? Are we calling this directly in the profile area?
	// Depending on how bug 3118 is fixed, we can cover all the bases once!
	if (is_array($profile_areas['info']['areas']['summary']['permission'])) {
		// Classical array of own/any
		$own_only = array(
			'own' => 'is_not_guest',
			'any' => array(),
		);
		$own_any = array(
			'own' => 'is_not_guest',
			'any' => 'profile_view',
		);
	} else {
		// Coming from the generic menu hook
		$own_only = 'is_not_guest';
		$own_any = $context['user']['is_owner'] ? 'is_not_guest' : 'profile_view';
	}

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
	global $smcFunc;

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

	if ($return)
		return true;
	else
		die;
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

function char_theme() {
	global $context, $smcFunc;

	// If they don't have permission to be here, goodbye.
	if (!$context['character']['editable']) {
		redirectexit('action=profile;u=' . $context['id_member'] . ';area=characters;char=' . $context['character']['id_character']);
	}

	$context['sub_template'] = 'char_theme';
}

?>