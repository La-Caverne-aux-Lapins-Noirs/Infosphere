<?php

function subject_context_activity_row($id_activity)
{
    $id_activity = (int)$id_activity;
    if ($id_activity <= 0)
        return (NULL);
    return (db_select_one("
        activity.*,
        template.codename AS template_codename,
        template.fr_name AS template_fr_name,
        template.en_name AS template_en_name,
        template.fr_description AS template_fr_description,
        template.en_description AS template_en_description
        FROM activity
        LEFT JOIN activity AS template ON template.id = activity.id_template
        WHERE activity.id = $id_activity
    "));
}

function subject_context_localized_value(array $row, $field, $language)
{
    $key = strtolower($language)."_".$field;
    $value = trim((string)($row[$key] ?? ""));
    if ($value != "")
        return ($value);
    if (!empty($row["template_link"]))
        return (trim((string)($row["template_".$key] ?? "")));
    return ("");
}

function subject_context_activity($id_activity)
{
    $row = subject_context_activity_row($id_activity);
    if (!is_array($row))
        return ([]);

    return ([
        "id" => (int)$row["id"],
        "codename" => (string)$row["codename"],
        "code" => (string)$row["codename"],
        "template_codename" => (string)($row["template_codename"] ?? ""),
        "FR" => subject_context_localized_value($row, "name", "FR"),
        "EN" => subject_context_localized_value($row, "name", "EN"),
        "description" => [
            "FR" => subject_context_localized_value($row, "description", "FR"),
            "EN" => subject_context_localized_value($row, "description", "EN"),
        ],
    ]);
}

function subject_context_matter($act)
{
    $id_parent = (int)($act->parent_activity ?? 0);
    if ($id_parent <= 0)
        return ([]);
    return (subject_context_activity($id_parent));
}

function subject_context_configuration_uses_template($cnf, $act)
{
    global $Configuration;

    if (empty($act->template_link) || empty($act->template_codename))
        return (false);
    $template = $Configuration->ActivitiesDir($act->template_codename, "");
    $configuration = str_replace("\\", "/", (string)$cnf);
    $template = rtrim(str_replace("\\", "/", $template), "/")."/";
    return (strncmp($configuration, $template, strlen($template)) == 0);
}

function subject_context_activity_laboratory($id_activity)
{
    $id_activity = (int)$id_activity;
    if ($id_activity <= 0)
        return (0);
    $row = db_select_one("
        activity_teacher.id_laboratory
        FROM activity_teacher
        LEFT JOIN laboratory ON laboratory.id = activity_teacher.id_laboratory
        WHERE activity_teacher.id_activity = $id_activity
          AND activity_teacher.id_laboratory IS NOT NULL
          AND activity_teacher.id_laboratory > 0
          AND laboratory.deleted IS NULL
        ORDER BY activity_teacher.id
    ");
    return ((int)($row["id_laboratory"] ?? 0));
}

function subject_context_laboratory_logo($codename)
{
    global $Configuration;

    $dir = $Configuration->GroupsDir($codename);
    foreach (["logo.png", "icon.png", "avatar.png"] as $name)
        if (is_file($dir.$name))
            return ($dir.$name);
    return ("");
}

function subject_context_laboratory($cnf, $act)
{
    $sources = [];
    $template_first = subject_context_configuration_uses_template($cnf, $act);
    $direct = (int)($act->id ?? 0);
    $template = (!empty($act->template_link)) ? (int)($act->id_template ?? 0) : 0;
    $parent = (int)($act->parent_activity ?? 0);
    $parent_row = subject_context_activity_row($parent);
    $parent_template = is_array($parent_row) && !empty($parent_row["template_link"])
        ? (int)($parent_row["id_template"] ?? 0) : 0;

    if ($template_first)
    {
        $sources[] = ["id" => $template, "source" => "template"];
        $sources[] = ["id" => $direct, "source" => "activity"];
    }
    else
    {
        $sources[] = ["id" => $direct, "source" => "activity"];
        $sources[] = ["id" => $template, "source" => "template"];
    }
    $sources[] = ["id" => $parent, "source" => "matter"];
    $sources[] = ["id" => $parent_template, "source" => "matter_template"];

    $id_laboratory = 0;
    $source = "";
    foreach ($sources as $candidate)
    {
        if ($candidate["id"] <= 0)
            continue ;
        $id_laboratory = subject_context_activity_laboratory($candidate["id"]);
        if ($id_laboratory > 0)
        {
            $source = $candidate["source"];
            break ;
        }
    }

    if ($id_laboratory <= 0)
        foreach ((array)($act->teacher ?? []) as $teacher)
            if ((int)($teacher["id_laboratory"] ?? 0) > 0)
            {
                $id_laboratory = (int)$teacher["id_laboratory"];
                $source = !empty($teacher["template"]) ? "template" : "inherited";
                break ;
            }

    if ($id_laboratory <= 0)
        return ([]);

    $row = db_select_one("
        id, codename, fr_name, en_name, fr_description, en_description
        FROM laboratory
        WHERE id = $id_laboratory AND deleted IS NULL
    ");
    if (!is_array($row))
        return ([]);

    $logo = subject_context_laboratory_logo($row["codename"]);
    return ([
        "id" => (int)$row["id"],
        "codename" => (string)$row["codename"],
        "FR" => (string)($row["fr_name"] ?? ""),
        "EN" => (string)($row["en_name"] ?? ""),
        "description" => [
            "FR" => (string)($row["fr_description"] ?? ""),
            "EN" => (string)($row["en_description"] ?? ""),
        ],
        "logo" => $logo,
        "small_logo" => $logo,
        "source" => $source,
    ]);
}

function subject_context_user_school_ids()
{
    global $User;

    if (!is_array($User) || (int)($User["id"] ?? 0) <= 0)
        return ([]);
    $user = $User;
    $schools = get_user_school($user);
    $out = [];
    foreach ((array)$schools as $school)
    {
        $id = (int)($school["id_school"] ?? 0);
        if ($id > 0)
            $out[] = $id;
    }
    return (array_values(array_unique($out)));
}

function subject_context_cycle_school_ids($act)
{
    $cycles = [];
    foreach ((array)($act->cycle ?? []) as $cycle)
    {
        $id = (int)($cycle["id_cycle"] ?? ($cycle["id"] ?? 0));
        if ($id > 0)
            $cycles[] = $id;
    }
    $cycles = array_values(array_unique($cycles));
    if (!count($cycles))
        return ([]);

    $out = [];
    foreach (db_select_all(
        "DISTINCT id_school FROM school_cycle WHERE id_cycle IN (".
        implode(",", $cycles).") ORDER BY id_school"
    ) as $row)
        if ((int)$row["id_school"] > 0)
            $out[] = (int)$row["id_school"];
    return ($out);
}

function subject_context_laboratory_school_ids(array $laboratory)
{
    $id = (int)($laboratory["id"] ?? 0);
    if ($id <= 0)
        return ([]);
    $out = [];
    foreach (db_select_all(
        "id_school FROM school_laboratory WHERE id_laboratory = $id ORDER BY id_school"
    ) as $row)
        if ((int)$row["id_school"] > 0)
            $out[] = (int)$row["id_school"];
    return ($out);
}

function subject_context_pick_school(array $candidates, array $user_schools)
{
    $candidates = array_values(array_unique(array_filter(array_map("intval", $candidates))));
    if (!count($candidates))
        return (0);
    $common = array_values(array_intersect($candidates, $user_schools));
    if (count($common))
        return ((int)$common[0]);
    if (count($candidates) == 1)
        return ((int)$candidates[0]);
    return (0);
}

function subject_context_school_id($act, array $laboratory)
{
    $user_schools = subject_context_user_school_ids();

    if ($act->session_registered != NULL)
    {
        $id = subject_context_pick_school(
            session_school_ids($act->session_registered), $user_schools
        );
        if ($id > 0)
            return ($id);
    }

    $id = subject_context_pick_school(subject_context_cycle_school_ids($act), $user_schools);
    if ($id > 0)
        return ($id);

    $id = subject_context_pick_school(
        subject_context_laboratory_school_ids($laboratory), $user_schools
    );
    if ($id > 0)
        return ($id);

    if (count($user_schools))
        return ((int)$user_schools[0]);

    $cycle_schools = subject_context_cycle_school_ids($act);
    if (count($cycle_schools))
        return ((int)$cycle_schools[0]);
    $laboratory_schools = subject_context_laboratory_school_ids($laboratory);
    if (count($laboratory_schools))
        return ((int)$laboratory_schools[0]);
    return (0);
}

function subject_context_school($act, array $laboratory)
{
    $id_school = subject_context_school_id($act, $laboratory);
    if ($id_school <= 0)
        return ([]);

    $context = document_context_school($id_school);
    if (!is_array($context))
        return ([]);
    $school = fetch_school($id_school);
    if (is_array($school))
    {
        $context["codename"] = (string)($school["codename"] ?? ($context["codename"] ?? ""));
        $context["FR"] = (string)($school["fr_name"] ?? ($context["name"] ?? ""));
        $context["EN"] = (string)($school["en_name"] ?? ($context["name"] ?? ""));
    }
    return ($context);
}

function subject_context_team($act)
{
    global $User;

    $team = [];
    $user_team = is_array($act->user_team ?? NULL) ? $act->user_team : [];
    if (isset($user_team["leader"]["codename"]))
        $team[] = $user_team["leader"]["codename"];
    foreach ((array)($user_team["user"] ?? []) as $member)
        if (($member["codename"] ?? "") != "" && (int)($member["status"] ?? 1) != 0)
            $team[] = $member["codename"];
    if (!count($team) && is_array($User) && ($User["codename"] ?? "") != "")
        $team[] = $User["codename"];
    return (array_values(array_unique($team)));
}

function subject_context_include_paths($cnf, $act, array $school)
{
    global $Configuration;

    $paths = [
        dirname($cnf),
        $Configuration->_ConfigurationDir(),
        $Configuration->ActivitiesDir($act->codename, ""),
    ];
    // Subject files may @insert/@push reusable exercises from the correction
    // catalogue.  That catalogue is independent from activity resources and
    // must therefore be an explicit mergeconf include root.
    if (function_exists("correction_root_dir"))
        $paths[] = correction_root_dir();
    else
        $paths[] = dirname(__DIR__)."/dres/corrections";
    if (!empty($act->template_codename))
        $paths[] = $Configuration->ActivitiesDir($act->template_codename, "");
    if (($school["codename"] ?? "") != "")
        $paths[] = $Configuration->SchoolsDir($school["codename"]);

    $out = [];
    foreach ($paths as $path)
        if ($path != "" && is_dir($path))
            $out[] = rtrim($path, "/");
    return (array_values(array_unique($out)));
}

function generate_subject($cnf, $act)
{
    global $User;
    global $Configuration;
    global $Language;

    $act->subject_generation_error = NULL;
    if (!is_file($cnf))
    {
        $act->subject_generation_error = "Configuration file not found: ".$cnf;
        return (NULL);
    }

    // DocBuilder does not load Evaluator's /etc/technocore/configuration.dab.
    // Supply the production defaults before school conventions and exercises,
    // including when no school was selected. Never depend on Scolaire here.
    $runtime_profile = __DIR__."/../res/technocore/configuration.dab";
    if (!is_readable($runtime_profile))
    {
        $act->subject_generation_error = "TechnoCore runtime profile not found: ".$runtime_profile;
        return (NULL);
    }

    $personal_activity_dir =
        $Configuration->UsersDir($User["codename"])."perso/{$act->codename}/";
    $directory_result = new_directory($personal_activity_dir);
    if ($directory_result->is_error())
    {
        $act->subject_generation_error = (string)$directory_result;
        return (NULL);
    }

    $laboratory = subject_context_laboratory($cnf, $act);
    $school = subject_context_school($act, $laboratory);
    $activity = subject_context_activity($act->id);
    $matter = subject_context_matter($act);
    if (($act->current_icon ?? "") != "" && is_file($act->current_icon))
    {
        $activity["logo"] = $act->current_icon;
        $activity["small_logo"] = $act->current_icon;
    }

    $user_team = is_array($act->user_team ?? NULL) ? $act->user_team : [];
    $data = [
        "language" => strtoupper($Language),
        "code_name" => (string)($act->codename ?? ""),
        "login" => subject_context_team($act),
        "token" => (string)($user_team["code"] ?? ""),
        "team_size" => [(int)$act->min_team_size, (int)$act->max_team_size],
        "medal" => [],
        "authorized_function" => [],
        "delivery" => [
            "method" => "NFS",
            "target" => [],
            "date" => $act->pickup_date,
        ],
    ];
    if (count($school))
    {
        $data["school"] = $school;
        $data["school_name"] = (string)($school["name"] ?? "");
    }
    if (count($matter))
        $data["matter"] = $matter;
    if (count($activity))
        $data["activity"] = $activity;
    if (count($laboratory))
        $data["laboratory"] = $laboratory;

    $instance = $personal_activity_dir."instance.dab";
    $generated = generate_dabsic($data, $instance);
    if ($generated->is_error())
    {
        $act->subject_generation_error = (string)$generated;
        return (NULL);
    }

    // Technical school conventions must be parsed before the subject itself:
    // reusable Dabsic resources can resolve FunctionPrefix, PutChar, ... while
    // they are being loaded.  The normal Infosphere instance stays last so
    // contextual activity/matter/front-page data can still override defaults.
    $school_profile = "";
    if ((int)($school["id"] ?? 0) > 0)
    {
        $school_profile = $personal_activity_dir."school-technocore.dab";
        $profile_error = NULL;
        if (!school_technocore_write_profile($school, $school_profile, $profile_error))
        {
            $act->subject_generation_error = $profile_error ??
                "Cannot build school TechnoCore profile";
            return (NULL);
        }
    }

    $outfile = $personal_activity_dir."subject.pdf";
    $command = "docbuilder";
    foreach (subject_context_include_paths($cnf, $act, $school) as $path)
        $command .= " -I ".escapeshellarg($path);

    $command .= " -i ".escapeshellarg($runtime_profile);
    if ($school_profile != "")
        $command .= " -i ".escapeshellarg($school_profile);

    $command .= " -i ".escapeshellarg($cnf);
    $command .= " -i ".escapeshellarg($instance);
    $command .= " -o ".escapeshellarg($outfile);

    $ret = run_command($command);
    if (($ret["exit_code"] ?? 1) !== 0 || !is_file($outfile))
    {
        $stderr = trim((string)($ret["stderr"] ?? ""));
        $stdout = trim((string)($ret["stdout"] ?? ""));
        $details = $stderr != "" ? $stderr : $stdout;
        if ($details == "")
            $details = "DocBuilder exited with code ".(string)($ret["exit_code"] ?? "unknown");
        $act->subject_generation_error = $details;
        add_log(REPORT, "Subject generation failed for {$act->codename}: ".$details);
        return (NULL);
    }
    return ($outfile);
}
