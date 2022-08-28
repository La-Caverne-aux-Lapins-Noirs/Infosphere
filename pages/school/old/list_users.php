<a href="<?=unrollurl(["a" => -1]); ?>">
    &larr;
</a>

<table id="home_table" class="school_table">
    <tr>
	<td><div>
	    <h3><?=$school["name"]; ?></h3>
	    <h4><?=$school["codename"]; ?></h4>
	    <div style="float: left; margin-left: 20px;">
		<?php print_icon("SchoolsDir", $school["codename"]); ?>
	    </div>
	    <div style="float: left; margin-left: 20px;">
		XXX<br />
		YYY<br />
		ZZZ<br />
	    </div>
	</div></td>
	<td><div>
		<h4><?=$Dictionnary["Directors"]; ?></h4>
		<ul>
		    <?php foreach ($school["directors"] as $dir) { ?>
			<li style="float: left; width: 33%; text-align: center;">
			    <?=display_avatar($dir, 50); ?>
			    <?=display_nickname($dir, true); ?>
			</li>
		    <?php } ?>
		</ul>
	</div></td>
    </tr>
    <tr>
	<td><div>
	    <h4><?=$Dictionnary["Users"]; ?></h4>
	    <ul>
		<?php foreach ($school["users"] as $user) { ?>
		    <li style="float: left; width: 33%; text-align: center;">
			<?=display_avatar($user, 50); ?>
			<?=display_nickname($user, true); ?>
		    </li>
		<?php } ?>
	    </ul>
	</div></td>
	<td><div>
	    <h4><?=$Dictionnary["Laboratories"]; ?></h4>
	    <ul>
		<?php foreach ($school["laboratories"] as $lab) { ?>
		    <li style="float: left; width: 33%; text-align: center;">
			<a href="index.php?p=LabMenu&amp;a=<?=$lab["id"]; ?>">
			    <?=$lab["codename"]; ?> - <?=$lab["name"]; ?>
			</a>
		    </li>
		<?php } ?>
	    </ul>
	</div></td>
    </tr>
    <tr>
	<td><div>
	    <h4><?=$Dictionnary["Rooms"]; ?></h4>
	    <ul>
		<?php foreach ($school["rooms"] as $room) { ?>
		    <li style="float: left; width: 33%; text-align: center;">
			<a href="index.php?p=AdminRoomsMenu&amp;a=<?=$room["id"]; ?>">
			    <?=$room["codename"]; ?> - <?=$room["name"]; ?>
			</a>
		    </li>
		<?php } ?>
	    </ul>
	</div></td>
	<td><div>
	    <h4><?=$Dictionnary["Curriculum"]; ?></h4>
	    <ul>
		<?php foreach ($school["cycles"] as $cycle) { ?>
		    <li style="float: left; width: 33%; text-align: center;">
			<a href="index.php?p=CycleMenu&amp;a=<?=$cycle["id"]; ?>">
			    <?=$cycle["codename"]; ?> - <?=$cycle["name"]; ?>
			</a>
		    </li>
		<?php } ?>
	    </ul>
	</div></td>
    </tr>
</table>
