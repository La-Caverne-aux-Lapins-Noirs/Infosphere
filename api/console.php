<?php

function ConsolePing($id, $data, $method, $output, $module)
{
    $version = "";
    require (__DIR__."/../version.php");
    return (new ValueResponse([
        "service" => "infosphere",
        "api" => "console-v1",
        "version" => (string)$version,
        "time" => date(DATE_ATOM),
    ]));
}

function ConsoleSelf($id, $data, $method, $output, $module)
{
    global $User;

    console_token_require_scope("console.read");
    $display_name = trim(((string)($User["first_name"] ?? ""))." ".((string)($User["family_name"] ?? "")));
    if ($display_name == "")
        $display_name = (string)($User["nickname"] ?? ($User["codename"] ?? ""));

    return (new ValueResponse([
        "user" => [
            "id" => (int)$User["id"],
            "codename" => (string)$User["codename"],
            "display_name" => $display_name,
            "first_name" => (string)($User["first_name"] ?? ""),
            "family_name" => (string)($User["family_name"] ?? ""),
            "nickname" => (string)($User["nickname"] ?? ""),
            "profile_status" => (string)($User["profile_status"] ?? "member"),
        ],
        "capabilities" => ["console.read"],
    ]));
}

function console_reference_sql($alias, $value)
{
    global $Database;

    $value = trim((string)$value);
    if ($value == "")
        bad_request();
    $escaped = $Database->real_escape_string($value);
    $parts = ["$alias.codename = '$escaped'"];
    if (ctype_digit($value) && (int)$value > 0)
        $parts[] = "$alias.id = ".(int)$value;
    return ("(".implode(" OR ", $parts).")");
}

function console_person_name(array $row)
{
    $name = trim(((string)($row["first_name"] ?? ""))." ".((string)($row["family_name"] ?? "")));
    if ($name == "")
        $name = (string)($row["nickname"] ?? ($row["codename"] ?? ""));
    return ($name);
}

function console_subject_access_sql($alias = "activity")
{
    global $User;

    $uid = (int)$User["id"];
    $director_authority = user_school_authority_sql("DIRECTOR");
    $assistant = (int)ASSISTANT;

    $enrolled = "EXISTS (
        SELECT 1
        FROM activity_cycle AS console_activity_cycle
        LEFT JOIN user_cycle AS console_user_cycle
          ON console_user_cycle.id_cycle = console_activity_cycle.id_cycle
         AND console_user_cycle.id_user = $uid
        WHERE console_activity_cycle.id_activity = $alias.id
          AND console_user_cycle.id IS NOT NULL
    )";
    $teaching = "EXISTS (
        SELECT 1
        FROM activity_teacher AS console_activity_teacher
        LEFT JOIN user_laboratory AS console_activity_lab
          ON console_activity_lab.id_laboratory = console_activity_teacher.id_laboratory
         AND console_activity_lab.id_user = $uid
         AND console_activity_lab.authority >= $assistant
        WHERE console_activity_teacher.id_activity = $alias.id
          AND (console_activity_teacher.id_user = $uid OR console_activity_lab.id IS NOT NULL)
    )";
    $cycle_director = "EXISTS (
        SELECT 1
        FROM activity_cycle AS console_directed_activity_cycle
        LEFT JOIN cycle_teacher AS console_cycle_teacher
          ON console_cycle_teacher.id_cycle = console_directed_activity_cycle.id_cycle
        LEFT JOIN user_laboratory AS console_cycle_lab
          ON console_cycle_lab.id_laboratory = console_cycle_teacher.id_laboratory
         AND console_cycle_lab.id_user = $uid
         AND console_cycle_lab.authority >= $assistant
        WHERE console_directed_activity_cycle.id_activity = $alias.id
          AND (console_cycle_teacher.id_user = $uid OR console_cycle_lab.id IS NOT NULL)
    )";
    $school_director = "EXISTS (
        SELECT 1
        FROM activity_cycle AS console_school_activity_cycle
        LEFT JOIN school_cycle AS console_school_cycle
          ON console_school_cycle.id_cycle = console_school_activity_cycle.id_cycle
        LEFT JOIN user_school AS console_director_school
          ON console_director_school.id_school = console_school_cycle.id_school
         AND console_director_school.id_user = $uid
         AND console_director_school.authority = $director_authority
        WHERE console_school_activity_cycle.id_activity = $alias.id
          AND console_director_school.id IS NOT NULL
    )";
    $visibility = is_admin()
        ? "1"
        : "(($enrolled) OR ($teaching) OR ($cycle_director) OR ($school_director))";
    return ([
        "enrolled" => $enrolled,
        "teaching" => $teaching,
        "cycle_director" => $cycle_director,
        "school_director" => $school_director,
        "visibility" => $visibility,
    ]);
}

