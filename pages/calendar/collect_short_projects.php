<?php

function collect_short_projects($week, $wlist)
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

	if ($s->is_assistant == false && ($module->registered == false || filter_out_activity($s, $wlist)))
	    continue ;
	if ($s->type_type != 1)
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
	$act->left = 100.0 * ($act->local_start / $total_len);
	$act->width = 100.0 * (($act->local_end - $act->local_start + 1) / $total_len);
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
