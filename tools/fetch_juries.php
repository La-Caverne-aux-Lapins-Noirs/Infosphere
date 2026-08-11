<?php

function certification_clean_label($value)
{
    return (trim((string)$value));
}

function jury_clean_label($value)
{
    return (certification_clean_label($value));
}

function certification_title_name(array $title)
{
    global $Language;

    $field = (isset($Language) ? $Language : "fr")."_name";
    foreach ([$field, "fr_name", "en_name", "codename"] as $key)
        if (isset($title[$key]) && trim((string)$title[$key]) != "")
            return (trim((string)$title[$key]));
    return ("");
}

function jury_title_name(array $title)
{
    return (certification_title_name($title));
}

function certification_skill_name(array $skill)
{
    global $Language;

    $field = (isset($Language) ? $Language : "fr")."_description";
    foreach ([$field, "fr_description", "en_description", "codename"] as $key)
    {
        if (!isset($skill[$key]) || trim((string)$skill[$key]) == "")
            continue ;
        $value = trim((string)$skill[$key]);
        if ($key != "codename")
            $value = preg_split('/\r?\n/', $value)[0];
        return ($value);
    }
    return ("");
}

function jury_person_name(array $jury)
{
    $name = trim(($jury["first_name"] ?? "")." ".($jury["family_name"] ?? ""));
    if ($name != "")
        return ($name);
    if (isset($jury["nickname"]) && trim((string)$jury["nickname"]) != "")
        return (trim((string)$jury["nickname"]));
    return ($jury["codename"] ?? "");
}

function fetch_jury_note($id_user)
{
    $id_user = (int)$id_user;
    $row = db_select_one("note FROM jury_note WHERE id_user = $id_user");
    if ($row == NULL)
        return ("");
    return ((string)($row["note"] ?? ""));
}

