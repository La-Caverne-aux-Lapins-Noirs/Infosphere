<?php

function select_right_elem(&$vis, $field)
{
    global $Language;

    $cfield = "current_$field";
    if (isset($vis->$field[$Language][0]))
	$vis->$cfield = $vis->$field[$Language][0];
    else if (isset($vis->$field["NA"][0]))
	$vis->$cfield = $vis->$field["NA"][0];
    else
	$vis->$cfield = NULL;
    return ($vis->$cfield);
}

class FullActivity extends Response
{
    public $id_template = -1;
    public $is_template = false;
    public $template_link = true;
    public $medal_template = false;
    public $support_template = false;
    public $template_codename = NULL;
    public $template_type = NULL;
    public $enabled;

    public $id;
    public $codename;
    public $deleted;
    public $type = 0;
    public $type_name = "";
    public $type_type = ""; // 2: activité en salle. 1: travaux. 0: autre (module et exercices seuls)
    public $hidden = 0;
    public $parent_activity = -1;
    public $reference_activity = -1;
    public $reference_codename = NULL;
    public $reference_name = "";
    public $reference_type = 0;
    public $parent_codename = NULL;
    public $parent_name = NULL;
    public $mandatory = 0;
    public $name = "";
    public $description = "";
    public $min_team_size = -1;
    public $max_team_size = -1;
    public $allow_unregistration = 0;
    public $maximum_subscription = -1;
    public $full = false;
    public $current_occupation = -1;
    public $credit_a = -1;
    public $credit_b = -1;
    public $credit_c = -1;
    public $credit_d = -1;
    public $credit = [];
    public $mark = 0;
    public $repository_name = "";
    public $repositories = [];
    
    public $grade_a = 85;
    public $grade_b = 70;
    public $grade_c = 60;
    public $grade_d = 50;
    public $grade_bonus = 75;

    const RANK_VALIDATION = 3;
    const GRADE_VALIDATION = 2;
    const PERCENT_VALIDATION = 1;
    const NO_VALODATION = 0;
    public $validation = FullActivity::RANK_VALIDATION;

    const MANUAL_SUBSCRIPTION = 0;
    const MANDATORY_SUBSCRIPTION = 1;
    const AUTOMATIC_SUBSCRIPTION = 2;
    public $subscription = FullActivity::MANUAL_SUBSCRIPTION;

    public $slot_duration = -1;
    public $estimated_work_duration = 0;
    public $configuration = NULL;
    public $current_configuration = NULL;
    public $subject = NULL;
    public $current_subject = NULL;
    public $ressource = NULL;
    public $current_ressource = NULL;
    public $music = NULL;
    public $wallpaper = NULL; // Collection de papiers peints, tous langages
    public $current_wallpaper;
    public $intro = NULL;
    public $syllabus;

    // Il faudra changer ca lors d'une prochaine maj.
    public $fr_name;
    public $fr_description;
    public $fr_objective;
    public $fr_method;
    public $fr_reference;

    public $en_name;
    public $en_description;
    public $en_objective;
    public $en_method;
    public $en_reference;
    // Parceque c'est vraiment de la merde comme fonctionnement
    
    public $emergence_date = NULL;
    public $done_date = NULL;
    public $registration_date = NULL;
    public $close_date = NULL;
    public $subject_appeir_date = NULL;
    public $subject_disappeir_date = NULL;
    public $pickup_date = NULL;

