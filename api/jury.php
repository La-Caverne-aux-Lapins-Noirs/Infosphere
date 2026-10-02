<?php

function JuryCanManage($id = -1)
{
    return (am_i_director());
}

function JuryCleanData($data)
{
    foreach ([
        "codename", "mail", "first_name", "family_name",
        "nickname", "phone", "titles", "note", "fr_name", "en_name", "diploma_text",
        "code", "fr_description", "en_description", "skills",
        "id_school", "id_title", "start_date", "end_date", "start_time",
        "end_time", "jury_arrival_time", "id_session_manager", "session", "jury"
    ] as $field)
        if (isset($data[$field]))
            $data[$field] = trim((string)$data[$field]);
    foreach ($data as $key => $value)
        if (preg_match('/^skill_reference_[0-9]+$/', $key))
            $data[$key] = trim((string)$value);
    return ($data);
}

function JuryListResponse($msg = "")
{
    $juries = fetch_juries();
    $jury_titles = fetch_jury_titles();
    $certification_skills = fetch_certification_skills();
    $title_sessions = fetch_title_sessions();
    ob_start();
    require ("./pages/jury/list.phtml");
    $value = ["content" => ob_get_clean()];
    if ($msg != "")
        $value["msg"] = $msg;
    return (new ValueResponse($value));
}

function JuryResolveUserId($id)
{
    if ($id == -1)
        return (new ErrorResponse("MissingParameter", "jury"));
    if (($ret = resolve_codename("user", $id))->is_error())
        return ($ret);
    $id = (int)$ret->value;
    $jury = db_select_one("id FROM user WHERE id = $id AND profile_status = 'jury'");
    if ($jury == NULL)
        return (new ErrorResponse("UserNotFound"));
    return (new ValueResponse($id));
}

function JuryUserFields($data)
{
    $fields = [];
    foreach (["first_name", "family_name", "nickname", "mail", "phone"] as $field)
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    $fields["profile_status"] = "jury";
    return ($fields);
}


function JurySubmittedTitleFields($data)
{
    foreach ($data as $key => $value)
        if (preg_match('/^title_[0-9]+$/', $key))
            return (true);
    return (false);
}

function JuryExtractTitles($data)
{
    if (JurySubmittedTitleFields($data) == false)
        return ($data["titles"] ?? NULL);

    $titles = [];
    foreach (fetch_jury_titles() as $title)
    {
        $field = "title_".$title["id"];
        if (isset($data[$field]) && (int)$data[$field] != 0)
            $titles[] = $title["codename"];
    }
    return (implode(";", $titles));
}


function CertificationUpdateSubmittedSkillReferences($id_title, $data)
{
    foreach ($data as $key => $value)
    {
        if (!preg_match('/^skill_reference_([0-9]+)$/', $key, $m))
            continue ;
        if (($ret = handle_links(
            (int)$id_title,
            (int)$m[1],
            "title",
            "skill",
            false,
            "title_skill",
            false,
            "",
            "",
            ["reference" => trim((string)$value)]
        ))->is_error())
            return ($ret);
    }
    return (new ValueResponse(true));
}

function CertificationHandleSubmittedSkillLinks($id_title, $data)
{
    $changed = false;

    if (($ret = CertificationUpdateSubmittedSkillReferences($id_title, $data))->is_error())
        return ($ret);
    if (isset($data["skills"]) && trim((string)$data["skills"]) != "")
    {
        if (($ret = handle_links(
            (int)$id_title,
            $data["skills"],
            "title",
            "skill",
            false,
            "title_skill"
        ))->is_error())
            return ($ret);
        $changed = true;
    }
    if ($changed || CertificationSubmittedSkillReferenceFields($data))
        CertificationRefreshUsersForTitle($id_title);
    return (new ValueResponse(true));
}

function CertificationSubmittedSkillReferenceFields($data)
{
    foreach ($data as $key => $value)
        if (preg_match('/^skill_reference_[0-9]+$/', $key))
            return (true);
    return (false);
}

