<?php

function break_template_link($activity, $template = false)
{
    global $Database;

    if ($activity->is_template && $template == false)
	return (new ErrorResponse("InvalidParameter"));
    // On fait pareil avec les sous activités
    if (($subs = db_select_all("* FROM activity WHERE parent_activity = $activity->id AND deleted IS NULL")) != NULL)
    {
	foreach ($subs as $sub)
	{
	    ($obj = new FullActivity)->build($sub["id"]);
	    if (($req = break_template_link($obj, $template))->is_error())
		return ($req);
	}
    }
    if (($instance = db_select_one("* FROM activity WHERE id = $activity->id AND deleted IS NULL")) == NULL)
	return (new ErrorResponse("NotAnId", $activity->id, "Activity"));
    if ($instance["template_link"] == false && 0)
	return (new ValueResponse);
    if ($instance["id_template"] == -1)
	return (new ValueResponse);
    if (($template = db_select_one("* FROM activity WHERE id = $activity->id_template")) == NULL)
	return (new ErrorResponse("NotAnId", $activity->id_template, "Activity Template"));

    $values["template_link"] = false;
    foreach ($instance as $field => $val)
    {
	if ($val == NULL && $template[$field] != NULL && strstr($field, "_date") === false)
	    $values[$field] = $template[$field];
    }

    if ($instance["medal_template"] == true)
    {
	$values["medal_template"] = false;
	$medal = db_select_all("* FROM activity_medal WHERE id_activity = $activity->id_template");
	foreach ($medal as $med)
	{
	    unset($med["id"]);
	    $keys = implode(", ", array_keys($med));
	    $med["id_activity"] = $activity->id;
	    foreach ($med as &$x)
	    {
		if ($x == NULL)
		    $x = "NULL";
	    }
	    $val = implode(", ", $med);
	    $Database->query("
                INSERT INTO activity_medal ($keys) VALUES ($val)
	    ");
	}
    }

    if ($instance["support_template"] == true)
    {
	$values["support_template"] = false;
	$class = db_select_all("* FROM activity_support WHERE id_activity = $activity->id_template");
	foreach ($class as $cla)
	{
	    unset($cla["id"]);
	    $keys = implode(", ", array_keys($cla));
	    $cla["id_activity"] = $activity->id;
	    foreach ($cla as &$x)
	    {
		if ($x == NULL)
		    $x = "NULL";
	    }
	    $val = implode(", ", $cla);
	    $Database->query("
                INSERT INTO activity_support ($keys) VALUES ($val)
	    ");
	}
    }

    // On copie tous les fichiers associés
    if (!file_exists("./dres/activity/".$instance["codename"]."/"))
    {
	system("mkdir -p ./dres/activity/".$instance["codename"]);
	system("cp -r ./dres/activity/".$template["codename"]."/* ./dres/activity/".$instance["codename"]."/");
    }

    $ret = update_table("activity", $activity->id, $values);
    add_log(EDITING_OPERATION, "Unlinking activity instance {$activity->id} from template {$activity->id_template}");
    return ($ret);
}

