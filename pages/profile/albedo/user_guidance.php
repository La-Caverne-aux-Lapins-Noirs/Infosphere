<?php
if (!isset($albedo) || $albedo != 1)
    return ;

// Albedo orienté utilisateurs: messages privés + médailles automatiques.
// Les seuils sont volontairement regroupés ici pour rester simples à ajuster.
define("USER_ALBEDO_ABSENCE_DAYS", 30);
define("USER_ALBEDO_ABSENCE_MIN_TOTAL", 6);
define("USER_ALBEDO_ABSENCE_MIN_COUNT", 3);
define("USER_ALBEDO_ABSENCE_MIN_RATE", 0.25);
define("USER_ALBEDO_MISSED_ACTIVITY_DAYS", 90);
define("USER_ALBEDO_MISSED_ACTIVITY_MIN_COUNT", 5);
define("USER_ALBEDO_MIN_MATTERS", 3);
define("USER_ALBEDO_PROGRESS_DAYS", 14);
define("USER_ALBEDO_PROGRESS_AVAILABLE_DAYS", 30);
define("USER_ALBEDO_PROGRESS_MIN_AVAILABLE", 3);
define("USER_ALBEDO_WORK_DAYS", 14);
define("USER_ALBEDO_WORK_SUSTAIN_DAYS", 30);
define("USER_ALBEDO_DEFAULT_COOLDOWN_DAYS", 14);

function user_albedo_sql_date_days_ago($days)
{
    return (db_form_date(now() - (int)$days * 60 * 60 * 24));
}

function user_albedo_current_students()
{
    return (db_select_all("\n        user.id, user.codename, user.nickname, user.first_name, user.family_name,\n        MIN(cycle.first_day) as first_cycle_day,\n        GROUP_CONCAT(DISTINCT cycle.id ORDER BY cycle.id SEPARATOR ',') as cycle_ids\n        FROM user\n        LEFT JOIN user_cycle ON user_cycle.id_user = user.id\n        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle\n        WHERE user.deleted IS NULL\n          AND user.password != ''\n          AND user.profile_status = 'member'\n          AND user.authority >= 0\n          AND cycle.id IS NOT NULL\n          AND cycle.deleted IS NULL\n          AND (cycle.done IS NULL OR cycle.done = 0)\n          AND (cycle.first_day IS NULL OR cycle.first_day <= NOW())\n          AND (cycle.first_day IS NULL OR DATE_ADD(cycle.first_day, INTERVAL 15 WEEK) >= NOW())\n        GROUP BY user.id\n        ORDER BY user.codename ASC\n    "));
}

function user_albedo_contexts($student)
{
    $contexts = [["user", (int)$student["id"]]];
    foreach (explode(",", (string)try_get($student, "cycle_ids", "")) as $id_cycle)
    {
        $id_cycle = (int)$id_cycle;
        if ($id_cycle > 0)
            $contexts[] = ["cycle", $id_cycle];
    }
    return ($contexts);
}

function user_albedo_mark_resolved($id_user, $condition_key)
{
    global $Database;

    $id_user = (int)$id_user;
    $condition_key = $Database->real_escape_string($condition_key);
    $Database->query("\n        UPDATE user_guidance\n        SET active = 0, resolved_date = NOW(), last_seen = NOW()\n        WHERE id_user = $id_user\n          AND condition_key = '$condition_key'\n          AND active = 1\n    ");
}

function user_albedo_load_state($id_user, $condition_key)
{
    global $Database;

    $id_user = (int)$id_user;
    $condition_key = $Database->real_escape_string($condition_key);
    return (db_select_one("\n        * FROM user_guidance\n        WHERE id_user = $id_user\n          AND condition_key = '$condition_key'\n        LIMIT 1\n    "));
}