function CertificationRefreshUsersForTitle($id_title)
{
    foreach (db_select_all("
        user_title.id_user
        FROM user_title
        LEFT JOIN user ON user.id = user_title.id_user
        WHERE user_title.id_title = ".((int)$id_title)."
          AND user.profile_status = 'jury'
    ") as $row)
        jury_refresh_user($row["id_user"]);
}

function DisplayJury($id, $data, $method, $output, $module)
{
    return (JuryListResponse());
}

function AddJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1)
        bad_request();
    $data = JuryCleanData($data);

    if (($ret = subscribe_jury(
        $data["first_name"] ?? "",
        $data["family_name"] ?? "",
        $data["mail"] ?? "",
        $data["phone"] ?? ""
    ))->is_error())
        return ($ret);
    $id_user = (int)$ret->value["id"];

    if (($ret = set_user_data($id_user, JuryUserFields($data)))->is_error())
        return ($ret);
    if (($ret = jury_set_user_titles($id_user, JuryExtractTitles($data) ?? ""))->is_error())
        return ($ret);
    if (array_key_exists("note", $data))
        if (($ret = jury_set_note($id_user, $data["note"]))->is_error())
            return ($ret);
    add_log(CREATIVE_OPERATION, "Jury $id_user added", $id_user);
    return (JuryListResponse($Dictionnary["JuryAdded"] ?? "Jury ajouté"));
}

function EditJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = JuryResolveUserId($id))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;
    $data = JuryCleanData($data);
    if (($ret = set_user_data($id_user, JuryUserFields($data)))->is_error())
        return ($ret);
    $titles = JuryExtractTitles($data);
    if ($titles !== NULL)
    {
        if (($ret = jury_set_user_titles($id_user, $titles))->is_error())
            return ($ret);
    }
    else
        jury_refresh_user($id_user);
    if (array_key_exists("note", $data))
        if (($ret = jury_set_note($id_user, $data["note"]))->is_error())
            return ($ret);
    add_log(EDITING_OPERATION, "Jury #$id_user edited", $id_user);
    return (JuryListResponse($Dictionnary["JuryModified"] ?? "Jury modifié"));
}

function DeleteJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = JuryResolveUserId($id))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;
    if (($ret = set_user_data($id_user, ["deleted" => db_form_date(now())]))->is_error())
        return ($ret);
    add_log(DESTRUCTIVE_OPERATION, "Jury #$id_user archived", $id_user);
    return (JuryListResponse($Dictionnary["JuryArchived"] ?? "Jury archivé"));
}

function RestoreJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = JuryResolveUserId($id))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;
    if (($ret = set_user_data($id_user, ["deleted" => NULL, "profile_status" => "jury"]))->is_error())
        return ($ret);
    jury_refresh_user($id_user);
    add_log(EDITING_OPERATION, "Jury #$id_user restored", $id_user);
    return (JuryListResponse($Dictionnary["JuryRestored"] ?? "Jury restauré"));
}

function AddJuryTitle($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
        bad_request();
    $data = JuryCleanData($data);
    $codename = $data["codename"] ?? "";
    if ($codename == "" || !is_symbol($codename))
        return (new ErrorResponse("MissingCodeName", $codename));
    $codename_sql = $Database->real_escape_string($codename);
    if (db_select_one("id FROM `title` WHERE codename = '$codename_sql'") != NULL)
        return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    $code = $Database->real_escape_string($data["code"] ?? "");
    $fr = $Database->real_escape_string($data["fr_name"] ?? $codename);
    $en = $Database->real_escape_string($data["en_name"] ?? ($data["fr_name"] ?? $codename));
    $diploma_text = $Database->real_escape_string($data["diploma_text"] ?? "");
    if ($Database->query("INSERT INTO `title` (codename, code, fr_name, en_name, diploma_text) VALUES ('$codename_sql', '$code', '$fr', '$en', '$diploma_text')") == false)
        return (new ErrorResponse("CannotAdd"));
    $id_title = (int)$Database->insert_id;
    if (($ret = CertificationHandleSubmittedSkillLinks($id_title, $data))->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "Certification title $codename added");
    return (JuryListResponse($Dictionnary["JuryTitleAdded"] ?? "Certification ajoutée"));
}

