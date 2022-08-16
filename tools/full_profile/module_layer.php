<?php

class ModuleLayer extends Layer
{
    public $LAYER = "MODULE";
    public $mandatory_percent = 0;
    public $current_percent = 0;
    public $grade = -1;
    public $hidden = false;
    public $manual_grade = NULL;
    public $manual_credit = NULL;
    public $grade_a = 85;
    public $grade_b = 70;
    public $grade_c = 60;
    public $grade_d = 50;
    public $grade_bonus = 75;
    public $grade_module = 0;
    public $no_grade = 0;
    public $validation_by_percent = false;
    public $old_validation = false;
    public $configuration = [];
    public $allow_unregistration;
    public $emergence_date;
    public $done_date;
    public $registration_date;
    public $close_date;

    // Les scores de la validation principale
    public $valid_grade_a = 0;
    public $valid_grade_b = 0;
    public $valid_grade_c = 0;
    public $valid_grade_d = 0;
    public $valid_grade_e = 0;

    public $id_team = -1; // Le lien module-utilisateur.

    // $sublayer sera des activity layer
    public function buildsub($user_id, $module_id, $blist = [])
    {
	global $Language;

	$activities = db_select_all("
           activity.id, activity.codename,
           session.id as id_session,
           team.commentaries as commentaries,
           team.manual_credit as manual_credit,
           team.manual_grade as manual_grade
           FROM activity
           LEFT JOIN team ON team.id_activity = activity.id
           LEFT JOIN session ON session.id = team.id_session
           WHERE activity.parent_activity = $module_id
             AND activity.deleted = 0
           GROUP BY activity.id
	");
	foreach ($activities as $act)
	{
	    $sub = new ActivityLayer;
	    $activity_id = $act["id"];
	    $sub->id_session = $act["id_session"];
	    $activity = new FullActivity;
	    $only_user = array_search("only_user", $blist) !== false;
	    $activity->build($activity_id, false, false, $sub->id_session, NULL, ["id" => $user_id], false, $only_user);
	    transfert(["id", "codename", "name", "description", "registered", "hidden", "subject_appeir_date", "pickup_date", "type", "is_teacher"], $sub, $activity);
	    $sub->credit = 0;
	    $sub->acquired_credit = 0;
	    $sub->commentaries = $act["commentaries"];
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
		    $sub->medal[$med["codename"]]["module_medal"] = false;
	    }

	    // On récupère les médailles acquises
	    $acquired = db_select_all("
               activity.{$Language}_name as activity_name,
               activity_user_medal.id_activity as id_activity,
               activity_user_medal.result as result,
               medal.id as id,
               medal.codename as codename,
               medal.{$Language}_name as name,
               medal.{$Language}_description as description,
               medal.icon as icon,
               medal.type as type,
               activity_medal.mandatory as mandatory,
               activity_medal.local as local,
               activity_medal.grade_a as grade_a,
               activity_medal.grade_b as grade_b,
               activity_medal.grade_c as grade_c,
               activity_medal.bonus as bonus,
               activity_medal.role as role
               FROM user_medal
               LEFT JOIN activity_user_medal ON user_medal.id = activity_user_medal.id_user_medal
               LEFT JOIN activity ON activity.id = activity_user_medal.id_activity
               LEFT JOIN medal ON medal.id = user_medal.id_medal
               LEFT JOIN activity_medal ON activity_medal.id_activity = activity.id
                                        AND activity_medal.id_medal = medal.id
               WHERE user_medal.id_user = $user_id
                 AND (activity_user_medal.id_activity = $activity_id
                  OR activity_user_medal.id_activity = $module_id)
	       ");
	    foreach ($acquired as $med)
	    {
		if ($med["id_activity"] == $module_id)
		    $target = &$this;
		else
		    $target = &$sub;

		// Si c'est une note, elle est forcement locale
		if (is_note($med["codename"]))
		    $med["local"] = true;

		if (!isset($target->medal[$med["codename"]]))
		{
		    $target->medal[$med["codename"]] = array_merge($med, [
			"failure" => 0,
			"failure_list" => [],
			"success" => 0,
			"success_list" => [],
			"local_sum" => 0
		    ]);
		    unset($target->medal[$med["codename"]]["result"]);
		    unset($target->medal[$med["codename"]]["activity_name"]);
		}
		if ($med["result"] == -1)
		{
		    $target->medal[$med["codename"]]["failure"] += 1;
		    $target->medal[$med["codename"]]["failure_list"][] = $med["activity_name"];
		}
		else if ($med["result"] == 1)
		{
		    $target->medal[$med["codename"]]["success"] += 1;
		    $target->medal[$med["codename"]]["success_list"][] = $med["activity_name"];
		    if ($med["local"] == 1)
			$target->medal[$med["codename"]]["local_sum"] += 1;
		}
		if (!isset($target->medal[$med["codename"]]["module_medal"]))
		    $target->medal[$med["codename"]]["module_medal"] = false;
	    }

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
