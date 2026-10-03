<?php

function activity_delivery_final_status_delivered()
{
    return ("automatic_pickup");
}

function activity_delivery_final_status_missing()
{
    return ("missing_delivery");
}

function activity_delivery_ignored_statuses()
{
    return ([
        "automatic_correction",
        "automatic_evaluation",
        activity_delivery_final_status_missing(),
        "deleted",
    ]);
}

function activity_delivery_visible_work_sql($alias = "pickedup_work")
{
    $statuses = array_map(static function ($status) {
        return ("'".str_replace("'", "''", $status)."'");
    }, activity_delivery_ignored_statuses());
    return ("($alias.status IS NULL OR $alias.status NOT IN (".implode(",", $statuses)."))");
}

function activity_delivery_archive_sql($alias = "pickedup_work")
{
    return (activity_delivery_visible_work_sql($alias)."\n"
        ."AND $alias.repository IS NOT NULL\n"
        ."AND $alias.repository != ''");
}

function activity_delivery_row_is_work(array $row)
{
    $status = strtolower(trim((string)($row["status"] ?? "")));
    return (!in_array($status, activity_delivery_ignored_statuses(), true));
}

function activity_delivery_has_work(array $rows)
{
    foreach ($rows as $row)
        if (is_array($row) && activity_delivery_row_is_work($row))
            return (true);
    return (false);
}

function activity_delivery_is_after_pickup($pickup_date)
{
    if ($pickup_date === NULL || $pickup_date === "")
        return (false);
    return (date_to_timestamp($pickup_date) < now());
}

function activity_delivery_team_status($pickup_date, array $rows)
{
    if (activity_delivery_has_work($rows))
        return ("delivered");
    if (activity_delivery_is_after_pickup($pickup_date))
        return ("missing");
    return ("pending");
}

function activity_delivery_response_message($response)
{
    if (!is_array($response))
        return ("");
    foreach (["message", "msg", "error", "content"] as $field)
        if (isset($response[$field]) && trim((string)$response[$field]) != "")
            return (trim((string)$response[$field]));
    return ("");
}

function activity_delivery_is_missing_response($response)
{
    if (!is_array($response) || (($response["result"] ?? "ko") === "ok"))
        return (false);

    $message = strtolower(activity_delivery_response_message($response));
    if ($message === "")
        return (false);
    $message = str_replace(["_", "-"], " ", $message);

    foreach ([
        "nothingturnedin",
        "nothing turned in",
        "no delivery",
        "no work",
        "pas de rendu",
        "empty repository",
        "empty directory",
        "repository is empty",
        "directory is empty",
        "repository not found",
        "directory not found",
        "path not found",
        "path does not exist",
        "no such file or directory",
    ] as $needle)
        if (strpos($message, $needle) !== false)
            return (true);
    return (false);
}

function activity_delivery_record_final($team_id, $delivered, $message = "")
{
    global $Database;

    $team_id = (int)$team_id;
    if ($team_id <= 0)
        return (false);
    $status = $delivered
        ? activity_delivery_final_status_delivered()
        : activity_delivery_final_status_missing();
    $other_status = $delivered
        ? activity_delivery_final_status_missing()
        : activity_delivery_final_status_delivered();
    $escaped = $Database->real_escape_string((string)$message);

    $existing = db_select_one("\n        id\n        FROM pickedup_work\n        WHERE id_team = $team_id\n          AND status = '$status'\n        ORDER BY pickedup_date DESC, id DESC\n    ");
    if ($existing != NULL)
    {
        $id = (int)$existing["id"];
        $ret = $Database->query("\n            UPDATE pickedup_work\n            SET pickedup_date = NOW(), observation = '$escaped', errors = NULL\n            WHERE id = $id\n        ");
    }
    else
        $ret = $Database->query("\n            INSERT INTO pickedup_work\n            (id_team, pickedup_date, repository, status, observation)\n            VALUES ($team_id, NOW(), NULL, '$status', '$escaped')\n        ");

    if ($ret === false || $ret === NULL)
        return (false);

    // A final state supersedes a stale marker of the opposite kind.  This does
    // not remove the no_delivery medal: a late delivery can legitimately exist
    // after a missed deadline.
    $Database->query("\n        UPDATE pickedup_work\n        SET status = 'deleted'\n        WHERE id_team = $team_id\n          AND status = '$other_status'\n    ");
    return (true);
}

function activity_delivery_has_final_marker($team_id)
{
    $team_id = (int)$team_id;
    if ($team_id <= 0)
        return (false);
    $delivered = activity_delivery_final_status_delivered();
    $missing = activity_delivery_final_status_missing();
    return (db_select_one("\n        id FROM pickedup_work\n        WHERE id_team = $team_id\n          AND status IN ('$delivered', '$missing')\n        LIMIT 1\n    ") != NULL);
}

function activity_delivery_no_delivery_medal_id()
{
    $row = db_select_one("\n        id FROM medal\n        WHERE codename = 'no_delivery'\n          AND deleted IS NULL\n        LIMIT 1\n    ");
    return ($row == NULL ? -1 : (int)$row["id"]);
}

function activity_delivery_award_no_delivery($activity_id, $team_id)
{
    global $Database;

    $activity_id = (int)$activity_id;
    $team_id = (int)$team_id;
    $id_medal = activity_delivery_no_delivery_medal_id();
    if ($activity_id <= 0 || $team_id <= 0 || $id_medal <= 0)
        return (0);

    $users = db_select_all("\n        id_user\n        FROM user_team\n        WHERE id_team = $team_id\n          AND status > 0\n          AND id_user > 0\n    ");
    $count = 0;
    foreach ($users as $usr)
    {
        $id_user = (int)$usr["id_user"];
        $existing = db_select_one("\n            id\n            FROM user_medal\n            WHERE id_user = $id_user\n              AND id_medal = $id_medal\n              AND id_activity = $activity_id\n              AND id_team = $team_id\n            ORDER BY id DESC\n            LIMIT 1\n        ");
        if ($existing == NULL)
        {
            $ok = $Database->query("\n                INSERT INTO user_medal\n                (id_user, id_medal, id_activity, id_team, id_user_team, result, strength)\n                VALUES ($id_user, $id_medal, $activity_id, $team_id, -1, 1, 2)\n            ");
            if ($ok !== false && $ok !== NULL)
                ++$count;
        }
        else
        {
            $id = (int)$existing["id"];
            $ok = $Database->query("\n                UPDATE user_medal\n                SET result = 1, strength = 2, insert_date = NOW()\n                WHERE id = $id\n            ");
            if ($ok !== false && $ok !== NULL)
                ++$count;
        }
    }
    return ($count);
}
