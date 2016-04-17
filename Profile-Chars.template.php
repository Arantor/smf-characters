<?php

function character_popup_row($id_character, $char) {
	global $context, $scripturl, $txt, $user_info, $cur_profile, $modSettings;
	echo '
				<li>
					<div class="character">
						<span class="avatar">
							', !empty($char['avatar']) ? '<img src="' . $char['avatar'] . '" alt="" />' : '<img src="' . $modSettings['avatar_url'] . '/default.png" alt="" />', '
						</span>
						<a href="', $scripturl, $char['character_url'], '">', $char['character_name'], '</a>';
	if (!empty($char['is_main']))
	{
		echo '
						(<abbr title="', $txt['main_char_desc'], '">', $txt['main_char'], '</abbr>)';
	}
	if ($id_character != $user_info['id_character'])
		echo '
						<span class="switch">
							<span data-href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=char_switch;char=', $id_character, ';', $context['session_var'], '=', $context['session_id'], '" class="button">', $txt['switch_chars'], '</a>
						</span>';

	echo '
					</div>
				</li>';
}

function template_characters_popup() {
	global $context, $scripturl, $txt, $user_info, $cur_profile, $modSettings;
	echo '
		<div id="posting_as">', sprintf($txt['you_are_posting_as'], $user_info['character_name']), '
		<div id="my_account" class="chars_container">
			<ul>';
	foreach ($cur_profile['characters'] as $id_character => $char)
	{
		if (!empty($char['is_main']))
			character_popup_row($id_character, $char);
	}
	echo '
			</ul>
		</div>
		<div id="my_characters">', $txt['my_characters'], '</div>
		<div id="chars_container" class="chars_container">
			<ul>';
	foreach ($cur_profile['characters'] as $id_character => $char)
	{
		if (empty($char['is_main']))
			character_popup_row($id_character, $char);
	}
	echo '
			</ul>
		</div>
		<script>
		$(".chars_container .switch span.button").on("click", function() {
			$.ajax({
				url: $(this).data("href")
			}).done(function( data ) {
				console.log("done");
				location.reload();
			});
		});
		</script>';
}

function template_character_profile() {
	global $context, $txt, $user_profile, $scripturl, $user_info;

	echo '
		<div id="admin_content">
					<div class="cat_bar">
						<h3 class="catbg">
						', !empty($context['character']['avatar']) ? '<img class="icon" style="max-width: 25px; max-height: 25px;" src="' . $context['character']['avatar'] . '" alt="">' : '', '
						', $context['character']['character_name'], '
						</h3>
					</div>
					
		<div class="errorbox" style="display:none" id="profile_error">
		</div>
	<div id="profileview" class="roundframe flow_auto">
		<div id="basicinfo">';

	if (!empty($context['character']['avatar']))
		echo '
			<img class="avatar" src="', $context['character']['avatar'], '" alt=""><br /><br />';
	else
		echo '
			<img class="avatar" src="', $context['member']['avatar']['href'], '" alt=""><br /><br />';

	if ($context['user']['is_owner'] && $user_info['id_character'] != $context['character']['id_character'])
	{
		echo '
			<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=char_switch_redir;char=', $context['character']['id_character'], ';', $context['session_var'], '=', $context['session_id'], '" class="button">', $txt['switch_to_char'], '</a><br /><br />';
	}
	if ($context['character']['editable'])
	{
		echo '
			<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=edit" class="button">', $txt['edit_char'], '</a><br /><br />';
	}
	if ($context['character']['editable'] && $context['character']['posts'] == 0 && !$context['character']['is_main'])
	{
		echo '
			<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=delete;', $context['session_var'], '=', $context['session_id'], '" class="button" onclick="return confirm(', JavaScriptEscape($txt['are_you_sure_delete_char']), ');">', $txt['delete_char'], '</a><br /><br />';
	}
	$days_registered = (int) ((time() - $user_profile[$context['id_member']]['date_registered']) / (3600 * 24));
	$posts_per_day = $days_registered > 1 ? comma_format($context['character']['posts'] / $days_registered, 2) : '';
	echo '
		</div>
		<div id="detailedinfo">
			<dl>
				<dt>', $txt['char_name'], '</dt>
				<dd>', $context['character']['character_name'], '</dd>
				<dt>', $txt['profile_posts'], ':</dt>
				<dd>', comma_format($context['character']['posts']), $days_registered > 1 ? ' (' . $posts_per_day . ' per day)' : '', '</dd>
				<dt>', $txt['age'], ':</dt>
				<dd>', !empty($context['character']['age']) ? $context['character']['age'] : 'N/A', '</dd>
			</dl>';

	if (!empty($context['character']['signature'])) {
		echo '
			<div class="char_signature">', parse_bbc($context['character']['signature'], true, 'sig_char' . $context['character']['id_character']), '</div>
			<dl></dl>';
	}

	echo '
			<dl class="noborder">
				<dt>', $txt['date_created'], '</dt>
				<dd>', timeformat($context['character']['date_created']), '</dd>
				<dt>', $txt['lastLoggedIn'], ': </dt>
				<dd>', timeformat($context['character']['last_active']), '</dd>';

	if ($context['character']['editable'])
		echo '
				<dt>', $txt['current_theme'], ':</dt>
				<dd>', $context['character']['theme_name'], ' <a class="button" href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=theme">', $txt['change_theme'], '</a></dd>';

	echo '
			</dl>
		</div>
	</div>
<div class="clear"></div>
				</div>';
}

