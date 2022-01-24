<?php

function fetch_cycle_activity($cycle, $cond = [])
{
    global $Language;

    if (($err = resolve_codename("cycle", $cycle))->is_error())
	return (new ErrorResponse("NotAnId", $cycle));
    $cycle = $err->value;

    $act = [];
    $all = db_select_all("
       id_activity as id
       FROM activity_cycle
       LEFT JOIN activity ON activity_cycle.id_activity = activity.id
       WHERE activity_cycle.id_cycle = $cycle AND activity.deleted = 0 AND activity.parent_activity = -1
    ");
    foreach ($all as $i => &$v)
    {
	$tmp = new FullActivity;
	if ($tmp->build($v["id"]) == false)
	    continue ;
	$act[] = $tmp;
    }
    return (new ValueResponse($act));
}
