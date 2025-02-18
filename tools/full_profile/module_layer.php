<?php

class ModuleLayer extends Layer
{
    public $LAYER = "MODULE";

    public $mandatory_percent = 0;
    public $current_percent = 0;
    public $grade = -1;
    public $hidden = false;

    public $maximum_subscription = -1;
    public $manual_grade = NULL;
    public $manual_credit = NULL;
    public $grade_a = 85;
    public $grade_b = 70;
    public $grade_c = 60;
    public $grade_d = 50;
    public $grade_bonus = 75;    
    public $validation = FullActivity::RANK_VALIDATION;
    
    public $configuration = [];
    
    public $allow_unregistration = true;
    public $emergence_date = NULL;
    public $done_date = NULL;
    public $registration_date = NULL;
    public $close_date = NULL;
    public $type_type = MODULE_OR_PICKABLE; // Const
    public $subscription = FullActivity::MANUAL_SUBSCRIPTION;

    // Les scores de la validation principale
    public $valid_grade_a = 0;
    public $valid_grade_b = 0;
    public $valid_grade_c = 0;
    public $valid_grade_d = 0;
    public $valid_grade_e = 0;

    public $credit_a = 0;
    public $credit_b = 0;
    public $credit_c = 0;
    public $credit_d = 0;
    public $credit = [];

    public $bonus_grade_a = 0;
    public $bonus_grade_b = 0;
    public $bonus_grade_c = 0;
    public $bonus_grade_d = 0;
    public $bonus_grade_bonus = 0;
    
    public $id_team = -1; // Le lien module-utilisateur.
    public $cursus = []; // Matiere obligatoire pour certains cursus
    public $registered = false;

    public $full_activity = NULL;
    
    public function get_credit()
    {
	if ($this->done_date == NULL || $this->done_date > now())
	    return (0);
	if ($this->grade > 4)
	    return ($this->credit[4]);
	return ($this->credit[$this->grade]);
    }
    
