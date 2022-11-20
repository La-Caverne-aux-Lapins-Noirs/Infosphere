<?php

function get_full_profile($user, $blist = [], $recalculate = true)
{
    // recalculate n'est pas utilisé actuellement...
    $data = new FullProfile;
    $data->build($user["id"], $blist);
    return ($data);
}

function get_partial_profile($user, $wlist = [], $rec = true)
{
    $ori = [
	"profile",
	"module",
	"school",
	"laboratory",
	"teacher"
    ];
    $blist = [];
    foreach ($ori as $o)
	if (!array_search($o, $wlist))
	    $blist[] = $o;
    return (get_full_profile($user, $blist, $rec));
}
