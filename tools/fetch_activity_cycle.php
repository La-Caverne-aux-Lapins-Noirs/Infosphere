<?php

function fetch_activity_cycle($id, $by_name)
{
    global $Language;

    return (db_select_all("
	cycle.*
        FROM activity_cycle
        LEFT JOIN cycle ON activity_cycle.id_cycle = cycle.id
        WHERE activity_cycle.id_activity = $id
    ", $by_name ? "codename" : ""));
}