    // $sublayer sera des activity layer
    public function buildsub($user_id, $module_id, $blist = [])
    {
	global $Language;
	global $Configuration;

	$this->credit[0] = 0;
	$this->credit[1] = &$this->credit_d;
	$this->credit[2] = &$this->credit_c;
	$this->credit[3] = &$this->credit_b;
	$this->credit[4] = &$this->credit_a;

	$activities = db_select_all("
           activity.id, activity.codename,
           session.id as id_session,
           team.id as id_team,
           team.manual_credit as manual_credit,
           team.manual_grade as manual_grade,
           activity_type.type as type_type
           FROM activity
           LEFT JOIN activity_type ON activity.type = activity_type.id
           LEFT JOIN team ON team.id_activity = activity.id
           LEFT JOIN session ON session.id = team.id_session
           WHERE activity.parent_activity = $module_id
             AND activity.deleted IS NULL
           GROUP BY activity.id
	");
	$fake = false;
	if (count($activities) == 0)
	{
	    $activities[] = [
		"id" => $module_id,
		"codename" => $this->codename,
		"id_session" => -1,
		"commentaries" => "",
		"manual_credit" => NULL,
		"manual_grade" => NULL,
		"type_type" => 0
	    ];
	    $fake = true;
	}
	foreach ($activities as $act)
	{
	    $sub = new ActivityLayer;
	    $activity_id = $act["id"];
	    $sub->id_session = $act["id_session"];
	    $activity = new FullActivity;
	    $only_user = array_search("only_user", $blist) !== false;
	    if ($activity->buildp(
		$activity_id, [
		    "recursive" => false,
		    "session_id" => $sub->id_session,
		    "user" => ["id" => $user_id],
		    "only_user" => $only_user,
		    "blist" => $blist
	    ]) == false)
	        return (false);
	    
	    transfert(["id", "codename", "name", "description", "registered", "subscription", "maximum_subscription", "hidden", "subject_appeir_date", "pickup_date", "type", "is_teacher"], $sub, $activity);
	    $sub->credit = 0;
	    $sub->acquired_credit = 0;
	    $sub->commentaries = "";
	    if ($activity->commentaries)
		$sub->commentaries .= $activity->commentaries["content"]."\n";
	    if ($activity->user_commentaries)
		$sub->commentaries .= $activity->user_commentaries["content"];
	    $sub->manual_grade = $act["manual_grade"];
	    $sub->manual_credit = $act["manual_credit"];
	    transfert(["begin_date", "end_date"], $sub, $activity->unique_session);

	    if ($activity->unique_session)
	    {
		$sub->begin_date = $activity->unique_session->begin_date;
		$sub->end_date = $activity->unique_session->end_date;
	    }

	    // Medailles officiellement disponibles
	    foreach ($activity->medal as $med)
	    {
		$sub->medal[$med["codename"]] = array_merge($med, [
		    "failure" => 0,
		    "failure_list" => [],
		    "success" => 0,
		    "success_list" => [],
		    "local_sum" => 0
		]);
		if (!isset($sub->medal[$med["codename"]]["module_medal"]))
		{
		    $sub->medal[$med["codename"]]["module_medal"] = true; // false
		    $sub->medal[$med["codename"]]["role"] = 0;
		}
	    }

	    if (array_search("activity_acquired_medal", $blist) === false)
	    {
		// On récupère les médailles acquises
		$acquired = db_select_all("
                   activity.{$Language}_name as activity_name,
                   activity.codename as activity_codename,
                   user_medal.id_activity as id_activity,
                   user_medal.result as result,
                   user_medal.strength as strength,
                   user_medal.id_team as id_team,
                   user_medal.id_user_team as id_user_team,
                   medal.id as id,
                   medal.codename as codename,
		   medal.command as command,
                   medal.{$Language}_name as name,
                   medal.{$Language}_description as description,
                   medal.type as type,
                   activity_medal.local as local,
                   activity_medal.mark as mark,
                   activity_medal.role as role
                   FROM user_medal
                   LEFT JOIN activity ON activity.id = user_medal.id_activity
                   LEFT JOIN medal ON medal.id = user_medal.id_medal
                   LEFT JOIN activity_medal
			ON activity_medal.id_activity = activity.id
                        AND activity_medal.id_medal = medal.id
                   WHERE user_medal.id_user = $user_id
                     AND (user_medal.id_activity = $activity_id
                     OR user_medal.id_activity = $module_id
                   ) AND medal.deleted IS NULL
		");
		$eliminatory = false;
		foreach ($acquired as $med)
		    if ($med["type"] == 2) // Eliminatoire
			$eliminatory = true;

		foreach ($acquired as $med)
		{
		    $med["icon"] = $Configuration->MedalsDir($med["codename"])."icon.png";
		    if (!file_exists($med["icon"]))
			$med["icon"] = NULL;
		    $med["band"] = $Configuration->MedalsDir($med["codename"])."band.png";
		    if (!file_exists($med["band"]))
			$med["band"] = NULL;
		    
		    if ($med["id_activity"] == $module_id)
			$target = &$this;
		    else
			$target = &$sub;

		    // Si c'est une note, elle est forcement locale
		    if (is_note($med["codename"])) //  && $this->validation == FullActivity::GRADE_VALIDATION)
			$med["local"] = true;

		    if (!isset($target->medal[$med["codename"]]))
		    {
			$target->medal[$med["codename"]] = array_merge($med, [
			    "failure" => 0,
			    "failure_list" => [],
			    "success" => 0,
			    "success_list" => [],
			    "local_sum" => 0,
			]);
			unset($target->medal[$med["codename"]]["result"]);
			unset($target->medal[$med["codename"]]["activity_name"]);
		    }
		    if ($med["result"] == -1 || $eliminatory)
		    {
			$target->medal[$med["codename"]]["failure"] += 1;
			$target->medal[$med["codename"]]["failure_list"][] =
			    ($med["activity_name"] != "" ? $med["activity_name"] : $med["activity_codename"]);
		    }
		    else if ($med["result"] == 1)
		    {
			if (!isset($target->medal[$med["codename"]]["strength"]) ||
			    $target->medal[$med["codename"]]["strength"] < $med["strength"])
			    $target->medal[$med["codename"]]["strength"] = $med["strength"];
			$target->medal[$med["codename"]]["success"] += 1;
			$msg =
			    ($med["activity_name"] != "" ?
			     $med["activity_name"] : $med["activity_codename"]
			    ).
			    ($med["local"] ?
			     " (L) " : ""
			    ).
			    ($med["strength"] != 2 ?
			     ("[".["<<", "<", "-", ">", ">>"][$med["strength"]]."]") : ""
			    );
			$target->medal[$med["codename"]]["success_list"][] = $msg;
			if ($med["local"])
			    $target->medal[$med["codename"]]["local_sum"] += 1;
		    }
		    // Si on a gagné une médaille mais qu'elle était pas spécialement
		    // prévue, c'est pas une "module medal"
		    if (!isset($target->medal[$med["codename"]]["module_medal"]))
			$target->medal[$med["codename"]]["module_medal"] = false;
		    // Médaille éliminatoire directement sur la matiere
		    // La matière est perdue, et la medaille doit etre affichée
		    // clairement
		    if ($med["id_activity"] == $module_id && $med["type"] == 2)
			$target->medal[$med["codename"]]["eliminatory"] = true;
		}
	    }

	    if (array_search("activity_presence", $blist) === false)
	    {
		// Presence et absences
		if ($sub->registered && $activity->unique_session != NULL)
		{
		    if ($activity->unique_session->end_date != NULL &&
			date_to_timestamp($activity->unique_session->end_date) < time())
		    {
			if ($activity->user_team["present"] == -2)
			    $sub->missing->add($activity->unique_session->begin_date, 1);
			else if ($activity->user_team["present"] == -1)
			{
			    $sub->late->add($activity->unique_session->begin_date, 1);
			    if ($activity->user_team["declaration_date"] != NULL)
			    {
				$diff = date_to_timestamp($activity->user_team["declaration_date"])
				      - date_to_timestamp($activity->unique_session->begin_date);
				$sub->cumulated_late->add($activity->unique_session->begin_date, $diff);
			    }
			}
			else if ($activity->user_team["present"] == 1)
			    $sub->present->add($activity->unique_session->begin_date, 1);
		    }
		}
	    }

	    if (array_search("activity_delivery", $blist) === false)
	    {
		// Rendu et pas rendu
		if ($activity->user_team != NULL && $activity->pickup_date != NULL && date_to_timestamp($activity->pickup_date) < time())
		{
		    if (count($activity->user_team["work"]) == 0)
			$sub->nowork->add($activity->pickup_date, 1);
		    else
		    {
			$sub->work->add($activity->pickup_date, 1);
			$sub->archive = db_select_one("
                           repository, pickedup_date FROM pickedup_work
                           WHERE id_team = {$activity->user_team["id"]}
                           ORDER BY pickedup_date DESC
			   ");
			$sub->pickedup_date = $sub->archive["pickedup_date"];
			$sub->archive = $sub->archive["repository"];
		    }
		}
	    }

	    $sub->full_activity = $activity;
	    if ($fake == false)
		$this->sublayer[] = $sub;
	}
    }

    function load_configuration($codename, $template_codename)
    {
	if (file_exists("./dres/activity/$codename/configuration.dab"))
	    $cnf_file = "./dres/activity/$codename/configuration.dab";
	else if (file_exists("./dres/activity/$template_codename/configuration.dab"))
	    $cnf_file = "./dres/activity/$template_codename/configuration.dab";
	else
	    return ;
	if (!($tmp = load_configuration($cnf_file, [], true))->is_error())
	    $this->configuration = $tmp->value;
    }
}