function template_edit_char() {
	global $context, $txt, $scripturl, $modSettings;

	if ($context['char_updated'])
	{
		echo '
		<div class="infobox">
			', sprintf($txt[$context['user']['is_owner'] ? 'character_updated_you' : 'character_updated_else'], $context['character']['character_name']), '
		</div>';
	}

	echo '
		<div id="admin_content">
					<div class="cat_bar">
						<h3 class="catbg">
						', $txt['edit_char'], '
						</h3>
					</div>';

	echo '
	<div id="profileview" class="roundframe flow_auto">
		<div class="errorbox" id="profile_error"', empty($context['form_errors']) ? ' style="display:none"' : '', '>
			<span>', $txt['char_editing_error'], '</span>
			<ul id="list_errors">';
	foreach ($context['form_errors'] as $err)
		echo '
				<li>', $err, '</li>';
	echo '
			</ul>
		</div>
		<div id="basicinfo">';

	echo '
		</div>
		<div id="detailedinfo">
			<form id="creator" action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=edit" method="post" accept-charset="', $context['character_set'], '">';

	if ($context['character']['groups_editable'])
	{
		echo '
				<dl>
					<dt>
						<strong>', $txt['primary_membergroup'], ': </strong><br>
					</dt>
					<dd>
						<select name="id_group">';

		// Fill the select box with all primary member groups that can be assigned to a member.
		foreach ($context['member_groups'] as $member_group)
			if (!empty($member_group['can_be_primary']))
				echo '
							<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected' : '', '>
								', $member_group['name'], '
							</option>';
		echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['additional_membergroups'], ':</strong>
					</dt>
					<dd>
						<span id="additional_groupsList">
							<input type="hidden" name="additional_groups[]" value="0">';

		// For each membergroup show a checkbox so members can be assigned to more than one group.
		foreach ($context['member_groups'] as $member_group)
			if ($member_group['can_be_additional'])
				echo '
							<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked' : '', ' class="input_check"> ', $member_group['name'], '</label><br>';
		echo '
						</span>
						<a href="javascript:void(0);" onclick="document.getElementById(\'additional_groupsList\').style.display = \'block\'; document.getElementById(\'additional_groupsLink\').style.display = \'none\'; return false;" id="additional_groupsLink" style="display: none;" class="toggle_down">', $txt['additional_membergroups_show'], '</a>
						<script>
							document.getElementById("additional_groupsList").style.display = "none";
							document.getElementById("additional_groupsLink").style.display = "";
						</script>
					</dd>
				</dl>';
	}

	echo '
				<dl>
					<dt>', $txt['char_name'], '</dt>
					<dd>
						<input type="text" name="char_name" id="char_name" size="50" value="', $context['character']['character_name'], '" maxlength="50" class="input_text">
					</dd>
					<dt>
						', $txt['avatar_link'];
	if (!empty($modSettings['avatar_max_width_external']))
	{
		echo '
						<div class="smalltext">', sprintf(
							$txt['max_avatar_size'],
							$modSettings['avatar_max_width_external'],
							$modSettings['avatar_max_height_external']
						), '</div>';
	}

	echo '
					</dt>
					<dd>
						<input type="text" name="avatar" id="avatar" size="50" value="', !empty($context['character']['avatar']) ? $context['character']['avatar'] : '', '" maxlength="255" class="input_type">
					</dd>
					<dt>', $txt['avatar_preview'], '</dt>
					<dd id="avatar_preview"></dd>
					<dt>', $txt['age'], ':</dt>
					<dd>
						<input type="text" name="age" id="age" size="50" value="', !empty($context['character']['age']) ? $context['character']['age'] : '', '" maxlength="50" class="input_text">
					</dd>
				</dl>
				<div class="char_signature"></div>
				<dl class="noborder" id="current_sig">
					<dt>', $txt['current_signature'], ':</dt>
				</dl>
				<div class="signature" id="current_sig_parsed">
					', !empty($context['character']['signature']) ? parse_bbc($context['character']['signature'], true, 'sig_char_' . $context['character']['id_character']) : '<em>' . $txt['no_signature_set'] . '</em>', '
				</div>
				<dl></dl>
				<dl class="noborder" id="sig_preview">
					<dt>', $txt['signature_preview'], ':</dt>
				</dl>
				<div class="signature" id="sig_preview_parsed"></div>
				<dl class="noborder" id="sig_header">
					<dt>', $txt['signature'], ':</dt>
				</dl>
				', template_control_richedit('char_signature', 'smileyBox_message', 'bbcBox_message');

	echo '
				<div style="width: 75%">
					<span class="floatright"><input type="button" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '" class="button_submit"></span>';
	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
				<span class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></span><br>';

	echo '
				</div>';

	if ($context['signature_warning'])
		echo '
					<span class="smalltext">', $context['signature_warning'], '</span>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
				<script>
					var maxLength = ', $context['signature_limits']['max_length'], ';
					last_signature = false;

					function calcCharLeft()
					{
						var oldSignature = "", currentSignature = $("#char_signature").data("sceditor").getText().replace(/&#/g, \'&#38;#\');
						var currentChars = 0;

						if (!document.getElementById("signatureLeft"))
							return;

						// Changed since we were last here?
						if (last_signature === currentSignature)
							return;
						last_signature = currentSignature;

						if (oldSignature != currentSignature)
						{
							oldSignature = currentSignature;

							var currentChars = currentSignature.replace(/\r/, "").length;
							if (is_opera)
								currentChars = currentSignature.replace(/\r/g, "").length;

							if (currentChars > maxLength)
								document.getElementById("signatureLeft").className = "error";
							else
								document.getElementById("signatureLeft").className = "";

							if (currentChars > maxLength)
								chars_ajax_getSignaturePreview(false);
							// Only hide it if the only errors were signature errors...
							else if (currentChars <= maxLength)
							{
								// Are there any errors to begin with?
								if ($(document).has("#list_errors"))
								{
									// Remove any signature errors
									$("#list_errors").remove(".sig_error");

									// Show this if other errors remain
									if (!$("#list_errors").has("li"))
									{
										$("#profile_error").css({display:"none"});
										$("#profile_error").html("");
									}
								}
							}
						}

						setInnerHTML(document.getElementById("signatureLeft"), maxLength - currentChars);
					}
					$(document).ready(function() {
						calcCharLeft();
						$("#preview_button").click(function() {
							return chars_ajax_getSignaturePreview(true);
						});
					});
					window.setInterval(calcCharLeft, 1000);
				</script>
				<dl></dl>
				<input type="hidden" name="u" value="', $context['id_member'], '" />
				<input type="submit" name="edit_char" class="button_submit" value="', $txt['save_changes'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['edit-char' . $context['character']['id_character'] . '_token_var'], '" value="', $context['edit-char' . $context['character']['id_character'] . '_token'], '">
			</form>
		</div>
	</div>
<div class="clear"></div>
				</div>';
}

