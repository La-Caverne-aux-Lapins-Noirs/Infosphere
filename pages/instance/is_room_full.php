<?php

function get_room_space($session)
{
    if ($session == NULL)
	return (-1);
    $total = 0;
    foreach ($session["room"] as $room)
    {
	if ($room["capacity"] == -1)
	    return (-1);
	$total += $room["capacity"];
    }
    return ($total);
}

function is_room_full($instance, $session, $registered, $places = 1)
{
    return (false); // J'ai du mal a comprendre pourquoi cette fonctionne ne fonctionne pas...
    if ($session == NULL)
	return (false);

    // Si ce sont des rendez vous, le remplissage de la salle n'a pas de sens
    if ($session["slots_count"] != 0)
	return (false);

    if (($total = get_room_space($session)) < 0)
	return (false);
    if ($instance["min_team_size"] < 1)
	$instance["min_team_size"] = 1;
    return ($total < (count($registered) + $places) * $instance["min_team_size"]);
}
