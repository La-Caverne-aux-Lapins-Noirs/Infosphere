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

function subject_generation_remove_tree($path)
{
    if ($path == "" || !file_exists($path))
        return ;
    if (!is_dir($path) || is_link($path))
    {
        @unlink($path);
        return ;
    }
    foreach (scandir($path) ?: [] as $entry)
    {
        if ($entry == "." || $entry == "..")
            continue ;
        subject_generation_remove_tree(rtrim($path, "/")."/".$entry);
    }
    @rmdir($path);
}

function subject_generation_work_directory($act)
{
    $prefix = rtrim(sys_get_temp_dir(), "/")."/infosphere_subject_".
        preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)($act->codename ?? "activity"))."_";
    try
    {
        $suffix = bin2hex(random_bytes(8));
    }
    catch (Throwable $exception)
    {
        $suffix = uniqid("", true);
    }
    $directory = $prefix.$suffix;
    if (!@mkdir($directory, 0700, true))
        return (NULL);
    return (rtrim($directory, "/")."/");
}

function subject_generation_configuration_mtime($cnf)
{
    $mtime = @filemtime($cnf);
    return ($mtime === false ? 0 : (int)$mtime);
}

function subject_generation_is_fresh($cnf, $outfile)
{
    if (!is_file($outfile))
        return (false);
    $configuration_mtime = subject_generation_configuration_mtime($cnf);
    $subject_mtime = @filemtime($outfile);
    if ($subject_mtime === false)
        return (false);
    return ($configuration_mtime <= (int)$subject_mtime);
}

function subject_generation_metadata_path($act)
{
    global $Configuration;
    global $Language;

    return ($Configuration->ActivitiesDir($act->codename, $Language)."subject.meta.json");
}

function subject_generation_read_metadata($cnf, $act)
{
    $file = subject_generation_metadata_path($act);
    if (!is_file($file))
        return (NULL);
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data))
        return (NULL);

    $configuration = realpath($cnf);
    if ($configuration === false)
        $configuration = $cnf;
    if (($data["configuration"] ?? "") !== $configuration)
        return (NULL);
    if ((int)($data["configuration_mtime"] ?? -1)
        !== subject_generation_configuration_mtime($cnf))
        return (NULL);
    if (!array_key_exists("personalized", $data))
        return (NULL);
    return ($data);
}

function subject_generation_write_metadata($cnf, $act, array $metadata)
{
    $file = subject_generation_metadata_path($act);
    $directory_result = new_directory($file);
    if ($directory_result->is_error())
        return (false);

    $configuration = realpath($cnf);
    if ($configuration === false)
        $configuration = $cnf;
    $data = [
        "configuration" => $configuration,
        "configuration_mtime" => subject_generation_configuration_mtime($cnf),
        "personalized" => !empty($metadata["Personalized"]),
        "dabsic_hash" => (string)($metadata["DabsicHash"] ?? ""),
    ];
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($json === false)
        return (false);

    $tmp = $file.".tmp.".getmypid();
    if (@file_put_contents($tmp, $json."\n") === false)
        return (false);
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file))
    {
        @unlink($tmp);
        return (false);
    }
    return (true);
}

function subject_generation_shared_output($act)
{
    global $Configuration;
    global $Language;

    return ($Configuration->ActivitiesDir($act->codename, $Language)."subject.pdf");
}

function subject_generation_personalized_output($act, array $user)
{
    global $Configuration;
    global $Language;

    return ($Configuration->UsersDir($user["codename"])."subjects/".
        $act->codename."/".$Language."/subject.pdf");
}

function subject_generation_personalized_user_is_valid($act, array $user)
{
    if ((int)($user["id"] ?? 0) <= 0 || ($user["codename"] ?? "") == "")
        return (false);

    // A personalized subject belongs to a learner instance, not to the staff
    // account merely previewing the activity page.
    return (!empty($act->registered) && (int)($act->leader ?? 0) > 0);
}

