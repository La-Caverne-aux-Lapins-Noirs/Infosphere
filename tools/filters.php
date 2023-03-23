<?php

function test_single_filter($session, $wlist, $field)
{
    // Si le filtre n'est pas défini, alors on ne retire pas la proposition
    if (!isset($wlist[$field]))
	return (false);
    // On parcoure toutes les propositions, si on en trouve une, on ne retire pas l'element
    foreach ($session->$field as $cyc)
    {
	// Si on trouve, on garde.
	if (array_search($cyc["id_$field"], $wlist[$field]) !== false)
	    return (false);
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
