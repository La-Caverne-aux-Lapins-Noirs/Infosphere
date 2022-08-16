<?php

function check_activity_field(&$fields, $files, $is_template = false, $id_template = -1)
{
    $tfields = [];

    $fields = convert_date($fields);

    if (!is_symbol($fields["codename"]))
	return (new ErrorResponse("InvalidCodeName", $fields["codename"]));
    $tfields["codename"] = $fields["codename"];

    if (isset($fields["parent_activity"]) && $fields["parent_activity"] != -1)
    {
	if (($parent = resolve_codename("activity", @$fields["parent_activity"]))->is_error())
	    return ($parent);
	$tfields["parent_activity"] = $parent->value;
    }
    else
	$tfields["parent_activity"] = -1;

    if ($tfields["parent_activity"] == -1)
	$tfields["type"] = 18; // Module

    if (isset($fields["reference_activity"])
	&& $fields["reference_activity"] != ""
	&& $fields["reference_activity"] != NULL)
    {
	if (($reference = resolve_codename("activity", $fields["reference_activity"]))->is_error())
	    return ($reference);
	$tfields["reference_activity"] = $reference->value;
    }
    else
	$tfields["reference_activity"] = -1;

    if (isset($fields["type"]))
    {
	if (($type = resolve_codename("activity_type", $fields["type"]))->is_error())
	    return ($type);
	$tfields["type"] = $type->value;
    }
    else
	$tfields["type"] = NULL;

    $tfields["is_template"] = $is_template ? "1" : "0";
    $tfields["id_template"] = $id_template;
    // $tfields["template_link"] = $is_template ? "1" : "0";

    default_int_val($tfields, $fields, "grade_a", 85);
    default_int_val($tfields, $fields, "grade_b", 70);
    default_int_val($tfields, $fields, "grade_c", 60);
    default_int_val($tfields, $fields, "grade_d", 50);
    default_bool_val($tfields, $fields, "validation", 3);

    default_int_val($tfields, $fields, "maximum_subscription", -1);
    default_int_val($tfields, $fields, "min_team_size", -1);
    default_int_val($tfields, $fields, "max_team_size", -1);
    default_int_val($tfields, $fields, "credit_a", 0);
    default_int_val($tfields, $fields, "credit_b", 0);
    default_int_val($tfields, $fields, "credit_c", 0);
    default_int_val($tfields, $fields, "credit_d", 0);
    default_int_val($tfields, $fields, "mark", 0);
    default_string_val($tfields, $fields, "repository_name", "");
    default_bool_val($tfields, $fields, "allow_unregistration", 0);
    default_bool_val($tfields, $fields, "enabled", 1);
    default_bool_val($tfields, $fields, "hidden", 0);
    default_int_val($tfields, $fields, "subscription", 0);
    default_int_val($tfields, $fields, "slot_duration", -1);
    default_int_val($tfields, $fields, "estimated_work_duration", 0);
    if ($tfields["slot_duration"] <= 0)
	$tfields["slot_duration"] = NULL;

    $date_fields = [
	"emergence_date", "done_date", "registration_date", "close_date",
	"subject_appeir_date", "subject_disappeir_date", "pickup_date"
    ];
    foreach ($date_fields as $dat)
    {
	if (!isset($fields[$dat])
	    || $fields[$dat] === NULL
	    || $fields[$dat] == -1
	    || $fields[$dat] == "")
	    $tfields[$dat] = NULL;
	else
	{
	    $tfields[$dat] = date_to_timestamp($fields[$dat]);
	    $tfields[$dat] = db_form_date($fields[$dat]);
	}
    }

    if (@strlen($files["subject_file"]["name"]))
    {
	$url = "./dres/activity/".$fields["codename"];
	system("mkdir -p $url ; touch {$url}/index.htm");
	$url .= "/subject.pdf";
	if (($ret = upload_pdf($files["subject_file"]["tmp_name"], $url, 10 * 1024 * 1024)) != "")
	    return (new ErrorResponse($ret, $files["subject_file"]["name"]));
    }
    else if (@strlen($fields["subject_file_link"]))
    {
	$url = "./dres/activity/".$fields["codename"];
	system("mkdir -p $url ; touch {$url}/index.htm");
	$url .= "/subject.htm";
	fulliframe($url, $fields["subject_file_link"]);
    }

    if (@strlen($files["ressource_files"]["name"]))
    {
	$url = "./dres/activity/".$fields["codename"]."/ressources/";
	system("mkdir -p $url ; touch {$url}/index.htm");
	if (($file = $files["ressource_files"]["tmp_name"]) != "")
	{
	    $filename = $files["ressource_files"]["name"];
	    if (mupload_file($file, $url.$filename) == false)
		return (new ErrorResponse("CannotMoveFile", $filename));
	}
    }

    if (@strlen($files["wallpaper_file"]["name"]))
    {
	$url = "./dres/activity/".$fields["codename"]."/";
	system("mkdir -p $url ; touch {$url}/index.htm");
	if (($file = $files["wallpaper_file"]["tmp_name"]) != "")
	{
	    $filename = "wallpaper.png";
	    if (upload_png($file, $url.$filename) == false)
		return (new ErrorResponse("CannotMoveFile", $filename));
	}
    }

    if (@strlen($files["configuration_file"]["name"]))
    {
	$url = "./dres/activity/".$fields["codename"];
	system("mkdir -p $url ; touch {$url}/index.htm");
	$url .= "configuration.dab";
	if (($file = $files["configuration_file"]["tmp_name"]) != "")
	{
	    if (mupload_file($file, $url) == false)
		return (new ErrorResponse("CannotMoveFile", $file));
	}
    }

    return (new ValueResponse($tfields));
}

function edit_activity($fields, $files, $is_template = false, $template_id = -1, $dry = false)
{
    if (($tfields = check_activity_field($fields, $files, $is_template, $template_id))->is_error())
	return ($tfields);
    $tfields = $tfields->value;
    if (($ret = resolve_codename("activity", $tfields["codename"], "codename", true))->is_error())
	return ($ret);
    if ($dry)
	return ($ret);

    $ret = $ret->value;
    $tfields["id_template"] = $ret["id_template"];

    $codename = $tfields["codename"];
    unset($tfields["codename"]);
    return (try_update("activity", $codename, $tfields, "", "", ["name", "description" => false], $fields));
}

function add_activity($fields, $files, $is_template = false, $id_template = -1, $dry = false)
{
    if (($tfields = check_activity_field($fields, $files, $is_template, $id_template))->is_error())
	return ($tfields);
    $tfields = $tfields->value;
    if (!($ret = resolve_codename("activity", $tfields["codename"], "codename", $dry))->is_error())
	return (new ErrorResponse("CodeNameAlreadyUsed", $tfields["codename"]));
    else if ($ret->label != "BadCodeName")
	return ($ret);

    if ($dry)
	return (new ValueResponse(["id" => -1]));

    $codename = $tfields["codename"];
    unset($tfields["codename"]);
    return (try_insert("activity", $codename, $tfields, "", "", ["name" => false, "description" => false], $fields));
}

