<?php

function print_day_activities(array $activities)
{
    global $Dictionnary;
    global $Position;
    global $User;

    foreach ($activities as $act)
    {
	$Session = $act->unique_session;
	require ("print_day_activities.phtml");
    }
}
