<?php

function collect_short_projects($week, $wlist, $is_filtered = false)
{
    global $User;
    global $one_week;
    global $one_day;
    global $ActivityType;

    $projects = [];
    $start = first_day_of_week($week);
    $end = $start + $one_week - 1;

    if ($start < $one_day * 7 * 53) // Si on est en template
    {
	$start += $one_day * 3; // Pour passer du jeudi 01 janvier 70 au 29 décembre 69
	$end += $one_day * 3;
    }

    $total_len = 7;
    $sesstmp = db_select_all("
       id
       FROM activity
       WHERE subject_appeir_date <= '".db_form_date($end)."'
         AND pickup_date >= '".db_form_date($start)."'
         AND type = 15
         AND deleted IS NULL
 ");
    foreach ($sesstmp as $sess)
    {
	($s = new FullActivity)->build($sess["id"], false, false);
	($module = new FullActivity)->build($s->parent_activity, false, false);
	if (!$is_filtered)
	{
	    if ($s->is_assistant == false && $module->registered == false)
		continue ;	    
	}
	else if (filter_out_activity($s, $wlist))
	    continue ;
	if ($s->type_type != 1 || $ActivityType[$s->type]["id"] != 15)
	    continue ;
	$projects[] = $s;
    }

    // On compte le nombre d'element en place par créneau
    $occupation = [];
    for ($i = 0; $i < 7; $i += 1)
    {
	$occupation[$i] = 0;
    }
    foreach ($projects as &$act)
    {
	if ($act->subject_appeir_date < $start)
	    $act->subject_appeir_date = $start;
	if ($act->pickup_date > $end)
	    $act->pickup_date = $end;

	$act->local_start = (int)(($act->subject_appeir_date - $start) / $one_day); // Numéro de tranche
	$act->local_end = (int)(($act->pickup_date - $start) / $one_day); // Numéro de tranche
	for ($i = $act->local_start; $i <= $act->local_end; ++$i)
	{
	    $occupation[$i] += 1;
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
	if ($act->local_start < 5)
	    $act->left = 100.0 * ($act->local_start / 6.0);
	else
	    $act->left = 100.0 * ((5.0 + ($act->local_start - 5.0) / 2.0) / 6.0);

	$act->width = 0;
	for ($i = $act->local_start; $i <= $act->local_end; ++$i)
	{
	    if ($i < 5)
		$act->width += 100.0 / 6.0;
	    else
		$act->width += (100.0 / 6.0) / 2.0;
	}
	$act->width -= 1.0;
	$act->height = 100.0 / $occupation[$act->local_start];

	$ctop = 0;
	do
	{
	    for ($i = $act->local_start; $i < $act->local_end; ++$i)
	    {
		if ($allocator[$i][$ctop] != false)
		    break ;
	    }
	    if ($i == $act->local_end)
	    {
		for ($j = $act->local_start; $j < $act->local_end; ++$j)
		    $allocator[$j][$ctop] = 1;
	    }
	    else
		$ctop += 1;
	}
	while  ($i != $act->local_end);

	$act->top = $ctop * $act->height;

	for ($i = $act->local_start; $i < $act->local_end; ++$i)
	    $left[$i] = $ctop + 1;
    }
    return ($projects);
}
