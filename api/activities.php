<?php

function DisplayModule($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    // On récupère les modules
    $page = $module;
    $template = ($module == "template");
    if ($id == -1)
    {
	$modules = get_modules($template);
	if ($output == "json")
	    return (new ValueResponse(["content" => json_encode($modules, JSON_UNESCAPED_SLASHES)]));
	ob_start();
	if (count($modules))
	    foreach ($modules as $mod)
		require ("./pages/activity/display_module_list_element.phtml");
	else
	    echo $Dictionnary["Empty"];
    }
    // On récupère UN module et ses activités
    else
    {
	$is_teacher = is_teacher_for_activity($id);
	$shortcut = false;
	if (!is_director() && !is_cycle_director() && !$is_teacher)
	    forbidden();
	else
	    $shortcut = true;
	if (($module = new FullActivity)->build($id) == false)
	    return (new ValueResponse(["content" => $Dictionnary["Empty"]]));
	foreach ($module->cycle as $cyc)
	    if (is_cycle_director_of(-1, $cyc["id_cycle"]))
	{
	    $shortcut = true;
	    break ;
	}
	if (!$shortcut)
	    forbidden();

	if ($output == "json")
	    return (new ValueResponse(["content" => json_encode($module, JSON_UNESCAPED_SLASHES)]));
	ob_start();
	if (@$_GET["sub"] == "1")
	{
	    // On récupère la liste des activités
	    if (count($module->subactivities))
		foreach ($module->subactivities as $act)
		    require ("./pages/activity/display_activity_list_element.phtml");
	    else
		echo $Dictionnary["Empty"];
	}
	else
	{
	    // On récupère le module lui même
	    require ("./pages/activity/display_activity.phtml");
	}
    }
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function DisplayActivityAdmin($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;
    global $one_year;

    if ($id == -1)
	bad_request();
    ob_start();
    $api_id_activity = $id;
    require ("./pages/modules/load_my_activities.php");
    require ("./pages/modules/load_managed_activities.php");
    $matter = $mdatas[array_key_first($mdatas)][0];
    $matter->medal_listed = true;
    $edit_medal = true;
    sort_by_medal_grade($matter->medal, false);
    require ("./pages/modules/module_admin.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function MoveActivity($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || !isset($data["new_father"]))
	bad_request();
    ($target_act = new FullActivity)->build($data["new_father"], false, false);
    if ($target_act->parent_activity != -1)
	bad_request();
    if ($target_act->is_teacher == false)
	return (new ErrorResponse("PermissionDenied"));
    db_update_one("activity", $id, ["parent_activity" => $target_act->id]);
    return (new ValueResponse(["msg" => $Dictionnary["Moved"]]));
}

function AddModule($id, $data, $method, $output, $module)
{
    if ($id != -1)
	bad_request();
    $template = ($module == "template");
    if (($request = add_activity([
	"codename" => $data["codename"],
	"type" => 18,
	"parent_activity" => NULL
    ], [], $template))->is_error())
	return ($request);
    return (DisplayModule($id, [], "GET", $output, $module));
}

function SetActivityRegistration($id, $data, $method, $output, $module)
{
    global $User;
    global $Dictionnary;
    global $Configuration;
    global $five_minute;
    global $SUBID;

    if ($id == -1 && $method != "DELETE")
	bad_request();
    $id = abs($id);
    if ($SUBID != -1)
	$SUBID = abs($SUBID);
    ($activity = new FullActivity)->build($id);
    $target_team = (int)$SUBID;

    if (is_assistant_for_activity($id) == false)
    {
	if (($module != "instance" && $module != "module" && $module  != "activity") ||
	    ($data["action"] != "subscribe" && $data["action"] != "registration") ||
	    $SUBID != -1)
	    forbidden();
	$id_user = $User["id"];
	$team = db_select_one("
	  team.id
	  FROM team
	  LEFT JOIN user_team
	  ON team.id = user_team.id_team
	  WHERE team.id_activity = $id AND user_team.id_user = $id_user
	");

	if ($team == NULL)
	{
	    // Pas de relation user_team, alors c'est une création ou une jonction
	    if ($method != "PUT")
		bad_request();
	    $ret = subscribe_to_instance(
		$activity, NULL, $target_team
	    );
	    if ($target_team != -1)
		$msg = "AJoinRequestHaveBeenSent";
	    else if ($activity->teamable)
		$msg = "YouHaveCreatedATeam";
	    else
		$msg = "YouHaveSubscribed";
	}
	else
	{
	    // Il y a une équipe, alors c'est une désinscription.
	    if ($method != "DELETE")
		bad_request();
	    $ret = unsubscribe_from_instance(
		$activity, NULL
	    );
	    if (!$activity->user_team)
		$msg = "YouHaveUnsubscribed";
	    else if ($activity->user_team["leader"]["id"] == $User["id"])
		$msg = "YouHaveDestroyedYourTeam";
	    else
		$msg = "YouHaveLeftYourTeam";
	}
	if ($ret->is_error())
	    return ($ret);

	ob_start();
	($activity = new FullActivity)->build($id);
	require_once ("./pages/instance/about_buttons.php");
	return (new ValueResponse([
	    "msg" => $Dictionnary[$msg],
	    "content" => ob_get_clean()
	]));
    }

    // Si on est là, c'est qu'on est prof ou assistant.
    if ($method == "PUT")
    {
	$target_team = (int)$SUBID;

	if (isset($data["subaction"]))
	{
	    if ($data["subaction"] == "complete")
	    {
		// On pioche dans les inscrits de la matière
		// $module = db_select_all("");
		debug_response("Non implémenté");
	    }
	    else if ($data["subaction"] == "merge")
	    {
		// On pioche dans les équipes incomplètes
		// $oteams = db_select_all("");
		debug_response("Non implémenté");
	    }
	    else
		bad_request();
	    $users = NULL;
	}
	else
	    $users = @$data["id_user"];

	$ret = subscribe_to_instance($activity, $users, $SUBID, true, true);
	if ($ret->is_error() || $module != "instance")
	    return ($ret);
	
	($activity = new FullActivity)->build($id);
	ob_start();
	if ($target_team == -1)
	    // Nouvelle équipe, nouvelle liste d'inscrits
	    require_once ("./pages/instance/team_list.php");
	else
	{
	    // Nouveau membre, nouvelle liste de membres
	    foreach ($activity->team as $cteam)
	    {
		if ($target_team != $cteam["id"])
		    continue ;
		require_once ("./pages/instance/new_single_team.phtml");
		break ;
	    }
	}
	return (new ValueResponse([
	    "msg" => (string)$ret,
	    "content" => ob_get_clean()
	]));
    }

    if ($method == "DELETE")
    {
	if ($data["action"] == "team")
	{
	    if ($SUBID != -1)
		$id_team = " AND team.id = ".(int)$SUBID;
	    else
		$id_team = "";
	    $user_teams = db_select_all("
		user_team.id_user FROM user_team LEFT JOIN team ON user_team.id_team = team.id
		WHERE team.id_activity = {$activity->id} $id_team
	    ");
	    if (!count($user_teams))
		not_found();
	}
	else
	{
	    if ($SUBID == -1)
		bad_request();
	    else
		$user_teams = [["id_user" => (int)$SUBID]];
	}
	foreach ($user_teams as $ut)
	{
	    $ret = unsubscribe_from_instance($activity, $ut["id_user"], true);
	    if ($ret->is_error())
		return ($ret);
	}
	    
	if ($module != "instance")
	    return ($ret);
	// Renouvellement de la liste - faute d'avoir l'information equipe/membre
	($activity = new FullActivity)->build($id);
	ob_start();
	require_once ("./pages/instance/team_list.php");
	return (new ValueResponse([
	    "msg" => (string)$ret,
	    "content" => ob_get_clean()
	]));
    }

    // Impossible
    bad_request();
}

function PickupActivity($id, $data, $method, $output, $module)
{
    global $Configuration;
    
    if ($id == -1)
	bad_request();
    $team = $data["pickup"];
    // Indique s'il faut récupérer le ramassage ou le dépot live
    $alive = false;
    if (isset($data["alive"]))
	$alive = !!$data["alive"];
    $official = false;
    if (isset($data["official"]))
	$official = !!$data["official"];
    $team_leader = db_select_one("
	user.codename
	FROM user_team LEFT JOIN user ON user_team.id_user = user.id
	WHERE id_team = $team AND status = 2
    ");
    if ($team_leader == NULL)
	not_found();
    $team_leader = $team_leader["codename"];
    if (($activity = new FullActivity)->build($id) == false)
	not_found();
    if (strlen($activity = $activity->repository_name) == 0)
	return (new ErrorResponse("NoRepositoryConfigured"));
    
    $ret = hand_request([
	"command" => "retrieve",
	"user" => $team_leader,
	"repo" => $activity,
	"alive" => $alive,
	"official" => $official
    ]);
    if (!isset($ret["result"]) || $ret["result"] != "ok")
	return (new ErrorResponse(isset($ret["message"]) ? $ret["message"] : "NothingTurnedIn"));

    return (new ValueResponse([
	"filename" => str_replace("-", "_", basename($activity))."_".str_replace(".", "_", $team_leader).".tar.gz",
	"content" => base64_decode($ret["content"])
    ]));
}

function EditTemplateLink($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    ($activity = new FullActivity)->build($id);
    if ($activity->id_template == -1 || $activity->id_template == NULL)
	return (new ErrorResponse("NoTemplate"));
    if ($activity->template_link == false)
	return (new ErrorResponse("CannotRestoreLink"));
    if (($ret = break_template_link($activity, $module == "template"))->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Done"
    ]));
}

function ResetTemplateLink($id, $data, $method, $output, $module)
{
    if ($id != -1)
	bad_request();
    ($activity = new FullActivity)->build($id);
    if ($activity->id_template == -1 || $activity->id_template == NULL)
	return (new ErrorResponse("NoTemplate"));
    if ($activity->template_link == false)
	return (new ErrorResponse("CannotRestoreLink"));
    return (new ErrorResponse([
	"msg" => "CannotReset"
    ]));
}

// Instantie autant les activités que les modules.
function Instantiate($id, $data, $method, $output, $module)
{
    if ($id == -1 || strlen(@$data["start_date"]) == 0)
	bad_request();
    ($activity = new FullActivity)->build($id);
    // Si c'est un module, on vise forcement une instantiation à la racine
    if ($activity->parent_activity != -1)
    {
	if (!strlen(@$data["parent"]))
	    $data["parent"] = -1;
	else
	{
	    ($parent_target = new FullActivity)->build($data["parent"]);
	    if ($parent_target->id_template != $id)
		return (new ErrorResponse("InvalidTarget"));
	    $data["parent"] = $parent_target->id;
	}
    }
    else
    {
	$data["type"] = 18;
	$data["parent"] = NULL;
    }
    if (!isset($data["prefix"]))
	$data["prefix"] = "";
    if (!isset($data["suffix"]))
	$data["suffix"] = "";
    instantiate_template($activity, $data["start_date"], $data["prefix"], $data["suffix"], $data["parent"]);
    $instances = db_select_all("
       id, codename
       FROM activity
       WHERE id_template = {$activity->id}
       AND deleted IS NULL
       AND template_link = 1
       AND done_date > NOW()
    ");
    ob_start();
    if (!count($instances))
	echo "<div>/</div>";
    else
    {
	foreach ($instances as $instance)
	{
	    echo "<div><a href=\"".inside_link("instances", $instance["id"])."\">";
	    echo $instance["codename"];
	    echo "</a></div>";
	}
    }
    return (new ValueResponse([
	"msg" => "ActivityInstantiated",
	"content" => ob_get_clean()
    ]));
}

function DisplayActivity($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    if (($activity = new FullActivity)->build($id) == false)
	return (new ValueResponse(["content" => $Dictionnary["Empty"]]));
    $template = $activity->is_template;
    $page = $module;
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($activity, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    if (@$_GET["sub"] == "1")
    {
	if ($activity->type_type != 2)
	{}
	else if (count($activity->session))
	    foreach ($activity->session as $session)
		require ("./pages/activity/display_session_list_element.phtml");
	else
	    echo $Dictionnary["Empty"];
    }
    else
    {
	// On récupère l'activité elle-même
	$module = $activity; // $module est la variable utilisée dans display_activity.phtml
	require ("./pages/activity/display_activity.phtml");
    }
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddActivity($id, $data, $method, $output, $module)
{
    if ($id != -1)
	bad_request();
    ($module = new FullActivity)->build(@$data["module"]);
    if ($data["codename"][0] != "-")
	$data["codename"] = "-".$data["codename"];
    $data["codename"] = $module->codename.$data["codename"];
    if (($request = add_activity(
	["codename" => $data["codename"], "parent_activity" => $module->id], [], $module->is_template))->is_error()
    )
        return ($request);
    $_GET["sub"] = 1;
    return (DisplayModule($module->id, [], "GET", $output, $module));
}

function DuplicateActivity($id, $data, $method, $output, $module)
{
    // copy_template.php
    ($original = new FullActivity)->build($id);
    if (($ret = copy_template($original, $data["codename"]))->is_error())
	return ($ret);
    return (DisplayModule(-1, $data, "GET", $output, $module));
}
    
function SetActivityLink($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if (isset($data["cycle"]))
    {
	// On vérifie que c'est bien un template avec lequel on va s'accrocher
	if (!is_director_for_cycle($data["cycle"]))
	    forbidden();
	if (($cyc = resolve_codename("cycle", $data["cycle"], "codename", true))->is_error())
	    return ($cyc);
	$cyc = $cyc->value;
	if (($act = resolve_codename("activity", $id, "codename", true))->is_error())
	    return ($act);
	$act = $act->value;
	if (!isset($cyc["is_template"]))
	    $cyc["is_template"] = 0;
	if (!isset($act["is_template"]))
	    $act["is_template"] = 0;
	if ($cyc["is_template"] != $act["is_template"])
	    bad_request("CannotMixInstancesAndTemplates");
    }
    $links = [
	"cycle" => [
	    "table" => "cycle",
	    "" => "cycle",
	    "properties" => [],
	    "display" => "cycle"
	],
	"skill" => [
	    "table" => "skill",
	    "" => "skill",
	    "properties" => [],
	    "display" => "skill"
	],
	///////////////////////////////////////////////////////////////////////
	"teacher" => [ // gere prof et labo en ajout et user en suppression
	    "table" => "teacher",
	    "" => "user",
	    "#" => "laboratory",
            "properties" => [],
	    "display" => "teacher"
	],
	"laboratory" => [ // seulement pour supprimer les labos
	    "table" => "teacher",
	    "" => "laboratory",
	    "properties" => [],
	    "display" => "teacher"
	],
	///////////////////////////////////////////////////////////////////////
	"support" => [ // gere tout support en ajout, et support seul en suppresion
	    "table" => "support",
            "" => "support",
	    "#" => "support_asset",
            "@" => "support_category",
	    "$" => ["activity", "subactivity"], // RTable
	    "properties" => [
		"chapter" => 0
	    ],
            "display" => "support"
	],
	"support_asset" => [ // seulement pour supprimer les supports "support_asset"
	    "table" => "support",
            "" => "support_asset",
     	    "properties" => [
		"chapter" => 0
	    ],
	     "display" => "support"
	],
	"support_category" => [ // seulement pour supprimer les supports "category"
	    "table" => "support",
	     "" => "support_category",
     	    "properties" => [
		"chapter" => 0
	    ],
	     "display" => "support"
	],
	"activity" => [ // seulement pour supprimer les supports "subactivity"
	    "table" => "support",
  	    "" => ["activity", "subactivity"],
	    "properties" => [
		"chapter" => 0
	    ],
	   "display" => "support"
	],
	///////////////////////////////////////////////////////////////////////
	"scale" => [
	    "table" => "scale",
	    "" => "scale",
	    "properties" => [
		"chapter" => 0,
		"type" => 0
	    ],
	    "display" => "scale"
	],
	"mcq" => [
	    "table" => "scale",
	    "name" => "mcq",
	    "" => "scale",
	    "properties" => [
		"chapter" => 0,
		"type" => 1
	    ],
	    "display" => "mcq"
	],
	"satisfaction" => [
	    "table" => "scale",
	    "name" => "satisfaction",
	    "" => "scale",
	    "properties" => [
		"chapter" => 0,
		"type" => 2
	    ],
	    "display" => "satisfaction"
	]
    ];
    foreach ($links as $link_name => $link_data)
    {
	if (!isset($data[$link_name]))
	    continue ;
	$pfx = get_prefix($data[$link_name]);
	$table = $link_data["table"];
	if (!isset($link_data[$pfx["prefix"]]))
	    continue ;
	else if (is_array($link_rtable = $link_field = $link_data[$pfx["prefix"]]))
	{
	    $link_rtable = $link_field[1];
	    $link_field = $link_field[0];
	}
	$properties = $link_data["properties"];

	// S'il y a un chapitre dans la configuration, alors on récupère le dernier dans la BDD + 1
	if (isset($properties["chapter"]))
	{
	    if (($idt = resolve_codename("activity", $id))->is_error())
		return ($idt);
	    $idt = $idt->value;
	    $idt = db_select_one("chapter FROM activity_$table WHERE id_activity = $idt ORDER BY chapter DESC");
	    $properties["chapter"] = ($idt === NULL ? 0 : $idt["chapter"] + 1);
	}

	if (($request = handle_linksf([
	    "left_value" => $id,
	    "right_value" => $pfx["mod"],
	    "left_field_name" => "activity",
	    "right_field_name" => $link_field,
	    "link_table_name" => "activity_$table",
	    "right_table_name" => $link_rtable,
	    "properties" => $properties
	]))->is_error())
	    return ($request);

	($activity = new FullActivity())->build($id);

	// L'affichage utilise les "categories" et pas les elements divers existants
	/*$link_data[""] = */$link_name = $link_data["display"];
	// Regénération
	return (new ValueResponse([
	    "msg" => $Dictionnary["Edited"],
	    "content" => list_of_linksb([
		"hook_name" => "activity",
		"hook_id" => $activity->id,
		"linked_name" => $link_data,
		"linked_elems" => $activity->$link_name
	])]));
    }
    bad_request();
}

function DeleteActivity($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $sb = db_select_all("id FROM activity WHERE parent_activity = $id");
    foreach ($sb as $s)
	if (($request = mark_as_deleted("activity", $s["id"], "codename", false, true))->is_error())
	    return ($request);
    if (($request = mark_as_deleted("activity", $id, "codename", false, true))->is_error())
	return ($request);
    return (new ValueResponse([
	"msg" => $Dictionnary["Deleted"],
    ]));
}

function EditActivity($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $LanguageList;

    if ($id == -1)
	bad_request();
    ($activity = new FullActivity)->build($id);
    // Specific treatment
    if (isset($data["codename"]))
    {
	if (($ret = edit_codename("activity", $activity->codename, $data["codename"]))->is_error())
	    return ($ret);
	return (new ValueResponse([
	    "msg" => $Dictionnary["Renamed"],
	]));
    }

    // On cherche à valider ou dévalider des éléments. Des verifications sont à faire.
    if (isset($data["validated"]) && $activity->is_template)
    {
	if ($data["validated"] && $activity->parent_activity == -1)
	{ // On veut valider une matière, on verifie que tout en dessous est validé
	    $unvalid = [];
	    foreach ($activity->subactivities as $suba)
		if ($suba->validated == false && $suba->enabled == true)
		    $unvalid[] = $suba->codename;
	    if (count($unvalid))
		return (new ErrorResponse("AllActivitiesAreNotValidated", implode(", ", $unvalid)));
	}
	else if (!$data["validated"] && $activity->parent_activity != -1)
	{ // On veut dévalider une une activité, on dévalide la matière au dessus
	    if (($top = EditActivity($activity->parent_activity, $data, $method, $output, $module))->is_error())
		return ($top);
	    // Ok.
	}
    }
    
    foreach (["emergence", "registration", "close", "subject_appeir", "subject_disappeir", "pickup", "done"] as $d)
    {
	if (!isset($data["{$d}_check"]))
	    continue ;
	if ($data["{$d}_check"])
	{
	    // Pas de résultat: c'est un template. Il faut le convertir
	    if (!isset($data["{$d}_date"]))
		$data = convert_date($data);
	    $final = $data["{$d}_date"];
	    $final = db_form_date($final);
	}
	else
	    $final = NULL;
	db_update_one("activity", $id, ["{$d}_date" => $final]);
	goto End;
    }
    
    // Specific sanitizer
    foreach (["type", "subscription"] as $ints)
	if (isset($data[$ints]))
	    $data[$ints] = (int)$data[$ints];
    if (isset($data["enabled"]))
    {
	if ($data["enabled"])
	    $data["disabled"] = "";
	else
	    $data["disabled"] = db_form_date(now());
	unset($data["enabled"]);
    }
    if (isset($data["repository_name"])
	&& strchr($data["repository_name"], " ") !== false)
        return (new ErrorResponse("InvalidRepositoryName"));
    $lng_fields = [];
    foreach (array_keys($LanguageList) as $lng)
	foreach ($data as $k => $v)
	    if (substr($k, 0, strlen($lng) + 1) == $lng."_")
	    {
		$text = substr($k, strlen($lng) + 1);
		if (!in_array($text, ["name", "description", "objective", "method", "reference"]))
		    continue ;
		$data[$k] = htmlspecialchars($v);
		$lng_fields[] = $k;
	    }
    if (isset($data["type"]))
    {
	if (($ret = db_select_one("* FROM activity_type WHERE id = {$data["type"]}")) == NULL)
	    return (new ErrorResponse("NotFound", $data["type"]));
	if (($ret["type"] == 0 && $activity->parent_activity != -1) || ($ret["type"] != 0 && $activity->parent_activity == -1))
	    return (new ErrorResponse("InvalidValue", $data["type"]));
    }
    if (isset($data["subscription"]))
	if ($data["subscription"] < 0 || $data["subscription"] > 2)
	    return (new ErrorResponse("InvalidValue", $data["subscription"]));
    if (isset($data["reference_activity"]) && $data["reference_activity"] != NULL)
    {
	if (($ret = resolve_codename("activity", $data["reference_activity"]))->is_error())
	    return ($ret);
	$data["reference_activity"] = $ret->value;
    }
    
    // General treatment
    $fields = array_merge([
	"type", "subscription", "disabled", "allow_unregistration", "hidden", "validated", "grade_a", "grade_b", "grade_c", "grade_d",
	"grade_bonus", "credit_a", "credit_b", "credit_c", "credit_d", "mark", "slot_duration", "repository_name",
	"reference_activity", "min_team_size", "max_team_size", "estimated_work_duration", "validation",
	"declaration_type"
    ], $lng_fields);
    $edit = [];
    foreach ($fields as $field)
    {
	if (!isset($data[$field]))
	    continue ;
	if ($data[$field] == "")
	    $data[$field] = NULL;
	if (substr($field, 0, 6) == "grade_")
	    $data[$field] = (int)fraction($data[$field], 100);
	$edit[$field] = $data[$field];
    }
    if (count($edit))
	db_update_one("activity", $id, $edit);

  End:
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
    ]));
}

function AddMedal($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    $page = $module;
    if (isset($data["medal"]))
	$medal = $data["medal"];
    else if (isset($data["codename"]))
	$medal = $data["codename"];
    else
	bad_request();
    if (($medal = split_symbols($medal))->is_error())
	return ($medal);
    if (($act = db_select_one("parent_activity FROM activity WHERE id = $id")) == NULL)
	not_found();
    if (($act = $act["parent_activity"]) === NULL)
	$act = -1;
    foreach ($medal->value as $med)
    {
	$pfx = get_prefix($med);
	$neg = $pfx["negative"] ? "-" : "";
	if (($err = handle_linksf([
	    "left_value" => $id,
	    "right_value" => $neg.$pfx["label"],
	    "left_field_name" => "activity",
	    "right_field_name" => "medal",
	    "properties" => [
		"mark" => isset($pfx["parameters"][0]) && $act != -1 ? (int)($pfx["parameters"][0]) : 1,
		"local" => $pfx["prefix"] == "#" ? 1 : 0,
		"role" =>
		    $act != -1 ?
			($pfx["prefix"] == "$" ? -1 : 1) :
			(isset($pfx["parameters"][0]) ? $pfx["parameters"][0] : 1),
	    ]
	]))->is_error()) {
	    return ($err);
	}
    }
    ob_start();
    ($module = new FullActivity)->build($id);
    require_once ("./pages/activity/display_medal.phtml");
    return (new ValueResponse([
	"msg" => $Dictionnary["Added"],
	"content" => ob_get_clean()
    ]));
}

function EditMedal($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || !isset($data["medal"]) || $data["medal"] == -1)
	bad_request();
    $page = $module;
    $id_medal = $data["medal"];
    $fields = [];
    foreach (["local", "role", "mark"] as $f)
	if (isset($data[$f]))
	    $fields[$f] = (int)$data[$f];
    if (count($fields) == 0)
	bad_request();
    if (db_update_one(
	"activity_medal", [
	    "id_activity" => $id,
	    "id_medal" => $data["medal"]
	],
	$fields,
    ) == NULL) {
	return (new ErrorResponse("NotFound"));
    }
    ob_start();
    ($module = new FullActivity)->build($id);
    require_once ("./pages/activity/display_medal.phtml");
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => ob_get_clean()
    ]));
}

