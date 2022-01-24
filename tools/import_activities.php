<?php

function test_date($template, &$date, &$crash_log, $label, $mandatory, $cat, $session = false)
{
    if (($dat = @$date[$label]) == NULL)
    {
	if ($mandatory == false)
	    return (true);
	$crash_log[] = "missing field for $label";
	return (false);
    }
    if ($template === NULL)
	$template = is_array($dat);
    if ($template == false)
    {
	if (date_to_timestamp($dat) == date_to_timestamp("today"))
	{
	    $crash_log[] = "invalid date format $dat for $label of $cat";
	    return (false);
	}
	return (true);
    }
    if (count($dat) < 2)
    {
	$crash_log[] = "minimal date format for template is 'week_number, day of week' for $label of $cat";
	return (false);
    }
    if ($dat[0] < 1 || $dat[0] > 52)
    {
	$crash_log[] = "invalid week number $dat [1-52] for $label of $cat";
	return (false);
    }
    if (count($dat) < 3)
    {
	if ($session == false)
	    $date[$label][2] = 0;
	else
	    $crash_log[] = "minimal date format for session template is 'week_number, day of week, hour' for $label of $cat";
    }
    if (count($dat) < 4)
	$date[$label][3] = 0;
    if (($day = array_search(strtolower($dat[1]), ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"])) === false)
    {
	$crash_log[] = "invalid week day {$dat[1]} for $label of $cat";
	return (false);
    }
    $final_date["week_emergence_date"] = $dat[0];
    $final_date["day_emergence_date"] = $day + 1;
    $final_date["hour_emergence_date"] = $dat[2].":".$dat[3];

    $date[$label] = convert_date($final_date)["emergence_date"];
    return (true);
}

function test_between(&$value, &$crash_log, $label, $min, $max, $mandatory, $cat, $default = NULL)
{
    if (!isset($value[$label]))
    {
	if ($mandatory)
	{
	    $crash_log[] = "field $label was mandatory for $cat";
	    return (false);
	}
	if ($default != NULL)
	    $value[$label] = $default;
	return (true);
    }
    $val = $value[$label];
    if ($val < $min || $val > $max)
    {
	$crash_log[] = "invalid value $val for $label [$min - $max] for $cat";
	return (false);
    }
    return (true);
}

function test_language(&$value, &$crash_log, $label, $mandatory, $cat)
{
    global $LanguageList;

    foreach ($LanguageList as $ac => $u)
    {
	$lng = $ac."_".$label;
	if (!isset($value[$lng]))
	{
	    if ($mandatory)
	    {
		$crash_log[] = "field $lng was mandatory for $cat";
		return (false);
	    }
	}
    }
    return (true);
}

function test_text(&$value, &$crash_log, $label, $mandatory, $cat)
{
    if (!isset($value[$label]))
    {
	if ($mandatory)
	{
	    $crash_log[] = "field $lng was mandatory for $cat";
	    return (false);
	}
    }
    return (true);
}

function import_activities($configuration, $template_page, $dry = false)
{
    global $ActivityType;

    $crash_log = [];
    $added_here = [];
    if (isset($configuration["Modules"]))
    {
	foreach ($configuration["Modules"] as $key => &$value)
	{
	    $codename = str_replace("_", "-", $key);
	    if (isset($value["codename"]))
		$codename = str_replace("_", "-", $value["codename"]);
	    $template = is_array($value["emergence_date"]); // C'est un template si ca emploi le systeme offset

	    if ($template != $template_page)
	    {
		$crash_log[] = "module $codename is not gonna be add because of type mismatch";
		continue ;
	    }
	    foreach (["emergence", "done"] as $dat)
	    {
		if (test_date($template, $value, $crash_log, $dat."_date", true, $key) == false && $dry == false)
		    return ([false, $crash_log]);
	    }
	    if (test_between($value, $crash_log, "credit", 0, 15, false, $key, 0) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "mandatory", 0, 1, false, $key, 0) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_language($value, $crash_log, "name", true, $key) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_language($value, $crash_log, "description", false, $key) == false && $dry == false)
		return ([false, $crash_log]);

	    if (test_between($value, $crash_log, "grade_a", 60, 100, false, $key, 85) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "grade_b", 30, $value["grade_a"], false, $key, $value["grade_a"] * 0.75) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "grade_d", 20, 80, false, $key, $value["grade_a"] * 0.75) == false && $dry == false)
		return ([false, $crash_log]);


	    $value["type"] = 18; // Module
	    $value["codename"] = $codename;
	    $value["parent_activity"] = -1;
	    $err = add_activity($value, [], $template, -1, $dry);
	    if ($err->is_error())
	    {
		if ($err->label == "CodeNameAlreadyUsed")
		{
		    if (!$dry && ($err = edit_activity($value, [], $template, -1))->is_error())
		    {
			$crash_log[] = "module exists and cannot edit module $codename: ".strval($err);
			return ([false, $crash_log]);
		    }
		}
		else
		{
		    $crash_log[] = "cannot edit module $codename: ".strval($err);
		    if ($dry == false)
			return ([false, $crash_log]);
		}
	    }
	    $added_here[$codename] = $template;
	}
    }

    if (isset($configuration["Calendar"]))
    {
	foreach ($configuration["Calendar"] as $key => &$value)
	{
	    $date_fields = [
		"emergence", "done", "subject_appeir",
		"subject_disappeir", "registration",
		"close", "pickup"
	    ];

	    $codename = str_replace("_", "-", $key);
	    if (isset($value["codename"]))
		$codename = str_replace("_", "-", $value["codename"]);
	    if (!isset($value["module"]))
	    {
		$crash_log[] = "missing module field required by $key";
		if ($dry == false)
		    return ([false, $crash_log]);
		$value["module"] = "placeholder";
	    }
	    $module = str_replace("_", "-", $value["module"]);
	    if (($mod = resolve_codename("activity", $module, "codename", true))->is_error())
	    {
		if ($dry && $mod->label == "BadCodeName" &&
		    (isset($added_here[$module]) || $module == "placeholder"))
		{
		    $mod = new ValueResponse([
			"id" => "89",
			"codename" => $module
		    ]);
		    if ($module != "placeholder")
			$mod->value["is_template"] = $added_here[$module];
		    else
		    {
			$in = false;
			foreach ($date_fields as $f)
			{
			    if (isset($value[$f."_date"]))
			    {
				$mod->value["is_template"] = is_array($value[$f."_date"]);
				$in = true;
				break ;
			    }
			}
		    }
		}
		else
		{
		    $crash_log[] = "error with $module required by $key, ".strval($mod);
		    if ($dry == false)
			return ([false, $crash_log]);
		    continue ;
		}
	    }
	    $mod = $mod->value;
	    if (!isset($mod["is_template"]))
		$template = $template_page;
	    else
		$template = $mod["is_template"]; // C'est un template si le module au dessus est un template
	    if ($template != $template_page)
	    {
		$crash_log[] = "warning: module $codename is not gonna be add because of type mismatch";
		continue ;
	    }

	    foreach ($date_fields as $dat)
	    {
		if (test_date($template, $value, $crash_log, $dat."_date", false, $key) == false && $dry == false)
		    return ([false, $crash_log]);
	    }

	    if (test_text($value, $crash_log, "type", true, $key))
	    {
		if (strtolower($value["type"]) == "tp")
		    $value["type"] = "PracticalWork";
		foreach ($ActivityType as $atype)
		{
		    if (strtolower($atype["codename"]) == strtolower($value["type"]))
		    {
			$value["type"] = $atype["id"];
			break ;
		    }
		}
	    }
	    else if ($dry == false)
		return ([false, $crash_log]);

	    if (test_between($value, $crash_log, "allow_unregistration", 0, 1, false, $key, 1) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "slot_duration", 0, 240, false, $key, -1) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "maximum_subscription", -1, 5000, false, $key, 1) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "team_size", -1, 12, false, $key, -1) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_between($value, $crash_log, "mandatory", 0, 1, false, $key, 0) == false && $dry == false)
		return ([false, $crash_log]);

	    if (test_language($value, $crash_log, "name", true, $key) == false && $dry == false)
		return ([false, $crash_log]);
	    if (test_language($value, $crash_log, "description", false, $key) == false && $dry == false)
		return ([false, $crash_log]);

	    $value["codename"] = $codename;
	    $value["parent_activity"] = $mod["id"];
	    $act = add_activity($value, [], $template, -1, $dry);

	    if ($act->is_error())
	    {
		if ($act->label == "CodeNameAlreadyUsed")
		{
		    if (($act = edit_activity($value, [], $template, -1, $dry))->is_error())
		    {
			$crash_log[] = "cannot edit activity $codename: ".strval($act);
			return ([false, $crash_log]);
		    }
		}
		else if ($act->label == "BadCodeName")
		{
		    if ($dry)
			$act = new ValueResponse(["id" => 89]);
		    else
		    {
			$crash_log[] = "cannot add $codename: ".strval($act);
			return ([false, $crash_log]);
		    }
		}
		else
		{
		    $crash_log[] = "cannot add $codename: ".strval($act);
		    if ($dry == false)
			return ([false, $crash_log]);
		}
	    }
	    $act = $act->value;

	    // if ($ActivityType[$value["type"]]["type"] == CLASSROOM && isset($value["Sessions"]))
	    if (isset($value["Sessions"]))
	    {
		foreach ($value["Sessions"] as $session)
		{
		    if (test_between($session, $crash_log, "maximum_subscription", -1, 5000, false, $key, 1) == false && $dry == false)
			return ([false, $crash_log]);
		    $fields = [
			"begin", "end"
		    ];
		    foreach ($fields as $dat)
		    {
			if (test_date($template, $session, $crash_log, $dat."_date", true, $key, true) == false && $dry == false)
			    return ([false, $crash_log]);
		    }
		    $session["activity"] = $act["id"];
		    if (($err = add_session($session, $dry))->is_error())
		    {
			$crash_log[] = "error while adding session to {$mod["codename"]} : $err->label";
			if ($dry == false)
			    return ([false, $crash_log]);
		    }
		}
	    }
	}
    }

    return ([!$dry, $crash_log]);
}

