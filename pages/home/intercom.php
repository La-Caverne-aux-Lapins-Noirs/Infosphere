<h2><?=$Dictionnary["AnnouncementAndMessages"]; ?></h2>
<?php return ; ?>
<?php foreach (db_select_all("
               message.*, user.id as uid, user.nickname, user.codename as ucodename,
               user.salt as usalt
               FROM message_alert
               LEFT JOIN message ON message_alert.id_message = message.id
               LEFT JOIN user ON message.id_user = user.id
               WHERE message_alert.id_user = {$User["id"]}
                 AND message_alert.status < 2
                 AND ( ( message.visibility = 1 AND message.id_message != -1 )
                       OR
                       ( message.visibility = 2 AND message.id_message = -1 )
                     )
               ORDER BY message_alert.postdate DESC
	       ") as $entry) { ?>
    <div style="width: 100%;">
	<?php if ($entry["visibility"] == 2) { ?>
	    <?php
	    $ref = [
		"user" => "ProfileMenu"
	    ];
	    if (!isset($ref[$entry["position"]]))
		continue ;
	    if ($entry["visibility"] == 2)
	    {
		// On ne regarde pas les messages privés des autres...
		if ($OriginalUser["id"] != $User["id"] && 0)
		    continue ;
		$cipher = crc32($entry["uid"].$entry["usalt"]);
		$cipher .= crc32($User["id"].$User["salt"]);
	    }
	    else
		$cipher = "";
	    ?>
	    <a href="index.php?p=<?=$ref[$entry["position"]]; ?>&amp;ref=<?=$entry["id"]; ?>">
		<?=get_secured_text($entry["title"], $cipher); ?>,
	    </a>
	    <?=$Dictionnary["By"]; ?>
	    <a href="index.php?p=ProfileMenu&amp;a=<?=$entry["uid"]; ?>">
		<?=@strlen($entry["nickname"]) ?
		   $entry["nickname"] :
		   $entry["ucodename"];
		?>
	    </a>,
	    <?=$entry["postdate"]; ?>
	<?php } else { ?>
	    <?php
	    $topic = db_select_one("
                           * FROM message WHERE id = {$entry["id_message"]}
			   ");
	    ?>
	    <h3><?=$topic["title"]; ?></h3>
	    <p>
		<?=$Dictionnary["By"]; ?>
		<a href="index.php?p=ProfileMenu&amp;a=<?=$entry["uid"]; ?>">
		    <?=@strlen($entry["nickname"]) ?
		       $entry["nickname"] :
		       $entry["ucodename"];
		    ?></a>,
		<?=$entry["postdate"]; ?>
		<br />
		<br />
		<?=$entry["message"]; ?>
	    </p>
	<?php } ?>
	<hr />
    </div>
<?php } ?>
