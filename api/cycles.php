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
	$ret = $ret->value;
	if (!is_admin())
	{
	    // M'ajouter comme directeur.
	    if (($ret = handle_links($User["id"], $ret["id"], "user", "cycle"))->is_error())
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
    global $LanguageList;

    if ($id == -1)
	bad_request();

    $fields = [];

    if (isset($data["codename"]))
    {
	if (!is_symbol($data["codename"]))
	    return (new ErrorResponse("InvalidParameter", "codename"));
	$fields["codename"] = $data["codename"];
    }

    if (isset($data["cycle"]))
    {
	if (!is_number($data["cycle"]) || $data["cycle"] < 0 || $data["cycle"] > 20)
	    return (new ErrorResponse("InvalidCycleNumber", $data["cycle"]));
	$fields["cycle"] = (int)$data["cycle"];
    }

    if (isset($data["objective"]))
    {
	if (!is_number($data["objective"]) || $data["objective"] < 0)
	    return (new ErrorResponse("InvalidParameter", "objective"));
	$fields["objective"] = (int)$data["objective"];
    }

    if ($module == "cycle" && isset($data["first_day"]))
    {
	if (trim($data["first_day"]) == "")
	    $fields["first_day"] = NULL;
	else if (!check_date($data["first_day"]))
	    return (new ErrorResponse("InvalidDate", $data["first_day"]));
	else
	    $fields["first_day"] = $data["first_day"];
    }

    if (isset($data["check_done"]))
	$fields["done"] = isset($data["done"]);

    foreach ($LanguageList as $lang => $label)
    {
	foreach (["name", "description"] as $field)
	{
	    $lname = $lang."_".$field;
	    if (isset($data[$lname]))
		$fields[$lname] = $data[$lname];
	}
    }

    if ($fields == [])
	return (new ValueResponse([]));

    if (($err = update_table("cycle", $id, $fields))->is_error())
	return ($err);

    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
    ]));
}

function SendCycleMail($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $OriginalUser;
    global $User;

    if ($id == -1 || $module != "cycle")
	bad_request();
    if (!isset($data["subject"]) || !isset($data["content"]))
	bad_request();

    $subject = trim($data["subject"]);
    $content = trim($data["content"]);
    $files = [];
    if (isset($data["file"]))
	foreach ($data["file"] as $fil)
	    if (isset($fil["name"], $fil["content"]))
		$files[$fil["name"]] = base64_decode($fil["content"]);
    if ($subject == "" || $content == "")
	bad_request();
    
    if (($cycle_id = resolve_codename("cycle", $id, "codename", true))->is_error())
	return ($cycle_id);
    $cycle_codename = $cycle_id->value["codename"];
    $cycle_id = $cycle_id->value["id"];

    $mails = [];
    foreach (db_select_all("
        DISTINCT user.mail as mail
        FROM user_cycle
        LEFT JOIN user ON user.id = user_cycle.id_user
        WHERE user_cycle.id_cycle = $cycle_id
        AND user.deleted IS NULL
        AND user.mail IS NOT NULL
        AND user.mail != ''
    ") as $usr)
    {
	if (filter_var($usr["mail"], FILTER_VALIDATE_EMAIL) !== false)
	    $mails[] = $usr["mail"];
    }

    $mails = array_values(array_unique($mails));
    if (count($mails) == 0)
	return (new ErrorResponse("NoUser"));

    $content = implode("\n", [
	// Note: on ne peut pas envoyer de mail en tant que quelqu'un d'autre
	"Vous recevez ce mail en tant qu'étudiant du cycle $cycle_codename.",
	"Celui-ci vous est envoyé par ".$OriginalUser["codename"].".",
	"",
	$content
    ]);

    if (($ret = send_mail($mails, $subject, $content, NULL, $files))->is_error())
	return ($ret);
    add_log(TRACE, "Sending mail to ".implode(", ", $mails)." : $subject : $content");

    return (new ValueResponse([
	"msg" => $Dictionnary["Mail"].": ".count($mails),
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
    global $Dictionnary;

    if ($id == -1 || $module != "cycle")
	bad_request();
    if (($users = resolve_codename("user", $data["user"]))->is_error())
	return ($users);
    $users = $users->value;
    if (($ret = handle_links($users, $id, "user", "cycle"))->is_error())
	return ($ret);
    if (($ret = automatic_subscription_subscribe_user_to_cycle($users, $id))->is_error())
	add_log(WARNING, "Automatic cycle subscription failed: ".strval($ret), 1);
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
		],
	    ]
    ])]));
}

function SetUserProps($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;
    global $User;

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

    if (isset($data["commentaries"]))
    {
	$commentaries = strip_tags($data["commentaries"]);
	$commentaries = $Database->real_escape_string($commentaries);
	$author = $User["id"];
	$now = db_form_date(now());
	$Database->query("
		INSERT INTO comment (id_user, id_misc, misc_type, content)
		VALUES ($author, {$usrcyc["id"]}, 2, '$commentaries')
	");
    }
    
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
    global $Dictionnary;

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
    if ($module == "cycle" && ($ret = automatic_subscription_subscribe_cycle_to_matter($id, $data["activity"]))->is_error())
	add_log(WARNING, "Automatic matter subscription failed: ".strval($ret), 1);
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
		],
		[
		    "name" => $Dictionnary["ReplacementSubscription"],
		    "codename" => "replacement_subscription",
		],
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

    if (!strlen(trim(@$data["replacement_subscription"])))
	$data["replacement_subscription"] = NULL;

    $actcyc = db_select_one("id FROM activity_cycle WHERE id_activity = $actid AND id_cycle = $cyc");
    
    if (($ret = update_table(
	"activity_cycle",
	$actcyc,
	$data,
	["id", "id_activity", "id_cycle", "activity", "action"]
    ))->is_error())
	return ($ret);
    if ($module == "cycle" && ($ret = automatic_subscription_subscribe_cycle_to_matter($cyc, $actid))->is_error())
	add_log(WARNING, "Automatic matter subscription failed: ".strval($ret), 1);
        
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
    global $Dictionnary;
    global $Database;
    global $one_week;
    
    if ($id == -1)
	bad_request();
    // On récupère la semaine d'instantiation
    // Celle ci ne peut pas etre plus dans le passé qu'un trimestre.
    $first_week = date_to_timestamp(@$data["first_week"]);
    if ($first_week < now() - 60 * 60 * 24 * 7 * 15)
	bad_request();
    $first_week_tstamp = $first_week;
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
	if (($error_msg = instantiate_template(
	    $act, db_form_date($first_week_tstamp + (int)$acti["week_shift"] * $one_week)
	))->is_error())
	    goto Clear;
	$matter[] = $activity = $error_msg->value;
	$props = [
	    "cursus" => $acti["cursus"]
	];
	if ($acti["replacement_subscription"])
	    $props["replacement_subscription"] = $acti["replacement_subscription"];
	if (($error_msg = handle_linksf([
	    "left_value" => $activity,
	    "right_value" => $id,
	    "left_field_name" => "activity",
	    "right_field_name" => "cycle",
	    "properties" => $props
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