function template_char_theme()
{
	global $context, $txt, $scripturl;

	echo '
		<div id="admin_content">
					<div class="cat_bar">
						<h3 class="catbg">
						Theme
						</h3>
					</div>

	<div id="profileview" class="roundframe flow_auto">
		<form id="char_theme_wrapper" action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=theme" method="post">';

	foreach ($context['themes'] as $id_theme => $theme)
	{
		echo '
			<div class="char_theme_container">
				<button name="theme[', $id_theme, ']" class="button"><img src="', $theme['thumbnail'], '" alt=""></button>
				', $theme['name'], '
			</div>';
	}
	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
<div class="clear"></div>
				</div>';
}

function template_char_posts()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', empty($context['is_topics']) ? $txt['showMessages'] : $txt['showTopics'], ' - ', $context['character']['character_name'], '
			</h3>
		</div>', !empty($context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . $context['page_index'] . '</div>
		</div>' : '';

	// For every post to be displayed, give it its own div, and show the important details of the post.
	foreach ($context['posts'] as $post)
	{
		echo '
		<div class="', $post['css_class'] ,'">
			<div class="counter">', $post['counter'], '</div>
			<div class="topic_details">
				<h5><strong><a href="', $scripturl, '?board=', $post['board']['id'], '.0">', $post['board']['name'], '</a> / <a href="', $scripturl, '?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['subject'], '</a></strong></h5>
				<span class="smalltext">', $post['time'], '</span>
			</div>
			<div class="list_posts">';

		if (!$post['approved'])
			echo '
				<div class="approve_post">
					<em>', $txt['post_awaiting_approval'], '</em>
				</div>';

		echo '
				', $post['body'], '
			</div>';

		if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
			echo '
			<div class="floatright">
				<ul class="quickbuttons">';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
					<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '"><span class="generic_icons reply_button"></span>', $txt['reply'], '</a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
					<li><a href="', $scripturl . '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '"><span class="generic_icons quote"></span>', $txt['quote_action'], '</a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
					<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';profile;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" data-confirm="', $txt['remove_message'] ,'" class="you_sure"><span class="generic_icons remove_button"></span>', $txt['remove'], '</a></li>';

		if ($post['can_reply'] || $post['can_quote'] || $post['can_delete'])
			echo '
				</ul>
			</div>';

		echo '
		</div>';
	}

	// No posts? Just end with a informative message.
	if (empty($context['posts']))
		echo '
			<div class="windowbg2">
				', $context['is_topics'] ? $txt['show_topics_none'] : $txt['show_posts_none'], '
			</div>
		</div>';

	// Show more page numbers.
	if (!empty($context['page_index']))
		echo '
		<div class="pagesection">
			<div class="pagelinks">', $context['page_index'], '</div>
		</div>';
}

