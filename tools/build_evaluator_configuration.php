<?php

function build_evaluator_configuration($activity, $userId)
{
    global $Configuration;

    $path = $Configuration->ActivitiesDir($activity->codename, NULL)."activity.dab";
    if (!file_exists($path))
    {
        add_log(REPORT, "Failed to retrieve activity.dab when build config evaluator");
        return ([NULL, NULL]);
    }

    $id_school = school_technocore_pick_activity_school($activity, $userId);
    if ($id_school <= 0)
    {
        add_log(REPORT, "Failed to resolve school when build config evaluator for ".
            $activity->codename." / user #".(int)$userId);
        return ([NULL, NULL]);
    }
    $school = fetch_school($id_school);
    if (!is_array($school) || !isset($school["id"]))
    {
        add_log(REPORT, "Failed to retrieve school #$id_school when build config evaluator");
        return ([NULL, NULL]);
    }

    $profile_error = NULL;
    $profile = school_technocore_profile_dabsic($school, $profile_error);
    if ($profile === NULL)
    {
        add_log(REPORT, "Failed to build school TechnoCore profile for evaluator: ".
            ($profile_error ?? "unknown error"));
        return ([NULL, NULL]);
    }

    $activity_configuration = file_get_contents($path);
    if ($activity_configuration === false)
    {
        add_log(REPORT, "Failed to read activity.dab when build config evaluator");
        return ([NULL, NULL]);
    }
    // The school profile must precede activity.dab because its @insert files
    // may resolve FunctionPrefix/MacroPrefix/PutChar while parsing.
    $actConf = $profile."\n".$activity_configuration;

    $functions = array_keys(db_select_all("
            function.codename
            FROM function
            LEFT JOIN function_medal ON function.id = function_medal.id_function
            LEFT JOIN user_medal ON user_medal.id_medal = function_medal.id_medal
            WHERE user_medal.id_user = ".(int)$userId, "codename"));
    $allowFunc = "[AuthorizedFunctions\n".implode(" ", $functions)."\n]";
    return ([$actConf, $allowFunc]);
}
?>
