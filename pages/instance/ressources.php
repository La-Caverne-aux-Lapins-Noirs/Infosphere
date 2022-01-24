<h4 style="height: 20px"><?=$Dictionnary["Ressources"]; ?></h4>

<?php if ($activity->subject == "") { ?>

    <div style="position: absolute; top: 40%; text-align: center; width: 100%; font-size: xx-large;">
	<i><?=$Dictionnary["RessourcesNotAvailable"]; ?></i>
    </div>

<?php } else if (!$activity->registered && !$activity->is_teacher) { ?>

    <div style="position: absolute; top: 40%; text-align: center; width: 100%; font-size: xx-large;">
	<i><?=$Dictionnary["YouMustBeRegisteredToSee"]; ?></i>
    </div>

<?php } else if (!$not_too_soon && !$activity->is_teacher) { ?>

    <div style="position: absolute; top: 40%; text-align: center; width: 100%; font-size: xx-large;">
	<i><?=$Dictionnary["RessourcesNotAvailableYet"]; ?></i>
    </div>

<?php } else if (!$not_too_late && !$activity->is_teacher) { ?>

    <div style="position: absolute; top: 40%; text-align: center; width: 100%; font-size: xx-large;">
	<i><?=$Dictionnary["RessourcesNotAvailableAnymore"]; ?></i>
    </div>

<?php } else { ?>

    <?=$list; ?>

<?php } ?>

