<?php

function DisplayCycles($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $page = $module;
    $cycles = fetch_cycle($module, $id, false, false, true);
    if ($module == "cursus")
	$_GET["p"] = "CycleTemplateMenu";
    else
	$_GET["p"] = "CycleMenu";
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($cycles, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/cycle/list_cycle.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddCycle($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

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
	if (($ret = add_cycle($cycle["name"], $cycle["year"], $cycle, $fweek))->is_error())
	    return ($ret);
	if (!is_admin())
	{
	    // M'ajouter comme directeur.
	    if (($ret = handle_links($User["id"], $cycle["id"], "user", "cycle"))->is_error())
		return ($ret);
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
	    "method" => "post",
	    "hook_name" => $module,
	    "hook_id" => $cycle["id"],
	    "linked_name" => "user",
	    "linked_elems" => $cycle["user"],
	    "admin_func" => "is_director_for_cycle",
	    "extra_properties" => [
		[
		    "name" => $Dictionnary["Curriculum"],
		    "codename" => "cursus",
		]
	    ]
    ])]));
}

function SetUserProps($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || $module != "cycle")
	bad_request();
    if (($users = resolve_codename("user", $data["user"]))->is_error())
	return ($users);
    $users = $users->value;
    if (($cyc = resolve_codename("cycle", $id))->is_error())
	return ($cyc);
    $cyc = $cyc->value;

    $usrcyc = db_select_one("id FROM user_cycle WHERE id_user = $users AND id_cycle = $cyc");
    if (($ret = update_table(
	"user_cycle",
	$usrcyc,
	$data,
	["id", "id_user", "id_cycle", "user", "action"]
    ))->is_error())
        return ($ret);
    
    $cycle = fetch_cycle($module, $id, true, false, true);
    $cycle = $cycle[array_key_first($cycle)];
    return (new ValueResponse([
	"msg" => "Edited",
	"content" => list_of_linksb([
	    "method" => "post",
	    "hook_name" => $module,
	    "hook_id" => $cycle["id"],
	    "linked_name" => "user",
	    "linked_elems" => $cycle["user"],
	    "admin_func" => "is_director_for_cycle",
	    "extra_properties" => [
		[
		    "name" => $Dictionnary["Curriculum"],
		    "codename" => "cursus",
		]
	    ]
    ])]));
}

function SetMatter($id, $data, $method, $output, $module)
{
    if ($id == -1 || !isset($data["activity"]))
	bad_request();
    if (($act = resolve_codename("activity", $data["activity"], "codename", true))->is_error())
	return ($act);
    $act = $act->value;

    // Templates dans templates, instances dans instances.
    if (isset($act[0]))
    {
	if ($act[0]["is_template"] != ($module == "cursus"))
	    bad_request();
    }
    else if ($act["is_template"] != ($module == "cursus"))
	bad_request();
    if (($ret = handle_links($data["activity"], $id, "activity", "cycle"))->is_error())
	return ($ret);
    $cycle = fetch_cycle($module, $id, true, false, true);
    $cycle = $cycle[array_key_first($cycle)];
    return (new ValueResponse([
	"msg" => "Edited",
	"content" => list_of_linksb([
	    "method" => "post",
	    "hook_name" => $module,
	    "hook_id" => $cycle["id"],
	    "linked_name" => "activity",
	    "linked_elems" => $cycle["activity"],
	    "admin_func" => "is_director_for_cycle",
	    "extra_properties" => [
		[
		    "name" => $Dictionnary["Curriculum"],
		    "codename" => "cursus",
		]
	    ]
    ])]));    
    }

function SetMatterProps($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || !isset($data["activity"]))
	bad_request();
    if (($act = resolve_codename("activity", $data["activity"], "codename", true))->is_error())
	return ($act);
    $act = $act->value;
    if (($actid = resolve_codename("activity", $data["activity"]))->is_error())
	return ($act);
    $actid = $actid->value;

    // Templates dans templates, instances dans instances.
    if (isset($act[0]))
    {
	if ($act[0]["is_template"] != ($module == "cursus"))
	    bad_request();
    }
    else if ($act["is_template"] != ($module == "cursus"))
	bad_request();

    if (($cyc = resolve_codename("cycle", $id))->is_error())
	return ($cyc);
    $cyc = $cyc->value;

    $actcyc = db_select_one("id FROM activity_cycle WHERE id_activity = $actid AND id_cycle = $cyc");
    
    if (($ret = update_table(
	"activity_cycle",
	$actcyc,
	$data,
	["id", "id_activity", "id_cycle", "activity", "action"]
    ))->is_error())
	return ($ret);
        
    $cycle = fetch_cycle($module, $id, true, false, true);
    $cycle = $cycle[array_key_first($cycle)];
    return (new ValueResponse([
	"msg" => "Edited",
	"content" => list_of_linksb([
	    "method" => "post",
	    "hook_name" => $module,
	    "hook_id" => $cycle["id"],
	    "linked_name" => "activity",
	    "linked_elems" => $cycle["activity"],
	    "admin_func" => "is_director_for_cycle",
	    "extra_properties" => [
		[
		    "name" => $Dictionnary["Curriculum"],
		    "codename" => "cursus",
		]
	    ]
    ])]));    
}

function SetSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || !isset($data["school"]))
	bad_request();
    if (($ret = handle_links($data["school"], $id, "school", "cycle"))->is_error())
	return ($ret);
    $cycle = fetch_cycle($module, $id);
    $cycle = $cycle[array_key_first($cycle)];
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "cycle",
	    "hook_id" => $id,
	    "linked_name" => "school",
	    "linked_elems" => $cycle["school"],
	    "admin_func" => "is_director_for_cycle",
    ])]));
}

function InstantiateCycle($id, $data, $method, $output, $module)
{
    global $Dicitonnary;
    global $Database;
    
    if ($id == -1)
	bad_request();
    // On récupère la semaine d'instantiation
    // Celle ci ne peut pas etre plus dans le passé qu'un trimestre.
    $first_week = date_to_timestamp(@$data["first_week"]);
    if ($first_week < now() - 60 * 60 * 24 * 7 * 15)
	bad_request();
    $first_week = db_form_date($first_week);
    // Instancier le cycle, puis toutes les matieres contenues
    $cycle = fetch_cycle($module, $id, true, false, true);
    $cycle = $cycle[array_key_first($cycle)];
    if (@strlen($data["instance_name"]) == 0)
	$name = $cycle["codename"]."_".datex("d_m_Y", $first_week);
    else
	$name = $data["instance_name"];
    if (($ret = add_cycle($name, $cycle["cycle"], $cycle, $first_week, $cycle["id"]))->is_error())
	return ($ret);
    $ret = $ret->value;
    // L'id du cycle créé
    $id = $ret["id"];

    // Sera un ErrorResponse au besoin.
    $error_msg = NULL;

    // Le cycle est maintenant crée. Il faut donc instantier le contenu du template.
    $matter = [];
    foreach ($cycle["activity"] as $acti)
    {
	($act = new FullActivity)->build($acti["id"]);
	if (($error_msg = instantiate_template($act, $first_week))->is_error())
	    goto Clear;
	$matter[] = $activity = $error_msg->value;
	if (($error_msg = handle_links([
	    "left_value" => $activity,
	    "right_value" => $id,
	    "left_field_name" => "activity",
	    "right_field_name" => "cycle",
	    "properties" => [
		"cursus" => $cycle["cursus"]
	    ]
	]))->is_error())
	    goto Clear;
    }
    // A ce stade, les matières, les activités, les sessions, les rendez vous existent.
    // Les dates sont résolues et les liens avec le cycle sont établis.
    // Comme les fonctions établissant les autorités sur ces éléments exploitent le modèle
    // pour dispatcher cette autorité, on a presque terminé: il faut transmettre les écoles
    // pour transmettre l'autorité de la direction de cette école
    foreach ($cycle["school"] as $school)
	if (($error_msg = handle_links($school["id"], $id, "school", "cycle"))->is_error())
	    goto Clear;
    return (new ValueResponse([
	"msg" => $Dictionnary["Done"]
    ]));
    
Clear:
    foreach ($matter as $matter_id)
    {
	foreach (db_select_all("id FROM activity WHERE id_parent  = $matter_id") as $act)
	{
	    foreach (db_select_all("id FROM session WHERE id_activity = ".$act["id"]) as $ses)
		$Database->query("DELETE FROM appointment_slot WHERE id_session = ".$ses["id"]);
	    $Database->query("DELETE FROM session WHERE id_activity = ".$act["id"]);
	}
	$Database->query("DELETE FROM activity WHERE id_parent = $matter_id");
    }
    $Database->query("DELETE FROM activity_cycle WHERE id_cycle = $id");
    $Database->query("DELETE FROM school_cycle WHERE id_cycle = $id");
    $Database->query("DELETE FROM cycle WHERE id = $id");
    return ($error_msg);
}

