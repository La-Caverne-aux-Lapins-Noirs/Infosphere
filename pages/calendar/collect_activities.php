<?php

function calendar_standalone_activity(array $row)
{
    global $User;

    $parent = session_standalone_parent($row);
    $session = new FullSession;
    $session->build($row, $parent, $User, true);
    $session->parent = $parent;

    $activity = new stdClass;
    $activity->unique_session = $session;
    $activity->current_subject = true;
    $activity->standalone_session = true;
    return ($activity);
}


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
    $uid = isset($User["id"]) ? (int)$User["id"] : -1;
    $school_visibility_sql = "";
    if (session_school_schema_ready())
        $school_visibility_sql = "
                      OR EXISTS (
                          SELECT 1
                          FROM session_school AS calendar_session_school
                          LEFT JOIN user_school AS calendar_user_school
                            ON calendar_user_school.id_school = calendar_session_school.id_school
                           AND calendar_user_school.id_user = $uid
                          WHERE calendar_session_school.id_session = session.id
                            AND calendar_user_school.id IS NOT NULL
                      )";
    $sesstmp = db_select_all("
        DISTINCT session.*
        FROM session
        LEFT JOIN activity ON session.id_activity = activity.id
        LEFT JOIN user_laboratory AS calendar_laboratory
          ON calendar_laboratory.id_laboratory = session.id_laboratory
         AND calendar_laboratory.id_user = $uid
        WHERE session.begin_date >= '".db_form_date($start)."'
          AND session.end_date <= '".db_form_date($end)."'
          AND session.deleted IS NULL
          AND (
              (session.id_activity > 0 AND activity.deleted IS NULL)
              OR (
                  COALESCE(session.id_activity, 0) <= 0
                  AND (
                      session.id_user = $uid
                      OR calendar_laboratory.id IS NOT NULL
                      $school_visibility_sql
                  )
              )
          )
    ");
    $blist = [
	"activity_acquired_medal",
	"activity_team_content",
	"activity_medal",
	"activity_support",
	"activity_details",
	"activity_texts",
    ];
    foreach ($sesstmp as $sess)
    {
        if (session_is_standalone($sess))
        {
            if (!isset($User["id"]) || !session_is_visible_to_user($sess, (int)$User["id"]))
                continue ;
            $sessions[] = calendar_standalone_activity($sess);
            continue ;
        }
        if (!session_has_activity($sess))
            continue ;

	($s = new FullActivity)->buildp(
	    $sess["id_activity"], [
		"recursive" => false,
		"session_id" => $sess["id"],
		"only_user" => true,
		"blist" => $blist,
	]);
        if (!$s)
            continue ;
	($module = new FullActivity)->buildp(
	    $s->parent_activity, [
		"recursive" => false,
		"only_user" => true,
		"blist" => $blist,
	]);

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
	    if (!isset($s->session_registered->id))
		continue ;
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

	$act->unique_session->local_start = (int)$act->unique_session->local_start;
	$act->unique_session->local_end = (int)$act->unique_session->local_end;
	
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
