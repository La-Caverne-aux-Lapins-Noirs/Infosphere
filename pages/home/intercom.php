<h2><?=$Dictionnary["AnnouncementAndMessages"]; ?></h2>
<?php
$private_messages = db_select_all("\n    message.*,\n    author.id as uid, author.nickname, author.codename as ucodename,\n    reader.view_date,\n    COALESCE((\n        SELECT MAX(child.post_date)\n        FROM message as child\n        WHERE child.id_message = message.id\n    ), message.post_date) as last_post_date,\n    COALESCE((\n        SELECT child.id_user\n        FROM message as child\n        WHERE child.id_message = message.id\n        ORDER BY child.post_date DESC\n        LIMIT 1\n    ), message.id_user) as last_user\n    FROM message\n    LEFT JOIN user as author ON author.id = message.id_user\n    LEFT JOIN (\n        SELECT id_message, MAX(view_date) as view_date\n        FROM message_user\n        WHERE id_user = {$User["id"]}\n        GROUP BY id_message\n    ) as reader ON reader.id_message = message.id\n    WHERE message.id_message IS NULL\n      AND message.misc_type = 'user'\n      AND message.visibility = ".INTERCOM_PRIVATE."\n      AND (message.id_user = {$User["id"]} OR message.id_misc = {$User["id"]})\n    HAVING reader.view_date IS NULL OR reader.view_date < last_post_date\n    ORDER BY last_post_date DESC\n    LIMIT 10\n");
?>
<?php if (count($private_messages) == 0) { ?>
    <p style="text-align: center; font-style: italic; font-size: small;">
	<?=$Dictionnary["NoMessages"]; ?>
    </p>
<?php } ?>
<?php foreach ($private_messages as $entry) { ?>
    <?php
    $other_user = $entry["id_user"] == $User["id"] ? $entry["id_misc"] : $entry["id_user"];
    $name = @strlen($entry["nickname"]) ? $entry["nickname"] : $entry["ucodename"];
    ?>
    <div style="width: 100%; margin-bottom: 8px;">
	<a href="index.php?p=ProfileMenu&amp;a=<?=$other_user; ?>&amp;ref=<?=$entry["id"]; ?>">
	    <?=htmlentities($entry["title"]); ?>
	</a>
	<br />
	<span style="font-size: small;">
	    <?=$Dictionnary["By"]; ?>
	    <a href="index.php?p=ProfileMenu&amp;a=<?=$entry["uid"]; ?>">
		<?=$name; ?>
	    </a>,
	    <?=human_date($entry["last_post_date"]); ?>
	</span>
	<hr />
    </div>
<?php } ?>