// This should be in a Admin-Chars.template.php file but I couldn't be bothered.
function template_membergroup_badges()
{
	global $scripturl, $context, $txt;

	echo '
		<form method="post" action="', $scripturl, '?action=admin;area=membergroups;sa=badges;', $context['session_var'], '=', $context['session_id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['char_group_level_acct'], '</h3>
			</div>
			<div class="windowbg2">
				<ul class="sortable">';

	foreach ($context['groups']['accounts'] as $group)
		display_group($group);

	echo '
				</ul>
			</div>

			<div class="cat_bar">
				<h3 class="catbg">', $txt['char_group_level_char'], '</h3>
			</div>
			<div class="windowbg2">
				<ul class="sortable">';

	foreach ($context['groups']['characters'] as $group)
		display_group($group);

	echo '
				</ul>
			</div>
			<input type="submit" value="', $txt['save'], '" class="button_submit">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<div class="clear"></div>
		</form>';
}

function display_group($group)
{
	global $txt, $settings;
	static $order = 1;

	echo '
					<li class="character_group">';

	if (!empty($group['online_color']))
		echo '
						<div class="group_name"><span style="color:', $group['online_color'], '">', $group['group_name'], '</span></div>';
	else
		echo '
						<div class="group_name">', $group['group_name'], '</div>';

	if (!empty($group['parsed_icons']))
		echo '
						<div class="group_icons">', $group['parsed_icons'], '</div>';
	else
		echo '
						<div class="group_icons">', $txt['no_badge'], '</div>';

	echo '
						<img src="', $settings['default_images_url'] . '/toggle.png" class="handle">';

	echo '
						<input type="hidden" name="group[', $group['id_group'], ']" value="', $group['id_group'], '">
					</li>';
	$order++;
}

