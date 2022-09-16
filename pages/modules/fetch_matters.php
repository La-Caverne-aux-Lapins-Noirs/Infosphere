<?php

function fetch_matters($id_user, $id_cycle)
{
    global $Language;

    return (db_select_all("
      activity.id
      FROM activity
      LEFT JOIN activity_cycle ON activity.id = activity_cycle.id_activity
      LEFT JOIN team ON activity.id = team.id_activity
      LEFT JOIN user_team ON user_team.id_team = team.id
        AND user_team.id_user = {$User["id"]}
      WHERE activity_cycle.id_cycle = {$cycle["id"]}
      AND activity.parent_activity = -1
      GROUP BY activity.id
      ORDER BY
        CASE WHEN user_team.id IS NULL
          THEN 0 ELSE 1 END,
      activity.{$Language}_name ASC
      "));
}
