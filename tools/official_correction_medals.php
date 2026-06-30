<?php

function official_correction_medal_activity($activity)
{
    if (is_object($activity) && isset($activity->id))
        return ($activity);

    $act = new FullActivity;
    if ($act->build((int)$activity, false, false, -1) == false)
        return (NULL);
    return ($act);
}

function official_correction_expected_medals($activity)
{
    $activity = official_correction_medal_activity($activity);
    if ($activity == NULL || !isset($activity->medal) || !is_array($activity->medal))
        return ([]);

    $medals = [];
    foreach ($activity->medal as $medal)
    {
        if (!isset($medal["id"]) || !isset($medal["codename"]))
            continue ;
        $id_medal = (int)$medal["id"];
        if ($id_medal <= 0)
            continue ;
        $medals[$id_medal] = [
            "id" => $id_medal,
            "codename" => $medal["codename"],
        ];
    }
    return ($medals);
}

function official_correction_team_students($team_id)
{
    $team_id = (int)$team_id;
    if ($team_id <= 0)
        return ([]);

    return (db_select_all("
        user_team.id_user
        FROM user_team
        WHERE user_team.id_team = $team_id
          AND user_team.status > 0
    "));
}

function official_correction_mark_one_missing_medal($id_user, $id_activity, $id_team, $id_medal)
{
    global $Database;

    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;
    $id_team = (int)$id_team;
    $id_medal = (int)$id_medal;
    if ($id_user <= 0 || $id_activity <= 0 || $id_team <= 0 || $id_medal <= 0)
        return (false);

    $existing = db_select_one("
        id, result
        FROM user_medal
        WHERE id_user = $id_user
          AND id_medal = $id_medal
          AND id_activity = $id_activity
          AND id_team = $id_team
          AND id_user_team = -1
    ");

    if ($existing != NULL && (int)$existing["result"] > 0)
        return (false);

    if ($existing != NULL)
    {
        $Database->query("
            UPDATE user_medal
            SET result = -1, strength = 2
            WHERE id = ".((int)$existing["id"])."
        ");
        return ($Database->affected_rows != 0);
    }

    $Database->query("
        INSERT INTO user_medal
        (id_user, id_medal, id_activity, id_team, id_user_team, result, strength)
        VALUES
        ($id_user, $id_medal, $id_activity, $id_team, -1, -1, 2)
    ");
    return ($Database->affected_rows != 0);
}

function official_correction_mark_missing_medals_as_failed($activity, $team_id)
{
    $activity = official_correction_medal_activity($activity);
    $team_id = (int)$team_id;
    if ($activity == NULL || $team_id <= 0)
        return (0);

    $expected_medals = official_correction_expected_medals($activity);
    if (!count($expected_medals))
        return (0);

    $changed = 0;
    foreach (official_correction_team_students($team_id) as $student)
    {
        $id_user = (int)$student["id_user"];
        foreach ($expected_medals as $medal)
            if (official_correction_mark_one_missing_medal(
                $id_user,
                $activity->id,
                $team_id,
                $medal["id"]
            ))
                $changed += 1;
    }

    if ($changed)
        add_log(TRACE, "Official correction marked $changed missing medal(s) as failed for activity {$activity->id}, team $team_id.", 1);
    return ($changed);
}

function official_correction_create_unknown_medal($codename)
{
    global $Database;
    if (!is_symbol($codename))
        return (new ErrorResponse("InvalidParameter", $codename));

    $escaped = $Database->real_escape_string($codename);
    $existing = db_select_one("id FROM medal WHERE codename = '$escaped'");
    if ($existing != NULL)
        return (new ValueResponse((int)$existing["id"]));

    $Database->query("
        INSERT INTO medal (codename, fr_name, en_name)
        VALUES ('$escaped', '$escaped', '$escaped')
    ");
    add_log(CREATIVE_OPERATION, "Automatically created medal '$codename' from correction report.", 1);
    return (new ValueResponse((int)$Database->insert_id));
}

function official_correction_resolve_or_create_medal($codename)
{
    if (($id_medal = resolve_codename("medal", $codename))->is_error())
        return (official_correction_create_unknown_medal($codename));
    return ($id_medal);
}
