<?php

$LoadedProfiles = [];

function sort_year_month($a, $b)
{
    $aa = explode("/", $a);
    $bb = explode("/", $b);

    if (($ret = strcmp($aa[1], $bb[1])) != 0)
	return ($ret);
    return (strcmp($aa[0], $bb[0]));
}

function sort_year_month_reverse($a, $b)
{
    $aa = explode("/", $a);
    $bb = explode("/", $b);

    if (($ret = strcmp($bb[1], $aa[1])) != 0)
	return ($ret);
    return (strcmp($bb[0], $aa[0]));
}

class FullProfile extends Layer
{
    public $LAYER = "TOP";
    public $codename;
    public $mail;
    public $nickname = "";
    public $first_name = "";
    public $family_name = "";
    public $birth_date = "";
    public $phone = "";
    public $address_name = "";
    public $street_name = "";
    public $postal_code = "";
    public $city = "";
    public $country = "";
    public $registration_date;
    public $visibility = 0;
    public $money = 0;
    public $credit = 0;
    public $group;
    public $managed_activities;
    public $functions = [];

    // Les cycles réunis en fonction de leur date de départ
    public $merged_sublayers = [];
    
    // Ces médailles sont pour les accomplissements personnels
    // et ne sont pas scolaires.
    public $medals = [];

    // Le détails des autorités
    public $authority = USER;
    public $administrator = false;
    public $content_author = false; // Accès aux templates et aux médailles
    public $teacher = false; // Accès aux instances + administration locale matiere
    public $assistant = false; // Administration locale minime matiere
    public $director = false; // Création instance, création cycle, modification cycle

    // Utilitaire minimaliste pour récupérer l'autorité d'un user sans tout charger
    function build_authority($user = NULL)
    {
	global $User;
	
	if ($user == NULL)
	    $user = $User;
	if ($user == NULL)
	    return ;
	$usr = @db_select_one("authority FROM user WHERE id = {$user["id"]}")["authority"];
	if ($usr)
	{
	    if ($usr == ADMINISTRATOR)
	    {
		$this->administrator =
		    $this->content_author =
			$this->teacher =
			    $this->assistant =
				$this->director = true;
		return ;
	    }
	    if ($usr == DIRECTOR)
		$this->director = true;
	}
	list_of_managed_activities($this);
    }

    function set_grade(&$mod, $val)
    {
	if ($val < $mod->grade_d / 100.0)
	    $mod->grade = 0;
	else if ($val < $mod->grade_c / 100.0)
	    $mod->grade = 1;
	else if ($val < $mod->grade_b / 100.0)
	    $mod->grade = 2;
	else if ($val < $mod->grade_a / 100.0)
	    $mod->grade = 3;
	else
	    $mod->grade = 4;
    }

