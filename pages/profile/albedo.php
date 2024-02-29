<?php
if (!isset($albedo) || $albedo != 1)
    return ;

//////////////////////////////////////////
//// LOG DE CONNEXION ET MAJ DES POSTES //
//////////////////////////////////////////

$logcnt = 0;
if (($logs = hand_request(["command" => "getlog"], false)) === false)
    add_log(TRACE, "Failed to get log from TechnoCore", 1);
else if (isset($logs["content"]))
{
    $ologs = [];
    foreach ($logs["content"] as $log)
    {
	if (!strlen(@$log["name"]) || !strlen(@$log["mac"]) || !strlen(@$log["ip"]) || !isset($log["type"]))
	    continue ;
	if (is_array($log["name"]))
	    continue ;
	$users = strlen(@$log["user"]) && strlen(@$log["date"]) && isset($log["lock"]) && isset($log["distant"]);
	$ologs[$log["name"]] = $log;
	$ologs[$log["name"]]["user"] = [];
	if (!$log["distant"])
	{
	    $ologs[$log["name"]]["local"] = $log["user"];
	    $ologs[$log["name"]]["lock"] = $log["lock"];
	}
    }

    foreach ($logs["content"] as $log)
    {
	if (!strlen(@$log["name"]) || !strlen(@$log["mac"]) || !strlen(@$log["ip"]) || !isset($log["type"]))
	    continue ;
	if (is_array($log["name"]))
	    continue ;
	$ologs[$log["name"]]["user"][] = $log["user"];
	$ologs[$log["name"]]["user"] = array_unique($ologs[$log["name"]]["user"]);
	$ologs[$log["name"]]["user"] = array_filter($ologs[$log["name"]]["user"]);
    }
    
    foreach ($ologs as $name => $log)
    {
	$lock = isset($log["lock"]) && $log["lock"];

	// Information sur le poste
	set_desk_data($log);

	// Log étudiants
	if (isset($log["user"]))
	{
	    foreach ($log["user"] as $user)
	    {
		if (($cod = resolve_codename("user", $user))->is_error())
		{
		    add_log(TRACE, "Trace Activity log get invalid login '".$log["user"]."'", 1);
		    continue ;
		}
		$cod = $cod->value;
		compute_student_log(
		    ["id" => $cod],
		    $log["distant"] ? 2 : $log["lock"] ? -1 : 1,
		    now(), //$log["date"],
		    $log["ip"],
		    $log["distant"]
		);
	    }
	}
	++$logcnt;
    }
    if ($logcnt)
    {
	add_log(EDITING_OPERATION, "Setting $logcnt activity logs from TechnoCore", 1);
	hand_request(["command" => "clearlog"]);
    }
}

