<?php

$data = get_full_profile($user, []);
$dat = [];
foreach ($lst as $k => $v)
{
    $dat[] = $cyc[$k];
}
$lst = $dat;
$Grade = ["E", "D", "C", "B", "A", "S"];
require_once (__DIR__."/report_card_open.phtml");
if (count($data->sublayer) > 1 && 0) // Sabotage
{
    require (__DIR__."/report_card_header.phtml");
    require (__DIR__."/report_card_summary.phtml");
    require (__DIR__."/report_card_footer.phtml");
}
foreach ($data->sublayer as $cycle)
{
    if (in_array($cycle->codename, $lst) == false)
	continue ;
    require (__DIR__."/report_card_header.phtml");
    require (__DIR__."/report_card_cycle.phtml");
    require (__DIR__."/report_card_footer.phtml");
}

require_once (__DIR__."/report_card_close.phtml");