function SetSoftware($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    if (($data["type"] = (int)$data["type"]) < 0 || $data["type"] > 2)
	bad_request();
    $data["software"] = $Database->real_escape_string($data["software"]);
    $Database->query("
       INSERT INTO activity_software (id_activity, software, type)
       VALUES ($id, '{$data["software"]}', {$data["type"]})
       ");
    ($module = new FullActivity)->build($id);
    $html = '<select name="type" style="width: 100%;">';
    foreach ([
	$Dictionnary["EvaluatorRepository"],
	$Dictionnary["ReferenceRepository"],
	$Dictionnary["ToolsRepository"],
    ] as $k => $label)
        $html .= "<option value=\"$k\">$label</option>";
    $html .= '</select>';
    return (new ValueResponse([
	"msg" => $Dictionnary["Added"],
	"content" => list_of_linksb([
	    "hook_name" => "activity",
	    "hook_id" => $id,
	    "linked_name" => "software",
	    "linked_elems" => $module->repositories,
	    "admin_func" => "is_teacher_for_activity",
	    "display_link" => false,
	    "additional_html" => $html,
	    "extra_form_id" => @$data["extra_form_id"],
	])
    ]));
}

function RemoveSoftware($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $SUBID;

    if ($id == -1)
	bad_request();
    $data["software"] = (int)$data["software"];
    $SUBID = abs((int)$SUBID);
    $Database->query("
	DELETE FROM activity_software
	WHERE id = $SUBID
	AND id_activity = $id
    ");
    ($module = new FullActivity)->build($id);
    $tabs = [
	$Dictionnary["EvaluatorRepository"],
	$Dictionnary["ReferenceRepository"],
	$Dictionnary["ToolsRepository"],
    ];
    $html = "<select name=\"type\" style=\"width: 100%;\">\n";
    foreach ($tabs as $k => $v)
	$html .= "<option value=\"$k\">$v</option>\n";
    $html = "</select>";
    return (new ValueResponse([
	"msg" => $Dictionnary["Deleted"],
	"content" => list_of_linksb([
	    "hook_name" => "activity",
	    "hook_id" => $id,
	    "linked_name" => "software",
	    "linked_elems" => $module->repositories,
	    "admin_func" => "is_teacher_for_activity",
	    "display_link" => false,
	    "additional_html" => $html
    ])]));
}
    
function SetSubject($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;
    global $Language;
    global $LanguageList;

    if ($id == -1)
	bad_request();
    if (!isset($data["language"]) || ($data["language"] != "NA" && !isset($LanguageList[$data["language"]])))
	$data["language"] = $Language;
    ($module = new FullActivity)->build($id);
    $target = $Configuration->ActivitiesDir($module->codename, $data["language"] == "NA" ? "" : $data["language"]);
    if (!isset($data["file"]) || count($data["file"]) == 0)
    {
	@unlink($target."subject.pdf");
	@unlink($target."subject.htm");
	@unlink($target."configuration.dab");
	$html = ""; goto GetOut; // web-mode a du mal avec l'indentation avec un else...
    }
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	$ext = pathinfo($files["name"], PATHINFO_EXTENSION);
	$content = base64_decode($files["content"]);
	new_directory($target);
	if ($ext == "html")
	    $ext = "htm";
	if ($ext == "pdf" || $ext == "htm")
	    file_put_contents($target."subject.$ext", $content);
	else if ($ext == "dab")
	    file_put_contents($target."configuration.dab", $content);
	else
	    return (new ErrorResponse("InvalidFile", $ext, $Dictionnary["SupportedFormats"].": pdf, htm, dab"));
    }
    ($module = new FullActivity)->build($id);
    ob_start();
    ?>
	<a href="<?=@$module->subject[$data["language"]][0]; ?>">
	    <?=@$module->subject[$data["language"]][0] ? $Dictionnary["SeeSubject"] : ""; ?>
	</a>
	<a href="<?=@$module->configuration[$data["language"]][0]; ?>">
	    <?=@$module->configuration[$data["language"]][0] ? $Dictionnary["SeeConfiguration"] : ""; ?>
	</a>
    <?php
    $html = ob_get_clean();
    GetOut:
    return (new ValueResponse([
	"msg" => $Dictionnary["Added"],
	"content" => $html
    ]));
}

function GetRessourceDir($id, $data, $method, $output, $module, $msg = "")
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1 || !isset($data["language"]))
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";

    $page = $module;
    ($activity = new FullActivity)->build($id);
    $root = $Configuration->ActivitiesDir($activity->codename, $data["language"] == "NA" ? "" : $data["language"])."ressource/";
    $html = get_dir($root, $data["path"], $page, $id, "ressource", "file_browser", is_teacher_for_activity($id), $data["language"]);

    $msg = $msg ? ["msg" => $msg] : [];
    return (new ValueResponse(array_merge($msg, [
	"content" => $html
    ])));
}

