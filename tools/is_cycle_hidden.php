<?php

function is_cycle_hidden($user, $cycle)
{
    global $User;

    if (is_admin())
	return (false);
    if (isset($user["id"]))
    {
	if ($user["id"] == $User["id"])
	    return (false);
    }
    else
    {
	if ($user->id == $User["id"])
	    return (false);
    }
    if (isset($user["sublayer"]))
	$sub = $user["sublayer"];
    else
	$sub = $user->sublayer;
    foreach ($sub as $c)
    {
	if (isset($c["id"]))
	{
	    if ($c["id"] == $cycle)
		return ($c["hidden"]);
	}
	else
	{
	    if ($c->id == $cycle)
		return ($c->hidden);
	}
    }
    return (false);
}

