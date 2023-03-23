<?php

// Debut et fin indique une etendue dans la base de donnée
// Matin et soir indique les points de départ et fin d'affichage seulement
// Debut et fin DOIVENT etre entre matin et soir
// Slotsize est la granularité des positions
function collect_activities($start, $end, $wlist, $morning, $evening, $slotsize, $is_filtered = false)
{
    global $User;
    global $one_day;

    if ($start < $one_day * 7 * 20) // Si on est en template
    {
	$start += $one_day * 3; // Pour passer du jeudi 01 janvier 70 au 29 décembre 69
	$end += $one_day * 3;
    }

    $sessions = [];
    $total_len = ($evening - $morning) / $slotsize;
    $sesstmp = db_select_all("
        session.id as id, id_activity
        FROM session
        LEFT JOIN activity ON session.id_activity = activity.id
        WHERE session.begin_date >= '".db_form_date($start)."'
          AND session.end_date <= '".db_form_date($end)."'
          AND session.deleted IS NULL
          AND activity.deleted IS NULL
	  ");
    foreach ($sesstmp as $sess)
    {
	($s = new FullActivity)->build($sess["id_activity"], false, false, $sess["id"]);
	($module = new FullActivity)->build($s->parent_activity, false, false);

	/*
	   if (!have_rights($sess["id_activity"], false) && filter_out_sessions($s, $wlist))
	   continue ;
	 */

	// Si filter renvoit faux, c'est que l'activité nous concerne pas en tant qu'éleve
	// Mais si on est assistant ou plus, alors il faut la garder.
	
	if (!$is_filtered)
	{
	    if ($s->is_assistant == false && $module->registered == false)
		continue ;	    
	}
	else if (filter_out_activity($s, $wlist))
	    continue ;
	if ($s->type_type != 2)
	    continue ;
	
	if (datex("G", $s->unique_session->begin_date) < 7)
	    continue ;
	if ($s->registered)
	{
	    if ($s->session_registered->id != -1 && $s->session_registered->id != $sess["id"])
		continue ;
	    if ($s->unique_session->slot_reserved)
	    {
		$s->unique_session->begin_date = date_to_timestamp($s->unique_session->user_slot["begin_date"]);
		$s->unique_session->end_date =  date_to_timestamp($s->unique_session->user_slot["end_date"]);
	    }
	    else if ($s->reference_activity != -1 && $s->unique_session->slot_reserved == false)
	    {
		$s->unique_session->registered = false;
		$s->unique_session->slot_reserved = false;
	    }
	}
	$sessions[] = $s;
    }

    // On compte le nombre d'element en place par créneau (quart d'heure)
    $occupation = [];
    for ($i = 0; $i <= 24 * 4 * $slotsize; $i += $slotsize)
	$occupation[$i / $slotsize] = 0;
    foreach ($sessions as &$act)
    {
	$act->unique_session->local_start = ($act->unique_session->begin_date % $one_day - $morning) / $slotsize; // Numéro de tranche
	if (($duration = $act->unique_session->end_date - $act->unique_session->begin_date) < $slotsize)
	    $duration = 1;
	else
	    $duration = (int)($duration / $slotsize);
	$act->unique_session->local_end = $act->unique_session->local_start + $duration;

	for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	{
	    $occupation[$i] = $occupation[$i] + 1;
	    $left[$i] = 0;
	}
    }

    // On etend maintenant le partage maximal de chaque activité a l'ensemble de sa zone d'occupation
    foreach ($sessions as &$act)
    {
	$max = 0;
	// On calcule le maximum local
	for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	{
	    if ($max < $occupation[$i])
		$max = $occupation[$i];
	}

	// On rebalance le maximum local sur toute la longueur de la session
	for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	    $occupation[$i] = $max;
    }

    // On prealloue un peu d'espace
    $allocator = [];
    $allocator_test = [];
    foreach ($sessions as &$act)
    {
	for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	{
	    $allocator[$i] = array_fill(0, $occupation[$i], 0);
	    $allocator_test[$i] = array_fill(0, $occupation[$i], 0);
	}
    }

    // Fix rapide parceque merde ca marche toujours pas a cause d'un mauvais rangement des putains de trucs de merde chiotte
    foreach ($sessions as &$act)
    {
	$cleft = 0;
	do
	{
	    for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	    {
		if (!isset($allocator_test[$i][$cleft]))
		{
		    $occupation[$i] += 1;
		    $allocator_test[$i][$cleft] = 0;
		    $allocator[$i][$cleft] = 0;
		}
		if ($allocator_test[$i][$cleft] != false)
		    break ;
	    }
	    if ($i == $act->unique_session->local_end)
	    {
		for ($j = $act->unique_session->local_start; $j < $act->unique_session->local_end; ++$j)
		    $allocator_test[$j][$cleft] = 1;
	    }
	    else
		$cleft += 1;
	}
	while  ($i != $act->unique_session->local_end);
    }

    // On va maintenant placer les sessions
    foreach ($sessions as &$act)
    {
	$act->unique_session->top = 100.0 * ($act->unique_session->local_start / $total_len);
	$act->unique_session->height = 100.0 * (($act->unique_session->local_end - $act->unique_session->local_start) / $total_len);

	$act->unique_session->width = 100.0 / $occupation[$act->unique_session->local_start];

	$cleft = 0;
	do
	{
	    for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	    {
		if ($allocator[$i][$cleft] != false)
		    break ;
	    }
	    if ($i == $act->unique_session->local_end)
	    {
		for ($j = $act->unique_session->local_start; $j < $act->unique_session->local_end; ++$j)
		    $allocator[$j][$cleft] = 1;
	    }
	    else
		$cleft += 1;
	}
	while  ($i != $act->unique_session->local_end);

	$act->unique_session->left = $cleft * $act->unique_session->width;

	for ($i = $act->unique_session->local_start; $i < $act->unique_session->local_end; ++$i)
	    $left[$i] = $cleft + 1;
    }
    return ($sessions);
}
