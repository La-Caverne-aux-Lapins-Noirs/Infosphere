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
		require_once ("./pages/instance/single_team.phtml");
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
    global $Dictionnary;
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
    $correction = false;
    if (isset($data["correction"]))
	$correction = !!$data["correction"];
    $team_leader = db_select_one("
	user.codename, user.id
	FROM user_team LEFT JOIN user ON user_team.id_user = user.id
	WHERE id_team = $team AND status = 2
    ");
    if ($team_leader == NULL)
	not_found();
    $user_id = $team_leader["id"];
    $team_leader = $team_leader["codename"];
    if (($activity = new FullActivity)->build($id) == false)
	not_found();
    if (strlen($activity_name = $activity->repository_name) == 0)
	return (new ErrorResponse("NoRepositoryConfigured"));

    if ($correction === true)
    {
	// Faire en sorte de retourner deux variables et les base64 différement pour les mettre dans le json
	[$actConf, $allowFunc] = build_evaluator_configuration($activity, $user_id);
	if ($actConf === NULL || $allowFunc === NULL)
	    return (new ErrorResponse("NoCorrectionAvailable"));
	$ret = hand_request([
	    "command" => "retrieve",
	    "user" => $team_leader,
	    "repo" => $activity_name,
	    "alive" => $alive,
	    "official" => $official,
	    "correction" => $correction,
	    "configuration" => base64_encode($actConf),
	    "allowFunc" => base64_encode($allowFunc),
	    "is_exam" => ((isset($activity->type) ? (int)$activity->type : 0) >= 5
		       && (isset($activity->type) ? (int)$activity->type : 0) <= 9)
	]);
    }
    else
	$ret = hand_request([
	    "command" => "retrieve",
	    "user" => $team_leader,
	    "repo" => $activity_name,
	    "alive" => $alive,
	    "official" => $official
	]);

    // On élimine les erreurs
    if (!isset($ret["result"]) || $ret["result"] != "ok" || !isset($ret["content"]))
	return (new ErrorResponse(isset($ret["message"]) ? $ret["message"] : "NothingTurnedIn"));

    if (($content = base64_decode($ret["content"])) == NULL)
	return (new ErrorResponse(isset($ret["message"]) ? $ret["message"] : "BadTarball"));
    
    if ($correction === true)
    {

	// On envoie les mails
	$students_mail = array_keys(db_select_all("
	user.mail
	FROM user_team LEFT JOIN user ON user_team.id_user = user.id
	WHERE id_team = $team", "mail"));

	if ($official)
	    $mail_content = "This evaluation is official and has been launched by a teacher !";
	else
	    $mail_content = "This evaluation is not official and has been launched by a teacher !";

	// En commentaire le temps de régler le fichier corrompu en téléchargement
	//
	send_mail($students_mail, $Dictionnary["EvaluationReport"]." ".basename($activity_name),
		  $mail_content, NULL, [["report.tar.gz" => $content]], false);
	if ($official)
	    official_correction_mark_missing_medals_as_failed($activity, $team);
    }

    if ($official)
	$official = "_official";
    else
	$official = "";
    return (new ValueResponse([
	"filename" => str_replace("-", "_", basename($activity_name))."_".str_replace(".", "_", $team_leader).$official.".tar.gz",
	"content" => $content
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
    foreach (["type", "subscription", "money"] as $ints)
	if (isset($data[$ints]))
	    $data[$ints] = (int)$data[$ints];
    if (isset($data["money"]) && $data["money"] < 0)
	return (new ErrorResponse("InvalidValue", $data["money"]));
    if (isset($data["automatic_correction_frequency"]) && $data["automatic_correction_frequency"] !== "")
    {
	if (!is_number($data["automatic_correction_frequency"])
	    || (int)$data["automatic_correction_frequency"] < 0)
	    return (new ErrorResponse("InvalidValue", $data["automatic_correction_frequency"]));
	$data["automatic_correction_frequency"] = (int)$data["automatic_correction_frequency"];
    }
    foreach (["progressive_slot_opening", "team_based_slot_opening"] as $bool)
	if (isset($data[$bool]))
	    $data[$bool] = $data[$bool] ? 1 : 0;
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
	"grade_bonus", "credit_a", "credit_b", "credit_c", "credit_d", "money", "slot_duration",
	"progressive_slot_opening", "team_based_slot_opening", "repository_name",
	"reference_activity", "min_team_size", "max_team_size", "estimated_work_duration",
	"automatic_correction_frequency", "validation", "declaration_type"
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
		"money" => isset($pfx["parameters"][0]) && $act != -1 ? (int)($pfx["parameters"][0]) : 1,
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
    foreach (["local", "role", "money"] as $f)
	if (isset($data[$f]))
	    $fields[$f] = (int)$data[$f];
    if (isset($fields["money"]) && $fields["money"] < 0)
	return (new ErrorResponse("InvalidValue", $fields["money"]));
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


function activity_software_repository_labels()
{
    global $Dictionnary;

    return ([
	0 => $Dictionnary["EvaluatorRepository"],
	1 => $Dictionnary["ReferenceRepository"],
	2 => $Dictionnary["ToolsRepository"],
    ]);
}

function render_activity_software_panel($page, $id, $module, $extra_form_id = "", $wrap = true)
{
    global $Dictionnary;

    $panel_id = "software-".$id.$extra_form_id;
    $labels = activity_software_repository_labels();
    $repositories = [];
    foreach ($labels as $type => $unused)
	$repositories[$type] = [];
    foreach ($module->repositories as $repo)
    {
	$type = isset($repo["type"]) ? (int)$repo["type"] : 2;
	if (!isset($repositories[$type]))
	    $repositories[$type] = [];
	$repositories[$type][] = $repo;
    }

    ob_start();
?>
<?php if ($wrap) { ?>
<div class="activity_software_panel" id="<?=$panel_id; ?>">
<?php } ?>
    <?php if (is_teacher_for_activity($id)) { ?>
	<form
	    method="put"
	    action="/api/<?=$page; ?>/<?=$id; ?>/software"
	    onsubmit="return silent_submit(this, '<?=$panel_id; ?>');"
	    class="activity_software_add_form"
	>
	    <input type="hidden" name="extra_form_id" value="<?=$extra_form_id; ?>" />
	    <select name="type">
		<?php foreach ($labels as $type => $label) { ?>
		    <option value="<?=$type; ?>"><?=$label; ?></option>
		<?php } ?>
	    </select>
	    <input
		type="text"
		name="software"
		placeholder="git://, svn://, https://..."
	    />
	    <input
		type="button"
		onclick="silent_submit(this, '<?=$panel_id; ?>');"
		value="&#10003;"
		style="color: green;"
	    />
	</form>
    <?php } ?>

    <?php foreach ($labels as $type => $label) { ?>
	<div class="activity_software_group">
	    <h5><?=$label; ?></h5>
	    <?php if (count($repositories[$type]) == 0) { ?>
		<p><?=$Dictionnary["Empty"]; ?></p>
	    <?php } else { ?>
		<?php foreach ($repositories[$type] as $repo) { ?>
		    <?php
		    $repository = $repo["software"];
		    $href = htmlspecialchars($repository, ENT_QUOTES);
		    $text = htmlspecialchars($repository, ENT_QUOTES);
		    ?>
		    <form
			method="delete"
			action="/api/<?=$page; ?>/<?=$id; ?>/software/<?=$repo["id"]; ?>"
			onsubmit="return silent_submit(this, '<?=$panel_id; ?>');"
			class="activity_software_entry"
		    >
			<input type="hidden" name="extra_form_id" value="<?=$extra_form_id; ?>" />
			<span>
			    <a href="<?=$href; ?>"><?=$text; ?></a>
			</span>
			<?php if (is_teacher_for_activity($id)) { ?>
			    <input
				type="button"
				onclick="silent_submit(this, '<?=$panel_id; ?>');"
				value="&#10007;"
				style="color: red;"
			    />
			<?php } ?>
		    </form>
		<?php } ?>
	    <?php } ?>
	</div>
    <?php } ?>
<?php if ($wrap) { ?>
</div>
<?php } ?>
<?php
    return (ob_get_clean());
}

function SetSoftware($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    $page = $module;
    if (($data["type"] = (int)$data["type"]) < 0 || $data["type"] > 2)
	bad_request();
    if (!isset($data["software"]) || trim($data["software"]) == "")
	bad_request();
    $data["software"] = $Database->real_escape_string(trim($data["software"]));
    $Database->query("
       INSERT INTO activity_software (id_activity, software, type)
       VALUES ($id, '{$data["software"]}', {$data["type"]})
       ");
    ($activity = new FullActivity)->build($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Added"],
	"content" => render_activity_software_panel($page, $id, $activity, @$data["extra_form_id"], false)
    ]));
}

function RemoveSoftware($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $SUBID;

    if ($id == -1)
	bad_request();
    $page = $module;
    $SUBID = abs((int)$SUBID);
    $Database->query("
	DELETE FROM activity_software
	WHERE id = $SUBID
	AND id_activity = $id
    ");
    ($activity = new FullActivity)->build($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Deleted"],
	"content" => render_activity_software_panel($page, $id, $activity, @$data["extra_form_id"], false)
    ]));
}

function activity_subject_language($data)
{
    global $Language;
    global $LanguageList;

    if (!isset($data["language"]) || ($data["language"] != "NA" && !isset($LanguageList[$data["language"]])))
	return ($Language);
    return ($data["language"]);
}

function activity_subject_files()
{
    return (["subject.pdf", "subject.txt", "subject.htm", "subject.html"]);
}

function remove_activity_subject_files($target)
{
    foreach (activity_subject_files() as $file)
	@unlink($target.$file);
}

function render_activity_subject_browser($page, $id, $activity, $language, $wrap = true)
{
    global $Configuration;
    global $Dictionnary;

    $root = $Configuration->ActivitiesDir($activity->codename, $language == "NA" ? "" : $language);
    $files = array_merge(["configuration.dab"], activity_subject_files());
    $entries = [];
    foreach ($files as $file)
	if (file_exists($root.$file))
	    $entries[] = $root.$file;

    ob_start();
?>
<?php if ($wrap) { ?>
<div class="file_browser" id="subject_browser<?=$language; ?>">
<?php } ?>
    <?php foreach ($entries as $content) { ?>
	<div
	    class="icon <?=pathinfo($content, PATHINFO_EXTENSION); ?>"
	    ondblclick="window.open('<?=$content; ?>', '_blank').focus();"
	>
	    <form action="/api/<?=$page; ?>/<?=$id; ?>/subject/<?=str_replace("/", "@", $content); ?>" method="delete" style="z-index: 3;">
		<input type="hidden" name="language" value="<?=$language; ?>" />
		<input type="hidden" name="fbid" value="subject_browser" />
		<input type="button" onclick="
		    if (ShiftPressed || window.confirm())
			silent_submit(this, 'subject_browser<?=$language; ?>');
		"
		value="&#10007;"
		/>
	    </form>
	    <div class="filename" style="z-index: 2;"><?=pathinfo($content, PATHINFO_BASENAME); ?></div>
	</div>
    <?php } ?>
    <?php if (count($entries) == 0) { ?>
	<p style="text-align: center;">
	    <br />
	    <?=$Dictionnary["Empty"]; ?>
	    <br />
	</p>
    <?php } ?>
<?php if ($wrap) { ?>
</div>
<?php } ?>
<?php
    return (ob_get_clean());
}

function GetSubjectDir($id, $data, $method, $output, $module, $msg = "")
{
    if ($id == -1)
	bad_request();

    $language = activity_subject_language($data);
    ($activity = new FullActivity)->build($id);
    $html = render_activity_subject_browser($module, $id, $activity, $language, false);

    $msg = $msg ? ["msg" => $msg] : [];
    return (new ValueResponse(array_merge($msg, [
	"content" => $html
    ])));
}

function SetSubject($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1)
	bad_request();

    $language = activity_subject_language($data);
    ($activity = new FullActivity)->build($id);
    $target = $Configuration->ActivitiesDir($activity->codename, $language == "NA" ? "" : $language);
    new_directory($target);

    if (isset($data["subject"]))
    {
	$file = $data["subject"];
	if ($file[0] == "-")
	    $file = substr($file, 1);
	$file = str_replace("@", "/", $file);
	$allowed_files = array_merge(["configuration.dab"], activity_subject_files());
	if (strncmp($target, $file, strlen($target)) != 0)
	    bad_request();
	if (dirname($file)."/" != $target)
	    bad_request();
	if (!in_array(basename($file), $allowed_files))
	    bad_request();
	if (remove_ressource_file("activity_subject", $id, $file) == false)
	    bad_request();
	return (GetSubjectDir($id, $data, "GET", $output, $module, "Deleted"));
    }

    if (!isset($data["file"]) || count($data["file"]) == 0)
    {
	$kind = isset($data["kind"]) ? $data["kind"] : "";
	if ($kind == "configuration")
	    @unlink($target."configuration.dab");
	else if ($kind == "subject")
	    remove_activity_subject_files($target);
	else
	{
	    remove_activity_subject_files($target);
	    @unlink($target."configuration.dab");
	}
	return (GetSubjectDir($id, $data, "GET", $output, $module, "Deleted"));
    }

    $kind = isset($data["kind"]) ? $data["kind"] : "";
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	$ext = strtolower(pathinfo($files["name"], PATHINFO_EXTENSION));
	$content = base64_decode($files["content"]);
	if ($kind == "configuration")
	{
	    if ($ext != "dab")
		return (new ErrorResponse("InvalidFile", $ext, $Dictionnary["SupportedFormats"].": dab"));
	    file_put_contents($target."configuration.dab", $content);
	}
	else if ($kind == "subject")
	{
	    if (!in_array($ext, ["pdf", "txt", "htm", "html"]))
		return (new ErrorResponse("InvalidFile", $ext, $Dictionnary["SupportedFormats"].": pdf, txt, htm, html"));
	    remove_activity_subject_files($target);
	    if ($ext == "htm")
		$ext = "html";
	    file_put_contents($target."subject.$ext", $content);
	}
	else
	{
	    if ($ext == "dab")
		file_put_contents($target."configuration.dab", $content);
	    else if (in_array($ext, ["pdf", "txt", "htm", "html"]))
	    {
		remove_activity_subject_files($target);
		if ($ext == "htm")
		    $ext = "html";
		file_put_contents($target."subject.$ext", $content);
	    }
	    else
		return (new ErrorResponse("InvalidFile", $ext, $Dictionnary["SupportedFormats"].": pdf, txt, htm, html, dab"));
	}
    }
    return (GetSubjectDir($id, $data, "GET", $output, $module, "Added"));
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

    $nocd = false;
    if (isset($data["nocd"]))
	$nocd = !!$data["nocd"];
    $fbid = "file_browser";
    if (isset($data["fbid"]))
	$fbid = $data["fbid"];
    $locked_path = isset($data["locked_path"]) ? $data["locked_path"] : "";
    $path_browser_can_cd = NULL;
    if (isset($data["path_browser_can_cd"]))
	$path_browser_can_cd = !!$data["path_browser_can_cd"];
    
    $html = get_dir($root, $data["path"], $page, $id, "ressource", $fbid, is_teacher_for_activity($id), $data["language"], $nocd, $locked_path, $path_browser_can_cd);

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
    $page = $module;
    if (!isset($data["path"]))
	$data["path"] = "";
    ($module = new FullActivity)->build($id);
    $path = resolve_path($data["path"]);
    $root = $Configuration->ActivitiesDir($module->codename, $data["language"] == "NA" ? "" : $data["language"])."ressource/";
    $target = resolve_path($root.$path)."/";
    if (strncmp($root, $target, strlen($root)) != 0)
	bad_request();
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if ($files["name"] == "index.php")
	    continue ; // Clairement, non.
	$filename = str_replace(" ", "_", $files["name"]);
	$content = base64_decode($files["content"]);
	new_directory($target);
	file_put_contents($target.$filename, $content);
	$protected_target = escapeshellarg($target.$filename);
	system("chmod 640 $protected_target"); // Surtout si il peut y avoir du script...
    }
    return (GetRessourceDir($id, $data, "GET", $output, $page, "RessourceAdded"));
}

function RemoveRessource($id, $data, $method, $output, $module)
{
    global $Configuration;

    // C'est ressource parceque c'est /api/activity/id/ressource
    if ($id == -1 || !isset($data["ressource"]))
	bad_request();
    if (!isset($data["language"]))
	$data["language"] = "";

    $page = $module;

    // On vérifie que le dossier est bien celui de l'activité demandé...
    ($module = new FullActivity)->build($id);
    $normal_dir = $Configuration->ActivitiesDir($module->codename, $data["language"] == "NA" ? "" : $data["language"])."ressource/";
    $file = $data["ressource"];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("activity_ressource", $id, $file) == false)
	bad_request();
    return (GetRessourceDir($id, $data, "GET", $output, $page, "RessourceDeleted"));
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
    $fbid = isset($data["fbid"]) ? $data["fbid"] : "mood_browser";
    $nocd = isset($data["nocd"]) ? !!$data["nocd"] : false;
    $locked_path = isset($data["locked_path"]) ? $data["locked_path"] : "";
    $path_browser_can_cd = NULL;
    if (isset($data["path_browser_can_cd"]))
	$path_browser_can_cd = !!$data["path_browser_can_cd"];
    
    $page = $module;
    ($activity = new FullActivity)->build($id);
    $root = $Configuration->ActivitiesDir($activity->codename, "")."mood/";
    $html = get_dir($root, $data["path"], $page, $id, "mood", $fbid, is_teacher_for_activity($id), $data["language"], $nocd, $locked_path, $path_browser_can_cd);

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
    $page = $module;
    if (in_array($action = $data["action"], ["wallpaper", "icon", "mood", "intro"]) == false)
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";
    ($activity = new FullActivity)->build($id);
    $path = resolve_path($data["path"]);
    $root = $Configuration->ActivitiesDir($activity->codename, "");
    $mood_root = $root."mood/";
    $target = resolve_path($mood_root.$path)."/";
    if (strncmp($mood_root, $target, strlen($mood_root)) != 0)
	bad_request();
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if ($files["name"] == "index.php")
	    continue ; // Clairement, non.
	$ext = strtolower(pathinfo($files["name"], PATHINFO_EXTENSION));
	$content = base64_decode($files["content"]);
	new_directory($target);
	$tar = NULL;
	if (in_array($ext, ["jpg", "jpeg", "png"]) && ($action == "wallpaper" || $action == "icon"))
	{
	    foreach (["jpg", "jpeg", "png"] as $old_ext)
		@unlink($root.$action.".".$old_ext);
	    $tar = $root.$action.".".$ext;
	}
	else if (in_array($ext, ["mp4", "ogv"]) && $action == "intro")
	{
	    foreach (["mp4", "ogv"] as $old_ext)
		@unlink($root."intro.".$old_ext);
	    $tar = $root."intro.".$ext;
	}
	else if (in_array($ext, ["txt"]) && $action == "mood")
	    $tar = $root."mood/playlist.txt";
	else if (in_array($ext, ["mp3", "ogg"]) && $action == "mood")
	    $tar = $target.basename($files["name"]);
	if ($tar)
	{
	    file_put_contents($tar, $content);
	    $protected_target = escapeshellarg($tar);
	    system("chmod 640 $protected_target"); // Surtout si il peut y avoir du script...
	}
    }
    if ($action == "mood")
	return (GetMoodDir($id, $data, "GET", $output, $page, "MoodAdded"));
    $language = "NA";
    ($activity = new FullActivity)->build($id);
    ob_start();
    if ($action == "wallpaper" && isset($activity->wallpaper[$language][0])) { ?>
    <div
	class="wallpaper_sample" style="background-image: url('<?=$activity->wallpaper[$language][0]; ?>?<?=now(); ?>');"
	ondblclick="window.open('<?=$activity->wallpaper[$language][0]; ?>', '_blank');"
    >
    </div>
<?php }
if ($action == "icon" && isset($activity->icon[$language][0])) { ?>
    <div
	class="wallpaper_sample" style="background-image: url('<?=$activity->icon[$language][0]; ?>?<?=now(); ?>');"
	ondblclick="window.open('<?=$activity->icon[$language][0]; ?>', '_blank');"
    >
    </div>
<?php }
if ($action == "intro" && isset($activity->intro[$language][0])) { ?>
    <video
	controls
	class="wallpaper_sample" src="<?=$activity->intro[$language][0]; ?>?<?=now(); ?>"
	ondblclick="window.open('<?=$activity->intro[$language][0]; ?>', '_blank');"
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
    global $Configuration;

    if ($id == -1 || !isset($data["mood"]))
	bad_request();
    if (!isset($data["language"]))
	$data["language"] = "NA";
    $page = $module;

    // On vérifie que le dossier est bien celui de l'activité demandé...
    ($activity = new FullActivity)->build($id);
    $normal_dir = $Configuration->ActivitiesDir($activity->codename, "")."mood/";
    $file = $data["mood"];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("activity_mood", $id, $file) == false)
	bad_request();
    return (GetMoodDir($id, $data, "GET", $output, $page, "MoodDeleted"));
}

function EditTodoList($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    $data["todolist"] = strip_tags($data["todolist"]);
    $tlist = $Database->real_escape_string($data["todolist"]);
    $Database->query("
	UPDATE activity SET todolist = '$tlist' WHERE id = $id
    ");
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"]
    ]));
}