function user_albedo_save_state($id_user, $condition_key, $severity, $state_hash, $score, $details)
{
    global $Database;

    $id_user = (int)$id_user;
    $severity = (int)$severity;
    $score_sql = $score === NULL ? "NULL" : (float)$score;
    $condition_key = $Database->real_escape_string($condition_key);
    $state_hash = $Database->real_escape_string($state_hash);
    $details = $Database->real_escape_string($details);

    $existing = user_albedo_load_state($id_user, $condition_key);
    if ($existing == NULL)
    {
        $Database->query("\n            INSERT INTO user_guidance\n            (id_user, condition_key, severity, state_hash, active, first_seen, last_seen, score, details)\n            VALUES\n            ($id_user, '$condition_key', $severity, '$state_hash', 1, NOW(), NOW(), $score_sql, '$details')\n        ");
        return (db_select_one("* FROM user_guidance WHERE id = ".((int)$Database->insert_id)));
    }

    $reset_first_seen = (int)$existing["active"] == 0 ? ", first_seen = NOW()" : "";
    $Database->query("\n        UPDATE user_guidance\n        SET severity = $severity,\n            state_hash = '$state_hash',\n            active = 1,\n            resolved_date = NULL,\n            last_seen = NOW(),\n            score = $score_sql,\n            details = '$details'\n            $reset_first_seen\n        WHERE id = ".((int)$existing["id"])."\n    ");
    return (user_albedo_load_state($id_user, $condition_key));
}

function user_albedo_date_old_enough($date, $days)
{
    if ($date == NULL || $date == "")
        return (true);
    return (date_to_timestamp($date) <= now() - (int)$days * 60 * 60 * 24);
}

function user_albedo_default_medal_command($codename)
{
    global $Configuration;

    return ("genicon sband ".$codename." -c ".$Configuration->MedalsDir(".ressources").".default_style.dab");
}

function user_albedo_generate_medal_icon($codename)
{
    global $Configuration;

    if (!is_symbol($codename))
        return (false);
    $target = $Configuration->MedalsDir($codename);
    $icon = $target."icon.png";
    if (file_exists($icon))
        return (true);

    new_directory($icon);
    $conf = $Configuration->MedalsDir(".ressources").".default_style.dab";
    $command = "DISPLAY=:1 genicon sband ".escapeshellarg($codename)
        ." -c ".escapeshellarg($conf)
        ." > ".escapeshellarg($icon);
    system($command, $status);
    return ($status == 0 && file_exists($icon));
}

