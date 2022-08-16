<?php
function reverse_sort($tag, $sort)
{
    if (($neg = (substr($sort, 0, 1) == "-")))
	$sort = substr($sort, 1);
    if ($tag == $sort)
    {
	if ($neg)
	    return ($sort);
	return ("-".$sort);
    }
    return ($tag);
}

$users = [];
foreach ($cmodule->team as $team)
{
    foreach ($team["user"] as $usrx)
    {
	$usrx["medal"] = by_codename($usrx["medal"]);
	$usrx["closed"] = $team["closed"];
	$users[] = $usrx;
    }
}

function sort_by($a, $b)
{
    global $sort;

    if (substr($sort, 0, 1) == "-")
    {
	$sort = substr($sort, 1);
	$res = sort_by($a, $b) * -1;
	$sort = "-".$sort;
	return ($res);
    }
    if (substr($sort, 0, 6) == "medal_")
    {
	$m = substr($sort, 6);
	return (strcmp($b["medal"][$m]["result"], $a["medal"][$m]["result"]));
    }
    if (is_integer($b[$sort]))
	return ($b[$sort] - $a[$sort]);
    return (strcmp($a[$sort], $b[$sort]));
}
if ($sort != "")
    usort($users, "sort_by");

require_once ("student_list.phtml");
?>
