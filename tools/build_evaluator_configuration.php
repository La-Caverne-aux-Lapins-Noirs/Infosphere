<?php

function evaluator_configuration_fail($message)
{
    add_log(REPORT, $message);
    return ([NULL, NULL, $message]);
}

function evaluator_configuration_include_paths($configuration, $activity, array $school)
{
    global $Configuration;

    // Keep this in sync with subject generation: the very same configuration.dab
    // must resolve the same @insert/@push dependencies for DocBuilder and Evaluator.
    $paths = [
        dirname($configuration),
        $Configuration->_ConfigurationDir(),
        $Configuration->ActivitiesDir($activity->codename, ""),
    ];
    if (function_exists("correction_root_dir"))
        $paths[] = correction_root_dir();
    else
        $paths[] = dirname(__DIR__)."/dres/corrections";
    if (!empty($activity->template_codename))
        $paths[] = $Configuration->ActivitiesDir($activity->template_codename, "");
    if (($school["codename"] ?? "") != "")
        $paths[] = $Configuration->SchoolsDir($school["codename"]);

    $out = [];
    foreach ($paths as $path)
        if ($path != "" && is_dir($path))
            $out[] = rtrim($path, "/");
    return (array_values(array_unique($out)));
}

function evaluator_configuration_resolve($configuration, $activity, array $school, $profile)
{
    $tmp = tempnam(sys_get_temp_dir(), "infosphere_eval_");
    if ($tmp === false)
        return ([NULL, "Cannot create evaluator configuration workspace"]);
    @unlink($tmp);
    $profile_file = $tmp.".school.dab";
    $resolved_file = $tmp.".activity.dab";

    if (@file_put_contents($profile_file, $profile) === false)
        return ([NULL, "Cannot write evaluator school profile"]);

    $command = ["mergeconf"];
    foreach (evaluator_configuration_include_paths($configuration, $activity, $school) as $path)
    {
        $command[] = "-I";
        $command[] = $path;
    }
    // Do not prepend res/technocore/configuration.dab here. Evaluator loads its
    // own node-local /etc/technocore/configuration.dab; only the school profile
    // and activity configuration belong in activity.dab.
    $command[] = "-i";
    $command[] = $profile_file;
    $command[] = "-i";
    $command[] = $configuration;
    $command[] = "-o";
    $command[] = $resolved_file;
    $command[] = "--resolve";

    $ret = run_command($command, 300);
    @unlink($profile_file);
    if (($ret["exit_code"] ?? 1) !== 0 || !is_file($resolved_file))
    {
        @unlink($resolved_file);
        $details = trim((string)($ret["stderr"] ?? ""));
        if ($details == "")
            $details = trim((string)($ret["stdout"] ?? ""));
        if ($details == "")
            $details = "mergeconf exited with code ".(string)($ret["exit_code"] ?? "unknown");
        return ([NULL, "Cannot resolve evaluator configuration: ".$details]);
    }

    $content = @file_get_contents($resolved_file);
    @unlink($resolved_file);
    if ($content === false)
        return ([NULL, "Cannot read resolved evaluator configuration"]);
    return ([$content, NULL]);
}

function build_evaluator_configuration($activity, $userId)
{
    // The subject and the evaluator intentionally consume the same source file.
    // FullActivity already resolved the language/template precedence for us.
    $path = $activity->current_configuration ?? NULL;
    if ($path === NULL || !is_file($path))
        return (evaluator_configuration_fail(
            "No configuration.dab available when building evaluator configuration for ".
            (string)($activity->codename ?? "unknown activity")
        ));

    $id_school = school_technocore_pick_activity_school($activity, $userId);
    if ($id_school <= 0)
        return (evaluator_configuration_fail(
            "Failed to resolve school when building evaluator configuration for ".
            $activity->codename." / user #".(int)$userId
        ));
    $school = fetch_school($id_school);
    if (!is_array($school) || !isset($school["id"]))
        return (evaluator_configuration_fail(
            "Failed to retrieve school #$id_school when building evaluator configuration"
        ));

    $profile_error = NULL;
    $profile = school_technocore_profile_dabsic($school, $profile_error);
    if ($profile === NULL)
        return (evaluator_configuration_fail(
            "Failed to build school TechnoCore profile for evaluator: ".
            ($profile_error ?? "unknown error")
        ));

    [$actConf, $resolve_error] = evaluator_configuration_resolve(
        $path, $activity, $school, $profile
    );
    if ($actConf === NULL)
        return (evaluator_configuration_fail($resolve_error));

    $functions = array_keys(db_select_all("
            function.codename
            FROM function
            LEFT JOIN function_medal ON function.id = function_medal.id_function
            LEFT JOIN user_medal ON user_medal.id_medal = function_medal.id_medal
            WHERE user_medal.id_user = ".(int)$userId, "codename"));
    $allowFunc = "[AuthorizedFunctions\n".implode(" ", $functions)."\n]";
    return ([$actConf, $allowFunc, NULL]);
}
?>
