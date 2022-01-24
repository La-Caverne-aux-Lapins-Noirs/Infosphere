<?php

$Full = ["activity", "instance", "session"];
$Instance = ["activity", "instance"];
$Activity = ["activity"];

function	have_rights($id_activity, $teacher = true) // teacher (true) or assistant (false)
{
    global	$User;
    global	$Full;

    if (!isset($User))
	return (false);
    if (is_admin($User))
	return (true);

    if (($id_activity = resolve_codename("activity", $id_activity))->is_error())
	return (false);
    $id_activity = $id_activity->value;
    if (($all = db_select_all("
          activity_teacher.id_user as id_user, activity_teacher.id_laboratory as id_laboratory
          FROM activity_teacher WHERE id_activity = $id_activity
    ")) == NULL)
    {
	return (false);
    }
    foreach ($all as $aut)
    {
	if ($aut["id_user"] == $User["id"])
	    return (true);
	foreach ($User["laboratories"] as $lab)
	{
	    if ($lab["id"] == $aut["id_laboratory"])
	    {
		if ($teacher == false)
		{
		    if ($lab["authority"] >= 1)
			return (true);
		}
		else
		{
		    if ($lab["authority"] >= 2)
			return (true);
		}
	    }
	}
    }

    $parent = db_select_one("parent_activity as id FROM activity WHERE id = $id_activity");
    if ($parent != NULL && $parent["id"] != -1)
	return (have_rights($parent["id"], $teacher));
    return (false);
}