function console_cycle_access_sql($alias = "cycle")
{
    global $User;

    $uid = (int)$User["id"];
    $director_authority = user_school_authority_sql("DIRECTOR");
    $assistant = (int)ASSISTANT;
    $enrolled = "EXISTS (
        SELECT 1 FROM user_cycle AS console_user_cycle
        WHERE console_user_cycle.id_cycle = $alias.id
          AND console_user_cycle.id_user = $uid
    )";
    $managed = "EXISTS (
        SELECT 1
        FROM cycle_teacher AS console_cycle_teacher
        LEFT JOIN user_laboratory AS console_cycle_lab
          ON console_cycle_lab.id_laboratory = console_cycle_teacher.id_laboratory
         AND console_cycle_lab.id_user = $uid
         AND console_cycle_lab.authority >= $assistant
        WHERE console_cycle_teacher.id_cycle = $alias.id
          AND (console_cycle_teacher.id_user = $uid OR console_cycle_lab.id IS NOT NULL)
    )";
    $school_director = "EXISTS (
        SELECT 1
        FROM school_cycle AS console_school_cycle
        LEFT JOIN user_school AS console_director_school
          ON console_director_school.id_school = console_school_cycle.id_school
         AND console_director_school.id_user = $uid
         AND console_director_school.authority = $director_authority
        WHERE console_school_cycle.id_cycle = $alias.id
          AND console_director_school.id IS NOT NULL
    )";
    $visibility = is_admin()
        ? "1"
        : "(($enrolled) OR ($managed) OR ($school_director))";
    return ([
        "enrolled" => $enrolled,
        "managed" => $managed,
        "school_director" => $school_director,
        "visibility" => $visibility,
    ]);
}

function console_room_visibility_sql($alias = "room")
{
    global $User;

    $uid = (int)$User["id"];
    if (is_admin())
        return ("1");
    return ("EXISTS (
        SELECT 1
        FROM school_room AS console_school_room
        LEFT JOIN user_school AS console_user_school
          ON console_user_school.id_school = console_school_room.id_school
         AND console_user_school.id_user = $uid
        WHERE console_school_room.id_room = $alias.id
          AND console_user_school.id IS NOT NULL
    )");
}

