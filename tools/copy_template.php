<?php

function activity_normalized_child_codename($parent_codename, $codename)
{
    $codename = trim((string)$codename);
    if ($codename == "")
        return (new ErrorResponse("MissingCodeName"));
    if (strncmp($codename, $parent_codename, strlen($parent_codename)) == 0)
        return (new ValueResponse($codename));
    if (substr($codename, 0, 1) != "-")
        $codename = "-".$codename;
    return (new ValueResponse($parent_codename.$codename));
}

function activity_rewrite_child_codename($old_parent, $new_parent, $child_codename)
{
    if (strncmp($child_codename, $old_parent, strlen($old_parent)) == 0)
        return ($new_parent.substr($child_codename, strlen($old_parent)));
    return ($new_parent."-".$child_codename);
}

function activity_codename_available($codename, $except_id = -1)
{
    global $Database;

    if (!is_symbol($codename))
        return (new ErrorResponse("InvalidCodeName", $codename));
    $except = $except_id == -1 ? "" : " AND id != ".(int)$except_id;
    $codename = $Database->real_escape_string($codename);
    $existing = db_select_one("id FROM activity WHERE codename = '$codename' $except");
    if ($existing != NULL)
        return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    return (new Response);
}

function activity_target_codename(FullActivity $activity, $codename)
{
    if ($activity->parent_activity == -1)
    {
        $codename = trim((string)$codename);
        if ($codename == "")
            return (new ErrorResponse("MissingCodeName"));
        if (!is_symbol($codename))
            return (new ErrorResponse("InvalidCodeName", $codename));
        return (new ValueResponse($codename));
    }
    $parent = db_select_one("codename FROM activity WHERE id = ".(int)$activity->parent_activity);
    if ($parent == NULL)
        return (new ErrorResponse("NotAnId", $activity->parent_activity, "activity"));
    if (($codename = activity_normalized_child_codename($parent["codename"], $codename))->is_error())
        return ($codename);
    if (!is_symbol($codename->value))
        return (new ErrorResponse("InvalidCodeName", $codename->value));
    return ($codename);
}

function rename_activity_codename_with_children(FullActivity $activity, $new_codename)
{
    if (($new_codename = activity_target_codename($activity, $new_codename))->is_error())
        return ($new_codename);
    $new_codename = $new_codename->value;

    if (($ret = activity_codename_available($new_codename, $activity->id))->is_error())
        return ($ret);

    if ($activity->parent_activity == -1)
    {
        $children = db_select_all("id, codename FROM activity WHERE parent_activity = ".(int)$activity->id." AND deleted IS NULL ORDER BY id ASC");
        $renames = [];
        foreach ($children as $child)
        {
            $child_new = activity_rewrite_child_codename($activity->codename, $new_codename, $child["codename"]);
            if (($ret = activity_codename_available($child_new, $child["id"]))->is_error())
                return ($ret);
            $renames[] = ["old" => $child["codename"], "new" => $child_new];
        }

        foreach ($renames as $rename)
            if (($ret = edit_codename("activity", $rename["old"], $rename["new"]))->is_error())
                return ($ret);
    }

    return (edit_codename("activity", $activity->codename, $new_codename));
}

function copy_single_activity(FullActivity $activity, $new_codename)
{
    global $Database;

    if ($activity->parent_activity == -1)
        return (copy_template($activity, $new_codename));
    if (($new_codename = activity_target_codename($activity, $new_codename))->is_error())
        return ($new_codename);
    $new_codename = $new_codename->value;
    if (($ret = activity_codename_available($new_codename))->is_error())
        return ($ret);

    if (($id = insert_activity($activity, $activity->parent_activity, $new_codename, 0, true))->is_error())
        return ($id);
    $id = $id->value;
    ($copy = new FullActivity)->build($id);
    if (($ret = break_template_link($copy, true))->is_error())
        return ($ret);
    $Database->query("UPDATE activity SET id_template = -1 WHERE id = $id");
    return (new ValueResponse($id));
}

function copy_template($activity, $new)
{
    global $Database;

    $ref_switch = [];

    $new = trim((string)$new);
    if (($ret = activity_codename_available($new))->is_error())
	return ($ret);

    $codename = $new;

    if (($id = insert_activity($activity, -1, $codename, 0, true))->is_error())
	return ($id);
    $id = $id->value;
    foreach ($activity->subactivities as $sub)
    {
	$codename = activity_rewrite_child_codename($activity->codename, $new, $sub->codename);
	if (($ret = activity_codename_available($codename))->is_error())
	    return ($ret);
	if (($newid = insert_activity($sub, $id, $codename, 0, true))->is_error())
	    return ($newid);
	$newid = $newid->value;

	// On enregistre la transformation [ancien] = nouveau
	$ref_switch[$sub->id]["newid"] = $newid;
	if (!isset($ref_switch[$sub->id]["refs"]))
	    $ref_switch[$sub->id]["refs"] = [];
	// On place, si on fait reference a une autre activité locale,
	// une reference
	if ($sub->reference_activity != -1)
	{
	    if (!isset($ref_switch[$sub->reference_activity]))
	    {
		$ref_switch[$sub->reference_activity]["newid"] = -1;
		$ref_switch[$sub->reference_activity]["refs"] = [];
	    }
	    $ref_switch[$sub->reference_activity]["refs"][] = $newid;
	}
    }

    foreach ($ref_switch as $sw)
    {
	// On convertis les liens locaux. Pas les liens externes.
	if ($sw["newid"] == -1 || count($sw["refs"]) == 0)
	    continue ;
	foreach ($sw["refs"] as $ids)
	{
	    $Database->query("
               UPDATE activity
               SET reference_activity = {$sw["newid"]}
               WHERE id = $ids
	       ");
	}
    }

    $activity = new FullActivity;
    $activity->build($id);
    if (($ret = break_template_link($activity, true))->is_error())
	return ($ret);
    $Database->query("UPDATE activity SET id_template = -1 WHERE id = $id");
    return (new Response);
}

