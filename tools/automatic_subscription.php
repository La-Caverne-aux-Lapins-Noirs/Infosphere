<?php

function automatic_subscription_resolve_ids($table, $ids)
{
    if (($ret = resolve_codename($table, $ids))->is_error())
	return ($ret);
    if ($ret->value === NULL || $ret->value === [])
	return (new ErrorResponse("NotFound", $ids));

    $ids = is_array($ret->value) ? $ret->value : [$ret->value];
    foreach ($ids as &$id)
	$id = (int)$id;
    return (new ValueResponse($ids));
}

function automatic_subscription_resolve_id($table, $id)
{
    if (($ids = automatic_subscription_resolve_ids($table, $id))->is_error())
	return ($ids);
    if (count($ids->value) != 1)
	return (new ErrorResponse("InvalidParameter", $id));
    return (new ValueResponse((int)$ids->value[0]));
}


function automatic_subscription_effective_sql($activity_alias, $template_alias = "template", $replacement_alias = NULL)
{
    $activity_alias = is_symbol($activity_alias) ? $activity_alias : "activity";
    $template_alias = is_symbol($template_alias) ? $template_alias : "template";
    $activity_subscription = "$activity_alias.subscription = 2";
    $template_subscription = "($activity_alias.subscription IS NULL AND $template_alias.subscription = 2)";
    $effective = "($activity_subscription OR $template_subscription)";

    if ($replacement_alias !== NULL && is_symbol($replacement_alias))
        return ("($replacement_alias.replacement_subscription = 2 OR ($replacement_alias.replacement_subscription IS NULL AND $effective))");
    return ($effective);
}

