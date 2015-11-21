<?php

function template_characters_popup() {
	global $context, $scripturl, $txt, $user_info, $cur_profile;
	echo '
		<div id="posting_as">', sprintf($txt['you_are_posting_as'], $user_info['character_name']), '
		<div id="my_characters">', $txt['my_characters'], '</div>
		<div id="chars_container">
			<ul>';
	foreach ($cur_profile['characters'] as $id_character => $char)
	{
		echo '
				<li>
					<div class="character">
						<span class="avatar">
							', !empty($char['avatar']) ? '<img src="' . $char['avatar'] . '" alt="" />' : '', '
						</span>
						<a href="', $scripturl, $char['character_url'], '">', $char['character_name'], '</a>';
		if ($id_character != $user_info['id_character'])
			echo '
						<span class="switch">
							<span data-href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=char_switch;char=', $id_character, ';', $context['session_var'], '=', $context['session_id'], '" class="button">', $txt['switch_chars'], '</a>
						</span>';

		echo '
					</div>
				</li>';
	}
	echo '
			</ul>
		</div>
		<script>
		$("#chars_container .switch span.button").on("click", function() {
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
			<img class="avatar" src="', $context['member']['avatar']['image'], '" alt=""><br /><br />';

	if ($context['user']['is_owner'] && $user_info['id_character'] != $context['character']['id_character'])
	{
		echo '
			<a href="#" class="button">', $txt['switch_to_char'], '</a><br /><br />';
	}
	if ($context['character']['editable'])
	{
		echo '
			<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=edit" class="button">', $txt['edit_char'], '</a><br /><br />';
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
	global $context, $txt, $scripturl;

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
					</div>

	<div id="profileview" class="roundframe flow_auto">
		<div id="basicinfo">';

	echo '
		</div>
		<div id="detailedinfo">
			<form id="creator" action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=edit" method="post" accept-charset="', $context['character_set'], '">
				<dl>
					<dt>', $txt['char_name'], '</dt>
					<dd>
						<input type="text" name="char_name" id="char_name" size="50" value="', $context['character']['character_name'], '" maxlength="50" class="input_text">
					</dd>
					<dt>', $txt['avatar_link'], '</dt>
					<dd>
						<input type="text" name="avatar" id="avatar" size="50" value="', !empty($context['character']['avatar']) ? $context['character']['avatar'] : '', '" maxlength="255" class="input_type">
					</dd>
					<dt>', $txt['avatar_preview'], '</dt>
					<dd id="avatar_preview">
					
					</dd>
					<dt>', $txt['age'], ':</dt>
					<dd>
						<input type="text" name="age" id="age" size="50" value="', !empty($context['character']['age']) ? $context['character']['age'] : '', '" maxlength="50" class="input_text">
					</dd>
				</dl>
				<div class="char_signature"></div>
				<dl></dl>
				<input type="submit" name="edit_char" class="button_submit" value="', $txt['save_changes'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['edit-char' . $context['character']['id_character'] . '_token_var'], '" value="', $context['edit-char' . $context['character']['id_character'] . '_token'], '">
			</form>
		</div>
	</div>
<div class="clear"></div>
				</div>';
}

function template_char_theme() {
	
	echo '
		<div id="admin_content">
					<div class="cat_bar">
						<h3 class="catbg">
						Theme
						</h3>
					</div>

	<div id="profileview" class="roundframe flow_auto">
		<div id="basicinfo">';

	echo '
		</div>
	</div>
<div class="clear"></div>
				</div>';
}

?>