<?php

class FullProfile extends Layer
{
    public $LAYER = "TOP";
    public $codename;
    public $mail;
    public $registration_date;
    public $phone = "";
    public $nickname = "";
    public $first_name = "";
    public $family_name = "";
    public $birth_date = "";
    public $visibility = 0;
    public $money = 0;
    public $credit = 0;
    public $group;
    public $managed_activities;
    public $functions = [];

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
	$usr = db_select_all("
	    laboratory.id_user as idu, user_laboratory.authority as aut,
            activity.is_template as tem
            FROM activity_teacher
	    LEFT JOIN laboratory ON laboratory.id = activity_teacher.id_laboratory
            LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
            LEFT JOIN activity ON activity_teacher.id_activity = activity.id
	    WHERE id_user = {$user["id"]} OR user_laboratory = {$user["id"]}
	    ");
	foreach ($usr as $u)
	{
	    if ($u["idu"] == $user["id"])
	    {
		if ($u["tem"])
		    $this->content_author = true;
		else
		    $this->teacher = true;
	    }
	    else if ($u["aut"] == 2 || $u["aut"] == 3)
		$this->teacher = true;
	    else if ($u["aut"] == 1)
		$this->assistant = true;
	}
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
	// On regarde si c'est un module a note. C'est la moyenne qui compte.
	if ($mod->grade_module)
	{
	    $avg = 0;
	    $not = 0;
	    foreach ($mod->medal as $medal)
	    {
		if (is_note($medal["codename"]))
		{
		    // En dur mour l'instant...
		    $avg += (int)substr($medal["codename"], 4);
		    $not += 1;
		}
	    }
	    $this->set_grade($mod, $avg / $not);
	    return ;
	}

	// Est un module a pourcentage total ? C'est le pourcentage d'acquisition qui compte
	if ($mod->validation_by_percent)
	{
	    foreach ($mod->medal as $medal)
	    {
		if ($medal["module_medal"] == false)
		    continue ;
		if ($medal["success"] > 0)
		    $acquired_medal += 1;
		$medal_count += 1;
	    }
	    $tmp = $acquired_medal / $medal_count;
	    set_grade($mod, $tmp * 100);
	    return ;
	}

	// Méthode de validation définitive
	// Il y a des médailles obligatoires pour chaque grade
	// les médailles bonus permettent d'avoir un bonus de grade
	// TOUTES les médailles accessible dans le module contribuent a ce pourcentage
	if ($mod->old_validation == false)
	{
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
		if ($medal["grade_a"])
		{
		    if ($medal["success"] > 0)
			$medala += 1;
		    $medala_cnt += 1;
		}
		else if ($medal["grade_b"])
		{
		    if ($medal["success"] > 0)
			$medalb += 1;
		    $medalb_cnt += 1;
		}
		else if ($medal["grade_c"])
		{
		    if ($medal["success"] > 0)
			$medalc += 1;
		    $medalc_cnt += 1;
		}
		else if ($medal["module_medal"] && $medal["bonus"] == 0)
		{
		    if ($medal["success"] > 0)
			$medald += 1;
		    $medald_cnt += 1;
		}
		else
		{ // Médailles optionnelles - seulement les positives
		    if ($medal["type"] != 0)
			continue ;
		    if ($medal["success"] > 0)
			$medale += 1;
		    $medale_cnt += 1;
		}
	    }

	    // Si on on a deja le grade D, on a le droit au grade C
	    // si on a deja le grade C, on a le droit au grade B, etc.
	    $mod->grade = 0;
	    if ($medald_cnt > 0 && $medald / $medald_cnt >= $mod->grade_d / 100.0)
	    {
		$mod->grade += 1;
		if ($medalc_cnt > 0 && $medalc / $medalc_cnt >= $mod->grade_c / 100.0)
		{
		    $mod->grade += 1;
		    if ($medalb_cnt > 0 && $medalb / $medalb_cnt >= $mod->grade_b / 100.0)
		    {
			$mod->grade += 1;
			if ($medala_cnt > 0 && $medala / $medala_cnt >= $mod->grade_a / 100.0)
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
	    return ;
	}

	//////////////////////////////////////////////////////////////////////////////
	// La validation a l'ancienne. Ne devrait plus être utilisé.                //
	// Il n'y a pas moyen d'ailleurs de définir ce champ a 1 via le formulaire. //
	//////////////////////////////////////////////////////////////////////////////

	$mandatory_count = 0;
	$mandatory = 0;
	$acquired_medal = 0;
	$medal_count = 0;
	$mandatory_percent = 0;
	$final_percent = 0;
	foreach ($mod->medal as $medal)
	{
	    if ($medal["module_medal"] == false)
		continue ;
	    if ($medal["success"] > 0)
	    {
		if ($medal["mandatory"])
		    $mandatory += 1;
		$acquired_medal += 1;
	    }
	    if ($medal["mandatory"])
		$mandatory_count += 1;

	    $medal_count += 1;
	}
	if ($medal_count != 0)
	{
	    $mod->mandatory_percent = $mandatory_count / $medal_count;
	    $mod->grade_c = $mod->mandatory_percent;
	    $mod->current_percent = $acquired_medal / $medal_count;
	}
	else
	{
	    $mod->mandatory_percent = 0;
	    $mod->current_percent = 0;
	}
	if ($mandatory >= $mandatory_count) // Toutes les obligatoires sont acquises
	{
	    // Il y avait des médailles obligatoires
	    if ($mandatory_count != 0)
	    {
		if ($mod->current_percent >= $mod->grade_a / 100.0)
		    $mod->grade = 4; // A
		else if ($mod->current_percent >= $mod->grade_b / 100.0)
		    $mod->grade = 3; // B
		else
		    $mod->grade = 2; // C
	    }
	    else // Il n'y en avait pas, donc en fait, c'est un "validation by percent"
		$this->set_grade($mod, $mod->current_percent);
	}
	else // Il manque des médailles obligatoires...
	{
	    if ($mod->current_percent >= $mod->grade_d / 100.0)
		$mod->grade = 1; // D, repéché
	    else
		$mod->grade = 0; // Echec
	}
	if ($mod->manual_grade != NULL)
	    $mod->grade = $mod->manual_grade;
	return ;
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
		for ($i = 1; $i <= $module->grade && $i < 4; ++$i)
		    $max += $module->credit[$i];
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
		    $original_mandatory = $medal["mandatory"];
		    $original_grade_a = $medal["grade_a"];
		    $original_grade_b = $medal["grade_b"];
		    $original_grade_c = $medal["grade_c"];
		    $original_bonus = $medal["bonus"];
		    $original_mandatory = $medal["mandatory"];
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
		    $medal["mandatory"] = $original_mandatory;
		    $medal["grade_a"] = $original_grade_a;
		    $medal["grade_b"] = $original_grade_b;
		    $medal["grade_c"] = $original_grade_c;
		    $medal["bonus"] = $original_bonus;
		}
	    }
	}
    }

    public function build($user_id, $blist = [])
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
	/* si c'est encore commenté alors que tu relis ca, c'est que tu peux le virer
	if (file_exists($Configuration->UsersDir."/".$this->codename."/avatar.png"))
	    $this->avatar = $Configuration->UsersDir."/".$this->codename."/avatar.png";
	if (file_exists($Configuration->UsersDir."/".$this->codename."/photo.png"))
	    $this->photo = $Configuration->UsersDir."/".$this->codename."/photo.png";
	 */
	if (array_search("module", $blist) === false)
	{
	    foreach ($data["cycle"] as $cycle)
	    {
		$l = new CycleLayer;
		foreach (["id", "codename", "done", "cycle", "first_day", "bonus_credit", "commentaries", "hidden", "id_user_cycle"] as $label)
		{
		    $l->$label = $cycle[$label];
		}
		$l->buildsub($user_id, $cycle["id"], $blist);
		$this->sublayer[] = $l;
	    }
	}

	if (array_search("laboratory", $blist) === false)
	{
	    $this->group = db_select_all("
               laboratory.codename,
               laboratory.icon,
               laboratory.{$Language}_name,
               laboratory.{$Language}_description
               FROM user_laboratory
               LEFT JOIN laboratory ON laboratory.id = user_laboratory.id_laboratory
               WHERE user_laboratory.id_user = $user_id
                 AND deleted = 0
	    ");
	}

	if (array_search("teacher", $blist) === false)
	{
	    $this->managed_activities = [];
	    $managed = db_select_all("
                activity.*, activity.{$Language}_name as name,
                parent_activity.codename as parent_codename
                FROM activity_teacher
                LEFT JOIN activity ON activity_teacher.id_activity = activity.id
                LEFT JOIN activity as parent_activity ON activity.parent_activity = parent_activity.id
                LEFT JOIN laboratory ON activity_teacher.id_laboratory = laboratory.id
                LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
                WHERE ( activity_teacher.id_user = $user_id OR user_laboratory.id_user = $user_id )
	    ");
	    foreach ($managed as $man)
	    {
		if ($man["type"] == 18)
		{
		    $new = new FullActivity;
		    $new->build($man["id"]);
		    $this->managed_activities[$man["codename"]] = $new;
		}
		else
		{
		    // Fetch the module
		    if (!isset($this->managed_activities[$man["parent_codename"]]))
		    {
			$new = new FullActivity;
			$new->build($man["parent_activity"], false, false);
			$this->managed_activities[$man["parent_codename"]] = $new;
		    }
		    else
			$new = &$this->managed_activities[$man["parent_codename"]];

		    // Fetch the activity
		    $act = new FullActivity;
		    $act->build($man["id"]);
		    $new->subactivities[$act->codename] = $act;
		}
	    }
	    /*
	    foreach ($this->managed_activities as &$act)
	    {
		usort($act->subactivities, "sort_by_begin");
	    }
	    */
	}

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
           ON medal.id = user_medal.id_medal
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
	return (true);
    }
}
