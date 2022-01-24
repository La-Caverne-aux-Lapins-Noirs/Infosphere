<?php

function is_registered($mod, $user)
{
    return (db_select_one("
       team.id as id,
       team.present as present,
       session.begin_date as begin_date
       FROM instance LEFT JOIN team ON instance.id = team.id_instance
       LEFT JOIN user_team ON team.id = user_team.id_team
       LEFT JOIN session ON team.id_session = session.id
       WHERE user_team.id_user = {$user} AND instance.id = {$mod}
    "));
}

function get_first_session_of_instance($mod)
{
    return (db_select_one("
       session.begin_date as begin_date
       FROM session
       WHERE session.id_instance = {$mod}
       ORDER BY session.begin_date ASC
    "));
}
