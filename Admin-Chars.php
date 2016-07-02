<?php

if (!defined('SMF'))
	die('No direct access...');

function integrate_chars_admin_actions(&$admin_areas)
{
	global $txt;
	if (allowedTo('admin_forum'))
		$admin_areas['members']['areas']['membergroups']['subsections']['badges'] = array($txt['badges'], 'admin_forum');
}

function integrate_chars_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	// Personal text isn't a thing.
	unset ($permissionList['membergroup']['profile_blurb']);
	// Neither are users having actual avatars.
	unset ($permissionList['membergroup']['profile_server_avatar']);
	unset ($permissionList['membergroup']['profile_upload_avatar']);
	unset ($permissionList['membergroup']['profile_remote_avatar']);
	// Or signatures, they _can_ have them, without question.
	unset ($permissionList['membergroup']['profile_signature']);
}

function integrate_chars_create_board(&$boardOptions, &$board_columns, &$board_parameters)
{
	if (isset($boardOptions['in_character']))
	{
		$board_columns['in_character'] = 'int';
		$board_parameters[] = $boardOptions['in_character'] ? 1 : 0;
	}
}

function integrate_delete_members_chars($users)
{
	global $smcFunc;

	// We need to fix the posted names of these users, so that they
	// tie into the characters they had.

	// 1. Get all the characters affected.
	$characters = array();
	$result = $smcFunc['db_query']('', '
		SELECT id_character, character_name
		FROM {db_prefix}characters
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$characters[$row['id_character']] = $row['character_name'];
	}
	$smcFunc['db_free_result']($result);

	// 2. Step through each of these and update them.
	foreach ($characters as $id_character => $character_name)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET id_character = 0,
				poster_name = {string:character_name}
			WHERE id_character = {int:id_character}',
			array(
				'id_character' => $id_character,
				'character_name' => $character_name,
			)
		);
	}

	// Then delete their characters.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}characters
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
}

function MembergroupBadges()
{
	global $smcFunc, $context, $txt, $settings;

	$context['groups'] = array(
		'accounts' => array(),
		'characters' => array(),
	);

	if (isset($_POST['group']) && is_array($_POST['group']))
	{
		$order = 1;
		foreach ($_POST['group'] as $group) {
			$group = (int) $group;
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}membergroups
				SET badge_order = {int:order}
				WHERE id_group = {int:group}',
				array(
					'order' => $order,
					'group' => $group,
				)
			);
			$order++;
		}
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, online_color, icons, is_character
		FROM {db_prefix}membergroups
		WHERE min_posts = -1
			AND id_group != {int:moderator_group}
		ORDER BY badge_order',
		array(
			'moderator_group' => 3
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['parsed_icons'] = '';
		if (!empty($row['icons']))
		{
			list($qty, $badge) = explode('#', $row['icons']);
			if (!empty($qty))
				$row['parsed_icons'] = str_repeat('<img src="' . $settings['default_images_url'] . '/membericons/' . $badge . '" alt="*">', $qty);
		}
		$context['groups'][$row['is_character'] ? 'characters' : 'accounts'][$row['id_group']] = $row;
	}
	$smcFunc['db_free_result']($request);

	loadTemplate('Admin-Chars');
	$context['page_title'] = $txt['badges'];
	$context['sub_template'] = 'membergroup_badges';
	loadJavascriptFile('chars-jquery-ui-1.11.4.js', array('default_theme' => true), 'chars_jquery');
	addInlineJavascript('
	$(\'.sortable\').sortable({handle: ".handle"});', true);
}

?>