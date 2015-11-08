<?php

function template_characters_popup() {
	global $context, $scripturl, $user_info, $cur_profile;
	echo '
		<div id="posting_as">You are posting as: ', $user_info['character_name'], '
		<div id="my_characters">My Characters</div>
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
							<span data-href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=char_switch;char=', $id_character, ';', $context['session_var'], '=', $context['session_id'], '" class="button">switch</a>
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
	global $context, $user_profile, $scripturl;

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
			<img class="avatar" src="', $context['character']['avatar'], '" alt="">';
	else
		echo '
			<img class="avatar" src="', $context['member']['avatar']['image'], '" alt="">';

	$days_registered = (int) ((time() - $user_profile[$context['id_member']]['date_registered']) / (3600 * 24));
	$posts_per_day = $days_registered > 1 ? comma_format($context['character']['posts'] / $days_registered, 2) : '';
	echo '
		</div>
		<div id="detailedinfo">
			<dl>
				<dt>Character Name: </dt>
				<dd>', $context['character']['character_name'], $context['character']['editable'] ? '<span class="generic_icons calendar_modify"></span>' : '', '</dd>
				<dt>Posts: </dt>
				<dd>', comma_format($context['character']['posts']), $days_registered > 1 ? ' (' . $posts_per_day . ' per day)' : '', '</dd>
				<dt>Age:</dt>
				<dd>', !empty($context['character']['age']) ? $context['character']['age'] : 'N/A', '</dd>
			</dl>';

	if (!empty($context['character']['signature'])) {
		echo '
			<div class="char_signature">', parse_bbc($context['character']['signature'], true, 'sig_char' . $context['character']['id_character']), '</div>
			<dl></dl>';
	}

	echo '
			<dl class="noborder">
				<dt>Date Created: </dt>
				<dd>', timeformat($context['character']['date_created']), '</dd>
				<dt>Last Active: </dt>
				<dd>', timeformat($context['character']['last_active']), '</dd>';

	if ($context['character']['editable'])
		echo '
				<dt>Current Theme:</dt>
				<dd>', $context['character']['theme_name'], ' <a class="button" href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=characters;char=', $context['character']['id_character'], ';sa=theme">change</a></dd>';

	echo '
			</dl>
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