function jury_set_note($id_user, $note)
{
    global $Database;

    $id_user = (int)$id_user;
    $note = trim((string)$note);
    if ($note == "")
    {
        if ($Database->query("DELETE FROM jury_note WHERE id_user = $id_user") == false)
            return (new ErrorResponse("CannotEdit"));
        return (new ValueResponse(true));
    }

    $note_sql = $Database->real_escape_string($note);
    if ($Database->query("
        INSERT INTO jury_note (id_user, note)
        VALUES ($id_user, '$note_sql')
        ON DUPLICATE KEY UPDATE note = '$note_sql'
    ") == false)
        return (new ErrorResponse("CannotEdit"));
    return (new ValueResponse(true));
}

function fetch_certification_titles($include_deleted = false)
{
    $where = $include_deleted ? "" : " WHERE deleted IS NULL ";
    $titles = db_select_all("*
        FROM `title`
        $where
        ORDER BY codename ASC
    ", "codename");

    foreach ($titles as &$title)
    {
        $title["skills"] = fetch_certification_title_skills($title["id"]);
        $title["skill_text"] = certification_title_skills_text($title["id"]);
    }
    unset($title);
    return ($titles);
}

function fetch_jury_titles($include_deleted = false)
{
    return (fetch_certification_titles($include_deleted));
}

function fetch_certification_title($id)
{
    if (($id = resolve_codename("title", $id))->is_error())
        return ($id);
    $id = (int)$id->value;
    $title = db_select_one("*
        FROM `title`
        WHERE id = $id
    ");
    if ($title == NULL)
        return (new ErrorResponse("NotAnId", $id, "title"));
    $title["skills"] = fetch_certification_title_skills($title["id"]);
    $title["skill_text"] = certification_title_skills_text($title["id"]);
    return (new ValueResponse($title));
}

function fetch_jury_title($id)
{
    return (fetch_certification_title($id));
}

function certification_title_id($value)
{
    if (!isset($value) || trim((string)$value) == "")
        return (NULL);
    if (($ret = resolve_codename("title", $value))->is_error())
        return (NULL);
    return ((int)$ret->value);
}

function jury_title_id($value)
{
    return (certification_title_id($value));
}

function jury_parse_title_list($raw)
{
    if (is_array($raw))
        $parts = $raw;
    else
        $parts = preg_split('/[;,\n]+/', (string)$raw);
    $out = [];
    foreach ($parts as $part)
    {
        $part = trim((string)$part);
        if ($part == "")
            continue ;
        $id = certification_title_id($part);
        if ($id !== NULL && !in_array($id, $out))
            $out[] = $id;
    }
    return ($out);
}

function fetch_certification_skills($by_name = false)
{
    return (db_select_all("*
        FROM skill
        ORDER BY codename ASC
    ", $by_name ? "codename" : ""));
}

function fetch_certification_skill($id)
{
    if (($id = resolve_codename("skill", $id))->is_error())
        return ($id);
    $id = (int)$id->value;
    $skill = db_select_one("*
        FROM skill
        WHERE id = $id
    ");
    if ($skill == NULL)
        return (new ErrorResponse("NotAnId", $id, "skill"));
    return (new ValueResponse($skill));
}

function fetch_certification_title_skills($id_title)
{
    $id_title = (int)$id_title;
    return (db_select_all("
        skill.*,
        title_skill.id as id_title_skill,
        title_skill.id_title as id_title,
        title_skill.id_skill as id_skill,
        title_skill.reference as reference
        FROM title_skill
        LEFT JOIN skill ON skill.id = title_skill.id_skill
        WHERE title_skill.id_title = $id_title
          AND skill.id IS NOT NULL
        ORDER BY skill.codename ASC
    ", "codename"));
}

function certification_title_skill_context($id_title)
{
    $skills = fetch_certification_title_skills($id_title);
    $by_codename = [];
    $names = [];
    $codenames = [];

    foreach ($skills as $skill)
    {
        $codename = $skill["codename"];
        $name = certification_skill_name($skill);
        $key = preg_replace('/[^A-Za-z0-9_]/', '_', $codename);
        if ($key == "")
            $key = "skill_".$skill["id"];
        $by_codename[$key] = [
            "id" => $skill["id"],
            "codename" => $codename,
            "name" => $name,
            "fr_description" => $skill["fr_description"] ?? "",
            "en_description" => $skill["en_description"] ?? "",
            "reference" => $skill["reference"] ?? "",
        ];
        $codenames[] = $codename;
        if ($name != "")
            $names[] = $name;
    }

    return ([
        "skills" => $by_codename,
        "skill_list" => implode(", ", $names),
        "skill_codenames" => implode(", ", $codenames),
    ]);
}

function certification_title_skills_text($id_title)
{
    $skills = fetch_certification_title_skills($id_title);
    $out = [];

    foreach ($skills as $skill)
        $out[] = $skill["codename"];
    return (implode(";", $out));
}

function fetch_jury_user_titles($id_user, $include_deleted = false, $type = "certificator")
{
    $id_user = (int)$id_user;
    $deleted = $include_deleted ? "" : " AND `title`.deleted IS NULL ";
    global $Database;
    $type_sql = $Database->real_escape_string($type);

    $titles = db_select_all("
        `title`.*,
        user_title.id as id_user_title,
        user_title.id_user as id_user,
        user_title.id_title as id_title,
        user_title.type as type
        FROM user_title
        LEFT JOIN `title` ON `title`.id = user_title.id_title
        WHERE user_title.id_user = $id_user
          AND user_title.type = '$type_sql'
          AND `title`.id IS NOT NULL
          $deleted
        ORDER BY `title`.codename ASC
    ", "codename");

    foreach ($titles as &$title)
    {
        $title["skills"] = fetch_certification_title_skills($title["id"]);
        $title["skill_text"] = certification_title_skills_text($title["id"]);
    }
    unset($title);
    return ($titles);
}

function jury_user_title_context($id_user)
{
    $titles = fetch_jury_user_titles($id_user);
    $by_codename = [];
    $names = [];
    $codenames = [];

    foreach ($titles as $title)
    {
        $codename = $title["codename"];
        $name = certification_title_name($title);
        $key = preg_replace('/[^A-Za-z0-9_]/', '_', $codename);
        if ($key == "")
            $key = "title_".$title["id"];
        $title_context = [
            "id" => $title["id"],
            "codename" => $codename,
            "name" => $name,
            "fr_name" => $title["fr_name"] ?? "",
            "en_name" => $title["en_name"] ?? "",
            "code" => $title["code"] ?? "",
        ];
        $title_context = array_merge($title_context, certification_title_skill_context($title["id"]));
        $by_codename[$key] = $title_context;
        $codenames[] = $codename;
        if ($name != "")
            $names[] = $name;
    }

    return ([
        "jury_titles" => $by_codename,
        "jury_title_list" => implode(", ", $names),
        "jury_title_codenames" => implode(", ", $codenames),
        "certificator_titles" => $by_codename,
        "certificator_title_list" => implode(", ", $names),
        "certificator_title_codenames" => implode(", ", $codenames),
    ]);
}

function jury_refresh_user($id_user)
{
    $extra = ["jury" => true, "profile_status" => "jury"];
    $extra = array_merge($extra, jury_user_title_context($id_user));
    return (refresh_user($id_user, NULL, $extra));
}

function jury_set_user_titles($id_user, $titles, $type = "certificator")
{
    global $Database;

    $id_user = (int)$id_user;
    $wanted = jury_parse_title_list($titles);
    $existing = fetch_jury_user_titles($id_user, true, $type);
    $existing_ids = [];
    global $Database;
    $type_sql = $Database->real_escape_string($type);

    foreach ($existing as $title)
        $existing_ids[] = (int)$title["id_title"];

    foreach ($existing_ids as $id_title)
        if (!in_array($id_title, $wanted))
            if ($Database->query("DELETE FROM user_title WHERE id_user = $id_user AND id_title = $id_title AND type = '$type_sql'") == false)
                return (new ErrorResponse("CannotEdit"));

    foreach ($wanted as $id_title)
        if (!in_array($id_title, $existing_ids))
            if ($Database->query("INSERT INTO user_title (id_user, id_title, type) VALUES ($id_user, $id_title, '$type_sql')") == false)
                return (new ErrorResponse("CannotEdit"));

    return (jury_refresh_user($id_user));
}

function jury_titles_text_for_user($id_user)
{
    $titles = fetch_jury_user_titles($id_user);
    $out = [];

    foreach ($titles as $title)
        $out[] = $title["codename"];
    return (implode(";", $out));
}


function fetch_explicit_session_teachers($id_session, $by_name = false)
{
    $id_session = (int)$id_session;
    $dat = db_select_all("
       session_teacher.id as id_session_teacher,
       user.id as id_user,
       user.codename as codename_user,
       laboratory.id as id_laboratory,
       laboratory.codename as codename_laboratory
       FROM session_teacher
       LEFT JOIN user ON user.id = session_teacher.id_user AND user.authority >= 0 AND user.profile_status = 'member'
       LEFT JOIN laboratory ON laboratory.id = session_teacher.id_laboratory AND laboratory.deleted IS NULL
       WHERE session_teacher.id_session = $id_session
    ");

    $new = [];
    foreach ($dat as &$d)
    {
        $n = [];
        $name = "";

        if ($d["codename_user"] != "")
        {
            $n["id"] = $d["id_user"];
            $n["id_user"] = $d["id_user"];
            $n["id_teacher"] = $d["id_user"];
            $n["id_session_teacher"] = $d["id_session_teacher"];
            $n["authority"] = TEACHER;
            $n["session_teacher"] = true;
            $name = $n["codename"] = $d["codename_user"];
            $n["prefix"] = "";
        }

        if ($d["codename_laboratory"] != "")
        {
            $n = fetch_laboratory($d["id_laboratory"]);
            $n["id_session_teacher"] = $d["id_session_teacher"];
            $n["authority"] = TEACHER;
            $n["session_teacher"] = true;
            $n["codename"] = "#".($name = $n["codename"]);
            $n["prefix"] = "#";
        }

        if ($name == "")
            continue ;
        if ($by_name)
            $new[$name] = $n;
        else
            $new[] = $n;
    }
    unset($d);
    return ($new);
}

function merge_teacher_lists($base, $extra, $by_name = false)
{
    if ($by_name)
    {
        foreach ($extra as $key => $teacher)
            $base[$key] = $teacher;
        return ($base);
    }

    $seen = [];
    $out = [];
    foreach ([$base, $extra] as $list)
    {
        foreach ($list as $teacher)
        {
            if (!isset($teacher["codename"]))
                continue ;
            $key = $teacher["codename"];
            if (isset($seen[$key]))
                continue ;
            $seen[$key] = true;
            $out[] = $teacher;
        }
    }
    return ($out);
}

function fetch_session_teachers($id_session, $by_name = false, $include_activity_teachers = true, $id_activity = NULL, $activity = NULL)
{
    $teachers = [];

    if ($include_activity_teachers)
    {
        if ($activity !== NULL && isset($activity->teacher))
            $teachers = $activity->teacher;
        else
        {
            if ($id_activity === NULL)
            {
                $row = db_select_one("id_activity FROM session WHERE id = ".(int)$id_session);
                $id_activity = $row ? $row["id_activity"] : NULL;
            }
            if ($id_activity !== NULL)
                $teachers = fetch_teacher($id_activity, $by_name, "activity", true);
        }
        foreach ($teachers as &$teacher)
            $teacher["implicit_session_teacher"] = true;
        unset($teacher);
    }

    return (merge_teacher_lists(
        $teachers,
        fetch_explicit_session_teachers($id_session, $by_name),
        $by_name
    ));
}

function fetch_session_juries($id_session)
{
    $id_session = (int)$id_session;
    $juries = db_select_all("
        user.id,
        user.codename,
        user.nickname,
        user.first_name,
        user.family_name,
        user.mail,
        user.phone,
        user.deleted,
        user.profile_status,
        session_teacher.id as id_session_teacher
        FROM session_teacher
        LEFT JOIN user ON user.id = session_teacher.id_user
        WHERE session_teacher.id_session = $id_session
          AND user.id IS NOT NULL
          AND user.profile_status = 'jury'
        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC
    ", "id");

    foreach ($juries as &$jury)
    {
        $jury["id_jury"] = $jury["id"];
        $jury["jury"] = $jury["codename"];
        $jury["titles"] = fetch_jury_user_titles($jury["id"]);
        $jury["title_text"] = jury_titles_text_for_user($jury["id"]);
    }
    unset($jury);
    return ($juries);
}

function ensure_session_juries($session)
{
    if (!is_object($session))
        return ([]);
    if (!isset($session->jury_loaded) || !$session->jury_loaded)
    {
        $session->jury = fetch_session_juries($session->id);
        $session->jury_loaded = true;
    }
    return ($session->jury);
}

function fetch_jury_sessions($id_user)
{
    global $Language;

    $id_user = (int)$id_user;
    $name_field = in_array($Language ?? "fr", ["fr", "en"], true) ? ($Language."_name") : "fr_name";

    $sessions = db_select_all("
        session_teacher.id as id_session_teacher,
        session.id,
        session.begin_date,
        session.end_date,
        session.deleted,
        activity.id as id_activity,
        activity.codename as activity_codename,
        COALESCE(NULLIF(activity.$name_field, ''), NULLIF(activity.fr_name, ''), NULLIF(activity.en_name, ''), activity.codename) as activity_name
        FROM session_teacher
        LEFT JOIN session ON session.id = session_teacher.id_session
        LEFT JOIN activity ON activity.id = session.id_activity
        WHERE session_teacher.id_user = $id_user
          AND session.id IS NOT NULL
          AND activity.id IS NOT NULL
        ORDER BY session.begin_date DESC, session.id DESC
    ");

    return ($sessions);
}


function session_jury_link_descriptor()
{
    return ([
        "name" => "jury",
        "placeholder" => "JuryLoginPlaceholder",
        "table" => "jury",
        "" => ["jury", "user"]
    ]);
}

function session_jury_link_params($session)
{
    return ([
        "hook_name" => "session",
        "hook_id" => $session->id,
        "linked_name" => session_jury_link_descriptor(),
        "linked_elems" => $session->jury,
        "admin_func" => "is_teacher_or_director_for_session",
        "link" => "profile"
    ]);
}

function split_jury_sessions(array $sessions)
{
    $now = now();
    $out = ["upcoming" => [], "past" => []];

    foreach ($sessions as $session)
    {
        $end = $session["end_date"] ?: $session["begin_date"];
        if ($end != NULL && date_to_timestamp($end) >= $now)
            $out["upcoming"][] = $session;
        else
            $out["past"][] = $session;
    }
    usort($out["upcoming"], function ($a, $b) {
        return (strcmp((string)$a["begin_date"], (string)$b["begin_date"]));
    });
    return ($out);
}

function fetch_juries($id = -1, $include_deleted = true)
{
    if ($id != -1 && $id !== "")
    {
        if (($id = resolve_codename("user", $id))->is_error())
            return ([]);
        $id = (int)$id->value;
        $id = " AND user.id = $id ";
    }
    else
        $id = "";

    $deleted = $include_deleted ? "" : " AND user.deleted IS NULL ";
    $juries = db_select_all("
        user.id,
        user.codename,
        user.nickname,
        user.first_name,
        user.family_name,
        user.mail,
        user.phone,
        user.registration_date,
        user.deleted,
        user.profile_status,
        jury_note.note as jury_note
        FROM user
        LEFT JOIN jury_note ON jury_note.id_user = user.id
        WHERE user.profile_status = 'jury'
          $deleted
          $id
        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC
    ", "codename");

    foreach ($juries as &$jury)
    {
        $jury["titles"] = fetch_jury_user_titles($jury["id"]);
        $jury["title_text"] = jury_titles_text_for_user($jury["id"]);
        $jury["sessions"] = fetch_jury_sessions($jury["id"]);
        $sessions = split_jury_sessions($jury["sessions"]);
        $jury["sessions_upcoming"] = $sessions["upcoming"];
        $jury["sessions_past"] = $sessions["past"];
    }
    unset($jury);
    return ($juries);
}