function EditJuryTitle($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = fetch_jury_title($id))->is_error())
        return ($ret);
    $title = $ret->value;
    $data = JuryCleanData($data);
    $fields = [];
    foreach (["code", "fr_name", "en_name", "diploma_text"] as $field)
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    if (count($fields))
        if (db_update_one("title", (int)$title["id"], $fields) === NULL)
            return (new ErrorResponse("CannotEdit"));
    if (($ret = CertificationHandleSubmittedSkillLinks($title["id"], $data))->is_error())
        return ($ret);
    add_log(EDITING_OPERATION, "Certification title {$title["codename"]} edited");
    return (JuryListResponse($Dictionnary["JuryTitleModified"] ?? "Certification modifiée"));
}

function DeleteJuryTitle($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = fetch_jury_title($id))->is_error())
        return ($ret);
    $title = $ret->value;
    if (db_update_one("title", (int)$title["id"], ["deleted" => db_form_date(now())]) === NULL)
        return (new ErrorResponse("CannotEdit"));
    CertificationRefreshUsersForTitle($title["id"]);
    add_log(DESTRUCTIVE_OPERATION, "Certification title {$title["codename"]} archived");
    return (JuryListResponse($Dictionnary["JuryTitleArchived"] ?? "Certification archivée"));
}

function AddCertificationSkill($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
        bad_request();
    $data = JuryCleanData($data);
    $codename = $data["codename"] ?? "";
    if ($codename == "" || !is_symbol($codename))
        return (new ErrorResponse("MissingCodeName", $codename));
    $codename_sql = $Database->real_escape_string($codename);
    if (db_select_one("id FROM skill WHERE codename = '$codename_sql'") != NULL)
        return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    $fr = $Database->real_escape_string($data["fr_description"] ?? "");
    $en = $Database->real_escape_string($data["en_description"] ?? "");
    if ($Database->query("INSERT INTO skill (codename, fr_description, en_description) VALUES ('$codename_sql', '$fr', '$en')") == false)
        return (new ErrorResponse("CannotAdd"));
    add_log(CREATIVE_OPERATION, "Skill $codename added");
    return (JuryListResponse($Dictionnary["SkillAdded"] ?? "Compétence ajoutée"));
}

function EditCertificationSkill($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = fetch_certification_skill($id))->is_error())
        return ($ret);
    $skill = $ret->value;
    $data = JuryCleanData($data);
    $fields = [];
    foreach (["fr_description", "en_description"] as $field)
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    if (count($fields))
        if (db_update_one("skill", (int)$skill["id"], $fields) === NULL)
            return (new ErrorResponse("CannotEdit"));
    foreach (db_select_all("id_title FROM title_skill WHERE id_skill = ".((int)$skill["id"])) as $row)
        CertificationRefreshUsersForTitle($row["id_title"]);
    add_log(EDITING_OPERATION, "Skill {$skill["codename"]} edited");
    return (JuryListResponse($Dictionnary["SkillModified"] ?? "Compétence modifiée"));
}


function TitleSessionResolve($id)
{
    $session = fetch_title_session_basic((int)$id);
    if ($session == NULL)
        return (new ErrorResponse("TitleSessionNotFound"));
    return (new ValueResponse($session));
}

function TitleSessionNormalizeTime($value, $default = NULL)
{
    $value = trim((string)$value);
    if ($value == "")
        return ($default);
    if (preg_match('/^[0-9]{2}:[0-9]{2}$/D', $value))
        $value .= ":00";
    if (!preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value))
        return (NULL);
    $parts = array_map('intval', explode(':', $value));
    if ($parts[0] > 23 || $parts[1] > 59 || $parts[2] > 59)
        return (NULL);
    return ($value);
}

