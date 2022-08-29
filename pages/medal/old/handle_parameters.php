<?php

require_once ("fetch_activities_for_medal.php");

$fetch = @try_get($_GET, "a", -1);
$unique = ($fetch != -1);
$sort = boolval(@try_get($_GET, "b", false));
$page = try_get($_GET, "n", 0);
$pagelen = 50;
$where = [];
if (!is_number($page)) // $page contient un tag
{
    $where = ["tags" => $Database->real_escape_string($page)];
    $page = "";
    $pagelen = -1;
}
else if ($page == -1)
    $pagelen = -1;

if ($unique == false)
{
    $fetch = @fetch_data(
	"medal",
	$fetch,
	["name", "description"],
	"codename",
	$sort,
	USE_DELETE_FIELD,
	DONT_PACK_DATA,
	$where,
	["tags"],
	$pagelen,
	$page
    );
    $meds = &$fetch->value;
}
else
{
    $meds = [
	["id" => (int)$fetch]
    ];
    $fetch = @fetch_activities_for_medal($fetch, $sort);
}

foreach ($meds as $i => &$m)
{
    $m["implied"] = db_select_all("
	medal.codename,
        medal.id,
        medal_medal.id as id_medal_medal,
        medal.icon
        FROM medal_medal
        LEFT JOIN medal
        ON medal_medal.id_implied_medal = medal.id
        WHERE medal.deleted IS NULL AND medal_medal.id_medal = ".$m["id"]."
	");
}

if ($unique)
    $fetch->value["implied"] = $meds[0]["implied"];

