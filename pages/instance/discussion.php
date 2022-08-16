<div class="full_box_with_title final_box">
    <h4><?=$Dictionnary["Discussion"]; ?></h4>
    <div>
	<?php
	$parent_template = db_select_one("
            template.codename
            FROM activity
            LEFT JOIN activity as template ON activity.id_template = template.id
            WHERE activity.id = {$activity->parent_activity}
	    ");
	if ($parent_template == NULL || @strlen($parent_template["codename"]) == 0)
	{
	    $parent_template = db_select_one("
               codename
               FROM activity
               WHERE activity.id = {$activity->parent_activity}
	    ");
	}
	intercom_display("activity", $parent_template["codename"], true);
	?>
    </div>
</div>