    // Le sublayer sera des cycle layer
    function validate_this_module(&$mod)
    {
	if ($mod->validation == FullActivity::NO_VALIDATION)
	    return ;
	
	// On regarde si c'est un module a note. C'est la moyenne qui compte.
	if ($mod->validation == FullActivity::GRADE_VALIDATION)
	{
	    $avg = 0;
	    $not = 0;
	    foreach ($mod->medal as $medal)
	    {
		if (is_note($medal["codename"]))
		{
		    $avg += atoi(substr($medal["codename"], strlen("token")));
		    $not += 1;
		}
	    }
	    $this->set_grade($mod, $avg / $not);
	    return ;
	}

	// Est un module a pourcentage total ? C'est le pourcentage d'acquisition qui compte
	if ($mod->validation == FullActivity::PERCENT_VALIDATION)
	{
	    $acquired_medal = 0;
	    $medal_count = 0;
	    foreach ($mod->medal as $medal)
	    {
		if ($medal["module_medal"] == false)
		    continue ;
		if ($medal["success"] > 0)
		    $acquired_medal += 1;
		$medal_count += 1;
	    }
	    $mod->current_percent = $tmp = $acquired_medal / $medal_count;
	    $this->set_grade($mod, $tmp);
	    return ;
	}

	// Méthode de validation définitive
	// Il y a des médailles obligatoires pour chaque grade
	// les médailles bonus permettent d'avoir un bonus de grade
	// TOUTES les médailles accessible dans le module contribuent a ce pourcentage
	// Validation par tranche de médailles
	$medala = 0;
	$medalb = 0;
	$medalc = 0;
	$medald = 0;
	$medale = 0;

	$medala_cnt = 0;
	$medalb_cnt = 0;
	$medalc_cnt = 0;
	$medald_cnt = 0;
	$medale_cnt = 0;
	foreach ($mod->medal as $medal)
	{
	    if ($medal["role"] == 4)
	    {
		if ($medal["success"] > 0)
		    $medala += 1;
		$medala_cnt += 1;
	    }
	    else if ($medal["role"] == 3)
	    {
		if ($medal["success"] > 0)
		    $medalb += 1;
		$medalb_cnt += 1;
	    }
	    else if ($medal["role"] == 2)
	    {
		if ($medal["success"] > 0)
		    $medalc += 1;
		$medalc_cnt += 1;
	    }
	    else if ($medal["module_medal"] && $medal["role"] == 1)
	    {
		if ($medal["success"] > 0)
		    $medald += 1;
		$medald_cnt += 1;
	    }
	    else // role == 0
	    { // Médailles optionnelles - seulement les positives
		if ($medal["type"] != 0)
		    continue ;
		if ($medal["success"] > 0)
		    $medale += 1;
		$medale_cnt += 1;
	    }
	}

	$mod->grade = 0;
	if (count($mod->medal) == 0)
	    return ;
	
	// Si on on a deja le grade D, on a le droit au grade C
	// si on a deja le grade C, on a le droit au grade B, etc.
	if ($medald_cnt == 0 || $medald / $medald_cnt >= $mod->grade_d / 100.0)
	{
	    $mod->grade += 1;
	    if ($medalc_cnt == 0 || $medalc / $medalc_cnt >= $mod->grade_c / 100.0)
	    {
		$mod->grade += 1;
		if ($medalb_cnt == 0 || $medalb / $medalb_cnt >= $mod->grade_b / 100.0)
		{
		    $mod->grade += 1;
		    if ($medala_cnt == 0 || $medala / $medala_cnt >= $mod->grade_a / 100.0)
		    {
			$mod->grade += 1;
		    }
		}
	    }
	}

	// Par tranche de "grade_bonus", on obtient un grade supplémentaire.
	// Ce grade ne permet pas de revenir d'un grade echec.
	if ($mod->grade != 0 && $medale_cnt)
	    $mod->grade += ((int)(100 * ($medale / $medale_cnt))) >= ((int)$mod->grade_bonus) ? 1 : 0;
	
	if ($medala_cnt)
	    $mod->valid_grade_a = 100 * $medala / $medala_cnt;
	if ($medalb_cnt)
	    $mod->valid_grade_b = 100 * $medalb / $medalb_cnt;
	if ($medalc_cnt)
	    $mod->valid_grade_c = 100 * $medalc / $medalc_cnt;
	if ($medald_cnt)
	    $mod->valid_grade_d = 100 * $medald / $medald_cnt;
	if ($medale_cnt)
	    $mod->valid_grade_e = 100 * $medale / $medale_cnt;
    }

    function validate_modules()
    {
	$total_credit = [];
	$total_available_credit = [];
	foreach ($this->sublayer as &$cycle)
	{
	    $total = 0;
	    $max = 0;
	    $grade = [];
	    $grade_cnt = 0;
	    foreach ($cycle->sublayer as &$module)
	    {
		if ($module->hidden)
		    continue ;
		$this->validate_this_module($module);
		if ($cycle->done && $module->grade > 0)
		{
		    $module->acquired_credit = 0;
		    for ($i = 1; $i <= $module->grade && $i < 4; ++$i)
			$module->acquired_credit += $module->credit[$i];
		    if ($module->manual_credit != NULL)
			$module->acquired_credit = $module->manual_credit;
		}
		$total += $module->acquired_credit;
		$max += $module->credit[$module->grade > 4 ? 4 : $module->grade];
		$grade[] = $module->grade;
		$grade_cnt += 1;
	    }
	    $cycle->success = $total > 30;
	    if ($grade_cnt != 0)
		$cycle->grade = array_sum($grade) / $grade_cnt;
	    else
		$cycle->grade = -1;

	    if (!isset($total_credit[$cycle->cycle]) || $total_credit[$cycle->cycle] < $total)
		$total_credit[$cycle->cycle] = $total;
	    if (!isset($total_available_credit[$cycle->cycle]) || $total_available_credit[$cycle->cycle] < $max)
		$total_available_credit[$cycle->cycle] = $max;

	    $cycle->credit = $max;
	    $cycle->acquired_credit = $total;

	}
	foreach ($total_credit as $c)
	{
	    $this->acquired_credit += $c;
	}
	foreach ($total_available_credit as $c)
	{
	    $this->credit += $c;
	}
    }

    // Si on a une medaille de module quelque part, et que
    // celle ci n'est pas locale, elle sera appliquée partout
    function share_medals()
    {
	foreach ($this->sublayer as &$cycle)
	{
	    foreach ($cycle->sublayer as &$module)
	    {
		foreach ($module->medal as &$medal)
		{
		    $original_role = $medal["role"];
		    $original_module_medal = $medal["module_medal"];
		    
		    if ($medal["module_medal"] && $medal["local"] == false)
		    {
			$global_medal = $this->medal[$medal["codename"]];

			if ($global_medal["local_sum"] < $global_medal["success"])
			{
			    $medal = $global_medal;
			}
		    }
		    
		    $medal["module_medal"] = $original_module_medal;
		    $medal["role"] = $original_role;
		}
	    }
	}
    }