function template_char_stats()
{
	global $context, $txt;

	// First, show a few text statistics such as post/topic count.
	echo '
	<div id="profileview" class="roundframe">
		<div id="generalstats">
			<dl class="stats">
				<dt>', $txt['statPanel_total_posts'], ':</dt>
				<dd>', $context['num_posts'], ' ', $txt['statPanel_posts'], '</dd>
				<dt>', $txt['statPanel_total_topics'], ':</dt>
				<dd>', $context['num_topics'], ' ', $txt['statPanel_topics'], '</dd>
			</dl>
		</div>';

	// This next section draws a graph showing what times of day they post the most.
	echo '
		<div id="activitytime" class="flow_hidden">
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="generic_icons history"></span> ', $txt['statPanel_activityTime'], '
				</h3>
			</div>';

	// If they haven't post at all, don't draw the graph.
	if (empty($context['posts_by_time']))
		echo '
			<span class="centertext">', $txt['statPanel_noPosts'], '</span>';
	// Otherwise do!
	else
	{
		echo '
			<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach ($context['posts_by_time'] as $time_of_day)
		{
			echo '
				<li', $time_of_day['is_last'] ? ' class="last"' : '', '>
					<div class="bar" style="padding-top: ', ((int) (100 - $time_of_day['relative_percent'])), 'px;" title="', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '">
						<div style="height: ', (int) $time_of_day['relative_percent'], 'px;">
							<span>', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '</span>
						</div>
					</div>
					<span class="stats_hour">', $time_of_day['hour_format'], '</span>
				</li>';
		}

		echo '

			</ul>';
	}

	echo '
			<span class="clear">
		</div>';

	// Two columns with the most popular boards by posts and activity (activity = users posts / total posts).
	echo '
		<div class="flow_hidden">
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="generic_icons replies"></span> ', $txt['statPanel_topBoards'], '
					</h3>
				</div>';

	if (empty($context['popular_boards']))
		echo '
				<span class="centertext">', $txt['statPanel_noPosts'], '</span>';

	else
	{
		echo '
				<dl class="stats">';

		// Draw a bar for every board.
		foreach ($context['popular_boards'] as $board)
		{
			echo '
					<dt>', $board['link'], '</dt>
					<dd>
						<div class="profile_pie" style="background-position: -', ((int) ($board['posts_percent'] / 5) * 20), 'px 0;" title="', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_char'], $board['posts_percent']), '">
							', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_char'], $board['posts_percent']), '
						</div>
						', empty($context['hide_num_posts']) ? $board['posts'] : '', '
					</dd>';
		}

		echo '
				</dl>';
	}
	echo '
			</div>';
	echo '
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="generic_icons replies"></span> ', $txt['statPanel_topBoardsActivity'], '
					</h3>
				</div>';

	if (empty($context['board_activity']))
		echo '
				<span>', $txt['statPanel_noPosts'], '</span>';
	else
	{
		echo '
				<dl class="stats">';

		// Draw a bar for every board.
		foreach ($context['board_activity'] as $activity)
		{
			echo '
					<dt>', $activity['link'], '</dt>
					<dd>
						<div class="profile_pie" style="background-position: -', ((int) ($activity['percent'] / 5) * 20), 'px 0;" title="', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '">
							', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '
						</div>
						', $activity['percent'], '%
					</dd>';
		}

		echo '
				</dl>';
	}
	echo '
			</div>
		</div>';

	echo '
	</div>';
}

