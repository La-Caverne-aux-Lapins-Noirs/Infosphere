<?php

function generate_token($id_session, $number = -1, $invalidation_date = NULL)
{
    global $Database;

    $s = db_select_one("
       session.end_date as end_date,
       COUNT(team.id) as count
       FROM session
       LEFT JOIN team ON session.id = team.id_session
       WHERE session.id = $id_session
       GROUP BY session.id
    ");

    if ($number == -1)
	$number = $s["count"];
    if ($invalidation_date == NULL)
	$invalidation_date = strtotime($s["end_date"]) + (60 * 60 * 2);

    if (($one = db_select_one("id FROM token ORDER BY id DESC")) == false)
	$one["id"] = 1;
    $invalidation_date = db_form_date($invalidation_date);
    for ($i = $one["id"]; $i < $one["id"] + $number; ++$i)
    {
	$Database->query("
          INSERT INTO
          token (id_session, codename, status, invalidation_date)
          VALUES ($id_session, '".hash("md5", $i)."', 0, '$invalidation_date')
	  ");
    }
}