function AddRessource($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1 || !isset($data["file"]) || !isset($data["language"]))
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";
    ($module = new FullActivity)->build($id);
    $path = resolve_path($data["path"]);
    $root = $Configuration->ActivitiesDir($module->codename, $data["language"] == "NA" ? "" : $data["language"])."ressource/";
    $target = resolve_path($root.$path)."/";
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if ($files["name"] == "index.php")
	    continue ; // Clairement, non.
	$ext = pathinfo($files["name"], PATHINFO_EXTENSION);
	$content = base64_decode($files["content"]);
	new_directory($target);
	file_put_contents($target.str_replace(" ", "_", $files["name"]), $content);
	system("chmod 640 ".$target.$files["name"]); // Surtout si il peut y avoir du script...
    }
    return (GetRessourceDir($id, $data, "GET", $output, $module, "RessourceAdded"));
}

function RemoveRessource($id, $data, $method, $output, $module)
{
    if ($id == -1 || !isset($data["file"]))
	bad_request();
    if (!isset($data["language"]))
	$data["language"] = "";

    // On vérifie que le dossier est bien celui de l'activité demandé...
    ($module = new FullActivity)->build($id);
    $normal_dir = $Configuration->ActivitiesDir($module->codename, $data["language"]);
    $file = $data["file"];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("activity_ressource", $id, $file) == false)
	bad_request();
    return (GetRessourceDir($id, $data, "GET", $output, $module, "RessourceDeleted"));
}
    
