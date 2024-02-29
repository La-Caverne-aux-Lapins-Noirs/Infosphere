<?php

class CycleLayer extends Layer
{
    public $LAYER = "CYCLE";
    public $cycle = -1;
    public $done = false;
    public $first_day = NULL;
    public $last_day = NULL;
    public $id_user_cycle = -1;
    public $cursus = []; // Y a t il une spécialité choisi au cursus?
    public $schools = []; // Les école associé au cycle (construit par FullProfile)
    
    // $sublayer sera des module layer
    public function buildsub($user_id, $cycle_id, $blist = [], $only_registered = true)
    {
	global $Language;
	global $Database;
	global $Configuration;
	global $one_week;

	if ($this->first_day)
	{
	    $this->last_day = date_to_timestamp($this->first_day) + 15 * $one_week;
	    $this->last_day = db_form_date($this->last_day);
	}
	if (array_search("cycle_teacher", $blist) === false)
	{
	    /// On récupère les profs du cycle
	    if (@$Configuration->Properties["direction_is_teacher"])
		$teachers = fetch_teacher($cycle_id, true, "cycle");
	    else
		$teachers = [];
	    // Aucune raison de récupérer les profs du cycle -> la direction
	    // n'a rien a voir avec les cours eux meme.
	    $auth = retrieve_authority($teachers);
	    $this->is_teacher = $auth >= TEACHER;
	    $this->is_assistant = $auth >= ASSISTANT;
	}

	if (array_search("cycle_school", $blist) === false)
	{
	    $this->school = db_select_all("
		school.*,
		school.{$Language}_name as name
		FROM school_cycle
		LEFT JOIN school
		ON school.id = school_cycle.id_school
		WHERE school_cycle.id_cycle = $this->id
	    ");
	}

	if (array_search("cycle_module", $blist) === false)
	{
	    ////// ON RECUPERE LES MODULES OU L'ON EST INSCRIT
	    $only_registered = $only_registered ? "
              AND user_team.id_user = $user_id
	    " : "";
	    $modules = db_select_all("
               activity.id,
               activity.{$Language}_name as name,
               activity.{$Language}_description as description,
               activity.codename as codename,
               user_team.id_user as id_user,
	       user_team.commentaries as user_commentaries,
               team.id as id_team,
               team.closed as closed,
               team.commentaries as commentaries,
               template.codename as template_codename,
               activity_cycle.cursus as cursus
               FROM activity_cycle
               LEFT JOIN team ON team.id_activity = activity_cycle.id_activity
               LEFT JOIN user_team ON user_team.id_team = team.id
               LEFT JOIN activity ON activity.id = activity_cycle.id_activity
               LEFT JOIN activity as template ON activity.id_template = template.id
               WHERE activity_cycle.id_cycle = $cycle_id
	         $only_registered
                 AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
                 AND activity.is_template = 0
                 AND activity.deleted IS NULL GROUP BY activity.id
	    ");
	    foreach ($modules as $mod)
	    {
		$module_id = $mod["id"];
		$sub = new ModuleLayer;
		
		$module = new FullActivity;
		if ($module->buildp(
		    $module_id, [
			"recursive" => false,
			"user" => ["id" => $user_id],
			"only_user" => true,
			"blist" => $blist
		]) == false)
		  return (false);
		$fields = [
		    "id", "codename", "name", "description", "credit_a",
		    "credit_b", "credit_c", "credit_d", "hidden",
		    "template_codename", "is_teacher", "closed", "commentaries",
		    "subscription", "grade_a", "grade_b", "grade_c", "grade_d",
		    "grade_bonus", "allow_unregistration",
		    "emergence_date", "done_date", "registration_date",
		    "close_date", "validation"
		];
		transfert($fields, $sub, $module);
		if ($module->user_team)
		{
		    $sub->id_team = $module->user_team["id"];
		    $sub->commentaries = $module->commentaries;
		    if ($module->commentaries != "" && $module->user_commentaries != "")
			$sub->commentaries .= "\n";
		    $sub->commentaries .= $module->user_commentaries;
		    $sub->bonus_grade_a = $module->bonus_grade_a;
		    $sub->bonus_grade_b = $module->bonus_grade_b;
		    $sub->bonus_grade_c = $module->bonus_grade_c;
		    $sub->bonus_grade_d = $module->bonus_grade_d;
		    $sub->bonus_grade_bonus = $module->bonus_grade_bonus;
		}
		$sub->cursus = explode(";", $mod["cursus"]);
		$sub->acquired_credit = 0;
		$sub->load_configuration($module->codename, $module->template_codename);
		$sub->registered = $module->registered;

		// On pré remplie sub en médaille, avant de construire
		foreach ($module->medal as $med)
		{
		    $sub->medal[$med["codename"]] = array_merge($med, [
			"failure" => 0,
			"failure_list" => [],
			"success" => 0,
			"success_list" => [],
			"local_sum" => 0
		    ], isset($sub->medal[$med["codename"]]) ? $sub->medal[$med["codename"]] : []);
		    $sub->medal[$med["codename"]]["module_medal"] = true;
		}
		$sub->buildsub($user_id, $module_id, $blist);
		$sub->full_activity = $module;
		$this->sublayer[$sub->codename] = $sub;
	    }
	}
	return (true);
    }
}
