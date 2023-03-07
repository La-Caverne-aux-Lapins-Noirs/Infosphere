<?php

function medal_tooltip($medal)
{
    global $Dictionnary;

    $failures = "";
    $successes = "";
    if (isset($medal["failure_list"]))
	foreach ($medal["failure_list"] as $fl)
	    $failures .= " - $fl\n";
    if (isset($medal["success_list"]))
	foreach ($medal["success_list"] as $sc)
	    $successes .= " - $sc\n";
    $ret = $medal["name"]." (".$medal["codename"].")\n".
	   ($medal["description"] != "" ? "\n".$medal["description"]."\n" : "")
    ;
    if (isset($medal["success"]) && $medal["success"] != 0)
	$ret .= $Dictionnary["Success"].": ".$medal["success"]."\n".$successes;
    if (isset($medal["failure"]) && $medal["failure"] != 0)
	$ret .= $Dictionnary["Failure"].": ".$medal["failure"].$failures;
    return ($ret);
}
