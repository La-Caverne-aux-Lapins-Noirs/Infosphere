<?php

function _sort_by_medal_origin($a, $b)
{
    $aa = isset($a["templated"]) || isset($a["referenced"]);
    $bb = isset($b["templated"]) || isset($b["referenced"]);
    return ((int)$aa - (int)$bb);
}

function sort_by_medal_origin(&$meds)
{
    usort($meds, "_sort_by_medal_origin");
    return ($meds);
}

