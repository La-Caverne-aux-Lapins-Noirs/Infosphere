<?php

require_once (__DIR__."/public_invitation.php");

function communication_event_schema_ready()
{
    static $ready = NULL;
    if ($ready !== NULL)
        return ($ready);
    $tables = db_select_one("COUNT(*) AS total FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name IN ('session_school', 'communication_event', 'communication_event_session')");
    $columns = db_select_one("COUNT(*) AS total FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'user_form'
          AND column_name IN ('id_communication_event', 'event_cancelled_at')");
    $event_columns = db_select_one("COUNT(*) AS total FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'communication_event'
          AND column_name = 'parental_authorization_required'");
    return ($ready = ($tables && (int)$tables["total"] === 3
        && $columns && (int)$columns["total"] === 2
        && $event_columns && (int)$event_columns["total"] === 1));
}

function communication_event_kind()
{
    return ("EVENT");
}

function communication_event_response_kind($id_event)
{
    return ("EVENT-".(int)$id_event);
}

function communication_event_is_response_kind($kind)
{
    return (is_string($kind) && preg_match('/^EVENT-[1-9][0-9]*$/D', trim($kind)) === 1);
}

function communication_event_can_manage_school($id_school, $id_user = -1)
{
    global $User;

    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (false);
    if ($id_user == -1)
        $id_user = (int)($User["id"] ?? 0);
    if ($id_user <= 0)
        return (false);
    if (is_admin() && $id_user == (int)($User["id"] ?? 0))
        return (true);
    return (
        user_has_school_authority($id_user, "DIRECTOR", $id_school)
        || user_has_school_authority($id_user, "SECRETARIAT", $id_school)
        || user_has_school_authority($id_user, "COMMERCIAL", $id_school)
    );
}

function communication_event_can_manage($event, $id_user = -1)
{
    if (!is_array($event))
        $event = communication_event_fetch((int)$event);
    if (!$event)
        return (false);
    return (communication_event_can_manage_school((int)$event["id_school"], $id_user));
}

function communication_event_fetch($id, $include_deleted = false)
{
    if (!communication_event_schema_ready())
        return (NULL);
    $id = (int)$id;
    if ($id <= 0)
        return (NULL);
    $deleted = $include_deleted ? "" : " AND communication_event.deleted IS NULL ";
    return (db_select_one("
        communication_event.*, school.codename AS school_codename,
        COALESCE(NULLIF(organization.fr_name, ''), NULLIF(organization.name, ''), school.codename) AS school_name
        FROM communication_event
        LEFT JOIN school ON school.id = communication_event.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE communication_event.id = $id $deleted
    "));
}

function communication_event_list_for_school($id_school)
{
    if (!communication_event_schema_ready())
        return ([]);
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return ([]);
    return (db_select_all("
        communication_event.*
        FROM communication_event
        WHERE communication_event.deleted IS NULL
          AND communication_event.id_school = $id_school
        ORDER BY communication_event.created_at DESC, communication_event.id DESC
    "));
}

function communication_event_sessions($id_event, $future_only = false)
{
    global $Language;

    if (!communication_event_schema_ready())
        return ([]);

    $id_event = (int)$id_event;
    $future = $future_only ? " AND session.end_date >= NOW() " : "";
    $rows = db_select_all("
        session.*
        FROM communication_event_session
        LEFT JOIN session ON session.id = communication_event_session.id_session
        WHERE communication_event_session.id_communication_event = $id_event
          AND session.id IS NOT NULL
          AND session.deleted IS NULL
          $future
        ORDER BY session.begin_date ASC, session.id ASC
    ");
    foreach ($rows as &$row)
    {
        $id_session = (int)$row["id"];
        $row["schools"] = db_select_all("
            school.id, school.codename,
            COALESCE(NULLIF(organization.{$Language}_name, ''), NULLIF(organization.name, ''), school.codename) AS name
            FROM session_school
            LEFT JOIN school ON school.id = session_school.id_school
            LEFT JOIN organization ON organization.id = school.id_organization
            WHERE session_school.id_session = $id_session
              AND school.deleted IS NULL
            ORDER BY name ASC, school.codename ASC
        ");
        $row["rooms"] = db_select_all("
            room.id, room.codename, room.{$Language}_name AS name, room.capacity
            FROM session_room
            LEFT JOIN room ON room.id = session_room.id_room
            WHERE session_room.id_session = $id_session
              AND room.deleted IS NULL
            ORDER BY room.{$Language}_name ASC, room.codename ASC
        ");
    }
    unset($row);
    return ($rows);
}

function communication_event_public_token(array $event)
{
    $encrypted = (string)($event["token_secret"] ?? "");
    if ($encrypted == "")
        return ("");
    $token = unsecure_data($encrypted);
    return (is_string($token) ? $token : "");
}

function communication_event_public_url(array $event)
{
    $token = communication_event_public_token($event);
    return ($token == "" ? "" : public_invitation_url("RegistrationForm", $token));
}

function communication_event_find_by_token($token)
{
    global $Database;

    if (!communication_event_schema_ready() || !public_invitation_token_is_valid($token))
        return (NULL);
    $hash = $Database->real_escape_string(public_invitation_token_hash($token));
    $event = db_select_one("
        * FROM communication_event
        WHERE token_hash = '$hash'
          AND revoked_at IS NULL
          AND deleted IS NULL
    ");
    return ($event ?: NULL);
}

function communication_event_class_choices()
{
    return ([
        -9 => "Autre",
        -8 => "CM1",
        -7 => "CM2",
        -6 => "6ème",
        -5 => "5ème",
        -4 => "4ème",
        -3 => "3ème",
        -2 => "Seconde",
        -1 => "Première",
         0 => "Terminale",
         1 => "Bac+1",
         2 => "Bac+2",
         3 => "Bac+3",
         4 => "Bac+4",
         5 => "Bac+5",
         6 => "Bac+6",
         7 => "Bac+7",
         8 => "Bac+8",
         9 => "En reconversion",
        10 => "?",
    ]);
}

function communication_event_team_label($id_team)
{
    $id_team = (int)$id_team;
    $parts = [];
    foreach (db_select_all("
        user.first_name, user.family_name
        FROM user_team
        LEFT JOIN user ON user.id = user_team.id_user
        WHERE user_team.id_team = $id_team
          AND user_team.status > 0
          AND user.deleted IS NULL
        ORDER BY user_team.status DESC, user_team.id ASC
    ") as $user)
    {
        $first = trim((string)($user["first_name"] ?? ""));
        $family = trim((string)($user["family_name"] ?? ""));
        if ($first == "")
            $first = "Participant";
        $initial = $family != "" ? mb_strtoupper(mb_substr($family, 0, 1, "UTF-8"), "UTF-8")."." : "";
        $parts[] = trim($first." ".$initial);
    }
    return (count($parts) ? implode(" / ", $parts) : "Équipe disponible");
}

function communication_event_public_sessions($id_event)
{
    $out = [];
    foreach (communication_event_sessions((int)$id_event, true) as $session)
    {
        $id_session = (int)$session["id"];
        $teams = [];
        foreach (db_select_all("
            team.id
            FROM team
            WHERE team.id_session = $id_session
              AND COALESCE(team.id_activity, 0) <= 0
              AND team.closed IS NULL
              AND team.canjoin = 1
            ORDER BY team.id ASC
        ") as $team)
        {
            $id_team = (int)$team["id"];
            $teams[] = [
                "id" => $id_team,
                "label" => communication_event_team_label($id_team),
            ];
        }
        $session["teams"] = $teams;
        $out[] = $session;
    }
    return ($out);
}

function communication_event_form_schema(array $event)
{
    $sessions = communication_event_public_sessions((int)$event["id"]);
    $session_choices = [];
    foreach ($sessions as $session)
    {
        $rooms = [];
        foreach (($session["rooms"] ?? []) as $room)
            $rooms[] = trim((string)($room["name"] ?? "")) != "" ? $room["name"] : $room["codename"];
        $label = datex("d/m/Y H:i", date_to_timestamp($session["begin_date"]))
            ." – ".datex("H:i", date_to_timestamp($session["end_date"]));
        if (count($rooms))
            $label .= " — ".implode(", ", $rooms);
        $session_choices[(string)$session["id"]] = $label;
    }
    return ([
        "groups" => ["Event"],
        "signature_groups" => [],
        "fields" => [
            "Event.FirstName" => ["label" => "Prénom", "type" => "text", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
            "Event.FamilyName" => ["label" => "Nom", "type" => "text", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
            "Event.Mail" => ["label" => "Adresse électronique", "type" => "email", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
            "Event.Phone" => ["label" => "Téléphone", "type" => "tel", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
            "Event.HighSchool" => ["label" => "Lycée / établissement actuel", "type" => "text", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
            "Event.City" => ["label" => "Ville", "type" => "text", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
            "Event.CurrentClass" => [
                "label" => "Niveau actuel", "type" => "select", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true,
                "choice_values" => array_map("strval", array_keys(communication_event_class_choices())),
                "choices" => array_values(communication_event_class_choices()),
            ],
            "Event.Specialties" => ["label" => "Spécialités / options", "type" => "text", "group" => "Event", "required" => false, "editable" => true, "custom_label" => true],
            "Event.Interests" => ["label" => "Qu'est-ce qui t'intéresse en informatique ?", "type" => "textarea", "group" => "Event", "required" => false, "editable" => true, "custom_label" => true],
            "Event.Session" => [
                "label" => "Créneau", "type" => "select", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true,
                "choice_values" => array_keys($session_choices), "choices" => array_values($session_choices),
            ],
            "Event.Team" => ["label" => "Équipe", "type" => "select", "group" => "Event", "required" => true, "editable" => true, "custom_label" => true],
        ] + (!empty($event["parental_authorization_required"]) ? [
            "Event.ParentAuthorization" => [
                "label" => "Autorisation parentale",
                "type" => "boolean_checkbox",
                "group" => "Event",
                "required" => true,
                "editable" => true,
                "custom_label" => true,
            ],
        ] : []),
        "event" => [
            "id" => (int)$event["id"],
            "name" => (string)$event["name"],
            "description" => (string)($event["description"] ?? ""),
            "parental_authorization_required" => !empty($event["parental_authorization_required"]),
            "sessions" => $sessions,
        ],
    ]);
}

function communication_event_create($id_school, $name, $description, $id_creator, $parental_authorization_required = false)
{
    global $Database;

    if (!communication_event_schema_ready())
        return (["ok" => false, "error" => "CommunicationEventSchemaMissing"]);
    $id_school = (int)$id_school;
    $id_creator = (int)$id_creator;
    $name = trim((string)$name);
    $description = trim((string)$description);
    if ($id_school <= 0 || $id_creator <= 0 || $name == "")
        return (["ok" => false, "error" => "MissingParameter"]);
    if (!communication_event_can_manage_school($id_school, $id_creator))
        return (["ok" => false, "error" => "PermissionDenied"]);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(public_invitation_token_hash($token));
    $secret = secure_data($token);
    if ($secret === false)
        return (["ok" => false, "error" => "CannotCreate"]);
    $name_sql = $Database->real_escape_string(substr($name, 0, 255));
    $description_sql = $Database->real_escape_string($description);
    $secret_sql = $Database->real_escape_string($secret);
    $parental = $parental_authorization_required ? 1 : 0;
    if ($Database->query("
        INSERT INTO communication_event
            (id_school, name, description, parental_authorization_required,
             token_hash, token_secret, id_creator, created_at)
        VALUES
            ($id_school, '$name_sql', '$description_sql', $parental,
             '$hash', '$secret_sql', $id_creator, NOW())
    ") === false)
        return (["ok" => false, "error" => "CannotCreate"]);
    $id = (int)$Database->insert_id;
    return (["ok" => true, "event" => communication_event_fetch($id), "token" => $token]);
}

function communication_event_edit($id_event, array $data)
{
    global $Database;

    $event = communication_event_fetch((int)$id_event);
    if (!$event)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "event"]);
    if (!communication_event_can_manage($event))
        return (["ok" => false, "error" => "PermissionDenied"]);
    $name = trim((string)($data["name"] ?? $event["name"]));
    $description = trim((string)($data["description"] ?? $event["description"]));
    $parental = !empty($data["parental_authorization_required"]) ? 1 : 0;
    if ($name == "")
        return (["ok" => false, "error" => "MissingParameter", "details" => "name"]);
    $name_sql = $Database->real_escape_string(substr($name, 0, 255));
    $description_sql = $Database->real_escape_string($description);
    if ($Database->query("UPDATE communication_event
        SET name = '$name_sql', description = '$description_sql',
            parental_authorization_required = $parental
        WHERE id = ".(int)$event["id"]) === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    return (["ok" => true, "event" => communication_event_fetch((int)$event["id"])]);
}

function communication_event_delete($id_event)
{
    global $Database;

    $event = communication_event_fetch((int)$id_event);
    if (!$event)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "event"]);
    if (!communication_event_can_manage($event))
        return (["ok" => false, "error" => "PermissionDenied"]);
    $sessions = communication_event_sessions((int)$event["id"], false);
    if ($Database->query("START TRANSACTION") === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    foreach ($sessions as $session)
        if ($Database->query("UPDATE session SET deleted = NOW() WHERE id = ".(int)$session["id"]." AND deleted IS NULL") === false)
        {
            $Database->query("ROLLBACK");
            return (["ok" => false, "error" => "CannotEdit"]);
        }
    if ($Database->query("UPDATE communication_event SET revoked_at = COALESCE(revoked_at, NOW()), deleted = NOW() WHERE id = ".(int)$event["id"]) === false)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotEdit"]);
    }
    $Database->query("COMMIT");
    return (["ok" => true]);
}

function communication_event_delete_session($id_event, $id_session)
{
    global $Database;

    $event = communication_event_fetch((int)$id_event);
    $id_session = abs((int)$id_session);
    if (!$event || $id_session <= 0)
        return (["ok" => false, "error" => "InvalidParameter"]);
    if (!communication_event_can_manage($event))
        return (["ok" => false, "error" => "PermissionDenied"]);
    $linked = db_select_one("id FROM communication_event_session WHERE id_communication_event = ".(int)$event["id"]." AND id_session = $id_session");
    if (!$linked)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "session"]);
    if ($Database->query("UPDATE session SET deleted = NOW() WHERE id = $id_session AND deleted IS NULL") === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    return (["ok" => true]);
}

function communication_event_registration_summary($id_event)
{
    $id_event = (int)$id_event;
    if (!communication_event_schema_ready() || $id_event <= 0)
        return (["total" => 0, "active" => 0, "cancelled" => 0]);
    $row = db_select_one("\n        COUNT(*) AS total,\n        SUM(event_cancelled_at IS NULL) AS active,\n        SUM(event_cancelled_at IS NOT NULL) AS cancelled\n        FROM user_form\n        WHERE id_communication_event = $id_event\n          AND completed_at IS NOT NULL\n    ");
    return ([
        "total" => (int)($row["total"] ?? 0),
        "active" => (int)($row["active"] ?? 0),
        "cancelled" => (int)($row["cancelled"] ?? 0),
    ]);
}


function communication_event_validate_school_ids(array $values, $id_owner_school)
{
    $ids = [];
    foreach ($values as $value)
    {
        $id = (int)$value;
        if ($id <= 0 || isset($ids[$id]))
            continue ;
        if (!communication_event_can_manage_school($id))
            return (["ok" => false, "error" => "PermissionDenied", "details" => "school #$id"]);
        $ids[$id] = true;
    }
    if (!count($ids))
        $ids[(int)$id_owner_school] = true;
    return (["ok" => true, "ids" => array_keys($ids)]);
}

function communication_event_add_session($id_event, array $data)
{
    global $Database;

    if (!communication_event_schema_ready())
        return (["ok" => false, "error" => "CommunicationEventSchemaMissing"]);
    $event = communication_event_fetch((int)$id_event);
    if (!$event)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "event"]);
    if (!communication_event_can_manage($event))
        return (["ok" => false, "error" => "PermissionDenied"]);

    $school_values = $data["schools"] ?? [];
    if (!is_array($school_values))
        $school_values = [$school_values];
    $schools = communication_event_validate_school_ids($school_values, (int)$event["id_school"]);
    if (!$schools["ok"])
        return ($schools);

    $name = trim((string)($data["name"] ?? ""));
    if ($name == "")
        $name = (string)$event["name"];
    $day = trim((string)($data["day"] ?? ""));
    $begin = trim((string)($data["begin"] ?? ""));
    $end = trim((string)($data["end"] ?? ""));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)
        || !preg_match('/^\d{2}:\d{2}$/D', $begin)
        || !preg_match('/^\d{2}:\d{2}$/D', $end))
        return (["ok" => false, "error" => "InvalidDate"]);
    if (strtotime($day." ".$end) <= strtotime($day." ".$begin))
        return (["ok" => false, "error" => "InvalidDate"]);

    $id_room = (int)($data["room"] ?? 0);
    if ($id_room <= 0)
        return (["ok" => false, "error" => "MissingParameter", "details" => "room"]);
    if ($id_room > 0)
    {
        $school_sql = implode(",", array_map("intval", $schools["ids"]));
        $room = db_select_one("
            room.id
            FROM room
            LEFT JOIN school_room ON school_room.id_room = room.id
            WHERE room.id = $id_room
              AND room.deleted IS NULL
              AND school_room.id_school IN ($school_sql)
        ");
        if (!$room)
            return (["ok" => false, "error" => "CommunicationEventRoomNotInSchool"]);
    }

    if ($Database->query("START TRANSACTION") === false)
        return (["ok" => false, "error" => "CannotCreate"]);
    $rollback = function() use ($Database) { $Database->query("ROLLBACK"); };

    $fields = [
        "name" => $name,
        "begin_date" => $day." ".$begin.":00",
        "end_date" => $day." ".$end.":00",
        "_school_ids" => $schools["ids"],
    ];
    if (($ret = add_session($fields))->is_error())
    {
        $rollback();
        return (["ok" => false, "error" => $ret->label ?? "CannotCreate", "details" => $ret->details ?? ""]);
    }
    $id_session = (int)$Database->insert_id;
    if ($id_session <= 0)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotCreate"]);
    }

    foreach ($schools["ids"] as $id_school)
        if ($Database->query("INSERT IGNORE INTO session_school (id_session, id_school) VALUES ($id_session, ".(int)$id_school.")") === false)
        {
            $rollback();
            return (["ok" => false, "error" => "CannotCreate"]);
        }

    if ($id_room > 0
        && $Database->query("INSERT INTO session_room (id_session, id_room) VALUES ($id_session, $id_room)") === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotCreate"]);
    }
    if ($Database->query("
        INSERT INTO communication_event_session (id_communication_event, id_session)
        VALUES (".(int)$event["id"].", $id_session)
    ") === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotCreate"]);
    }
    if ($Database->query("COMMIT") === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotCreate"]);
    }

    add_log(CREATIVE_OPERATION, "Communication event session #$id_session created", $id_session);
    return (["ok" => true, "id_session" => $id_session]);
}

function communication_event_find_user_by_mail($mail)
{
    global $Database;

    $mail = trim((string)$mail);
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
        return (["ok" => false, "error" => "BadMail"]);
    $mail_sql = $Database->real_escape_string(mb_strtolower($mail, "UTF-8"));
    $rows = db_select_all("
        id, codename, first_name, family_name, phone, mail, current_class, profile_status, deleted
        FROM user
        WHERE LOWER(mail) = '$mail_sql'
        ORDER BY id ASC
    ");
    if (!count($rows))
        return (["ok" => true, "user" => NULL]);
    foreach ($rows as $row)
        if (($row["profile_status"] ?? "") != "prospect" || !empty($row["deleted"]))
            return (["ok" => false, "error" => "CommunicationEventKnownUser"]);
    if (count($rows) > 1)
        return (["ok" => false, "error" => "CommunicationEventAmbiguousProspect"]);
    return (["ok" => true, "user" => $rows[0]]);
}

function communication_event_unique_login($first_name, $family_name)
{
    $base = build_named_user_login($first_name, $family_name);
    if ($base == "." || strlen($base) < 2)
        $base = "prospect";
    $candidate = $base;
    $suffix = 2;
    while (db_select_one("id FROM user WHERE codename = '".$GLOBALS["Database"]->real_escape_string($candidate)."'") != NULL)
        $candidate = substr($base, 0, 58).".".$suffix++;
    return ($candidate);
}

function communication_event_create_prospect($first_name, $family_name, $mail, $phone, $city, $current_class)
{
    $login = communication_event_unique_login($first_name, $family_name);
    $created = subscribe($login, $mail, NULL, false, true, "prospect");
    if ($created->is_error())
        return (["ok" => false, "error" => $created->label, "details" => $created->details ?? ""]);
    $user = $created->value;
    $saved = set_user_data((int)$user["id"], [
        "first_name" => trim((string)$first_name),
        "family_name" => trim((string)$family_name),
        "phone" => trim((string)$phone),
        "city" => trim((string)$city),
        "current_class" => (int)$current_class,
    ]);
    if ($saved->is_error())
        return (["ok" => false, "error" => $saved->label, "details" => $saved->details ?? ""]);
    return (["ok" => true, "user" => $saved->value]);
}

function communication_event_validate_answers(array $event, array $submitted)
{
    $first_name = trim((string)($submitted["Event.FirstName"] ?? ""));
    $family_name = trim((string)($submitted["Event.FamilyName"] ?? ""));
    $mail = trim((string)($submitted["Event.Mail"] ?? ""));
    $phone = trim((string)($submitted["Event.Phone"] ?? ""));
    $high_school = trim((string)($submitted["Event.HighSchool"] ?? ""));
    $city = trim((string)($submitted["Event.City"] ?? ""));
    $specialties = trim((string)($submitted["Event.Specialties"] ?? ""));
    $interests = trim((string)($submitted["Event.Interests"] ?? ""));
    $current_class = filter_var($submitted["Event.CurrentClass"] ?? NULL, FILTER_VALIDATE_INT);
    $id_session = filter_var($submitted["Event.Session"] ?? NULL, FILTER_VALIDATE_INT);
    $team_raw = trim((string)($submitted["Event.Team"] ?? "new"));

    if ($first_name == "" || $family_name == "" || $phone == "" || $mail == ""
        || $high_school == "" || $city == "")
        return (["ok" => false, "error" => "MissingParameter"]);
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
        return (["ok" => false, "error" => "BadMail"]);
    $first_name = mb_substr($first_name, 0, 255, "UTF-8");
    $family_name = mb_substr($family_name, 0, 255, "UTF-8");
    $phone = mb_substr($phone, 0, 255, "UTF-8");
    $high_school = mb_substr($high_school, 0, 255, "UTF-8");
    $city = mb_substr($city, 0, 255, "UTF-8");
    $specialties = mb_substr($specialties, 0, 1000, "UTF-8");
    $interests = mb_substr($interests, 0, 4000, "UTF-8");
    if (!empty($event["parental_authorization_required"]))
    {
        $parental = $submitted["Event.ParentAuthorization"] ?? [];
        if (!is_array($parental))
            $parental = [$parental];
        $parental = array_map("strval", $parental);
        if (!in_array("1", $parental, true))
            return (["ok" => false, "error" => "CommunicationEventParentalAuthorizationRequired"]);
    }
    if ($current_class === false || !array_key_exists((int)$current_class, communication_event_class_choices()))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "current_class"]);
    if ($id_session === false || (int)$id_session <= 0)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "session"]);
    $id_session = (int)$id_session;
    $linked = db_select_one("
        communication_event_session.id_session
        FROM communication_event_session
        LEFT JOIN session ON session.id = communication_event_session.id_session
        WHERE communication_event_session.id_communication_event = ".(int)$event["id"]."
          AND communication_event_session.id_session = $id_session
          AND session.deleted IS NULL
          AND session.end_date >= NOW()
    ");
    if (!$linked)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "session"]);

    $id_team = 0;
    if ($team_raw !== "new")
    {
        $id_team = filter_var($team_raw, FILTER_VALIDATE_INT);
        if ($id_team === false || (int)$id_team <= 0)
            return (["ok" => false, "error" => "InvalidParameter", "details" => "team"]);
        $id_team = (int)$id_team;
        $team = db_select_one("
            id FROM team
            WHERE id = $id_team
              AND id_session = $id_session
              AND COALESCE(id_activity, 0) <= 0
              AND closed IS NULL
              AND canjoin = 1
        ");
        if (!$team)
            return (["ok" => false, "error" => "TeamDoesNotExist"]);
    }

    return (["ok" => true, "values" => [
        "first_name" => $first_name,
        "family_name" => $family_name,
        "phone" => $phone,
        "mail" => $mail,
        "high_school" => $high_school,
        "city" => $city,
        "specialties" => $specialties,
        "interests" => $interests,
        "parental_authorization" => !empty($event["parental_authorization_required"]),
        "current_class" => (int)$current_class,
        "id_session" => $id_session,
        "id_team" => $id_team,
        "team_raw" => $team_raw,
    ]]);
}

function communication_event_finalize(array $event, array $submitted)
{
    global $Database;

    $validated = communication_event_validate_answers($event, $submitted);
    if (!$validated["ok"])
        return ($validated);
    $v = $validated["values"];

    if ($Database->query("START TRANSACTION") === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    $rollback = function() use ($Database) { $Database->query("ROLLBACK"); };

    $found = communication_event_find_user_by_mail($v["mail"]);
    if (!$found["ok"])
    {
        $rollback();
        return ($found);
    }
    $user = $found["user"];
    if ($user == NULL)
    {
        $created = communication_event_create_prospect(
            $v["first_name"], $v["family_name"], $v["mail"], $v["phone"], $v["city"], $v["current_class"]
        );
        if (!$created["ok"])
        {
            $rollback();
            return ($created);
        }
        $user = $created["user"];
    }
    else
    {
        $id_user = (int)$user["id"];
        $saved = set_user_data($id_user, [
            "first_name" => $v["first_name"],
            "family_name" => $v["family_name"],
            "phone" => $v["phone"],
            "city" => $v["city"],
            "current_class" => $v["current_class"],
        ]);
        if ($saved->is_error())
        {
            $rollback();
            return (["ok" => false, "error" => $saved->label, "details" => $saved->details ?? ""]);
        }
        $user = $saved->value;
    }
    $id_user = (int)$user["id"];

    $already = db_select_one("
        id FROM user_form
        WHERE id_user = $id_user
          AND id_communication_event = ".(int)$event["id"]."
          AND completed_at IS NOT NULL
          AND event_cancelled_at IS NULL
          AND revoked_at IS NULL
        ORDER BY id DESC
    ");
    if ($already)
    {
        $rollback();
        return (["ok" => false, "error" => "CommunicationEventAlreadyRegistered"]);
    }

    $id_team = (int)$v["id_team"];
    if ($id_team <= 0)
    {
        if ($Database->query("INSERT INTO team (team_name, id_activity, id_session, canjoin) VALUES (NULL, NULL, ".(int)$v["id_session"].", 1)") === false)
        {
            $rollback();
            return (["ok" => false, "error" => "CannotCreateNewTeam"]);
        }
        $id_team = (int)$Database->insert_id;
        $status = 2;
    }
    else
        $status = 1;

    $membership = db_select_one("
        user_team.id, team.id_session
        FROM user_team
        LEFT JOIN team ON team.id = user_team.id_team
        WHERE user_team.id_user = $id_user
          AND team.id_session = ".(int)$v["id_session"]."
          AND user_team.status > 0
    ");
    if ($membership)
    {
        $rollback();
        return (["ok" => false, "error" => "AlreadySubscribed"]);
    }
    $code = $Database->real_escape_string(hash("md5", $id_team.$id_user));
    if ($Database->query("INSERT INTO user_team (id_team, id_user, status, code) VALUES ($id_team, $id_user, $status, '$code')") === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotAddUserToTeam"]);
    }

    $answers = $submitted;
    $answers["Event.Session"] = (string)$v["id_session"];
    $answers["Event.Team"] = (string)$id_team;
    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $schema_json = json_encode(communication_event_form_schema($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($answers_json === false || $schema_json === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotEdit"]);
    }

    $personal_token = public_invitation_generate_token();
    $personal_hash = $Database->real_escape_string(public_invitation_token_hash($personal_token));
    $answers_sql = $Database->real_escape_string($answers_json);
    $schema_sql = $Database->real_escape_string($schema_json);
    $mail_sql = $Database->real_escape_string($v["mail"]);
    $name_sql = $Database->real_escape_string(trim($v["first_name"]." ".$v["family_name"]));
    $kind_sql = $Database->real_escape_string(communication_event_response_kind((int)$event["id"]));
    $id_creator = (int)($event["id_creator"] ?? 1);
    if ($id_creator <= 0)
        $id_creator = 1;
    if ($Database->query("
        INSERT INTO user_form
            (id_user, id_creator, recipient_mail, recipient_name, kind, token_hash, fields, answers,
             created_at, expires_at, last_saved_at, completed_at, id_communication_event)
        VALUES
            ($id_user, $id_creator, '$mail_sql', '$name_sql', '$kind_sql', '$personal_hash', '$schema_sql', '$answers_sql',
             NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), NOW(), NOW(), ".(int)$event["id"].")
    ") === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotEdit"]);
    }

    if ($Database->query("COMMIT") === false)
    {
        $rollback();
        return (["ok" => false, "error" => "CannotEdit"]);
    }
    add_log(CREATIVE_OPERATION, "Prospect #$id_user registered to communication event #".(int)$event["id"], $id_user);
    return ([
        "ok" => true,
        "completed" => true,
        "redirect" => public_invitation_url("RegistrationForm", $personal_token),
        "id_user" => $id_user,
        "id_team" => $id_team,
    ]);
}

function communication_event_response_data(array $form)
{
    $answers = registration_form_decode_json($form["answers"] ?? "{}", []);
    $id_session = (int)($answers["Event.Session"] ?? 0);
    $id_team = (int)($answers["Event.Team"] ?? 0);
    $session = $id_session > 0 ? db_select_one("* FROM session WHERE id = $id_session") : NULL;
    return ([
        "answers" => $answers,
        "id_session" => $id_session,
        "id_team" => $id_team,
        "session" => $session,
        "cancelled" => !empty($form["event_cancelled_at"]),
    ]);
}

function communication_event_cancel_response($token)
{
    global $Database;

    if (!public_invitation_token_is_valid($token))
        return (["ok" => false, "error" => "RegistrationFormInvalidToken"]);
    $hash = $Database->real_escape_string(public_invitation_token_hash($token));
    $form = db_select_one("
        * FROM user_form
        WHERE token_hash = '$hash'
          AND id_communication_event IS NOT NULL
          AND completed_at IS NOT NULL
          AND revoked_at IS NULL
    ");
    if (!$form || !communication_event_is_response_kind($form["kind"] ?? ""))
        return (["ok" => false, "error" => "RegistrationFormInvalidToken"]);
    if (!empty($form["event_cancelled_at"]))
        return (["ok" => true, "cancelled" => true]);

    $data = communication_event_response_data($form);
    $id_user = (int)$form["id_user"];
    $id_team = (int)$data["id_team"];
    if ($Database->query("START TRANSACTION") === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    if ($id_team > 0)
    {
        $membership = db_select_one("id FROM user_team WHERE id_team = $id_team AND id_user = $id_user");
        if ($membership)
        {
            $removed = unsubscribe_user_from_team($id_team, $id_user);
            if ($removed->is_error())
            {
                $Database->query("ROLLBACK");
                return (["ok" => false, "error" => $removed->label, "details" => $removed->details ?? ""]);
            }
        }
    }
    if ($Database->query("UPDATE user_form SET event_cancelled_at = NOW() WHERE id = ".(int)$form["id"]) === false)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotEdit"]);
    }
    $Database->query("COMMIT");
    add_log(EDITING_OPERATION, "Communication event registration cancelled", $id_user);
    return (["ok" => true, "cancelled" => true]);
}

function communication_event_prospect_forms($id_user)
{
    if (!communication_event_schema_ready())
        return ([]);
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);
    return (db_select_all("
        user_form.id, user_form.id_communication_event, user_form.completed_at, user_form.event_cancelled_at,
        user_form.answers, communication_event.name, communication_event.deleted,
        communication_event.id_school
        FROM user_form
        LEFT JOIN communication_event ON communication_event.id = user_form.id_communication_event
        WHERE user_form.id_user = $id_user
          AND user_form.id_communication_event IS NOT NULL
          AND user_form.completed_at IS NOT NULL
        ORDER BY user_form.completed_at DESC, user_form.id DESC
    "));
}

function communication_event_prospect_badges($id_user)
{
    $forms = communication_event_prospect_forms((int)$id_user);
    if (!count($forms))
        return ("");
    $items = [];
    foreach ($forms as $form)
    {
        $data = communication_event_response_data($form);
        $date = "";
        if (is_array($data["session"]) && !empty($data["session"]["begin_date"]))
            $date = datex("d/m/Y", date_to_timestamp($data["session"]["begin_date"]));
        $label = "Inscrit via évènement : ".trim((string)($form["name"] ?? ""));
        if ($date != "")
            $label .= " — ".$date;
        if (!empty($form["event_cancelled_at"]))
            $label .= " (annulé)";

        $answers = $data["answers"] ?? [];
        $context = [];
        $high_school = trim((string)($answers["Event.HighSchool"] ?? ""));
        $city = trim((string)($answers["Event.City"] ?? ""));
        $specialties = trim((string)($answers["Event.Specialties"] ?? ""));
        $interests = trim((string)($answers["Event.Interests"] ?? ""));
        if ($high_school != "")
            $context[] = $high_school;
        if ($city != "")
            $context[] = $city;
        if ($specialties != "")
            $context[] = "Spécialités : ".$specialties;

        $item = "<span class='prospect_event_badge' title='Inscription via un évènement de communication'>".htmlspecialchars($label)."</span>";
        if (count($context))
            $item .= "<span class='prospect_event_context'>".htmlspecialchars(implode(" · ", $context))."</span>";
        if ($interests != "")
            $item .= "<span class='prospect_event_interests'><strong>Intérêts :</strong> ".htmlspecialchars($interests)."</span>";
        $items[] = "<span class='prospect_event_entry'>".$item."</span>";
    }
    return ("<div class='prospect_event_badges'>".implode("", $items)."</div>");
}
