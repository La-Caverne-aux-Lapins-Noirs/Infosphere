<?php

class CycleLayer extends Layer
{
    public $LAYER = "CYCLE";
    public $cycle = -1;
    public $done = false;
    public $first_day = NULL;
    public $last_day = NULL;
    public $id_user_cycle = -1;
    
    // $sublayer sera des module layer
    public function buildsub($user_id, $cycle_id, $blist = [])
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

	////// ON RECUPERE LES MODULES OU L'ON EST INSCRIT
	$modules = db_select_all("
           activity.id,
           user_team.id_user as id_user,
           team.id as id_team,
           team.closed as closed,
           team.commentaries as commentaries,
           template.codename as template_codename
           FROM activity_cycle
           LEFT JOIN team ON team.id_activity = activity_cycle.id_activity
           LEFT JOIN user_team ON user_team.id_team = team.id
           LEFT JOIN activity ON activity.id = activity_cycle.id_activity
           LEFT JOIN activity as template ON activity.id_template = template.id
           WHERE activity_cycle.id_cycle = $cycle_id
	     AND user_team.id_user = $user_id
             AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
             AND activity.is_template = 0
           AND activity.deleted IS NULL GROUP BY activity.id
	");
	foreach ($modules as $mod)
	{
	    $module_id = $mod["id"];
	    $sub = new ModuleLayer;

	    $module = new FullActivity;
	    if ($module->build($module_id, false, false, -1, NULL, ["id" => $user_id], false, true) == false)
		return (false);
	    $fields = [
		"id", "codename", "name", "description", "credit_a", "credit_b", "credit_c", "credit_d", "hidden", "template_codename",
		"is_teacher", "closed", "commentaries", "validation_by_percent", "old_validation", "subscription",
		"grade_a", "grade_b", "grade_c", "grade_d", "grade_bonus", "grade_module", "allow_unregistration", "no_grade",
		"emergence_date", "done_date", "registration_date", "close_date", "validation"
	    ];
	    transfert($fields, $sub, $module);
	    if ($module->user_team)
	    {
		$sub->id_team = $module->user_team["id"];
		$sub->commentaries = $module->user_team["commentaries"];
	    }
	    $sub->acquired_credit = 0;
	    $sub->load_configuration($module->codename, $module->template_codename);
	    $sub->registered = $module->registered;
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
	    $this->sublayer[$sub->codename] = $sub;
	}
	return (true);
    }
}