function ConsoleSchools($id, $data, $method, $output, $module)
{
    global $User;
    global $Language;

    console_token_require_scope("console.read");
    $id_user = (int)$User["id"];
    $rows = db_select_all("
        school.id,
        school.codename,
        COALESCE(
            NULLIF(organization.{$Language}_name, ''),
            NULLIF(organization.name, ''),
            NULLIF(organization.legal_name, ''),
            school.codename
        ) AS name,
        user_school.authority
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE user_school.id_user = $id_user
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY school.codename, user_school.authority
    ");

    $schools = [];
    foreach ($rows as $row)
    {
        $school_id = (int)$row["id"];
        if (!isset($schools[$school_id]))
            $schools[$school_id] = [
                "id" => $school_id,
                "codename" => (string)$row["codename"],
                "name" => (string)$row["name"],
                "roles" => [],
            ];
        $role = normalize_school_authority($row["authority"] ?? "");
        if ($role != "" && !in_array($role, $schools[$school_id]["roles"], true))
            $schools[$school_id]["roles"][] = $role;
    }
    return (new ValueResponse(["schools" => array_values($schools)]));
}

function ConsoleCycles($id, $data, $method, $output, $module)
{
    global $Language;

    console_token_require_scope("console.read");
    $access = console_cycle_access_sql("cycle");
    $enrolled = $access["enrolled"];
    $managed = $access["managed"];
    $school_director = $access["school_director"];
    $visibility = $access["visibility"];
    $rows = db_select_all("
        cycle.id,
        cycle.codename,
        COALESCE(NULLIF(cycle.{$Language}_name, ''), cycle.codename) AS name,
        cycle.first_day,
        cycle.done,
        CASE WHEN $enrolled THEN 1 ELSE 0 END AS enrolled,
        CASE WHEN $managed THEN 1 ELSE 0 END AS managed,
        CASE WHEN $school_director THEN 1 ELSE 0 END AS school_director
        FROM cycle
        WHERE cycle.deleted IS NULL
          AND ($visibility)
        ORDER BY cycle.first_day DESC, cycle.codename
    ");
    foreach ($rows as &$row)
    {
        $row["id"] = (int)$row["id"];
        $row["enrolled"] = (bool)$row["enrolled"];
        $row["managed"] = (bool)$row["managed"];
        $row["school_director"] = (bool)$row["school_director"];
    }
    unset($row);
    return (new ValueResponse(["cycles" => $rows]));
}

function ConsoleRooms($id, $data, $method, $output, $module)
{
    global $Language;

    console_token_require_scope("console.read");
    $visibility = console_room_visibility_sql("room");
    $rooms = db_select_all("
        DISTINCT room.id, room.codename,
        COALESCE(NULLIF(room.{$Language}_name, ''), room.codename) AS name,
        room.capacity
        FROM room
        WHERE room.deleted IS NULL
          AND ($visibility)
        ORDER BY name, room.codename
    ");
    foreach ($rooms as &$room)
    {
        $room["id"] = (int)$room["id"];
        $room["capacity"] = $room["capacity"] === NULL ? NULL : (int)$room["capacity"];
    }
    unset($room);
    return (new ValueResponse(["rooms" => $rooms]));
}

function ConsoleSubjects($id, $data, $method, $output, $module)
{
    global $Language;

    console_token_require_scope("console.read");
    $access = console_subject_access_sql("activity");
    $enrolled = $access["enrolled"];
    $teaching = $access["teaching"];
    $cycle_director = $access["cycle_director"];
    $school_director = $access["school_director"];
    $visibility = $access["visibility"];

    $subjects = db_select_all("
        activity.id, activity.codename,
        COALESCE(NULLIF(activity.{$Language}_name, ''), activity.codename) AS name,
        CASE WHEN $enrolled THEN 1 ELSE 0 END AS enrolled,
        CASE WHEN $teaching THEN 1 ELSE 0 END AS teaching,
        CASE WHEN $cycle_director THEN 1 ELSE 0 END AS cycle_director,
        CASE WHEN $school_director THEN 1 ELSE 0 END AS school_director
        FROM activity
        WHERE activity.deleted IS NULL
          AND activity.disabled IS NULL
          AND (activity.is_template IS NULL OR activity.is_template = 0)
          AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
          AND (activity.hidden IS NULL OR activity.hidden = 0)
          AND ($visibility)
        ORDER BY activity.codename
    ");
    foreach ($subjects as &$subject)
    {
        $subject["id"] = (int)$subject["id"];
        $roles = [];
        if (is_admin())
            $roles[] = "admin";
        if ((int)$subject["enrolled"])
            $roles[] = "élève";
        if ((int)$subject["teaching"])
            $roles[] = "enseignement";
        if ((int)$subject["cycle_director"])
            $roles[] = "cycle";
        if ((int)$subject["school_director"])
            $roles[] = "direction";
        $subject["roles"] = $roles;
        unset($subject["enrolled"], $subject["teaching"], $subject["cycle_director"], $subject["school_director"]);
    }
    unset($subject);
    return (new ValueResponse(["subjects" => $subjects]));
}

function console_subject_activities($id_subject)
{
    global $Language;

    $id_subject = (int)$id_subject;
    $rows = db_select_all("
        activity.id,
        activity.codename,
        COALESCE(NULLIF(activity.{$Language}_name, ''), activity.codename) AS name,
        activity_type.codename AS type,
        activity.emergence_date,
        activity.registration_date,
        activity.subject_appeir_date,
        activity.close_date,
        activity.pickup_date,
        activity.done_date,
        (
            SELECT MIN(console_session.begin_date)
            FROM session AS console_session
            WHERE console_session.id_activity = activity.id
              AND console_session.deleted IS NULL
              AND console_session.begin_date >= NOW()
        ) AS next_session
        FROM activity
        LEFT JOIN activity_type ON activity_type.id = activity.type
        WHERE activity.parent_activity = $id_subject
          AND activity.deleted IS NULL
          AND activity.disabled IS NULL
          AND (activity.hidden IS NULL OR activity.hidden = 0)
        ORDER BY
          COALESCE(activity.subject_appeir_date, activity.emergence_date, activity.registration_date, activity.done_date, '9999-12-31 23:59:59'),
          activity.codename
    ");
    foreach ($rows as &$row)
        $row["id"] = (int)$row["id"];
    unset($row);
    return ($rows);
}

function console_subject_teachers($id_subject)
{
    global $Language;

    $id_subject = (int)$id_subject;
    $rows = db_select_all("
        'user' AS kind,
        user.id,
        user.codename,
        user.first_name,
        user.family_name,
        user.nickname,
        NULL AS name
        FROM activity_teacher
        LEFT JOIN user ON user.id = activity_teacher.id_user
        WHERE activity_teacher.id_activity = $id_subject
          AND activity_teacher.id_user IS NOT NULL
          AND user.deleted IS NULL
        UNION ALL
        SELECT
        'laboratory' AS kind,
        laboratory.id,
        laboratory.codename,
        NULL AS first_name,
        NULL AS family_name,
        NULL AS nickname,
        COALESCE(NULLIF(laboratory.{$Language}_name, ''), laboratory.codename) AS name
        FROM activity_teacher
        LEFT JOIN laboratory ON laboratory.id = activity_teacher.id_laboratory
        WHERE activity_teacher.id_activity = $id_subject
          AND activity_teacher.id_laboratory IS NOT NULL
          AND laboratory.deleted IS NULL
        ORDER BY kind, codename
    ");
    foreach ($rows as &$row)
    {
        $row["id"] = (int)$row["id"];
        if ($row["kind"] == "user")
            $row["name"] = console_person_name($row);
        unset($row["first_name"], $row["family_name"], $row["nickname"]);
    }
    unset($row);
    return ($rows);
}

function ConsoleSubject($id, $data, $method, $output, $module)
{
    global $Language;

    console_token_require_scope("console.read");
    $reference = $data["subject"] ?? "";
    $where = console_reference_sql("activity", $reference);
    $access = console_subject_access_sql("activity");
    $enrolled = $access["enrolled"];
    $teaching = $access["teaching"];
    $cycle_director = $access["cycle_director"];
    $school_director = $access["school_director"];
    $visibility = $access["visibility"];
    $subject = db_select_one("
        activity.id, activity.codename,
        COALESCE(NULLIF(activity.{$Language}_name, ''), activity.codename) AS name,
        activity.{$Language}_description AS description,
        activity.{$Language}_objective AS objective,
        activity.emergence_date,
        activity.registration_date,
        activity.close_date,
        activity.done_date,
        activity.estimated_work_duration,
        activity.automatic_correction_frequency,
        CASE WHEN $enrolled THEN 1 ELSE 0 END AS enrolled,
        CASE WHEN $teaching THEN 1 ELSE 0 END AS teaching,
        CASE WHEN $cycle_director THEN 1 ELSE 0 END AS cycle_director,
        CASE WHEN $school_director THEN 1 ELSE 0 END AS school_director
        FROM activity
        WHERE $where
          AND activity.deleted IS NULL
          AND activity.disabled IS NULL
          AND (activity.is_template IS NULL OR activity.is_template = 0)
          AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
          AND (activity.hidden IS NULL OR activity.hidden = 0)
          AND ($visibility)
        LIMIT 1
    ");
    if ($subject == NULL)
        not_found();
    $subject["id"] = (int)$subject["id"];
    foreach (["estimated_work_duration", "automatic_correction_frequency"] as $field)
        $subject[$field] = $subject[$field] === NULL ? NULL : (int)$subject[$field];
    $roles = [];
    if (is_admin())
        $roles[] = "admin";
    if ((int)$subject["enrolled"])
        $roles[] = "élève";
    if ((int)$subject["teaching"])
        $roles[] = "enseignement";
    if ((int)$subject["cycle_director"])
        $roles[] = "cycle";
    if ((int)$subject["school_director"])
        $roles[] = "direction";
    $subject["roles"] = $roles;
    unset($subject["enrolled"], $subject["teaching"], $subject["cycle_director"], $subject["school_director"]);

    $id_subject = (int)$subject["id"];
    $cycles = db_select_all("
        DISTINCT cycle.id, cycle.codename,
        COALESCE(NULLIF(cycle.{$Language}_name, ''), cycle.codename) AS name
        FROM activity_cycle
        LEFT JOIN cycle ON cycle.id = activity_cycle.id_cycle
        WHERE activity_cycle.id_activity = $id_subject
          AND cycle.deleted IS NULL
        ORDER BY cycle.first_day DESC, cycle.codename
    ");
    foreach ($cycles as &$cycle)
        $cycle["id"] = (int)$cycle["id"];
    unset($cycle);
    $activities = console_subject_activities($id_subject);
    $subaction = (string)($data["subaction"] ?? "");
    if ($subaction == "activities")
        return (new ValueResponse(["subject" => $subject, "activities" => $activities]));
    if ($subaction != "")
        bad_request();

    $upcoming = [];
    $now = time();
    foreach ($activities as $activity)
    {
        $candidate = $activity["next_session"] ?: $activity["subject_appeir_date"] ?: $activity["emergence_date"] ?: NULL;
        if ($candidate !== NULL && strtotime($candidate) >= $now)
            $upcoming[] = $activity;
        if (count($upcoming) >= 5)
            break;
    }
    return (new ValueResponse([
        "subject" => $subject,
        "cycles" => $cycles,
        "teachers" => console_subject_teachers($id_subject),
        "activity_count" => count($activities),
        "upcoming_activities" => $upcoming,
    ]));
}

function console_cycle_subjects($id_cycle)
{
    global $Language;

    $id_cycle = (int)$id_cycle;
    $rows = db_select_all("
        DISTINCT activity.id, activity.codename,
        COALESCE(NULLIF(activity.{$Language}_name, ''), activity.codename) AS name,
        activity_cycle.week_shift,
        activity_cycle.cursus
        FROM activity_cycle
        LEFT JOIN activity ON activity.id = activity_cycle.id_activity
        WHERE activity_cycle.id_cycle = $id_cycle
          AND activity.id IS NOT NULL
          AND activity.deleted IS NULL
          AND activity.disabled IS NULL
          AND (activity.is_template IS NULL OR activity.is_template = 0)
          AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
          AND (activity.hidden IS NULL OR activity.hidden = 0)
        ORDER BY activity_cycle.week_shift, activity.codename
    ");
    foreach ($rows as &$row)
    {
        $row["id"] = (int)$row["id"];
        $row["week_shift"] = (int)$row["week_shift"];
    }
    unset($row);
    return ($rows);
}

function console_cycle_teachers($id_cycle)
{
    global $Language;

    $id_cycle = (int)$id_cycle;
    $rows = db_select_all("
        'user' AS kind,
        user.id,
        user.codename,
        user.first_name,
        user.family_name,
        user.nickname,
        NULL AS name
        FROM cycle_teacher
        LEFT JOIN user ON user.id = cycle_teacher.id_user
        WHERE cycle_teacher.id_cycle = $id_cycle
          AND cycle_teacher.id_user IS NOT NULL
          AND user.deleted IS NULL
        UNION ALL
        SELECT
        'laboratory' AS kind,
        laboratory.id,
        laboratory.codename,
        NULL AS first_name,
        NULL AS family_name,
        NULL AS nickname,
        COALESCE(NULLIF(laboratory.{$Language}_name, ''), laboratory.codename) AS name
        FROM cycle_teacher
        LEFT JOIN laboratory ON laboratory.id = cycle_teacher.id_laboratory
        WHERE cycle_teacher.id_cycle = $id_cycle
          AND cycle_teacher.id_laboratory IS NOT NULL
          AND laboratory.deleted IS NULL
        ORDER BY kind, codename
    ");
    foreach ($rows as &$row)
    {
        $row["id"] = (int)$row["id"];
        if ($row["kind"] == "user")
            $row["name"] = console_person_name($row);
        unset($row["first_name"], $row["family_name"], $row["nickname"]);
    }
    unset($row);
    return ($rows);
}

function ConsoleCycle($id, $data, $method, $output, $module)
{
    global $Language;

    console_token_require_scope("console.read");
    $reference = $data["cycle"] ?? "";
    $where = console_reference_sql("cycle", $reference);
    $access = console_cycle_access_sql("cycle");
    $enrolled = $access["enrolled"];
    $managed = $access["managed"];
    $school_director = $access["school_director"];
    $visibility = $access["visibility"];
    $cycle = db_select_one("
        cycle.id, cycle.codename,
        COALESCE(NULLIF(cycle.{$Language}_name, ''), cycle.codename) AS name,
        cycle.{$Language}_description AS description,
        cycle.first_day,
        cycle.cycle,
        cycle.objective,
        cycle.done,
        CASE WHEN $enrolled THEN 1 ELSE 0 END AS enrolled,
        CASE WHEN $managed THEN 1 ELSE 0 END AS managed,
        CASE WHEN $school_director THEN 1 ELSE 0 END AS school_director
        FROM cycle
        WHERE $where
          AND cycle.deleted IS NULL
          AND ($visibility)
        LIMIT 1
    ");
    if ($cycle == NULL)
        not_found();
    $cycle["id"] = (int)$cycle["id"];
    $cycle["cycle"] = (int)$cycle["cycle"];
    $cycle["objective"] = (int)$cycle["objective"];
    $cycle["done"] = (bool)$cycle["done"];
    $roles = [];
    if (is_admin())
        $roles[] = "admin";
    if ((int)$cycle["enrolled"])
        $roles[] = "élève";
    if ((int)$cycle["managed"])
        $roles[] = "encadrement";
    if ((int)$cycle["school_director"])
        $roles[] = "direction";
    $cycle["roles"] = $roles;
    unset($cycle["enrolled"], $cycle["managed"], $cycle["school_director"]);

    $id_cycle = (int)$cycle["id"];
    $subjects = console_cycle_subjects($id_cycle);
    $subaction = (string)($data["subaction"] ?? "");
    if ($subaction == "subjects")
        return (new ValueResponse(["cycle" => $cycle, "subjects" => $subjects]));
    if ($subaction != "")
        bad_request();

    $schools = db_select_all("
        DISTINCT school.id, school.codename,
        COALESCE(NULLIF(school.{$Language}_name, ''), school.codename) AS name
        FROM school_cycle
        LEFT JOIN school ON school.id = school_cycle.id_school
        WHERE school_cycle.id_cycle = $id_cycle
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY school.codename
    ");
    foreach ($schools as &$school)
        $school["id"] = (int)$school["id"];
    unset($school);
    return (new ValueResponse([
        "cycle" => $cycle,
        "schools" => $schools,
        "teachers" => console_cycle_teachers($id_cycle),
        "subject_count" => count($subjects),
    ]));
}

function ConsoleRoom($id, $data, $method, $output, $module)
{
    global $Language;

    console_token_require_scope("console.read");
    $reference = $data["room"] ?? "";
    $where = console_reference_sql("room", $reference);
    $visibility = console_room_visibility_sql("room");
    $room = db_select_one("
        room.id, room.codename,
        COALESCE(NULLIF(room.{$Language}_name, ''), room.codename) AS name,
        room.capacity
        FROM room
        WHERE $where
          AND room.deleted IS NULL
          AND ($visibility)
        LIMIT 1
    ");
    if ($room == NULL)
        not_found();
    $room["id"] = (int)$room["id"];
    $room["capacity"] = $room["capacity"] === NULL ? NULL : (int)$room["capacity"];
    $id_room = (int)$room["id"];
    $schools = db_select_all("
        DISTINCT school.id, school.codename,
        COALESCE(NULLIF(school.{$Language}_name, ''), school.codename) AS name
        FROM school_room
        LEFT JOIN school ON school.id = school_room.id_school
        WHERE school_room.id_room = $id_room
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY school.codename
    ");
    foreach ($schools as &$school)
        $school["id"] = (int)$school["id"];
    unset($school);
    return (new ValueResponse(["room" => $room, "schools" => $schools]));
}

function console_schedule_range($period)
{
    $timezone = new DateTimeZone(calendar_feed_timezone());
    $now = new DateTimeImmutable("now", $timezone);
    if ($period == "today")
        $start = $now->setTime(0, 0, 0);
    else if ($period == "week")
        $start = $now->modify("monday this week")->setTime(0, 0, 0);
    else
        return (NULL);
    $end = $period == "today" ? $start->modify("+1 day") : $start->modify("+7 days");
    return ([$start, $end, $timezone]);
}

function console_schedule_event(array $event, DateTimeZone $timezone)
{
    $out = [];
    foreach (["kind", "id", "activity_id", "role", "summary", "description", "location", "all_day"] as $key)
        if (array_key_exists($key, $event))
            $out[$key] = $event[$key];
    foreach (["id", "activity_id"] as $key)
        if (isset($out[$key]))
            $out[$key] = (int)$out[$key];
    $out["all_day"] = !empty($out["all_day"]);
    foreach (["begin", "end"] as $key)
    {
        $timestamp = isset($event[$key]) ? (int)$event[$key] : 0;
        if ($timestamp <= 0)
            $out[$key] = NULL;
        else
            $out[$key] = (new DateTimeImmutable("@".$timestamp))->setTimezone($timezone)->format(DATE_ATOM);
    }
    return ($out);
}

function ConsoleSchedule($id, $data, $method, $output, $module)
{
    global $User;

    console_token_require_scope("console.read");
    $period = (string)($data["action"] ?? "");
    $range = console_schedule_range($period);
    if ($range === NULL)
        bad_request();
    [$start, $end, $timezone] = $range;

    $user = $User;
    $events = calendar_feed_collect_events($user, $start->getTimestamp(), $end->getTimestamp());
    $out = [];
    foreach ($events as $event)
        $out[] = console_schedule_event($event, $timezone);

    return (new ValueResponse([
        "period" => $period,
        "range" => [
            "start" => $start->format(DATE_ATOM),
            "end" => $end->format(DATE_ATOM),
            "timezone" => $timezone->getName(),
        ],
        "events" => $out,
    ]));
}

function ConsoleListTokens($id, $data, $method, $output, $module)
{
    global $User;

    if (console_token_authenticated())
        forbidden();
    return (new ValueResponse(["tokens" => console_token_list((int)$User["id"])]));
}

function ConsoleCreateToken($id, $data, $method, $output, $module)
{
    global $User;

    header("Cache-Control: no-store");
    header("Pragma: no-cache");
    if (console_token_authenticated())
        forbidden();
    return (console_token_create(
        (int)$User["id"],
        $data["name"] ?? "Terminal",
        "console.read"
    ));
}

function ConsoleRevokeToken($id, $data, $method, $output, $module)
{
    global $User;

    if (console_token_authenticated())
        forbidden();
    $ret = console_token_revoke((int)$id, (int)$User["id"]);
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse(["msg" => "ConsoleTokenRevoked"]));
}

$Tab = [
    "GET" => [
        "ping" => ["everybody", "ConsolePing"],
        "self" => ["logged_in", "ConsoleSelf"],
        "schools" => ["logged_in", "ConsoleSchools"],
        "cycles" => ["logged_in", "ConsoleCycles"],
        "rooms" => ["logged_in", "ConsoleRooms"],
        "subjects" => ["logged_in", "ConsoleSubjects"],
        "subject" => ["logged_in", "ConsoleSubject"],
        "cycle" => ["logged_in", "ConsoleCycle"],
        "room" => ["logged_in", "ConsoleRoom"],
        "today" => ["logged_in", "ConsoleSchedule"],
        "week" => ["logged_in", "ConsoleSchedule"],
        "tokens" => ["logged_in", "ConsoleListTokens"],
    ],
    "POST" => [
        "token" => ["logged_in", "ConsoleCreateToken"],
    ],
    "DELETE" => [
        "token" => ["logged_in", "ConsoleRevokeToken"],
    ],
];
