<?php

function user_money_add($id_user, $amount, $source_type, $source_key, array $extra = [])
{
    global $Database;
    global $User;

    $id_user = (int)$id_user;
    $amount = (int)$amount;
    if ($id_user <= 0 || $amount == 0)
        return (false);

    $source_type = $Database->real_escape_string($source_type);
    $source_key = $Database->real_escape_string($source_key);
    $comment = $Database->real_escape_string($extra["comment"] ?? "");
    $id_activity = isset($extra["id_activity"]) && $extra["id_activity"] !== NULL ? (int)$extra["id_activity"] : "NULL";
    $id_medal = isset($extra["id_medal"]) && $extra["id_medal"] !== NULL ? (int)$extra["id_medal"] : "NULL";
    $id_team = isset($extra["id_team"]) && $extra["id_team"] !== NULL ? (int)$extra["id_team"] : "NULL";
    $id_actor = isset($extra["id_actor"]) && $extra["id_actor"] !== NULL ? (int)$extra["id_actor"] : (isset($User["id"]) ? (int)$User["id"] : "NULL");

    if (db_select_one("id FROM user_money_log WHERE id_user = $id_user AND source_type = '$source_type' AND source_key = '$source_key'") != NULL)
        return (false);

    if ($Database->query("
        INSERT INTO user_money_log
        (id_user, amount, source_type, source_key, id_activity, id_medal, id_team, id_actor, comment)
        VALUES
        ($id_user, $amount, '$source_type', '$source_key', $id_activity, $id_medal, $id_team, $id_actor, '$comment')
    ") == NULL)
        return (false);

    $Database->query("UPDATE user SET money = money + $amount WHERE id = $id_user");
    add_log(TRACE, "Money change $amount for user $id_user from $source_type/$source_key", 1);
    return (true);
}

function user_money_reward_activity_medal($id_user, $id_activity, $id_medal, $id_team = NULL)
{
    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;
    $id_medal = (int)$id_medal;
    if ($id_user <= 0 || $id_activity <= 0 || $id_medal <= 0)
        return (false);

    $reward = db_select_one("
        activity_medal.money,
        medal.codename as medal_codename,
        activity.codename as activity_codename
        FROM activity_medal
        LEFT JOIN medal ON medal.id = activity_medal.id_medal
        LEFT JOIN activity ON activity.id = activity_medal.id_activity
        WHERE activity_medal.id_activity = $id_activity
          AND activity_medal.id_medal = $id_medal
    ");
    if ($reward == NULL || (int)$reward["money"] <= 0)
        return (false);

    return (user_money_add(
        $id_user,
        (int)$reward["money"],
        "activity_medal",
        "activity:$id_activity:medal:$id_medal",
        [
            "id_activity" => $id_activity,
            "id_medal" => $id_medal,
            "id_team" => $id_team,
            "comment" => "Medal ".($reward["medal_codename"] ?? $id_medal)." on activity ".($reward["activity_codename"] ?? $id_activity),
        ]
    ));
}

function user_money_reward_activity_completion($id_user, $id_activity, $id_team)
{
    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;
    $id_team = (int)$id_team;
    if ($id_user <= 0 || $id_activity <= 0 || $id_team <= 0)
        return (false);

    $activity = db_select_one("codename, money FROM activity WHERE id = $id_activity");
    if ($activity == NULL || (int)$activity["money"] <= 0)
        return (false);

    return (user_money_add(
        $id_user,
        (int)$activity["money"],
        "activity_completion",
        "activity:$id_activity",
        [
            "id_activity" => $id_activity,
            "id_team" => $id_team,
            "comment" => "Completed activity ".($activity["codename"] ?? $id_activity),
        ]
    ));
}

function user_money_reward_completed_activity_for_team($id_activity, $id_team, $work_was_seen = false)
{
    $id_activity = (int)$id_activity;
    $id_team = (int)$id_team;
    if ($id_activity <= 0 || $id_team <= 0)
        return (0);

    $team = db_select_one("id, present FROM team WHERE id = $id_team AND id_activity = $id_activity");
    $present = $team == NULL ? 0 : (int)$team["present"];
    if ($team == NULL || !($present > 0 || $present == -1))
        return (0);

    if (!$work_was_seen && db_select_one("id FROM pickedup_work WHERE id_team = $id_team AND status != 'deleted'") == NULL)
        return (0);

    $users = db_select_all("
        DISTINCT user_team.id_user as id_user
        FROM user_team
        LEFT JOIN user_medal ON user_medal.id_user = user_team.id_user
          AND user_medal.id_team = user_team.id_team
          AND user_medal.id_activity = $id_activity
          AND user_medal.result > 0
        LEFT JOIN activity_medal ON activity_medal.id_activity = $id_activity
          AND activity_medal.id_medal = user_medal.id_medal
          AND activity_medal.role >= 0
        WHERE user_team.id_team = $id_team
          AND user_team.status > 0
          AND activity_medal.id IS NOT NULL
    ");

    $count = 0;
    foreach ($users as $usr)
        if (user_money_reward_activity_completion($usr["id_user"], $id_activity, $id_team))
            ++$count;
    return ($count);
}

function user_money_reward_completed_activity($id_activity)
{
    $id_activity = (int)$id_activity;
    if ($id_activity <= 0)
        return (0);
    $count = 0;
    foreach (db_select_all("id FROM team WHERE id_activity = $id_activity") as $team)
        $count += user_money_reward_completed_activity_for_team($id_activity, $team["id"]);
    return ($count);
}

function user_money_weekly_presence_bonus($threshold_seconds = 126000, $amount = 20)
{
    $now = now();
    // 0 = Sunday. The bonus is intentionally idempotent, so a late Albedo run
    // on Sunday night does not double-pay anyone.
    if ((int)datex("w", $now) != 0 || datex("H:i", $now) < "23:42")
        return (0);

    $week_start = first_day_of_week($now);
    $start = db_form_date($week_start);
    $end = db_form_date($now);
    $source_key = "week:".datex("Y-m-d", $week_start);

    $students = db_select_all("
        user.id as id_user,
        SUM(user_log.duration) as duration
        FROM user_log
        LEFT JOIN user ON user.id = user_log.id_user
        LEFT JOIN user_school ON user_school.id_user = user.id AND user_school.authority = 'STUDENT'
        WHERE user.deleted IS NULL
          AND user_school.id IS NOT NULL
          AND user_log.type = 1
          AND user_log.log_date >= '$start'
          AND user_log.log_date <= '$end'
        GROUP BY user.id
        HAVING duration > $threshold_seconds
    ");

    $count = 0;
    foreach ($students as $student)
        if (user_money_add(
            $student["id_user"],
            $amount,
            "weekly_presence_bonus",
            $source_key,
            ["comment" => "Weekly on-site presence bonus"]
        ))
            ++$count;
    return ($count);
}
