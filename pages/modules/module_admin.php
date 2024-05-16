<?php
$medal_size = 92 * 0.75;
$edit_medal = true;
$all_users = [];
foreach ($matter->team as $sub)
    $all_users[] = $sub["user"][array_key_first($sub["user"])]["codename"];
$all_users = implode(";", $all_users);
?>
<input
    type="button"
    class="modulebutton"
    onclick="display_panel('module', <?=$matter->id; ?>, false, true);"
    value="<?=$Dictionnary["BackToModule"]; ?>"
    style="cursor: pointer; width: 100%; height: 70px; font-size: large; border: 0; white-space: normal;"
/>
<br />
<br />

<form
    method="put"
    action="/api/module/<?=$matter->id; ?>/registration"
    onsubmit="return silent_submitf(this, {});"
>
    <input
	type="text"
	name="id_user"
	value=""
	placeholder="<?=$Dictionnary["CodeName"]; ?>"
    />
    <input
	type="button"
	onclick="silent_submitf(this, {});"
	style="width: 100px;"
	value="+"
    />
</form>

<form
    <?php
    // Ce formulaire sert à la fois a etre envoyé afin de récupérer
    // la liste des médailles
    // et au bouton permettant de garnir le presse
    // papier de la liste des utilisateurs
    ?>
    id="form_module_<?=$matter->id; ?>_admin"
    method="get"
    action="/api/activity/<?=$matter->id; ?>/admin"
    style="position: absolute; right: 0px;"
>
    <input
	type="hidden"
	id="actbuttonlist<?=$matter->id; ?>"
	value="<?=$all_users; ?>"
    />
    <input
	type="button"
	onclick="navigator.clipboard.writeText(document.getElementById('actbuttonlist<?=$matter->id; ?>').value);"
	value="<?=$Dictionnary["GetUserList"]; ?>"
	stype="position: absolute; right: 0px; padding: 10px 10px 10px 10px;"
    />
</form>
<br /><br />
<table class="table_content" style="min-height: 400px;">
    <tr>
	<th style="height: 20px; width: 30px;">
	<th style="width: 100px;">
	    <?=$Dictionnary["Avatar"]; ?>
	</th>
	<th style="width: 200px;">
	    <?=$Dictionnary["Name"]; ?>
	</th>
	<th style="width: 50px;">
	    <?=$Dictionnary["Bonus"]; ?>
	</th>
	<th style="width: 200px;">
	    <?=$Dictionnary["Commentaries"]; ?>
	</th>
	<th rowspan="<?=count($matter->team) + 1; ?>">
	    <?php $cnt = 0; ?>
	    <?php if (isset($matter->medal_listed) && $matter->medal_listed) { ?>
		<div class="medal_scrollbar" id="medal_scrollbar<?=$matter->id; ?>">
		    <div
			class="medal_scrollbar_cursor"
			id="medal_scrollbar_cursor<?=$matter->id; ?>"
			onmousedown="hscroll('<?=$matter->id; ?>', true);"
		    ></div>
		</div>
		<div class="medal_scroll" id="medal_scroll<?=$matter->id; ?>">
		    <table
			class="table_content"
			id="medal_scroll_table<?=$matter->id; ?>"
			style="height: 100%;"
		    >
			<?php foreach ($matter->team as $sub) { ?>
			    <?php $subuser = $sub["user"][array_key_first($sub["user"])]; ?>
			    <tr class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>">
				<?php foreach ($matter->medal as $medal) { ?>
				    <td style="width: 100px; height: 110px;">
					<?php require ("single_medal.phtml"); ?>
				    </td>
				<?php } ?>
			    </tr>
			<?php } ?>
		    </table>
		</div>
	    <?php } else { ?>
		<?=$Dictionnary["MedalsNotLoadedYet"]; ?>
	    <?php } ?>
	</th>
    </tr>
    <?php $cnt = 0; ?>
    <?php foreach ($matter->team as $sub) { ?>
	<?php $subuser = $sub["user"][array_key_first($sub["user"])]; ?>
	<tr class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>" id="team<?=$sub["id"]; ?>">
	    <td>
		<form method="delete" action="/api/module/<?=$matter->id; ?>/registration">
		    <input
			type="hidden"
			name="id_user"
			value="<?=$subuser["id"]; ?>"
		    />
		    <input
			type="button"
			onclick="window.confirm('<?=$Dictionnary["ConfirmDeletion"]; ?>') && silent_submitf(this, {toremove:'team<?=$sub["id"]; ?>'});"
			style="color: red; width: calc(100% - 10px); height: 100px; font-size: 20px;"
			value="&#10007;"
		    />
		</form>
	    </td>
	    <td style="height: 100px;">
		<?php display_avatar($subuser, 100, true); ?>
	    </td>
	    <td class="module_nickname">
		<?php display_nickname($subuser, true, true); ?>
	    </td>
	    <td class="module_bonus_grade">
		<form method="put" action="/api/team/<?=$sub["id"]; ?>/user/<?=$subuser["id"]; ?>">
		    <span>A</span>
		    <input
			type="input"
			name="bonus_grade_a"
			value="<?=$subuser["bonus_grade_a"]; ?>"
			oninput="delay_before_submit(1000, this);"
		    />
		</form>
		<form method="put" action="/api/team/<?=$sub["id"]; ?>/user/<?=$subuser["id"]; ?>">
		    <span>B</span>
		    <input
			type="input"
			name="bonus_grade_b"
			value="<?=$subuser["bonus_grade_b"]; ?>"
			oninput="delay_before_submit(1000, this);"
		    />
		</form>
		<form method="put" action="/api/team/<?=$sub["id"]; ?>/user/<?=$subuser["id"]; ?>">
		    <span>C</span>
		    <input
			type="input"
			name="bonus_grade_c"
			value="<?=$subuser["bonus_grade_c"]; ?>"
			oninput="delay_before_submit(1000, this);"
		    />
		</form>
		<form method="put" action="/api/team/<?=$sub["id"]; ?>/user/<?=$subuser["id"]; ?>">
		    <span>D</span>
		    <input
			type="input"
			name="bonus_grade_d"
			value="<?=$subuser["bonus_grade_d"]; ?>"
			oninput="delay_before_submit(1000, this);"
		    />
		</form>
		<form method="put" action="/api/team/<?=$sub["id"]; ?>/user/<?=$subuser["id"]; ?>">
		    <span>X</span>
		    <input
			type="input"
			name="bonus_grade_bonus"
			value="<?=$subuser["bonus_grade_bonus"]; ?>"
			oninput="delay_before_submit(1000, this);"
		    />
		</form>
	    </td>
	    <td>
		<form
		    method="put"
		    action="/api/instance/<?=$sub["id"]; ?>/comment/<?=$subuser["id"]; ?>"
		    style="width: 200px; height: 100px;"
		>
		    <textarea
			name="commentaries"
			id="commentaries<?=$sub["id"]; ?>"
			oninput="delay_before_submit(1000, this, 'objectives<?=$sub["id"]; ?>');"
			style="width: 200px; height: 100px; resize: none;"
		    ><?php
		     if (@strlen($subuser["commentaries"]["content"])) {
			 echo $subuser["commentaries"]["content"];
		     }
		     ?></textarea>
		</form>
	    </td>
	</tr>
    <?php } ?>
</table>
<script>
 adapt_scroll_size('<?=$matter->id; ?>');
</script>
