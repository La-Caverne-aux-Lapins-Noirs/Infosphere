<?php

function filter_out_activity($session, $wlist)
{
    global $User;

    if (isset($wlist["type"]) && $wlist["type"] == "template")
    {
	if ($session->is_template == false)
	    return (true);
    }
    else
    {
	if ($session->is_template == true)
	    return (true);
    }
    //////////////////////
    // Temporaire : Cette fonction est censé gerer seulement les filtres manuels
    /////////////////////
    foreach ($session->team as $tm)
    {
	if (isset($tm["user"][$User["id"]]))
	    return (false);
    }
    return (test_single_filter($session, $wlist, "cycle") || test_single_filter($session, $wlist, "teacher")
    );
}

function fullpart($x, $y)
{
    return ($x - $x % $y);
}

function collect_projects($start, $end, $wlist)
{
    global $User;
    global $one_week;
    global $ActivityType;
    global $one_day;

    if ($start < $one_day * 7 * 20) // Si on est en template
    {
	$start += $one_day * 3; // Pour passer du jeudi 01 janvier 70 au 29 décembre 69
	$end += $one_day * 3;
    }

    $projects = [];
    $start = first_day_of_week($start);
    $end = first_day_of_week($end);

    if (($total_len = ceil(($end - $start) / $one_week)) < 1)
	$total_len = 0;
    $total_len += 1;
    $sesstmp = db_select_all("
       id
       FROM activity
       WHERE subject_appeir_date <= '".db_form_date($end)."'
         AND pickup_date >= '".db_form_date($start)."'
         AND deleted IS NULL
	 ");
    foreach ($sesstmp as $sess)
    {
	($s = new FullActivity)->build($sess["id"], false, false);
	($module = new FullActivity)->build($s->parent_activity, false, false);
	if ($s->is_assistant == false && ($module->registered == false || filter_out_activity($s, $wlist)))
	    continue ;
	if ($s->type_type != 1 || $ActivityType[$s->type]["id"] == 15)
	    continue ;
	$projects[] = $s;
    }

    // On compte le nombre d'element en place par créneau
    $occupation = [];
    foreach ($projects as &$act)
    {
	if ($act->subject_appeir_date < $start)
	    $act->subject_appeir_date = $start;
	if ($act->pickup_date > $end)
	    $act->pickup_date = $end;

	$act->local_start = floor(($act->subject_appeir_date - $start) / $one_week); // Numéro de tranche
	if (($duration = $act->pickup_date - $act->subject_appeir_date) < $one_week)
	    $duration = 1;
	else
	    $duration = (int)ceil($duration / $one_week);
	$act->local_end = $act->local_start + $duration;

	for ($i = $act->local_start; $i < $act->local_end; ++$i)
	{
	    $occupation[$i] = isset($occupation[$i]) ? $occupation[$i] + 1 : 1;
	    $left[$i] = 0;
	}
    }

    // On etend maintenant le partage maximal de chaque activité a l'ensemble de sa zone d'occupation
    foreach ($projects as &$act)
    {
	$max = 0;
	// On calcule le maximum local
	for ($i = $act->local_start; $i < $act->local_end; ++$i)
	{
	    if ($max < $occupation[$i])
		$max = $occupation[$i];
	}
	// On rebalance le maximum local sur toute la longueur de la session
	for ($i = $act->local_start; $i < $act->local_end; ++$i)
	    $occupation[$i] = $max;
    }

    $allocator = [];
    foreach ($projects as &$act)
    {
	for ($i = $act->local_start; $i < $act->local_end; ++$i)
	    $allocator[$i] = array_fill(0, $occupation[$i], 0);
    }

    // On va maintenant placer les projects
    foreach ($projects as &$act)
    {
	$act->top = 100.0 * ($act->local_start / $total_len);
	$act->height = 100.0 * (($act->local_end - $act->local_start) / $total_len);

	$act->width = 100.0 / $occupation[$act->local_start];

	$cleft = 0;
	do
	{
	    for ($i = $act->local_start; $i < $act->local_end; ++$i)
	    {
		if (@$allocator[$i][$cleft] != false)
		    break ;
	    }
	    if ($i == $act->local_end)
	    {
		for ($j = $act->local_start; $j < $act->local_end; ++$j)
		    $allocator[$j][$cleft] = 1;
	    }
	    else
		$cleft += 1;
	}
	while  ($i != $act->local_end);

	$act->left = $cleft * $act->width;

	for ($i = $act->local_start; $i < $act->local_end; ++$i)
	    $left[$i] = $cleft + 1;
    }
    return ($projects);
}

