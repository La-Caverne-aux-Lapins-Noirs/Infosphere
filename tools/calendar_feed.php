<?php

/*
 * Publication iCalendar du planning personnel.
 *
 * Le token est un bearer token: toute personne qui connait l'URL peut lire le
 * planning. Il doit donc etre long, aleatoire et revocable.
 */

function calendar_feed_random_token()
{
    if (function_exists("random_bytes"))
	return (bin2hex(random_bytes(32)));
    return (bin2hex(openssl_random_pseudo_bytes(32)));
}

function calendar_feed_set_token(array &$user, $rotate = false)
{
    global $Database;

    if (!$rotate && isset($user["calendar_token"]) && strlen($user["calendar_token"]) == 64)
	return ($user["calendar_token"]);

    $token = calendar_feed_random_token();
    $escaped = $Database->real_escape_string($token);
    if (!$Database->query("UPDATE user SET calendar_token = '$escaped' WHERE id = ".(int)$user["id"]))
	return (NULL);
    $user["calendar_token"] = $token;
    return ($token);
}

function calendar_feed_disable(array &$user)
{
    global $Database;

    if (!$Database->query("UPDATE user SET calendar_token = NULL WHERE id = ".(int)$user["id"]))
	return (false);
    $user["calendar_token"] = NULL;
    return (true);
}

function calendar_feed_scheme()
{
    if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]))
    {
	$proto = strtolower(trim(explode(",", $_SERVER["HTTP_X_FORWARDED_PROTO"])[0]));
	if ($proto == "https" || $proto == "http")
	    return ($proto);
    }
    if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "" && strtolower($_SERVER["HTTPS"]) != "off")
	return ("https");
    return ("http");
}

function calendar_feed_host()
{
    $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : $_SERVER["SERVER_NAME"];
    // Evite d'injecter un Host arbitraire dans les URLs affichees / publiees.
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host))
	$host = $_SERVER["SERVER_NAME"];
    return ($host);
}

function calendar_feed_url($token)
{
    return (calendar_feed_scheme()."://".calendar_feed_host()."/calendar.php?token=".rawurlencode($token));
}

function calendar_feed_activity_url($activity, $session = NULL)
{
    $url = calendar_feed_scheme()."://".calendar_feed_host()."/index.php?p=ActivityMenu&a=".(int)$activity;
    if ($session !== NULL)
	$url .= "&b=".(int)$session;
    return ($url);
}

function calendar_feed_calendar_url()
{
    return (calendar_feed_scheme()."://".calendar_feed_host()."/index.php?p=CalendarMenu");
}

function calendar_feed_timezone()
{
    global $Configuration;

    $timezone = "Europe/Paris";
    if (isset($Configuration->Properties["calendar_timezone"]) && $Configuration->Properties["calendar_timezone"] != "")
	$timezone = $Configuration->Properties["calendar_timezone"];
    try
    {
	new DateTimeZone($timezone);
    }
    catch (Exception $e)
    {
	$timezone = "Europe/Paris";
    }
    return ($timezone);
}

function calendar_feed_user_from_token($token)
{
    global $Database;

    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token))
	return (NULL);
    $token = $Database->real_escape_string($token);
    $user = db_select_one("* FROM user WHERE calendar_token = '$token' AND deleted IS NULL");
    if ($user == NULL)
	return (NULL);
    unset($user["password"]);
    unset($user["salt"]);
    unset($user["local_salt"]);
    get_user_public_data($user);
    return ($user);
}

function calendar_feed_teacher_level($teachers, $user_id)
{
    $level = -1;
    foreach ($teachers as $teacher)
    {
	if (isset($teacher["user"]) && is_array($teacher["user"]))
	{
	    foreach ($teacher["user"] as $member)
	    {
		if (isset($member["id"]) && $member["id"] == $user_id &&
		    isset($member["authority"]) && $member["authority"] > $level)
		    $level = $member["authority"];
	    }
	}
	else if (isset($teacher["id"]) && $teacher["id"] == $user_id)
	{
	    $authority = isset($teacher["authority"]) ? $teacher["authority"] : TEACHER;
	    if ($authority > $level)
		$level = $authority;
	}
    }
    return ($level);
}

