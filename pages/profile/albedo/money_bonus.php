<?php
/////////////////////////////////////////////////////////
/// Prime hebdomadaire de présence physique à l'école ///
/////////////////////////////////////////////////////////

if (!isset($albedo) || $albedo != 1)
    return ;

$paid = user_money_weekly_presence_bonus(35 * 60 * 60, 20);
if ($paid)
    add_log(TRACE, "Weekly presence bonus paid to $paid student(s).", 1);