    public function build($user_id, $blist = [], $only_registered = true)
    {
	global $Language;
	global $Configuration;

	$fields = [
	    "id",
	    "codename",
	    "nickname",
	    "mail",
	    "registration_date",
	    "first_name",
	    "family_name",
	    "phone",
	    "address_name",
	    "street_name",
	    "postal_code",
	    "city",
	    "country",
	    "birth_date",
	    "authority",
	    "visibility"
	];

	if (($data = fetch_user($user_id))->is_error())
	    return (false);
	$data = $data->value;
	if (array_search("profile", $blist) === false)
	{
	    foreach ($fields as $label)
	    {
		$this->$label = $data[$label];
	    }
	}

	if (array_search("module", $blist) === false)
	{
	    foreach ($data["cycle"] as $cycle)
	    {
		$l = new CycleLayer;
		foreach (["id", "codename", "done", "cycle", "first_day", "commentaries", "hidden", "id_user_cycle", "cursus"] as $label)
		    $l->$label = $cycle[$label];
		$l->id = $cycle["id_cycle"];
		$l->buildsub($user_id, $cycle["id_cycle"], $blist, $only_registered);
		$this->sublayer[] = $l;
	    }
	}

	if (array_search("school", $blist) === false)
	{
	    $this->school = db_select_all("
	        school.id as id_school,
		school.codename as codename,
		school.{$Language}_name as name,
	        user_school.authority as authority,
	        user_school.id as id
	        FROM school
	        LEFT JOIN user_school
	        ON user_school.id_school = school.id
	        WHERE user_school.id_user = $user_id AND school.deleted IS NULL
		");
	}

	if (array_search("laboratory", $blist) === false)
	{
	    $this->group = db_select_all("
               laboratory.id,
               laboratory.codename,
	       user_laboratory.authority,
               laboratory.{$Language}_name as name,
               laboratory.{$Language}_description as description
               FROM user_laboratory
               LEFT JOIN laboratory ON laboratory.id = user_laboratory.id_laboratory
               WHERE user_laboratory.id_user = $user_id
                 AND deleted IS NULL
	    ");
	}

	if (array_search("teacher", $blist) === false)
	    $this->managed_activities = list_of_managed_activities($this);

	$this->retrieve();
	$this->share_medals();
	$this->validate_modules();

	$this->medals = db_select_all("
           medal.*,
           medal.{$Language}_name as name,
           medal.{$Language}_description as description
           FROM user_medal
           LEFT JOIN activity_user_medal
           ON user_medal.id = activity_user_medal.id_user_medal
           LEFT JOIN medal
           ON medal.id = user_medal.id_medal AND medal.deleted IS NULL
           WHERE user_medal.id_user = {$this->id}
           AND activity_user_medal.id_activity = -1
           AND activity_user_medal.result = 1
	");
	foreach ($this->medals as &$medx)
	{
	    $medx["success"] = 1;
	    $medx["failure"] = 0;
	    $medx["mandatory"] = false;
	    $medx["local"] = false;
	}

	foreach ($this->sublayer as $cycle)
	{
	    foreach ($cycle->sublayer as $module)
	    {
		if ($module->hidden)
		    continue ;
		foreach ($module->sublayer as $act)
		{
		    foreach ($act->medal as $med)
		    {
			if (is_note($med["codename"])
			    || !isset($med["id"])
			    || $med["success"] <= 0)
			    continue ;
			$tmp = db_select_all("
                          function.codename
                          FROM function_medal
                          LEFT JOIN function ON function_medal.id_function = function.id
                          WHERE function_medal.id_medal = {$med["id"]}
			  ");
			foreach ($tmp as $t)
			{
			    $this->functions[$t["codename"]] = $t["codename"];
			}
		    }
		}
	    }
	}
	
	foreach ($this->sublayer as $l) // Parcours des cycles
	{
	    // On identifie les cycles regroupés du fait de leur date
	    $fd = datex("m/y", $l->first_day);
	    if (!isset($this->merged_sublayers[$fd]))
	    {
		$this->merged_sublayers[$fd] = $l;
		$this->merged_sublayers[$fd]->cursus =
		    array_merge($this->merged_sublayers[$fd]->cursus, $l->cursus);
		$this->merged_sublayers[$fd]->cursus =
		    array_unique($this->merged_sublayers[$fd]->cursus);
		$this->merged_sublayers[$fd]->cycles = [$l->id];
		$this->merged_sublayers[$fd]->matters = [];
	    }
	    else
		$this->merged_sublayers[$fd]->cycles[] = $l->id;

	    // On parcoure les modules du cycle qu'on range dans la case
	    // mergeante par date de debut
	    foreach ($l->sublayer as $matt)
		$this->merged_sublayers[$fd]->matters[$matt->codename] = $matt;
	    uksort($this->merged_sublayers, "sort_year_month");
	}
	return (true);
    }
}