function calendar_feed_role($activity, $session_id = NULL)
{
    global $User;

    // Ne pas utiliser $activity->is_teacher ici: retrieve_authority() considere
    // un administrateur comme enseignant de toute activite. Le flux personnel
    // doit au contraire ne publier que les enseignements reellement attribues.
    if (calendar_feed_teacher_level($activity->teacher, $User["id"]) >= ASSISTANT)
	return ("teacher");

    if ($activity->registered)
    {
	// Une activite peut proposer plusieurs sessions. Si l'utilisateur est
	// inscrit a l'une d'elles, les autres ne doivent pas apparaitre comme
	// confirmees.
	if ($session_id !== NULL && $activity->session_registered != NULL &&
	    isset($activity->session_registered->id) &&
	    $activity->session_registered->id != -1 &&
	    $activity->session_registered->id != $session_id)
	    return ("none");
	return ("registered");
    }

    // Activite relevant du cursus / de la matiere de l'utilisateur, mais a
    // laquelle il n'est pas inscrit. Elle sera publiee TENTATIVE et ne
    // bloquera pas la disponibilite dans l'agenda externe.
    if ($activity->can_subscribe)
	return ("tentative");
    return ("none");
}

function calendar_feed_blit_activity($id, $session_id = -1)
{
    $blist = [
	"activity_acquired_medal",
	"activity_team_content",
	"activity_medal",
	"activity_support",
	"activity_details",
	"activity_texts",
    ];
    $activity = new FullActivity;
    if (!$activity->buildp((int)$id, [
	"recursive" => false,
	"session_id" => (int)$session_id,
	"only_user" => true,
	"blist" => $blist,
    ]))
	return (NULL);
    return ($activity);
}

