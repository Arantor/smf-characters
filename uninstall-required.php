<?php

/**
 * This script removes all the extraneous data if the user requests it be removed on uninstall.
 *
 * NOTE: This script is meant to run using the <samp><code></code></samp> elements of our package-info.xml file. This is because
 * certain items in the database and within SMF will need to be removed regardless of whether the user wants to keep data or not,
 * for example hooks need to be deactivated.
 */

/**
 *  Before attempting to execute, this file attempts to load SSI.php to enable access to the database functions.
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	require_once(dirname(__FILE__) . '/SSI.php');
	db_extend('packages');
}
// If we are outside SMF and can't find SSI.php, then throw an error
elseif (!defined('SMF'))
{
	die('<b>Error:</b> Cannot uninstall - please verify you put this file in the same place as SMF\'s SSI.php.');
}

global $smcFunc;

// 1. Removing all the SMF hooks.
$hooks = array();
$hooks[] = array(
	'hook' => 'integrate_user_info',
	'function' => 'integrate_chars',
	'file' => '$sourcedir/Characters.php',
);

foreach ($hooks as $hook)
{
    remove_integration_function($hook['hook'], $hook['function'], true, !empty($hook['file']) ? $hook['file'] : '');
}

?>