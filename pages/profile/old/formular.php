<form method="post" action="index.php?<?=unrollget(); ?>" enctype="multipart/form-data" class="profile_edit">

    <div style="width: 33%; float: left; height: 410px;">
	<div style="width: 33%; height: 100px;">
	    <?=$user["avatar"] != "" ? "<img style='width: 64px; height: 64px;' src='".$user['avatar']."' />" : "<img style='width: 64px; height: 64px;' src='res/no_avatar.png'>" ?>
	    <br />
	    <label for="avatar"><?=$Dictionnary["Avatar"];?> : </label>
	    <input type="file" accept="image/png" name="avatar"/>
	</div>
	<br />

	<?php if (is_admin()) { ?>
	    <div style="width: 33%; height: 100px;">
		<?=$user["photo"] != "" ? "<img style='width: 64px; height: 64px;' src='".$user['photo']."' />" : "<img style='width: 64px; height: 64px;' src='res/no_avatar.png'>" ?>
		<br />
		<label for="photo"><?=$Dictionnary["Photo"];?> : </label>
		<input type="file" accept="image/png" name="photo"/>
	    </div>
	    <br />
	<?php } ?>
    </div>

    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?=$user["id"]; ?>">

    <label for="nickname"><?=$Dictionnary["Nickname"];?> : </label>
    <input type="text" value="<?=$user["nickname"]; ?>" name="nickname"/>
    <br />

    <label for="mail"><?=$Dictionnary["Mail"];?> : </label>
    <input type="text" value="<?=$user["mail"]; ?>" name="mail"/>
    <br />

    <label for="first_name"><?=$Dictionnary["FirstName"];?> : </label>
    <input type="text" value="<?=$user['first_name']; ?>" name="first_name" />
    <br />

    <label for="family_name"><?=$Dictionnary["Name"];?> : </label>
    <input type="text" value="<?=$user['family_name']; ?>" name="family_name"/>
    <br />

    <label for="birth_date"><?=$Dictionnary["BirthDate"];?> : </label>
    <input type="date" value="<?=date("d/m/Y", date_to_timestamp($user['birth_date'])); ?>" name="birth_date"/>
    <br />

    <label for="address_name"><?=$Dictionnary["AddressName"];?> : </label>
    <input type="text" value="<?=$user['address_name']; ?>" name="address_name" />
    <br />

    <label for="street_name"><?=$Dictionnary["Street"];?> : </label>
    <input type="text" value="<?=$user['street_name']; ?>" name="street_name" />
    <br />

    <label for="postal_code"><?=$Dictionnary["PostalCode"];?> : </label>
    <input type="text" value="<?=$user['postal_code']; ?>" name="postal_code" />
    <br />

    <label for="city"><?=$Dictionnary["City"];?> : </label>
    <input type="text" value="<?=$user['city']; ?>" name="city"/>
    <br />

    <label for="country"><?=$Dictionnary["Country"];?> : </label>
    <input type="text" value="<?=$user['country']; ?>" name="country" />
    <br />

    <?php foreach ($conf_fields as $i => $cnf) { ?>
	<?php if (!isset($user['configuration'][$cnf["label"]])) { ?>
	    <?php $user['configuration'][$cnf["label"]] = ""; ?>
	<?php } ?>
	<label
	    for="<?=$cnf["label"]; ?>"
	><?=$Dictionnary[$i];?> :</label>
	<?php if ($cnf["type"] == "checkbox") { ?>
	    <input
		type="checkbox"
		<?=@$user['misc_configuration']['profile'][$cnf["label"]] ? "checked" : ""; ?>
		name="<?=$cnf["label"]; ?>"
	    />
	<?php } else { ?>
	    <input
		type="text"
		value="<?=@$user['misc_configuration']['profile'][$cnf]; ?>"
		name="<?=$cnf["label"]; ?>"
	    />
	<?php } ?>
	<br />
    <?php } ?>

    <?php if (is_admin()) { ?>
	<label for="admin_note"><?=$Dictionnary["AdministrativeNote"];?> : </label>
	<input type="text" value="<?=$user['admin_note']; ?>" name="admin_note" />
	<br />
    <?php } ?>

    <a href="index.php?<?=unrollget(); ?>&amp;edit_profile=0"><?=$Dictionnary["UneditProfile"]; ?></a>
    <br />
    <br />
    <label for="visiblity"><?=$Dictionnary["ProfileVisibility"]; ?></label>
    <select name="visibility">
	<option value="0" <?=$user["visibility"] == 0 ? "selected" : ""; ?>><?=$Dictionnary["HideEverything"]; ?></option>
	<option value="1" <?=$user["visibility"] == 1 ? "selected" : ""; ?>><?=$Dictionnary["ShowProfileOnly"]; ?></option>
	<option value="2" <?=$user["visibility"] == 2 ? "selected" : ""; ?>><?=$Dictionnary["ShowProfileAndSuccessfulActivities"]; ?></option>
	<option value="3" <?=$user["visibility"] == 3 ? "selected" : ""; ?>><?=$Dictionnary["ShowEverything"]; ?></option>
    </select>
    <input type="submit" value="<?=$Dictionnary["Update"];?>">
</form>
<br />
<br />
<!--form method="post">
     <label for="old_pass"><?=$Dictionnary["OldPass"];?> : </label><input type="password" name="old_pass" required/><br>
     <label for="new_pass"><?=$Dictionnary["NewPass"];?> : </label><input type="password" name="new_pass" required/><br>
     <label for="conf_pass"><?=$Dictionnary["ConfPass"];?> : </label><input type="password" name="repeat_pass" required/>
     <br><br>
     <input type="hidden" name="action" value="pass_update">
     <input type="hidden" name="id" value="<?=$user["id"]; ?>">
     <input type="submit" value="<?=$Dictionnary["Update"];?>">
     </form-->
<p>
</p>
