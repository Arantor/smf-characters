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
					'subsections' => [],
				),
				'sheets' => array(
					'label' => $txt['char_sheet_admin'],
					'function' => 'CharacterSheets',
					'icon' => 'package_ops',
					'permission' => array('admin_forum'),
					'subsections' => [],
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
	$characters = [];
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
		'accounts' => [],
		'characters' => [],
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
		'save' => 'char_template_save',
	);

	$sa = isset($_GET['sa'], $subactions[$_GET['sa']]) ? $subactions[$_GET['sa']] : $subactions['index'];
	$sa();
}

function char_template_list()
{
	global $smcFunc, $context, $txt;

	$context['char_templates'] = [];
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

function char_template_add()
{
	global $context, $txt, $sourcedir;
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => '',
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
	$context['template_name'] = '';
	$context['template_id'] = 0;

	$context['page_title'] = $txt['char_templates_add'];
	$context['sub_template'] = 'char_template_edit';
	loadTemplate('Admin-Chars');
}

function char_template_edit()
{
	global $context, $txt, $sourcedir, $smcFunc;
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	$template_id = isset($_GET['template_id']) ? (int) $_GET['template_id'] : 0;
	$request = $smcFunc['db_query']('', '
		SELECT id_template, template_name, template
		FROM {db_prefix}character_sheet_templates
		WHERE id_template = {int:template}',
		array(
			'template' => $template_id,
		)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	if (empty($row))
	{
		redirectexit('action=admin;area=templates');
	}
	$context['template_id'] = $template_id;
	$context['template_name'] = $row['template_name'];

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => un_preparsecode($row['template']),
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

	$context['page_title'] = $txt['char_templates_edit'];
	$context['sub_template'] = 'char_template_edit';
	loadTemplate('Admin-Chars');
}

function char_template_save()
{
	global $context, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	checkSession();
	if (empty($_POST['template_name']) || empty($_POST['message']))
		redirectexit('action=admin;area=templates');

	$template_name = $smcFunc['htmlspecialchars'](trim($_POST['template_name']), ENT_QUOTES);
	$template = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
	preparsecode($template);

	$template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;

	if (empty($template_id)) {
		// New insertion
		$smcFunc['db_insert']('',
			'{db_prefix}character_sheet_templates',
			array('template_name' => 'string', 'template' => 'string', 'position' => 'int'),
			array($template_name, $template, 0),
			array('id_template')
		);
	} else {
		// Updating an existing one
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}character_sheet_templates
			SET template_name = {string:template_name},
				template = {string:template}
			WHERE id_template = {int:template_id}',
			array(
				'template_id' => $template_id,
				'template_name' => $template_name,
				'template' => $template,
			)
		);
	}

	redirectexit('action=admin;area=templates');
}

function CharacterSheets()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl;
	loadTemplate('Admin-Chars');
	require_once($sourcedir . '/Subs-List.php');

	$listOptions = array(
		'id' => 'approval_queue',
		'title' => $txt['char_sheet_admin'],
		'base_href' => $scripturl . '?action=admin;area=sheets',
		'default_sort_col' => 'updated',
		'no_items_label' => $txt['no_pending_sheets'],
		'get_items' => array(
			'function' => function($start, $items_per_page, $sort)
			{
				global $smcFunc;
				$rows = [];
				$request = $smcFunc['db_query']('', '
					SELECT mem.id_member, mem.real_name, chars.id_character,
						chars.character_name, MAX(csv.created_time) AS latest_version,
						MAX(csv.approved_time) AS last_approval, MAX(csv.approval_state) AS approval_state
					FROM {db_prefix}character_sheet_versions AS csv
					INNER JOIN {db_prefix}characters AS chars ON (csv.id_character = chars.id_character)
					INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
					GROUP BY csv.id_character
					HAVING approval_state = 1
					ORDER BY {raw:sort}',
					array(
						'sort' => $sort,
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$rows[] = $row;
				}
				$smcFunc['db_free_result']($request);
				return $rows;
			},
			'params' => array('regular'),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['name'],
				),
				'data' => array(
					'function' => function ($rowData) use ($scripturl)
					{
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . '" target="_blank">' . $rowData['real_name'] . '</a>';
					}
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'char_name' => array(
				'header' => array(
					'value' => str_replace(':', '', $txt['char_name']),
				),
				'data' => array(
					'function' => function ($rowData) use ($scripturl)
					{
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . ';area=characters;char=' . $rowData['id_character'] . '" target="_blank">' . $rowData['character_name'] . '</a>';
					}
				),
				'sort' => array(
					'default' => 'chars.character_name',
					'reverse' => 'chars.character_name DESC',
				),
			),
			'char_sheet' => array(
				'header' => array(
					'value' => '',
				),
				'data' => array(
					'function' => function ($rowData) use ($txt, $scripturl)
					{
						return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . ';area=characters;char=' . $rowData['id_character'] . ';sa=sheet" target="_blank">' . $txt['char_sheet'] . '</a>';
					},
					'class' => 'centercol',
				),
			),
			'updated' => array(
				'header' => array(
					'value' => $txt['last_updated'],
				),
				'data' => array(
					'db' => 'latest_version',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'latest_version',
					'reverse' => 'latest_version DESC',
				),
			),
			'approved' => array(
				'header' => array(
					'value' => $txt['previously_approved'],
				),
				'data' => array(
					'function' => function ($rowData) use ($txt)
					{
						return $rowData['last_approval'] ? '<span class="generic_icons approve_button" title="' . $txt['yes'] . '"></span>' : '<span class="generic_icons unapprove_button" title="' . $txt['no'] . '"></span>';
					},
					'class' => 'centercol',
				),
			),
		),
	);

	createList($listOptions);

	$context['page_title'] = $txt['char_sheet_admin'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'approval_queue';
}

?>