function subject_generation_context($cnf, $act, $work_directory)
{
    global $Language;

    $runtime_profile = __DIR__."/../res/technocore/configuration.dab";
    if (!is_readable($runtime_profile))
    {
        $act->subject_generation_error =
            "TechnoCore runtime profile not found: ".$runtime_profile;
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

    $instance = $work_directory."instance.dab";
    $generated = generate_dabsic($data, $instance);
    if ($generated->is_error())
    {
        $act->subject_generation_error = (string)$generated;
        return (NULL);
    }

    $school_profile = "";
    if ((int)($school["id"] ?? 0) > 0)
    {
        $school_profile = $work_directory."school-technocore.dab";
        $profile_error = NULL;
        if (!school_technocore_write_profile($school, $school_profile, $profile_error))
        {
            $act->subject_generation_error = $profile_error ??
                "Cannot build school TechnoCore profile";
            return (NULL);
        }
    }

    $command = ["docbuilder"];
    foreach (subject_context_include_paths($cnf, $act, $school) as $path)
    {
        $command[] = "-I";
        $command[] = $path;
    }
    $command[] = "-i";
    $command[] = $runtime_profile;
    if ($school_profile != "")
    {
        $command[] = "-i";
        $command[] = $school_profile;
    }
    $command[] = "-i";
    $command[] = $cnf;
    $command[] = "-i";
    $command[] = $instance;

    return ([
        "command" => $command,
        "school" => $school,
        "instance" => $instance,
    ]);
}

function subject_generation_command_error($ret, $prefix = "DocBuilder")
{
    $stderr = trim((string)($ret["stderr"] ?? ""));
    $stdout = trim((string)($ret["stdout"] ?? ""));
    $details = $stderr != "" ? $stderr : $stdout;
    if ($details == "")
        $details = $prefix." exited with code ".
            (string)($ret["exit_code"] ?? "unknown");
    return ($details);
}

function subject_generation_resolve_metadata($cnf, $act, array $context)
{
    $command = $context["command"];
    $command[] = "--metadata-only";
    $ret = run_command($command, 300);
    if (($ret["exit_code"] ?? 1) !== 0)
    {
        $act->subject_generation_error = subject_generation_command_error($ret);
        return (NULL);
    }
    $metadata = json_decode(trim((string)($ret["stdout"] ?? "")), true);
    if (!is_array($metadata) || !array_key_exists("Personalized", $metadata))
    {
        $act->subject_generation_error =
            "DocBuilder returned invalid subject metadata: ".
            trim((string)($ret["stdout"] ?? ""));
        return (NULL);
    }
    $metadata["Personalized"] = !empty($metadata["Personalized"]);
    subject_generation_write_metadata($cnf, $act, $metadata);
    return ($metadata);
}

function subject_generation_lock($outfile)
{
    $lockfile = rtrim(sys_get_temp_dir(), "/")."/infosphere_subject_".
        sha1($outfile).".lock";
    $lock = @fopen($lockfile, "c");
    if ($lock === false)
        return (NULL);
    if (!flock($lock, LOCK_EX))
    {
        fclose($lock);
        return (NULL);
    }
    return ($lock);
}

function subject_generation_render($cnf, $act, array $context, $outfile)
{
    $directory_result = new_directory($outfile);
    if ($directory_result->is_error())
    {
        $act->subject_generation_error = (string)$directory_result;
        return (NULL);
    }

    $lock = subject_generation_lock($outfile);
    if ($lock === NULL)
    {
        $act->subject_generation_error = "Cannot lock subject generation for ".$outfile;
        return (NULL);
    }

    if (subject_generation_is_fresh($cnf, $outfile))
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return ($outfile);
    }

    try
    {
        $suffix = bin2hex(random_bytes(6));
    }
    catch (Throwable $exception)
    {
        $suffix = uniqid("", true);
    }
    $temporary_output = $outfile.".tmp.".getmypid().".".$suffix;
    $command = $context["command"];
    $command[] = "-o";
    $command[] = $temporary_output;

    $ret = run_command($command, 300);
    if (($ret["exit_code"] ?? 1) !== 0 || !is_file($temporary_output))
    {
        @unlink($temporary_output);
        $details = subject_generation_command_error($ret);
        $act->subject_generation_error = $details;
        add_log(REPORT, "Subject generation failed for {$act->codename}: ".$details);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (NULL);
    }

    @chmod($temporary_output, 0644);
    if (!@rename($temporary_output, $outfile))
    {
        @unlink($temporary_output);
        $act->subject_generation_error = "Cannot publish generated subject: ".$outfile;
        flock($lock, LOCK_UN);
        fclose($lock);
        return (NULL);
    }

    flock($lock, LOCK_UN);
    fclose($lock);
    return ($outfile);
}