function TitleSessionValidatedData($data, $existing = NULL)
{
    $data = JuryCleanData($data);
    $out = [];
    foreach (["id_school", "id_title", "id_session_manager"] as $field)
        if (array_key_exists($field, $data))
            $out[$field] = trim((string)$data[$field]) == "" ? NULL : (int)$data[$field];
    foreach (["start_date", "end_date"] as $field)
        if (array_key_exists($field, $data))
        {
            if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data[$field]))
                return (new ErrorResponse("InvalidParameter", $field));
            $out[$field] = $data[$field];
        }
    foreach (["start_time", "end_time"] as $field)
        if (array_key_exists($field, $data))
        {
            $time = TitleSessionNormalizeTime($data[$field], NULL);
            if ($time === NULL && trim((string)$data[$field]) != "")
                return (new ErrorResponse("InvalidParameter", $field));
            $out[$field] = $time;
        }
    if (array_key_exists("jury_arrival_time", $data))
    {
        $time = TitleSessionNormalizeTime($data["jury_arrival_time"], "08:30:00");
        if ($time === NULL)
            return (new ErrorResponse("InvalidParameter", "jury_arrival_time"));
        $out["jury_arrival_time"] = $time;
    }

    $merged = is_array($existing) ? array_replace($existing, $out) : $out;
    if (!isset($merged["id_school"]) || (int)$merged["id_school"] <= 0 ||
        db_select_one("id FROM school WHERE id = ".((int)$merged["id_school"])." AND deleted IS NULL") == NULL)
        return (new ErrorResponse("InvalidParameter", "school"));
    if (!isset($merged["id_title"]) || (int)$merged["id_title"] <= 0 ||
        db_select_one("id FROM `title` WHERE id = ".((int)$merged["id_title"])." AND deleted IS NULL") == NULL)
        return (new ErrorResponse("InvalidParameter", "title"));
    if (!isset($merged["start_date"], $merged["end_date"]) || $merged["start_date"] > $merged["end_date"])
        return (new ErrorResponse("InvalidTitleSessionPeriod"));
    if (isset($merged["start_time"], $merged["end_time"]) && $merged["start_time"] !== NULL && $merged["end_time"] !== NULL && $merged["start_time"] >= $merged["end_time"])
        return (new ErrorResponse("InvalidTitleSessionHours"));
    if (!isset($merged["jury_arrival_time"]) || trim((string)$merged["jury_arrival_time"]) == "")
        $out["jury_arrival_time"] = "08:30:00";

    if (isset($merged["id_session_manager"]) && $merged["id_session_manager"] !== NULL && (int)$merged["id_session_manager"] > 0)
    {
        $manager = db_select_one("
            user.id
            FROM user_school
            LEFT JOIN user ON user.id = user_school.id_user
            WHERE user_school.id_school = ".((int)$merged["id_school"])."
              AND user_school.id_user = ".((int)$merged["id_session_manager"])."
              AND user.id IS NOT NULL
              AND user.deleted IS NULL
              AND user.profile_status != 'jury'
              AND user_school.authority != 'STUDENT'
        ");
        if ($manager == NULL)
            return (new ErrorResponse("InvalidTitleSessionManager"));
    }
    return (new ValueResponse($out));
}

