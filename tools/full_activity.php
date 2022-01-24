<?php

class FullActivity extends Response
{
    public $id_template = -1;
    public $is_template = false;
    public $template_link = true;
    public $template_codename = NULL;
    public $enabled;

    public $id;
    public $codename;
    public $deleted;
    public $type = 0;
    public $type_name = "";
    public $hidden = 0;
    public $parent_activity = -1;
    public $reference_activity = -1;
    public $reference_codename = NULL;
    public $reference_name = "";
    public $parent_codename = NULL;
    public $parent_name = NULL;
    public $mandatory = 0;
    public $name = "";
    public $description = "";
    public $team_size = -1;
    public $allow_unregistration = 0;
    public $maximum_subscription = -1;
    public $full = false;
    public $current_occupation = -1;
    public $credit = -1;
    public $mark = 0;
    public $repository_name = "";
    public $reference_repository = "";
    public $grade_a = 85;
    public $grade_b = 70;
    public $grade_c = 60;
    public $grade_d = 50;
    public $grade_bonus = 75;
    public $grade_module = false;
    public $validation_by_percent = false;
    public $old_validation = false;
    public $no_grade = false;
    public $subscription = 0;
    public $slot_duration = -1;
    public $estimated_work_duration = 0;
    public $configuration = NULL;
    public $subject = NULL;
    public $ressources = NULL;
    public $wallpaper = NULL;
    public $syllabus;

    public $fr_name;
    public $fr_description;

    public $emergence_date = NULL;
    public $done_date = NULL;
    public $registration_date = NULL;
    public $close_date = NULL;
    public $subject_appeir_date = NULL;
    public $subject_disappeir_date = NULL;
    public $pickup_date = NULL;

    public $teacher = [];
    public $medal = [];
    public $note = false;
    public $class = [];
    public $cycle = [];
    public $team = [];
    public $nbr_students = 0;
    public $can_subscribe = false;
    public $registered = false;
    public $user_team = NULL;
    public $leader = false;
    public $session_registered = -1;
    public $registered_elsewhere = false;
    public $is_teacher = false;
    public $is_assistant = false;

    public $evaluation = NULL;
    public $unique_session = NULL;
    public $session = [];
    public $subactivities = [];

    // Pour le calendrier... ne pas s'en servir
    public $local_start;
    public $local_end;
    public $top;
    public $height;
    public $left;
    public $width;

