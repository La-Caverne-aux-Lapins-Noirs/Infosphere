<?php

function print_projects($projects)
{
    global $Dictionnary;

    foreach ($projects as $act)
    {
	require ("print_projects.phtml");
    }
}