function TitleSessionCalendarSessionsFitPeriod($id_title_session, $start_date, $end_date)
{
    $id_title_session = (int)$id_title_session;
    $start_date = db_escape($start_date);
    $end_date = db_escape($end_date);
    return (db_select_one("
        title_session_session.id
        FROM title_session_session
        LEFT JOIN session ON session.id = title_session_session.id_session
        WHERE title_session_session.id_title_session = $id_title_session
          AND session.id IS NOT NULL
          AND session.deleted IS NULL
          AND (DATE(session.begin_date) < '$start_date' OR DATE(session.end_date) > '$end_date')
    ") == NULL);
}

function AddTitleSession($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id != -1)
        bad_request();
    if (($validated = TitleSessionValidatedData($data))->is_error())
        return ($validated);
    $v = $validated->value;
    $manager = isset($v["id_session_manager"]) && $v["id_session_manager"] !== NULL ? (int)$v["id_session_manager"] : "NULL";
    $start_time = isset($v["start_time"]) && $v["start_time"] !== NULL ? "'".db_escape($v["start_time"])."'" : "NULL";
    $end_time = isset($v["end_time"]) && $v["end_time"] !== NULL ? "'".db_escape($v["end_time"])."'" : "NULL";
    $arrival = db_escape($v["jury_arrival_time"] ?? "08:30:00");
    if ($Database->query("
        INSERT INTO title_session
            (id_school, id_title, start_date, end_date, start_time, end_time, jury_arrival_time, id_session_manager)
        VALUES
            (".((int)$v["id_school"]).", ".((int)$v["id_title"]).", '".db_escape($v["start_date"])."', '".db_escape($v["end_date"])."', $start_time, $end_time, '$arrival', $manager)
    ") == false)
        return (new ErrorResponse("CannotAdd"));
    $new_id = (int)$Database->insert_id;
    add_log(CREATIVE_OPERATION, "Title session #$new_id added", $new_id);
    return (JuryListResponse($Dictionnary["TitleSessionAdded"] ?? "Session de titre ajoutée"));
}

function EditTitleSession($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = TitleSessionResolve($id))->is_error())
        return ($ret);
    $session = $ret->value;
    if (($validated = TitleSessionValidatedData($data, $session))->is_error())
        return ($validated);
    $future = array_replace($session, $validated->value);
    if (!TitleSessionCalendarSessionsFitPeriod((int)$session["id"], $future["start_date"], $future["end_date"]))
        return (new ErrorResponse("SessionOutsideTitleSessionPeriod"));
    if (count($validated->value) && db_update_one("title_session", (int)$session["id"], $validated->value) === NULL)
        return (new ErrorResponse("CannotEdit"));
    add_log(EDITING_OPERATION, "Title session #".$session["id"]." edited", (int)$session["id"]);
    return (JuryListResponse($Dictionnary["TitleSessionModified"] ?? "Session de titre modifiée"));
}

function DeleteTitleSession($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = TitleSessionResolve($id))->is_error())
        return ($ret);
    if (db_update_one("title_session", (int)$ret->value["id"], ["deleted" => db_form_date(now())]) === NULL)
        return (new ErrorResponse("CannotEdit"));
    add_log(DESTRUCTIVE_OPERATION, "Title session #".$ret->value["id"]." archived", (int)$ret->value["id"]);
    return (JuryListResponse($Dictionnary["TitleSessionArchived"] ?? "Session de titre archivée"));
}

