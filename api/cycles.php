<?php

function DisplayCycles($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $page = $module;
    $cycles = fetch_cycle($module, $id, true, false, true);
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($cycles, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/cycle/list_cycle.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddCycle($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1 || !isset($data["cycles"]))
	bad_request();
    $cnt = 0;
    foreach ($data["cycles"] as $cycle)
    {
	if (($fweek = isset($cycle["first_week"]) ? $cycle["first_week"] : NULL) === NULL)
	{
	    if ($module != "cursus")
		bad_request();
	}
	else
	{
	    if ($module != "cycle")
		bad_request();
	}
	if (($ret = add_cycle($cycle["name"], $cycle["year"], $fweek))->is_error())
	    return ($ret);
	if (!is_admin())
	{
	    // M'ajouter comme directeur.
	}
	$subs[] = $cycle["name"];
	$cnt += 1;
    }
    $ret = DisplayCycles(-1, [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["CycleAdded"].": $cnt";
    return ($ret);
}

function DeleteCycle($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    if (($ret = mark_as_deleted("cycle", $id))->is_error())
	return ($ret);
    $dis = DisplayCycles(-1, $data, $method, $output, $module);
    $dis->value = array_merge(["msg" => "Deleted"], $dis->value);
    return ($dis);
}

function EditCycle($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    $fields = [];
    if (isset($data["check_done"]))
    {
	$is_done = isset($data["check_done"]) ? (bool)@$data["done"] : false;
	$fields["done"] = $is_done;
    }

    if ($fields == [])
	return (new ValueResponse([]));
    if (($err = update_table("cycle", $id, ["done" => $is_done]))->is_error())
	return ($err);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
    ]));
}

function SetCycleTeacher($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    if (isset($data["teacher"]))
    {
	if (($teachers = split_teacher($data["teacher"]))->is_error())
	    return ($teachers);	
	$teachers = $teachers->value;
    }
    else
    {
	if (!isset($data["laboratory"]))
	    bad_request();
	if (($lab = split_symbols($data["laboratory"], ";"))->is_error())
	    return ($data["laboratory"]);
	$teachers = [
	    "user" => [],
	    "laboratory" => $lab->value
	];
    }
    foreach ($teachers["laboratory"] as $lab)
	if (($ret = handle_links($id, $lab, "cycle", "laboratory", false, "cycle_teacher"))->is_error())
	    return ($ret);
    foreach ($teachers["user"] as $lab)
	if (($ret = handle_links($id, $lab, "cycle", "user", false, "cycle_teacher"))->is_error())
	    return ($ret);
    $cycle = fetch_cycle($module, $id);
    $cycle = $cycle[array_key_first($cycle)];
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "cycle",
	    "hook_id" => $id,
	    "linked_name" => [
		"placeholder" => "Teacher or laboratory",
		"table" => "teacher",
		"" => "teacher",
		"#" => "laboratory",
	    ],
	    "linked_elems" => $cycle["teacher"],
	    "admin_func" => "is_director_for_cycle",
    ])]));
}

function SetUser($id, $data, $method, $output, $module)
{
    if ($id == -1 || $module != "cycle")
	bad_request();
    if (($users = resolve_codename("user", $data["user"]))->is_error())
	return ($users);
    $users = $users->value;
    if (($ret = handle_links($users, $id, "user", "cycle"))->is_error())
	return ($ret);
    $cycle = fetch_cycle($module, $id, true, false, true);
    $cycle = $cycle[array_key_first($cycle)];
    return (new ValueResponse([
	"msg" => "Edited",
	"content" => list_of_linksb([
	    "hook_name" => "cycle",
	    "hook_id" => $cycle["id"],
	    "linked_name" => "user",
	    "linked_elems" => $cycle["user"],
	    "admin_func" => "is_director_for_cycle"
    ])]));
}
