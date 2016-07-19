<?php

if (!defined('SMF'))
	die('No direct access...');

function integrate_character_autosuggest(&$searchTypes)
{
	$searchTypes['memberchar'] = 'MemberChar';
	$searchTypes['character'] = 'Character';
	$searchTypes['rawcharacter'] = 'RawCharacter';
}

function AutoSuggest_Search_Character()
{
	global $user_info, $smcFunc, $context;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], ['%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;']);

	$xml_data = [
		'items' => [
			'identifier' => 'item',
			'children' => [],
		],
	];

	// Find the characters
	$request = $smcFunc['db_query']('', '
		SELECT chars.id_character, chars.id_member, chars.character_name, mem.real_name
		FROM {db_prefix}characters AS chars
		INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
		WHERE {raw:real_name} LIKE {string:search}
			AND mem.is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		[
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(character_name)' : 'character_name',
			'search' => $_REQUEST['search'],
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['display_name'] = strtr($row['character_name'] . ' (' . $row['real_name'] . ')', ['&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;']);

		$xml_data['items']['children'][$row['id_member']] = [
			'attributes' => [
				'id' => $row['id_member'] . ';area=characters;char=' . $row['id_character'],
			],
			'value' => $row['display_name'],
		];
	}
	$smcFunc['db_free_result']($request);

	return $xml_data;
}

function AutoSuggest_Search_RawCharacter()
{
	global $user_info, $smcFunc, $context;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], ['%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;']);

	$xml_data = [
		'items' => [
			'identifier' => 'item',
			'children' => [],
		],
	];

	// Find the characters
	$request = $smcFunc['db_query']('', '
		SELECT chars.id_character, chars.id_member, chars.character_name, mem.real_name
		FROM {db_prefix}characters AS chars
		INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
		WHERE {raw:real_name} LIKE {string:search}
			AND mem.is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		[
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(character_name)' : 'character_name',
			'search' => $_REQUEST['search'],
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['display_name'] = strtr($row['character_name'], ['&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;']);

		$xml_data['items']['children'][$row['id_character']] = [
			'attributes' => [
				'id' => $row['id_member'] . ';area=characters;char=' . $row['id_character'],
			],
			'value' => $row['display_name'],
		];
	}
	$smcFunc['db_free_result']($request);

	return $xml_data;
}

function AutoSuggest_Search_MemberChar()
{
	global $user_info, $smcFunc, $context;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], ['%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;']);

	// Find the member.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE {raw:real_name} LIKE {string:search}' . (!empty($context['search_param']['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		[
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name',
			'buddy_list' => $user_info['buddies'],
			'search' => $_REQUEST['search'],
		]
	);
	$xml_data = [
		'items' => [
			'identifier' => 'item',
			'children' => [],
		],
	];
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['real_name'] = strtr($row['real_name'], ['&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;']);

		$xml_data['items']['children'][$row['id_member']] = [
			'attributes' => [
				'id' => $row['id_member'],
			],
			'value' => $row['real_name'],
		];
	}
	$smcFunc['db_free_result']($request);

	// Find their characters
	$request = $smcFunc['db_query']('', '
		SELECT chars.id_member, chars.character_name, mem.real_name
		FROM {db_prefix}characters AS chars
		INNER JOIN {db_prefix}members AS mem ON (chars.id_member = mem.id_member)
		WHERE {raw:real_name} LIKE {string:search}
			AND mem.is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		[
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(character_name)' : 'character_name',
			'search' => $_REQUEST['search'],
		]
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't fetch when we already have the matching parent account.
		if (isset($xml_data['items']['children'][$row['id_member']])) {
			continue;
		}
		$row['display_name'] = strtr($row['character_name'] . ' (' . $row['real_name'] . ')', ['&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;']);

		$xml_data['items']['children'][$row['id_member']] = [
			'attributes' => [
				'id' => $row['id_member'],
			],
			'value' => $row['display_name'],
		];
	}
	$smcFunc['db_free_result']($request);

	return $xml_data;
}

?>