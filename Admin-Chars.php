<?php

if (!defined('SMF'))
	die('No direct access...');

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

?>