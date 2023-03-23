<?php

function medal_tooltip($medal)
{
    global $Dictionnary;

    $failures = "";
    $successes = "";
    $suc = 0;
    $fel = 0;
    if (isset($medal["failure_list"]))
    {
	foreach (array_unique($medal["failure_list"]) as $fl)
	{
	    $failures .= " - $fl\n";
	    $fel += 1;
	}
    }
    if (isset($medal["success_list"]))
    {
	foreach (array_unique($medal["success_list"]) as $sc)
	{
	    $successes .= " - $sc\n";
	    $suc += 1;
	}
    }
    $ret = $medal["name"]." (".$medal["codename"].")\n".
	   ($medal["description"] != "" ? "\n".$medal["description"]."\n" : "")
    ;
    if (isset($medal["success"]) && $medal["success"] != 0)
	$ret .= $Dictionnary["Success"].": $suc\n".$successes;
    if (isset($medal["failure"]) && $medal["failure"] != 0)
	$ret .= $Dictionnary["Failure"].": $fel\n".$failures;
    return ($ret);
}
