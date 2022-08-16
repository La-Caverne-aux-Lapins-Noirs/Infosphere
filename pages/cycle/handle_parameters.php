<?php

$cycle = try_get($_GET, "a", -1);
$parent = try_get($_GET, "activity", -1);
$unique = ($cycle != -1);
$sort = boolval(try_get($_GET, "b", false));

$always_unroll = @try_get($_GET, "unroll", false);
if ($cycle == -1)
    $unroll_first = $always_unroll;
else
    $unroll_first = false;

$fetch = @fetch_cycle($cycle, $sort, $unique);
if (($act_code = db_select_one("codename FROM activity WHERE id = ".$parent)) != NULL)
    $act_code = $act_code["codename"];

