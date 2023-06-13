<?php

function copy_template($activity, $new)
{
    global $Database;

    $ref_switch = [];

    if (!($err = resolve_codename("activity", $new))->is_error())
	return (new ErrorResponse("CodeNameAlreadyUsed", $new));
    if ($err->label != "BadCodeName")
	return ($err);

    $codename = $new;

    if (($id = insert_activity($activity, -1, $codename, 0, true))->is_error())
	return ($id);
    $id = $id->value;
    foreach ($activity->subactivities as $sub)
    {
	$codename = str_replace($activity->codename, $new, $sub->codename);
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

