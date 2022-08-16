<?php

function fetch_activity_skill($id, $by_name)
{
    global $Language;

    return (db_select_all("
	skill.*
        FROM activity_skill
        LEFT JOIN skill ON activity_skill.id_skill = skill.id
        WHERE activity_skill.id_activity = $id
    ", $by_name ? "codename" : ""));
}

