<?php

function fetch_activity_template($id = -1, $rec = false, $deleted = 0)
{
    global $Language;

    $act = [];
    $id = (int)$id;
    $rec = $id != -1 || $rec;
    if ($id != -1)
    {
	$wh = " AND activity.id = $id ";
	$or = " close_date ASC, pickup_date ASC, codename ASC";
    }
    else
    {
	$wh = " AND activity.parent_activity = -1 ";
	$or = " codename ASC";
    }
    $deleted = (int)$deleted;
    if ($deleted == 0)
	$deleted = " activity.deleted = 0 AND ";
    else
	$deleted = "";
    $all = db_select_all("
       id, parent_activity
       FROM activity
       WHERE $deleted
       activity.is_template = 1
       $wh
       ORDER BY $or
    ");
    foreach ($all as $i => &$v)
    {
	$tmp = new FullActivity;
	if ($tmp->build($v["id"], $deleted == "", $rec) == false)
	    continue ;
	$act[] = $tmp;
    }
    return (new ValueResponse($act));
}
