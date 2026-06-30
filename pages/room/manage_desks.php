<h2>
    <?=$Dictionnary["UnknownDesks"]; ?>
</h2><br />
<?php foreach ($nd as $desk) { ?>
    <div id="desk<?=$desk["id"]; ?>" style="background-color: black; border-radius: 15px;">
	<h3 style="text-align: center; width: 100%;">
	    <?=$desk["codename"]; ?>
	</h3>
	<table><tr><td style="width: 50px;">
	    <img src="<?=$Types[$desk["type"]]; ?>" />
	</td><td>
	    <p style="font-size: x-small;">
		<?=$Dictionnary["MacAddress"]; ?>: <?=$desk["mac"]; ?><br />
		<?=$Dictionnary["IpAddress"]; ?>: <?=$desk["ip"]; ?><br />
		<?=$Dictionnary["Status"]; ?>: <?=$Status[$desk["status"]]; ?><br />
		<?php
		$select_system_codename = in_array("codename", db_select_rows("room_desk_user"))
		    ? "room_desk_user.codename"
		    : "NULL";
		$usrs = db_select_all("
                user.id as id,
                COALESCE(user.codename, $select_system_codename) as codename,
                user.nickname,
                user.first_name,
                user.family_name,
                $select_system_codename as system_codename,
                room_desk_user.distant,
                room_desk_user.locked
                FROM room_desk_user
		LEFT JOIN user ON room_desk_user.id_user = user.id
		WHERE id_room_desk = {$desk["id"]}
                  AND TIMESTAMPDIFF(SECOND, room_desk_user.last_update, NOW()) < 60 * 5
		");
		?>
		<?php if (count($usrs)) { ?>
		    <h5><?=$Dictionnary["Users"]; ?></h5>
		    <ul>
			<?php foreach ($usrs as $usr) { ?>
			    <?php if ($usr["id"] != NULL) { ?>
				<?php display_nickname($usr); ?>
			    <?php } else { ?>
				<b><?=htmlentities($usr["codename"]); ?></b>
			    <?php } ?>
			    <?php if ($usr["distant"]) { ?>
				(SSH)
			    <?php } ?>
			    <?php if ($usr["locked"]) { ?>
				&#128274;
			    <?php } ?>
			<?php } ?>
		    </ul>
		<?php } ?>
	    </p>
	</td></tr><tr><td colspan="2">
	    <form
		method="put"
		action="/api/desk/<?=$desk["id"]; ?>"
		onsubmit="return false;"
	    >
		<select name="id_room">
		    <?php foreach ($rooms as $room) { ?>
			<option value="<?=$room["id"]; ?>">
			    <?=$room["codename"]; ?>
			</option>
		    <?php } ?>
		</select>
		<input type="number" name="x" placeholder="X (0-1250)" />
		<input type="number" name="y" placeholder="Y (0-500)" />
		<input
		    type="button"
		    value="<?=$Dictionnary["Assign"]; ?>"
		    onclick="silent_submitf(this.parentNode, {toclear: 'desk<?=$desk["id"]; ?>'});"
		/>
	    </form>
	</td></tr></table>
    </div>
<?php } ?>