function GetMoodDir($id, $data, $method, $output, $module, $msg = "")
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1)
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";
    // Requis seulement pour éviter les collisions de formulaire, sinon inutile pour la mécanique interne
    if (!isset($data["language"]))
	$data["language"] = "NA";
	
    $page = $module;
    ($activity = new FullActivity)->build($id);
    $root = $Configuration->ActivitiesDir($activity->codename, "")."mood/";
    $html = get_dir($root, $data["path"], $page, $id, "mood", "mood_browser", is_teacher_for_activity($id), $data["language"]);

    $msg = $msg ? ["msg" => $msg] : [];
    return (new ValueResponse(array_merge($msg, [
	"content" => $html
    ])));
}

function AddMood($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1 || !isset($data["file"]) || !isset($data["action"]))
	bad_request();
    if (in_array($action = $data["action"], ["wallpaper", "icon", "mood", "intro"]) == false)
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";
    ($module = new FullActivity)->build($id);
    $path = resolve_path($data["path"]);
    $root = $Configuration->ActivitiesDir($module->codename, "");
    $target = resolve_path($root."mood/".$path)."/";
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if ($files["name"] == "index.php")
	    continue ; // Clairement, non.
	$ext = pathinfo($files["name"], PATHINFO_EXTENSION);
	$content = base64_decode($files["content"]);
	new_directory($target);
	$tar = NULL;
	if (in_array($ext, ["jpg", "jpeg", "png"]) && ($action == "wallpaper" || $action == "icon"))
	    $tar = $root.$action.".".$ext;
	else if (in_array($ext, ["mp4", "ogv"]) && $action == "intro")
	    $tar = $root."intro.".$ext;
	else if (in_array($ext, ["txt"]) && $action == "mood")
	    $tar = $root."playlist.".$ext;
	else if (in_array($ext, ["mp3", "ogg"]) && $action == "mood")
	    $tar = $target.str_replace(" ", "_", $files["name"]);
	if ($tar)
	{
	    file_put_contents($tar, $content);
	    $tar = escapeshellarg($tar);
	    system("chmod 640 $tar"); // Surtout si il peut y avoir du script...
	}
    }
    if ($action == "mood")
	return (GetMoodDir($id, $data, "GET", $output, $module, "MoodAdded"));
    $language = "NA";
    ($module = new FullActivity)->build($id);
    ob_start();
    if ($action == "wallpaper") { ?>
	<div
	    class="wallpaper_sample" style="background-image: url('<?=$module->wallpaper[$language][0]; ?>?<?=now(); ?>');"
	    ondblclick="window.open('<?=$module->wallpaper[$language][0]; ?>', '_blank');"
	>
	</div>
    <?php }
    if ($action == "icon") { ?>
	<div
	    class="wallpaper_sample" style="background-image: url('<?=$module->icon[$language][0]; ?>?<?=now(); ?>');"
	    ondblclick="window.open('<?=$module->icon[$language][0]; ?>', '_blank');"
	>
	</div>
    <?php }
    if ($action == "intro") { ?>
	<video
	    controls
	    class="wallpaper_sample" src="<?=$module->intro[$language][0]; ?>?<?=now(); ?>"
	    ondblclick="window.open('<?=$module->intro[$language][0]; ?>', '_blank');"
	>
	</video>
    <?php }
    return (new ValueResponse([
	"msg" => $Dictionnary["MoodAdded"],
	"content" => ob_get_clean()
    ]));
}

function RemoveMood($id, $data, $method, $output, $module)
{
    if ($id == -1 || !isset($data["file"]))
	bad_request();
    if (!isset($data["language"]))
	$data["language"] = "";

    // On vérifie que le dossier est bien celui de l'activité demandé...
    ($module = new FullActivity)->build($id);
    $normal_dir = $Configuration->ActivitiesDir($module->codename, $data["language"]);
    $file = $data["file"];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("activity_mood", $id, $file) == false)
	bad_request();
    return (GetMoodDir($id, $data, "GET", $output, $module, "MoodDeleted"));
}