function user_albedo_ensure_medal($codename, $fr_name, $fr_description, $en_name = NULL, $en_description = NULL, $positive = true)
{
    global $Database;

    if (!is_symbol($codename))
        return (NULL);
    $codename_sql = $Database->real_escape_string($codename);
    $type = $positive ? 0 : 1;
    $command = user_albedo_default_medal_command($codename);
    $command_sql = $Database->real_escape_string($command);
    $existing = db_select_one("id, tags, type, command FROM medal WHERE codename = '$codename_sql' LIMIT 1");
    if ($existing != NULL)
    {
        $updates = [];
        $tags = trim((string)try_get($existing, "tags", ""));
        if ($tags == "")
            $updates[] = "tags = 'albedo'";
        else if (!in_array("albedo", array_map("trim", explode(",", $tags)), true))
            $updates[] = "tags = '".$Database->real_escape_string($tags.",albedo")."'";
        if (!isset($existing["type"]) || $existing["type"] === NULL || !is_between((int)$existing["type"], 0, 2))
            $updates[] = "type = $type";
        if (trim((string)try_get($existing, "command", "")) == "")
            $updates[] = "command = '$command_sql'";
        if (count($updates))
            $Database->query("UPDATE medal SET ".implode(", ", $updates)." WHERE id = ".((int)$existing["id"]));
        user_albedo_generate_medal_icon($codename);
        return ((int)$existing["id"]);
    }

    $fr_name = $Database->real_escape_string($fr_name);
    $fr_description = $Database->real_escape_string($fr_description);
    $en_name = $Database->real_escape_string($en_name === NULL ? $fr_name : $en_name);
    $en_description = $Database->real_escape_string($en_description === NULL ? $fr_description : $en_description);
    $Database->query("
        INSERT INTO medal
        (codename, tags, type, command, fr_name, fr_description, en_name, en_description)
        VALUES
        ('$codename_sql', 'albedo', $type, '$command_sql', '$fr_name', '$fr_description', '$en_name', '$en_description')
    ");
    $id = (int)$Database->insert_id;
    user_albedo_generate_medal_icon($codename);
    return ($id);
}

function user_albedo_award_medal($id_user, $medal, $positive = true)
{
    global $Database;

    $id_user = (int)$id_user;
    $id_medal = user_albedo_ensure_medal(
        $medal["codename"],
        $medal["fr_name"],
        $medal["fr_description"],
        isset($medal["en_name"]) ? $medal["en_name"] : NULL,
        isset($medal["en_description"]) ? $medal["en_description"] : NULL,
        $positive
    );
    if ($id_user <= 0 || $id_medal == NULL || $id_medal <= 0)
        return (false);

    $result = $positive ? 1 : -1;
    $existing = db_select_one("\n        id FROM user_medal\n        WHERE id_user = $id_user\n          AND id_medal = $id_medal\n          AND id_activity = -1\n          AND id_team = -1\n          AND id_user_team = -1\n        LIMIT 1\n    ");
    if ($existing != NULL)
        return (false);

    $Database->query("\n        INSERT INTO user_medal\n        (id_user, id_medal, id_activity, id_team, id_user_team, result, strength)\n        VALUES\n        ($id_user, $id_medal, -1, -1, -1, $result, 2)\n    ");
    return ($Database->affected_rows != 0);
}

function user_albedo_private_message($id_user, $title, $message)
{
    global $Database;

    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    $title = intercom_message_text($title);
    $message = intercom_message_text($message);
    if (strlen($title) < 3 || strlen($message) < 2)
        return (false);

    $Database->query("\n        INSERT INTO message\n        (id_user, id_laboratory, visibility, misc_type, id_misc, id_message, title, message)\n        VALUES\n        (1, NULL, ".INTERCOM_PRIVATE.", 'user', $id_user, NULL, '$title', '$message')\n    ");
    return ((int)$Database->insert_id);
}

function user_albedo_trigger($student, $condition_key, $severity, $score, $details, $title, $message, $medal, $positive_medal = false, $cooldown_days = USER_ALBEDO_DEFAULT_COOLDOWN_DAYS, $min_active_days = 0)
{
    global $Database;

    $id_user = (int)$student["id"];
    $state_hash = sha1($condition_key."|".$severity."|".$details);
    $state = user_albedo_save_state($id_user, $condition_key, $severity, $state_hash, $score, $details);
    if ($state == NULL)
        return (false);

    if ($min_active_days > 0 && !user_albedo_date_old_enough($state["first_seen"], $min_active_days))
        return (false);

    $same_state = isset($state["last_message_date"]) && $state["state_hash"] == $state_hash;
    $can_message = user_albedo_date_old_enough($state["last_message_date"], $cooldown_days);
    if ($can_message)
    {
        $id_message = user_albedo_private_message($id_user, $title, $message);
        if ($id_message)
        {
            $Database->query("\n                UPDATE user_guidance\n                SET last_message_date = NOW()\n                WHERE id = ".((int)$state["id"])."\n            ");
            add_log(TRACE, "Albedo user guidance message '$condition_key' sent to {$student["codename"]} #$id_user", 1, user_albedo_contexts($student));
        }
    }

    if ($medal != NULL && user_albedo_date_old_enough($state["last_medal_date"], 3650))
    {
        if (user_albedo_award_medal($id_user, $medal, $positive_medal))
        {
            $Database->query("\n                UPDATE user_guidance\n                SET last_medal_date = NOW()\n                WHERE id = ".((int)$state["id"])."\n            ");
            add_log(TRACE, "Albedo user guidance medal '{$medal["codename"]}' set for {$student["codename"]} #$id_user", 1, user_albedo_contexts($student));
        }
    }
    return (true);
}

function user_albedo_medal($codename, $fr_name, $fr_description)
{
    return ([
        "codename" => $codename,
        "fr_name" => $fr_name,
        "fr_description" => $fr_description,
        "en_name" => $codename,
        "en_description" => $fr_description,
    ]);
}

function user_albedo_absence_metrics($id_user)
{
    $id_user = (int)$id_user;
    $since = user_albedo_sql_date_days_ago(USER_ALBEDO_ABSENCE_DAYS);
    $row = db_select_one("\n        COUNT(*) as total,\n        SUM(CASE WHEN team.present = -2 THEN 1 ELSE 0 END) as absent\n        FROM team\n        LEFT JOIN user_team ON user_team.id_team = team.id\n        LEFT JOIN session ON session.id = team.id_session\n        WHERE user_team.id_user = $id_user\n          AND user_team.status > 0\n          AND team.present IN (1, -1, -2)\n          AND session.id IS NOT NULL\n          AND session.deleted IS NULL\n          AND session.end_date >= '$since'\n          AND session.end_date < NOW()\n    ");
    $total = (int)try_get($row, "total", 0);
    $absent = (int)try_get($row, "absent", 0);
    $rate = $total > 0 ? $absent / $total : 0;
    return (["total" => $total, "absent" => $absent, "rate" => $rate]);
}

function user_albedo_missed_activity_metrics($id_user)
{
    $id_user = (int)$id_user;
    $since = user_albedo_sql_date_days_ago(USER_ALBEDO_MISSED_ACTIVITY_DAYS);
    $row = db_select_one("\n        COUNT(DISTINCT child.id) as missed\n        FROM activity as child\n        LEFT JOIN activity as matter ON matter.id = child.parent_activity\n        LEFT JOIN team as matter_team ON matter_team.id_activity = matter.id\n        LEFT JOIN user_team as matter_user_team\n          ON matter_user_team.id_team = matter_team.id\n         AND matter_user_team.id_user = $id_user\n         AND matter_user_team.status > 0\n        WHERE child.deleted IS NULL\n          AND matter.deleted IS NULL\n          AND child.parent_activity IS NOT NULL\n          AND child.parent_activity != -1\n          AND matter_user_team.id IS NOT NULL\n          AND (child.close_date IS NOT NULL AND child.close_date >= '$since' AND child.close_date < NOW())\n          AND NOT EXISTS (\n              SELECT own_user_team.id\n              FROM team as own_team\n              LEFT JOIN user_team as own_user_team\n                ON own_user_team.id_team = own_team.id\n               AND own_user_team.id_user = $id_user\n               AND own_user_team.status > 0\n              WHERE own_team.id_activity = child.id\n                AND own_user_team.id IS NOT NULL\n          )\n    ");
    return (["missed" => (int)try_get($row, "missed", 0)]);
}

function user_albedo_matter_choice_metrics($id_user)
{
    $id_user = (int)$id_user;
    $row = db_select_one("\n        COUNT(DISTINCT matter.id) as total,\n        COUNT(DISTINCT CASE\n            WHEN (matter.registration_date IS NULL OR matter.registration_date <= NOW())\n             AND (matter.close_date IS NULL OR matter.close_date >= NOW())\n            THEN matter.id ELSE NULL END) as open_count,\n        COUNT(DISTINCT chosen_team.id_activity) as chosen\n        FROM user_cycle\n        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle\n        LEFT JOIN activity_cycle ON activity_cycle.id_cycle = cycle.id\n        LEFT JOIN activity as matter ON matter.id = activity_cycle.id_activity\n        LEFT JOIN team as chosen_team ON chosen_team.id_activity = matter.id\n        LEFT JOIN user_team as chosen_user_team\n          ON chosen_user_team.id_team = chosen_team.id\n         AND chosen_user_team.id_user = $id_user\n         AND chosen_user_team.status > 0\n        WHERE user_cycle.id_user = $id_user\n          AND cycle.deleted IS NULL\n          AND (cycle.done IS NULL OR cycle.done = 0)\n          AND (cycle.first_day IS NULL OR cycle.first_day <= NOW())\n          AND (cycle.first_day IS NULL OR DATE_ADD(cycle.first_day, INTERVAL 15 WEEK) >= NOW())\n          AND matter.id IS NOT NULL\n          AND matter.deleted IS NULL\n          AND (matter.parent_activity IS NULL OR matter.parent_activity = -1)\n    ");
    return ([
        "total" => (int)try_get($row, "total", 0),
        "open" => (int)try_get($row, "open_count", 0),
        "chosen" => (int)try_get($row, "chosen", 0),
    ]);
}

function user_albedo_no_medal_days($student)
{
    $id_user = (int)$student["id"];
    $row = db_select_one("\n        MAX(insert_date) as last_medal\n        FROM user_medal\n        WHERE id_user = $id_user\n          AND result > 0\n    ");
    $reference = try_get($row, "last_medal", NULL);
    if ($reference == NULL || $reference == "")
        $reference = try_get($student, "first_cycle_day", NULL);
    if ($reference == NULL || $reference == "")
        return (0);
    return (floor((now() - date_to_timestamp($reference)) / (60 * 60 * 24)));
}

function user_albedo_progress_metrics($id_user)
{
    $id_user = (int)$id_user;
    $since_acquired = user_albedo_sql_date_days_ago(USER_ALBEDO_PROGRESS_DAYS);
    $since_available = user_albedo_sql_date_days_ago(USER_ALBEDO_PROGRESS_AVAILABLE_DAYS);
    $acquired = db_select_one("\n        COUNT(*) as count\n        FROM user_medal\n        WHERE id_user = $id_user\n          AND result > 0\n          AND insert_date >= '$since_acquired'\n    ");
    $available = db_select_one("\n        COUNT(DISTINCT activity_medal.id) as count\n        FROM activity_medal\n        LEFT JOIN activity ON activity.id = activity_medal.id_activity\n        LEFT JOIN team ON team.id_activity = activity.id\n        LEFT JOIN user_team ON user_team.id_team = team.id\n        LEFT JOIN session ON session.id = team.id_session\n        WHERE user_team.id_user = $id_user\n          AND user_team.status > 0\n          AND activity.deleted IS NULL\n          AND activity_medal.role >= 0\n          AND (\n              (session.end_date IS NOT NULL AND session.end_date >= '$since_available' AND session.end_date <= NOW())\n              OR (activity.pickup_date IS NOT NULL AND activity.pickup_date >= '$since_available' AND activity.pickup_date <= NOW())\n              OR (activity.done_date IS NOT NULL AND activity.done_date >= '$since_available' AND activity.done_date <= NOW())\n          )\n    ");
    $acq = (int)try_get($acquired, "count", 0);
    $avail = (int)try_get($available, "count", 0);
    return (["acquired" => $acq, "available" => $avail, "ratio" => $avail > 0 ? $acq / $avail : 0]);
}

function user_albedo_work_hours($id_user)
{
    $id_user = (int)$id_user;
    $since = user_albedo_sql_date_days_ago(USER_ALBEDO_WORK_DAYS - 1);
    $types = function_exists("user_log_valid_activity_types") ? implode(",", user_log_valid_activity_types()) : "0,1,2";
    $row = db_select_one("\n        SUM(duration) as duration\n        FROM user_log\n        WHERE id_user = $id_user\n          AND type IN ($types)\n          AND log_date >= '$since'\n          AND log_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)\n    ");
    return (((int)try_get($row, "duration", 0)) / (60 * 60));
}

function user_albedo_process_student($student)
{
    $id_user = (int)$student["id"];
    $active = [];

    $absence = user_albedo_absence_metrics($id_user);
    if ($absence["total"] >= USER_ALBEDO_ABSENCE_MIN_TOTAL
        && $absence["absent"] >= USER_ALBEDO_ABSENCE_MIN_COUNT
        && $absence["rate"] >= USER_ALBEDO_ABSENCE_MIN_RATE)
    {
        $active[] = "absence_warning";
        user_albedo_trigger(
            $student,
            "absence_warning",
            2,
            $absence["rate"],
            json_encode($absence),
            "Point d'assiduité",
            "Nous avons détecté plusieurs absences récentes. Si quelque chose bloque ton travail ou ta présence, réponds à ce message pour prévenir l'équipe pédagogique.",
            user_albedo_medal("albedo_absence_warning", "Alerte assiduité", "Signal automatique: trop d’absences récentes."),
            false,
            14
        );
    }

    $missed = user_albedo_missed_activity_metrics($id_user);
    if ($missed["missed"] >= USER_ALBEDO_MISSED_ACTIVITY_MIN_COUNT)
    {
        $active[] = "missed_past_activity";
        user_albedo_trigger(
            $student,
            "missed_past_activity",
            2,
            $missed["missed"],
            json_encode($missed),
            "Activités non rejointes",
            "Plusieurs activités passées de matières que tu as choisies semblent ne pas avoir été rejointes. Vérifie tes inscriptions aux activités et demande de l'aide si quelque chose n'est pas clair.",
            user_albedo_medal("albedo_missed_past_activity", "Activités manquées", "Signal automatique: plusieurs activités passées sans inscription."),
            false,
            14
        );
    }

    $matters = user_albedo_matter_choice_metrics($id_user);
    if ($matters["chosen"] < USER_ALBEDO_MIN_MATTERS
        && $matters["total"] >= USER_ALBEDO_MIN_MATTERS
        && $matters["open"] == 0)
    {
        $active[] = "not_enough_matters";
        user_albedo_trigger(
            $student,
            "not_enough_matters",
            2,
            $matters["chosen"],
            json_encode($matters),
            "Choix de matières à vérifier",
            "Tu sembles avoir choisi peu de matières, et les périodes d'inscription accessibles sont fermées. Contacte l'équipe pédagogique rapidement si ton choix de matières n'est pas volontaire.",
            user_albedo_medal("albedo_not_enough_matters", "Choix de matières insuffisant", "Signal automatique: trop peu de matières choisies et inscriptions fermées."),
            false,
            30
        );
    }

    $days = user_albedo_no_medal_days($student);
    if ($days >= 49)
    {
        $active[] = "reboot_invitation";
        user_albedo_trigger($student, "reboot_invitation", 3, $days, "days=$days", "Invitation au reboot", "Cela fait longtemps qu'aucune médaille n'a été obtenue. On te propose de faire un point de reboot: repartons d'un objectif simple et atteignable cette semaine.", user_albedo_medal("albedo_reboot_invitation", "Invitation au reboot", "Signal automatique: longue période sans médaille."), false, 14);
    }
    else if ($days >= 21)
    {
        $active[] = "big_difficulty";
        user_albedo_trigger($student, "big_difficulty", 2, $days, "days=$days", "Point de difficulté", "Aucune médaille n'a été obtenue depuis plusieurs semaines. Réponds à ce message pour qu'on identifie ensemble le prochain objectif réaliste.", user_albedo_medal("albedo_big_difficulty", "Grosse difficulté détectée", "Signal automatique: aucune médaille depuis plusieurs semaines."), false, 14);
    }
    else if ($days >= 7)
    {
        $active[] = "difficulty";
        user_albedo_trigger($student, "difficulty", 1, $days, "days=$days", "Besoin d'un coup de pouce ?", "Aucune médaille n'a été obtenue depuis plus d'une semaine. Choisis une petite cible et signale si tu veux qu'on t'aide à la débloquer.", user_albedo_medal("albedo_difficulty", "Difficulté détectée", "Signal automatique: aucune médaille récente."), false, 14);
    }

    $progress = user_albedo_progress_metrics($id_user);
    if ($progress["available"] >= USER_ALBEDO_PROGRESS_MIN_AVAILABLE && $progress["ratio"] >= 2.0)
    {
        $active[] = "mega_progress";
        user_albedo_trigger($student, "mega_progress", 3, $progress["ratio"], json_encode($progress), "Méga progression", "Grosse validation de médailles détectée: tu as validé environ deux fois plus de médailles que celles attendues récemment. Très gros rattrapage !", user_albedo_medal("albedo_mega_progress", "Méga progression", "Signal automatique: validation massive de médailles."), true, 30);
    }
    else if ($progress["available"] >= USER_ALBEDO_PROGRESS_MIN_AVAILABLE && $progress["ratio"] >= 1.33)
    {
        $active[] = "super_progress";
        user_albedo_trigger($student, "super_progress", 2, $progress["ratio"], json_encode($progress), "Super progression", "Très belle progression détectée: tu as validé nettement plus de médailles que celles attendues récemment, probablement en récupérant du retard. Continue !", user_albedo_medal("albedo_super_progress", "Super progression", "Signal automatique: validation importante de médailles."), true, 30);
    }

    $hours = user_albedo_work_hours($id_user);
    if ($hours >= 120)
    {
        $active[] = "mega_work";
        user_albedo_trigger($student, "mega_work", 3, $hours, "hours=$hours", "Méga travail", "Ta jauge de travail reste exceptionnellement haute depuis longtemps. C'est impressionnant; pense aussi à préserver ton rythme et ta santé.", user_albedo_medal("albedo_mega_work", "Méga travail", "Signal automatique: jauge de travail durablement exceptionnelle."), true, 30, USER_ALBEDO_WORK_SUSTAIN_DAYS);
    }
    else if ($hours >= 90)
    {
        $active[] = "super_work";
        user_albedo_trigger($student, "super_work", 2, $hours, "hours=$hours", "Super travail", "Ta jauge de travail est très haute de façon durable. Bravo pour l'investissement; garde un rythme soutenable.", user_albedo_medal("albedo_super_work", "Super travail", "Signal automatique: jauge de travail durablement très haute."), true, 30, USER_ALBEDO_WORK_SUSTAIN_DAYS);
    }

    foreach ([
        "absence_warning", "missed_past_activity", "not_enough_matters",
        "difficulty", "big_difficulty", "reboot_invitation",
        "super_progress", "mega_progress", "super_work", "mega_work"
    ] as $condition)
        if (!in_array($condition, $active))
            user_albedo_mark_resolved($id_user, $condition);
}

$processed = 0;
foreach (user_albedo_current_students() as $student)
{
    user_albedo_process_student($student);
    ++$processed;
}
add_log(TRACE, "Albedo user guidance checked $processed current student(s).", 1, [], true);