function AddTitleSessionCalendarSession($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if (($ret = TitleSessionResolve($id))->is_error())
        return ($ret);
    $title_session = $ret->value;
    $id_title_session = (int)$title_session["id"];
    $id_session = isset($data["session"]) ? (int)$data["session"] : 0;
    $calendar_session = $id_session > 0 ? db_select_one("id, begin_date, end_date FROM session WHERE id = $id_session AND deleted IS NULL") : NULL;
    if ($calendar_session == NULL)
        return (new ErrorResponse("InvalidParameter", "session"));
    if (($calendar_session["begin_date"] ?? NULL) == NULL || ($calendar_session["end_date"] ?? NULL) == NULL ||
        substr($calendar_session["begin_date"], 0, 10) < $title_session["start_date"] ||
        substr($calendar_session["end_date"], 0, 10) > $title_session["end_date"])
        return (new ErrorResponse("SessionOutsideTitleSessionPeriod"));
    $linked = db_select_one("id_title_session FROM title_session_session WHERE id_session = $id_session");
    if ($linked != NULL && (int)$linked["id_title_session"] != $id_title_session)
        return (new ErrorResponse("SessionAlreadyLinkedToTitleSession"));
    if ($linked == NULL && $Database->query("
        INSERT INTO title_session_session (id_title_session, id_session)
        VALUES ($id_title_session, $id_session)
    ") == false)
        return (new ErrorResponse("CannotEdit"));

    // Compatibility with the former model where jury members were stored as
    // session_teacher rows. When a calendar session enters a title session,
    // preserve qualified historical assignments by promoting them to the new
    // title-session-wide relation. The old rows are intentionally left intact.
    foreach (db_select_all("
        user.id
        FROM session_teacher
        LEFT JOIN user ON user.id = session_teacher.id_user
        WHERE session_teacher.id_session = $id_session
          AND user.id IS NOT NULL
          AND user.profile_status = 'jury'
          AND user.deleted IS NULL
    ") as $legacy_jury)
    {
        $id_jury = (int)$legacy_jury["id"];
        if (jury_can_certify_title($id_jury, (int)$title_session["id_title"]))
            $Database->query("
                INSERT IGNORE INTO title_session_jury (id_title_session, id_user)
                VALUES ($id_title_session, $id_jury)
            ");
    }

    add_log(CREATIVE_OPERATION, "Session #$id_session linked to title session #$id_title_session", $id_title_session);
    return (JuryListResponse($Dictionnary["SessionAddedToTitleSession"] ?? "Session ajoutée à la session de titre"));
}

function RemoveTitleSessionCalendarSession($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if (($ret = TitleSessionResolve($id))->is_error())
        return ($ret);
    $id_title_session = (int)$ret->value["id"];
    $id_session = isset($data["session"]) ? (int)$data["session"] : 0;
    if ($id_session <= 0)
        bad_request();
    if ($Database->query("DELETE FROM title_session_session WHERE id_title_session = $id_title_session AND id_session = $id_session") == false)
        return (new ErrorResponse("CannotEdit"));
    add_log(DESTRUCTIVE_OPERATION, "Session #$id_session unlinked from title session #$id_title_session", $id_title_session);
    return (JuryListResponse($Dictionnary["SessionRemovedFromTitleSession"] ?? "Session retirée de la session de titre"));
}

function SetTitleSessionJury($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if (($ret = TitleSessionResolve($id))->is_error())
        return ($ret);
    $session = $ret->value;
    $jury = trim((string)($data["jury"] ?? ""));
    if ($jury == "")
        bad_request();
    if (($resolved = resolve_codename("user", $jury, "codename", true))->is_error())
        return ($resolved);
    $user = $resolved->value;
    if (($user["profile_status"] ?? "") != "jury")
        return (new ErrorResponse("UserNotFound"));
    $id_user = (int)$user["id"];
    if (!jury_can_certify_title($id_user, (int)$session["id_title"]))
        return (new ErrorResponse("JuryNotQualifiedForTitle"));
    $id_title_session = (int)$session["id"];

    if ($method == "DELETE")
    {
        if ($Database->query("DELETE FROM title_session_jury WHERE id_title_session = $id_title_session AND id_user = $id_user") == false)
            return (new ErrorResponse("CannotEdit"));
        add_log(DESTRUCTIVE_OPERATION, "Jury #$id_user removed from title session #$id_title_session", $id_user);
        return (JuryListResponse($Dictionnary["JuryRemovedFromTitleSession"] ?? "Jury retiré de la session de titre"));
    }
    if ($Database->query("
        INSERT IGNORE INTO title_session_jury (id_title_session, id_user)
        VALUES ($id_title_session, $id_user)
    ") == false)
        return (new ErrorResponse("CannotEdit"));
    add_log(CREATIVE_OPERATION, "Jury #$id_user added to title session #$id_title_session", $id_user);
    return (JuryListResponse($Dictionnary["JuryAddedToTitleSession"] ?? "Jury ajouté à la session de titre"));
}

$Tab = [
    "GET" => [
        "" => [
            "JuryCanManage",
            "DisplayJury",
        ],
    ],
    "POST" => [
        "" => [
            "JuryCanManage",
            "AddJury",
        ],
        "title" => [
            "JuryCanManage",
            "AddJuryTitle",
        ],
        "skill" => [
            "JuryCanManage",
            "AddCertificationSkill",
        ],
        "title-session" => [
            "JuryCanManage",
            "AddTitleSession",
        ],
        "title-session-session" => [
            "JuryCanManage",
            "AddTitleSessionCalendarSession",
        ],
        "title-session-jury" => [
            "JuryCanManage",
            "SetTitleSessionJury",
        ],
    ],
    "PUT" => [
        "" => [
            "JuryCanManage",
            "EditJury",
        ],
        "restore" => [
            "JuryCanManage",
            "RestoreJury",
        ],
        "title" => [
            "JuryCanManage",
            "EditJuryTitle",
        ],
        "skill" => [
            "JuryCanManage",
            "EditCertificationSkill",
        ],
        "title-session" => [
            "JuryCanManage",
            "EditTitleSession",
        ],
    ],
    "DELETE" => [
        "" => [
            "JuryCanManage",
            "DeleteJury",
        ],
        "title" => [
            "JuryCanManage",
            "DeleteJuryTitle",
        ],
        "title-session" => [
            "JuryCanManage",
            "DeleteTitleSession",
        ],
        "title-session-session" => [
            "JuryCanManage",
            "RemoveTitleSessionCalendarSession",
        ],
        "title-session-jury" => [
            "JuryCanManage",
            "SetTitleSessionJury",
        ],
    ],
];
