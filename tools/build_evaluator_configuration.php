<?php

function build_evaluator_configuration($activity, $userId)
{
    global $Configuration;
    $path = $Configuration->ActivitiesDir($activity->codename, NULL)."activity.dab";
    $evConfiguration["actConf"] = "";
    if (!file_exists($path))
	{
	    add_log(REPORT, "Failed to retrieve activity.dab when build config evaluator");
	    return [NULL, NULL];
	}
    else
	$evConfiguration["actConf"] = file_get_contents($path);
    $functions = array_keys(db_select_all("
			function.codename
			FROM function
			LEFT JOIN function_medal ON function.id = function_medal.id_function
			LEFT JOIN user_medal ON user_medal.id_medal = function_medal.id_medal
			WHERE user_medal.id_user = $userId", "codename"));
    $evConfiguration["authorizedFunc"] = "[AuthorizedFunctions\n".implode(" ", $functions)."\n]";
    return [$evConfiguration["actConf"], $evConfiguration["authorizedFunc"]];
}
?>
