<?php

function get_full_profile($user, $blist = [], $recalculate = true, $only_registered = true)
{
    global $LoadedProfiles;

    // J'experimente ca pour augmenter les perfs.
    // Ca aura peut etre des effets negatifs: a voir.
    // if (isset($LoadedProfiles[$user["id"]]))
    // return ($LoadedProfiles[$user["id"]]);
    
    // recalculate n'est pas utilisé actuellement...
    $data = new FullProfile;
    $data->build($user["id"], $blist, $only_registered);
    // $LoadedProfiles[$user["id"]] = $data;
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