function generate_subject($cnf, $act, $subject_user = NULL)
{
    global $User;

    $act->subject_generation_error = NULL;
    if (!is_file($cnf))
    {
        $act->subject_generation_error = "Configuration file not found: ".$cnf;
        return (NULL);
    }

    if ($subject_user === NULL)
        $subject_user = $User;
    if (!is_array($subject_user))
    {
        $act->subject_generation_error = "No user context available for subject generation.";
        return (NULL);
    }

    $metadata = subject_generation_read_metadata($cnf, $act);
    $work_directory = NULL;
    $context = NULL;

    if ($metadata === NULL)
    {
        $work_directory = subject_generation_work_directory($act);
        if ($work_directory === NULL)
        {
            $act->subject_generation_error = "Cannot create temporary subject workspace.";
            return (NULL);
        }
        $context = subject_generation_context($cnf, $act, $work_directory);
        if ($context === NULL)
        {
            subject_generation_remove_tree($work_directory);
            return (NULL);
        }
        $metadata = subject_generation_resolve_metadata($cnf, $act, $context);
        if ($metadata === NULL)
        {
            subject_generation_remove_tree($work_directory);
            return (NULL);
        }
    }

    if (!empty($metadata["personalized"]))
        $personalized = true;
    else
        $personalized = !empty($metadata["Personalized"]);

    if ($personalized)
    {
        if (!subject_generation_personalized_user_is_valid($act, $subject_user))
        {
            if ($work_directory !== NULL)
                subject_generation_remove_tree($work_directory);
            $act->subject_generation_error =
                "This subject is personalized and requires a registered learner context.";
            return (NULL);
        }
        $outfile = subject_generation_personalized_output($act, $subject_user);
    }
    else
        $outfile = subject_generation_shared_output($act);

    if (subject_generation_is_fresh($cnf, $outfile))
    {
        if ($work_directory !== NULL)
            subject_generation_remove_tree($work_directory);
        return ($outfile);
    }

    if ($context === NULL)
    {
        $work_directory = subject_generation_work_directory($act);
        if ($work_directory === NULL)
        {
            $act->subject_generation_error = "Cannot create temporary subject workspace.";
            return (NULL);
        }
        $context = subject_generation_context($cnf, $act, $work_directory);
        if ($context === NULL)
        {
            subject_generation_remove_tree($work_directory);
            return (NULL);
        }
    }

    $outfile = subject_generation_render($cnf, $act, $context, $outfile);
    subject_generation_remove_tree($work_directory);
    return ($outfile);
}

function ensure_subject_for_access($act, $subject_user = NULL)
{
    if ($act->current_configuration === NULL || !is_file($act->current_configuration))
        return ($act->current_subject);

    $subject = generate_subject($act->current_configuration, $act, $subject_user);
    if ($subject !== NULL)
        $act->current_subject = $subject;
    else
        $act->current_subject = NULL;
    return ($act->current_subject);
}