function calendar_feed_session_events($start = NULL, $end = NULL)
{
    global $User;

    $events = [];
    $date_filter = "";
    if ($start !== NULL && $end !== NULL)
    {
        $start = (int)$start;
        $end = (int)$end;
        if ($end > $start)
            $date_filter = "
          AND session.begin_date < '".db_form_date($end)."'
          AND session.end_date > '".db_form_date($start)."'";
    }
    $uid = isset($User["id"]) ? (int)$User["id"] : -1;
    $school_visibility_sql = "";
    if (session_school_schema_ready())
        $school_visibility_sql = "
                      OR EXISTS (
                          SELECT 1
                          FROM session_school AS calendar_session_school
                          LEFT JOIN user_school AS calendar_user_school
                            ON calendar_user_school.id_school = calendar_session_school.id_school
                           AND calendar_user_school.id_user = $uid
                          WHERE calendar_session_school.id_session = session.id
                            AND calendar_user_school.id IS NOT NULL
                      )";
    $rows = db_select_all("
        DISTINCT session.*
        FROM session
        LEFT JOIN activity ON activity.id = session.id_activity
        LEFT JOIN activity_type ON activity_type.id = activity.type
        LEFT JOIN user_laboratory AS calendar_laboratory
          ON calendar_laboratory.id_laboratory = session.id_laboratory
         AND calendar_laboratory.id_user = $uid
        WHERE session.deleted IS NULL
          $date_filter
          AND (
              (
                  session.id_activity > 0
                  AND activity.deleted IS NULL
                  AND activity.is_template = 0
                  AND activity_type.type = 2
              )
              OR (
                  COALESCE(session.id_activity, 0) <= 0
                  AND (
                      session.id_user = $uid
                      OR calendar_laboratory.id IS NOT NULL
                      $school_visibility_sql
                  )
              )
          )
        ORDER BY session.begin_date ASC
    ");

    foreach ($rows as $row)
    {
        if (session_is_standalone($row))
        {
            if (!isset($User["id"]) || !session_is_visible_to_user($row, (int)$User["id"]))
                continue ;
            $session = new FullSession;
            if ($session->build_session((int)$row["id"], $User, true) === false)
                continue ;
            if ($session->begin_date === NULL || $session->end_date === NULL)
                continue ;

            $rooms = [];
            foreach ($session->room as $room)
                if (isset($room["name"]) && $room["name"] != "")
                    $rooms[] = $room["name"];

            $kind = $session->standalone_kind;
            $owner = session_standalone_owner_label($row);
            $events[] = [
                "kind" => "session",
                "id" => (int)$session->id,
                "role" => $kind === "laboratory" ? "laboratory" : ($kind === "school" ? "school" : "personal"),
                "summary" => trim((string)$session->name) != ""
                    ? $session->name
                    : ($kind === "laboratory" ? "Événement du laboratoire" : ($kind === "school" ? "Événement école" : "Événement personnel")),
                "description" => $kind === "laboratory"
                    ? "Laboratoire : ".$owner
                    : ($kind === "school" ? "École : ".$owner : "Session personnelle"),
                "location" => implode(", ", array_unique($rooms)),
                "begin" => $session->begin_date,
                "end" => $session->end_date,
                "url" => calendar_feed_calendar_url(),
            ];
            continue ;
        }
        if (!session_has_activity($row))
            continue ;

        $activity = calendar_feed_blit_activity($row["id_activity"], $row["id"]);
        if ($activity == NULL || $activity->unique_session == NULL)
            continue ;
        $session = $activity->unique_session;
        $role = calendar_feed_role($activity, (int)$row["id"]);
        if ($role == "none")
            continue ;

        $begin = $session->begin_date;
        $end = $session->end_date;
        // Pour l'eleve, un rendez-vous reserve remplace la plage generale de la
        // session. Pour l'enseignant/assistant, on conserve la plage generale.
        if ($role != "teacher" && $session->slot_reserved && $session->user_slot != NULL)
        {
            $begin = date_to_timestamp($session->user_slot["begin_date"]);
            $end = date_to_timestamp($session->user_slot["end_date"]);
        }
        if ($begin === NULL || $end === NULL)
            continue ;

        $rooms = [];
        foreach ($session->room as $room)
            if (isset($room["name"]) && $room["name"] != "")
                $rooms[] = $room["name"];

        $events[] = [
            "kind" => "session",
            "id" => (int)$session->id,
            "activity_id" => (int)$activity->id,
            "role" => $role,
            "summary" => $activity->name != "" ? $activity->name : $activity->parent_name,
            "description" => $activity->parent_name,
            "location" => implode(", ", array_unique($rooms)),
            "begin" => $begin,
            "end" => $end,
        ];
    }
    return ($events);
}

function calendar_feed_project_events($start = NULL, $end = NULL)
{
    $events = [];
    $date_filter = "";
    if ($start !== NULL && $end !== NULL)
    {
        $start = (int)$start;
        $end = (int)$end;
        if ($end > $start)
            $date_filter = "
          AND activity.subject_appeir_date < '".db_form_date($end)."'
          AND activity.pickup_date >= '".db_form_date($start)."'";
    }
    $rows = db_select_all("
        activity.id
        FROM activity
        LEFT JOIN activity_type ON activity_type.id = activity.type
        WHERE activity.deleted IS NULL
          AND activity.is_template = 0
          AND activity_type.type = 1
          AND activity.subject_appeir_date IS NOT NULL
          AND activity.pickup_date IS NOT NULL
          $date_filter
        ORDER BY activity.subject_appeir_date ASC
    ");

    foreach ($rows as $row)
    {
	$activity = calendar_feed_blit_activity($row["id"]);
	if ($activity == NULL)
	    continue ;
	$role = calendar_feed_role($activity);
	if ($role == "none")
	    continue ;

	$events[] = [
	    "kind" => "activity",
	    "id" => (int)$activity->id,
	    "activity_id" => (int)$activity->id,
	    "role" => $role,
	    "summary" => $activity->name != "" ? $activity->name : $activity->codename,
	    "description" => $activity->parent_name,
	    "begin" => $activity->subject_appeir_date,
	    "end" => $activity->pickup_date,
	    "all_day" => true,
	];
    }
    return ($events);
}

function calendar_feed_collect_events(array &$user, $start = NULL, $end = NULL)
{
    global $User;

    $old_user = isset($User) ? $User : NULL;
    $User = $user;
    get_user_public_data($User);

    $events = array_merge(calendar_feed_session_events($start, $end), calendar_feed_project_events($start, $end));
    usort($events, function ($a, $b) {
	if ($a["begin"] == $b["begin"])
	    return (strcmp($a["kind"].$a["id"], $b["kind"].$b["id"]));
	return ($a["begin"] < $b["begin"] ? -1 : 1);
    });
    $user = $User;
    $User = $old_user;
    return ($events);
}

function calendar_ics_escape($text)
{
    $text = (string)$text;
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace(["\r\n", "\r", "\n"], "\\n", $text);
    $text = str_replace(";", "\\;", $text);
    $text = str_replace(",", "\\,", $text);
    return ($text);
}

function calendar_ics_fold($line)
{
    if (strlen($line) <= 75)
	return ($line);

    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false)
	return ($line);
    $lines = [];
    $current = "";
    $limit = 75;
    foreach ($chars as $char)
    {
	if ($current != "" && strlen($current.$char) > $limit)
	{
	    $lines[] = $current;
	    $current = " ".$char;
	    $limit = 75;
	}
	else
	    $current .= $char;
    }
    if ($current != "")
	$lines[] = $current;
    return (implode("\r\n", $lines));
}

function calendar_ics_line($name, $value)
{
    return (calendar_ics_fold($name.":".$value)."\r\n");
}

function calendar_ics_role_label($role)
{
    if ($role == "teacher")
	return ("Enseignement / encadrement");
    if ($role == "tentative")
	return ("Inscription à effectuer");
    if ($role == "laboratory")
        return ("Événement de laboratoire");
    if ($role == "personal")
        return ("Événement personnel");
    return ("Inscrit");
}

function calendar_ics_event(array $event, $timezone)
{
    $out = "BEGIN:VEVENT\r\n";
    $uid = "infosphere-".$event["kind"]."-".$event["id"]."@infosphere";
    $out .= calendar_ics_line("UID", $uid);
    $out .= calendar_ics_line("DTSTAMP", gmdate("Ymd\\THis\\Z", $event["begin"]));

    if (isset($event["all_day"]) && $event["all_day"])
    {
	$out .= calendar_ics_line("DTSTART;VALUE=DATE", datex("Ymd", $event["begin"]));
	// DTEND est exclusif pour les evenements all-day.
	$out .= calendar_ics_line("DTEND;VALUE=DATE", datex("Ymd", $event["end"] + 24 * 60 * 60));
    }
    else
    {
	$out .= calendar_ics_line("DTSTART;TZID=".$timezone, datex("Ymd\\THis", $event["begin"]));
	$out .= calendar_ics_line("DTEND;TZID=".$timezone, datex("Ymd\\THis", $event["end"]));
    }

    $summary = $event["summary"];
    if ($event["role"] == "tentative")
	$summary = "[À inscrire] ".$summary;
    $out .= calendar_ics_line("SUMMARY", calendar_ics_escape($summary));

    $event_url = isset($event["url"]) && $event["url"] != ""
        ? $event["url"]
        : calendar_feed_activity_url(
            $event["activity_id"], $event["kind"] == "session" ? $event["id"] : NULL
        );
    $description = calendar_ics_role_label($event["role"]);
    if (isset($event["description"]) && $event["description"] != "")
	$description .= "\n".$event["description"];
    $description .= "\nInfosphere: ".$event_url;
    $out .= calendar_ics_line("DESCRIPTION", calendar_ics_escape($description));

    if (isset($event["location"]) && $event["location"] != "")
	$out .= calendar_ics_line("LOCATION", calendar_ics_escape($event["location"]));
    $out .= calendar_ics_line("URL", $event_url);
    $out .= calendar_ics_line("CATEGORIES", "INFOSPHERE,".strtoupper($event["role"]));

    if ($event["role"] == "tentative")
	$out .= calendar_ics_line("STATUS", "TENTATIVE");
    else
	$out .= calendar_ics_line("STATUS", "CONFIRMED");

    // Les projets / ruees sont des fenetres de travail ou des delais, pas des
    // indisponibilites continues de plusieurs jours. Ils restent donc
    // transparents dans les calculs de disponibilite des agendas externes.
    if ($event["role"] == "tentative" || $event["kind"] == "activity")
	$out .= calendar_ics_line("TRANSP", "TRANSPARENT");
    else
	$out .= calendar_ics_line("TRANSP", "OPAQUE");
    $out .= "END:VEVENT\r\n";
    return ($out);
}

function calendar_feed_render(array &$user)
{
    $timezone = calendar_feed_timezone();
    $events = calendar_feed_collect_events($user);

    $name = "Infosphere";
    if (isset($user["first_name"]) && $user["first_name"] != "")
	$name .= " - ".$user["first_name"];

    $out = "BEGIN:VCALENDAR\r\n";
    $out .= calendar_ics_line("VERSION", "2.0");
    $out .= calendar_ics_line("PRODID", "-//Infosphere//Planning personnel//FR");
    $out .= calendar_ics_line("CALSCALE", "GREGORIAN");
    $out .= calendar_ics_line("METHOD", "PUBLISH");
    $out .= calendar_ics_line("X-WR-CALNAME", calendar_ics_escape($name));
    $out .= calendar_ics_line("X-WR-TIMEZONE", $timezone);
    $out .= calendar_ics_line("REFRESH-INTERVAL;VALUE=DURATION", "PT15M");
    $out .= calendar_ics_line("X-PUBLISHED-TTL", "PT15M");
    foreach ($events as $event)
	$out .= calendar_ics_event($event, $timezone);
    $out .= "END:VCALENDAR\r\n";
    return ($out);
}
