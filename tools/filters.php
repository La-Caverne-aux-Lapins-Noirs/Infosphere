<?php

function test_single_filter($session, $wlist, $field)
{
    global $one_week;
    
    // Si le filtre n'est pas défini, alors on ne retire pas la proposition
    if (!isset($wlist[$field]))
	return (false);
    // On parcoure toutes les propositions, si on en trouve une, on ne retire pas l'element
    foreach ($session->$field as $cyc)
    {
	// Si on trouve, on garde.
	if (array_search($cyc["id_$field"], $wlist[$field]) !== false)
	{
	    /*
	       Non gérable car trop de variables sont déterminées avant. Le fait que le calendrier
	       soit basé sur les dates et ensuite éliminte les éléments non désirable
	       est un design interessant en tant qu'administrateur mais problématique autrement
	     */
	    /*
	    // Si c'est pour un cycle, il y a peut etre un offset.
	    if ($session->is_template && $field == "cycle")
	    {
		$ac = db_select_one("
		    * FROM activity_cycle
		    WHERE id_cycle = ".$cyc["id_$field"]."
		    AND id_activity = $session->parent_activity
		");
		if (!$ac)
		    return (false);
		foreach ([
		    "emergence", "done", "registration", "close",
		    "subject_appeir", "subject_disappeir", "pickup"
		] as $d)
		{
		    $d = $d."_date";
		    if ($session->$d != NULL)
			$session->$d += $ac["week_shift"] * $one_week;
		}
		foreach ($session->session as &$s)
		{
		    if ($s->begin_date != NULL)
			$s->begin_date += $ac["week_shift"] * $one_week;
		    if ($s->end_date != NULL)
			$s->end_date += $ac["week_shift"] * $one_week;
		}
	     }
	    */
	    return (false);
	}
    }
    // On a pas trouvé la proposition sur un filtre donné, on retire
    return (true);
}

function filter_out_sessions($session, $wlist)
{
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
    return (
	test_single_filter($session, $wlist, "cycle")
	|| test_single_filter($session, $wlist, "teacher")
	|| test_single_filter($session, $wlist, "room")
    );
}