function automatic_subscription_user_has_activity($id_user, $id_activity)
{
    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;
    return (db_select_one("
        team.id
        FROM team
        LEFT JOIN user_team ON user_team.id_team = team.id
        WHERE user_team.id_user = $id_user
        AND team.id_activity = $id_activity
    ") != NULL);
}

function automatic_subscription_subscribe_user_to_activity($id_user, $id_activity, $context = "")
{
    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;

    if ($id_user <= 0 || $id_activity <= 0)
	return (new ErrorResponse("InvalidParameter"));

    if (automatic_subscription_user_has_activity($id_user, $id_activity))
	return (new ValueResponse(0));

    $ret = subscribe_to_instance($id_activity, $id_user, -1, true, true);
    if ($ret->is_error())
    {
	$msg = "Automatic subscription failed: user #$id_user to activity #$id_activity";
	if ($context != "")
	    $msg .= " ($context)";
	$msg .= ": ".strval($ret);
	add_log(WARNING, $msg, 1);
	return ($ret);
    }
    add_log(TRACE, "Automatic subscription: user #$id_user to activity #$id_activity".($context != "" ? " ($context)" : ""), 1);
    return (new ValueResponse(1));
}

function automatic_subscription_subscribe_user_to_activity_children($id_user, $id_activity, $limit = 0)
{
    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;
    $limit = (int)$limit;
    $lim = $limit > 0 ? " LIMIT $limit" : "";
    $done = 0;
    $errors = [];

    if ($id_user <= 0 || $id_activity <= 0)
	return (new ErrorResponse("InvalidParameter"));

    $children = db_select_all("
        child.id as id_activity
        FROM activity as child
        LEFT JOIN activity as template ON child.id_template = template.id
        WHERE child.parent_activity = $id_activity
        AND ( child.is_template = 0 OR child.is_template IS NULL )
        AND child.deleted IS NULL
        AND child.disabled IS NULL
        AND ".automatic_subscription_effective_sql("child", "template")."
        AND ( child.registration_date IS NULL OR child.registration_date <= NOW() )
        AND ( child.close_date IS NULL OR child.close_date > NOW() )
        AND ( child.done_date IS NULL OR child.done_date > NOW() )
        ORDER BY child.id
        $lim
    ");

    foreach ($children as $child)
    {
	$ret = automatic_subscription_subscribe_user_to_activity(
	    $id_user,
	    $child["id_activity"],
	    "child of activity #$id_activity"
	);
	if ($ret->is_error())
	    $errors[] = strval($ret);
	else
	    $done += (int)$ret->value;
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_subscribe_user_to_cycle_matter($id_user, $id_cycle, $id_activity)
{
    $id_user = (int)$id_user;
    $id_cycle = (int)$id_cycle;
    $id_activity = (int)$id_activity;

    if ($id_user <= 0 || $id_cycle <= 0 || $id_activity <= 0)
	return (new ErrorResponse("InvalidParameter"));

    $matter = db_select_one("
        activity.id
        FROM activity_cycle
        LEFT JOIN activity ON activity.id = activity_cycle.id_activity
        LEFT JOIN activity as template ON activity.id_template = template.id
        WHERE activity_cycle.id_cycle = $id_cycle
        AND activity_cycle.id_activity = $id_activity
        AND ( activity.parent_activity IS NULL OR activity.parent_activity = -1 )
        AND ( activity.is_template = 0 OR activity.is_template IS NULL )
        AND activity.deleted IS NULL
        AND activity.disabled IS NULL
        AND ( activity.registration_date IS NULL OR activity.registration_date <= NOW() )
        AND ( activity.close_date IS NULL OR activity.close_date > NOW() )
        AND ( activity.done_date IS NULL OR activity.done_date > NOW() )
        AND ".automatic_subscription_effective_sql("activity", "template", "activity_cycle")."
    ");
    if ($matter == NULL)
	return (new ValueResponse(0));

    return (automatic_subscription_subscribe_user_to_activity(
	$id_user,
	$id_activity,
	"matter of cycle #$id_cycle"
    ));
}

function automatic_subscription_subscribe_one_user_to_cycle($id_user, $id_cycle, $limit = 0)
{
    $id_user = (int)$id_user;
    $id_cycle = (int)$id_cycle;
    $limit = (int)$limit;
    $lim = $limit > 0 ? " LIMIT $limit" : "";
    $done = 0;
    $errors = [];

    $matters = db_select_all("
        activity.id as id_activity
        FROM activity_cycle
        LEFT JOIN activity ON activity.id = activity_cycle.id_activity
        LEFT JOIN activity as template ON activity.id_template = template.id
        WHERE activity_cycle.id_cycle = $id_cycle
        AND ( activity.parent_activity IS NULL OR activity.parent_activity = -1 )
        AND ( activity.is_template = 0 OR activity.is_template IS NULL )
        AND activity.deleted IS NULL
        AND activity.disabled IS NULL
        AND ( activity.registration_date IS NULL OR activity.registration_date <= NOW() )
        AND ( activity.close_date IS NULL OR activity.close_date > NOW() )
        AND ( activity.done_date IS NULL OR activity.done_date > NOW() )
        AND ".automatic_subscription_effective_sql("activity", "template", "activity_cycle")."
        ORDER BY activity.id
        $lim
    ");

    foreach ($matters as $matter)
    {
	$ret = automatic_subscription_subscribe_user_to_activity(
	    $id_user,
	    $matter["id_activity"],
	    "matter of cycle #$id_cycle"
	);
	if ($ret->is_error())
	    $errors[] = strval($ret);
	else
	    $done += (int)$ret->value;
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_subscribe_user_to_cycle($id_user, $id_cycle, $limit = 0)
{
    if (($users = automatic_subscription_resolve_ids("user", $id_user))->is_error())
	return ($users);
    if (($cycles = automatic_subscription_resolve_ids("cycle", $id_cycle))->is_error())
	return ($cycles);

    $done = 0;
    $errors = [];
    foreach ($cycles->value as $cycle)
    {
	if ($cycle <= 0)
	    continue ;
	foreach ($users->value as $user)
	{
	    if ($user <= 0)
		continue ;
	    $ret = automatic_subscription_subscribe_one_user_to_cycle($user, $cycle, $limit);
	    if ($ret->is_error())
		$errors[] = strval($ret);
	    else
	    {
		$done += (int)$ret->value["done"];
		$errors = array_merge($errors, $ret->value["errors"]);
	    }
	}
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_subscribe_one_cycle_to_matter($id_cycle, $id_activity, $limit = 0)
{
    $id_cycle = (int)$id_cycle;
    $id_activity = (int)$id_activity;
    $limit = (int)$limit;
    $lim = $limit > 0 ? " LIMIT $limit" : "";
    $done = 0;
    $errors = [];

    $users = db_select_all("
        user_cycle.id_user as id_user
        FROM user_cycle
        LEFT JOIN user ON user.id = user_cycle.id_user
        WHERE user_cycle.id_cycle = $id_cycle
        AND user.deleted IS NULL
        ORDER BY user_cycle.id_user
        $lim
    ");

    foreach ($users as $user)
    {
	$ret = automatic_subscription_subscribe_user_to_cycle_matter(
	    $user["id_user"],
	    $id_cycle,
	    $id_activity
	);
	if ($ret->is_error())
	    $errors[] = strval($ret);
	else
	    $done += (int)$ret->value;
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_subscribe_cycle_to_matter($id_cycle, $id_activity, $limit = 0)
{
    if (($cycles = automatic_subscription_resolve_ids("cycle", $id_cycle))->is_error())
	return ($cycles);
    if (($activities = automatic_subscription_resolve_ids("activity", $id_activity))->is_error())
	return ($activities);

    $done = 0;
    $errors = [];
    foreach ($cycles->value as $cycle)
    {
	if ($cycle <= 0)
	    continue ;
	foreach ($activities->value as $activity)
	{
	    if ($activity <= 0)
		continue ;
	    $ret = automatic_subscription_subscribe_one_cycle_to_matter($cycle, $activity, $limit);
	    if ($ret->is_error())
		$errors[] = strval($ret);
	    else
	    {
		$done += (int)$ret->value["done"];
		$errors = array_merge($errors, $ret->value["errors"]);
	    }
	}
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_repair_matters($limit)
{
    $limit = max(1, (int)$limit);
    $done = 0;
    $errors = [];

    $missing = db_select_all("
        DISTINCT user_cycle.id_user as id_user,
        user_cycle.id_cycle as id_cycle,
        activity.id as id_activity
        FROM user_cycle
        LEFT JOIN user ON user.id = user_cycle.id_user
        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle
        LEFT JOIN activity_cycle ON activity_cycle.id_cycle = user_cycle.id_cycle
        LEFT JOIN activity ON activity.id = activity_cycle.id_activity
        LEFT JOIN activity as template ON activity.id_template = template.id
        WHERE user.deleted IS NULL
        AND cycle.deleted IS NULL
        AND ( cycle.done IS NULL OR cycle.done = 0 )
        AND ( cycle.is_template IS NULL OR cycle.is_template = 0 )
        AND activity.id IS NOT NULL
        AND ( activity.parent_activity IS NULL OR activity.parent_activity = -1 )
        AND ( activity.is_template = 0 OR activity.is_template IS NULL )
        AND activity.deleted IS NULL
        AND activity.disabled IS NULL
        AND ( activity.registration_date IS NULL OR activity.registration_date <= NOW() )
        AND ( activity.close_date IS NULL OR activity.close_date > NOW() )
        AND ( activity.done_date IS NULL OR activity.done_date > NOW() )
        AND ".automatic_subscription_effective_sql("activity", "template", "activity_cycle")."
        AND NOT EXISTS (
            SELECT 1
            FROM team
            LEFT JOIN user_team ON user_team.id_team = team.id
            WHERE team.id_activity = activity.id
            AND user_team.id_user = user_cycle.id_user
        )
        ORDER BY user_cycle.id, activity.id
        LIMIT $limit
    ");

    foreach ($missing as $row)
    {
	$ret = automatic_subscription_subscribe_user_to_activity(
	    $row["id_user"],
	    $row["id_activity"],
	    "albedo matter repair for cycle #".$row["id_cycle"]
	);
	if ($ret->is_error())
	    $errors[] = strval($ret);
	else
	    $done += (int)$ret->value;
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_repair_activities($limit)
{
    $limit = max(1, (int)$limit);
    $done = 0;
    $errors = [];

    $missing = db_select_all("
        DISTINCT parent_user_team.id_user as id_user,
        child.id as id_activity,
        parent.id as id_matter
        FROM team as parent_team
        LEFT JOIN user_team as parent_user_team ON parent_user_team.id_team = parent_team.id
        LEFT JOIN activity as parent ON parent.id = parent_team.id_activity
        LEFT JOIN activity as child ON child.parent_activity = parent.id
        LEFT JOIN activity as template ON child.id_template = template.id
        WHERE parent_user_team.id_user IS NOT NULL
        AND parent_user_team.status != 0
        AND parent.deleted IS NULL
        AND child.id IS NOT NULL
        AND ( child.is_template = 0 OR child.is_template IS NULL )
        AND child.deleted IS NULL
        AND child.disabled IS NULL
        AND ".automatic_subscription_effective_sql("child", "template")."
        AND ( child.registration_date IS NULL OR child.registration_date <= NOW() )
        AND ( child.close_date IS NULL OR child.close_date > NOW() )
        AND ( child.done_date IS NULL OR child.done_date > NOW() )
        AND NOT EXISTS (
            SELECT 1
            FROM team as child_team
            LEFT JOIN user_team as child_user_team ON child_user_team.id_team = child_team.id
            WHERE child_team.id_activity = child.id
            AND child_user_team.id_user = parent_user_team.id_user
        )
        ORDER BY parent_team.id, child.id
        LIMIT $limit
    ");

    foreach ($missing as $row)
    {
	$ret = automatic_subscription_subscribe_user_to_activity(
	    $row["id_user"],
	    $row["id_activity"],
	    "albedo activity repair for matter #".$row["id_matter"]
	);
	if ($ret->is_error())
	    $errors[] = strval($ret);
	else
	    $done += (int)$ret->value;
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

function automatic_subscription_repair($limit = 100)
{
    $limit = max(1, (int)$limit);
    $matter_limit = max(1, (int)ceil($limit / 2));
    $activity_limit = max(1, $limit - $matter_limit);
    $done = 0;
    $errors = [];

    $ret = automatic_subscription_repair_matters($matter_limit);
    if ($ret->is_error())
	$errors[] = strval($ret);
    else
    {
	$done += (int)$ret->value["done"];
	$errors = array_merge($errors, $ret->value["errors"]);
    }

    $ret = automatic_subscription_repair_activities($activity_limit);
    if ($ret->is_error())
	$errors[] = strval($ret);
    else
    {
	$done += (int)$ret->value["done"];
	$errors = array_merge($errors, $ret->value["errors"]);
    }

    return (new ValueResponse([
	"done" => $done,
	"errors" => $errors,
    ]));
}

?>
