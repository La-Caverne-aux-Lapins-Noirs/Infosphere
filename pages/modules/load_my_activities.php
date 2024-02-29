<?php
$blacklist = [
    "profile",
    "profile_school",
    "profile_laboratory",
    "profile_teacher", // Une version plus light est utilisé dans load_managed

    "cycle_teacher",
    "cycle_school",
    "activity_acquired_medal",
    "activity_presence",
    "activity_delivery",
    "activity_teacher",
    "activity_cycle",
    "activity_team_content",
    "activity_support",
    "activity_details",
];
$user = get_full_profile($User, $blacklist, false, false);

function sort_by_name($a, $b)
{
    if (strlen($a->name))
	$aa = $a->name;
    else if (strlen($a->template_codename))
	$aa = $a->template_codename;
    else
	$aa = $a->codename;
    if (strlen($b->name))
	$bb = $b->name;
    else if (strlen($b->template_codename))
	$bb = $b->template_codename;
    else
	$bb = $b->codename;
    return (strcmp($aa, $bb));
}

uksort($user->merged_sublayers, "sort_year_month_reverse");

$datas = [];
foreach ($user->merged_sublayers as $cycle)
{
    $min_cred = 0;
    $max_cred = 0;
    $matter_to_sort = [];
    foreach ($cycle->cycles as $cyc)
    {
	$fnd = false;
	$out = array_filter(
	    $user->sublayer,
	    function ($elm) use ($cyc) { return $elm->id == $cyc; }
	);
	$out = $out[array_key_first($out)];
	foreach ($out->sublayer as $mod)
	{
	    if ($mod->hidden)
		continue ;
	    if (isset($requested) && $requested != NULL && $requested->id == $mod->id)
		$requested_listed = true;
	    $min_cred += $mod->credit_d;
	    $max_cred += $mod->credit_a;
	    $mod->cycle = $cyc;
	    $matter_to_sort[] = $mod;
	}
    }
    if (count($matter_to_sort) == 0)
	echo $Dictionnary["Empty"];
    uasort($matter_to_sort, "sort_by_name");
    $datas[] = [
	"cycle" => $cycle,
	"matter_to_sort" => $matter_to_sort,
	"min_cred" => $min_cred,
	"max_cred" => $max_cred,
    ];
}
