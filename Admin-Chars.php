<?php

if (!defined('SMF'))
	die('No direct access...');

function integrate_chars_admin_actions(&$admin_areas)
{
	global $txt;
	if (allowedTo('admin_forum'))
	{
		$admin_areas['members']['areas']['membergroups']['subsections']['badges'] = array($txt['badges'], 'admin_forum');

		$admin_areas['characters'] = array(
			'title' => $txt['chars_menu_title'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'templates' => array(
					'label' => $txt['char_templates'],
					'function' => 'CharacterTemplates',
					'icon' => 'quick_edit_button',
					'permission' => array('admin_forum'),
					'subsections' => array(),
				),
			),
		);
	}
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
		checkSession();
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

function CharacterTemplates()
{
	$subactions = array(
		'index' => 'char_template_list',
		'add' => 'char_template_add',
		'edit' => 'char_template_edit',
		'reorder' => 'char_template_reorder',
	);

	$sa = isset($_GET['sa'], $subactions[$_GET['sa']]) ? $subactions[$_GET['sa']] : $subactions['index'];
	$sa();
}

function char_template_list() {
	global $smcFunc, $context, $txt;

	$context['char_templates'] = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_template, template_name, position
		FROM {db_prefix}character_sheet_templates
		ORDER BY position ASC');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['char_templates'][$row['id_template']] = $row;
	}
	$smcFunc['db_free_result']($request);

	loadTemplate('Admin-Chars');
	$context['page_title'] = $txt['char_templates'];
	$context['sub_template'] = 'char_templates';
	loadJavascriptFile('chars-jquery-ui-1.11.4.js', array('default_theme' => true), 'chars_jquery');
	addInlineJavascript('
	$(\'.sortable\').sortable({handle: ".handle"});', true);
}

function char_template_reorder()
{
	global $smcFunc;
	if (isset($_POST['template']) && is_array($_POST['template']))
	{
		checkSession();
		$order = 1;
		foreach ($_POST['template'] as $template) {
			$template = (int) $template;
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}character_sheet_templates
				SET position = {int:order}
				WHERE id_template = {int:template}',
				array(
					'order' => $order,
					'template' => $template,
				)
			);
			$order++;
		}
	}
	redirectexit('action=admin;area=templates');
}

?>