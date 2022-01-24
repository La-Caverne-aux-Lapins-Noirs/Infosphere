<?php

function enter_token($code)
{
    global $Database;
    global $Language;
    global $User;

    // Le token n'est pas bon
    if (($one = db_select_one("* FROM token WHERE codename = '$code'")) == false)
	return ("InvalidToken");
    // Le token est d'une activité auquel l'utilisateur n'est pas inscrit...
    if (($tok = db_select_one("
       team.id as id
       FROM session
         LEFT JOIN team ON team.id_session = session.id
         LEFT JOIN user_team ON user_team.id_team = team.id
       WHERE user_team.id_user = ".$User["id"]." AND session.id = ".$one["id_session"]."
       ")) == false)
        return ("StolenToken");

    if ($one["status"] != 0)
	return ("TokenAlreadyEntered");

    $Database->query("UPDATE team SET present = 1 WHERE id = ".$tok["id"]);
    if (date_to_timestamp($one["invalidation_date"]) < time())
    {
	$Database->query("UPDATE token SET status = -1 WHERE id = ".$one["id"]);
	return ("TokenEnteredLate");
    }
    $Database->query("UPDATE token SET status = 1 WHERE id = ".$one["id"]);
    return ("");
}