function template_char_summary()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// Display the basic information about the user
	echo '
	<div id="profileview" class="roundframe flow_auto">
		<div id="basicinfo">';

	// Are there any custom profile fields for above the name?
	if (!empty($context['print_custom_fields']['above_member']))
	{
		echo '
			<div class="custom_fields_above_name">
				<ul >';

		foreach ($context['print_custom_fields']['above_member'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li>', $field['output_html'], '</li>';

		echo '
				</ul>
			</div>
			<br>';
	}

	echo '
			<div class="username clear">
				<h4>', $context['member']['name'], '</h4>
			</div>
			', $context['member']['avatar']['image'], '
			<div class="badges">', $context['member']['badges'], '</div>
			<div class="position">', (!empty($context['member']['group']) ? $context['member']['group'] : ''), '</div>';

	// Are there any custom profile fields for below the avatar?
	if (!empty($context['print_custom_fields']['below_avatar']))
	{
		echo '
			<div class="custom_fields_below_avatar">
				<ul >';

		foreach ($context['print_custom_fields']['below_avatar'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li>', $field['output_html'], '</li>';

		echo '
				</ul>
			</div>
			<br>';
	}

		echo '
			<ul class="clear">';
	// Email is only visible if it's your profile or you have the moderate_forum permission
	if ($context['member']['show_email'])
		echo '
				<li><a href="mailto:', $context['member']['email'], '" title="', $context['member']['email'], '" rel="nofollow"><span class="generic_icons mail" title="' . $txt['email'] . '"></span></a></li>';

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
		echo '
				<li><a href="', $context['member']['website']['url'], '" title="' . $context['member']['website']['title'] . '" target="_blank" class="new_win">', ($settings['use_image_buttons'] ? '<span class="generic_icons www" title="' . $context['member']['website']['title'] . '"></span>' : $txt['www']), '</a></li>';

	// Are there any custom profile fields as icons?
	if (!empty($context['print_custom_fields']['icons']))
	{
		foreach ($context['print_custom_fields']['icons'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li class="custom_field">', $field['output_html'], '</li>';
	}

	echo '
			</ul>
			<span id="userstatus">', $context['can_send_pm'] ? '<a href="' . $context['member']['online']['href'] . '" title="' . $context['member']['online']['text'] . '" rel="nofollow">' : '', $settings['use_image_buttons'] ? '<span class="' . ($context['member']['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . $context['member']['online']['text'] . '"></span>' : $context['member']['online']['label'], $context['can_send_pm'] ? '</a>' : '', $settings['use_image_buttons'] ? '<span class="smalltext"> ' . $context['member']['online']['label'] . '</span>' : '';

	// Can they add this member as a buddy?
	if (!empty($context['can_have_buddy']) && !$context['user']['is_owner'])
		echo '
				<br><a href="', $scripturl, '?action=buddy;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">[', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], ']</a>';

	echo '
			</span>';

	if (!$context['user']['is_owner'] && $context['can_send_pm'])
		echo '
			<a href="', $scripturl, '?action=pm;sa=send;u=', $context['id_member'], '" class="infolinks">', $txt['profile_sendpm_short'], '</a>';

	echo '
			<a href="', $scripturl, '?action=profile;area=showposts;u=', $context['id_member'], '" class="infolinks">', $txt['showPosts'], '</a>';

	if ($context['user']['is_owner'] && !empty($modSettings['drafts_post_enabled']))
		echo '
			<a href="', $scripturl, '?action=profile;area=showdrafts;u=', $context['id_member'], '" class="infolinks">', $txt['drafts_show'], '</a>';

	echo '
			<a href="', $scripturl, '?action=profile;area=statistics;u=', $context['id_member'], '" class="infolinks">', $txt['statPanel'], '</a>';

	// Are there any custom profile fields for bottom?
	if (!empty($context['print_custom_fields']['bottom_poster']))
	{
		echo '
			<div class="custom_fields_bottom">
				<ul class="nolist">';

		foreach ($context['print_custom_fields']['bottom_poster'] as $field)
			if (!empty($field['output_html']))
				echo '
					<li>', $field['output_html'], '</li>';

		echo '
				</ul>
			</div>';
	}

	echo '
		</div>';

	echo '
		<div id="detailedinfo">
			<dl>';

	if ($context['user']['is_owner'] || $context['user']['is_admin'])
		echo '
				<dt>', $txt['username'], ': </dt>
				<dd>', $context['member']['username'], '</dd>';

	if (!isset($context['disabled_fields']['posts']))
		echo '
				<dt>', $txt['profile_posts'], ': </dt>
				<dd>', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</dd>';

	if ($context['member']['show_email'])
	{
		echo '
				<dt>', $txt['email'], ': </dt>
				<dd><a href="mailto:', $context['member']['email'], '">', $context['member']['email'], '</a></dd>';
	}

	if (!empty($modSettings['titlesEnable']) && !empty($context['member']['title']))
		echo '
				<dt>', $txt['custom_title'], ': </dt>
				<dd>', $context['member']['title'], '</dd>';

	if (!empty($context['member']['blurb']))
		echo '
				<dt>', $txt['personal_text'], ': </dt>
				<dd>', $context['member']['blurb'], '</dd>';

	echo '
				<dt>', $txt['age'], ':</dt>
				<dd>', $context['member']['age'] . ($context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . $settings['images_url'] . '/cake.png" alt="">' : ''), '</dd>';

	echo '
			</dl>';

	// Any custom fields for standard placement?
	if (!empty($context['print_custom_fields']['standard']))
	{
		echo '
				<dl>';

		foreach ($context['print_custom_fields']['standard'] as $field)
			if (!empty($field['output_html']))
				echo '
					<dt>', $field['name'], ':</dt>
					<dd>', $field['output_html'], '</dd>';

		echo '
				</dl>';
	}

	echo '
				<dl class="noborder">';

	// Can they view/issue a warning?
	if ($context['can_view_warning'] && $context['member']['warning'])
	{
		echo '
					<dt>', $txt['profile_warning_level'], ': </dt>
					<dd>
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=', ($context['can_issue_warning'] && !$context['user']['is_owner'] ? 'issuewarning' : 'viewwarning') , '">', $context['member']['warning'], '%</a>';

		// Can we provide information on what this means?
		if (!empty($context['warning_status']))
			echo '
						<span class="smalltext">(', $context['warning_status'], ')</span>';

		echo '
					</dd>';
	}

	// Is this member requiring activation and/or banned?
	if (!empty($context['activate_message']) || !empty($context['member']['bans']))
	{

		// If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves.
		if (!empty($context['activate_message']))
			echo '
					<dt class="clear"><span class="alert">', $context['activate_message'], '</span>&nbsp;(<a href="', $context['activate_link'], '"', ($context['activate_type'] == 4 ? ' class="you_sure" data-confirm="'. $txt['profileConfirm'] .'"' : ''), '>', $context['activate_link_text'], '</a>)</dt>';

		// If the current member is banned, show a message and possibly a link to the ban.
		if (!empty($context['member']['bans']))
		{
			echo '
					<dt class="clear"><span class="alert">', $txt['user_is_banned'], '</span>&nbsp;[<a href="#" onclick="document.getElementById(\'ban_info\').style.display = document.getElementById(\'ban_info\').style.display == \'none\' ? \'\' : \'none\';return false;">' . $txt['view_ban'] . '</a>]</dt>
					<dt class="clear" id="ban_info" style="display: none;">
						<strong>', $txt['user_banned_by_following'], ':</strong>';

			foreach ($context['member']['bans'] as $ban)
				echo '
						<br><span class="smalltext">', $ban['explanation'], '</span>';

			echo '
					</dt>';
		}
	}

	echo '
					<dt>', $txt['date_registered'], ': </dt>
					<dd>', $context['member']['registered'], '</dd>';

	// If the person looking is allowed, they can check the members IP address and hostname.
	if ($context['can_see_ip'])
	{
		if (!empty($context['member']['ip']))
		echo '
					<dt>', $txt['ip'], ': </dt>
					<dd><a href="', $scripturl, '?action=profile;area=tracking;sa=ip;searchip=', $context['member']['ip'], ';u=', $context['member']['id'], '">', $context['member']['ip'], '</a></dd>';

		if (empty($modSettings['disableHostnameLookup']) && !empty($context['member']['ip']))
			echo '
					<dt>', $txt['hostname'], ': </dt>
					<dd>', $context['member']['hostname'], '</dd>';
	}

	echo '
					<dt>', $txt['local_time'], ':</dt>
					<dd>', $context['member']['local_time'], '</dd>';

	if (!empty($modSettings['userLanguage']) && !empty($context['member']['language']))
		echo '
					<dt>', $txt['language'], ':</dt>
					<dd>', $context['member']['language'], '</dd>';

	if ($context['member']['show_last_login'])
		echo '
					<dt>', $txt['lastLoggedIn'], ': </dt>
					<dd>', $context['member']['last_login'], (!empty($context['member']['is_hidden']) ? ' (' . $txt['hidden'] . ')' : ''), '</dd>';

	echo '
				</dl>';

	// Are there any custom profile fields for above the signature?
	if (!empty($context['print_custom_fields']['above_signature']))
	{
		echo '
				<div class="custom_fields_above_signature">
					<ul class="nolist">';

		foreach ($context['print_custom_fields']['above_signature'] as $field)
			if (!empty($field['output_html']))
				echo '
						<li>', $field['output_html'], '</li>';

		echo '
					</ul>
				</div>';
	}

	// Show the users signature.
	if ($context['signature_enabled'] && !empty($context['member']['signature']))
		echo '
				<div class="signature">
					<h5>', $txt['signature'], ':</h5>
					', $context['member']['signature'], '
				</div>';

	// Are there any custom profile fields for below the signature?
	if (!empty($context['print_custom_fields']['below_signature']))
	{
		echo '
				<div class="custom_fields_below_signature">
					<ul class="nolist">';

		foreach ($context['print_custom_fields']['below_signature'] as $field)
			if (!empty($field['output_html']))
				echo '
						<li>', $field['output_html'], '</li>';

		echo '
					</ul>
				</div>';
	}

	if (!empty($context['member']['characters']) && count($context['member']['characters']) > 1)
	{
		echo '
				<div class="character_list">
					<h5>', $txt['my_characters'], ':</h5>
				</div>
				<ul class="characters">';
		foreach ($context['member']['characters'] as $char)
		{
			if ($char['is_main'])
				continue;

			echo '
					<li>
						<div class="char_avatar">
							', !empty($char['avatar']) ? '<img src="' . $char['avatar'] . '" alt="">' : '', '
						</div>
						<div class="char_name">
							<a href="', $scripturl, $char['character_url'], '">', $char['character_name'], '</a>
							<div class="char_created">
								', sprintf($txt['char_created'], timeformat($char['date_created'])), '
							</div>
						</div>
						<div class="char_group">
							', $context['member']['display_group'], '
							<div class="char_created">&nbsp;</div>
						</div>
					</li>';
			//var_dump($char);
		}
		echo '
				</ul>';
	}

	echo '
		</div>
	</div>
<div class="clear"></div>';
}

?>