<?php

function build_activity_sketch($activity, $unroll = false)
{
    if (is_array($activity) == false)
	$activity = [$activity];

    $res = [];
    $nbr = 2; // 1 est occupé par les labels
    foreach ($activity as $act)
    {
	$class = 0;
	$practical = 0;
	$followup = 0;
	$demo = 0;
	$exam = 0;
	$projects = 0;

	foreach ($act->subactivities as $sub)
	{
	    $slots = false;
	    if ($sub->min_team_size > 1)
		$tsize = $sub->min_team_size;
	    else
		$tsize = 1;
	    if ($sub->type >= 1 && $sub->type <= 2)
		$current = &$practical;
	    else if ($sub->type >= 3 && $sub->type <= 4)
		$current = &$class;
	    else if ($sub->type >= 4 && $sub->type <= 9)
		$current = &$exam;
	    else if (($sub->type >= 10 && $sub->type <= 11) || $sub->type == 13)
	    {
		$current = &$followup;
		$slots = true;
	    }
	    else if ($sub->type == 12)
	    {
		$current = &$demo;
		$slots = true;
	    }
	    else if ($sub->type >= 14 && $sub->type <= 17)
	    {
		$projects += 1;
		continue ;
	    }
	    else
		continue ;

	    if ($slots == false)
	    {
		foreach ($sub->session as $sess)
		{
		    $current += ($sess->end_date - $sess->begin_date) / $tsize;
		}
	    }
	    else
	    {
		if ($sub->slot_duration == -1)
		    $current = "X";
		else
		{
		    if ($current !== "X")
			$current += ($sub->slot_duration) / $tsize;
		}
	    }
	    if ($unroll)
	    {
		$res[] = [
		    "students" => 1,
		    "codename" => $sub->codename,
		    "name" => $sub->name,
		    "class" => sprintf("%d", $class),
		    "practical" => sprintf("%d", $practical),
		    "followup" => ($followup !== "X" ? "=A$nbr*$followup*60" : "X"),
		    "demo" => ($demo !== "X" ? "=A$nbr*$demo*60" : "X"),
		    "exam" => "=A$nbr*$exam"
		];
		$class = 0;
		$practical = 0;
		$followup = 0;
		$demo = 0;
		$exam = 0;
		$projects = 0;
	    }
	}

	if ($unroll == false)
	{
	    $res[] = [
		"students" => 1,
		"codename" => $act->codename,
		"name" => $act->name,
		"credit" => $act->credit,
		"class" => sprintf("%d", $class),
		"practical" => sprintf("%d", $practical),
		"followup" => ($followup !== "X" ? "=A$nbr*$followup*60" : "X"),
		"demo" => ($demo !== "X" ? "=A$nbr*$demo*60" : "X"),
		"exam" => "=A$nbr*$exam",
		"projects" => $projects,
	    ];
	}
	$nbr += 1;
    }
    return ($res);
}

function build_cycle_sketch($cycles, $unroll = false)
{
    $res = [];
    foreach ($cycles as $cyc)
    {
	$act = [];
	$cyc = fetch_cycle($cyc);
	foreach ($cyc["activity"] as $a)
	{
	    $act[] = $a["id"];
	}
	$out = build_activity_sketch($act, $unroll);
	$res = array_merge($res, $out);
    }
    return ($res);
}