    public function build($activity_id, $deleted = false, $recursive = true, $session_id = -1, $module = NULL, $user = NULL, $get_medal = false, $only_user = false)
    {
	global $User;
	global $Language;
	global $LanguageList;

	if (($ret = resolve_codename("activity", $activity_id))->is_error())
	    return (false);
	$activity_id = $ret->value;

	if ($user == NULL)
	    $user = $User;
	$this->value = &$this;

	// On commence par les propriétés principales de l'activité
	// Les elements supprimés peuvent etre collecté
	$data = db_select_one("
           activity.*,
           activity.{$Language}_name as name,
           activity.{$Language}_description as description,
           activity.validation_by_percent as validation_by_percent,
           activity.old_validation as old_validation,
           activity.no_grade as no_grade,
           parent.id_template as parent_id_template,
           parent.codename as parent_codename,
           parent.{$Language}_name as parent_name,
           template.codename as template_codename,
           template.{$Language}_name as template_name,
           activity_type.codename as type_name
           FROM activity
           LEFT JOIN activity as parent ON activity.parent_activity = parent.id
           LEFT JOIN activity as template ON activity.id_template = template.id
           LEFT JOIN activity_type ON activity_type.id = activity.type
           WHERE activity.id = $activity_id ".(!$deleted ? "AND activity.deleted = 0" : "")."
	   ");
	if ($data == NULL || !isset($data["id"]))
	    return (false);
	if ($data["parent_name"] == NULL && $data["parent_id_template"] != NULL)
	{
	    $data["parent_name"] = db_select_one("
               {$Language}_name as name
               FROM activity
               WHERE id = {$data["parent_id_template"]}
	       ")["name"];
	}
	$datefields = [
	    "emergence_date", "done_date", "registration_date",
	    "close_date", "subject_appeir_date", "subject_disappeir_date",
	    "pickup_date"
	];
	templated_fill("activity", $data, $datefields);
	$fields = [
	    "id", "codename", "type", "type_name", "hidden", "parent_activity", "reference_activity", "parent_codename",
	    "mandatory", "name", "description", "team_size", "allow_unregistration", "credit", "mark",
	    "subscription", "slot_duration", "estimated_work_duration", "configuration", "subject", "emergence_date", "done_date", "registration_date",
	    "close_date", "subject_appeir_date", "subject_disappeir_date", "pickup_date", "id_template",
	    "is_template", "template_link", "template_codename", "enabled", "deleted", "parent_name",
	    "maximum_subscription", "validation_by_percent", "no_grade", "old_validation", "grade_a", "grade_b", "grade_c", "grade_d", "grade_module", "grade_bonus", "repository_name", "reference_repository",
	];
	foreach ($LanguageList as $k => $v)
	{
	    $fields[] = $k."_name";
	    $fields[] = $k."_description";
	    $fields[] = $k."_syllabus";
	}
	transfert($fields, $this, $data);
	// Juste au cas ou...
	$this->id = $activity_id;
	$this->name = $data[$Language."_name"];
	$this->description = $data[$Language."_description"];

	if (file_exists("./dres/activity/".$this->codename."/ressources/"))
	    $this->ressources = "./dres/activity/".$this->codename."/ressources/";
	else if (file_exists("./dres/activity/".$this->template_codename."/ressources/"))
	    $this->ressources = "./dres/activity/".$this->template_codename."/ressources/";

	if (file_exists("./dres/activity/".$this->codename."/subject.pdf"))
	    $this->subject = "./dres/activity/".$this->codename."/subject.pdf";
	else if (file_exists("./dres/activity/".$this->template_codename."/subject.pdf"))
	    $this->subject = "./dres/activity/".$this->template_codename."/subject.pdf";

	if (file_exists("./dres/activity/".$this->codename."/subject.htm"))
	    $this->subject = "./dres/activity/".$this->codename."/subject.htm";
	else if (file_exists("./dres/activity/".$this->template_codename."/subject.htm"))
	    $this->subject = "./dres/activity/".$this->template_codename."/subject.htm";

	if (file_exists("./dres/activity/".$this->codename."/".$Language."_syllabus.txt"))
	    $this->syllabus = "./dres/activity/".$this->codename."/".$Language."_syllabus.txt";
	else if (file_exists("./dres/activity/".$this->template_codename."/".$Language."_syllabus.txt"))
	    $this->syllabus = "./dres/activity/".$this->template_codename."/".$Language."_syllabus.txt";

	if (file_exists("./dres/activity/".$this->codename."/wallpaper.png"))
	    $this->wallpaper = "./dres/activity/".$this->codename."/wallpaper.png";
	else if (file_exists("./dres/activity/".$this->template_codename."/wallpaper.png"))
	    $this->wallpaper = "./dres/activity/".$this->template_codename."/wallpaper.png";

	to_timestamp($this->emergence_date);
	to_timestamp($this->done_date);
	to_timestamp($this->registration_date);
	to_timestamp($this->close_date);
	to_timestamp($this->subject_appeir_date);
	to_timestamp($this->subject_disappeir_date);
	to_timestamp($this->pickup_date);

	// On récupère tout ce qui est lié a l'activité d'une manière ou d'une autre
	if ($this->id_template != -1 && $this->template_link)
	{
	    if ($data["medal_template"])
		$this->medal = fetch_activity_medal($this->id_template, true);
	    if ($data["class_template"])
		$this->class = fetch_activity_class($this->id_template);
	}

	// ON A UNE ACTIVITE DE REFERENCE
	if ($this->reference_activity != -1)
	{
	    // L'activité de référence pourra etre enrichie ensuite sur les médailles,
	    // les professeurs et les supports de cours... mais pas sur les équipes.
	    $this->reference_codename = db_select_one("codename, {$Language}_name as name FROM activity WHERE id = $this->reference_activity");
	    $this->reference_name = $this->reference_codename["name"];
	    $this->reference_codename = $this->reference_codename["codename"];
	    $this->medal = array_merge($this->medal, fetch_activity_medal($this->reference_activity, true));
	    $this->teacher = array_merge($this->teacher, fetch_teacher($this->reference_activity, true));
	    $this->class = array_merge($this->class, fetch_activity_class($this->reference_activity));
	    // Les fameuses équipes. Inscrit en projet == inscrit en soutenance!
	    $team_pool_id = $this->reference_activity;
	}
	else
	    $team_pool_id = $activity_id;

	$this->medal = array_merge($this->medal, fetch_activity_medal($data["id"], true));
	$this->teacher = array_merge($this->teacher, fetch_teacher($data["id"], true));
	$this->class = array_merge($this->class, fetch_activity_class($data["id"]));

	$this->cycle = fetch_activity_cycle($data["id"], true);
	$auth = retrieve_authority($this->teacher);
	$this->is_teacher = $auth >= TEACHER;
	$this->is_assistant = $auth >= ASSISTANT;

	/// On regarde les succes de l'utilisateur a cette activité
	foreach ($this->medal as &$med)
	{
	    $got = db_select_one("
		activity_user_medal.result as result,
                activity_medal.mandatory as parent_mandatory,
                activity_medal.local as local,
                activity_medal.grade_a as grade_a,
                activity_medal.grade_b as grade_b,
                activity_medal.grade_c as grade_c,
                activity_medal.bonus as bonus,
                activity_medal.mandatory as mandatory
		FROM activity_user_medal
                LEFT JOIN user_medal ON activity_user_medal.id_user_medal = user_medal.id
                LEFT JOIN activity_medal ON activity_user_medal.id_activity = activity_medal.id_activity
                WHERE user_medal.id_user = {$user["id"]}
                  AND user_medal.id_medal = {$med["id"]}
                  AND activity_user_medal.id_activity = $this->id
		  ");
	    foreach (["result", "parent_mandatory"/*, "local", "mandatory"*/] as $f)
	    {
		$med[$f] = $got[$f];
	    }
	}

	// On regarde les propriétés du module parent...
	if ($this->parent_activity != -1)
	{
	    /// L'activité appartient a un module qui a ses cycles propres - et ses profs
	    $this->cycle = array_merge($this->cycle, fetch_activity_cycle($this->parent_activity, true));
	    $this->teacher = array_merge($this->teacher, fetch_teacher($this->parent_activity, true));
	    $acyc = db_select_all("
                 id_cycle FROM activity_cycle
                 WHERE id_activity = {$this->parent_activity} OR id_activity = {$this->id}
	    ");
	}
	else
	{
	    $acyc = db_select_all("
                 id_cycle FROM activity_cycle
                 WHERE id_activity = {$this->id}
	    ");
	}
	foreach ($acyc as $ac)
	{
	    $this->teacher = array_merge($this->teacher, fetch_teacher($ac, true, "cycle"));
	}

	$auth = retrieve_authority($this->teacher);
	$this->is_teacher = $this->is_teacher || $auth >= TEACHER;
	$this->is_assistant = $this->is_assistant || $auth >= ASSISTANT;

	/// Equipe
	if ($only_user && $user)
	{
	    $limit = " AND user_team.id_user = {$user["id"]} ";
	    $selteam = " LEFT JOIN user_team ON team.id = user_team.id_team ";
	}
	else
	{
	    $limit = "";
	    $selteam = "";
	}
	if ($user != NULL || $only_user == false)
	{
	    $this->team = db_select_all("
               team.*
               FROM team
               $selteam
               WHERE team.id_activity = $team_pool_id
               $limit
	    ");
	    foreach ($this->team as &$team)
	    {
		$team["user"] = db_select_all("
                  user.codename as codename,
                  user.nickname as nickname,
                  user.id as id,
                  user.avatar as avatar,
                  user.photo as photo,
                  user.visibility as visibility,
                  user_team.status as status,
                  user_team.commentaries as commentaries
                  FROM user_team
                  LEFT JOIN user ON user_team.id_user = user.id
                  WHERE user_team.id_team = {$team["id"]} $limit
		  ", "id");
		$this->nbr_students += count($team["user"]);
		$team["work"] = db_select_all("
                  *
                  FROM pickedup_work
                  WHERE id_team = {$team["id"]}
                  ORDER BY pickedup_date DESC
		  ");
		foreach ($team["user"] as &$u)
		{
		    if ($u["status"] == 2)
			$team["leader"] = &$u;
		}

		if ($this->registered == false)
		{
		    $leader = NULL;
		    foreach ($team["user"] as $uu)
		    {
			if ($uu["status"] == 2)
			    $leader = $uu;
			if ($uu["id"] == $user["id"])
			{
			    $this->user_team = $team;
			    $this->leader = $uu["status"];
			    $this->registered = true;
			    break ;
			}
		    }
		    if ($this->user_team == $team)
			$this->user_team["leader"] = $leader;
		}

		if ($get_medal)
		{
		    foreach ($team["user"] as &$usr)
		    {
			$usr["medal"] = [];
			if ($usr["visibility"] < SUCCESSFUL_ACTIVITIES && $this->is_teacher == false)
			    continue ;
			$mod = new ModuleLayer;
			$mod->buildsub($usr["id"], $this->id);
			$mod->sublayer = NULL;
			$mod->medal = [];
			foreach ($this->medal as $medx)
			{
			    $m = db_select_one("
   			      activity_user_medal.result as result
                              FROM user_medal LEFT JOIN medal ON user_medal.id_medal = medal.id
                              LEFT JOIN activity_user_medal ON user_medal.id = activity_user_medal.id_user_medal
                              WHERE user_medal.id_user = {$usr["id"]}
                              AND user_medal.id_medal = {$medx["id"]}
                              AND activity_user_medal.result = 1
                              ORDER BY medal.codename ASC
			   ");
			    if ($m != NULL)
			    {
				$medx["result"] = $m["result"];
				$medx["success"] = 1;
			    }
			    else
			    {
				$medx["result"] = 0;
				$medx["success"] = 0;
			    }
			    $medx["module_medal"] = true;
			    $usr["medal"][] = $medx;
			    $mod->medal[] = $medx;
			}
			$prf = new Fullprofile;
			$refid = $this->reference_activity;
			$tmp = db_select_one("
                           activity.codename, parent.codename as template_codename
                           FROM activity LEFT JOIN activity as parent ON parent.id = activity.id_template
                           WHERE activity.id = $this->id
			");
			$mod->load_configuration($tmp["codename"], $tmp["template_codename"]);
			$prf->validate_this_module($mod);
			$usr["grade"] = $mod->grade;
			$usr["percent"] = $mod->current_percent;
		    }
		}
	    }
	}

	$this->current_occupation = $this->team_size * count($this->team);
	if ($this->unique_session)
	    $this->full = $this->unique_session->full;
	else
	    $this->full = !in_limit($this->current_occupation, $this->maximum_subscription);

	// Si on est pas sur un module.
	if ($this->type != 18)
	{
	    $session_id = (int)$session_id;
	    if ($session_id != -1)
		$ses = " AND id = $session_id ";
	    else
		$ses = "";
	    $all = db_select_all(
		"* FROM session WHERE id_activity = $activity_id $ses ".
		(!$deleted ? " AND deleted = 0" : "")
	    );
	    foreach ($all as $a)
	    {
		$ses = new FullSession;
		$ses->build($a, $this, NULL, $only_user);
		if ($ses->registered)
		    $this->session_registered = $ses->id;
		$this->session[] = $ses;
	    }
	    if (count($this->session))
	    {
		$this->unique_session = &$this->session[0];
		if ($this->unique_session->id != $this->session_registered && $this->session_registered != -1)
		    $this->registered_elsewhere = true;
	    }
	}

	// On établis les equipes des sessions qui dépendent d'un projet
	$tem = [];
	foreach ($this->team as &$tt)
	    $tem[$tt["id"]] = &$tt;
	foreach ($this->session as &$sess)
	{
	    foreach ($sess->slot as &$slot)
	    {
		if ($slot["id_team"] <= 0)
		    continue ;
		if (isset($tem[$slot["id_team"]]))
		{
		    $slot["team"] = $tem[$slot["id_team"]];
		    $tt["slot"] = true;
		}
	    }
	    $sess->team = $this->team;
	}

	get_user_promotions($user);
	foreach ($user["cycle"] as $cycle)
	{
	    if (isset($this->cycle[$cycle["codename"]]))
	    {
		$this->can_subscribe = true;
		break ;
	    }
	}

	// Si on ne veut pas les sous activités...
	if ($recursive == false)
	    return (true);

	// On termine en chargeant toutes les sous activités
	$subs = db_select_all("
	    id
	    FROM activity
	    WHERE parent_activity = $activity_id ".(!$deleted ? " AND activity.deleted = 0 " : "")."
            ORDER BY close_date ASC, pickup_date ASC, codename ASC
	    ");
	foreach ($subs as $sub)
	{
	    $new = new FullActivity;
	    if ($new->build
		($sub["id"], $deleted, $recursive, $session_id, $this, NULL, NULL, $only_user))
		$this->subactivities[$new->codename] = $new;
	}
	return (true);
    }
}
