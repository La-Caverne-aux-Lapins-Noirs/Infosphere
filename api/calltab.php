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
    if ($Tab[$METHOD][$DATA["action"]][0]($ID) == false)
	forbidden();
    $request = $Tab[$METHOD][$DATA["action"]][1]($ID, $DATA, $METHOD, $OUTPUT, $MODULE);
}

