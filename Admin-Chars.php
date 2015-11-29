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

?>