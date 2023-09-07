<?php

if (strtolower($METHOD) == "options")
{
    $auth = [];
    foreach ($Tab as $k => $v)
	$auth []= $k;
    $auth = "Allow: ".implode(",", $auth);
    http_response_code(200);
    header($auth);
    $request = new Response;
    return ;
}

if (isset($Tab[$METHOD][$DATA["action"]]))
{
    // Plusieurs filtres sont possibles - C'est un OU entre chaque.
    $Filter = $Tab[$METHOD][$DATA["action"]][0];
    $Filter = explode(",", $Filter);
    $Auth = false;
    foreach ($Filter as $ft)
	if ($ft($ID))
	    $Auth = true;
    if ($Auth == false)
	forbidden();
    $request = $Tab[$METHOD][$DATA["action"]][1]($ID, $DATA, $METHOD, $OUTPUT, $MODULE);
}