    public $teacher = [];
    public $director = [];
    public $medal = [];
    public $note = false;
    public $support = [];
    public $cycle = [];
    public $scale = [];
    public $mcq = [];
    public $satisfaction = [];
    public $team = [];
    public $nbr_students = 0;
    public $can_subscribe = false;
    public $registered = false;
    public $user_team = NULL;
    public $code = NULL;
    public $leader = false;
    public $session_registered = NULL;
    public $registered_elsewhere = false;
    public $is_teacher = false;
    public $is_assistant = false;
    public $is_director = false;

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
           activity.{$Language}_method as method,
           activity.{$Language}_objective as objective,
           activity.{$Language}_reference as reference,
           activity.validation as validation,
           parent.id_template as parent_id_template,
           parent.codename as parent_codename,
           parent.{$Language}_name as parent_name,
           template.codename as template_codename,
           template.{$Language}_name as template_name,
           template.type as template_type,
           activity_type.codename as type_name,
           activity_type.type as type_type
           FROM activity
           LEFT JOIN activity as parent ON activity.parent_activity = parent.id
           LEFT JOIN activity as template ON activity.id_template = template.id
           LEFT JOIN activity_type ON activity_type.id = activity.type
           WHERE activity.id = $activity_id ".(!$deleted ? "AND activity.deleted IS NULL" : "")."
	   ");
	if ($data == NULL || !isset($data["id"]))
	    return (false);
	if ($data["parent_name"] == NULL && $data["parent_id_template"] != NULL && $data["parent_id_template"] != -1)
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
	    "id", "codename", "type", "type_name", "type_type", "hidden", "parent_activity", "reference_activity", "parent_codename",
	    "mandatory", "name", "description", "objective", "method", "reference", "min_team_size", "max_team_size", "allow_unregistration", "credit_a", "credit_b", "credit_c", "credit_d", "mark",
	    "subscription", "slot_duration", "estimated_work_duration", "configuration", "subject", "emergence_date", "done_date", "registration_date",
	    "close_date", "subject_appeir_date", "subject_disappeir_date", "pickup_date", "id_template",
	    "is_template", "template_link", "medal_template", "support_template", "template_codename", "deleted", "parent_name",
	    "maximum_subscription", "validation", "grade_a", "grade_b", "grade_c", "grade_d", "grade_bonus", "repository_name"
	];
	foreach ($LanguageList as $k => $v)
	{
	    $fields[] = $k."_name";
	    $fields[] = $k."_description";
	    $fields[] = $k."_objective";
	    $fields[] = $k."_method";
	    $fields[] = $k."_reference";
	    $fields[] = $k."_syllabus";
	}
	transfert($fields, $this, $data);
	// Juste au cas ou...
	$this->id = $activity_id;
	$this->name = $data[$Language."_name"];
	$this->enabled = $data["disabled"] === NULL;
	$this->description = $data[$Language."_description"];
	$this->credit = [
	    0 => 0,
	    1 => $this->credit_d,
	    2 => $this->credit_c,
	    3 => $this->credit_b,
	    4 => $this->credit_a
	];

	$this->repositories = db_select_all("* FROM activity_software WHERE id_activity = $this->id ORDER BY type");
	global $Configuration;

	foreach ([
	    "ressource/" => &$this->ressource,
	    "mood/" => &$this->music,
	    "subject.pdf" => &$this->subject,
	    "subject.htm" => &$this->subject,
	    "syllabus.dab" => &$this->syllabus,
	    "wallpaper.png" => &$this->wallpaper,
	    "wallpaper.jpeg" => &$this->wallpaper,
	    "wallpaper.jpg" => &$this->wallpaper,
	    "intro.mp4" => &$this->intro,
	    "intro.ogv" => &$this->intro,
	    "configuration.dab" => &$this->configuration
	] as $target => &$fafields)
	{
	    if ($fafields === NULL)
		$fafields = [];
	    $LngList = array_merge(["NA" => "NA"], $LanguageList);
	    foreach ($LngList as $lng => $unused)
	    {
		if (!isset($fafields[$lng]))
		    $fafields[$lng] = [];

		// On cherche dans l'instance avec son langage et sans langage
		if (file_exists($tmp = $Configuration->ActivitiesDir($this->codename, $lng == "NA" ? "" : $lng).$target))
		{
		    $fafields[$lng][] = $tmp;
		}
	    
		if (!$this->template_link)
		    continue ;
		if (file_exists($tmp = $Configuration->ActivitiesDir($this->template_codename, $lng == "NA" ? "" : $lng).$target))
		    $fafields[$lng][] = $tmp;
	    }
	}

	to_timestamp($this->emergence_date);
	to_timestamp($this->done_date);
	to_timestamp($this->registration_date);
	to_timestamp($this->close_date);
	to_timestamp($this->subject_appeir_date);
	to_timestamp($this->subject_disappeir_date);
	to_timestamp($this->pickup_date);

	select_right_elem($this, "wallpaper");
	select_right_elem($this, "ressource");
	select_right_elem($this, "intro");
	select_right_elem($this, "subject");
	select_right_elem($this, "configuration");

	if ($this->current_configuration !== NULL && file_exists($this->current_configuration))
	{
	    if (($out = generate_subject($this->current_configuration, $this)) != NULL)
		$this->current_subject = $out;
	    else
		$this->current_configuration = NULL;
	}
	
	$this->medal = [];
	$this->support = [];
	$this->teacher = [];
	
	// On récupère tout ce qui est lié a l'activité d'une manière ou d'une autre
	if ($this->id_template != -1 && $this->template_link)
	{
	    if ($data["medal_template"])
		$this->medal = array_merge($this->medal, fetch_activity_medal($this->id_template, true, $this->template_type));
	    if ($data["support_template"])
		$this->support = array_merge($this->support, fetch_activity_support($this->id_template));
	}

	// ON A UNE ACTIVITE DE REFERENCE
	if ($this->reference_activity != -1)
	{
	    // L'activité de référence pourra etre enrichie ensuite sur les médailles,
	    // les professeurs et les supports de cours... mais pas sur les équipes.
	    $this->reference_codename = db_select_one("codename, type, {$Language}_name as name FROM activity WHERE id = $this->reference_activity");
	    $this->reference_name = $this->reference_codename["name"];
	    $this->type = $this->reference_codename["type"];
	    $this->reference_codename = $this->reference_codename["codename"];
	    $this->medal = array_merge($this->medal, fetch_activity_medal($this->reference_activity, true, $this->reference_type));
	    $this->teacher = array_merge($this->teacher, fetch_teacher($this->reference_activity, true));
	    $this->support = array_merge($this->support, fetch_activity_support($this->reference_activity));
	    // Les fameuses équipes. Inscrit en projet == inscrit en soutenance!
	    $team_pool_id = $this->reference_activity;
	}
	else
	    $team_pool_id = $activity_id;

	foreach ($this->medal as &$md) $md["ref"] = true;
	foreach ($this->teacher as &$md) $md["ref"] = true;
	foreach ($this->support as &$md) $md["ref"] = true;

	$this->medal = array_merge($this->medal, fetch_activity_medal($data["id"], true, $this->type));
	$this->teacher = array_merge($this->teacher, fetch_teacher($data["id"], true));
	$this->support = array_merge($this->support, fetch_activity_support($data["id"]));

	foreach ($this->medal as &$md) if (!isset($md["ref"])) $md["ref"] = false;
	foreach ($this->teacher as &$md) if (!isset($md["ref"])) $md["ref"] = false;
	foreach ($this->support as &$md) if (!isset($md["ref"])) $md["ref"] = false;

	$this->skill = fetch_link("activity", "skill", $data["id"], true, ["description"])->value;
	$this->cycle = fetch_link("activity", "cycle", $data["id"], true, ["name"])->value;
	$this->scale = fetch_link("activity", "scale", $data["id"], true, ["name", "content"], " AND type = 0 ORDER BY chapter ")->value;
	$this->mcq = fetch_link("activity", "scale", $data["id"], true, ["name", "content"], " AND type = 1 ORDER BY chapter ")->value;
	$this->satisfaction = fetch_link("activity", "scale", $data["id"], true, ["name", "content"], " AND type = 2 ORDER BY chapter ")->value;

	foreach (["scale" => &$this->scale, "mcq" => &$this->mcq, "satisfaction" => &$this->satisfaction] as $k => &$tmpscale)
	{
	    foreach ($tmpscale as &$tmp_scale)
	    {
		$tmp_scale["id_$k"] = $tmp_scale["id_scale"];
	    }
	}
	
	$auth = retrieve_authority($this->teacher);
	$this->is_teacher = $auth >= TEACHER;
	$this->is_assistant = $auth >= ASSISTANT;

	/// On regarde les succes de l'utilisateur a cette activité
	foreach ($this->medal as $k => $med)
	{
	    $got = db_select_one("
		activity_user_medal.result as result,
                activity_medal.role as role,
                activity_medal.local as local
		FROM activity_user_medal
                LEFT JOIN user_medal ON activity_user_medal.id_user_medal = user_medal.id
                LEFT JOIN activity_medal ON activity_user_medal.id_activity = activity_medal.id_activity
                WHERE user_medal.id_user = {$user["id"]}
                  AND user_medal.id_medal = {$med["id"]}
                  AND activity_user_medal.id_activity = $this->id
		  ");
	    if ($got != NULL)
		foreach (["result"] as $f)
		    $this->medal[$k][$f] = $got[$f];
	}

	// On regarde les propriétés du module parent...
	if ($this->parent_activity != -1)
	{
	    /// L'activité appartient a un module qui a ses cycles propres - et ses profs
	    $parent = fetch_link("activity", "cycle", $this->parent_activity, true, ["name"])->value;
	    foreach ($parent as &$_p)
		$_p["inherit"] = true;
	    $this->cycle = array_merge(
		$this->cycle,
		$parent
	    );
	    // fetch_teacher parceque le branchement a deux possibilités.
	    $teacher = fetch_teacher($this->parent_activity, true);
	    foreach ($teacher as &$_t)
		$_t["inherit"] = true;
	    $this->teacher = array_merge(
		$this->teacher,
		$teacher
	    );
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
	if (@$Configuration->Properties["direction_is_teacher"])
	    foreach ($acyc as $ac)
		$this->teacher = array_merge($this->teacher, fetch_teacher($ac, true, "cycle"));

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
                  user.visibility as visibility,
                  user_team.status as status,
                  user_team.commentaries as commentaries,
                  user_team.code as code
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

	$this->current_occupation = $this->max_team_size * count($this->team);
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
		(!$deleted ? " AND deleted IS NULL" : "")
	    );
	    foreach ($all as $a)
	    {
		$ses = new FullSession;
		$ses->build($a, $this, NULL, $only_user);
		if ($ses->registered)
		    $this->session_registered = $ses;
		$this->session[] = $ses;
	    }
	    if (count($this->session))
	    {
		$this->unique_session = &$this->session[0];
		if ($this->session_registered != NULL
		    && $this->unique_session->id != $this->session_registered->id)
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
	if ($this->parent_activity == -1)
	{
	    foreach ($user["cycle"] as $cycle)
	    {
		if (isset($this->cycle[$cycle["codename"]]))
		{
		    $this->can_subscribe = true;
		    break ;
		}
	    }
	}
	else
	{
	    $topsub = db_select_one("
              * FROM team LEFT JOIN user_team ON team.id = user_team.id_team
              WHERE user_team.id_user = {$user["id"]}
              AND team.id_activity = $this->parent_activity
	      ");
	    if ($topsub)
		$this->can_subscribe = true;
	}

	// Si on ne veut pas les sous activités...
	if ($recursive == false)
	    return (true);

	// On termine en chargeant toutes les sous activités
	$subs = db_select_all("
	    id
	    FROM activity
	    WHERE parent_activity = $activity_id ".(!$deleted ? " AND activity.deleted IS NULL " : "")."
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
