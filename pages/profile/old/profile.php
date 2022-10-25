
<div style="width: 33%; float: left">
    <?php display_avatar($user, 200); ?>
</div>

<br />
<?php if ($user["nickname"] != "") { ?>
    <h3><?=$user["nickname"];?></h3>
    <h4>(<?=$user["codename"];?>)</h4>
<?php } else { ?>
    <h3><?=$user["codename"];?></h3>
<?php } ?>
<p>
    <b><?=$Dictionnary["Name"]; ?></b>: <?=$user["family_name"]; ?>
    <?php if (strlen($user["first_name"])) { ?>
	<b><?=$Dictionnary["FirstName"]; ?></b>: <?=$user["first_name"]; ?>
    <?php } ?><br />
    <b><?=$Dictionnary["Mail"]; ?></b>: <?=$user["mail"]; ?><br />
    <b><?=$Dictionnary["BirthDate"]; ?></b>: <?=$user["birth_date"] ? human_date($user["birth_date"], true) : ""; ?><br />
    <b><?=$Dictionnary["Status"]; ?></b>: <?=$Dictionnary[$user["authority"]]; ?><br>
    <b><?=$Dictionnary["RegisterDate"]; ?></b>: <?=human_date($user["registration_date"]); ?><br />
    <b><?=$Dictionnary["Money"]; ?></b>: <?=$user["money"]; ?><br />
    <?php if ($user["id"] == $User["id"] || is_admin()) { ?>
	<b><?=$Dictionnary["Credit"]; ?></b>: <?=$user["credit"]; ?><br />
    <?php } ?>
    <?php if ($User["id"] == $user["id"] || is_admin()) { ?>
	<a href="index.php?<?=unrollget(); ?>&amp;edit_profile=1"><?=$Dictionnary["EditProfile"]; ?></a>
    <?php } ?>
</p>

<?php /*
<p style="width: 32%; float: right; height: 100%; position: absolute; top: 0px; right: 0px; overflow-y: auto; font-style: italic;">
    <?php if (is_admin()) { ?>
	<form method="post">
	    <input type="hidden" name="action" value="refresh_profile" />
	    <input type="submit" value="<?=$Dictionnary["RefreshProfile"]; ?>" />
	</form>
	<br />
	<?=$user["admin_note"]; ?>
    <?php } ?>
</p>
*/ ?>
