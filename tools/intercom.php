<?php

function intercom_get($table, $id_misc, $ref = -1, $id = -1)
{
    global $Database;
    global $User;
    global $Language;
    global $OriginalUser;

    if (($id_misc = resolve_codename($table, $id_misc))->is_error())
	return ($id_misc);
    $id_misc = $id_misc->value;
    if (!is_number($ref))
	return (new ErrorResponse("InvalidParameter", $ref));
    $labs = db_select_all("id_laboratory FROM user_laboratory WHERE id_user = {$User["id"]}", "id_laboratory");
    if ($table == "user")
    {
	if (!($ret = resolve_codename($table, $id_misc))->is_error())
	    $ret = $ret->value;
	else
	    $ret = -1;
    }
    else
	$ret = -1;

    if ($id != -1)
	$id = " AND message.id = $id ";
    else
	$id = " AND message.id_message = $ref GROUP BY message.id";

    if ($ref == -1)
	$order = "DESC";
    else
	$order = "ASC";

    $topic = [];
    $tmp = db_select_all("
      message.*,
      author.id as aid, author.salt as asalt,
      author.nickname as nickname, author.codename as codename,
      author.avatar as avatar, author.photo as photo,
      target.id as tid, target.salt as tsalt,
      message_alert.status as status,
      message_alert.id as id_status
      FROM message
      LEFT OUTER JOIN user as author ON author.id = message.id_user
      LEFT OUTER JOIN user as target ON target.id = message.id_misc
      LEFT OUTER JOIN message_alert ON message_alert.id_message = message.id
                  AND message_alert.id_user = {$User["id"]}
      WHERE position = '$table'
        AND id_misc = '$id_misc'
        $id
      ORDER BY visibility DESC, lastdate $order
    ");

    foreach ($tmp as $t)
    {
	if ($ref == -1)
	{
	    // On est à la racine.
	    if ($t["id_laboratory"] != -1 && !isset($labs[$t["id_laboratory"]]))
		continue ;
	    if ($t["visibility"] == 3 && !is_admin())
		continue ;
	    if ($t["visibility"] == 2 && $t["id_user"] != $OriginalUser["id"] && $ret != $OriginalUser["id"])
		continue ;
	    if ($t["status"] == 0 && isset($t["id_status"]))
	    {
		$Database->query("
                  UPDATE message_alert
                  SET status = 1
                  WHERE id = {$t["id_status"]}
		  ");
	    }
	}
	else
	{
	    // On est dans un sujet. On regarde le sujet racine pour voir ses droits d'accès.
	    $top = intercom_get($table, $id_misc, -1, $t["id_message"]);
	    // Si on l'a pas, c'est qu'on y a pas le droit...
	    if ($top->is_error() || !isset($top->value[0]))
		continue ;
	    $t["visibility"] = $top->value[0]["visibility"];
	    $t["id_laboratory"] = $top->value[0]["id_laboratory"];
	    if ($top->value[0]["status"] != 2)
	    {
		if (isset($top->value[0]["id_status"]))
		    $Database->query("
                      UPDATE message_alert
                      SET status = 2
                      WHERE id = {$top->value[0]["id_status"]}
		      ");
		else
		    $Database->query("
                      INSERT INTO message_alert (id_message, id_user, status)
                      VALUES ({$t["id"]}, {$User["id"]}, 2)
		      ");
	    }
	}
	if ($t["id_laboratory"] != -1)
	    $t["laboratory"] = db_select_one(
		"{$Language}_name as name FROM laboratory WHERE id = {$t["id_laboratory"]}");
	$t["author"] = db_select_one("* FROM user WHERE id = {$t["id_user"]}");

	if ($t["visibility"] > 1 || $t["id_laboratory"] != -1)
	{
	    $cipher = crc32($t["aid"].$t["asalt"]);
	    $cipher .= crc32($t["tid"].$t["tsalt"]);
	}
	else
	    $cipher = "";

	if (@strlen($t["title"]))
	    $t["title"] = get_secured_text($t["title"], $cipher);
	if (@strlen($t["message"]))
	    $t["message"] = get_secured_text($t["message"], $cipher);
	$topic[] = $t;
    }
    return (new ValueResponse($topic));
}

function intercom_add_com($table, $id_misc, $ref, $msg)
{
    global $Database;
    global $User;

    if ($ref == -1)
	return (new ErrorResponse("NotAnId", $ref));
    if (!is_symbol($table))
	return (new ErrorResponse("BadCodeName", $table));
    $ref = (int)$ref;
    $id_misc = (int)$id_misc;

    // Cipher message
    $up = db_select_one("
       message.*,
       author.id as aid, author.salt as asalt,
       target.id as tid, target.salt as tsalt
       FROM message
       LEFT OUTER JOIN user as author ON author.id = message.id_user
       LEFT OUTER JOIN user as target ON target.id = message.id_misc
       WHERE message.id = $ref
    ");
    if ($up["visibility"] > 1 || $up["id_laboratory"] != -1)
    {
	$cipher = crc32($up["aid"].$up["asalt"]);
	$cipher .= crc32($up["tid"].$up["tsalt"]);
    }
    else
	$cipher = "";
    $msg = secure_text($msg, $cipher);
    $Database->query("
	INSERT INTO message
	(position, id_user, id_misc, id_message, message, visibility)
	VALUES
	('$table', {$User["id"]}, $id_misc, $ref, '$msg', {$up["visibility"]})
	");
    $ret = new ValueResponse($Database->insert_id);
    $Database->query("
	UPDATE message SET lastdate = NOW() WHERE id = $ref
    ");

    // On va récupérer ceux dont il est pertinent qu'ils soient mis au courant du message
    // Déjà, on liste ceux qui ont déja posté dans le forum
    $users = [];
    $posters = db_select_all("
       id_user as id
       FROM message
       WHERE position = '$table' AND id_misc = $id_misc
    ");
    foreach ($posters as $v)
    {
	$users[$v["id"]] = " id = ".$v["id"];
    }

    // On prend ceux du laboratoire a qui est destiné le topic
    if ($up["id_laboratory"] != -1)
    {
	$labos = db_select_all("id_user as id FROM user_laboratory WHERE user_laboratory.id_laboratory = {$up["id_laboratory"]}");
	foreach ($labos as $v)
	{
	    $users[$v["id"]] = " id = ".$v["id"];
	}
    }

    if ($table == "user") // C'est la page d'un utilisateur, il est donc concerné...
	$specific = [["id" => $up["id_misc"]]];
    else // C'est une autre page, donc... regardons du coté de user_$nompage
	$specific = db_select_all("id_user as id FROM user_{$table} WHERE id_{$table} = {$up["id_misc"]}");
    foreach ($specific as $v)
    {
	$users[$v["id"]] = " id = ".$v["id"];
    }
    if (count($users) == 0)
	return ($ret);

    // Maintenant on va filtrer ceux qui sont bannis ou sont pas venu depuis très longtemps
    $users = implode($users, " OR ");
    $users = db_select_all("
       id FROM user
       WHERE ( $users )
         AND authority > 0
         AND last_visit > NOW() - 60 * 60 * 24 * 365
    ");
    // Et enfin, on peut ajouter l'alerte nouveau message
    foreach ($users as $usr)
    {
	$status = 0;
	if ($User["id"] == $usr["id"])
	    $status = 2;
	// Dans tous les cas, on met l'alerte sur le TOPIC.
	// Evidemment, si il y a deja un systeme d'alerte, on le met a jour.
	$check = db_select_one("
            * FROM message_alert
            WHERE id_message = {$ret->value}
            AND id_user = {$usr["id"]}
	    ");
	if ($check == NULL)
	    $Database->query("
		INSERT INTO message_alert (id_message, id_user, status)
                VALUES ({$ret->value}, {$usr["id"]}, $status)
		");
	else
	    $Database->query("
                 UPDATE message_alert SET status = $status, postdate = NOW()
                 WHERE id = {$check["id"]}
		 ");
	// Et si c'est une ANNONCE, on le met aussi sur le MESSAGE
	// Pour que celui ci puisse etre traité seul, car il sera affiché sur le DASHBOARD
	$Database->query("
           INSERT INTO message_alert (id_message, id_user, status)
                VALUES ($ref, {$usr["id"]}, $status)
		");
    }

    return ($ret);
}

function intercom_add_topic($table, $id_misc, $title, $msg, $visibility = 0, $labs = -1)
{
    global $Database;
    global $User;

    if (($visibility >= 4 || $visibility < 0))
	return (new ErrorResponse("InvalidParameter", $visibility));
    if (($visibility == 1 || $visibility == 3) && !is_admin())
	return (new ErrorResponse("InvalidParameter", $visibility));
    if ($visibility == 2 && $table != "user")
	return (new ErrorResponse("InvalidParameter", $visibility));
    if ($visibility > 1 || $labs != -1)
    {
	$author = db_select_one("* FROM user WHERE id = {$User["id"]}");;
	$target = db_select_one("* FROM user WHERE id = ".((int)$id_misc)."");
	$cipher = crc32($author["id"].$author["salt"]);
	$cipher .= crc32($target["id"].$target["salt"]);
    }
    else
	$cipher = "";
    $title = secure_text($title, $cipher);
    $Database->query("
	INSERT INTO message
	(position, id_user, id_laboratory, visibility, id_misc, title)
	VALUES
	('$table', {$User["id"]}, $labs, $visibility, $id_misc, '$title')
	");
    return (intercom_add_com($table, $id_misc, $Database->insert_id, $msg));
}

function intercom_handle_request($table, $id_misc)
{
    global $_POST;

    if (!isset($_POST["action"]) || !isset($_POST["ref"]) || !isset($_POST["message"]))
	return (new ErrorResponse("InvalidRequest"));
    if ($_POST["action"] != "add_message" || strlen($_POST["message"]) < 2)
	return (new ErrorResponse("InvalidRequest"));
    if ($_POST["ref"] == -1)
    {
	if (!isset($_POST["title"]) || !isset($_POST["laboratory"]) || !isset($_POST["visibility"]))
	    return (new ErrorResponse("InvalidRequest"));
	if ($_POST["laboratory"] == "")
	    $lab = -1;
	else if (($lab = resolve_codename("laboratory", $_POST["laboratory"]))->is_error())
	    return ($lab);
	else
	    $lab = $lab->value;
	return (intercom_add_topic($table, $id_misc, $_POST["title"], $_POST["message"], $_POST["visibility"], $lab));
    }
    return (intercom_add_com($table, $id_misc, $_POST["ref"], $_POST["message"]));
}

function intercom_display($table, $id_misc, $public = false, $ref = -1, $labs = true)
{
    global $Dictionnary;

    if ($ref == -1)
	$colspan = $public ? 3 : 4;
    else
	$colspan = 1;
    if (($subjects = intercom_get($table, $id_misc, $ref))->is_error())
    {
	echo $Dictionnary["IntercomIsCurrentlyNotAvailable"];
	return ($subjects);
    }
    if ($ref != -1 && ($parent = intercom_get($table, $id_misc, -1, $ref))->is_error())
    {
	echo $Dictionnary["IntercomIsCurrentlyNotAvailable"];
	return ($parent);
    }
    if ($ref != -1 && !isset($parent->value[0]["title"]))
    {
	echo $Dictionnary["IntercomDenied"];
	return (new Response);
    }
?>
    <table style="width: 100%; text-align: center;">
	<tr>
	    <th colspan="<?=$colspan; ?>">
		<?=$Dictionnary["Channel"]; ?> #<?=$id_misc; ?>
		<?php if ($ref != -1) { ?>
		    : <?=$parent->value[0]["title"]; ?>
		    <a href="index.php?<?=unrollget(["ref" => NULL]); ?>">
			&larr;
		    </a>
		<?php } ?>
	    </th>
	</tr>
	<?php if ($ref == -1) { ?>
	    <tr>
		<th><?=$Dictionnary["Title"]; ?></th>
		<th><?=$Dictionnary["Author"]; ?></th>
		<?php if ($public == false) { ?>
		    <th><?=$Dictionnary["Visibility"]; ?></th>
		<?php } ?>
		<th><?=$Dictionnary["LastModified"]; ?></th>
	    </tr>
	<?php } ?>

	<?php if ($ref == -1) { ?>
	    <?php foreach ($subjects->value as $sub) { ?>
		<tr>
		    <td>
			<a
			    href="index.php?<?=unrollget(["ref" => $sub["id"]]); ?>"
			    style="line-height: 20px; text-decoration: none;"
			>
			    <div style="width: 100%; height: 100%; background-color: gray;">
				<?php if ($sub["status"] == 0) { ?>
				    <img src="./res/new_message.png" style="width: 20px; height: 20px; position: relative; top: 5px;" />
				<?php } else if ($sub["status"] == 1) { ?>
				    <img src="./res/unread_message.png" style="width: 20px; height: 20px; position: relative; top: 5px;" />
				<?php } else { ?>
				    <img src="./res/message.png" style="width: 20px; height: 20px; position: relative; top: 5px;" />
				<?php } ?>
				<?=$sub["title"]; ?>
			    </div>
			</a>
		    </td>
		    <td>
			<a href="index.php?p=ProfileMenu&amp;a=<?=$sub["author"]["id"]; ?>">
			    <?=$sub["author"]["codename"]; ?>
			</a>
		    </td>
		    <?php if ($public == false) { ?>
			<td>
			    <?php $vis = ["Public", "Announcement", "PrivateMessage", "Administrative"]; ?>

			    <?php if ($sub["id_laboratory"] != -1) { ?>
				<?=$sub["laboratory"]["name"]; ?>
			    <?php } else { ?>
				<?=$Dictionnary[$vis[$sub["visibility"]]]; ?>
			    <?php } ?>
			</td>
		    <?php } ?>
		    <td><?=$sub["lastdate"]; ?></td>
		</tr>
	    <?php } ?>
	    <tr>
		<td>&nbsp;</td><td></td><td></td>
		<?php if ($public == false) { ?>
		    <td></td>
		<?php } ?>
	    </tr>
	<?php } else { // INTERIEUR DU FORUM ?>
	    <?php foreach ($subjects->value as $sub) { ?>
		<tr>
		    <td style="text-align: left; border: 1px solid black; position: relative;">
			<div style="width: 120px; display: inline-block; text-align: left;">
			    <?=strlen($sub["nickname"]) ? $sub["nickname"] : $sub["codename"]; ?>
			    <?php display_avatar($sub, 100); ?>
			</div>
			<div style="display: inline-block; position: absolute; top: 0px;">
			    <?=datex("d/m/Y H:i", date_to_timestamp($sub["lastdate"])); ?>
			    <br />
			    <?=$sub["message"]; ?>
			</div>
		    </td>
		</tr>
	    <?php } ?>
	<?php } ?>


	<tr>
	    <td colspan="<?=$colspan; ?>" style="position: relative;">
		<hr />
		<p style="text-align: center;">
		    <?=$Dictionnary["AddAnEntry"]; ?>
		</p>
		<form method="post" action="index.php?<?=unrollget(["ref" => $ref]); ?>">
		    <input type="hidden" name="action" value="add_message" />
		    <input type="hidden" name="ref" value="<?=$ref; ?>" />
		    <div style="width: 90%; position: absolute; left: 0px;">
			<?php if ($ref == -1) { ?>
			    <input type="text"
				   name="title"
				   placeholder="<?=$Dictionnary["Title"]; ?>"
				   style="width: 33%;"
			    />
			    <?php if ($labs != false) { ?>
				<input
				    type="text"
				    name="laboratory"
				    placeholder="<?=$Dictionnary["Laboratory"]; ?>"
				    style="width: 33%;"
				/>
			    <?php } else { ?>
				<input
				    type="hidden"
				    name="laboratory"
				    value=""
				/>
			    <?php } ?>
			    <?php if ($public == false) { ?>
				<select
				    name="visibility"
					  style="width: 32%;"
				>
				    <option value="0"><?=$Dictionnary["Public"]; ?></option>
				    <option value="2"><?=$Dictionnary["PrivateMessage"]; ?></option>
				    <?php if (is_admin()) { ?>
					<option value="1"><?=$Dictionnary["Annoucement"]; ?></option>
					<option value="3"><?=$Dictionnary["Administrative"]; ?></option>
				    <?php } ?>
				</select>
			    <?php } else { ?>
				<input type="hidden" name="visibility" value="0" />
			    <?php } ?>
			<?php } ?>
			<br />
			<textarea
			    name="message"
			    style="width: 99%; height: 50px;"
			></textarea>
		    </div>
		    <?php
		    $height = ($ref == -1) ? 70 : 50;
		    ?>
		    <div style="width: 10%; position: absolute; right: 0px; height: <?=$height; ?>px;">
			<input type="submit" value="+" style="width: 100%; height: 100%;" />
		    </div>
		</form>
	    </td>
	</tr>
    </table>
<?php
}
