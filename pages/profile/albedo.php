<?php
if (!isset($albedo) || $albedo != 1)
    return ;
$logcnt = 0;
if (($logs = hand_request(["command" => "getlog"], false)) === false)
    add_log(TRACE, "Failed to get log from TechnoCore", 1);
else if (isset($logs["content"]))
{
    foreach ($logs["content"] as $log)
    {
	if (($cod = resolve_codename("user", $log["login"]))->is_error())
	{
	    add_log(TRACE, "Trace Activity log get invalid login '".$log["login"]."'", 1);
	    continue ;
	}
	$cod = $cod->value;
	compute_student_log(
	    ["id" => $cod],
	    $log["lock"] ? -1 : 1,
	    $log["date"],
	    $log["mac"], // IP OU MAC
	);
	++$logcnt;
    }
    if ($logcnt)
    {
	add_log(EDITING_OPERATION, "Setting $logcnt activity logs from TechnoCore", 1);
	//hand_request(["command" => "clearlog"]);
    }
}
