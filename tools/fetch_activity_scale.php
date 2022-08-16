<?php

function fetch_activity_scale($id, $by_name)
{
    global $Language;

    return (db_select_all("
	scale.*
        FROM activity_scale
        LEFT JOIN scale ON activity_scale.id_scale = scale.id
        WHERE activity_scale.id_activity = $id
    ", $by_name ? "codename" : ""));
}

