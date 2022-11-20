<?php

function fetch_activity($id = -1, $rec = false)
{
    global $Language;

    $act = [];
    $id = (int)$id;
    $rec = $id != -1 || $rec;
    if ($id != -1)
	$id = " AND activity.id = $id ";
    else
	$id = " AND activity.parent_activity IS NULL ";
    $all = db_select_all("
       id, parent_activity, codename
       FROM activity
       WHERE activity.deleted IS NULL
       AND activity.is_template = 0
       $id
       ORDER BY close_date ASC, pickup_date ASC, codename ASC
    ");
    foreach ($all as $i => &$v)
    {
	$tmp = new FullActivity;
	if ($tmp->build($v["id"], false, $rec) == false)
	    continue ;
	$act[] = $tmp;
    }
    return (new ValueResponse($act));
}

