<?php

function session_signin_schema_ready()
{
    static $ready = NULL;
    if ($ready === NULL)
    {
        $table = db_select_one("COUNT(*) AS total FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'session_teacher_presence'");
        $columns = db_select_one("COUNT(*) AS total FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'session'
              AND column_name IN ('signin_generated_at', 'signin_id_actor',
                  'signin_morning_end', 'signin_afternoon_start', 'signin_sha256')");
        $ready = $table && (int)$table["total"] === 1
            && $columns && (int)$columns["total"] === 5;
    }
    return ($ready);
}

// La déclaration est propre à une session et à un formateur : elle ne signe pas
// la feuille à sa place. La signature manuscrite reste à recueillir sur papier.
function session_signin_teacher_is_assigned($id_session, $id_user, $activity = NULL)
{
    $session = db_select_one("id, id_activity FROM session WHERE id = ".(int)$id_session." AND deleted IS NULL");
    if (!$session || (int)$session["id_activity"] <= 0)
        return (false);
    $id_activity = (int)$session["id_activity"];
    if ($activity === NULL)
        ($activity = new FullActivity)->build($id_activity, false, false);
    foreach (fetch_session_teachers($id_session, false, true, $id_activity, $activity) as $teacher)
    {
        if ((int)($teacher["id_user"] ?? 0) === (int)$id_user)
            return (true);
        $laboratory = (int)($teacher["id_laboratory"] ?? 0);
        if ($laboratory > 0 && session_laboratory_authority($id_user, $laboratory) >= TEACHER)
            return (true);
    }
    return (false);
}

function session_signin_teacher_button($id_activity, $id_session)
{
    global $User, $Dictionnary;

    if (!session_signin_schema_ready() || !is_array($User)
        || !session_signin_teacher_is_assigned($id_session, $User["id"]))
        return ;
    $today = datex("Y-m-d");
    $declared = db_select_one("id_user FROM session_teacher_presence WHERE id_session = ".(int)$id_session." AND id_user = ".(int)$User["id"]." AND attendance_day = '$today'");
    if ($declared)
    {
        echo '<p>'.session_signin_escape($Dictionnary["SessionTeacherPresenceRecorded"]).'</p>';
        return ;
    }
    $session = db_select_one("begin_date, end_date FROM session WHERE id = ".(int)$id_session." AND deleted IS NULL");
    if (!$session || !period(date_to_timestamp($session["begin_date"]) - 600, date_to_timestamp($session["end_date"])))
    {
        echo '<p>'.session_signin_escape($Dictionnary["SessionTeacherPresencePeriod"]).'</p>';
        return ;
    }
    echo '<form method="put" action="/api/instance/'.(int)$id_activity.'/teacher_presence/'.(int)$id_session.'"'
        .' onsubmit="return silent_submitf(this, {tofill: \'session_teacher_presence_button\'});">'
        .'<input type="submit" class="instance_button" value="'.session_signin_escape($Dictionnary["SessionTeacherPresenceDeclare"]).'" />'
        .'</form>';
}

function session_signin_assigned_teacher_users($id_session, $activity = NULL)
{
    $id_session = (int)$id_session;
    if ($id_session <= 0)
        return ([]);
    $session = db_select_one("id_activity FROM session WHERE id = $id_session AND deleted IS NULL");
    if (!$session || (int)$session["id_activity"] <= 0)
        return ([]);
    $id_activity = (int)$session["id_activity"];
    if ($activity === NULL)
        ($activity = new FullActivity)->build($id_activity, false, false);

    $ids = [];
    foreach (fetch_session_teachers($id_session, false, true, $id_activity, $activity) as $teacher)
    {
        $id_user = (int)($teacher["id_user"] ?? 0);
        if ($id_user > 0)
            $ids[$id_user] = true;

        $id_laboratory = (int)($teacher["id_laboratory"] ?? 0);
        if ($id_laboratory > 0)
            foreach (db_select_all("
                user.id
                FROM user_laboratory
                INNER JOIN user ON user.id = user_laboratory.id_user
                WHERE user_laboratory.id_laboratory = $id_laboratory
                  AND user_laboratory.authority >= ".TEACHER."
                  AND user.deleted IS NULL
                  AND user.profile_status = 'member'
            ") as $member)
                $ids[(int)$member["id"]] = true;
    }
    if (!$ids)
        return ([]);

    return (db_select_all("
        id, first_name, family_name, use_name, codename
        FROM user
        WHERE id IN (".implode(",", array_keys($ids)).")
          AND deleted IS NULL AND profile_status = 'member'
        ORDER BY family_name, first_name, codename
    "));
}

function session_signin_attendance_days($session)
{
    if (is_object($session))
        $session = get_object_vars($session);
    if (!is_array($session))
        return ([]);
    $begin = date_to_timestamp($session["begin_date"] ?? NULL);
    $end = date_to_timestamp($session["end_date"] ?? NULL);
    if (!$begin || !$end || $end <= $begin)
        return ([]);

    $days = [];
    foreach (session_signin_day_bounds($begin, $end) as $bounds)
        $days[datex("Y-m-d", $bounds[0])] = true;
    return (array_keys($days));
}

function session_signin_admin_presence_controls($id_activity, $id_session)
{
    if (!is_admin() || !session_signin_schema_ready())
        return ;
    $id_activity = (int)$id_activity;
    $id_session = (int)$id_session;
    $session = db_select_one("* FROM session WHERE id = $id_session AND id_activity = $id_activity AND deleted IS NULL");
    if (!$session)
        return ;

    ($activity = new FullActivity)->build($id_activity, false, false);
    $teachers = session_signin_assigned_teacher_users($id_session, $activity);
    $days = session_signin_attendance_days($session);
    $presence = [];
    foreach (db_select_all("
        id_user, attendance_day
        FROM session_teacher_presence
        WHERE id_session = $id_session
    ") as $row)
        $presence[(int)$row["id_user"]][(string)$row["attendance_day"]] = true;

    echo '<div id="session_teacher_presence_admin">';
    echo '<h4>Présence des formateurs</h4>';
    echo '<p>Correction administrateur. Toute modification invalide le PDF d’émargement déjà généré, qui devra alors être régénéré.</p>';
    if (!$teachers)
        echo '<p>Aucun formateur affecté à cette session.</p>';
    else if (!$days)
        echo '<p>Les dates de la session sont invalides.</p>';
    else
    {
        echo '<table class="plain_table" style="width: 100%;">';
        echo '<tr><th>Jour</th><th>Formateur</th><th>Présence</th><th>Action</th></tr>';
        foreach ($days as $day)
            foreach ($teachers as $teacher)
            {
                $id_user = (int)$teacher["id"];
                $present = !empty($presence[$id_user][$day]);
                echo '<tr><td>'.session_signin_escape(datex("d/m/Y", date_to_timestamp($day." 12:00:00"))).'</td>';
                echo '<td>'.session_signin_escape(session_signin_person_name($teacher)).'</td>';
                echo '<td>'.($present ? 'Présent' : 'Non déclaré').'</td><td>';
                echo '<form method="put" action="/api/instance/'.$id_activity.'/teacher_presence_admin/'.$id_session.'" '
                    .'onsubmit="return silent_submitf(this, {after_success: function () { window.location.reload(); }});">';
                echo '<input type="hidden" name="teacher" value="'.$id_user.'" />';
                echo '<input type="hidden" name="attendance_day" value="'.session_signin_escape($day).'" />';
                echo '<input type="hidden" name="present" value="'.($present ? '0' : '1').'" />';
                echo '<input type="submit" value="'.($present ? 'Retirer la présence' : 'Marquer présent').'" />';
                echo '</form></td></tr>';
            }
        echo '</table>';
    }
    echo '</div>';
}

function session_signin_cycles($activity)
{
    $cycles = [];
    foreach ($activity->cycle as $cycle)
        if (empty($cycle["is_template"]) && empty($cycle["deleted"]))
            $cycles[(int)$cycle["id"]] = $cycle;
    uasort($cycles, function ($a, $b) {
        return ([(int)$a["cycle"], (int)$a["id"]] <=> [(int)$b["cycle"], (int)$b["id"]]);
    });
    return ($cycles);
}

function session_signin_students($activity, $id_session, array $cycles)
{
    $id_pool = $activity->reference_activity > 0
        ? (int)$activity->reference_activity : (int)$activity->id;
    $filter = $activity->reference_activity > 0
        ? "team.id_activity = $id_pool"
        : "team.id_activity = $id_pool AND team.id_session = ".(int)$id_session;
    $students = db_select_all("
        DISTINCT user.id, user.first_name, user.family_name, user.use_name, user.codename
        FROM team
        INNER JOIN user_team ON user_team.id_team = team.id AND user_team.status > 0
        INNER JOIN user ON user.id = user_team.id_user
        WHERE $filter AND user.deleted IS NULL AND user.profile_status = 'member'
        ORDER BY user.family_name, user.first_name, user.codename
    ");
    $out = array_fill_keys(array_keys($cycles), []);
    foreach ($students as $student)
    {
        $best = NULL;
        foreach (db_select_all("
            cycle.id, cycle.cycle FROM user_cycle
            INNER JOIN cycle ON cycle.id = user_cycle.id_cycle
            WHERE user_cycle.id_user = ".(int)$student["id"]." AND cycle.deleted IS NULL
        ") as $cycle)
        {
            $id = (int)$cycle["id"];
            if (!isset($cycles[$id]))
                continue ;
            if ($best === NULL || [(int)$cycle["cycle"], $id] > [(int)$best["cycle"], (int)$best["id"]])
                $best = $cycle;
        }
        $key = $best === NULL ? 0 : (int)$best["id"];
        if (!isset($out[$key]))
            $out[$key] = [];
        $out[$key][] = $student;
    }
    return ($out);
}

function session_signin_data($activity, $session)
{
    global $Language;

    $cycles = session_signin_cycles($activity);
    $students = session_signin_students($activity, $session->id, $cycles);
    $schools = [];
    foreach ($cycles as $id => $cycle)
    {
        $school = db_select_one("
            school.id FROM school_cycle
            INNER JOIN school ON school.id = school_cycle.id_school
            WHERE school_cycle.id_cycle = $id AND school.deleted IS NULL
            ORDER BY school_cycle.id ASC
        ");
        $schools[$id] = $school ? fetch_school((int)$school["id"]) : NULL;
    }
    $rooms = db_select_all("
        room.id, room.codename, room.capacity, room.{$Language}_name as name,
        school.id as id_school
        FROM session_room
        INNER JOIN room ON room.id = session_room.id_room AND room.deleted IS NULL
        LEFT JOIN school_room ON school_room.id_room = room.id
        LEFT JOIN school ON school.id = school_room.id_school AND school.deleted IS NULL
        WHERE session_room.id_session = ".(int)$session->id."
        ORDER BY room.id
    ");
    $unique_rooms = [];
    foreach ($rooms as $room)
        $unique_rooms[(int)$room["id"]] = $room;
    $rooms = array_values($unique_rooms);

    // L'affectation a été vérifiée au moment de la déclaration. Une modification
    // ultérieure des responsables ne doit pas effacer le formateur historique.
    $trainers = [];
    foreach (db_select_all("
        user.id, user.first_name, user.family_name, user.use_name, user.codename,
        session_teacher_presence.attendance_day
        FROM session_teacher_presence
        INNER JOIN user ON user.id = session_teacher_presence.id_user
        WHERE session_teacher_presence.id_session = ".(int)$session->id."
          AND user.deleted IS NULL AND user.profile_status = 'member'
        ORDER BY user.family_name, user.first_name, user.codename
    ") as $person)
        $trainers[$person["attendance_day"]][] = $person;

    $capacity = max(0, (int)$activity->maximum_subscription, (int)$session->db_maximum_subscription);
    if ($capacity === 0)
    {
        foreach ($rooms as $room)
            $capacity += max(0, (int)$room["capacity"]);
    }
    if ($capacity === 0)
        $capacity = 20;
    return (compact("cycles", "students", "schools", "rooms", "trainers", "capacity"));
}

function session_signin_person_name(array $person)
{
    $name = trim((string)($person["first_name"] ?? "")." ".(string)(($person["use_name"] ?? "") ?: ($person["family_name"] ?? "")));
    return ($name !== "" ? $name : (string)($person["codename"] ?? ""));
}

function session_signin_escape($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}
