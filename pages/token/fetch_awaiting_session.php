<?php

function fetch_awaiting_session()
{
    global $Database;
    global $User;
    global $Language;

    $toks = db_select_all("
       activity.id as id_instance,
       session.id as id_session,
       activity.{$Language}_name as name,
       session.begin_date as begin_date,
       session.end_date as end_date
       FROM team
         LEFT JOIN user_team ON team.id = user_team.id_team
         LEFT JOIN session ON team.id_session = session.id
         LEFT JOIN activity ON activity.id = session.id_activity
         LEFT JOIN token ON token.id_session = session.id
       WHERE user_team.id_user = ".$User["id"]." AND token.invalidation_date > NOW()
       ");
    return (new ValueResponse($toks));
}

