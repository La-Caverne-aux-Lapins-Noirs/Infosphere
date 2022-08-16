<?php

function split_teacher($s)
{
    if (($s = split_symbols($s, ";", true))->is_error())
	return ($s);
    $s = $s->value;
    $groups = [];
    $usrs = [];
    foreach ($s as $i => $l)
    {
	$neg = false;
	if (substr($l, 0, 1) == "-")
	{
	    $neg = true;
	    $l = substr($l, 1);
	}
	if ($l[0] == '#')
	{
	    $l = substr($l, 1);
	    if (($q = resolve_codename("laboratory", $l))->is_error())
		return ($q);
	    if ($neg)
		$groups[] = (int)("-".$q->value);
	    else
		$groups[] = (int)$q->value;
	}
	else
	{
	    if (($q = resolve_codename("user", $l))->is_error())
		return ($q);
	    if ($neg)
		$usrs[] = (int)("-".$q->value);
	    else
		$usrs[] = (int)($q->value);
	}
    }
    return (new ValueResponse(["laboratory" => $groups, "user" => $usrs]));
}
