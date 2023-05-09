<?php $medal = fetch_medal($_GET["a"]); ?>
<div style="position: absolute; top: 10px; left: 10px; width: 420px;">
    <img src="<?=$medal["icon"]; ?>" alt="" />
    <br />
    <br />
    <h1 style="width: 100%;"><?=$medal["name"]; ?></h1>
    <h5 style="width: 100%;">(<?=$medal["codename"]; ?>)</h5>
    <p style="width: 400px;">
	<?=$medal["description"]; ?>
    </p>
</div>
<div style="position: absolute; top: 10px; left: 420px; height: calc(100% - 20px); width: calc((100% - 430px) / 2 - 10px); overflow: auto;" class="content_table">
    <h2><?=$Dictionnary["Activities"]; ?></h2>
    <?php 
    $activities = db_select_all("
    activity.{$Language}_name as name, activity.* FROM activity_medal
    LEFT JOIN activity ON activity.id = activity_medal.id_activity
    WHERE activity_medal.id_medal = ".$medal["id"]."
    ");
    ?>
    <table>
	<tr>
	    <th><?=$Dictionnary["Name"]; ?></th>
	    <th><?=$Dictionnary["CodeName"]; ?></th>
	    <th><?=$Dictionnary["Date"]; ?></th>
	</tr>
	<?php $i = 0; ?>
	<?php foreach ($activities as $act) { ?>
	    <tr style="<?=$i++ % 2 ? "background-color: lightgray;": ""; ?>"><td>
		<?=$act["name"]; ?>
	    </td><td>
		<?=$act["codename"]; ?>
	    </td><td>
		<?php if (!$act["is_template"] && $act["parent_activity"] != NULL && $act["parent_activity"] != -1) { ?>
		    <?=datex("d/m/Y", $act["subject_appeir_date"]); ?>
		<?php } else { ?>
		    /
		<?php } ?>
	    </td></tr>
	<?php } ?>
    </table>
</div>
<div style="position: absolute; top: 10px; left: calc(420px + (100% - 430px) / 2 + 10px); height: calc(100% - 20px); width: calc((100% - 430px) / 2 - 10px); overflow: auto;" class="content_table">
    <h2><?=$Dictionnary["Users"]; ?></h2>
    <?php
    $user = db_select_all("
    user.codename, user_medal.id as id_user_medal FROM user_medal
    LEFT JOIN user ON user.id = user_medal.id_user
    WHERE user_medal.id_medal = {$medal["id"]}
    ");
    ?>
    <table>
	<tr>
	    <th><?=$Dictionnary["Name"]; ?></th>
	    <th><?=$Dictionnary["Activity"]; ?></th>
	</tr>
	<?php $i = 0; ?>
	<?php foreach ($user as $u) { ?>
	    <tr style="<?=$i++ % 2 ? "background-color: lightgray;": ""; ?>"><td>
		<?=$u["codename"]; ?>
	    </td><td>
		<?php
		$aum = db_select_all("
                   activity.codename, activity.{$Language}_name as name, activity_user_medal.* FROM activity_user_medal
                   LEFT JOIN activity ON activity.id = activity_user_medal.id_activity
                   WHERE id_user_medal = {$u["id_user_medal"]}
		   ");
		foreach ($aum as $a) { ?>
		    <?=$a["name"]; ?> (<?=$a["codename"]; ?>)<br />
		<?php } ?>
	    </td></tr>
	<?php } ?>
    </table>
</div>
